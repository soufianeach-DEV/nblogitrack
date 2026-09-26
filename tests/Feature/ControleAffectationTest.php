<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * La bonne commande dans le bon camion, avec le bon chauffeur et le bon
 * permis : chaque test rejoue un couple que l'audit a vu accepter.
 */
class ControleAffectationTest extends TestCase
{
    use RefreshDatabase;

    private function chauffeur(array $champs = []): Driver
    {
        $utilisateur = User::factory()->chauffeur()->create();

        return Driver::create([
            'user_id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'cpc_expiry' => now()->addYears(2)->toDateString(),
            'tacho_card_expiry' => now()->addYears(2)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
            ...$champs,
        ]);
    }

    private function camion(string $immatriculation, array $champs = []): Vehicle
    {
        return Vehicle::create([
            'registration' => $immatriculation,
            'vin' => 'VF1'.str_pad((string) crc32($immatriculation), 14, '0'),
            'vehicle_type' => 'Porteur',
            'brand' => 'Volvo',
            'model' => 'FM',
            'capacity_tonnes' => 12,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
            ...$champs,
        ]);
    }

    private function affecter(TransportOrder $ordre, Vehicle $camion, Driver $chauffeur, array $plus = [])
    {
        return $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), [
                'vehicle_registration' => $camion->registration,
                'driver_id' => $chauffeur->id,
                ...$plus,
            ]);
    }

    private function ordre(array $champs = []): TransportOrder
    {
        return TransportOrder::factory()->create([
            'pickup_date' => now()->addDays(3)->setTime(8, 0),
            'weight' => 1000,
            'distance_km' => 120,
            ...$champs,
        ]);
    }

    public function test_un_tracteur_de_44_t_type_frigo_exige_le_permis_ce(): void
    {
        $tracteur = $this->camion('1-TCN-335', ['vehicle_type' => 'Frigo', 'capacity_tonnes' => 26.7]);
        $this->assertSame('CE', $tracteur->permisRequis());

        $this->affecter($this->ordre(), $tracteur, $this->chauffeur(['license_type' => 'C']))
            ->assertSessionHasErrors(['driver_id' => 'Permis inadapté : ce véhicule exige le permis CE (permis C).']);

        $this->affecter($this->ordre(), $tracteur, $this->chauffeur(['license_type' => 'CE']))->assertSessionHasNoErrors();
    }

    public function test_le_permis_requis_de_la_fiche_prime_sur_le_gabarit(): void
    {
        // Porteur de 3,4 t de charge utile mais 12 t en charge (grue) :
        // l'administrateur a indique le permis C sur sa fiche.
        $grue = $this->camion('1-GRU-001', ['capacity_tonnes' => 3.4, 'permis_requis' => 'C']);

        $this->affecter($this->ordre(), $grue, $this->chauffeur(['license_type' => 'C1E']))
            ->assertSessionHasErrors('driver_id');
        $this->affecter($this->ordre(), $grue, $this->chauffeur(['license_type' => 'C']))->assertSessionHasNoErrors();
    }

    public function test_une_marchandise_dangereuse_exige_un_vehicule_equipe_adr(): void
    {
        $chauffeur = $this->chauffeur(['adr_certified' => true, 'adr_expiry' => now()->addYears(2)->toDateString()]);

        $this->affecter($this->ordre(['is_hazardous' => true]), $this->camion('1-SAN-ADR'), $chauffeur)
            ->assertSessionHasErrors(['vehicle_registration' => 'Marchandise dangereuse : ce véhicule n\'est pas équipé ADR (plaques orange, extincteurs, lot de bord).']);

        $this->affecter($this->ordre(['is_hazardous' => true]), $this->camion('1-AVE-ADR', ['adr_equipe' => true]), $chauffeur)
            ->assertSessionHasNoErrors();
    }

    public function test_le_certificat_adr_du_chauffeur_doit_etre_date_et_valable(): void
    {
        $citerne = $this->camion('1-CIT-001', ['vehicle_type' => 'Citerne', 'adr_equipe' => true]);

        $this->affecter($this->ordre(['is_hazardous' => true]), $citerne, $this->chauffeur(['adr_certified' => true]))
            ->assertSessionHasErrors(['driver_id' => 'Marchandise dangereuse : la fin de validité de son certificat ADR n\'est pas enregistrée.']);

        $this->affecter($this->ordre(['is_hazardous' => true]), $citerne, $this->chauffeur([
            'adr_certified' => true,
            'adr_expiry' => now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('driver_id');
    }

    public function test_le_groupage_verifie_la_charge_cumulee(): void
    {
        $semi = $this->camion('1-GQB-410', ['vehicle_type' => 'Semi-remorque', 'capacity_tonnes' => 26.8]);
        $chauffeur = $this->chauffeur();
        $jour = now()->addDays(3)->setTime(8, 0);

        $this->affecter($this->ordre(['weight' => 19760, 'pickup_date' => $jour]), $semi, $chauffeur)->assertSessionHasNoErrors();

        $this->affecter($this->ordre(['weight' => 19700, 'pickup_date' => $jour->copy()->setTime(10, 0)]), $semi, $chauffeur)
            ->assertSessionHasErrors('vehicle_registration');
        $this->assertStringStartsWith('Charge cumulée trop lourde', session('errors')->first('vehicle_registration'));

        $this->affecter($this->ordre(['weight' => 5000, 'pickup_date' => $jour->copy()->setTime(10, 0)]), $semi, $chauffeur)->assertSessionHasNoErrors();
    }

    public function test_le_groupage_respecte_le_plafond_de_conduite_journalier(): void
    {
        $camion = $this->camion('1-JOU-001');
        $chauffeur = $this->chauffeur();
        $jour = now()->addDays(3)->setTime(7, 0);

        $this->affecter($this->ordre(['distance_km' => 500, 'pickup_date' => $jour]), $camion, $chauffeur)->assertSessionHasNoErrors();

        $this->affecter($this->ordre(['distance_km' => 500, 'pickup_date' => $jour->copy()->setTime(9, 0)]), $camion, $chauffeur)
            ->assertSessionHasErrors('driver_id');
    }

    public function test_une_mission_en_route_sans_date_ou_partie_depuis_longtemps_occupe_son_camion(): void
    {
        $camion = $this->camion('1-ADG-457');
        $roule = $this->chauffeur();

        foreach ([null, now()->subDays(10)] as $depart) {
            TransportOrder::where('vehicle_registration', $camion->registration)->delete();
            TransportOrder::factory()->enRoute()->create([
                'vehicle_registration' => $camion->registration,
                'driver_id' => $roule->id,
                'pickup_date' => $depart,
                'picked_up_at' => $depart,
                'distance_km' => 100,
            ]);

            $this->affecter($this->ordre(['pickup_date' => now()->setTime(18, 0)]), $camion, $this->chauffeur())
                ->assertSessionHasErrors(['vehicle_registration' => 'Ce camion est déjà affecté à un autre chauffeur ce jour-là.']);

            $this->affecter($this->ordre(['pickup_date' => now()->setTime(18, 0)]), $this->camion('1-AUT-'.($depart ? '002' : '001')), $roule)
                ->assertSessionHasErrors(['driver_id' => 'Ce chauffeur a déjà une mission ce jour-là avec un autre camion.']);
        }
    }

    public function test_reaffecter_une_mission_en_retard_verifie_les_documents_a_partir_d_aujourd_hui(): void
    {
        $ordre = $this->ordre(['pickup_date' => now()->subDays(4)->setTime(8, 0)]);
        $ordre->update(['status' => 'ASSIGNED', 'vehicle_registration' => $this->camion('1-ANC-001')->registration, 'driver_id' => $this->chauffeur()->id, 'assigned_at' => now()->subDays(5)]);

        $permisEchu = $this->chauffeur(['license_expiry' => now()->subDays(2)->toDateString()]);

        $this->affecter($ordre->fresh(), $this->camion('1-NEU-001'), $permisEchu, ['motif' => 'Chauffeur malade', 'reaffectation' => true])
            ->assertSessionHasErrors('driver_id');

        $this->affecter($ordre->fresh(), $this->camion('1-NEU-002'), $this->chauffeur(), ['motif' => 'Chauffeur malade', 'reaffectation' => true])
            ->assertSessionHasNoErrors();

        // La mission repart aujourd'hui avec son nouveau binome.
        $this->assertTrue($ordre->fresh()->pickup_date->isToday());
    }

    public function test_sans_code_95_ni_carte_tachygraphe_le_chauffeur_ne_part_pas(): void
    {
        $this->affecter($this->ordre(), $this->camion('1-C95-001'), $this->chauffeur(['cpc_expiry' => null]))
            ->assertSessionHasErrors(['driver_id' => 'Ce chauffeur ne peut pas prendre la route : aucune qualification code 95 enregistrée.']);

        $this->affecter($this->ordre(), $this->camion('1-TAC-001'), $this->chauffeur(['tacho_card_expiry' => null]))
            ->assertSessionHasErrors('driver_id');
    }

    public function test_le_chauffeur_ne_peut_pas_partir_si_un_document_a_expire_depuis_l_affectation(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = $this->ordre(['pickup_date' => now()->setTime(8, 0)]);
        $this->affecter($ordre, $this->camion('1-DEP-001'), $chauffeur)->assertSessionHasNoErrors();

        $chauffeur->update(['license_expiry' => now()->subDay()->toDateString()]);

        $this->actingAs($chauffeur->user)
            ->patch(route('missions.status', $ordre), ['statut' => 'IN_PROGRESS'])
            ->assertSessionHas('error');

        $this->assertSame('ASSIGNED', $ordre->fresh()->status);
    }

    public function test_corriger_une_fiche_signale_les_missions_a_reaffecter(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = $this->ordre(['pickup_date' => now()->addDays(20)->setTime(8, 0)]);
        $this->affecter($ordre, $this->camion('1-FIC-001'), $chauffeur)->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('drivers.update', $chauffeur), [
                'is_available' => true,
                'adr_certified' => false,
                'employment_status' => 'OUVRIER',
                'license_expiry' => now()->addDays(10)->toDateString(),
                'cpc_expiry' => now()->addYears(2)->toDateString(),
                'tacho_card_expiry' => now()->addYears(2)->toDateString(),
                'medical_exam_date' => now()->subMonths(2)->toDateString(),
            ])
            ->assertSessionHas('success')
            ->assertSessionHas('error', fn (string $message) => str_contains($message, $ordre->tracking_number));
    }

    public function test_l_ecran_grise_a_la_date_de_la_mission_et_signale_les_couples_non_conformes(): void
    {
        $permisCourt = $this->chauffeur(['license_expiry' => now()->addDays(3)->toDateString()]);
        $tracteur = $this->camion('1-FRI-044', ['vehicle_type' => 'Frigo', 'capacity_tonnes' => 26.7]);
        $enAttente = $this->ordre(['pickup_date' => now()->addDays(10)->setTime(8, 0)]);

        $affectee = $this->ordre(['pickup_date' => now()->addDays(1)->setTime(8, 0)]);
        $affectee->update(['status' => 'ASSIGNED', 'vehicle_registration' => $tracteur->registration, 'driver_id' => $this->chauffeur(['license_type' => 'C'])->id, 'assigned_at' => now()]);

        $planificateur = User::factory()->planificateur()->create();

        $attente = AssertableInertia::fromTestResponse($this->actingAs($planificateur)->get(route('planning.index')))->toArray()['props'];
        $carte = collect($attente['orders']['data'])->firstWhere('id', $enAttente->id);
        $this->assertStringContainsString('permis expiré', $carte['refus_chauffeurs'][$permisCourt->id]);
        $this->assertSame('CE', collect($attente['vehicles'])->firstWhere('registration', '1-FRI-044')['permis_requis']);
        $this->assertSame(['B', 'C1', 'C'], $attente['couverture']['C']);

        $affectees = AssertableInertia::fromTestResponse($this->actingAs($planificateur)->get(route('planning.index', ['status' => 'ASSIGNED'])))->toArray()['props'];
        $carte = collect($affectees['orders']['data'])->firstWhere('id', $affectee->id);
        $this->assertSame(['Permis inadapté : ce véhicule exige le permis CE (permis C).'], $carte['alertes']);
    }
}
