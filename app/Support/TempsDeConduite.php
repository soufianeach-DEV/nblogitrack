<?php

namespace App\Support;

use App\Models\TransportOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class TempsDeConduite
{
    public const VITESSE_MOYENNE = 65.0;

    public const CONDUITE_CONTINUE_MAX = 4.5;

    public const PAUSE = 0.75;

    public const CONDUITE_JOUR_MAX = 9.0;

    public const CONDUITE_SEMAINE_MAX = 56.0;

    public const JOURS_CONSECUTIFS_MAX = 6;

    public const REPOS_JOURNALIER = 11.0;

    public static function heuresDeConduite(?int $km): float
    {
        return $km === null ? 0.0 : round($km / self::VITESSE_MOYENNE, 2);
    }

    public static function nombreDePauses(float $heures): int
    {
        return $heures <= self::CONDUITE_CONTINUE_MAX
            ? 0
            : (int) ceil($heures / self::CONDUITE_CONTINUE_MAX) - 1;
    }

    public static function dureeTotale(?int $km): float
    {
        $conduite = self::heuresDeConduite($km);

        return round($conduite + self::nombreDePauses($conduite) * self::PAUSE, 2);
    }

    public static function journees(?int $km): int
    {
        return max(1, (int) ceil(self::heuresDeConduite($km) / self::CONDUITE_JOUR_MAX));
    }

    public static function resume(?int $km): string
    {
        if ($km === null) {
            return 'Distance inconnue';
        }

        if ($km === 0) {
            return 'Course intra-urbaine';
        }

        $conduite = self::heuresDeConduite($km);
        $pauses = self::nombreDePauses($conduite);
        $texte = self::enHeures($conduite).' de conduite';

        if ($pauses > 0) {
            $texte .= ' + '.$pauses.' pause'.($pauses > 1 ? 's' : '').' de 45 min';
        }

        if (($jours = self::journees($km)) > 1) {
            $texte .= ' sur '.$jours.' jours';
        }

        return $texte;
    }

    public static function enHeures(float $heures): string
    {
        $h = (int) floor($heures);
        $min = (int) round(($heures - $h) * 60);

        if ($min === 60) {
            $h++;
            $min = 0;
        }

        return $min === 0 ? $h.' h' : $h.' h '.str_pad((string) $min, 2, '0', STR_PAD_LEFT);
    }

    public static function conduiteDeLaSemaine(int $chauffeurId, CarbonInterface $date, ?int $ordreExclu = null): float
    {
        $km = TransportOrder::where('driver_id', $chauffeurId)
            ->where('status', '!=', 'CANCELLED')
            ->when($ordreExclu, fn ($q) => $q->where('id', '!=', $ordreExclu))
            ->whereBetween('pickup_date', [
                $date->copy()->startOfWeek(),
                $date->copy()->endOfWeek(),
            ])
            ->sum('distance_km');

        return self::heuresDeConduite((int) $km);
    }

    public static function joursConsecutifsAvant(int $chauffeurId, CarbonInterface $date): int
    {
        $journees = TransportOrder::where('driver_id', $chauffeurId)
            ->where('status', '!=', 'CANCELLED')
            ->whereNotNull('pickup_date')
            ->where('pickup_date', '<', $date->copy()->startOfDay())
            ->where('pickup_date', '>=', $date->copy()->subDays(self::JOURS_CONSECUTIFS_MAX + 1)->startOfDay())
            ->selectRaw('DISTINCT pickup_date::date AS jour')
            ->pluck('jour')
            ->map(fn ($j) => Carbon::parse($j)->toDateString())
            ->flip();

        $suite = 0;
        $curseur = $date->copy()->subDay()->startOfDay();

        while ($journees->has($curseur->toDateString())) {
            $suite++;
            $curseur->subDay();
        }

        return $suite;
    }

    /**
     * @return list<string>
     */
    public static function empechements(int $chauffeurId, ?int $km, ?CarbonInterface $enlevement, ?int $ordreExclu = null): array
    {
        if ($enlevement === null) {
            return [];
        }

        $motifs = [];
        $conduite = self::heuresDeConduite($km);

        $semaine = self::conduiteDeLaSemaine($chauffeurId, $enlevement, $ordreExclu);

        if ($semaine + $conduite > self::CONDUITE_SEMAINE_MAX) {
            $motifs[] = sprintf(
                'plafond hebdomadaire depasse : %s deja engagees plus %s pour cette mission, maximum %s',
                self::enHeures($semaine), self::enHeures($conduite), self::enHeures(self::CONDUITE_SEMAINE_MAX),
            );
        }

        if (self::joursConsecutifsAvant($chauffeurId, $enlevement) >= self::JOURS_CONSECUTIFS_MAX) {
            $motifs[] = sprintf(
                'septieme journee d\'affilee : le repos hebdomadaire doit commencer apres %d jours',
                self::JOURS_CONSECUTIFS_MAX,
            );
        }

        return $motifs;
    }
}
