<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Traductions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Troisieme passage sur la planification : refus invisibles, suggestions
 * de villes sans accent, filtre par jour d'enlevement et permis.
 */
class PlanificationTroisiemeParcoursTest extends TestCase
{
    use RefreshDatabase;

    private function chauffeur(array $champs = []): Driver
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
            ...$champs,
        ]);
    }

    private function camion(string $immatriculation = '1-ABC-123', array $champs = []): Vehicle
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
            ...$champs,
        ]);
    }

    public function test_affecter_un_ordre_livre_est_refuse_par_un_message_visible(): void
    {
        $ordre = TransportOrder::factory()->livree()->create(['weight' => 1000]);

        // La carte de l'ordre n'est plus dans l'onglet « En attente » au
        // retour : le refus doit arriver dans le bandeau, pas sur un champ.
        $this->actingAs(User::factory()->planificateur()->create())
            ->from(route('planning.index'))
            ->post(route('planning.assign', $ordre), [
                'vehicle_registration' => $this->camion()->registration,
                'driver_id' => $this->chauffeur()->id,
            ])
            ->assertRedirect(route('planning.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error', Traductions::t('msg.planif_ordre_non_attente', 'Seul un ordre en attente peut être affecté.'));

        $ordre->refresh();
        $this->assertSame('DELIVERED', $ordre->status);
        $this->assertNull($ordre->vehicle_registration);
    }

    public function test_un_ordre_annule_est_refuse_avant_meme_de_valider_le_formulaire(): void
    {
        $ordre = TransportOrder::factory()->create(['status' => 'CANCELLED']);

        $this->actingAs(User::factory()->planificateur()->create())
            ->from(route('planning.index'))
            ->post(route('planning.assign', $ordre), [])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error');

        $this->assertSame('CANCELLED', $ordre->fresh()->status);
    }

    public function test_un_refus_propre_au_camion_reste_sur_la_carte(): void
    {
        $ordre = TransportOrder::factory()->create(['weight' => 5000, 'distance_km' => 80]);

        // L'ordre reste en attente : sa carte est toujours la pour afficher
        // l'erreur sous le choix du vehicule.
        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), [
                'vehicle_registration' => $this->camion('1-PTT-001', ['capacity_tonnes' => 2])->registration,
                'driver_id' => $this->chauffeur()->id,
            ])
            ->assertSessionHasErrors('vehicle_registration')
            ->assertSessionMissing('error');

        $this->assertSame('PENDING', $ordre->fresh()->status);
    }

    public function test_une_ville_se_propose_sans_accent_ni_majuscule(): void
    {
        TransportOrder::factory()->create(['delivery_address' => 'Quai de la Batte 10, 4000 Liège']);
        $planificateur = User::factory()->planificateur()->create();

        foreach (['liege', 'LIEGE', 'Liège', 'iège'] as $saisie) {
            $this->actingAs($planificateur)
                ->get(route('planning.index', ['q' => $saisie]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('suggestions', fn ($suggestions) => collect($suggestions)->contains('Liège')));
        }
    }

    public function test_le_filtre_du_jour_ne_garde_que_les_enlevements_de_ce_jour(): void
    {
        $jour = now()->addDays(5)->startOfDay();
        $duJour = TransportOrder::factory()->create(['pickup_date' => $jour->copy()->setTime(14, 30)]);
        TransportOrder::factory()->create(['pickup_date' => $jour->copy()->addDay()->setTime(8, 0)]);
        TransportOrder::factory()->create(['pickup_date' => null]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('planning.index', ['jour' => $jour->toDateString()]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('jour', $jour->toDateString())
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $duJour->id)
                ->where('compteurs', fn ($compteurs) => (int) collect($compteurs)->get('PENDING') === 1)
                ->where('priorites', fn ($priorites) => collect($priorites)->sum('nombre') === 1));
    }

    public function test_la_pagination_garde_le_jour(): void
    {
        $jour = now()->addDays(3)->startOfDay();
        TransportOrder::factory()->count(16)->create(['pickup_date' => $jour->copy()->setTime(9, 0)]);
        TransportOrder::factory()->create(['pickup_date' => $jour->copy()->addDays(2)->setTime(9, 0)]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('planning.index', ['jour' => $jour->toDateString()]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('orders.total', 16)
                ->where('orders.next_page_url', fn ($url) => str_contains((string) $url, 'jour='.$jour->toDateString())));
    }

    public function test_un_jour_mal_forme_est_ignore(): void
    {
        TransportOrder::factory()->create(['pickup_date' => now()->addDays(2)->setTime(9, 0)]);
        TransportOrder::factory()->create(['pickup_date' => now()->addDays(4)->setTime(9, 0)]);
        $planificateur = User::factory()->planificateur()->create();

        foreach (['2026-02-31', 'demain', '05/10/2026', ['2026-10-05']] as $saisie) {
            $this->actingAs($planificateur)
                ->get(route('planning.index', ['jour' => $saisie]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('jour', null)
                    ->has('orders.data', 2));
        }
    }

    public function test_la_page_donne_de_quoi_griser_un_permis_inadapte(): void
    {
        $semi = $this->camion('1-SEM-001', ['vehicle_type' => 'Semi-remorque', 'capacity_tonnes' => 24]);
        $chauffeur = $this->chauffeur(['license_type' => 'C']);
        $planificateur = User::factory()->planificateur()->create();

        // La page reproduit Driver::motifPermis : il lui faut le type et la
        // charge utile du camion, et la categorie de permis du chauffeur.
        $this->actingAs($planificateur)
            ->get(route('planning.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vehicles.0.vehicle_type', 'Semi-remorque')
                ->where('vehicles.0.capacity_tonnes', fn ($charge) => (float) $charge === 24.0)
                ->where('drivers.0.license_type', 'C'));

        // Ce que la page grise, le serveur le refuse, sur la carte.
        $ordre = TransportOrder::factory()->create(['weight' => 1000, 'distance_km' => 80]);

        $this->actingAs($planificateur)
            ->post(route('planning.assign', $ordre), [
                'vehicle_registration' => $semi->registration,
                'driver_id' => $chauffeur->id,
            ])
            ->assertSessionHasErrors('driver_id');

        $this->assertSame('PENDING', $ordre->fresh()->status);
    }

    public function test_un_formulaire_d_affectation_perime_ne_devient_pas_une_reaffectation(): void
    {
        $ordre = TransportOrder::factory()->affectee()->create([
            'driver_id' => $this->chauffeur()->id,
            'vehicle_registration' => $this->camion('1-AAA-111')->registration,
            'weight' => 1000,
        ]);

        // Un collegue a affecte l'ordre entre-temps : ce formulaire voulait
        // l'affecter, pas le reaffecter.
        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), [
                'vehicle_registration' => $this->camion('1-BBB-222')->registration,
                'driver_id' => $this->chauffeur()->id,
                'reaffectation' => false,
            ])
            ->assertSessionHas('error');

        $this->assertSame('1-AAA-111', $ordre->fresh()->vehicle_registration);
    }
}
