<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\OrderCharge;
use App\Models\PurchaseInvoice;
use App\Models\Translation;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Facturier;
use App\Support\Formats;
use App\Support\Traductions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Troisieme parcours de la facturation : factures d'achat, declaration de
 * TVA, paiements, supplements et traductions. Chaque test reproduit un
 * ecran qui acceptait une saisie absurde ou affichait un message illisible.
 */
class FacturationTroisiemeParcoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-26 10:00');
    }

    private function camion(string $immatriculation = '1-ABC-123'): Vehicle
    {
        return Vehicle::create([
            'registration' => $immatriculation,
            'vin' => 'VF1'.str_pad((string) crc32($immatriculation), 14, '0'),
            'vehicle_type' => 'Porteur',
            'brand' => 'Volvo',
            'model' => 'FH',
            'capacity_tonnes' => 12,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
        ]);
    }

    private function achat(array $champs = []): array
    {
        return [
            'supplier_name' => 'Total Energies',
            'reference' => 'TE-2026-0917',
            'category' => 'CARBURANT',
            'vehicle_registration' => '1-ABC-123',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'issued_on' => '2026-09-05',
            'due_on' => '2026-10-05',
            'liters' => 820.5,
            'amount_excl_tax' => 1450,
            'vat_rate' => '21',
            'vat_deductible' => true,
            ...$champs,
        ];
    }

    private function achatEnBase(string $emission): PurchaseInvoice
    {
        return PurchaseInvoice::create([
            'supplier_name' => 'Total Energies',
            'reference' => 'TE-'.$emission,
            'category' => 'CARBURANT',
            'vehicle_registration' => $this->camion()->registration,
            'period_start' => substr($emission, 0, 7).'-01',
            'period_end' => substr($emission, 0, 7).'-28',
            'issued_on' => $emission,
            'due_on' => $emission,
            'amount_excl_tax' => 1000,
            'vat_rate' => 21,
            'vat_amount' => 210,
            'amount_incl_tax' => 1210,
            'vat_deductible' => true,
            'status' => 'TO_PAY',
        ]);
    }

    private function factureEmise(): Invoice
    {
        $this->travelTo('2026-04-10 10:00');

        $client = Client::factory()->create();
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => '2026-03-12',
            'estimated_cost' => 1000,
        ]);

        return app(Facturier::class)->facturer()->first();
    }

    public function test_une_periode_d_achat_a_venir_est_refusee(): void
    {
        $this->camion();
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin)
            ->post(route('purchases.store'), $this->achat(['period_start' => '2027-03-01', 'period_end' => '2027-03-31']))
            ->assertSessionHasErrors(['period_start' => 'La période facturée ne peut pas être dans le futur.']);

        $this->assertSame(0, PurchaseInvoice::count());

        // Le mois en cours a commence : sa facture de carburant est legitime.
        $this->actingAs($admin)
            ->post(route('purchases.store'), $this->achat(['period_start' => '2026-09-01', 'period_end' => '2026-09-30']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, PurchaseInvoice::count());
    }

    public function test_une_erreur_d_achat_nomme_le_champ_dans_la_langue_du_comptable(): void
    {
        $this->camion();

        $this->actingAs(User::factory()->administrateur()->create(['locale' => 'nl']))
            ->post(route('purchases.store', ['langue' => 'nl']), $this->achat(['supplier_name' => '']))
            ->assertSessionHasErrors(['supplier_name' => 'Het veld leverancier is verplicht.']);

        $this->actingAs(User::factory()->administrateur()->create(['locale' => 'en']))
            ->post(route('purchases.store', ['langue' => 'en']), $this->achat(['taxed_km' => 'beaucoup']))
            ->assertSessionHasErrors(['taxed_km' => 'The taxed kilometres field must be a number.']);

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('purchases.store'), $this->achat(['issued_on' => '2026-10-02', 'due_on' => '2026-11-01']))
            ->assertSessionHasErrors(['issued_on' => 'Une facture d\'achat ne peut pas être datée dans le futur.']);
    }

    public function test_fevrier_reste_fevrier_dans_la_tva_un_30_du_mois(): void
    {
        $this->achatEnBase('2026-02-15');
        $this->travelTo('2026-10-30 10:00');

        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('purchases.tva'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Factures/Tva')
                ->where('lignes.0.mois', '2026-02')
                ->where('lignes.0.libelle', 'février 2026')
                ->where('lignes.0.trimestre', 'T1 2026')
                ->etc());
    }

    public function test_septembre_reste_au_troisieme_trimestre_un_31_du_mois(): void
    {
        $this->achatEnBase('2026-09-10');
        $this->travelTo('2026-10-31 10:00');

        // Le 31 septembre n'existe pas : sans correction, la ligne passait
        // en octobre, donc au quatrieme trimestre de la declaration.
        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('purchases.tva'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('lignes.0.mois', '2026-09')
                ->where('lignes.0.libelle', 'septembre 2026')
                ->where('lignes.0.trimestre', 'T3 2026')
                ->etc());
    }

    public function test_un_paiement_anterieur_a_la_facture_cite_la_date_en_clair(): void
    {
        $facture = $this->factureEmise();
        $this->assertSame('2026-04-01', $facture->issued_on->toDateString());

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('invoices.paid', $facture), ['montant' => 100, 'date' => '2026-03-31', 'methode' => 'TRANSFER'])
            ->assertSessionHasErrors('date');

        $message = session('errors')->first('date');
        $this->assertSame('Le paiement ne peut pas être antérieur au 01/04/2026, date d\'émission de la facture.', $message);
        $this->assertStringNotContainsString('2026-04-01', $message);
        $this->assertSame(0, $facture->payments()->count());
    }

    public function test_un_paiement_futur_ne_cite_pas_la_regle(): void
    {
        $facture = $this->factureEmise();

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('invoices.paid', $facture), ['montant' => 100, 'date' => '2026-04-11', 'methode' => 'TRANSFER'])
            ->assertSessionHasErrors(['date' => 'Un paiement ne peut pas être daté dans le futur.']);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('invoices.paid', $facture), ['montant' => 100, 'date' => '2026-04-05'])
            ->assertSessionHasErrors(['methode' => 'Le champ moyen de paiement est obligatoire.']);
    }

    public function test_le_nom_du_champ_de_paiement_suit_la_langue_de_l_interface(): void
    {
        $facture = $this->factureEmise();

        Translation::create(['cle' => 'champ.date_paiement', 'groupe' => 'champ', 'fr' => 'date du paiement', 'nl' => 'betaaldatum', 'en' => 'payment date']);
        Traductions::oublier();

        $this->actingAs(User::factory()->administrateur()->create(['locale' => 'nl']))
            ->patch(route('invoices.paid', ['langue' => 'nl', 'invoice' => $facture]), ['montant' => 100, 'methode' => 'TRANSFER'])
            ->assertSessionHasErrors(['date' => 'Het veld betaaldatum is verplicht.']);
    }

    public function test_le_formulaire_de_traduction_nomme_la_langue(): void
    {
        $traduction = Translation::create(['cle' => 'nav.services', 'groupe' => 'nav', 'fr' => 'Services', 'nl' => 'Diensten', 'en' => 'Services']);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('translations.update', $traduction), ['fr' => '', 'nl' => 'Diensten', 'en' => 'Services'])
            ->assertSessionHasErrors(['fr' => 'Le champ texte en français est obligatoire.']);

        $this->assertSame('Services', $traduction->fresh()->fr);
    }

    public function test_un_supplement_hors_plafond_cite_le_plafond_en_euros(): void
    {
        $ordre = TransportOrder::factory()->livree()->create();
        $planificateur = User::factory()->planificateur()->create();

        $this->actingAs($planificateur)
            ->post(route('transport-orders.charges.store', $ordre), ['libelle' => 'Attente au quai', 'montant' => 150000])
            ->assertSessionHasErrors('montant');

        $message = session('errors')->first('montant');
        $this->assertStringContainsString('Un supplément ne peut pas dépasser', $message);
        $this->assertStringContainsString(Formats::montant(100000), $message);
        $this->assertStringNotContainsString('100000', $message);

        $this->actingAs($planificateur)
            ->post(route('transport-orders.charges.store', $ordre), ['libelle' => 'ab', 'montant' => 50])
            ->assertSessionHasErrors(['libelle' => 'Le champ libellé du supplément doit compter au moins 3 caractères.']);

        $this->assertSame(0, OrderCharge::count());
    }

    public function test_un_supplement_reste_possible_sur_une_annulation_indemnisee(): void
    {
        // Le formulaire ne se masque que sans indemnite : avec une
        // indemnite, l'annulation est facturee et peut porter un supplement.
        $ordre = TransportOrder::factory()->create([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancellation_fee' => 120,
        ]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('transport-orders.charges.store', $ordre), ['libelle' => 'Manutention', 'montant' => 45])
            ->assertSessionHas('success');

        $this->assertSame(1, OrderCharge::count());
    }
}
