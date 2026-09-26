<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\Client;
use App\Models\Driver;
use App\Models\Indisponibilite;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\TempsDeConduite;
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

        // Un autre trajet le meme jour : plus de quinze heures de volant.
        $this->affecter($this->ordre(['distance_km' => 500, 'pickup_date' => $jour->copy()->setTime(9, 0), 'delivery_lat' => 45.76, 'delivery_lng' => 4.83]), $camion, $chauffeur)
            ->assertSessionHasErrors('driver_id');
    }

    public function test_deux_envois_sur_le_meme_trajet_avec_le_meme_camion_ne_font_qu_une_route(): void
    {
        $camion = $this->camion('1-GRP-001');
        $chauffeur = $this->chauffeur();
        $jour = now()->addDays(3)->setTime(7, 0);
        $trajet = ['distance_km' => 310, 'pickup_lat' => 50.85, 'pickup_lng' => 4.35, 'delivery_lat' => 48.86, 'delivery_lng' => 2.35];

        $this->affecter($this->ordre([...$trajet, 'pickup_date' => $jour]), $camion, $chauffeur)->assertSessionHasNoErrors();
        $this->affecter($this->ordre([...$trajet, 'pickup_date' => $jour->copy()->setTime(8, 0)]), $camion, $chauffeur)->assertSessionHasNoErrors();
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
                ->assertSessionHasErrors('vehicle_registration');
            $this->assertStringStartsWith('Ce camion est déjà affecté à un autre chauffeur ce jour-là (TRK-', session('errors')->first('vehicle_registration'));

            $this->affecter($this->ordre(['pickup_date' => now()->setTime(18, 0)]), $this->camion('1-AUT-'.($depart ? '002' : '001')), $roule)
                ->assertSessionHasErrors('driver_id');
            $this->assertStringStartsWith('Ce chauffeur a déjà une mission ce jour-là avec un autre camion (TRK-', session('errors')->first('driver_id'));
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

    public function test_un_chauffeur_en_conge_ou_un_camion_au_garage_ne_s_affecte_pas(): void
    {
        $ordre = $this->ordre(['pickup_date' => now()->addDays(10)->setTime(8, 0)]);
        $chauffeur = $this->chauffeur();
        $camion = $this->camion('1-GAR-001');

        Indisponibilite::create(['driver_id' => $chauffeur->id, 'du' => now()->addDays(9)->toDateString(), 'au' => now()->addDays(12)->toDateString(), 'motif' => 'CONGE']);
        $this->affecter($ordre, $camion, $chauffeur)
            ->assertSessionHasErrors('driver_id');
        $this->assertStringContainsString('congé du', session('errors')->first('driver_id'));

        Indisponibilite::create(['vehicle_registration' => $camion->registration, 'du' => now()->addDays(10)->toDateString(), 'au' => now()->addDays(10)->toDateString(), 'motif' => 'ENTRETIEN']);
        $this->affecter($ordre, $camion, $this->chauffeur())->assertSessionHasErrors('vehicle_registration');

        // Hors de la periode, rien ne s'y oppose.
        $this->affecter($this->ordre(['pickup_date' => now()->addDays(20)->setTime(8, 0)]), $camion, $chauffeur)->assertSessionHasNoErrors();
    }

    public function test_poser_un_conge_sur_une_mission_affectee_la_signale(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = $this->ordre(['pickup_date' => now()->addDays(5)->setTime(8, 0)]);
        $this->affecter($ordre, $this->camion('1-CON-001'), $chauffeur)->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('drivers.unavailability', $chauffeur), ['du' => now()->addDays(4)->toDateString(), 'au' => now()->addDays(8)->toDateString(), 'motif' => 'MALADIE'])
            ->assertSessionHas('success')
            ->assertSessionHas('error', fn (string $m) => str_contains($m, $ordre->tracking_number));

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('drivers.unavailability', $chauffeur), ['du' => now()->subDay()->toDateString(), 'au' => now()->addDay()->toDateString(), 'motif' => 'CONGE'])
            ->assertSessionHasErrors('du');
    }

    public function test_un_produit_chimique_exige_une_declaration_adr_explicite(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($client->compte())
            ->post(route('transport-orders.store'), ['goods_type' => 'Produits chimiques', 'weight' => 500])
            ->assertSessionHasErrors('is_hazardous');

        $this->actingAs($client->compte())
            ->post(route('transport-orders.store'), ['goods_type' => 'Produits chimiques', 'weight' => 500, 'is_hazardous' => false])
            ->assertSessionDoesntHaveErrors('is_hazardous');

        [, $jeton] = ApiKey::generer([
            'name' => 'Cle', 'client_id' => $client->id, 'abilities' => ['lecture', 'ecriture'],
            'created_by' => User::factory()->administrateur()->create()->id,
        ]);
        $this->postJson('/api/v1/expeditions', [
            'enlevement' => 'Rue Neuve 43, 3500 Hasselt', 'livraison' => 'Avenue Louise 200, 1000 Bruxelles',
            'poids' => 500, 'marchandise' => 'Produits chimiques',
            'date_enlevement' => now()->addDays(2)->toDateString(), 'date_livraison' => now()->addDays(9)->toDateString(),
        ], ['Authorization' => 'Bearer '.$jeton])
            ->assertStatus(422)
            ->assertJsonValidationErrors('matieres_dangereuses');
    }

    public function test_une_commande_plus_lourde_que_toute_la_flotte_est_refusee_a_la_commande(): void
    {
        $this->camion('1-MAX-001', ['capacity_tonnes' => 26.9]);

        $this->actingAs(Client::factory()->create()->compte())
            ->post(route('transport-orders.store'), ['weight' => 30000, 'goods_type' => 'Palettes'])
            ->assertSessionHasErrors('weight');
        $this->assertStringContainsString('26,9 t', session('errors')->first('weight'));
    }

    public function test_le_volume_se_verifie_seul_et_en_cumul(): void
    {
        $fourgon = $this->camion('1-VOL-001', ['capacity_volume' => 45]);
        $chauffeur = $this->chauffeur();
        $jour = now()->addDays(3)->setTime(8, 0);

        $this->affecter($this->ordre(['volume' => 90]), $fourgon, $chauffeur)->assertSessionHasErrors('vehicle_registration');

        $this->affecter($this->ordre(['volume' => 30, 'pickup_date' => $jour]), $fourgon, $chauffeur)->assertSessionHasNoErrors();
        $this->affecter($this->ordre(['volume' => 20, 'pickup_date' => $jour->copy()->setTime(9, 0)]), $fourgon, $chauffeur)
            ->assertSessionHasErrors('vehicle_registration');
        $this->assertStringStartsWith('Volume cumulé', session('errors')->first('vehicle_registration'));
    }

    public function test_une_camionnette_ne_demande_ni_code_95_ni_carte_tachygraphe(): void
    {
        $camionnette = $this->camion('1-VAN-001', ['vehicle_type' => 'Camionnette', 'capacity_tonnes' => 1.2]);
        $this->assertSame('B', $camionnette->permisRequis());
        $sansQualification = $this->chauffeur(['cpc_expiry' => null, 'tacho_card_expiry' => null]);

        $this->affecter($this->ordre(['weight' => 800]), $camionnette, $sansQualification)->assertSessionHasNoErrors();

        // Au-dela de 3,5 t, les deux documents restent exiges.
        $this->affecter($this->ordre(['pickup_date' => now()->addDays(5)->setTime(8, 0)]), $this->camion('1-POR-001'), $sansQualification)
            ->assertSessionHasErrors('driver_id');
        $this->assertStringContainsString('code 95', session('errors')->first('driver_id'));
    }

    public function test_un_chauffeur_sans_code_95_est_compte_inapte_et_a_mettre_en_regle(): void
    {
        $sansCode95 = $this->chauffeur(['cpc_expiry' => null]);
        $enRegle = $this->chauffeur();
        // Un chauffeur de camionnette (permis B) n'a besoin ni de l'un ni de l'autre.
        $permisB = $this->chauffeur(['license_type' => 'B', 'cpc_expiry' => null, 'tacho_card_expiry' => null]);
        $admin = User::factory()->administrateur()->create();

        $ids = fn (string $etat) => collect(AssertableInertia::fromTestResponse(
            $this->actingAs($admin)->get(route('drivers.index', ['etat' => $etat]))
        )->toArray()['props']['chauffeurs'])->pluck('id');

        $this->assertTrue($ids('inaptes')->contains($sansCode95->id));
        $this->assertFalse($ids('inaptes')->contains($permisB->id));
        $this->assertFalse($ids('disponibles')->contains($sansCode95->id));
        $this->assertTrue($ids('disponibles')->contains($enRegle->id));
        $this->assertTrue($ids('disponibles')->contains($permisB->id));
        $this->assertTrue($ids('conformite')->contains($sansCode95->id));
        $this->assertFalse($ids('conformite')->contains($enRegle->id));
    }

    public function test_un_envoi_qu_aucun_camion_ne_peut_prendre_est_refuse_a_la_commande(): void
    {
        // Un camion ADR sans hayon, un camion a hayon sans ADR : aucun ne
        // reunit les deux.
        $this->camion('1-ADR-001', ['adr_equipe' => true, 'capacity_volume' => 60]);
        $this->camion('1-HAY-001', ['has_tail_lift' => true, 'capacity_volume' => 60]);
        $client = Client::factory()->create();
        $commande = [
            'pickup_address' => 'Rue Neuve 43, 3500 Hasselt, Belgique',
            'delivery_address' => 'Avenue Louise 200, 1000 Bruxelles, Belgique',
            'delivery_country' => 'BE',
            'pickup_lat' => 50.9311, 'pickup_lng' => 5.3378,
            'delivery_lat' => 50.8504, 'delivery_lng' => 4.3488,
            'weight' => 1200,
            'goods_type' => 'Produits chimiques',
            'priority' => 'NORMAL',
            'tariff_grid_id' => TariffGrid::factory()->create()->id,
        ];

        $this->actingAs($client->compte())
            ->post(route('transport-orders.store'), [...$commande, 'is_hazardous' => true, 'needs_tail_lift' => true])
            ->assertSessionHasErrors('flotte');
        $this->assertStringEndsWith('kg, équipement ADR, hayon élévateur) : demandez un devis.', session('errors')->first('flotte'));

        $this->actingAs($client->compte())
            ->post(route('transport-orders.store'), [...$commande, 'is_hazardous' => true, 'needs_tail_lift' => false])
            ->assertSessionDoesntHaveErrors('flotte');

        // Le plus grand camion charge 60 m³.
        $this->actingAs($client->compte())
            ->post(route('transport-orders.store'), [...$commande, 'is_hazardous' => false, 'volume' => 80])
            ->assertSessionHasErrors('volume');
        $this->assertStringContainsString('60', session('errors')->first('volume'));

        [, $jeton] = ApiKey::generer([
            'name' => 'Cle', 'client_id' => $client->id, 'abilities' => ['lecture', 'ecriture'],
            'created_by' => User::factory()->administrateur()->create()->id,
        ]);
        $this->postJson('/api/v1/expeditions', [
            'enlevement' => 'Rue Neuve 43, 3500 Hasselt', 'livraison' => 'Avenue Louise 200, 1000 Bruxelles',
            'poids' => 1200, 'marchandise' => 'Produits chimiques', 'matieres_dangereuses' => true, 'hayon' => true,
            'date_enlevement' => now()->addDays(2)->toDateString(), 'date_livraison' => now()->addDays(9)->toDateString(),
        ], ['Authorization' => 'Bearer '.$jeton])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Aucun camion de notre flotte ne réunit ces conditions (1')
                && str_ends_with($message, 'kg, équipement ADR, hayon élévateur) : demandez un devis.'));
        $this->assertSame(0, TransportOrder::count());
    }

    public function test_pas_de_prix_pour_un_poids_que_la_flotte_ne_porte_pas(): void
    {
        $this->camion('1-MAX-002', ['capacity_tonnes' => 26.9]);
        TariffGrid::factory()->create();
        $client = Client::factory()->create();
        $trajet = ['delivery_country' => 'BE', 'pickup_lat' => 50.9311, 'pickup_lng' => 5.3378, 'delivery_lat' => 50.8504, 'delivery_lng' => 4.3488];

        $this->actingAs($client->compte())
            ->postJson(route('transport-orders.estimation'), [...$trajet, 'weight' => 30000])
            ->assertOk()
            ->assertJsonPath('prix', []);

        $prix = $this->actingAs($client->compte())
            ->postJson(route('transport-orders.estimation'), [...$trajet, 'weight' => 20000])
            ->assertOk()
            ->json('prix');
        $this->assertNotEmpty($prix);
    }

    public function test_la_reaffectation_d_une_mission_en_retard_garde_l_ancien_enlevement_au_journal(): void
    {
        $prevu = now()->subDays(4)->setTime(8, 0);
        $ordre = $this->ordre(['pickup_date' => $prevu]);
        $ordre->update(['status' => 'ASSIGNED', 'vehicle_registration' => $this->camion('1-LOG-001')->registration, 'driver_id' => $this->chauffeur()->id, 'assigned_at' => now()->subDays(5)]);

        $this->affecter($ordre->fresh(), $this->camion('1-LOG-002'), $this->chauffeur(), ['motif' => 'Panne du camion', 'reaffectation' => true])
            ->assertSessionHasNoErrors();

        $journal = ActivityLog::where('action', 'order.reassigned')->firstOrFail();
        $this->assertSame($prevu->format('Y-m-d H:i'), $journal->properties['ancien_enlevement']);
        $this->assertStringContainsString('reporté au', $journal->description);
    }

    public function test_une_mission_en_route_depuis_plus_d_une_semaine_est_signalee(): void
    {
        $ancienne = TransportOrder::factory()->enRoute()->create([
            'vehicle_registration' => $this->camion('1-OUB-001')->registration,
            'driver_id' => $this->chauffeur()->id,
            'pickup_date' => now()->subDays(12),
            'picked_up_at' => now()->subDays(12),
        ]);
        $recente = TransportOrder::factory()->enRoute()->create([
            'vehicle_registration' => $this->camion('1-OUB-002')->registration,
            'driver_id' => $this->chauffeur()->id,
            'pickup_date' => now()->subDay(),
            'picked_up_at' => now()->subDay(),
        ]);

        $cartes = collect(AssertableInertia::fromTestResponse(
            $this->actingAs(User::factory()->planificateur()->create())->get(route('planning.index', ['status' => 'IN_PROGRESS']))
        )->toArray()['props']['orders']['data'])->keyBy('id');

        $this->assertSame(now()->subDays(12)->format('d/m/Y'), $cartes[$ancienne->id]['en_route_depuis']);
        $this->assertArrayNotHasKey('en_route_depuis', $cartes[$recente->id]);
    }

    public function test_le_repos_journalier_remet_le_compteur_de_pauses_a_zero(): void
    {
        // 737 km : 9 h le premier jour (une pause), 2 h 20 le lendemain.
        $this->assertSame(1, TempsDeConduite::nombreDePauses(TempsDeConduite::heuresDeConduite(737)));
        $this->assertSame(0, TempsDeConduite::nombreDePauses(4.4));
        $this->assertSame(2, TempsDeConduite::nombreDePauses(18.0));
    }
}
