<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Le parcours d'une mission : le planificateur affecte, le chauffeur
 * confirme l'enlevement, puis la livraison.
 */
class PriseEnChargeTest extends TestCase
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

    private function camion(): Vehicle
    {
        return Vehicle::create([
            'registration' => '1-ABC-123',
            'vin' => 'VF1ABCDEF12345678',
            'vehicle_type' => 'Porteur',
            'brand' => 'Volvo',
            'model' => 'FH',
            'capacity_tonnes' => 26,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
        ]);
    }

    public function test_l_affectation_reserve_la_mission_sans_la_demarrer(): void
    {
        $chauffeur = $this->chauffeur();
        $camion = $this->camion();
        $ordre = TransportOrder::factory()->create([
            'status' => 'PENDING',
            'weight' => 1000,
            'distance_km' => 80,
            'pickup_date' => now()->addDay()->setTime(8, 0),
        ]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), [
                'vehicle_registration' => $camion->registration,
                'driver_id' => $chauffeur->id,
            ])
            ->assertSessionHasNoErrors();

        $ordre->refresh();
        $this->assertSame('ASSIGNED', $ordre->status);
        $this->assertNotNull($ordre->assigned_at);
        $this->assertNull($ordre->picked_up_at);
    }

    public function test_le_chauffeur_confirme_l_enlevement_puis_la_livraison(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->affectee()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs($chauffeur->user)
            ->get(route('missions.index', ['mission' => $ordre->tracking_number]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('mission.action.statut', 'IN_PROGRESS'));

        $this->actingAs($chauffeur->user)
            ->patch(route('missions.status', $ordre), ['statut' => 'IN_PROGRESS'])
            ->assertSessionHas('success');

        $ordre->refresh();
        $this->assertSame('IN_PROGRESS', $ordre->status);
        $this->assertNotNull($ordre->picked_up_at);

        $this->actingAs($chauffeur->user)
            ->patch(route('missions.status', $ordre), ['statut' => 'DELIVERED'])
            ->assertSessionHas('success');

        $this->assertSame('DELIVERED', $ordre->refresh()->status);
    }

    public function test_une_mission_non_enlevee_ne_se_livre_pas(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->affectee()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs($chauffeur->user)
            ->patch(route('missions.status', $ordre), ['statut' => 'DELIVERED'])
            ->assertSessionHas('error');

        $this->assertSame('ASSIGNED', $ordre->refresh()->status);
    }

    public function test_le_planificateur_ne_fait_pas_partir_une_mission_affectee(): void
    {
        $ordre = TransportOrder::factory()->affectee()->create();

        $this->actingAs(User::factory()->planificateur()->create())
            ->patch(route('planning.status', $ordre), ['status' => 'IN_PROGRESS'])
            ->assertSessionHas('error');

        $this->assertSame('ASSIGNED', $ordre->refresh()->status);
    }

    public function test_une_mission_affectee_se_desaffecte(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->affectee()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.desaffecter', $ordre), ['motif' => 'Chauffeur malade'])
            ->assertSessionHasNoErrors();

        $ordre->refresh();
        $this->assertSame('PENDING', $ordre->status);
        $this->assertNull($ordre->driver_id);
        $this->assertNull($ordre->assigned_at);
    }

    public function test_le_suivi_distingue_l_affectation_de_l_enlevement(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->affectee()->create(['driver_id' => $chauffeur->id]);
        $client = Client::find($ordre->client_id)->compte();

        $this->actingAs($client)
            ->get(route('tracking.show', ['tracking_number' => $ordre->tracking_number]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('etapes.1.libelle', 'Affectation')
                ->where('etapes.1.fait', true)
                ->where('etapes.2.libelle', 'Enlèvement')
                ->where('etapes.2.fait', false));
    }
}
