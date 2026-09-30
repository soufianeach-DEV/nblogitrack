<?php

namespace Tests\Feature;

use App\Mail\OrdreLivre;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Le client apprend la livraison, confirmee par le chauffeur ou par le planificateur. */
class AvisLivraisonTest extends TestCase
{
    use RefreshDatabase;

    private User $commanditaire;

    private Driver $chauffeur;

    private TransportOrder $ordre;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $client = Client::factory()->create();
        $this->commanditaire = User::factory()->create(['client_id' => $client->id, 'company_role' => 'ORDERS']);
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
        ]);
        ActivityLog::record('order.created', 'Création', $this->ordre, [], $this->commanditaire->id);
    }

    public function test_la_livraison_confirmee_par_le_chauffeur_previent_le_client(): void
    {
        $this->actingAs($this->chauffeur->user)
            ->patch(route('missions.status', $this->ordre), [
                'statut' => 'DELIVERED',
                'receptionnaire' => 'Marc Dupont',
                'reserves' => 'Un carton enfoncé',
            ])
            ->assertSessionHas('success');

        Mail::assertSent(OrdreLivre::class, 1);
        Mail::assertSent(OrdreLivre::class, fn (OrdreLivre $m) => $m->hasTo($this->commanditaire->email) && $m->ordre->is($this->ordre));

        $html = Mail::sent(OrdreLivre::class)->first()->render();
        $this->assertStringContainsString('Marc Dupont', $html);
        $this->assertStringContainsString('Un carton enfoncé', $html);
        $this->assertStringContainsString($this->ordre->tracking_number, $html);
    }

    public function test_la_livraison_enregistree_par_le_planificateur_previent_aussi_le_client(): void
    {
        $this->actingAs(User::factory()->planificateur()->create())
            ->patch(route('planning.status', $this->ordre), ['status' => 'DELIVERED'])
            ->assertSessionHas('success');

        Mail::assertSent(OrdreLivre::class, fn (OrdreLivre $m) => $m->hasTo($this->commanditaire->email));
    }

    public function test_un_refus_de_livrer_n_envoie_rien(): void
    {
        $this->actingAs($this->chauffeur->user)->post(route('missions.incident', $this->ordre), [
            'type' => 'PANNE', 'marchandise_endommagee' => false, 'commentaire' => 'Panne moteur',
        ]);

        $this->actingAs($this->chauffeur->user)
            ->patch(route('missions.status', $this->ordre), ['statut' => 'DELIVERED'])
            ->assertSessionHas('error');

        Mail::assertNotSent(OrdreLivre::class);
    }
}
