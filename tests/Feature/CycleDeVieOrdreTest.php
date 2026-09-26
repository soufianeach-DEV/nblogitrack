<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\OrderWorkflow;
use App\Support\TransitionRefusee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le cycle de vie d'un ordre : les transitions permises, leur atomicite,
 * la reaffectation en route et la preuve de livraison.
 */
class CycleDeVieOrdreTest extends TestCase
{
    use RefreshDatabase;

    private function chauffeur(): Driver
    {
        $utilisateur = User::factory()->chauffeur()->create();

        return Driver::create([
            'user_id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
        ]);
    }

    private function camion(string $immatriculation): Vehicle
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

    public function test_les_statuts_du_modele_sont_ceux_du_cycle_de_vie(): void
    {
        $this->assertSame(OrderStatus::valeurs(), TransportOrder::STATUTS);
        $this->assertSame(OrderStatus::actifs(), TransportOrder::ACTIFS);
    }

    public function test_une_marchandise_chargee_ne_repasse_jamais_en_attente(): void
    {
        $this->assertFalse(OrderStatus::IN_PROGRESS->peutPasserA(OrderStatus::PENDING));
        $this->assertFalse(OrderStatus::IN_PROGRESS->peutPasserA(OrderStatus::ASSIGNED));
        $this->assertSame([], OrderStatus::DELIVERED->suivants());
        $this->assertSame([], OrderStatus::CANCELLED->suivants());
    }

    public function test_une_transition_sur_un_etat_perime_est_refusee(): void
    {
        $ordre = TransportOrder::factory()->affectee()->create();
        $perime = TransportOrder::find($ordre->id);

        // Le chauffeur charge pendant que le client regarde encore l'ordre
        // « affecte » et son indemnite.
        OrderWorkflow::enlever($ordre);

        try {
            OrderWorkflow::annuler($perime, OrderStatus::ASSIGNED, $ordre->client_id, 50);
            $this->fail('L annulation sur un etat perime aurait du etre refusee.');
        } catch (TransitionRefusee) {
        }

        $this->assertSame('IN_PROGRESS', $ordre->fresh()->status);
        $this->assertNull($ordre->fresh()->cancellation_fee);
    }

    public function test_deux_affectations_simultanees_ne_s_ecrasent_pas(): void
    {
        $ordre = TransportOrder::factory()->create(['weight' => 1000, 'distance_km' => 80]);
        $copie = TransportOrder::find($ordre->id);
        $premier = $this->chauffeur();

        OrderWorkflow::affecter($ordre, $this->camion('1-AAA-111'), $premier, now());

        $this->expectException(TransitionRefusee::class);
        OrderWorkflow::affecter($copie, $this->camion('1-BBB-222'), $this->chauffeur(), now());
    }

    public function test_une_mission_en_route_se_reaffecte_sans_revenir_en_attente(): void
    {
        $ancien = $this->chauffeur();
        $ordre = TransportOrder::factory()->enRoute()->create([
            'driver_id' => $ancien->id,
            'vehicle_registration' => $this->camion('1-AAA-111')->registration,
            'weight' => 1000,
            'distance_km' => 80,
            'pickup_date' => now()->subDay(),
        ]);
        $relais = $this->chauffeur();

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), [
                'vehicle_registration' => $this->camion('1-BBB-222')->registration,
                'driver_id' => $relais->id,
                'motif' => 'Panne moteur sur l autoroute',
            ])
            ->assertSessionHasNoErrors();

        $ordre->refresh();
        $this->assertSame('IN_PROGRESS', $ordre->status);
        $this->assertSame('1-BBB-222', $ordre->vehicle_registration);
        $this->assertSame($relais->id, $ordre->driver_id);
        $this->assertNotNull($ordre->picked_up_at);
        $this->assertTrue(ActivityLog::where('action', 'order.reassigned')->exists());
    }

    public function test_une_reaffectation_demande_un_motif(): void
    {
        $ordre = TransportOrder::factory()->affectee()->create([
            'driver_id' => $this->chauffeur()->id,
            'vehicle_registration' => $this->camion('1-AAA-111')->registration,
            'weight' => 1000,
        ]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), [
                'vehicle_registration' => $this->camion('1-BBB-222')->registration,
                'driver_id' => $this->chauffeur()->id,
            ])
            ->assertSessionHasErrors('motif');

        $this->assertSame('1-AAA-111', $ordre->fresh()->vehicle_registration);
    }

    public function test_la_livraison_garde_l_heure_le_receptionnaire_et_les_reserves(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->enRoute()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs($chauffeur->user)
            ->patch(route('missions.status', $ordre), [
                'statut' => 'DELIVERED',
                'receptionnaire' => '  Marie Dupont ',
                'reserves' => 'Une palette filmee dechiree',
            ])
            ->assertSessionHas('success');

        $ordre->refresh();
        $this->assertSame('DELIVERED', $ordre->status);
        $this->assertNotNull($ordre->delivered_at);
        $this->assertSame('Marie Dupont', $ordre->received_by);
        $this->assertSame('Une palette filmee dechiree', $ordre->delivery_reserves);
        $this->assertFalse($ordre->suivi_direct);
    }
}
