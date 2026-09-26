<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Driver;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tarificateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\GrillesDeDemonstration;
use Tests\TestCase;

/**
 * Fret retour. La mission M part de Bruxelles le lundi 12 octobre pour
 * Lyon et y livre le mardi 13 vers 9 h 20 ; apres 11 h de repos, son camion
 * peut charger a Villeurbanne des le mercredi 14 a 7 h, et jusqu'au
 * jeudi 15.
 */
class FretRetourTest extends TestCase
{
    use GrillesDeDemonstration;
    use RefreshDatabase;

    private TransportOrder $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 10:00');
        Http::fake(['router.project-osrm.org/*' => Http::response([], 503)]);
        $this->creerLesGrillesDeDemonstration();

        DB::table('postal_codes')->insert([
            ['country_code' => 'BE', 'code' => '1000', 'city' => 'Bruxelles', 'lat' => 50.8504, 'lng' => 4.3488],
            ['country_code' => 'BE', 'code' => '2000', 'city' => 'Antwerpen', 'lat' => 51.2199, 'lng' => 4.4035],
            ['country_code' => 'BE', 'code' => '4000', 'city' => 'Liège', 'lat' => 50.6326, 'lng' => 5.5797],
            ['country_code' => 'BE', 'code' => '5000', 'city' => 'Namur', 'lat' => 50.4669, 'lng' => 4.8675],
            ['country_code' => 'FR', 'code' => '69100', 'city' => 'Villeurbanne', 'lat' => 45.7719, 'lng' => 4.8902],
            ['country_code' => 'FR', 'code' => '69001', 'city' => 'Lyon', 'lat' => 45.764, 'lng' => 4.8357],
            ['country_code' => 'FR', 'code' => '13001', 'city' => 'Marseille', 'lat' => 43.2965, 'lng' => 5.3698],
            ['country_code' => 'FR', 'code' => '59000', 'city' => 'Lille', 'lat' => 50.6292, 'lng' => 3.0573],
        ]);

