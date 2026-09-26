<?php

namespace App\Support;

use App\Models\TransportOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TempsDeConduite
{
    public const VITESSE_MOYENNE = 65.0;

    public const CONDUITE_CONTINUE_MAX = 4.5;

    public const PAUSE = 0.75;

    public const CONDUITE_JOUR_MAX = 9.0;

    public const CONDUITE_SEMAINE_MAX = 56.0;

    public const JOURS_CONSECUTIFS_MAX = 6;

    public const REPOS_JOURNALIER = 11.0;

    /** Journee de service : 24 h moins le repos journalier de 11 h. */
    public const AMPLITUDE_MAX = 13.0;

    public static function heuresDeConduite(?int $km): float
    {
        return $km === null ? 0.0 : round($km / self::VITESSE_MOYENNE, 2);
    }

    /**
     * Pauses de 45 min sur toute la mission. Le repos journalier remet le
     * compteur a zero : 737 km (9 h puis 2 h 20 le lendemain) ne demandent
     * qu'une pause, pas deux.
     */
    public static function nombreDePauses(float $heures): int
    {
        $journees = (int) floor($heures / self::CONDUITE_JOUR_MAX);
        $reste = $heures - $journees * self::CONDUITE_JOUR_MAX;

        return $journees * self::pausesDuJour(self::CONDUITE_JOUR_MAX) + self::pausesDuJour($reste);
    }

    private static function pausesDuJour(float $heures): int
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
            return Traductions::t('planif.distance_inconnue', 'Distance inconnue');
        }

        if ($km === 0) {
            return Traductions::t('planif.course_urbaine', 'Course intra-urbaine');
        }

        $conduite = self::heuresDeConduite($km);
        $pauses = self::nombreDePauses($conduite);
        $texte = Traductions::t('planif.duree_conduite', ':duree de conduite', ['duree' => self::enHeures($conduite)]);

        if ($pauses > 0) {
            $texte .= ' '.($pauses > 1
                ? Traductions::t('planif.pauses', '+ :n pauses de 45 min', ['n' => $pauses])
                : Traductions::t('planif.pause', '+ :n pause de 45 min', ['n' => $pauses]));
        }

        if (($jours = self::journees($km)) > 1) {
            $texte .= ' '.Traductions::t('planif.sur_jours', 'sur :n jours', ['n' => $jours]);
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

    /**
     * Les missions d'un chauffeur qui demarrent entre deux jours, avec leur
     * vrai jour de depart : une mission affectee dont l'enlevement prevu
     * est passe partira aujourd'hui, une mission chargee la veille compte
     * le jour du chargement (voir ControleAffectation::occupation).
     *
     * @return Collection<int, array{jour: string, cle: string, km: int}>
     */
    private static function departs(int $chauffeurId, CarbonInterface $du, CarbonInterface $au, ?int $ordreExclu): Collection
    {
        return TransportOrder::where('driver_id', $chauffeurId)
            ->when($ordreExclu, fn ($q) => $q->where('id', '!=', $ordreExclu))
            ->where(fn ($q) => $q->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
                ->orWhere(fn ($l) => $l->where('status', 'DELIVERED')
                    ->whereBetween('pickup_date', [$du->copy()->startOfDay(), $au->copy()->endOfDay()])))
            ->get(['id', 'status', 'pickup_date', 'picked_up_at', 'distance_km', 'approche_km', 'vehicle_registration', 'pickup_lat', 'pickup_lng', 'delivery_lat', 'delivery_lng'])
            ->map(fn (TransportOrder $m) => [
                'jour' => ($m->status === 'DELIVERED'
                    ? Carbon::instance($m->picked_up_at ?? $m->pickup_date)->startOfDay()
                    : ControleAffectation::occupation($m)[0])->toDateString(),
                'cle' => self::tournee($m->vehicle_registration, $m),
                'km' => (int) $m->distance_km + (int) $m->approche_km,
            ])
            ->filter(fn (array $d) => $d['jour'] >= $du->toDateString() && $d['jour'] <= $au->toDateString())
            ->values();
    }

    /**
     * Deux envois du meme camion sur le meme trajet le meme jour (groupage)
     * ne font qu'une route : ils ne comptent qu'une fois.
     */
    private static function tournee(?string $camion, TransportOrder $o): string
    {
        return implode('|', [
            $camion ?? '-',
            round((float) $o->pickup_lat, 2), round((float) $o->pickup_lng, 2),
            round((float) $o->delivery_lat, 2), round((float) $o->delivery_lng, 2),
        ]);
    }

    /**
     * @param  Collection<int, array{jour: string, cle: string, km: int}>  $departs
     */
    private static function heures(Collection $departs, bool $premierJour): float
    {
        return (float) $departs->groupBy(fn (array $d) => $d['jour'].'#'.$d['cle'])
            ->sum(fn (Collection $g) => $premierJour
                ? min(self::heuresDeConduite($g->max('km')), self::CONDUITE_JOUR_MAX)
                : self::heuresDeConduite($g->max('km')));
    }

    public static function conduiteDeLaSemaine(int $chauffeurId, CarbonInterface $date, ?int $ordreExclu = null): float
    {
        return self::heures(self::departs($chauffeurId, $date->copy()->startOfWeek(), $date->copy()->endOfWeek(), $ordreExclu), false);
    }

    /**
     * Conduite deja prevue le jour de l'enlevement : la premiere journee de
     * chaque mission qui commence ce jour-la (une mission de deux jours ne
     * compte que neuf heures le premier jour).
     */
    public static function conduiteDuJour(int $chauffeurId, CarbonInterface $date, ?int $ordreExclu = null): float
    {
        return self::heures(self::departs($chauffeurId, $date, $date, $ordreExclu), true);
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
    public static function empechements(int $chauffeurId, ?int $km, ?CarbonInterface $enlevement, ?int $ordreExclu = null, ?TransportOrder $candidat = null, ?string $camion = null): array
    {
        if ($enlevement === null) {
            return [];
        }

        $motifs = [];
        $conduite = self::heuresDeConduite($km);

        // Un envoi groupe sur le meme trajet, avec le meme camion, ne
        // rajoute pas de route : il ne compte pas une seconde fois.
        if ($candidat !== null && $camion !== null) {
            $cle = self::tournee($camion, $candidat);
            $jourDit = $enlevement->toDateString();

            if (self::departs($chauffeurId, $enlevement, $enlevement, $ordreExclu)->contains(fn (array $d) => $d['jour'] === $jourDit && $d['cle'] === $cle)) {
                $conduite = 0.0;
            }
        }

        $semaine = self::conduiteDeLaSemaine($chauffeurId, $enlevement, $ordreExclu);

        if ($semaine + $conduite > self::CONDUITE_SEMAINE_MAX) {
            $motifs[] = Traductions::t(
                'planif.plafond_hebdo',
                'plafond hebdomadaire dépassé : :semaine déjà engagées plus :mission pour cette mission, maximum :max',
                [
                    'semaine' => self::enHeures($semaine),
                    'mission' => self::enHeures($conduite),
                    'max' => self::enHeures(self::CONDUITE_SEMAINE_MAX),
                ],
            );
        }

        // Groupage : deux envois de 500 km le meme jour font plus de quinze
        // heures de volant ; le plafond journalier est de neuf heures.
        $jour = self::conduiteDuJour($chauffeurId, $enlevement, $ordreExclu);

        if ($jour > 0 && $conduite > 0 && $jour + min($conduite, self::CONDUITE_JOUR_MAX) > self::CONDUITE_JOUR_MAX) {
            $motifs[] = Traductions::t(
                'planif.plafond_jour',
                'plafond journalier dépassé : :jour déjà prévues ce jour-là plus :mission, maximum :max',
                [
                    'jour' => self::enHeures($jour),
                    'mission' => self::enHeures(min($conduite, self::CONDUITE_JOUR_MAX)),
                    'max' => self::enHeures(self::CONDUITE_JOUR_MAX),
                ],
            );
        }

        if (self::joursConsecutifsAvant($chauffeurId, $enlevement) >= self::JOURS_CONSECUTIFS_MAX) {
            $motifs[] = Traductions::t(
                'planif.septieme_jour',
                'septième journée d\'affilée : le repos hebdomadaire doit commencer après :n jours',
                ['n' => self::JOURS_CONSECUTIFS_MAX],
            );
        }

        return $motifs;
    }
}
