<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Localites des pays que GeoNames ne publie pas (GR.zip : HTTP 404). Pour
 * eux seuls, la localite se situe en ligne par Photon, le geocodeur que le
 * formulaire d'adresse utilise deja.
 *
 * L'emprise ne suffit pas a proteger le prix : le rectangle grec couvre
 * aussi l'Albanie, la Macedoine du Nord et la cote turque. Le point transmis
 * reste compare, a 30 km pres, a la localite nommee dans l'adresse ;
 * l'emprise borne la recherche et sert de liste d'inclusion.
 */
final class Geocodeur
{
    /** [lat min, lng min, lat max, lng max] : points extremes elargis d'environ 5 km. */
    public const EMPRISES = [
        // Gavdos 34,80 N · Ormenio 41,75 N · Othonoi 19,37 E · Strongyli 29,65 E
        'GR' => [34.75, 19.30, 41.80, 29.70],
    ];

    private const PHOTON = 'https://photon.komoot.io/api/';

    public static function couvre(string $pays): bool
    {
        return isset(self::EMPRISES[strtoupper(trim($pays))]);
    }

    public static function dansLEmprise(float $lat, float $lng, string $pays): bool
    {
        $e = self::EMPRISES[strtoupper(trim($pays))] ?? null;

        return $e !== null && $lat >= $e[0] && $lat <= $e[2] && $lng >= $e[1] && $lng <= $e[3];
    }

    /**
     * Avec le point du formulaire : seules les localites portant exactement
     * ce nom (le formulaire l'a tire du meme Photon), la plus proche du
     * point. Sans point (API, simulateur) : la plus pertinente.
     *
     * @throws GeocodageIndisponible
     */
    public static function localite(string $ville, string $pays, ?float $lat = null, ?float $lng = null): ?object
    {
        $candidats = self::candidats($ville, $pays);
        $nom = self::cle($ville);
        $memeNom = array_values(array_filter($candidats, fn (object $c) => self::cle($c->ville) === $nom));

        if ($lat === null || $lng === null) {
            return $memeNom[0] ?? $candidats[0] ?? null;
        }

        usort($memeNom, fn (object $a, object $b) => Tarificateur::distanceVol($lat, $lng, $a->lat, $a->lng)
            <=> Tarificateur::distanceVol($lat, $lng, $b->lat, $b->lng));

        return $memeNom[0] ?? null;
    }

    /**
     * @return array<int, object>
     *
     * @throws GeocodageIndisponible
     */
    private static function candidats(string $ville, string $pays): array
    {
        $pays = strtoupper(trim($pays));
        $ville = trim($ville);
        $emprise = self::EMPRISES[$pays] ?? null;

        if ($emprise === null || mb_strlen($ville) < 2) {
            return [];
        }

        $cle = 'geo:photon:'.$pays.':'.md5(mb_strtolower($ville));
        $trouves = Cache::get($cle);

        if (! is_array($trouves)) {
            $trouves = self::interroger($ville, $pays, $emprise);
            // Trouve : 30 jours. Vide : une heure. Panne : jamais en cache.
            Cache::put($cle, $trouves, $trouves === [] ? now()->addHour() : now()->addDays(30));
        }

        return array_map(fn (array $c) => (object) $c, $trouves);
    }

    /**
     * @param  array<int, float>  $emprise
     * @return array<int, array<string, mixed>>
     *
     * @throws GeocodageIndisponible
     */
    private static function interroger(string $ville, string $pays, array $emprise): array
    {
        // « layer » se repete : http_build_query ne sait pas l'ecrire.
        $url = self::PHOTON.'?'.http_build_query([
            'q' => $ville,
            'lang' => 'fr',
            'limit' => 10,
            'bbox' => implode(',', [$emprise[1], $emprise[0], $emprise[3], $emprise[2]]),
        ]).'&layer=city&layer=district&layer=locality';

        try {
            $reponse = Http::timeout(5)->acceptJson()->get($url);
        } catch (\Throwable $panne) {
            throw new GeocodageIndisponible('Photon injoignable.', 0, $panne);
        }

        if (! $reponse->ok()) {
            throw new GeocodageIndisponible('Photon HTTP '.$reponse->status().'.');
        }

        $trouves = [];

        foreach ((array) $reponse->json('features', []) as $f) {
            $nom = trim((string) ($f['properties']['name'] ?? ''));
            $point = $f['geometry']['coordinates'] ?? null;

            if ($nom === '' || ! is_array($point) || count($point) < 2
                || strtoupper((string) ($f['properties']['countrycode'] ?? '')) !== $pays) {
                continue;
            }

            [$lng, $lat] = [(float) $point[0], (float) $point[1]];

            if (self::dansLEmprise($lat, $lng, $pays)) {
                $trouves[] = ['ville' => $nom, 'region' => $f['properties']['state'] ?? null, 'lat' => $lat, 'lng' => $lng];
            }
        }

        return $trouves;
    }

    private static function cle(string $nom): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($nom))));
    }
}
