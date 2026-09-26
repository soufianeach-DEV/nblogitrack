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
 * Chaque lien du tableau de bord ouvre une liste qui montre ce que le
 * tableau de bord annonce : meme critere, meme nombre.
 */
class TableauDeBordLiensTest extends TestCase
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
            'model' => 'FH',
            'capacity_tonnes' => 12,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
            ...$champs,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function props(User $utilisateur, string $adresse): array
    {
        return AssertableInertia::fromTestResponse(
            $this->actingAs($utilisateur)->get($adresse)->assertOk()
        )->toArray()['props'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function alerte(array $props, string $finDuLien): ?array
    {
        return collect($props['alertes'])->first(fn (array $a) => str_ends_with((string) $a['lien'], $finDuLien));
    }

    public function test_voir_les_chauffeurs_a_mettre_en_regle_ouvre_la_liste_du_meme_nombre(): void
    {
        $this->chauffeur();
        $attendus = [
            $this->chauffeur(['medical_exam_date' => now()->subMonths(14)->toDateString()])->id,
            // Sans visite enregistree : le widget le compte, le filtre
            // « visite » ne le montrait pas.
            $this->chauffeur(['medical_exam_date' => null])->id,
            $this->chauffeur(['license_expiry' => now()->addDays(30)->toDateString()])->id,
            $this->chauffeur(['cpc_expiry' => now()->subDay()->toDateString()])->id,
            $this->chauffeur(['tacho_card_expiry' => now()->subDay()->toDateString()])->id,
        ];
        // Ni l'indisponible ni le parti ne roulent : ils ne sont pas comptes.
        $this->chauffeur(['is_available' => false, 'medical_exam_date' => now()->subMonths(14)->toDateString()]);
        $this->chauffeur([
            'is_available' => false,
            'medical_exam_date' => now()->subMonths(14)->toDateString(),
            'left_on' => now()->subMonth()->toDateString(),
            'departure_reason' => 'RETRAITE',
        ]);

        $administrateur = User::factory()->administrateur()->create();

        $tableau = $this->props($administrateur, route('dashboard'));
        $this->assertSame(5, $tableau['conformite']['total_chauffeurs']);

        $liste = $this->props($administrateur, route('drivers.index', ['etat' => 'conformite']));
        $this->assertSame(5, $liste['compteurs']['conformite']);
        $this->assertEqualsCanonicalizing($attendus, array_column($liste['chauffeurs'], 'id'));
    }

    public function test_voir_les_vehicules_a_mettre_en_regle_ouvre_la_liste_du_meme_nombre(): void
    {
        $echu = [
            'inspection_date' => now()->subMonths(13)->toDateString(),
            'inspection_valid_until' => now()->subMonth()->toDateString(),
        ];

        $this->camion('1-BON-001');
        $this->camion('1-EXP-001', $echu);
        $this->camion('1-EXP-002', ['is_available' => false, ...$echu]);
        $this->camion('1-EXP-003', ['is_available' => false, ...$echu]);
        $this->camion('1-EXP-004', ['is_available' => false, ...$echu]);

        // Retire du service mais parti en mission : il roule, il compte.
        TransportOrder::factory()->affectee()->create(['vehicle_registration' => '1-EXP-003']);
        // Une mission terminee ne le fait plus rouler.
        TransportOrder::factory()->livree()->create(['vehicle_registration' => '1-EXP-004']);

        $administrateur = User::factory()->administrateur()->create();

        $tableau = $this->props($administrateur, route('dashboard'));
        $this->assertSame(2, $tableau['conformite']['total_vehicules']);

        $liste = $this->props($administrateur, route('vehicles.index', ['etat' => 'controle_roulant']));
        $this->assertSame(2, $liste['compteurs']['controle_roulant']);
        $this->assertEqualsCanonicalizing(['1-EXP-001', '1-EXP-003'], array_column($liste['vehicules'], 'immatriculation'));

        // Le filtre « controle depasse » garde son sens : tout le parc echu.
        $this->assertSame(4, $liste['compteurs']['controle']);
    }

    public function test_l_alerte_des_retards_ouvre_la_liste_des_expeditions_en_retard(): void
    {
        $client = Client::factory()->create();
        $autre = Client::factory()->create();

        $enRetard = [
            TransportOrder::factory()->create([
                'client_id' => $client->id,
                'requested_delivery_date' => now()->subDay()->toDateString(),
            ])->id,
            TransportOrder::factory()->affectee()->create([
                'client_id' => $client->id,
                'requested_delivery_date' => now()->subDays(3)->toDateString(),
            ])->id,
        ];
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'requested_delivery_date' => now()->subDays(5)->toDateString(),
        ]);
        TransportOrder::factory()->create([
            'client_id' => $client->id,
            'requested_delivery_date' => now()->addDays(5)->toDateString(),
        ]);
        TransportOrder::factory()->create(['client_id' => $client->id, 'requested_delivery_date' => null]);
        TransportOrder::factory()->enRoute()->create([
            'client_id' => $autre->id,
            'requested_delivery_date' => now()->subDays(2)->toDateString(),
        ]);

        // Le personnel voit les retards de toutes les entreprises.
        $planificateur = User::factory()->planificateur()->create();
        $alerte = $this->alerte($this->props($planificateur, route('dashboard')), '/transport-orders?retard=1');
        $this->assertNotNull($alerte);
        $this->assertStringStartsWith('3 ', $alerte['titre']);

        $liste = $this->props($planificateur, route('transport-orders.index', ['retard' => 1]));
        $this->assertSame(3, $liste['orders']['total']);
        $this->assertSame('1', $liste['filters']['retard']);

        // Le client ne voit que les siens, sur le tableau de bord comme dans
        // sa liste.
        $compte = $client->compte();
        $alerte = $this->alerte($this->props($compte, route('dashboard')), '/transport-orders?retard=1');
        $this->assertNotNull($alerte);
        $this->assertStringStartsWith('2 ', $alerte['titre']);

        $liste = $this->props($compte, route('transport-orders.index', ['retard' => 1]));
        $this->assertSame(2, $liste['orders']['total']);
        $this->assertEqualsCanonicalizing($enRetard, array_column($liste['orders']['data'], 'id'));

        // Sans le filtre, la liste reprend toutes ses expeditions.
        $liste = $this->props($compte, route('transport-orders.index'));
        $this->assertSame(5, $liste['orders']['total']);
        $this->assertNull($liste['filters']['retard']);
    }

    public function test_l_attente_d_affectation_du_client_ouvre_ses_expeditions_en_attente(): void
    {
        $client = Client::factory()->create();
        TransportOrder::factory()->count(2)->create(['client_id' => $client->id]);
        TransportOrder::factory()->livree()->create(['client_id' => $client->id]);

        $compte = $client->compte();
        $alerte = $this->alerte($this->props($compte, route('dashboard')), '/transport-orders?status=PENDING');
        $this->assertNotNull($alerte);
        $this->assertStringStartsWith('2 ', $alerte['titre']);

        $liste = $this->props($compte, route('transport-orders.index', ['status' => 'PENDING']));
        $this->assertSame(2, $liste['orders']['total']);
    }

    public function test_les_alertes_de_planification_ouvrent_la_planification_filtree(): void
    {
        $dangereuse = TransportOrder::factory()->dangereuse()->create();
        TransportOrder::factory()->create(['pickup_date' => now()->addDay()]);

        $planificateur = User::factory()->planificateur()->create();
        $tableau = $this->props($planificateur, route('dashboard'));

        $adr = $this->alerte($tableau, '/planification?contrainte=adr');
        $this->assertNotNull($adr);
        $this->assertStringStartsWith('1 ', $adr['titre']);

        $this->assertNotNull($this->alerte($tableau, '/planification?status=PENDING'));

        $liste = $this->props($planificateur, route('planning.index', ['contrainte' => 'adr']));
        $this->assertSame(1, $liste['orders']['total']);
        $this->assertSame($dangereuse->id, $liste['orders']['data'][0]['id']);
    }

    public function test_un_jour_du_calendrier_ouvre_l_onglet_ou_sont_ses_enlevements(): void
    {
        $demain = now()->addDay()->setTime(8, 0);
        $apresDemain = now()->addDays(2)->setTime(8, 0);
        $chauffeur = $this->chauffeur();

        TransportOrder::factory()->affectee()->create(['pickup_date' => $demain, 'driver_id' => $chauffeur->id]);
        TransportOrder::factory()->enRoute()->create(['pickup_date' => $demain, 'driver_id' => $chauffeur->id]);
        // Jour dont toutes les missions sont deja parties : l'onglet
        // « Affectees » serait vide.
        TransportOrder::factory()->enRoute()->create(['pickup_date' => $apresDemain, 'driver_id' => $chauffeur->id]);

        $jours = collect($this->props(User::factory()->planificateur()->create(), route('dashboard'))['calendrier']['jours'])
            ->keyBy('date');

        $this->assertSame(0, $jours[$demain->toDateString()]['a_affecter']);
        $this->assertSame(1, $jours[$demain->toDateString()]['a_partir']);
        $this->assertSame(0, $jours[$apresDemain->toDateString()]['a_partir']);
        $this->assertSame(1, $jours[$apresDemain->toDateString()]['enlevements']);
    }

    public function test_le_message_de_kilometrage_annonce_le_releve_affiche(): void
    {
        $camion = $this->camion('1-KMS-003', ['mileage' => 175661.64]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->from(route('vehicles.index'))
            ->patch(route('vehicles.update', $camion->registration), ['is_available' => true, 'mileage' => 170000])
            ->assertSessionHasErrors('mileage');

        $message = session('errors')->first('mileage');
        $this->assertStringContainsString('175 661 km', $message);
        $this->assertStringNotContainsString('175 662', $message);
    }

    public function test_la_recherche_trouve_les_expeditions_par_numero_de_tva(): void
    {
        $client = Client::factory()->create(['vat_number' => 'BE0456789123']);
        $ordre = TransportOrder::factory()->create(['client_id' => $client->id]);
        TransportOrder::factory()->create([
            'client_id' => Client::factory()->create(['vat_number' => 'BE0987654321'])->id,
        ]);

        $planificateur = User::factory()->planificateur()->create();

        foreach (['BE0456789123', '0456789123', 'be0456'] as $terme) {
            $liste = $this->props($planificateur, route('transport-orders.index', ['q' => $terme]));
            $this->assertSame(1, $liste['orders']['total'], $terme);
            $this->assertSame($ordre->id, $liste['orders']['data'][0]['id']);
        }
    }
}
