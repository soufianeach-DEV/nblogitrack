<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Le trajet d'un camion, heure par heure, selon le reglement 561/2006 :
 * 4 h 30 de conduite puis 45 min de pause, 9 h de conduite par jour,
 * journee de service de 13 h au plus, repos journalier de 11 h, reprise
 * au plus tot a 6 h. Le chargement est du temps de travail (directive
 * 2002/15), ni conduite ni repos.
 *
 * Sert a deux questions : quand un camion parti du depot peut-il charger
 * a l'etranger au plus tot, et quand un camion qui vient de livrer peut-il
 * recharger a cote (fret retour). Aucune requete en base. Les instants
 * sont a l'heure de Bruxelles ; les quais et les interdictions de
 * circuler suivent l'heure locale du pays d'enlevement.
 */
final class Chronologie
{
    /** Pays ou les plus de 7,5 t ne roulent pas le dimanche ni les feries (hors France). */
    private const INTERDICTION_DIMANCHE = ['AT', 'CZ', 'DE', 'HR', 'HU', 'IT', 'LU', 'PL', 'SI', 'SK'];

    private Carbon $t;

    private Carbon $debutService;

    private float $conduiteJour = 0.0;

    private float $conduiteContinue = 0.0;

    private function __construct(CarbonInterface $depart, private readonly string $pays, private readonly ?string $region)
    {
        $this->t = Carbon::instance($depart)->setTimezone(config('app.timezone'));
        $this->debutService = $this->t->copy();
    }

    /**
     * Premier enlevement possible pour un camion envoye du depot : depart
     * au premier jour ouvre en Belgique apres la commande, a l'heure de
     * prise de service.
     */
    public static function premierEnlevement(string $pays, float $lat, float $lng, CarbonInterface $commande, ?string $region = null): Carbon
    {
        $chrono = new self(self::premierDepart($commande), strtoupper($pays), $region);
        $chrono->conduire(self::kmDepuisDepot($lat, $lng));

        return $chrono->charger();
    }

    /**
     * Fret retour : le camion a livre (fin de dechargement a $finLivraison),
     * se repose 11 h, roule $approcheKm, puis charge au premier quai ouvert.
     */
    public static function rechargementApres(CarbonInterface $finLivraison, float $approcheKm, string $pays, ?string $region = null): Carbon
    {
        $chrono = new self($finLivraison, strtoupper($pays), $region);
        $chrono->reposer();
        $chrono->conduire($approcheKm);

        return $chrono->charger(apresRepos: true);
    }

    /**
     * Fin du dechargement d'un camion charge a $chargement, pour $km de
     * route.
     */
    public static function finDeLivraison(CarbonInterface $chargement, float $km, string $paysLivraison = 'BE'): Carbon
    {
        $chrono = new self($chargement, strtoupper($paysLivraison), null);
        $chrono->travailler((float) config('fret.chrono.chargement_h', 1.0));
        $chrono->conduire($km);
        $chrono->travailler((float) config('fret.chrono.dechargement_h', 1.0));

        return $chrono->t->copy();
    }

    public static function kmDepuisDepot(float $lat, float $lng): float
    {
        $depot = config('fret.depot');

        return Tarificateur::distanceVol((float) $depot['lat'], (float) $depot['lng'], $lat, $lng) * (float) config('fret.facteur_route', 1.3);
    }

    /**
     * Premier jour de depart ouvre en Belgique apres la commande, un jour
     * plus tard si la commande arrive apres l'heure limite.
     */
    public static function premierDepart(CarbonInterface $commande): Carbon
    {
        $commande = Carbon::instance($commande)->setTimezone(config('app.timezone'));
        [$h, $m] = array_map('intval', explode(':', (string) config('fret.chrono.heure_limite', '16:00')));
        $jours = $commande->copy()->setTime($h, $m)->lt($commande) ? 2 : 1;
        $jour = $commande->copy()->startOfDay();

        while ($jours > 0) {
            $jour->addDay();

            if (self::jourDeDepart($jour)) {
                $jours--;
            }
        }

        return self::aLaPriseDeService($jour);
    }

