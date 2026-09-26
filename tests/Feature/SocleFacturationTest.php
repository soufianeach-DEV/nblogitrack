<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\OrderCharge;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\EnvoiPeppol;
use App\Support\FactureUbl;
use App\Support\Facturier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Le socle de facturation : acheteur fige, categories de TVA, paiements
 * partiels, avoirs et supplements.
 */
class SocleFacturationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-04-10 10:00');
    }

    private function livree(Client $client, float $prix = 1000, string $quand = '2026-03-12'): TransportOrder
    {
        return TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => $quand,
            'estimated_cost' => $prix,
        ]);
    }

    private function facturer(): Invoice
    {
        return app(Facturier::class)->facturer()->first();
    }

    private function admin(): User
    {
        return User::factory()->administrateur()->create();
    }

    public function test_la_facture_fige_l_identite_de_l_acheteur(): void
    {
        $client = Client::factory()->create(['company_name' => 'Ancien Nom SA', 'city' => 'Gand']);
        $this->livree($client);
        $facture = $this->facturer();

        $client->update(['company_name' => 'Nouveau Nom SA', 'city' => 'Liège']);

        $facture->refresh();
        $this->assertSame('Ancien Nom SA', $facture->buyer_name);
        $this->assertSame('Gand', $facture->buyer_city);
        $this->assertSame('BE', $facture->buyer_country);
        $this->assertStringContainsString('Ancien Nom SA', FactureUbl::pour($facture->load('lines')));
    }

    /** Regles Peppol belges : le numero BCE figure dans l'entite legale. */
    public function test_le_xml_porte_le_numero_bce_des_societes_belges(): void
    {
        $client = Client::factory()->create(['vat_number' => 'BE0417497106']);
        $this->livree($client);

        $xml = FactureUbl::pour($this->facturer()->load('lines'));

        $this->assertStringContainsString('<cbc:CompanyID schemeID="0208">0123456749</cbc:CompanyID>', $xml);
        $this->assertStringContainsString('<cbc:CompanyID schemeID="0208">0417497106</cbc:CompanyID>', $xml);
    }

    /** Un point d'acces configure recoit le XML ; sans lui, rien ne part. */
    public function test_la_facture_est_remise_au_point_d_acces_peppol(): void
    {
        Mail::fake();
        Http::fake(['peppol.exemple/*' => Http::response(['id' => 'x'], 202)]);
        $this->livree(Client::factory()->create());
        $facture = $this->facturer();

        $this->assertNull(EnvoiPeppol::envoyer($facture));
        Http::assertNothingSent();

        config(['services.peppol.url' => 'https://peppol.exemple/factures', 'services.peppol.cle' => 'essai']);
        $this->assertTrue(EnvoiPeppol::envoyer($facture));

        Http::assertSent(fn ($r) => $r->url() === 'https://peppol.exemple/factures'
            && $r->hasHeader('Authorization', 'Bearer essai')
            && str_contains($r->body(), $facture->reference));
    }

    public function test_la_categorie_de_tva_suit_le_pays_du_preneur(): void
    {
        $belge = Client::factory()->create();
        $francais = Client::factory()->create(['country' => 'France', 'vat_number' => 'FR12345678901']);
        $suisse = Client::factory()->create(['country' => 'Suisse', 'vat_number' => 'CHE123456789']);

        foreach ([$belge, $francais, $suisse] as $client) {
            $this->livree($client);
        }

        $factures = app(Facturier::class)->facturer()->keyBy('client_id');

        $this->assertSame(['S', 21.0], [$factures[$belge->id]->vat_category, (float) $factures[$belge->id]->vat_rate]);
        $this->assertSame(['AE', 0.0], [$factures[$francais->id]->vat_category, (float) $factures[$francais->id]->vat_rate]);
        $this->assertSame(['O', 0.0], [$factures[$suisse->id]->vat_category, (float) $factures[$suisse->id]->vat_rate]);
        $this->assertSame('AE', $factures[$francais->id]->lines->first()->vat_category);
    }

    public function test_un_paiement_partiel_laisse_le_solde_a_payer(): void
    {
        $client = Client::factory()->create();
        $this->livree($client, 1000);
        $facture = $this->facturer();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('invoices.paid', $facture), ['montant' => 400, 'date' => '2026-04-05', 'methode' => 'TRANSFER'])
            ->assertSessionHasNoErrors();

        $facture->refresh();
        $this->assertSame('SENT', $facture->status);
        $this->assertEqualsWithDelta(810.0, $facture->solde(), 0.001);

        $this->actingAs($admin)
            ->patch(route('invoices.paid', $facture), ['montant' => 900, 'date' => '2026-04-06', 'methode' => 'TRANSFER'])
            ->assertSessionHasErrors('montant');

        $this->actingAs($admin)
            ->patch(route('invoices.paid', $facture), ['montant' => 810, 'date' => '2026-04-06', 'methode' => 'TRANSFER'])
            ->assertSessionHasNoErrors();

        $facture->refresh();
        $this->assertSame('PAID', $facture->status);
        $this->assertSame('2026-04-06', $facture->paid_on->toDateString());
        $this->assertCount(2, $facture->payments);
    }

    public function test_un_avoir_sans_refacturation_annule_la_facture(): void
    {
        $client = Client::factory()->create();
        $ordre = $this->livree($client);
        $facture = $this->facturer();

        $this->actingAs($this->admin())
            ->post(route('invoices.credit', $facture), ['motif' => 'Geste commercial', 'refacturer' => false])
            ->assertSessionHas('success');

        $avoir = Invoice::where('type', Invoice::AVOIR)->first();
        $this->assertSame('CREDITED', $facture->fresh()->status);
        $this->assertSame('AV-2026-0001', $avoir->reference);
        $this->assertSame($facture->id, $avoir->credited_invoice_id);
        $this->assertEquals($facture->amount_incl_tax, $avoir->amount_incl_tax);
        $this->assertFalse($avoir->lines->first()->active);

        // L'expedition reste rattachee a la facture annulee : rien a refacturer.
        $this->assertCount(0, app(Facturier::class)->facturer());
        $this->assertNotNull($ordre->fresh()->invoiceLine);

        $ubl = FactureUbl::pour($avoir->load('lines', 'creditedInvoice'));
        $this->assertStringContainsString('<CreditNote', $ubl);
        $this->assertStringContainsString('<cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>', $ubl);
        $this->assertStringContainsString('<cbc:ID>'.$facture->reference.'</cbc:ID>', $ubl);
    }

    public function test_un_avoir_avec_refacturation_reemet_une_facture_corrigee(): void
    {
        $client = Client::factory()->create(['billing_address' => 'Rue Fausse 1']);
        $ordre = $this->livree($client);
        $facture = $this->facturer();

        $client->update(['billing_address' => 'Rue Juste 2']);

        $this->actingAs($this->admin())
            ->post(route('invoices.credit', $facture), ['motif' => 'Adresse de facturation erronée', 'refacturer' => true])
            ->assertSessionHas('success');

        $nouvelle = Invoice::where('type', Invoice::FACTURE)->where('id', '!=', $facture->id)->first();
        $this->assertNotNull($nouvelle);
        $this->assertSame('Rue Juste 2', $nouvelle->buyer_address);
        $this->assertSame($nouvelle->id, $ordre->fresh()->invoiceLine->invoice_id);
        $this->assertEquals($facture->amount_incl_tax, $nouvelle->amount_incl_tax);
    }

    public function test_une_facture_deja_reglee_en_partie_ne_s_annule_pas_par_avoir(): void
    {
        $client = Client::factory()->create();
        $this->livree($client);
        $facture = $this->facturer();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('invoices.paid', $facture), ['montant' => 100, 'date' => '2026-04-05', 'methode' => 'TRANSFER']);

        $this->actingAs($admin)
            ->post(route('invoices.credit', $facture), ['motif' => 'Erreur de prix', 'refacturer' => true])
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::where('type', Invoice::AVOIR)->count());
    }

    public function test_le_rapport_de_tva_deduit_les_avoirs(): void
    {
        $client = Client::factory()->create();
        $this->livree($client, 1000);
        $facture = $this->facturer();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('invoices.credit', $facture), ['motif' => 'Geste commercial', 'refacturer' => false]);

        $this->actingAs($admin)
            ->get(route('purchases.tva'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('totaux.collectee', fn ($v) => abs((float) $v) < 0.001)->etc());
    }

    public function test_un_supplement_part_sur_la_facture_et_ne_se_retire_plus_ensuite(): void
    {
        $client = Client::factory()->create();
        $ordre = $this->livree($client, 1000);
        $planificateur = User::factory()->planificateur()->create();

        $this->travelTo('2026-03-20 10:00');
        $this->actingAs($planificateur)
            ->post(route('transport-orders.charges.store', $ordre), ['libelle' => 'Attente au quai 2 h', 'montant' => 90])
            ->assertSessionHas('success');
        $this->travelTo('2026-04-10 10:00');

        $facture = $this->facturer();
        $this->assertEqualsWithDelta(1090.0, (float) $facture->amount_excl_tax, 0.001);
        $this->assertSame(['TRANSPORT', 'SURCHARGE'], $facture->lines->pluck('kind')->all());

        $supplement = OrderCharge::first();
        $this->actingAs($planificateur)
            ->delete(route('transport-orders.charges.destroy', [$ordre, $supplement]))
            ->assertSessionHas('error');
        $this->assertNotNull($supplement->fresh());
    }

    public function test_un_supplement_sur_une_annulation_gratuite_est_refuse(): void
    {
        $ordre = TransportOrder::factory()->create(['status' => 'CANCELLED', 'cancelled_at' => now()]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('transport-orders.charges.store', $ordre), ['libelle' => 'Attente', 'montant' => 50])
            ->assertSessionHas('error');

        $this->assertSame(0, OrderCharge::count());
    }

    public function test_un_supplement_d_une_annulation_gratuite_ne_se_facture_pas_et_se_retire(): void
    {
        $client = Client::factory()->create();
        $this->livree($client, 1000);
        // Pose pendant la mission, avant que le client annule sans frais.
        $annulee = TransportOrder::factory()->create([
            'client_id' => $client->id,
            'status' => 'CANCELLED',
            'cancelled_at' => '2026-03-15 09:00',
            'cancellation_fee' => 0,
        ]);
        $this->travelTo('2026-03-14 10:00');
        $supplement = OrderCharge::create(['transport_order_id' => $annulee->id, 'label' => 'Attente au quai', 'amount' => 90]);
        $this->travelTo('2026-04-10 10:00');

        $facture = $this->facturer();
        $this->assertEqualsWithDelta(1000.0, (float) $facture->amount_excl_tax, 0.001);
        $this->assertSame(['TRANSPORT'], $facture->lines->pluck('kind')->all());

        $planificateur = User::factory()->planificateur()->create();
        $this->actingAs($planificateur)
            ->get(route('transport-orders.show', $annulee))
            ->assertInertia(fn ($page) => $page
                ->where('peutAjouterSupplement', false)
                ->where('peutRetirerSupplement', true)
                ->etc());

        $this->actingAs($planificateur)
            ->delete(route('transport-orders.charges.destroy', [$annulee, $supplement]))
            ->assertSessionHas('success');
        $this->assertNull($supplement->fresh());
    }

    public function test_un_supplement_d_une_annulation_indemnisee_se_facture(): void
    {
        $client = Client::factory()->create();
        $annulee = TransportOrder::factory()->create([
            'client_id' => $client->id,
            'status' => 'CANCELLED',
            'cancelled_at' => '2026-03-15 09:00',
            'cancellation_fee' => 150,
        ]);
        $this->travelTo('2026-03-14 10:00');
        OrderCharge::create(['transport_order_id' => $annulee->id, 'label' => 'Attente au quai', 'amount' => 90]);
        $this->travelTo('2026-04-10 10:00');

        $facture = $this->facturer();
        $this->assertEqualsWithDelta(240.0, (float) $facture->amount_excl_tax, 0.001);
        $this->assertSame(['CANCELLATION', 'SURCHARGE'], $facture->lines->pluck('kind')->all());
    }

    public function test_le_client_ne_pose_pas_de_supplement(): void
    {
        $client = Client::factory()->create();
        $ordre = $this->livree($client);

        $this->actingAs($client->compte())
            ->post(route('transport-orders.charges.store', $ordre), ['libelle' => 'Remise', 'montant' => 50])
            ->assertForbidden();
    }
}
