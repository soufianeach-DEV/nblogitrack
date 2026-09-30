<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Yasumi\Holiday;
use Yasumi\Yasumi;

/**
 * Jours feries du pays d'enlevement : quai ferme, et interdiction de
 * circuler pour les plus de 7,5 t en France et en Allemagne. La Belgique
 * garde sa liste ; les autres pays viennent de Yasumi, region comprise
 * (Lander allemands, Alsace-Moselle).
 */
class JoursFeries
{
    private const FIXES = [
        '01-01' => ['ferie.nouvel_an', 'Jour de l\'An'],
        '05-01' => ['ferie.travail', 'Fête du Travail'],
        '07-21' => ['ferie.nationale', 'Fête nationale'],
        '08-15' => ['ferie.assomption', 'Assomption'],
        '11-01' => ['ferie.toussaint', 'Toussaint'],
        '11-11' => ['ferie.armistice', 'Armistice'],
        '12-25' => ['ferie.noel', 'Noël'],
    ];

    /** Fournisseur Yasumi de chaque pays desservi. */
    private const PAYS = [
        'AT' => 'Austria', 'BG' => 'Bulgaria', 'CH' => 'Switzerland', 'CZ' => 'CzechRepublic', 'DE' => 'Germany',
        'DK' => 'Denmark', 'EE' => 'Estonia', 'ES' => 'Spain', 'FI' => 'Finland', 'FR' => 'France',
        'GB' => 'UnitedKingdom', 'GR' => 'Greece', 'HR' => 'Croatia', 'HU' => 'Hungary', 'IE' => 'Ireland',
        'IT' => 'Italy', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia', 'NL' => 'Netherlands',
        'NO' => 'Norway', 'PL' => 'Poland', 'PT' => 'Portugal', 'RO' => 'Romania', 'SE' => 'Sweden',
        'SI' => 'Slovenia', 'SK' => 'Slovakia',
    ];

    /** Nom GeoNames du Land -> fournisseur Yasumi. */
    private const LANDER = [
        'baden-wurttemberg' => 'BadenWurttemberg', 'bayern' => 'Bavaria', 'berlin' => 'Berlin',
        'brandenburg' => 'Brandenburg', 'bremen' => 'Bremen', 'hamburg' => 'Hamburg', 'hessen' => 'Hesse',
        'niedersachsen' => 'LowerSaxony', 'mecklenburg-vorpommern' => 'MecklenburgWesternPomerania',
        'nordrhein-westfalen' => 'NorthRhineWestphalia', 'rheinland-pfalz' => 'RhinelandPalatinate',
        'saarland' => 'Saarland', 'sachsen' => 'Saxony', 'sachsen-anhalt' => 'SaxonyAnhalt',
        'schleswig-holstein' => 'SchleswigHolstein', 'thuringen' => 'Thuringia',
    ];

    /** Departements d'Alsace-Moselle, par debut de code postal. */
    private const ALSACE_MOSELLE = ['57' => 'Moselle', '67' => 'BasRhin', '68' => 'HautRhin'];

    /** @var array<string, array<string, string>> */
    private static array $memoire = [];

    /**
     * @return array<string, string> date => nom
     */
    public static function pour(int $annee, string $pays = 'BE', ?string $region = null): array
    {
        $pays = strtoupper($pays);

        if ($pays === 'BE') {
            return self::belges($annee);
        }

        if (! isset(self::PAYS[$pays])) {
            return [];
        }

        // Land inconnu : tous les feries de tous les Lander. Prudent, au
        // prix de quelques jours bloques en trop.
        if ($pays === 'DE' && $region === null) {
            $feries = [];

            foreach (self::LANDER as $land) {
                $feries += self::yasumi('Germany/'.$land, $annee);
            }

            ksort($feries);

            return $feries;
        }

        return self::yasumi(self::PAYS[$pays].($region !== null ? '/'.$region : ''), $annee);
    }

