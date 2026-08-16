<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Localite
{
    /**
     * Les villes belges portent deux noms officiels, et la base de codes
     * postaux ne connait que le nom local : Antwerpen, Gent, Brugge. Un
     * visiteur francophone qui tape Anvers ne trouvait rien, alors que les
     * adresses de l'application, elles, sont en francais.
     *
     * Chaque equivalent a ete confronte a la base : n'y figurent que les
     * localites reellement stockees sous leur nom neerlandais. Enghien,
     * Lessines, Renaix et Waremme y sont sous leur nom francais — les
     * traduire les aurait rendues introuvables. Mons, Namur ou Charleroi
     * portent le meme nom dans les deux langues et n'ont rien a y faire.
     */
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

    /**
     * Le nom sous lequel la base connait cette localite.
     */
    public static function locale(string $ville): string
    {
        return self::EQUIVALENTS[mb_strtolower(trim($ville))] ?? trim($ville);
    }

    /**
     * Les coordonnees d'une localite, moyennees sur ses codes postaux.
     *
     * Six cent dix mille codes sont importes localement : la recherche ne
     * sort pas de l'application et ne depend d'aucun service exterieur.
     *
     * Le point rendu est le centre approximatif de la localite, pas une
     * adresse. C'est suffisant pour tarifer, puisque le prix depend de la
     * distance entre deux villes et non du numero dans la rue.
     */
    public static function coordonnees(string $ville, string $pays): ?object
    {
        return DB::table('postal_codes')
            ->selectRaw('MIN(city) AS ville, AVG(lat) AS lat, AVG(lng) AS lng')
            ->where('country_code', strtoupper(trim($pays)))
            ->where('city', 'ilike', self::locale($ville))
            ->groupBy('city')
            ->first();
    }

    /**
     * Les noms locaux dont la version francaise commence par ce debut de
     * saisie : taper « anv » doit proposer Antwerpen.
     *
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
