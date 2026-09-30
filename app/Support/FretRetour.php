<?php

namespace App\Support;

use App\Models\TransportOrder;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Le fret retour : un camion qui livre a l'etranger recharge pres de sa
 * livraison pour rentrer en Belgique au lieu de rouler a vide. Une seule
 * regle sert au prix (tarif fret retour) et a la planification (binome
 * propose) : un tarif retour vendu correspond toujours a un binome
 * reellement affectable.
 *
 * L'appariement se fait sur les adresses de livraison prevues, jamais sur
 * la position GPS : pas de nouvelle finalite de traitement.
 */
final class FretRetour
{
    /** Verrou transactionnel : deux clients ne vendent pas la meme place. */
    public const VERROU = 740301;

    /** Un import dont on connait le lieu et la date d'enlevement. */
    public static function concerne(TransportOrder $o): bool
    {
        return strtoupper((string) $o->pickup_country) !== 'BE'
            && strtoupper((string) $o->delivery_country) === 'BE'
            && $o->pickup_date !== null
            && $o->pickup_lat !== null && $o->pickup_lng !== null;
    }

    /** Kilometres a vide d'une livraison au lieu de chargement. */
    public static function approche(TransportOrder $mission, float $lat, float $lng): float
    {
        return Tarificateur::distanceVol((float) $mission->delivery_lat, (float) $mission->delivery_lng, $lat, $lng)
            * (float) config('fret.facteur_route', 1.3);
    }

    /**
     * Fin du dechargement d'une mission engagee : l'heure enregistree, ou
     * celle que donne son trajet.
     */
    public static function finDeLivraison(TransportOrder $mission): Carbon
    {
        if ($mission->delivered_at !== null) {
            return Carbon::instance($mission->delivered_at);
        }

        $chargement = $mission->picked_up_at ?? $mission->pickup_date;
        $chargement = $chargement === null || $chargement->lt(today()) ? now() : $chargement;

        return Chronologie::finDeLivraison($chargement, (float) ($mission->distance_km ?? 0), (string) $mission->delivery_country);
    }

    /**
     * Les missions engagees dont le camion peut charger cette demande a son
     * retour, les plus proches d'abord.
     *
     * @return Collection<int, array{mission: TransportOrder, approche_km: float, arrivee: Carbon, rechargement: Carbon, reste_kg: float}>
     */
    public static function porteuses(TransportOrder $demande, bool $pourPrix = false, int $max = 3): Collection
    {
        if (! self::concerne($demande)) {
            return collect();
        }

        // Les trois premiers candidats sont ceux des cinq premiers : un
        // seul calcul par demande sert a toute la page de planification.
        if ($demande->id !== null && $max <= 5) {
            return MemoireRequete::retenir(
                'porteuses:'.$demande->id.':'.(int) $pourPrix,
                fn () => self::chercherPorteuses($demande, $pourPrix, 5),
            )->take($max)->values();
        }

        return self::chercherPorteuses($demande, $pourPrix, $max);
    }