        $this->mission = TransportOrder::factory()->create([
            'client_id' => Client::factory(),
            'status' => 'ASSIGNED',
            'assigned_at' => now(),
            'pickup_address' => 'Rue Haute 100, 1000 Bruxelles, Belgique',
            'delivery_address' => 'Rue de la République 1, 69002 Lyon, France',
            'pickup_lat' => 50.8504, 'pickup_lng' => 4.3488,
            'delivery_lat' => 45.764, 'delivery_lng' => 4.8357,
            'delivery_country' => 'FR',
            'pickup_date' => '2026-10-12 07:00',
            'distance_km' => 737,
            'weight' => 6000,
            'volume' => null,
            'tariff_grid_id' => $this->grille('FR')->id,
            'vehicle_registration' => $this->camion('1-SEM-001')->registration,
            'driver_id' => $this->chauffeur()->id,
        ]);
    }

    private function grille(string $zone, string $niveau = 'STANDARD'): TariffGrid
    {
        return TariffGrid::where('zone', $zone)->where('service_level', $niveau)->firstOrFail();
    }

    private function camion(string $immatriculation, array $champs = []): Vehicle
    {
        return Vehicle::create([
            'registration' => $immatriculation,
            'vin' => 'VF1'.str_pad((string) crc32($immatriculation), 14, '0'),
            'vehicle_type' => 'Semi-remorque', 'brand' => 'Volvo', 'model' => 'FH',
            'capacity_tonnes' => 26, 'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
            ...$champs,
        ]);
    }

    private function chauffeur(): Driver
    {
        $utilisateur = User::factory()->chauffeur()->create();

        return Driver::create([
            'user_id' => $utilisateur->id, 'license_number' => 'P-'.$utilisateur->id, 'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(), 'cpc_expiry' => now()->addYears(2)->toDateString(),
            'tacho_card_expiry' => now()->addYears(2)->toDateString(), 'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
        ]);
    }

    /** Import Villeurbanne -> Anvers, 8 t. */
    private function import(string $date = '2026-10-14T09:00', array $plus = []): array
    {
        return [
            'pickup_address' => 'Rue Francis de Pressensé 10, 69100 Villeurbanne, France',
            'pickup_country' => 'FR',
            'pickup_lat' => 45.7719, 'pickup_lng' => 4.8902,
            'delivery_address' => 'Noorderlaan 100, 2000 Antwerpen, Belgique',
            'delivery_country' => 'BE',
            'delivery_lat' => 51.2199, 'delivery_lng' => 4.4035,
            'weight' => 8000, 'goods_type' => 'Palettes', 'priority' => 'NORMAL',
            'pickup_date' => $date,
            'tariff_grid_id' => $this->grille('FR')->id,
            'shipper_name' => 'Entrepôt Villeurbanne', 'shipper_phone' => '+33 4 00 00 00 00',
            ...$plus,
        ];
    }

    private function estimer(array $commande, ?User $compte = null)
    {
        return $this->actingAs($compte ?? Client::factory()->create()->compte())
            ->postJson(route('transport-orders.estimation'), $commande)
            ->assertOk();
    }

    private function commander(array $commande, ?User $compte = null)
    {
        return $this->actingAs($compte ?? Client::factory()->create()->compte())
            ->post(route('transport-orders.store'), $commande);
    }

    public function test_un_import_sur_le_retour_d_un_camion_recoit_le_tarif_fret_retour(): void
    {
        $estimation = $this->estimer($this->import());
        $this->assertSame('BACKHAUL', $estimation->json('tarif'));

        $this->commander($this->import())->assertSessionHasNoErrors();

        $ordre = TransportOrder::where('pickup_country', 'FR')->firstOrFail();
        $km = Tarificateur::distanceRoutiere(45.7719, 4.8902, 51.2199, 4.4035);
        $grilles = TariffGrid::where('zone', 'FR')->get();
        $ligne = Tarificateur::parFormule($grilles, $km, 8000, 'FR', false);
        $attendu = Tarificateur::fretRetour($grilles, $ligne, $km, 8000, false)[$this->grille('FR')->id];

        $this->assertSame('BACKHAUL', $ordre->pricing_basis);
        $this->assertSame($this->mission->id, $ordre->backhaul_order_id);
        $this->assertEqualsWithDelta($attendu, (float) $ordre->estimated_cost, 0.001);
        $this->assertLessThan($ligne[$this->grille('FR')->id], (float) $ordre->estimated_cost);
        $this->assertLessThan(20, $ordre->approche_km);
    }

    public function test_la_remise_ne_passe_jamais_sous_le_tarif_national_et_garde_les_formules_ordonnees(): void
    {
        $grilles = TariffGrid::where('zone', 'FR')->get();
        $nationales = TariffGrid::where('zone', 'BE')->get();
        $niveau = fn (string $n) => $grilles->firstWhere('service_level', $n)->id;

        foreach ([100, 1200, 8000, 24000] as $kg) {
            foreach ([false, true] as $adr) {
                foreach ([85.6, 314.3, 736.7] as $km) {
                    $ligne = Tarificateur::parFormule($grilles, $km, $kg, 'FR', $adr);
                    $national = Tarificateur::parFormule($nationales, $km, $kg, 'BE', $adr);
                    $prix = Tarificateur::fretRetour($grilles, $ligne, $km, $kg, $adr);

                    foreach ($grilles as $g) {
                        $plancher = $national[$nationales->firstWhere('service_level', $g->service_level)->id];
                        $this->assertGreaterThanOrEqual(min($plancher, $ligne[$g->id]) - 0.01, $prix[$g->id]);
                        $this->assertLessThanOrEqual($ligne[$g->id], $prix[$g->id]);
                    }

                    $this->assertLessThanOrEqual($prix[$niveau('STANDARD')] + 0.01, $prix[$niveau('ECO')]);
                    $this->assertGreaterThanOrEqual($prix[$niveau('STANDARD')] * 1.10 - 0.02, $prix[$niveau('EXPRESS')]);
                }
            }
        }
    }

    public function test_hors_de_la_fenetre_pas_de_tarif_fret_retour(): void
    {
        // Mardi 13 : le chauffeur n'a pas encore pris son repos ; vendredi
        // 16 : plus de deux jours apres la livraison.
        $this->assertSame('LANE', $this->estimer($this->import('2026-10-13T15:00'))->json('tarif'));
        $this->assertSame('LANE', $this->estimer($this->import('2026-10-16T09:00'))->json('tarif'));
    }

    public function test_trop_loin_pas_de_tarif_fret_retour(): void
    {
        $this->assertSame('LANE', $this->estimer($this->import(plus: [
            'pickup_address' => 'La Canebière 1, 13001 Marseille, France', 'pickup_lat' => 43.2965, 'pickup_lng' => 5.3698,
        ]))->json('tarif'));
    }

    public function test_la_mission_du_meme_client_ne_donne_pas_le_tarif(): void
    {
        $this->assertSame('LANE', $this->estimer($this->import(), $this->mission->client->compte())->json('tarif'));
    }

    public function test_une_mission_en_attente_ne_porte_pas_de_fret_retour(): void
    {
        $this->mission->update(['status' => 'PENDING']);

        $this->assertSame('LANE', $this->estimer($this->import())->json('tarif'));
    }

    public function test_la_capacite_restante_est_respectee(): void
    {
        TransportOrder::factory()->create([
            'status' => 'PENDING', 'pickup_country' => 'FR', 'delivery_country' => 'BE', 'weight' => 20000,
            'pricing_basis' => 'BACKHAUL', 'backhaul_order_id' => $this->mission->id,
            'tariff_grid_id' => $this->grille('FR')->id,
        ]);

        $this->assertSame('LANE', $this->estimer($this->import())->json('tarif'));
    }

    public function test_un_binome_non_conforme_ne_justifie_pas_le_tarif(): void
    {
        // Matiere dangereuse : le camion de M n'est pas equipe ADR.
        $this->assertSame('LANE', $this->estimer($this->import(plus: ['is_hazardous' => true]))->json('tarif'));
    }

    public function test_le_client_ne_peut_pas_se_declarer_fret_retour(): void
    {
        $this->commander($this->import('2026-10-16T09:00', [
            'pricing_basis' => 'BACKHAUL', 'backhaul_order_id' => $this->mission->id,
        ]))->assertSessionHasNoErrors();

        $ordre = TransportOrder::where('pickup_country', 'FR')->firstOrFail();
        $this->assertSame('LANE', $ordre->pricing_basis);
        $this->assertNull($ordre->backhaul_order_id);
    }

    public function test_un_tarif_change_entre_affichage_et_commande_est_refuse(): void
    {
        $compte = Client::factory()->create()->compte();
        $annonce = $this->estimer($this->import(), $compte)->json('prix.'.$this->grille('FR')->id);
        $this->mission->update(['status' => 'CANCELLED']);

        $this->commander($this->import(plus: ['prix_annonce' => $annonce]), $compte)->assertSessionHasErrors('tariff_grid_id');
        $this->assertSame(0, TransportOrder::where('pickup_country', 'FR')->count());
    }

    public function test_le_prix_retour_reste_acquis_si_la_porteuse_disparait(): void
    {
        $this->commander($this->import())->assertSessionHasNoErrors();
        $ordre = TransportOrder::where('pickup_country', 'FR')->firstOrFail();
        $prix = (float) $ordre->estimated_cost;

        $this->mission->update(['status' => 'CANCELLED']);

        $this->assertEqualsWithDelta($prix, (float) $ordre->fresh()->estimated_cost, 0.001);
        $carte = collect($this->planification('PENDING')['orders']['data'])->firstWhere('id', $ordre->id);
        $this->assertFalse($carte['porteuse_info']['valide']);
    }

    private function planification(string $statut, array $plus = []): array
    {
        return AssertableInertia::fromTestResponse(
            $this->actingAs(User::factory()->planificateur()->create())->get(route('planning.index', ['status' => $statut, ...$plus]))
        )->toArray()['props'];
    }

    public function test_la_planification_propose_le_binome_qui_livre_a_cote(): void
    {
        $this->commander($this->import('2026-10-14T09:00'))->assertSessionHasNoErrors();
        $ordre = TransportOrder::where('pickup_country', 'FR')->firstOrFail();

        $carte = collect($this->planification('PENDING')['orders']['data'])->firstWhere('id', $ordre->id);
        $propose = $carte['fret_retour'][0];

        $this->assertSame($this->mission->vehicle_registration, $propose['vehicle_registration']);
        $this->assertLessThan(20, (float) str_replace(',', '.', $propose['approche_km']));

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), ['vehicle_registration' => $propose['vehicle_registration'], 'driver_id' => $propose['driver_id']])
            ->assertSessionHasNoErrors();

        $this->assertSame('ASSIGNED', $ordre->fresh()->status);
        $this->assertLessThan(20, $ordre->fresh()->approche_km);
    }

    public function test_sans_camion_proche_le_depart_du_depot_est_indique(): void
    {
        $this->commander($this->import(plus: [
            'pickup_address' => 'Rue Nationale 1, 59000 Lille, France', 'pickup_lat' => 50.6292, 'pickup_lng' => 3.0573,
            'delivery_address' => 'Boulevard d\'Avroy 1, 4000 Liège, Belgique', 'delivery_lat' => 50.6326, 'delivery_lng' => 5.5797,
        ]))->assertSessionHasNoErrors();

        $carte = collect($this->planification('PENDING')['orders']['data'])->firstWhere('pickup_country', 'FR');

        $this->assertSame([], $carte['fret_retour']);
        $this->assertGreaterThan(100, $carte['positionnement']['km']);
    }

    public function test_la_mission_affiche_ses_frets_retour_possibles_et_le_filtre_etranger(): void
    {
        $this->commander($this->import('2026-10-16T09:00'))->assertSessionHasNoErrors();
        $this->commander($this->import('2026-10-14T09:00', ['weight' => 3000]))->assertSessionHasNoErrors();

        $carte = collect($this->planification('ASSIGNED')['orders']['data'])->firstWhere('id', $this->mission->id);
        $this->assertCount(1, $carte['retours_possibles']);

        $attente = $this->planification('PENDING', ['contrainte' => 'etranger']);
        $this->assertCount(2, $attente['orders']['data']);
        $this->assertSame(2, collect($attente['contraintes'])->firstWhere('valeur', 'etranger')['nombre']);
    }

    public function test_un_camion_qui_livre_loin_ne_charge_pas_le_lendemain_a_l_autre_bout(): void
    {
        $namur = fn (string $jour) => TransportOrder::factory()->create([
            'pickup_address' => 'Place d\'Armes 1, 5000 Namur, Belgique', 'pickup_lat' => 50.4669, 'pickup_lng' => 4.8675,
            'pickup_date' => $jour, 'distance_km' => 60, 'weight' => 1000, 'tariff_grid_id' => $this->grille('BE')->id,
        ]);
        $planificateur = User::factory()->planificateur()->create();
        $binome = ['vehicle_registration' => $this->mission->vehicle_registration, 'driver_id' => $this->mission->driver_id];

        // Livre a Lyon le mardi 13 : Namur (680 km) le mercredi est trop tot.
        $this->actingAs($planificateur)->post(route('planning.assign', $namur('2026-10-14 08:00')), $binome)
            ->assertSessionHasErrors('vehicle_registration');
        $this->assertStringContainsString('Lyon', session('errors')->first('vehicle_registration'));

        $this->actingAs($planificateur)->post(route('planning.assign', $namur('2026-10-15 08:00')), $binome)
            ->assertSessionHasNoErrors();
    }
}