    private static function jourDeDepart(CarbonInterface $jour): bool
    {
        return in_array($jour->dayOfWeekIso, config('fret.chrono.jours_depart', [1, 2, 3, 4, 5]), true)
            && ! JoursFeries::chome($jour);
    }

    private static function aLaPriseDeService(CarbonInterface $jour): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', (string) config('fret.chrono.prise_de_service', '06:00')));

        return Carbon::instance($jour)->copy()->setTime($h, $m);
    }

    private function conduire(float $km): void
    {
        $reste = max(0.0, $km) / TempsDeConduite::VITESSE_MOYENNE;

        while ($reste > 1e-6) {
            if (($fin = $this->finInterdiction($this->t)) !== null) {
                // Pas de reprise en pleine nuit a la fin d'une interdiction.
                $this->reprendreApres($fin);

                continue;
            }

            if ($this->conduiteJour >= TempsDeConduite::CONDUITE_JOUR_MAX - 1e-6
                || $this->heuresDeService() >= TempsDeConduite::AMPLITUDE_MAX - 1e-6) {
                $this->reposer();

                continue;
            }

            if ($this->conduiteContinue >= TempsDeConduite::CONDUITE_CONTINUE_MAX - 1e-6) {
                $this->t->addMinutes((int) round(TempsDeConduite::PAUSE * 60));
                $this->conduiteContinue = 0.0;

                continue;
            }

            $troncon = min(
                $reste,
                TempsDeConduite::CONDUITE_CONTINUE_MAX - $this->conduiteContinue,
                TempsDeConduite::CONDUITE_JOUR_MAX - $this->conduiteJour,
                TempsDeConduite::AMPLITUDE_MAX - $this->heuresDeService(),
                $this->heuresAvantInterdiction(),
            );

            $this->avancer($troncon);
            $this->conduiteJour += $troncon;
            $this->conduiteContinue += $troncon;
            $reste -= $troncon;
        }
    }

    private function travailler(float $heures): void
    {
        $this->avancer($heures);
    }

    /** Repos journalier de 11 h, reprise au plus tot a l'heure de prise de service. */
    private function reposer(): void
    {
        $this->reprendreApres($this->t->copy()->addMinutes((int) round(TempsDeConduite::REPOS_JOURNALIER * 60)));
    }

    private function reprendreApres(CarbonInterface $instant): void
    {
        $reprise = Carbon::instance($instant);
        $prise = self::aLaPriseDeService($reprise->copy()->startOfDay());

        if ($reprise->lt($prise)) {
            $reprise = $prise;
        } elseif ($reprise->gt($prise) && $reprise->hour >= 20) {
            $reprise = self::aLaPriseDeService($reprise->copy()->addDay()->startOfDay());
        }

        $this->t = $reprise;
        $this->debutService = $reprise->copy();
        $this->conduiteJour = 0.0;
        $this->conduiteContinue = 0.0;
    }

    /**
     * Premier chargement possible a partir de maintenant. Pas de repos
     * impose si le chargement tient dans la journee de service ; sinon,
     * ou si l'entreprise l'exige toujours, 11 h de repos avant.
     */
    private function charger(bool $apresRepos = false): Carbon
    {
        $chargement = (float) config('fret.chrono.chargement_h', 1.0);
        $quai = $this->quai($this->t);
        $toujours = config('fret.chrono.repos_apres_arrivee') === 'toujours' && ! $apresRepos;
        // Attendre le quai 11 h ou plus vaut repos journalier.
        $reposeEnAttendant = $this->t->diffInMinutes($quai) >= TempsDeConduite::REPOS_JOURNALIER * 60;
        $finDeService = $this->debutService->copy()->addMinutes((int) round(TempsDeConduite::AMPLITUDE_MAX * 60));

        if (! $reposeEnAttendant && ($toujours || $quai->copy()->addMinutes((int) round($chargement * 60))->gt($finDeService))) {
            $this->reposer();
            $quai = $this->quai($this->t);
        }

        return self::arrondir($quai);
    }

    /**
     * Le premier instant, a partir de $instant, ou le quai du lieu de
     * chargement est ouvert et laisse le temps de charger : jour ouvre du
     * pays, heures d'ouverture locales.
     */
    private function quai(CarbonInterface $instant): Carbon
    {
        $fuseau = Trajet::fuseau($this->pays);
        $local = Carbon::instance($instant)->setTimezone($fuseau);
        [$ouverture, $fermeture] = config('fret.chrono.quai', ['07:00', '17:00']);
        [$ho, $mo] = array_map('intval', explode(':', $ouverture));
        [$hf, $mf] = array_map('intval', explode(':', $fermeture));
        $chargement = (int) round((float) config('fret.chrono.chargement_h', 1.0) * 60);

        for ($i = 0; $i < 30; $i++) {
            $jour = $local->copy()->startOfDay();
            $debut = $jour->copy()->setTime($ho, $mo);
            $dernier = $jour->copy()->setTime($hf, $mf)->subMinutes($chargement);

            if (in_array($jour->dayOfWeekIso, config('fret.chrono.jours_enlevement', [1, 2, 3, 4, 5]), true)
                && ! JoursFeries::chome($jour, $this->pays, $this->region)
                && $local->lte($dernier)) {
                return ($local->lt($debut) ? $debut : $local)->setTimezone(config('app.timezone'));
            }

            $local = $jour->addDay()->setTime($ho, $mo);
        }

        return $local->setTimezone(config('app.timezone'));
    }

    /**
     * Fin de l'interdiction de circuler en cours a cet instant dans le
     * pays d'enlevement, ou null. France : du samedi (ou veille de ferie)
     * 22 h au dimanche (ou ferie) 22 h. Ailleurs : dimanche et feries,
     * jusqu'a 22 h.
     */
    private function finInterdiction(CarbonInterface $instant): ?Carbon
    {
        $local = Carbon::instance($instant)->setTimezone(Trajet::fuseau($this->pays));
        $chome = fn (CarbonInterface $jour) => $jour->isSunday() || JoursFeries::est($jour, $this->pays, $this->region);

        if ($this->pays === 'FR') {
            if ($chome($local) && $local->hour < 22) {
                return $local->copy()->setTime(22, 0)->setTimezone(config('app.timezone'));
            }

            $lendemain = $local->copy()->addDay()->startOfDay();

            if ($local->hour >= 22 && $chome($lendemain)) {
                return $lendemain->setTime(22, 0)->setTimezone(config('app.timezone'));
            }

            return null;
        }

        if (in_array($this->pays, self::INTERDICTION_DIMANCHE, true) && $chome($local) && $local->hour < 22) {
            return $local->copy()->setTime(22, 0)->setTimezone(config('app.timezone'));
        }

        return null;
    }

    private function heuresAvantInterdiction(): float
    {
        $local = $this->t->copy()->setTimezone(Trajet::fuseau($this->pays));
        $candidats = [];

        foreach ([0, 1, 2] as $decalage) {
            $jour = $local->copy()->startOfDay()->addDays($decalage);
            $debut = $this->pays === 'FR' ? $jour->copy()->subDay()->setTime(22, 0) : $jour->copy();

            if ($this->finInterdiction($debut->copy()->addMinute()) !== null && $debut->gt($local)) {
                $candidats[] = $local->diffInMinutes($debut) / 60;
            }
        }

        return $candidats === [] ? INF : max(0.0, min($candidats));
    }

    private function heuresDeService(): float
    {
        return $this->debutService->diffInMinutes($this->t) / 60;
    }

    private function avancer(float $heures): void
    {
        $this->t->addSeconds((int) round($heures * 3600));
    }

    private static function arrondir(CarbonInterface $instant): Carbon
    {
        $pas = (int) config('fret.chrono.arrondi_min', 15);
        $c = Carbon::instance($instant)->copy()->second(0);
        $reste = $c->minute % $pas;

        return $reste === 0 && (int) $instant->format('s') === 0 ? $c : $c->addMinutes($pas - $reste);
    }
}
