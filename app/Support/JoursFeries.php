<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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

    /**
     * @return array<string, string>
     */
    public static function pour(int $annee): array
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

    public static function nom(CarbonInterface $date): ?string
    {
        return self::pour((int) $date->format('Y'))[$date->toDateString()] ?? null;
    }

    public static function est(CarbonInterface $date): bool
    {
        return self::nom($date) !== null;
    }

    public static function chome(CarbonInterface $date): bool
    {
        return $date->isSunday() || self::est($date);
    }

    public static function prochainJourOuvrable(CarbonInterface $date): Carbon
    {
        $curseur = Carbon::parse($date)->startOfDay();

        while (self::chome($curseur)) {
            $curseur->addDay();
        }

        return $curseur;
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