    private static function chercherPorteuses(TransportOrder $demande, bool $pourPrix, int $max): Collection
    {

        $lat = (float) $demande->pickup_lat;
        $lng = (float) $demande->pickup_lng;
        $rayon = (float) config('fret.retour.approche_max_km', 150);
        $degres = $rayon / (float) config('fret.facteur_route', 1.3) / 111;
        $degresLng = $degres / max(0.2, cos(deg2rad($lat)));
        $enlevement = Carbon::instance($demande->pickup_date);
        $region = self::region($demande);

        return TransportOrder::with(['vehicle.indisponibilites', 'driver.user', 'driver.indisponibilites'])
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->whereNotNull('vehicle_registration')
            ->whereNotNull('driver_id')
            ->where('delivery_country', '!=', 'BE')
            ->whereBetween('delivery_lat', [$lat - $degres, $lat + $degres])
            ->whereBetween('delivery_lng', [$lng - $degresLng, $lng + $degresLng])
            ->when($demande->id !== null, fn ($q) => $q->where('id', '!=', $demande->id))
            ->when($pourPrix, fn ($q) => $q->where('client_id', '!=', $demande->client_id))
            ->get()
            ->map(function (TransportOrder $m) use ($lat, $lng, $demande, $region) {
                $approche = self::approche($m, $lat, $lng);
                $arrivee = self::finDeLivraison($m);

                return [
                    'mission' => $m,
                    'approche_km' => round($approche, 1),
                    'arrivee' => $arrivee,
                    'rechargement' => Chronologie::rechargementApres($arrivee, $approche, (string) $demande->pickup_country, $region),
                ];
            })
            ->filter(fn (array $c) => $c['approche_km'] <= $rayon
                && $enlevement->gte($c['rechargement'])
                && $enlevement->lte($c['arrivee']->copy()->addDays((int) config('fret.retour.attente_max_jours', 2))->endOfDay()))
            ->sortBy([['approche_km', 'asc'], fn ($a, $b) => $b['arrivee'] <=> $a['arrivee']])
            ->take(5)
            ->map(function (array $c) use ($demande) {
                $m = $c['mission'];
                $reste = self::capaciteRestante($m, $demande->id);

                return [...$c, 'reste_kg' => $reste['kg'], 'reste_m3' => $reste['m3']];
            })
            ->filter(fn (array $c) => self::binomeLibre($c['mission'], $demande, $c['reste_kg'], $c['reste_m3']))
            ->take($max)
            ->values();
    }

    /**
     * Le binome de la mission est-il libre et apte pour la demande ? Il doit
     * rester en service, n'avoir aucune autre mission entre sa livraison et
     * ce chargement, avoir la place, et passer tous les controles.
     */
    private static function binomeLibre(TransportOrder $m, TransportOrder $demande, float $resteKg, ?float $resteM3): bool
    {
        $camion = $m->vehicle;
        $chauffeur = $m->driver;

        if ($camion === null || $chauffeur === null || ! $camion->is_available
            || ! $chauffeur->is_available || ! $chauffeur->user?->is_active) {
            return false;
        }

        if ($resteKg < (float) $demande->weight || ($demande->volume !== null && $resteM3 !== null && $resteM3 < (float) $demande->volume)) {
            return false;
        }

        $derniere = ControleAffectation::derniereMission($camion->registration, Carbon::instance($demande->pickup_date)->startOfDay()->subDay(), $demande->id);

        if ($derniere !== null && $derniere->id !== $m->id && (int) $derniere->backhaul_order_id !== $m->id) {
            return false;
        }

        $essai = $demande->replicate();
        $essai->id = $demande->id;
        $essai->approche_km = (int) round(self::approche($m, (float) $demande->pickup_lat, (float) $demande->pickup_lng));

        return ControleAffectation::refus($essai, $camion, $chauffeur) === [];
    }

    /**
     * Ce qu'il reste a charger dans le camion de la mission, une fois
     * comptes les frets retour deja vendus sur elle.
     *
     * @return array{kg: float, m3: float|null}
     */
    public static function capaciteRestante(TransportOrder $m, ?int $sauf): array
    {
        $vendus = TransportOrder::where('backhaul_order_id', $m->id)
            ->where('status', '!=', 'CANCELLED')
            ->when($sauf !== null, fn ($q) => $q->where('id', '!=', $sauf))
            ->selectRaw('COALESCE(SUM(weight), 0) AS kg, COALESCE(SUM(volume), 0) AS m3')
            ->first();

        $camion = $m->vehicle;

        return [
            'kg' => (float) $camion?->capacity_tonnes * 1000 - (float) $vendus->kg,
            'm3' => $camion?->capacity_volume === null ? null : (float) $camion->capacity_volume - (float) $vendus->m3,
        ];
    }

