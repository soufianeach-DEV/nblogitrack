<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Apres un accident ou une panne, la mission attend : le chauffeur reprend
 * la route une fois le camion repare, ou demande un autre vehicule que le
 * planificateur lui affecte. Une marchandise endommagee se livre.
 */
class SuiteIncidentTest extends TestCase
{
    use RefreshDatabase;

    private Driver $chauffeur;

    private TransportOrder $ordre;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $utilisateur = User::factory()->chauffeur()->create();
        $this->chauffeur = Driver::create([
            'cpc_expiry' => now()->addYears(2)->toDateString(),
            'tacho_card_expiry' => now()->addYears(2)->toDateString(),
            'user_id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
        ]);
        $this->camion('1-ABC-123');
        $this->ordre = TransportOrder::factory()->enRoute()->create([
            'driver_id' => $this->chauffeur->id,
            'vehicle_registration' => '1-ABC-123',
        ]);
    }

    private function camion(string $immatriculation): Vehicle
    {
        return Vehicle::create([
            'registration' => $immatriculation,
            'vin' => 'VF1ABCDEF'.substr(md5($immatriculation), 0, 8),
            'vehicle_type' => 'Porteur',
            'brand' => 'Volvo',
            'model' => 'FH',
            'capacity_tonnes' => 26,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
        ]);
    }

    private function signaler(string $type)
    {
        return $this->actingAs($this->chauffeur->user)
            ->post(route('missions.incident', $this->ordre), ['type' => $type, 'marchandise_endommagee' => false, 'commentaire' => 'Panne moteur']);
    }

    private function livrer()
    {
        return $this->actingAs($this->chauffeur->user)
            ->patch(route('missions.status', $this->ordre), ['statut' => 'DELIVERED']);
    }

    private function decider(string $decision)
    {
        return $this->actingAs($this->chauffeur->user)
            ->post(route('missions.incident.suite', $this->ordre), ['decision' => $decision]);
    }

    public function test_une_panne_empeche_de_livrer_jusqu_a_la_reprise(): void
    {
        $this->signaler('PANNE');

        $this->livrer()->assertSessionHas('error');
        $this->assertSame('IN_PROGRESS', $this->ordre->fresh()->status);

        $this->actingAs($this->chauffeur->user)
            ->get(route('missions.index', ['mission' => $this->ordre->tracking_number]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('mission.immobilisation.type', 'PANNE')
                ->where('mission.immobilisation.vehicule_demande', false));

        $this->decider('reprendre')->assertSessionHas('success');
        $this->assertSame(1, ActivityLog::where('action', 'order.incident_resolved')->count());

        $this->livrer()->assertSessionHas('success');
        $this->assertSame('DELIVERED', $this->ordre->fresh()->status);
    }

    public function test_le_chauffeur_demande_un_autre_vehicule_et_le_planificateur_le_voit(): void
    {
        $this->signaler('ACCIDENT');

        $this->decider('vehicule')->assertSessionHas('success');
        $this->decider('vehicule');
        $this->assertSame(1, ActivityLog::where('action', 'order.vehicle_requested')->count());

        // Toujours immobilise : un autre vehicule est attendu.
        $this->livrer()->assertSessionHas('error');

        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('planning.index', ['status' => 'IN_PROGRESS']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orders.data.0.immobilisation.vehicule_demande', true));
    }

    public function test_un_autre_camion_affecte_leve_l_immobilisation(): void
    {
        $this->signaler('PANNE');
        $this->decider('vehicule');

        $this->camion('1-XYZ-999');
        $this->ordre->update(['vehicle_registration' => '1-XYZ-999']);

        $this->livrer()->assertSessionHas('success');
        $this->assertSame('DELIVERED', $this->ordre->fresh()->status);
    }

    public function test_une_marchandise_endommagee_se_livre(): void
    {
        $this->signaler('DOMMAGE');

        $this->livrer()->assertSessionHas('success');
        $this->assertSame('DELIVERED', $this->ordre->fresh()->status);
    }

    public function test_une_nouvelle_panne_apres_la_reprise_bloque_a_nouveau(): void
    {
        $this->signaler('PANNE');
        $this->decider('reprendre');
        $this->signaler('PANNE');

        $this->livrer()->assertSessionHas('error');
    }

    public function test_sans_immobilisation_rien_a_decider(): void
    {
        $this->decider('reprendre')->assertSessionHas('error');
        $this->assertSame(0, ActivityLog::where('action', 'order.incident_resolved')->count());
    }

    public function test_le_planificateur_reaffecte_un_camion_et_le_chauffeur_peut_livrer(): void
    {
        $this->ordre->update(['weight' => 1000, 'volume' => 5, 'is_hazardous' => false]);
        $this->signaler('PANNE');
        $this->decider('vehicule');
        $this->camion('1-XYZ-999');

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $this->ordre), [
                'vehicle_registration' => '1-XYZ-999',
                'driver_id' => $this->chauffeur->id,
                'motif' => 'Panne moteur, camion de remplacement',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('1-XYZ-999', $this->ordre->fresh()->vehicle_registration);
        $this->livrer()->assertSessionHas('success');
    }
}
