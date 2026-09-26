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

        return DB::table('postal_codes')
            ->selectRaw('MIN(city) AS ville, AVG(lat) AS lat, AVG(lng) AS lng')
            ->where('country_code', $pays)
            ->where('city', 'ilike', self::locale($ville))
            ->groupBy('city')
            ->first();
    }

    /**
     * Pays dont GeoNames ne publie pas les codes postaux : liste dans
     * Geocodeur::EMPRISES et sans aucune ligne dans postal_codes. Si les
     * donnees arrivent un jour, la verification stricte reprend la main.
     */
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