    /**
     * Sens inverse, pour le planificateur : les imports en attente que le
     * camion de cette mission peut prendre a son retour.
     *
     * @return Collection<int, array{ordre: TransportOrder, approche_km: float}>
     */
    public static function chargements(TransportOrder $mission, int $max = 3): Collection
    {
        if ($mission->delivery_country === 'BE' || $mission->delivery_lat === null) {
            return collect();
        }

        $lat = (float) $mission->delivery_lat;
        $lng = (float) $mission->delivery_lng;
        $degres = (float) config('fret.retour.approche_max_km', 150) / (float) config('fret.facteur_route', 1.3) / 111;
        $degresLng = $degres / max(0.2, cos(deg2rad($lat)));

        return TransportOrder::where('status', 'PENDING')
            ->where('pickup_country', '!=', 'BE')
            ->where('delivery_country', 'BE')
            ->whereNotNull('pickup_date')
            ->whereBetween('pickup_lat', [$lat - $degres, $lat + $degres])
            ->whereBetween('pickup_lng', [$lng - $degresLng, $lng + $degresLng])
            ->orderBy('pickup_date')
            ->limit(10)
            ->get()
            ->filter(fn (TransportOrder $o) => self::porteuses($o, false, 5)->contains(fn (array $c) => $c['mission']->id === $mission->id))
            ->take($max)
            ->map(fn (TransportOrder $o) => ['ordre' => $o, 'approche_km' => round(self::approche($mission, (float) $o->pickup_lat, (float) $o->pickup_lng), 1)])
            ->values();
    }

    /**
     * Sans camion sur place : un camion part du depot. Kilometres a vide
     * et dernier jour de depart pour charger a la date prevue.
     *
     * @return array{km: float, depart_au_plus_tard: string}
     */
    public static function positionnement(TransportOrder $o): array
    {
        $km = Chronologie::kmDepuisDepot((float) $o->pickup_lat, (float) $o->pickup_lng);
        $jour = Carbon::instance($o->pickup_date ?? now())->startOfDay()->subDays(TempsDeConduite::journees((int) round($km)) - 1);

        return ['km' => round($km), 'depart_au_plus_tard' => $jour->format('d/m/Y')];
    }

    /**
     * Kilometres a vide pour que ce camion charge cet ordre : depuis sa
     * derniere livraison si elle est recente, sinon depuis le depot. Nul pour
     * un enlevement en Belgique (trajets courts, deja couverts).
     */
    public static function approchePour(TransportOrder $ordre, Vehicle $camion): ?int
    {
        if (strtoupper((string) $ordre->pickup_country) === 'BE' || $ordre->pickup_lat === null) {
            return null;
        }

        $jour = Carbon::instance($ordre->pickup_date ?? now())->startOfDay();
        $derniere = ControleAffectation::derniereMission($camion->registration, $jour->copy()->subDay(), $ordre->id);

        if ($derniere !== null && $derniere->delivery_lat !== null
            && ControleAffectation::occupation($derniere)[1]->diffInDays($jour) <= 3) {
            return (int) round(self::approche($derniere, (float) $ordre->pickup_lat, (float) $ordre->pickup_lng));
        }

        return (int) round(Chronologie::kmDepuisDepot((float) $ordre->pickup_lat, (float) $ordre->pickup_lng));
    }

    public static function region(TransportOrder $o): ?string
    {
        return self::regionDe((string) $o->pickup_country, (string) $o->pickup_address);
    }

    /** La region des feries d'une adresse : Land allemand, Alsace-Moselle. */
    public static function regionDe(string $pays, ?string $adresse): ?string
    {
        $cp = $adresse === null ? null : Adresse::codePostal($adresse);

        return JoursFeries::region($pays, $cp, $cp === null ? null : Localite::region($pays, $cp));
    }

    public static function premierEnlevement(string $pays, float $lat, float $lng, ?string $adresse = null, ?CarbonInterface $commande = null): Carbon
    {
        return Chronologie::premierEnlevement($pays, $lat, $lng, $commande ?? now(), self::regionDe($pays, $adresse));
    }
}