    public static function nom(CarbonInterface $date, string $pays = 'BE', ?string $region = null): ?string
    {
        return self::pour((int) $date->format('Y'), $pays, $region)[$date->toDateString()] ?? null;
    }

    public static function est(CarbonInterface $date, string $pays = 'BE', ?string $region = null): bool
    {
        return self::nom($date, $pays, $region) !== null;
    }

    public static function chome(CarbonInterface $date, string $pays = 'BE', ?string $region = null): bool
    {
        return $date->isSunday() || self::est($date, $pays, $region);
    }

    public static function prochainJourOuvrable(CarbonInterface $date, string $pays = 'BE', ?string $region = null): Carbon
    {
        $curseur = Carbon::parse($date)->startOfDay();

        while (self::chome($curseur, $pays, $region)) {
            $curseur->addDay();
        }

        return $curseur;
    }

    /**
     * La region dont les feries s'appliquent : le Land allemand (colonne
     * region de GeoNames), le departement d'Alsace-Moselle (code postal).
     * Null : feries nationaux, ou tous les Lander pour l'Allemagne.
     */
    public static function region(string $pays, ?string $codePostal, ?string $regionGeonames): ?string
    {
        $pays = strtoupper($pays);

        if ($pays === 'DE' && $regionGeonames !== null) {
            return self::LANDER[Traductions::cleDepuis($regionGeonames)] ?? null;
        }

        if ($pays === 'FR' && $codePostal !== null) {
            return self::ALSACE_MOSELLE[substr(preg_replace('/\D/', '', $codePostal), 0, 2)] ?? null;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function belges(int $annee): array
    {
        $feries = [];

        foreach (self::FIXES as $jour => [$cle, $nom]) {
            $feries[$annee.'-'.$jour] = Traductions::t($cle, $nom);
        }

        $paques = self::paques($annee);

        $feries[$paques->copy()->addDay()->toDateString()] = Traductions::t('ferie.paques', 'Lundi de Pâques');
        $feries[$paques->copy()->addDays(39)->toDateString()] = Traductions::t('ferie.ascension', 'Ascension');
        $feries[$paques->copy()->addDays(50)->toDateString()] = Traductions::t('ferie.pentecote', 'Lundi de Pentecôte');

        ksort($feries);

        return $feries;
    }

    /**
     * Feries officiels et bancaires (pas les simples celebrations), nommes
     * dans la langue de l'ecran, en anglais si Yasumi n'a pas la traduction.
     *
     * @return array<string, string>
     */
    private static function yasumi(string $fournisseur, int $annee): array
    {
        $langue = app()->getLocale();
        $cle = $fournisseur.'|'.$annee.'|'.$langue;

        if (isset(self::$memoire[$cle])) {
            return self::$memoire[$cle];
        }

        $locale = ['fr' => 'fr_FR', 'nl' => 'nl_NL'][$langue] ?? 'en_US';
        $feries = [];

        foreach (Yasumi::create($fournisseur, $annee, $locale)->getHolidays() as $jour) {
            if (! in_array($jour->getType(), [Holiday::TYPE_OFFICIAL, Holiday::TYPE_BANK], true)) {
                continue;
            }

            $nom = $jour->getName();

            // Nom non traduit : Yasumi rend sa cle (« germanUnityDay »).
            if (preg_match('/^[a-z]+[A-Z]/', $nom)) {
                try {
                    $nom = $jour->getName(['en_US']);
                } catch (\Throwable) {
                    $nom = ucfirst(mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $nom)));
                }
            }

            $feries[$jour->format('Y-m-d')] = $nom;
        }

        ksort($feries);

        return self::$memoire[$cle] = $feries;
    }

    private static function paques(int $annee): Carbon
    {
        $a = $annee % 19;
        $b = intdiv($annee, 100);
        $c = $annee % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mois = intdiv($h + $l - 7 * $m + 114, 31);
        $jour = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($annee, $mois, $jour)->startOfDay();
    }
}
