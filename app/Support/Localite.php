<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Localite
{
    private const EQUIVALENTS = [
        'alost' => 'Aalst',
        'anvers' => 'Antwerpen',
        'audenarde' => 'Oudenaarde',
        'bruges' => 'Brugge',
        'courtrai' => 'Kortrijk',
        'furnes' => 'Veurne',
        'gand' => 'Gent',
        'grammont' => 'Geraardsbergen',
        'hal' => 'Halle',
        'looz' => 'Borgloon',
        'louvain' => 'Leuven',
        'malines' => 'Mechelen',
        'ostende' => 'Oostende',
        'roulers' => 'Roeselare',
        'saint-nicolas' => 'Sint-Niklaas',
        'saint-trond' => 'Sint-Truiden',
        'termonde' => 'Dendermonde',
        'tirlemont' => 'Tienen',
        'tongres' => 'Tongeren',
        'vilvorde' => 'Vilvoorde',
        'ypres' => 'Ieper',
    ];

    public static function locale(string $ville): string
    {
        return self::EQUIVALENTS[mb_strtolower(trim($ville))] ?? trim($ville);
    }

    /**
     * $lat et $lng : le point transmis, utilise seulement pour un pays
     * verifie en ligne (voir Geocodeur).
     *
     * @throws GeocodageIndisponible
     */
    public static function coordonnees(string $ville, string $pays, ?float $lat = null, ?float $lng = null): ?object
    {
        $pays = strtoupper(trim($pays));

        if (self::enLigne($pays)) {
            return Geocodeur::localite($ville, $pays, $lat, $lng);
        }

        // Le nom saisi d'abord, puis son equivalent local : « Gand » est
        // dans la table des codes postaux sous son nom francais, « Gent »
        // aussi selon la source. Chercher seulement « Gent » refusait une
        // ville que la liste de suggestions venait de proposer.
        // « % » ou « _ » ne sont pas des localites : sans echappement, une
        // adresse « 1000 % » trouvait la premiere ville du pays.
        $noms = array_values(array_unique([trim($ville), self::locale($ville)]));
        $motifs = array_map(fn (string $nom) => addcslashes($nom, '\\%_'), $noms);
        $filtre = function ($requete) use ($motifs) {
            foreach ($motifs as $motif) {
                $requete->orWhere('city', 'ilike', $motif);
            }
        };

        // Avec un point, la ligne la plus proche : deux Saint-Denis, deux
        // Neustadt, ne se confondent plus au centre de leur moyenne.
        if ($lat !== null && $lng !== null) {
            return DB::table('postal_codes')
                ->selectRaw('city AS ville, lat, lng, region')
                ->where('country_code', $pays)
                ->where($filtre)
                ->orderByRaw('power(lat - ?, 2) + power((lng - ?) * cos(radians(?)), 2)', [$lat, $lng, $lat])
                ->first();
        }

        return DB::table('postal_codes')
            ->selectRaw('MIN(city) AS ville, AVG(lat) AS lat, AVG(lng) AS lng, MIN(region) AS region')
            ->where('country_code', $pays)
            ->where($filtre)
            ->groupBy('city')
            ->orderByRaw('CASE WHEN MIN(city) ILIKE ? THEN 0 ELSE 1 END', [$motifs[0]])
            ->first();
    }

    /** La region GeoNames d'un code postal (le Land allemand). */
    public static function region(string $pays, string $codePostal): ?string
    {
        $region = DB::table('postal_codes')
            ->where('country_code', strtoupper($pays))
            ->where('code', trim($codePostal))
            ->value('region');

        return $region === null ? null : (string) $region;
    }

    /**
     * Pays dont GeoNames ne publie pas les codes postaux : liste dans
     * Geocodeur::EMPRISES et sans aucune ligne dans postal_codes. Si les
     * donnees arrivent un jour, la verification stricte reprend la main.
     */
    /**
     * La localite du referentiel la plus proche d'un point, a moins de
     * $rayon km : une ville choisie dans une liste sous son nom francais
     * (« Cologne ») se retrouve sous son nom local (« Köln »).
     */
    public static function plusProche(string $pays, float $lat, float $lng, float $rayon = 15): ?object
    {
        $point = DB::table('postal_codes')
            ->selectRaw('city AS ville, lat, lng, region')
            ->where('country_code', strtoupper(trim($pays)))
            ->orderByRaw('power(lat - ?, 2) + power((lng - ?) * cos(radians(?)), 2)', [$lat, $lng, $lat])
            ->first();

        if ($point === null || Tarificateur::distanceVol($lat, $lng, (float) $point->lat, (float) $point->lng) > $rayon) {
            return null;
        }

        return $point;
    }

    public static function enLigne(string $pays): bool
    {
        $pays = strtoupper(trim($pays));

        return Geocodeur::couvre($pays)
            && ! DB::table('postal_codes')->where('country_code', $pays)->exists();
    }

    /**
     * @return array<int, string>
     */
    public static function suggestions(string $debut): array
    {
        $debut = mb_strtolower(trim($debut));

        if ($debut === '') {
            return [];
        }

        $trouves = [];

        foreach (self::EQUIVALENTS as $francais => $local) {
            if (str_starts_with($francais, $debut)) {
                $trouves[] = $local;
            }
        }

        return $trouves;
    }
}
