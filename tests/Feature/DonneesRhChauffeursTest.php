<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Indisponibilite;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * L'ecran Chauffeurs est ouvert a tout le personnel, mais la fiche ne se
 * modifie que par l'administrateur : date de naissance, retraite prevue,
 * entree en service, numero de permis et motif de sortie (une inaptitude
 * medicale est une donnee de sante) ne partent que vers lui, ni par cet
 * ecran ni par la planification ou le suivi, et le journal ne les recopie
 * pas. Le motif d'une indisponibilite (« maladie ») suit la meme regle : le
 * planificateur n'en voit que la periode, le journal ne le garde pas.
 */
class DonneesRhChauffeursTest extends TestCase
{
    use RefreshDatabase;

    /** Cles de la fiche reservees a qui peut la modifier. */
    private const CLES_RH = [
        'numero_permis', 'embauche', 'naissance', 'naissance_affichee', 'age',
        'retraite_prevue', 'retraite_affichee', 'motif_sortie', 'motif_sortie_code',
    ];

    /** Ce dont le planificateur a besoin pour affecter. */
    private const CLES_PLANIFICATION = [
        'nom', 'permis', 'permis_echeance', 'visite', 'code95', 'tacho', 'adr', 'adr_fin',
        'indisponibilites', 'empechements', 'heures', 'disponible', 'sorti_le', 'depart_futur',
    ];

    private function chauffeur(array $champs = []): Driver
    {
        $utilisateur = User::factory()->chauffeur()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);

