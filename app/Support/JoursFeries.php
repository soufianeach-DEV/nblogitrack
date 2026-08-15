<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Les dix jours feries legaux belges.
 *
 * Sept sont a date fixe, trois se calent sur Paques : le lundi de
 * Paques, l'Ascension le jeudi quarante jours apres, et le lundi de
 * Pentecote cinquante jours apres. Paques se calcule par l'algorithme
 * de Meeus plutot que par easter_date(), qui exige l'extension
 * calendar et manquerait sur un hebergement mal servi.
 */
class JoursFeries
{
    private const FIXES = [
        '01-01' => 'Jour de l\'An',
        '05-01' => 'Fete du Travail',
        '07-21' => 'Fete nationale',
        '08-15' => 'Assomption',
        '11-01' => 'Toussaint',
        '11-11' => 'Armistice',
        '12-25' => 'Noel',
    ];

    /**
     * Les feries d'une annee, indexes par date au format Y-m-d.
     *
     * @return array<string, string>
     */
    public static function pour(int $annee): array
    {
        $feries = [];

        foreach (self::FIXES as $jour => $nom) {
            $feries[$annee.'-'.$jour] = $nom;
        }

        $paques = self::paques($annee);

        $feries[$paques->copy()->addDay()->toDateString()] = 'Lundi de Paques';
        $feries[$paques->copy()->addDays(39)->toDateString()] = 'Ascension';
        $feries[$paques->copy()->addDays(50)->toDateString()] = 'Lundi de Pentecote';

        ksort($feries);

        return $feries;
    }

    public static function nom(CarbonInterface $date): ?string
    {
        return self::pour((int) $date->format('Y'))[$date->toDateString()] ?? null;
    }

    public static function est(CarbonInterface $date): bool
    {
        return self::nom($date) !== null;
    }

    /**
     * Un jour ou l'entreprise ne charge pas : dimanche ou ferie. Le samedi
     * reste ouvre, le transport y travaille.
     */
    public static function chome(CarbonInterface $date): bool
    {
        return $date->isSunday() || self::est($date);
    }

    /** Le premier jour ouvrable a partir de la date donnee, elle comprise. */
    public static function prochainJourOuvrable(CarbonInterface $date): Carbon
    {
        $curseur = Carbon::parse($date)->startOfDay();

        while (self::chome($curseur)) {
            $curseur->addDay();
        }

        return $curseur;
    }

    /**
     * Dimanche de Paques par l'algorithme de Meeus, Jones et Butcher,
     * valable pour le calendrier gregorien.
     */
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
