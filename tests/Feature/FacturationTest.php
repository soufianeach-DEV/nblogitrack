<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Facturier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FacturationTest extends TestCase
{
    use RefreshDatabase;

    private function livree(Client $client, string $quand, float $prix = 500): TransportOrder
    {
        return TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => $quand,
            'estimated_cost' => $prix,
        ]);
    }

    public function test_une_facture_regroupe_le_mois_d_un_client(): void
    {
        $client = Client::factory()->create();

        $this->livree($client, '2026-03-04', 300);
        $this->livree($client, '2026-03-18', 700);

        $factures = app(Facturier::class)->facturer(Carbon::parse('2026-03-01'));

        $this->assertCount(1, $factures);
        $this->assertSame('1000.00', $factures->first()->amount_excl_tax);
        $this->assertCount(2, $factures->first()->lines);
    }

    public function test_deux_clients_font_deux_factures(): void
    {
        $this->livree(Client::factory()->create(), '2026-03-04');
        $this->livree(Client::factory()->create(), '2026-03-05');

        $this->assertCount(2, app(Facturier::class)->facturer(Carbon::parse('2026-03-01')));
    }

    public function test_deux_mois_font_deux_factures(): void
    {
        $client = Client::factory()->create();

        $this->livree($client, '2026-03-04');
        $this->livree($client, '2026-04-04');

        $this->assertCount(2, app(Facturier::class)->facturer());
    }

    public function test_seules_les_livraisons_sont_facturees(): void
    {
        $client = Client::factory()->create();

        TransportOrder::factory()->create(['client_id' => $client->id]);
        TransportOrder::factory()->enRoute()->create(['client_id' => $client->id]);

        $this->assertCount(0, app(Facturier::class)->facturer());
        $this->assertSame(0, Invoice::count());
    }

    public function test_relancer_la_facturation_ne_refacture_rien(): void
    {
        $client = Client::factory()->create();
        $this->livree($client, '2026-03-04');

        app(Facturier::class)->facturer();
        app(Facturier::class)->facturer();

        $this->assertSame(1, Invoice::count());
    }

    public function test_un_client_etranger_est_en_autoliquidation(): void
    {
        $client = Client::factory()->create(['country' => 'France']);
        $this->livree($client, '2026-03-04', 1000);

        $facture = app(Facturier::class)->facturer()->first();

        $this->assertTrue($facture->reverse_charge);
        $this->assertSame('0.00', $facture->vat_amount);
        $this->assertSame('1000.00', $facture->amount_incl_tax);
    }

    public function test_un_client_belge_paie_la_tva(): void
    {
        $client = Client::factory()->create(['country' => 'Belgique']);
        $this->livree($client, '2026-03-04', 1000);

        $facture = app(Facturier::class)->facturer()->first();

        $this->assertFalse($facture->reverse_charge);
        $this->assertSame('210.00', $facture->vat_amount);
        $this->assertSame('1210.00', $facture->amount_incl_tax);
    }

    public function test_la_numerotation_repart_a_un_chaque_annee(): void
    {
        $client = Client::factory()->create();

        $this->livree($client, '2025-03-04');
        $this->livree($client, '2026-03-04');

        $references = app(Facturier::class)->facturer()->pluck('reference')->sort()->values();

        $this->assertSame('FAC-2025-0001', $references[0]);
        $this->assertSame('FAC-2026-0001', $references[1]);
    }

    public function test_la_facture_suivante_prend_le_rang_suivant(): void
    {
        $client = Client::factory()->create();

        $this->livree($client, '2026-03-04');
        app(Facturier::class)->facturer();

        $this->livree($client, '2026-04-04');
        $seconde = app(Facturier::class)->facturer()->first();

        $this->assertSame('FAC-2026-0002', $seconde->reference);
    }

    public function test_la_communication_structuree_porte_sa_cle(): void
    {
        $client = Client::factory()->create();
        $this->livree($client, '2026-03-04');

        $reference = app(Facturier::class)->facturer()->first()->payment_reference;

        $this->assertMatchesRegularExpression('/^\+\+\+\d{3}\/\d{4}\/\d{5}\+\+\+$/', $reference);

        $chiffres = preg_replace('/\D/', '', $reference);
        $controle = (int) substr($chiffres, -2);
        $attendu = ((int) substr($chiffres, 0, 10)) % 97;

        $this->assertSame($attendu === 0 ? 97 : $attendu, $controle);
    }

    public function test_l_echeance_suit_le_delai_convenu(): void
    {
        foreach (['30 jours' => 30, '45 jours' => 45, '60 jours' => 60] as $delai => $jours) {
            $client = Client::factory()->create(['payment_terms' => $delai]);
            $this->livree($client, '2026-03-04');

            $facture = app(Facturier::class)->facturer()->last();

            $this->assertSame($jours, (int) $facture->issued_on->diffInDays($facture->due_on),
                'Le delai « '.$delai.' » ne donne pas '.$jours.' jours.');
        }
    }

    public function test_sans_delai_convenu_l_echeance_est_a_trente_jours(): void
    {
        $client = Client::factory()->create(['payment_terms' => null]);
        $this->livree($client, '2026-03-04');

        $facture = app(Facturier::class)->facturer()->first();

        $this->assertSame(30, (int) $facture->issued_on->diffInDays($facture->due_on));
    }

    public function test_une_livraison_facturee_non_reglee_attend_son_paiement(): void
    {
        $client = Client::factory()->create();
        $ordre = $this->livree($client, '2026-03-04');

        $this->assertFalse($ordre->estEnAttenteDePaiement());

        app(Facturier::class)->facturer();

        $this->assertTrue($ordre->fresh()->estEnAttenteDePaiement());
    }

    public function test_une_livraison_reglee_n_attend_plus_rien(): void
    {
        $client = Client::factory()->create();
        $ordre = $this->livree($client, '2026-03-04');

        app(Facturier::class)->facturer()->first()->update(['status' => 'PAID']);

        $this->assertFalse($ordre->fresh()->estEnAttenteDePaiement());
    }

    public function test_l_etat_deduit_arrive_jusqu_a_la_liste(): void
    {
        $client = Client::factory()->create();
        $this->livree($client, '2026-03-04');

        app(Facturier::class)->facturer();

        $reponse = $this->actingAs(User::find($client->id))
            ->get(route('transport-orders.index'));

        $reponse->assertOk();

        $ligne = $reponse->viewData('page')['props']['orders']['data'][0];

        $this->assertArrayHasKey('en_attente_de_paiement', $ligne);
        $this->assertTrue($ligne['en_attente_de_paiement']);
    }

    public function test_la_commande_emet_les_factures(): void
    {
        $client = Client::factory()->create();
        $this->livree($client, now()->subMonth()->startOfMonth()->addDays(3)->toDateString());

        $this->artisan('factures:generer')->assertSuccessful();

        $this->assertSame(1, Invoice::count());
    }

    public function test_la_commande_en_essai_n_ecrit_rien(): void
    {
        $client = Client::factory()->create();
        $this->livree($client, now()->subMonth()->startOfMonth()->addDays(3)->toDateString());

        $this->artisan('factures:generer --essai')->assertSuccessful();

        $this->assertSame(0, Invoice::count());
    }
}
