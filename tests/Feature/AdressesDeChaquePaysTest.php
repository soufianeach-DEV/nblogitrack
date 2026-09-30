<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Support\Adresse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\GrillesDeDemonstration;
use Tests\TestCase;

/**
 * Une adresse reelle de chaque pays propose a la creation d'expedition,
 * ecrite comme le formulaire l'enregistre (« rue numero, code localite,
 * pays ») : code postal et localite relus, livraison depuis Bruxelles
 * commandee, enlevement vers Bruxelles commande ou renvoye au devis.
 */
class AdressesDeChaquePaysTest extends TestCase
{
    use GrillesDeDemonstration;
    use RefreshDatabase;

    /** pays => [rue et numero, code postal, localite, nom francais du pays, lat, lng] */
    private const ADRESSES = [
        'AT' => ['Stephansplatz 1', '1010', 'Wien', 'Autriche', 48.2085, 16.3721],
        'BE' => ['Rue de la Loi 16', '1000', 'Bruxelles', 'Belgique', 50.8455, 4.3700],
        'BG' => ['ul. Kukush 1', '1309', 'Sofia', 'Bulgarie', 42.7056, 23.2986],
        'CH' => ['Bahnhofstrasse 1', '8001', 'Zürich', 'Suisse', 47.3667, 8.5410],
        'CZ' => ['Václavské náměstí 1', '110 00', 'Praha', 'Tchéquie', 50.0833, 14.4254],
        'DE' => ['Unter den Linden 1', '10117', 'Berlin', 'Allemagne', 52.5170, 13.3960],
        'DK' => ['Rådhuspladsen 1', '1550', 'København', 'Danemark', 55.6759, 12.5695],
        'EE' => ['Vabaduse väljak 7', '10146', 'Tallinn', 'Estonie', 59.4339, 24.7449],
        'ES' => ['Calle de Alcalá 1', '28014', 'Madrid', 'Espagne', 40.4180, -3.7010],
        'FI' => ['Mannerheimintie 1', '00100', 'Helsinki', 'Finlande', 60.1686, 24.9414],
        'FR' => ['Rue de Rivoli 1', '75001', 'Paris', 'France', 48.8559, 2.3590],
        'GB' => ['Downing Street 10', 'SW1A 2AA', 'London', 'Royaume-Uni', 51.5034, -0.1276],
        'GR' => ['Ermou 1', '105 63', 'Athina', 'Grèce', 37.9755, 23.7348],
        'HR' => ['Ilica 1', '10000', 'Zagreb', 'Croatie', 45.8131, 15.9770],
        'HU' => ['Váci utca 1', '1052', 'Budapest', 'Hongrie', 47.4960, 19.0510],
        'IE' => ["O'Connell Street 1", 'D01 F5P2', 'Dublin', 'Irlande', 53.3498, -6.2603],
        'IT' => ['Via del Corso 1', '00186', 'Roma', 'Italie', 41.9009, 12.4768],
        'LT' => ['Gedimino pr. 1', 'LT-01103', 'Vilnius', 'Lituanie', 54.6872, 25.2797],
        'LU' => ['Grand-Rue 1', 'L-1661', 'Luxembourg', 'Luxembourg', 49.6116, 6.1300],
        'LV' => ['Brīvības iela 1', 'LV-1010', 'Rīga', 'Lettonie', 56.9496, 24.1052],
        'NL' => ['Dam 1', '1012 JS', 'Amsterdam', 'Pays-Bas', 52.3731, 4.8932],
        'NO' => ['Karl Johans gate 1', '0154', 'Oslo', 'Norvège', 59.9110, 10.7500],
        'PL' => ['ul. Marszałkowska 1', '00-624', 'Warszawa', 'Pologne', 52.2167, 21.0180],
        'PT' => ['Rua Augusta 1', '1100-048', 'Lisboa', 'Portugal', 38.7100, -9.1370],
        'RO' => ['Calea Victoriei 1', '030023', 'București', 'Roumanie', 44.4300, 26.0970],
        'SE' => ['Drottninggatan 1', '111 51', 'Stockholm', 'Suède', 59.3300, 18.0600],
        'SI' => ['Prešernov trg 1', '1000', 'Ljubljana', 'Slovénie', 46.0510, 14.5060],
        'SK' => ['Hlavné námestie 1', '811 01', 'Bratislava', 'Slovaquie', 48.1440, 17.1080],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Lundi 5 octobre 2026 : la date d'enlevement demandee (trois
        // semaines plus tard) laisse le temps d'atteindre chaque pays.
        $this->travelTo('2026-10-05 10:00');
        $this->creerLesGrillesDeDemonstration();

        // GeoNames ne publie pas la Grece : sa localite se verifie en
        // ligne (Photon), simule ici.
        Http::fake(function (Request $requete) {
            if (str_contains($requete->url(), 'photon.komoot.io')) {
                return Http::response(['type' => 'FeatureCollection', 'features' => [[
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [23.7348, 37.9755]],
                    'properties' => ['name' => 'Athina', 'state' => 'Attica', 'countrycode' => 'GR', 'type' => 'city'],
                ]]]);
            }

            return Http::response([], 503);
        });

