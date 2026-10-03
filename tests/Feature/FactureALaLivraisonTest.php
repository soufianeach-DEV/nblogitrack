<?php

namespace Tests\Feature;

use App\Mail\FactureEmise;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\OrderCharge;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Facturier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Une mission livree se facture tout de suite, que la livraison soit
 * confirmee par le chauffeur ou enregistree par le planificateur.
 */
class FactureALaLivraisonTest extends TestCase
{
    use RefreshDatabase;

    private Driver $chauffeur;

    private TransportOrder $ordre;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $client = Client::factory()->create();
        ClientContact::create([
            'client_id' => $client->id,
            'first_name' => 'Nadia',
            'last_name' => 'Peeters',
            'email' => 'compta@exemple.be',
            'is_primary' => true,
        ]);
        $utilisateur = User::factory()->chauffeur()->create();
        $this->chauffeur = Driver::create([
            'user_id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'cpc_expiry' => now()->addYears(2)->toDateString(),
            'tacho_card_expiry' => now()->addYears(2)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
        ]);
        $this->ordre = TransportOrder::factory()->enRoute()->create([
            'client_id' => $client->id,
            'driver_id' => $this->chauffeur->id,
            'estimated_cost' => 500,
        ]);
    }

    private function livrerParLeChauffeur(): void
    {
        $this->actingAs($this->chauffeur->user)
            ->patch(route('missions.status', $this->ordre), [
                'statut' => 'DELIVERED',
                'receptionnaire' => 'Marc Dupont',
            ])
            ->assertSessionHas('success');
    }

    public function test_la_livraison_confirmee_par_le_chauffeur_emet_et_envoie_la_facture(): void
    {
        $this->livrerParLeChauffeur();

        $facture = Invoice::sole();
        $this->assertSame(today()->toDateString(), $facture->issued_on->toDateString());
        $this->assertSame('SENT', $facture->status);
        $this->assertNotNull($facture->fresh()->sent_at);
        $ligne = $facture->lines()->sole();
        $this->assertSame(InvoiceLine::TRANSPORT, $ligne->kind);
        $this->assertSame($this->ordre->id, $ligne->transport_order_id);
        $this->assertEquals(500, $facture->amount_excl_tax);

        Mail::assertSent(FactureEmise::class, fn (FactureEmise $m) => $m->hasTo('compta@exemple.be'));
        $this->assertTrue(ActivityLog::where('action', 'invoices.generated')
            ->where('properties->a_la_livraison', true)->exists());
    }

    public function test_la_livraison_enregistree_par_le_planificateur_emet_aussi_la_facture(): void
    {
        $this->actingAs(User::factory()->planificateur()->create())
            ->patch(route('planning.status', $this->ordre), ['status' => 'DELIVERED'])
            ->assertSessionHas('success');

        $this->assertSame($this->ordre->id, Invoice::sole()->lines()->sole()->transport_order_id);
        Mail::assertSent(FactureEmise::class, 1);
    }

    public function test_les_supplements_deja_poses_partent_sur_la_meme_facture(): void
    {
        OrderCharge::create(['transport_order_id' => $this->ordre->id, 'label' => 'Attente au quai', 'amount' => 45]);

        $this->livrerParLeChauffeur();

        $facture = Invoice::sole();
        $this->assertSame(2, $facture->lines()->count());
        $this->assertEquals(545, $facture->amount_excl_tax);
    }

    public function test_une_mission_facturee_a_la_livraison_ne_l_est_pas_une_seconde_fois(): void
    {
        $this->livrerParLeChauffeur();

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('invoices.now'))
            ->assertSessionHas('error');
        $this->artisan('factures:generer', ['--tout' => true])->assertSuccessful();

        $this->assertSame(1, Invoice::count());
    }

    public function test_une_facture_en_echec_ne_bloque_pas_la_livraison(): void
    {
        $this->mock(Facturier::class, fn ($m) => $m->shouldReceive('aFacturer')->andThrow(new \RuntimeException('base indisponible')));

        $this->livrerParLeChauffeur();

        $this->assertSame('DELIVERED', $this->ordre->fresh()->status);
        $this->assertSame(0, Invoice::count());
        $this->assertTrue(ActivityLog::where('action', 'invoice.auto_failed')->exists());
    }
}
