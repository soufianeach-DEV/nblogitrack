<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Client;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * GeoNames ne publie pas les codes postaux grecs : la localite d'une
 * livraison en Grece se verifie par Photon, avec la meme regle des 30 km
 * que partout ailleurs.
 */
class GreceSansReferentielTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $photon = [];

    private int $statutPhoton = 200;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('postal_codes')->insert([
            ['country_code' => 'BE', 'code' => '3500', 'city' => 'Hasselt', 'lat' => 50.9311, 'lng' => 5.3378],
            ['country_code' => 'BE', 'code' => '1000', 'city' => 'Bruxelles', 'lat' => 50.8504, 'lng' => 4.3488],
        ]);

        $athenes = [
            ['name' => 'Athènes', 'state' => 'Attique', 'lat' => 37.9838, 'lng' => 23.7275],
            ['name' => 'Athènes', 'state' => 'Géorgie', 'lat' => 33.9519, 'lng' => -83.3576, 'pays' => 'US'],
        ];
        $this->photon = [
            'Athènes' => $athenes,
            'Athina' => $athenes,
            'Agios Nikolaos' => [
                ['name' => 'Agios Nikolaos', 'state' => 'Crète', 'lat' => 35.1900, 'lng' => 25.7164],
                ['name' => 'Agios Nikolaos', 'state' => 'Macédoine-Centrale', 'lat' => 40.2476, 'lng' => 23.6717],
            ],
        ];

        Http::fake(function (Request $requete) {
            if (str_contains($requete->url(), 'photon.komoot.io')) {
                if ($this->statutPhoton !== 200) {
                    return Http::response('', $this->statutPhoton);
                }

                parse_str((string) parse_url($requete->url(), PHP_URL_QUERY), $q);

                return Http::response(['type' => 'FeatureCollection', 'features' => array_map(fn (array $v) => [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [$v['lng'], $v['lat']]],
                    'properties' => ['name' => $v['name'], 'state' => $v['state'], 'countrycode' => $v['pays'] ?? 'GR', 'type' => 'city'],
                ], $this->photon[$q['q'] ?? ''] ?? [])]);
            }

            if (str_contains($requete->url(), 'router.project-osrm.org')) {
                return Http::response(['code' => 'Ok', 'routes' => [['distance' => 2_900_000]]]);
            }

            return Http::response([], 404);
        });

        TariffGrid::factory()->zone('GR', 'Grèce')->create();
    }

    /** @return array<string, mixed> */
    private function commande(array $remplace = []): array
    {
        return array_merge([
            'pickup_address' => 'Rue Neuve 43, 3500 Hasselt, Belgique',
            'delivery_address' => 'Ermou 10, 105 57 Athènes, Grèce',
            'delivery_country' => 'GR',
            'pickup_lat' => 50.9311,
            'pickup_lng' => 5.3378,
            'delivery_lat' => 37.9755,
            'delivery_lng' => 23.7348,
            'weight' => 1200,
            'goods_type' => TransportOrder::MARCHANDISES[0],
            'priority' => 'NORMAL',
            'requested_delivery_date' => now()->addDays(10)->toDateString(),
            'tariff_grid_id' => TariffGrid::where('zone', 'GR')->value('id'),
        ], $remplace);
    }

    private function deposer(array $remplace = [])
    {
        return $this->actingAs(Client::factory()->create()->compte())
            ->post(route('transport-orders.store'), $this->commande($remplace));
    }

    /** @return array{0: Client, 1: string} */
    private function jeton(): array
    {
        $entreprise = Client::factory()->create();
        [, $jeton] = ApiKey::generer([
            'name' => 'Clé grecque',
            'client_id' => $entreprise->id,
            'abilities' => ['lecture', 'ecriture'],
            'created_by' => User::factory()->administrateur()->create()->id,
        ]);

        return [$entreprise, $jeton];
    }

    /** @return array<string, mixed> */
    private function corpsApi(): array
    {
        return [
            'enlevement' => 'Rue Neuve 43, 3500 Hasselt',
            'livraison' => 'Ermou 10, 105 57 Athina',
            'pays_livraison' => 'GR',
            'poids' => 850,
            'marchandise' => TransportOrder::MARCHANDISES[0],
            'date_enlevement' => now()->addDays(2)->toDateString(),
            'date_livraison' => now()->addDays(9)->toDateString(),
        ];
    }

    public function test_une_commande_vers_athenes_est_deposee(): void
    {
        $this->deposer()->assertSessionHasNoErrors();

        $ordre = TransportOrder::firstOrFail();
        $this->assertSame(2900, (int) $ordre->distance_km);
        $this->assertEqualsWithDelta(37.9755, (float) $ordre->delivery_lat, 0.0001);
    }

    public function test_un_point_deplace_dans_l_emprise_grecque_est_refuse(): void
    {
        // Florina : en Grece, dans l'emprise, a quelque 300 km d'Athenes.
        $this->deposer(['delivery_lat' => 40.7818, 'delivery_lng' => 21.4098])
            ->assertSessionHasErrors(['delivery_address' => 'L\'adresse de livraison ne correspond pas au point transmis. Resélectionnez-la dans la liste de suggestions.']);

        $this->assertSame(0, TransportOrder::count());
    }

    public function test_un_point_en_albanie_dans_le_rectangle_grec_est_refuse(): void
    {
        // Tirana : dans le rectangle grec, mais a 500 km d'Athenes.
        $this->deposer(['delivery_lat' => 41.3275, 'delivery_lng' => 19.8187])
            ->assertSessionHasErrors('delivery_address');

        $this->assertSame(0, TransportOrder::count());
    }

    public function test_un_homonyme_se_verifie_contre_le_plus_proche(): void
    {
        $this->deposer([
            'delivery_address' => 'Akti Koundourou 5, 721 00 Agios Nikolaos, Grèce',
            'delivery_lat' => 35.1880,
            'delivery_lng' => 25.7190,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, TransportOrder::count());
    }

    public function test_une_localite_inconnue_de_photon_est_refusee(): void
    {
        $this->deposer(['delivery_address' => 'Odos 1, 999 99 Zzzzville, Grèce'])
            ->assertSessionHasErrors(['delivery_address' => 'L\'adresse de livraison ne correspond à aucune localité connue. Choisissez-la dans la liste de suggestions.']);

        $this->assertSame(0, TransportOrder::count());
    }

    public function test_photon_en_panne_refuse_sans_rien_enregistrer(): void
    {
        $this->statutPhoton = 503;

        $this->deposer()
            ->assertSessionHasErrors(['delivery_address' => 'La vérification de l\'adresse est momentanément indisponible. Réessayez dans quelques minutes.']);

        $this->assertSame(0, TransportOrder::count());
    }

    public function test_un_pays_sans_donnees_hors_liste_reste_refuse_sans_photon(): void
    {
        $italie = TariffGrid::factory()->zone('IT', 'Italie')->create();

        $this->deposer([
            'delivery_address' => 'Via Roma 1, 00100 Roma, Italie',
            'delivery_country' => 'IT',
            'delivery_lat' => 41.9028,
            'delivery_lng' => 12.4964,
            'tariff_grid_id' => $italie->id,
        ])->assertSessionHasErrors('delivery_address');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'photon.komoot.io'));
    }

    public function test_des_codes_postaux_grecs_importes_reprennent_la_main(): void
    {
        DB::table('postal_codes')->insert(['country_code' => 'GR', 'code' => '10557', 'city' => 'Athina', 'lat' => 37.9795, 'lng' => 23.7162]);

        $this->deposer(['delivery_address' => 'Ermou 10, 10557 Athina, Grèce'])->assertSessionHasNoErrors();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'photon.komoot.io'));
    }

    public function test_l_api_depose_une_livraison_en_grece(): void
    {
        [$entreprise, $jeton] = $this->jeton();

        $this->postJson('/api/v1/expeditions', $this->corpsApi(), ['Authorization' => 'Bearer '.$jeton])->assertCreated();

        $this->assertEqualsWithDelta(37.9838, (float) TransportOrder::where('client_id', $entreprise->id)->firstOrFail()->delivery_lat, 0.0001);
    }

    public function test_l_api_signale_une_panne_de_photon_par_un_503(): void
    {
        $this->statutPhoton = 503;
        [, $jeton] = $this->jeton();

        $this->postJson('/api/v1/expeditions', $this->corpsApi(), ['Authorization' => 'Bearer '.$jeton])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '120');

        $this->assertSame(0, TransportOrder::count());
    }

    public function test_le_simulateur_tarife_la_grece(): void
    {
        $reponse = $this->postJson(route('tarifs.simuler'), ['depart' => 'Bruxelles', 'destination' => 'Athènes', 'pays' => 'GR', 'poids' => 500])
            ->assertOk()
            ->assertJsonPath('arrivee', 'Athènes')
            ->assertJsonPath('distance', 2900);

        $this->assertSame(
            TariffGrid::where('zone', 'GR')->where('is_active', true)->count(),
            count($reponse->json('formules')),
        );
    }

    public function test_le_simulateur_signale_une_panne_de_photon(): void
    {
        $this->statutPhoton = 503;

        $this->postJson(route('tarifs.simuler'), ['depart' => 'Bruxelles', 'destination' => 'Athènes', 'pays' => 'GR', 'poids' => 500])
            ->assertStatus(503)
            ->assertJsonPath('erreur', 'Le service est momentanément indisponible.');
    }

    public function test_la_page_tarifs_marque_la_grece_en_ligne(): void
    {
        TariffGrid::factory()->create();

        $this->get(route('tarifs.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Tarifs/Index')
            ->where('destinations', fn ($d) => collect($d)->firstWhere('code', 'GR')['en_ligne'] === true
                && collect($d)->firstWhere('code', 'BE')['en_ligne'] === false));
    }
}