        foreach (self::ADRESSES as $pays => [, $cp, $ville, , $lat, $lng]) {
            if ($pays !== 'GR') {
                DB::table('postal_codes')->insert(['country_code' => $pays, 'code' => $cp, 'city' => $ville, 'lat' => $lat, 'lng' => $lng]);
            }
        }
    }

    /** @return array<string, array{string}> */
    public static function pays(): array
    {
        return array_combine(array_keys(self::ADRESSES), array_map(fn ($p) => [$p], array_keys(self::ADRESSES)));
    }

    /** @return array<string, array{string}> */
    public static function paysEtrangers(): array
    {
        return array_diff_key(self::pays(), ['BE' => true]);
    }

    private static function adresse(string $pays): string
    {
        [$rue, $cp, $ville, $nom] = self::ADRESSES[$pays];

        return "{$rue}, {$cp} {$ville}, {$nom}";
    }

    /** @return array<string, mixed> */
    private function commande(string $depart, string $arrivee): array
    {
        $zone = $depart === 'BE' ? $arrivee : $depart;

        return [
            'pickup_address' => self::adresse($depart),
            'pickup_country' => $depart,
            'pickup_lat' => self::ADRESSES[$depart][4], 'pickup_lng' => self::ADRESSES[$depart][5],
            'delivery_address' => self::adresse($arrivee),
            'delivery_country' => $arrivee,
            'delivery_lat' => self::ADRESSES[$arrivee][4], 'delivery_lng' => self::ADRESSES[$arrivee][5],
            'weight' => 800,
            'goods_type' => 'Palettes',
            'priority' => 'NORMAL',
            'pickup_date' => '2026-10-27T09:00',
            'tariff_grid_id' => TariffGrid::where('zone', $zone)->where('is_active', true)->orderByRaw("service_level = 'STANDARD' DESC")->value('id'),
            'shipper_name' => 'Expéditeur test',
            'shipper_phone' => '+32 2 000 00 00',
        ];
    }

    private function commander(array $donnees)
    {
        return $this->actingAs(Client::factory()->create()->compte())->post(route('transport-orders.store'), $donnees);
    }

    #[DataProvider('pays')]
    public function test_l_adresse_est_relue(string $pays): void
    {
        [, $cp, $ville] = self::ADRESSES[$pays];
        $adresse = self::adresse($pays);

        $this->assertSame($cp, Adresse::codePostal($adresse));
        $this->assertSame($ville, Adresse::localite($adresse));
        $this->assertTrue(Adresse::paysCoherent($adresse, $pays, true));
    }

    #[DataProvider('pays')]
    public function test_une_livraison_depuis_bruxelles_est_commandee(string $pays): void
    {
        $this->assertNotNull(TariffGrid::where('zone', $pays)->where('is_active', true)->value('id'), "Aucune grille active pour {$pays}");

        $this->commander($this->commande('BE', $pays))->assertSessionHasNoErrors();

        $ordre = TransportOrder::latest('id')->firstOrFail();
        $this->assertSame($pays, $ordre->delivery_country);
        $this->assertSame(self::adresse($pays), $ordre->delivery_address);
    }

    #[DataProvider('paysEtrangers')]
    public function test_un_enlevement_vers_bruxelles_est_commande_ou_renvoye_au_devis(string $pays): void
    {
        $reponse = $this->commander($this->commande($pays, 'BE'));

        if (in_array($pays, config('fret.pays_enlevement'), true)) {
            $reponse->assertSessionHasNoErrors();
            $this->assertSame($pays, TransportOrder::latest('id')->value('pickup_country'));
        } else {
            $reponse->assertSessionHasErrors('pickup_address');
            $this->assertSame(0, TransportOrder::count());
        }
    }
}
