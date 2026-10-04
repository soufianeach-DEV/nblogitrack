<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\OrderCharge;
use App\Models\Payment;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Encours;
use App\Support\Facturier;
use App\Support\JoursFeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Trois factures en retard, ou une commande qui ferait depasser le plafond de
 * credit aux factures dues, bloquent toute nouvelle commande.
 */
class PlafondEncoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('postal_codes')->insert([
            ['country_code' => 'BE', 'code' => '3500', 'city' => 'Hasselt', 'lat' => 50.9311, 'lng' => 5.3378],
            ['country_code' => 'BE', 'code' => '1000', 'city' => 'Bruxelles', 'lat' => 50.8504, 'lng' => 4.3488],
        ]);

        Http::fake([
            'router.project-osrm.org/*' => Http::response(['code' => 'Ok', 'routes' => [['distance' => 84000]]]),
            '*' => Http::response([], 200),
        ]);
    }

    private function deposer(Client $entreprise): TestResponse
    {
        [, $jeton] = ApiKey::generer([
            'name' => 'Essai', 'client_id' => $entreprise->id, 'abilities' => ['lecture', 'ecriture'],
            'created_by' => User::factory()->administrateur()->create()->id,
        ]);

        return $this->postJson('/api/v1/expeditions', [
            'enlevement' => 'Rue Neuve 43, 3500 Hasselt',
            'livraison' => 'Avenue Louise 200, 1000 Bruxelles',
            'poids' => 850,
            'marchandise' => TransportOrder::MARCHANDISES[0],
            'date_enlevement' => JoursFeries::prochainJourOuvrable(now()->addDays(2))->toDateString(),
            'date_livraison' => now()->addDays(12)->toDateString(),
        ], ['Authorization' => 'Bearer '.$jeton]);
    }

    /** Une facture de 500 € HT emise le 10 avril, a 30 jours. */
    private function facturer(Client $entreprise): void
    {
        $this->travelTo('2026-04-10');
        TransportOrder::factory()->livree()->create(['client_id' => $entreprise->id, 'actual_delivery_date' => '2026-03-10', 'estimated_cost' => 500]);
        app(Facturier::class)->facturer();
    }

    public function test_l_encours_ne_compte_que_les_factures_emises_non_reglees(): void
    {
        $entreprise = Client::factory()->create(['credit_limit' => null, 'payment_terms' => '30 jours']);
        $this->facturer($entreprise);
        $enAttente = TransportOrder::factory()->create(['client_id' => $entreprise->id, 'status' => 'PENDING', 'estimated_cost' => 200]);
        OrderCharge::create(['transport_order_id' => $enAttente->id, 'label' => 'Attente', 'amount' => 50]);
        TransportOrder::factory()->create(['client_id' => $entreprise->id, 'status' => 'CANCELLED', 'estimated_cost' => 800, 'cancellation_fee' => 100]);

        // Facture de 500 HT, soit 605 TTC. Les expeditions, supplements et
        // indemnites pas encore factures ne comptent pas.
        $encours = Encours::de($entreprise);
        $this->assertSame(605.0, $encours['factures']);
        $this->assertSame(605.0, $encours['total']);
        $this->assertSame(0, $encours['en_retard']);

        // Un paiement partiel reduit l'encours ; la facture reglee l'annule.
        $facture = Invoice::where('client_id', $entreprise->id)->first();
        Payment::create(['invoice_id' => $facture->id, 'amount' => 100, 'paid_on' => '2026-04-12', 'method' => 'TRANSFER']);
        $this->assertSame(505.0, Encours::de($entreprise)['total']);
        Payment::create(['invoice_id' => $facture->id, 'amount' => 505, 'paid_on' => '2026-04-15', 'method' => 'TRANSFER']);
        $this->assertSame(0.0, Encours::de($entreprise)['total']);
    }

    public function test_la_commande_est_comptee_au_regime_de_tva_de_l_entreprise(): void
    {
        $belge = Client::factory()->create();
        // Preneur etabli en France : autoliquidation, le montant reste HT.
        $francaise = Client::factory()->create(['country' => 'France', 'vat_number' => 'FR40303265045']);

        $this->assertSame(242.0, Encours::ttc($belge, 200));
        $this->assertSame(200.0, Encours::ttc($francaise, 200));
    }

    public function test_le_refus_cite_la_commande_et_le_formulaire_previent_quand_le_plafond_est_atteint(): void
    {
        $entreprise = Client::factory()->create(['credit_limit' => 1210, 'payment_terms' => '30 jours']);
        $this->facturer($entreprise);
        $espaces = fn (?string $m) => str_replace(["\u{202F}", "\u{00A0}"], ' ', (string) $m);

        // 605 TTC dus : 500 HT de plus (605 TTC) tiennent juste, 501 non.
        $this->assertNull(Encours::refus($entreprise, 500));
        $refus = $espaces(Encours::refus($entreprise, 501));
        $this->assertStringContainsString('605,00', $refus);
        $this->assertStringContainsString('606,21', $refus);
        $this->assertNull(Encours::refus($entreprise, 0));

        // Plafond atteint pile, ou plafond de 0 : plus rien ne passe, et le
        // formulaire le dit des l'ouverture.
        $entreprise->update(['credit_limit' => 605]);
        $this->assertNotNull(Encours::refus($entreprise, 0));

        $sansCredit = Client::factory()->create(['credit_limit' => 0, 'is_validated' => true]);
        $client = User::factory()->create(['role' => 'CLIENT', 'client_id' => $sansCredit->id, 'company_role' => 'ADMIN']);
        $this->actingAs($client)->get(route('transport-orders.create', ['langue' => 'fr']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('blocageEncours', fn ($m) => is_string($m) && str_contains($m, 'Plafond')));
    }

    public function test_le_personnel_recoit_un_message_qui_lui_est_adresse(): void
    {
        $entreprise = Client::factory()->create(['company_name' => 'Transports Dubois', 'credit_limit' => 100]);

        $message = Encours::refus($entreprise, 500, pourLePersonnel: true);

        $this->assertStringContainsString('Transports Dubois', $message);
        $this->assertStringContainsString('écran Entreprises', $message);
    }

    public function test_une_facture_en_retard_avertit_trois_bloquent(): void
    {
        TariffGrid::factory()->create();
        $entreprise = Client::factory()->create(['credit_limit' => null, 'payment_terms' => '30 jours']);
        $client = User::factory()->create(['role' => 'CLIENT', 'client_id' => $entreprise->id, 'company_role' => 'ADMIN']);

        // Une facture par mois : janvier, fevrier, mars.
        foreach (['2026-02-05' => '2026-01-10', '2026-03-05' => '2026-02-10', '2026-04-05' => '2026-03-10'] as $jour => $livraison) {
            $this->travelTo($jour);
            TransportOrder::factory()->livree()->create(['client_id' => $entreprise->id, 'actual_delivery_date' => $livraison, 'estimated_cost' => 500]);
            app(Facturier::class)->facturer();
        }

        // Le 20 mars : seule la facture de janvier (echeance 7 mars) est en retard.
        $this->travelTo('2026-03-20');
        $this->assertSame(1, Encours::de($entreprise)['en_retard']);
        $this->assertNull(Encours::refus($entreprise, 0));
        $this->actingAs($client)->get(route('transport-orders.create', ['langue' => 'fr']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('blocageEncours', null)
                ->where('avertissementEncours', fn ($m) => is_string($m) && str_contains($m, '3')));

        // Le 20 mai : les trois sont en retard, les commandes sont bloquees.
        $this->travelTo('2026-05-20');
        $this->assertSame(3, Encours::de($entreprise)['en_retard']);
        $avant = TransportOrder::count();

        $this->deposer($entreprise)->assertStatus(422)->assertJsonPath('motif', 'encours');
        $this->assertSame($avant, TransportOrder::count());

        $this->actingAs($client)->get(route('transport-orders.create', ['langue' => 'fr']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('blocageEncours', fn ($m) => is_string($m) && str_contains($m, 'retard')));
    }

    public function test_le_plafond_bloque_au_dela_et_laisse_passer_en_dessous(): void
    {
        TariffGrid::factory()->create();

        $serree = Client::factory()->create(['credit_limit' => 10]);
        $this->deposer($serree)->assertStatus(422)->assertJsonPath('motif', 'encours');
        $this->assertSame(0, TransportOrder::where('client_id', $serree->id)->count());

        $large = Client::factory()->create(['credit_limit' => 100000]);
        $this->deposer($large)->assertCreated();

        $sansPlafond = Client::factory()->create(['credit_limit' => null]);
        $this->deposer($sansPlafond)->assertCreated();
    }

    public function test_l_admin_regle_delai_et_plafond(): void
    {
        $entreprise = Client::factory()->create(['is_validated' => true, 'credit_limit' => 5000, 'payment_terms' => '30 jours']);
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin)->patch(route('clients.terms', ['langue' => 'fr', 'client' => $entreprise]), [
            'payment_terms' => '60 jours',
            'credit_limit' => '',
        ])->assertSessionHas('success');

        $entreprise->refresh();
        $this->assertSame('60 jours', $entreprise->payment_terms);
        $this->assertNull($entreprise->credit_limit);

        $this->actingAs($admin)->patch(route('clients.terms', ['langue' => 'fr', 'client' => $entreprise]), [
            'payment_terms' => '90 jours',
        ])->assertSessionHasErrors('payment_terms');

        // Enregistrer sans rien changer n'ecrit rien au journal.
        $avant = ActivityLog::where('action', 'client.terms_updated')->count();
        $this->actingAs($admin)->patch(route('clients.terms', ['langue' => 'fr', 'client' => $entreprise]), [
            'payment_terms' => '60 jours', 'credit_limit' => '',
        ])->assertSessionHas('success');
        $this->assertSame($avant, ActivityLog::where('action', 'client.terms_updated')->count());

        $client = User::factory()->create(['role' => 'CLIENT', 'client_id' => $entreprise->id, 'company_role' => 'ADMIN']);
        $this->actingAs($client)->patch(route('clients.terms', ['langue' => 'fr', 'client' => $entreprise]), [
            'payment_terms' => '45 jours', 'credit_limit' => 999999,
        ])->assertForbidden();
    }
}
