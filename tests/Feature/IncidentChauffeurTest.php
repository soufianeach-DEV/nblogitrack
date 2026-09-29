<?php

namespace Tests\Feature;

use App\Mail\IncidentExpedition;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Le chauffeur signale un accident, une panne ou une marchandise
 * endommagee : le planificateur le voit, le client est prevenu, la
 * mission garde son etat.
 */
class IncidentChauffeurTest extends TestCase
{
    use RefreshDatabase;

    private function chauffeur(): Driver
    {
        $utilisateur = User::factory()->chauffeur()->create();

        return Driver::create([
            'cpc_expiry' => now()->addYears(2)->toDateString(),
            'tacho_card_expiry' => now()->addYears(2)->toDateString(),
            'user_id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
        ]);
    }

    public function test_le_chauffeur_signale_un_accident_et_le_client_est_prevenu(): void
    {
        Mail::fake();
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->enRoute()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs($chauffeur->user)
            ->post(route('missions.incident', $ordre), [
                'type' => 'ACCIDENT',
                'commentaire' => 'Accrochage sur l\'E40, camion immobilisé',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // La mission garde son etat : le planificateur decide de la suite.
        $this->assertSame('IN_PROGRESS', $ordre->fresh()->status);

        $ligne = ActivityLog::where('action', 'order.incident')->sole();
        $this->assertSame((string) $ordre->id, $ligne->subject_id);
        $this->assertSame('ACCIDENT', $ligne->properties['type']);
        $this->assertSame('Accrochage sur l\'E40, camion immobilisé', $ligne->properties['commentaire']);

        Mail::assertSent(IncidentExpedition::class, fn (IncidentExpedition $m) => $m->hasTo($ordre->client->compte()->email));
    }

    public function test_le_planificateur_voit_l_incident_sur_la_mission_et_dans_le_suivi(): void
    {
        Mail::fake();
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->enRoute()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs($chauffeur->user)->post(route('missions.incident', $ordre), [
            'type' => 'DOMMAGE',
            'commentaire' => 'Palette renversée, 3 cartons écrasés',
        ]);

        $planificateur = User::factory()->planificateur()->create();

        $this->actingAs($planificateur)
            ->get(route('planning.index', ['status' => 'IN_PROGRESS']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orders.data.0.incidents.0.type', 'DOMMAGE')
                ->where('orders.data.0.incidents.0.commentaire', 'Palette renversée, 3 cartons écrasés'));

        $this->actingAs($chauffeur->user)
            ->get(route('missions.index', ['mission' => $ordre->tracking_number]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('mission.incidents.0.libelle', 'Marchandise endommagée'));
    }

    public function test_un_autre_chauffeur_ne_peut_pas_signaler_sur_la_mission(): void
    {
        Mail::fake();
        $ordre = TransportOrder::factory()->enRoute()->create(['driver_id' => $this->chauffeur()->id]);

        $this->actingAs($this->chauffeur()->user)
            ->post(route('missions.incident', $ordre), ['type' => 'PANNE', 'commentaire' => 'Pneu crevé'])
            ->assertRedirect(route('missions.index'));

        $this->assertDatabaseMissing('activity_logs', ['action' => 'order.incident']);
        Mail::assertNothingSent();
    }

    public function test_une_mission_livree_ne_recoit_plus_d_incident(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->livree()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs($chauffeur->user)
            ->post(route('missions.incident', $ordre), ['type' => 'PANNE', 'commentaire' => 'Pneu crevé'])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('activity_logs', ['action' => 'order.incident']);
    }

    public function test_le_signalement_demande_un_commentaire_et_un_type_connu(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->enRoute()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs($chauffeur->user)
            ->post(route('missions.incident', $ordre), ['type' => 'VOL', 'commentaire' => ''])
            ->assertSessionHasErrors(['type', 'commentaire']);
    }

    public function test_le_courriel_au_client_ne_montre_pas_le_detail_interne(): void
    {
        $ordre = TransportOrder::factory()->enRoute()->create();
        $html = (new IncidentExpedition($ordre, $ordre->client->compte()))->render();

        $this->assertStringContainsString($ordre->tracking_number, $html);
        $this->assertStringContainsString('Un incident est survenu pendant le transport', $html);
    }
}