        return Driver::create([
            'user_id' => $utilisateur->id,
            'license_number' => 'B-998877',
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'cpc_expiry' => now()->addYears(2)->toDateString(),
            'tacho_card_expiry' => now()->addYears(2)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'employment_status' => 'OUVRIER',
            'hired_on' => '2001-03-01',
            'birth_date' => '1970-05-12',
            'retirement_planned_on' => '2035-05-12',
            'left_on' => now()->addMonth()->toDateString(),
            'departure_reason' => 'INAPTITUDE',
            'is_available' => true,
            ...$champs,
        ]);
    }

    /** @return array<string, mixed> */
    private function fiche(array $champs = []): array
    {
        return [
            'is_available' => true,
            'adr_certified' => false,
            'employment_status' => 'OUVRIER',
            'hired_on' => '2001-03-01',
            'birth_date' => '1970-05-12',
            'retirement_planned_on' => '2035-05-12',
            ...$champs,
        ];
    }

    public function test_le_planificateur_ne_recoit_pas_les_donnees_rh(): void
    {
        $this->chauffeur();

        $reponse = $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('drivers.index'))
            ->assertOk();

        $reponse->assertInertia(function (AssertableInertia $page) {
            $page->component('Parc/Chauffeurs')
                ->where('peutModifier', false)
                ->has('chauffeurs', 1);

            foreach (self::CLES_RH as $cle) {
                $page->missing('chauffeurs.0.'.$cle);
            }

            foreach (self::CLES_PLANIFICATION as $cle) {
                $page->has('chauffeurs.0.'.$cle);
            }

            $page->where('chauffeurs.0.depart_futur', true)->etc();
        });

        // Nulle part dans la page, pas meme dans un autre champ.
        $reponse->assertDontSee('1970-05-12')->assertDontSee('B-998877');
    }

    public function test_l_administrateur_recoit_la_fiche_complete(): void
    {
        $this->chauffeur();

        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('drivers.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('peutModifier', true)
                ->where('chauffeurs.0.numero_permis', 'B-998877')
                ->where('chauffeurs.0.embauche', '01/03/2001')
                ->where('chauffeurs.0.naissance', '1970-05-12')
                ->where('chauffeurs.0.age', Carbon::parse('1970-05-12')->age)
                ->where('chauffeurs.0.retraite_prevue', '2035-05-12')
                ->where('chauffeurs.0.motif_sortie', 'Inaptitude médicale')
                ->where('chauffeurs.0.motif_sortie_code', 'INAPTITUDE')
                ->etc());
    }

    public function test_la_recherche_du_planificateur_ne_parcourt_pas_le_numero_de_permis(): void
    {
        $this->chauffeur();
        $nombre = fn (User $qui, string $terme) => count(AssertableInertia::fromTestResponse(
            $this->actingAs($qui)->get(route('drivers.index', ['q' => $terme]))->assertOk()
        )->toArray()['props']['chauffeurs']);

        $planificateur = User::factory()->planificateur()->create();
        $administrateur = User::factory()->administrateur()->create();

        $this->assertSame(0, $nombre($planificateur, '998877'));
        $this->assertSame(1, $nombre($planificateur, 'Dupont'));
        $this->assertSame(1, $nombre($administrateur, '998877'));
    }

    public function test_le_journal_d_un_depart_ne_garde_ni_le_motif_ni_les_donnees_rh(): void
    {
        $chauffeur = $this->chauffeur(['left_on' => null, 'departure_reason' => null]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('drivers.update', $chauffeur), $this->fiche([
                'left_on' => today()->toDateString(),
                'departure_reason' => 'INAPTITUDE',
            ]))
            ->assertSessionHasNoErrors();

        // La fiche garde le motif ; le journal, non.
        $this->assertSame('INAPTITUDE', $chauffeur->fresh()->departure_reason);

        $ligne = ActivityLog::where('action', 'driver.left')->sole();
        $this->assertSame('Départ de Jean Dupont', $ligne->description);
        $this->assertArrayHasKey('left_on', $ligne->properties);

        foreach (Driver::DONNEES_RH as $champ) {
            $this->assertArrayNotHasKey($champ, $ligne->properties);
        }

        $this->assertStringNotContainsString('INAPTITUDE', json_encode($ligne->properties));
        $this->assertStringNotContainsString('1970-05-12', json_encode($ligne->properties));
    }

    public function test_le_journal_d_une_fiche_modifiee_ne_garde_pas_les_donnees_rh(): void
    {
        $chauffeur = $this->chauffeur(['left_on' => null, 'departure_reason' => null]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('drivers.update', $chauffeur), $this->fiche(['birth_date' => '1971-06-13']))
            ->assertSessionHasNoErrors();

        $this->assertSame('1971-06-13', $chauffeur->fresh()->birth_date->format('Y-m-d'));

        $ligne = ActivityLog::where('action', 'driver.updated')->sole();
        $this->assertSame('Chauffeur Jean Dupont mis à jour', $ligne->description);
        $this->assertTrue($ligne->properties['is_available']);

        foreach (Driver::DONNEES_RH as $champ) {
            $this->assertArrayNotHasKey($champ, $ligne->properties);
        }
    }

    public function test_la_migration_nettoie_le_journal_deja_ecrit_et_peut_etre_rejouee(): void
    {
        $depart = ActivityLog::create([
            'action' => 'driver.left',
            'description' => 'Départ de Jean Dupont (Inaptitude médicale)',
            'subject_type' => 'Driver',
            'subject_id' => '1',
            'properties' => [
                'is_available' => false,
                'birth_date' => '1970-05-12',
                'hired_on' => '2001-03-01',
                'retirement_planned_on' => '2035-05-12',
                'left_on' => '2026-10-01',
                'departure_reason' => 'INAPTITUDE',
            ],
        ]);
        // Un nom qui finit lui-meme par une parenthese ne perd que le motif.
        $nomEntreParentheses = ActivityLog::create([
            'action' => 'driver.left',
            'description' => 'Départ de Marc Lambert (fils) (Retraite)',
            'properties' => ['left_on' => '2026-09-01', 'departure_reason' => 'RETRAITE'],
        ]);
        $sansMotif = ActivityLog::create([
            'action' => 'driver.left',
            'description' => 'Départ de Luc Martin (—)',
            'properties' => ['left_on' => '2026-09-01'],
        ]);
        $modification = ActivityLog::create([
            'action' => 'driver.updated',
            'description' => 'Chauffeur Jean Dupont mis à jour',
            'properties' => ['is_available' => true, 'birth_date' => '1970-05-12', 'hired_on' => '2001-03-01'],
        ]);
        // Une autre action n'est pas touchee.
        $autre = ActivityLog::create([
            'action' => 'page.updated',
            'description' => 'Page « depart (Retraite) » modifiée',
            'properties' => ['birth_date' => 'garde'],
        ]);

        $migration = require database_path('migrations/2026_10_23_100000_retirer_les_donnees_rh_du_journal.php');
        $migration->up();
        $migration->up();

        $depart->refresh();
        $this->assertSame('Départ de Jean Dupont', $depart->description);
        $this->assertSame(['is_available' => false, 'left_on' => '2026-10-01'], $depart->properties);

        $this->assertSame('Départ de Marc Lambert (fils)', $nomEntreParentheses->fresh()->description);
        $this->assertSame(['left_on' => '2026-09-01'], $nomEntreParentheses->fresh()->properties);
        $this->assertSame('Départ de Luc Martin', $sansMotif->fresh()->description);
        $this->assertSame(['is_available' => true], $modification->fresh()->properties);

        $this->assertSame('Page « depart (Retraite) » modifiée', $autre->fresh()->description);
        $this->assertSame(['birth_date' => 'garde'], $autre->fresh()->properties);
    }

    public function test_la_planification_n_envoie_pas_la_fiche_du_chauffeur_avec_l_ordre(): void
    {
        // Depart prevu pour inaptitude : il roule encore, et ses missions
        // livrees restent dans l'onglet « Livrees ».
        $chauffeur = $this->chauffeur();
        TransportOrder::factory()->affectee()->create(['driver_id' => $chauffeur->id]);
        TransportOrder::factory()->livree()->create(['driver_id' => $chauffeur->id]);
        $planificateur = User::factory()->planificateur()->create();

        foreach (['ASSIGNED', 'DELIVERED'] as $statut) {
            $this->actingAs($planificateur)
                ->get(route('planning.index', ['status' => $statut]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Planning/Index')
                    ->where('statut', $statut)
                    ->has('orders.data', 1)
                    ->where('orders.data.0.chauffeur', 'Jean Dupont')
                    ->missing('orders.data.0.driver')
                    ->etc())
                ->assertDontSee('B-998877')
                ->assertDontSee('INAPTITUDE');
        }
    }

    public function test_une_fiche_serialisee_telle_quelle_n_emporte_pas_les_donnees_rh(): void
    {
        $fiche = $this->chauffeur()->fresh()->toArray();

        foreach (Driver::DONNEES_RH as $champ) {
            $this->assertArrayNotHasKey($champ, $fiche);
        }

        // Ce qui sert a planifier reste.
        $this->assertArrayHasKey('license_type', $fiche);
        $this->assertArrayHasKey('license_expiry', $fiche);
        $this->assertArrayHasKey('left_on', $fiche);
    }

    public function test_seul_l_administrateur_apprend_en_planification_qu_une_retraite_est_depassee(): void
    {
        $retraite = now()->subMonths(5)->subDays(3);
        $this->chauffeur(['left_on' => null, 'departure_reason' => null, 'retirement_planned_on' => $retraite->toDateString()]);
        $planification = fn (User $qui) => $this->actingAs($qui)->get(route('planning.index'))->assertOk();

        // Elle n'empeche pas de conduire, et le planificateur ne peut pas
        // corriger la fiche : ni la date ni le simple signal.
        $planification(User::factory()->planificateur()->create())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('drivers', 1)
                ->where('drivers.0.nom', 'Jean Dupont')
                ->missing('drivers.0.retraite_passee')
                ->missing('drivers.0.retraite_a_revoir')
                ->etc())
            ->assertDontSee($retraite->format('d/m/Y'))
            ->assertDontSee($retraite->toDateString());

        $planification(User::factory()->administrateur()->create())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('drivers.0.retraite_passee', $retraite->format('d/m/Y'))
                ->etc());
    }

    public function test_le_suivi_ne_donne_le_numero_de_permis_qu_a_l_administrateur(): void
    {
        $ordre = TransportOrder::factory()->affectee()->create(['driver_id' => $this->chauffeur()->id]);
        $suivi = fn (User $qui) => $this->actingAs($qui)
            ->get(route('tracking.show', ['tracking_number' => $ordre->tracking_number]))
            ->assertOk();

        $suivi(User::factory()->planificateur()->create())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('chauffeur.nom', 'Jean Dupont')
                ->where('chauffeur.numero_permis', null)
                ->etc())
            ->assertDontSee('B-998877');

        $suivi(User::factory()->administrateur()->create())
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('chauffeur.numero_permis', 'B-998877')
                ->etc());
    }

    public function test_le_journal_d_une_indisponibilite_de_chauffeur_ne_garde_pas_son_motif(): void
    {
        $chauffeur = $this->chauffeur(['left_on' => null, 'departure_reason' => null]);
        $administrateur = User::factory()->administrateur()->create();
        $du = today()->addDays(4);
        $au = today()->addDays(8);
        $periode = 'du '.$du->format('d/m/Y').' au '.$au->format('d/m/Y');

        $this->actingAs($administrateur)
            ->post(route('drivers.unavailability', $chauffeur), [
                'du' => $du->toDateString(), 'au' => $au->toDateString(), 'motif' => 'MALADIE',
            ])
            ->assertSessionHasNoErrors();

        // La periode garde son motif ; le journal, non.
        $absence = Indisponibilite::where('driver_id', $chauffeur->id)->sole();
        $this->assertSame('MALADIE', $absence->motif);

        $this->actingAs($administrateur)
            ->delete(route('unavailability.destroy', $absence))
            ->assertSessionHasNoErrors();

        $creee = ActivityLog::where('action', 'unavailability.created')->sole();
        $retiree = ActivityLog::where('action', 'unavailability.removed')->sole();

        $this->assertSame('Indisponibilité enregistrée (chauffeur Jean Dupont) : '.$periode, $creee->description);
        $this->assertSame('Indisponibilité supprimée (chauffeur Jean Dupont) : '.$periode, $retiree->description);

        foreach ([$creee, $retiree] as $ligne) {
            $this->assertStringNotContainsStringIgnoringCase('maladie', $ligne->description.json_encode($ligne->properties));
        }
    }

    public function test_le_journal_d_une_immobilisation_de_camion_garde_son_motif(): void
    {
        $camion = Vehicle::create([
            'registration' => '1-RHX-001',
            'vin' => 'VF1RHX00000000001',
            'vehicle_type' => 'Porteur',
            'brand' => 'Volvo',
            'model' => 'FM',
            'capacity_tonnes' => 12,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
        ]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('vehicles.unavailability', $camion), [
                'du' => today()->addDays(2)->toDateString(), 'au' => today()->addDays(3)->toDateString(), 'motif' => 'ENTRETIEN',
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString(
            '(véhicule 1-RHX-001) : entretien du ',
            ActivityLog::where('action', 'unavailability.created')->sole()->description,
        );
    }

    public function test_la_migration_retire_le_motif_des_indisponibilites_de_chauffeur_deja_journalisees(): void
    {
        $ligne = fn (string $action, string $description, string $sujet = 'Driver') => ActivityLog::create([
            'action' => $action,
            'description' => $description,
            'subject_type' => $sujet,
            'subject_id' => '1',
            'properties' => ['concerne' => 'chauffeur Jean Dupont'],
        ]);

        // Le resume etait ecrit dans la langue de l'administrateur.
        $francais = $ligne('unavailability.created', 'Indisponibilité enregistrée (chauffeur Jean Dupont) : maladie du 05/10/2026 au 09/10/2026');
        $neerlandais = $ligne('unavailability.removed', 'Indisponibilité supprimée (chauffeur Jean Dupont) : ziekte van 05/10/2026 tot 09/10/2026');
        $anglais = $ligne('unavailability.created', 'Indisponibilité enregistrée (chauffeur Marc Lambert (fils)) : sick leave from 12/10/2026 to 14/10/2026');
        $tronquee = $ligne('unavailability.created', 'Indisponibilité enregistrée (chauffeur Luc Martin) : maladie du 05/10/20');
        // Le motif d'un camion n'est pas une donnee de sante : il reste.
        $camion = $ligne('unavailability.created', 'Indisponibilité enregistrée (véhicule 1-ABC-123) : entretien du 05/10/2026 au 09/10/2026', 'Vehicle');

        $migration = require database_path('migrations/2026_10_23_100000_retirer_les_donnees_rh_du_journal.php');
        $migration->up();
        $migration->up();

        $this->assertSame('Indisponibilité enregistrée (chauffeur Jean Dupont) : du 05/10/2026 au 09/10/2026', $francais->fresh()->description);
        $this->assertSame('Indisponibilité supprimée (chauffeur Jean Dupont) : du 05/10/2026 au 09/10/2026', $neerlandais->fresh()->description);
        $this->assertSame('Indisponibilité enregistrée (chauffeur Marc Lambert (fils)) : du 12/10/2026 au 14/10/2026', $anglais->fresh()->description);
        $this->assertSame('Indisponibilité enregistrée (chauffeur Luc Martin)', $tronquee->fresh()->description);
        $this->assertSame(['concerne' => 'chauffeur Jean Dupont'], $francais->fresh()->properties);
        $this->assertSame('Indisponibilité enregistrée (véhicule 1-ABC-123) : entretien du 05/10/2026 au 09/10/2026', $camion->fresh()->description);
    }

    public function test_l_ecran_chauffeurs_ne_donne_le_motif_d_une_absence_qu_a_l_administrateur(): void
    {
        $chauffeur = $this->chauffeur(['left_on' => null, 'departure_reason' => null]);
        $du = today()->addDays(4);
        $au = today()->addDays(8);
        Indisponibilite::create([
            'driver_id' => $chauffeur->id, 'du' => $du->toDateString(), 'au' => $au->toDateString(),
            'motif' => 'MALADIE', 'commentaire' => 'Hospitalisation',
        ]);
        $periode = $du->format('d/m/Y').' au '.$au->format('d/m/Y');
        $absences = fn (User $qui) => AssertableInertia::fromTestResponse(
            $this->actingAs($qui)->get(route('drivers.index'))->assertOk()
        )->toArray()['props']['chauffeurs'][0]['indisponibilites'];

        // Le planificateur garde la periode, qui borne l'affectation.
        $vue = $absences(User::factory()->planificateur()->create());
        $this->assertSame('absence du '.$periode, $vue[0]['resume']);
        $this->assertNull($vue[0]['commentaire']);
        $this->assertStringNotContainsStringIgnoringCase('maladie', json_encode($vue));
        $this->assertStringNotContainsString('Hospitalisation', json_encode($vue));

        // L'administrateur, qui la gere, voit tout comme avant.
        $vue = $absences(User::factory()->administrateur()->create());
        $this->assertSame('maladie du '.$periode, $vue[0]['resume']);
        $this->assertSame('Hospitalisation', $vue[0]['commentaire']);
    }

    public function test_la_planification_donne_la_periode_d_une_absence_sans_son_motif(): void
    {
        $chauffeur = $this->chauffeur(['left_on' => null, 'departure_reason' => null]);
        $du = today()->addDays(4);
        $au = today()->addDays(8);
        Indisponibilite::create(['driver_id' => $chauffeur->id, 'du' => $du->toDateString(), 'au' => $au->toDateString(), 'motif' => 'MALADIE']);
        $absence = 'absence du '.$du->format('d/m/Y').' au '.$au->format('d/m/Y');

        $camion = Vehicle::create([
            'registration' => '1-RHX-002',
            'vin' => 'VF1RHX00000000002',
            'vehicle_type' => 'Porteur',
            'brand' => 'Volvo',
            'model' => 'FM',
            'capacity_tonnes' => 12,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
        ]);
        $enAttente = TransportOrder::factory()->create(['pickup_date' => $du->copy()->addDay()->setTime(8, 0), 'weight' => 1000, 'distance_km' => 120]);
        // Deja affectee a ce chauffeur : la carte signale son absence.
        $affectee = TransportOrder::factory()->affectee()->create([
            'pickup_date' => $du->copy()->addDay()->setTime(8, 0), 'weight' => 1000, 'distance_km' => 120,
            'vehicle_registration' => $camion->registration, 'driver_id' => $chauffeur->id,
        ]);
        $planificateur = User::factory()->planificateur()->create();
        $carte = fn (string $statut, TransportOrder $ordre) => collect(AssertableInertia::fromTestResponse(
            $this->actingAs($planificateur)->get(route('planning.index', ['status' => $statut]))->assertOk()
        )->toArray()['props']['orders']['data'])->firstWhere('id', $ordre->id);

        // Le chauffeur grise dans la liste, avec la periode pour seule raison.
        $this->assertSame($absence, $carte('PENDING', $enAttente)['refus_chauffeurs'][$chauffeur->id]);

        $alertes = $carte('ASSIGNED', $affectee)['alertes'];
        $this->assertContains('Ce chauffeur est indisponible pendant la mission : '.$absence.'.', $alertes);
        $this->assertStringNotContainsStringIgnoringCase('maladie', json_encode($alertes));

        // Le refus d'une affectation ne le dit pas davantage.
        $this->actingAs($planificateur)
            ->post(route('planning.assign', $enAttente), ['vehicle_registration' => $camion->registration, 'driver_id' => $chauffeur->id])
            ->assertSessionHasErrors(['driver_id' => 'Ce chauffeur est indisponible pendant la mission : '.$absence.'.']);
    }
}
