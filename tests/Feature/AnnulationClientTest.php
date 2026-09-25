<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Facturier;
use App\Support\Traductions;
use Database\Seeders\TranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Article 8 bis des conditions generales : le client annule lui-meme une
 * expedition pas encore chargee, gratuitement tant qu'aucun camion n'est
 * reserve, contre une indemnite ensuite.
 */
class AnnulationClientTest extends TestCase
{
    use RefreshDatabase;

    private function ordre(string $statut, float $prix = 400): TransportOrder
    {
        return TransportOrder::factory()->create([
            'status' => $statut,
            'estimated_cost' => $prix,
            'assigned_at' => $statut === 'PENDING' ? null : now()->subHour(),
        ]);
    }

    private function client(TransportOrder $ordre): User
    {
        return User::find($ordre->client_id);
    }

    public function test_une_expedition_en_attente_s_annule_sans_frais(): void
    {
        $ordre = $this->ordre('PENDING');

        $this->actingAs($this->client($ordre))
            ->patch(route('transport-orders.cancel', $ordre), ['frais' => 0])
            ->assertSessionHas('success');

        $ordre->refresh();
        $this->assertSame('CANCELLED', $ordre->status);
        $this->assertNull($ordre->cancellation_fee);
        $this->assertNotNull($ordre->cancelled_at);
        $this->assertSame($ordre->client_id, $ordre->cancelled_by);
        $this->assertTrue(ActivityLog::where('action', 'order.cancelled_by_client')->exists());
    }

    public function test_une_expedition_affectee_coute_un_quart_de_son_prix(): void
    {
        $ordre = $this->ordre('ASSIGNED', 400);

        $this->actingAs($this->client($ordre))
            ->patch(route('transport-orders.cancel', $ordre), ['frais' => 100])
            ->assertSessionHas('success');

        $ordre->refresh();
        $this->assertSame('CANCELLED', $ordre->status);
        $this->assertEquals(100, (float) $ordre->cancellation_fee);
    }

    public function test_l_indemnite_a_un_minimum_et_ne_depasse_jamais_le_prix(): void
    {
        $this->assertSame(50.0, $this->ordre('ASSIGNED', 120)->fraisAnnulation());
        $this->assertSame(30.0, $this->ordre('ASSIGNED', 30)->fraisAnnulation());
        $this->assertSame(0.0, $this->ordre('PENDING', 400)->fraisAnnulation());
        $this->assertNull($this->ordre('IN_PROGRESS', 400)->fraisAnnulation());
    }

    public function test_un_montant_qui_a_change_n_est_pas_impose(): void
    {
        // Le client a vu l'annulation gratuite, mais un camion a ete
        // affecte entre-temps : on lui montre le nouveau montant.
        $ordre = $this->ordre('ASSIGNED', 400);

        $this->actingAs($this->client($ordre))
            ->patch(route('transport-orders.cancel', $ordre), ['frais' => 0])
            ->assertSessionHas('error');

        $this->assertSame('ASSIGNED', $ordre->refresh()->status);
    }

    public function test_une_marchandise_chargee_ne_s_annule_plus_en_ligne(): void
    {
        $ordre = $this->ordre('IN_PROGRESS');

        $this->actingAs($this->client($ordre))
            ->patch(route('transport-orders.cancel', $ordre), ['frais' => 0])
            ->assertSessionHas('error');

        $this->assertSame('IN_PROGRESS', $ordre->refresh()->status);
    }

    public function test_seul_le_client_de_l_expedition_l_annule(): void
    {
        $ordre = $this->ordre('PENDING');

        $this->actingAs(User::find(Client::factory()->create()->id))
            ->patch(route('transport-orders.cancel', $ordre), ['frais' => 0])
            ->assertNotFound();

        $this->actingAs(User::factory()->planificateur()->create())
            ->patch(route('transport-orders.cancel', $ordre), ['frais' => 0])
            ->assertNotFound();

        $this->assertSame('PENDING', $ordre->refresh()->status);
    }

    public function test_la_fiche_propose_l_annulation_au_client_seulement(): void
    {
        $ordre = $this->ordre('ASSIGNED', 400);

        $this->actingAs($this->client($ordre))
            ->get(route('transport-orders.show', $ordre))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('annulation.frais', 100)
                ->where('annulation.taux', 25));

        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('transport-orders.show', $ordre))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('annulation', null));
    }

    public function test_l_indemnite_est_facturee_le_mois_de_l_annulation(): void
    {
        $client = Client::factory()->create();
        $mois = now()->subMonth()->startOfMonth()->addDays(5);

        TransportOrder::factory()->create([
            'client_id' => $client->id,
            'status' => 'CANCELLED',
            'cancelled_at' => $mois,
            'cancellation_fee' => 100,
            'estimated_cost' => 400,
        ]);
        TransportOrder::factory()->create([
            'client_id' => $client->id,
            'status' => 'CANCELLED',
            'cancelled_at' => $mois,
            'cancellation_fee' => null,
        ]);
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => $mois->toDateString(),
            'estimated_cost' => 500,
        ]);

        $facture = app(Facturier::class)->facturer()->first();

        $this->assertEquals(600, (float) $facture->amount_excl_tax);
        $this->assertCount(2, $facture->lines);
        $this->assertTrue($facture->lines->contains(
            fn ($ligne) => str_starts_with($ligne->description, 'Indemnité d\'annulation') && (float) $ligne->amount_excl_tax === 100.0
        ));
    }

    public function test_la_ligne_d_indemnite_se_lit_dans_la_langue_du_pdf(): void
    {
        $this->seed(TranslationSeeder::class);
        Traductions::oublier();

        $ordre = TransportOrder::factory()->create([
            'status' => 'CANCELLED',
            'cancelled_at' => now()->subMonth()->startOfMonth()->addDays(5),
            'cancellation_fee' => 100,
        ]);
        $facture = app(Facturier::class)->facturer()->first();

        app()->setLocale('nl');
        $html = view('pdf.facture', ['facture' => $facture->load('client', 'lines.transportOrder'), 'qr' => null])->render();

        $this->assertStringContainsString('Annuleringsvergoeding '.$ordre->tracking_number, $html);
    }
}
