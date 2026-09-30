<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use App\Support\ControleAffectation;
use App\Support\TempsDeConduite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Le jeu de demonstration a ete ecrit a date fixe : des expeditions
 * « en route » depuis 2024, et un couple sur trois qui ne passerait pas
 * l'ecran de planification (permis C sur un tracteur de 44 t, matiere
 * dangereuse dans un frigo, chauffeur sur deux camions a la fois). Cette
 * etape le remet d'aplomb, relativement a aujourd'hui, avec les regles
 * memes de l'application.
 */
final class CoherenceDesAffectations
{
    /** Expeditions reellement en route : une poignee, parties hier ou aujourd'hui. */
    private const EN_ROUTE = 8;

    /** Le compte chauffeur de demonstration a toujours une mission en cours. */
    private const CHAUFFEUR_DEMO = 'wim.peeters121@nblogitrack.be';

    public function __invoke(): void
    {
        $vehicules = Vehicle::orderBy('registration')->get();
        $chauffeurs = Driver::with('user')->orderBy('id')->get();

        $this->solderLesVieillesMissions();
        $this->recalerLesLivraisons($vehicules, $chauffeurs);
        $this->recalerLesMissionsEnRoute($vehicules, $chauffeurs);
    }

    /**
     * Une expedition partie il y a des mois est livree depuis longtemps :
     * seules les plus recentes restent en route.
     */
    private function solderLesVieillesMissions(): void
    {
        $gardees = TransportOrder::where('status', 'IN_PROGRESS')
            ->orderByDesc('assigned_at')->orderByDesc('id')
            ->limit(self::EN_ROUTE)
            ->pluck('id');

        TransportOrder::where('status', 'IN_PROGRESS')
            ->whereNotIn('id', $gardees)
            ->get()
            ->each(function (TransportOrder $o) {
                $depart = Carbon::instance($o->assigned_at ?? $o->created_date ?? now()->subMonth())->setTime(7, 0);
                $arrivee = $depart->copy()->addDays(TempsDeConduite::journees($o->distance_km) - 1);

                $o->forceFill([
                    'status' => 'DELIVERED',
                    'pickup_date' => $depart,
                    'picked_up_at' => $depart,
                    'delivered_at' => $arrivee->copy()->setTime(15, 0),
                    'actual_delivery_date' => $arrivee->toDateString(),
                ])->saveQuietly();
            });
    }

    /**
     * Livraisons passees : un chauffeur deja embauche, un permis qui
     * couvre le camion, l'ADR si la marchandise est dangereuse, et
     * personne sur deux camions le meme jour.
     */
    private function recalerLesLivraisons(Collection $vehicules, Collection $chauffeurs): void
    {
        $occupe = [];

        TransportOrder::where('status', 'DELIVERED')->orderBy('id')->get()
            ->each(function (TransportOrder $o) use ($vehicules, $chauffeurs, &$occupe) {
                $jour = Carbon::instance($o->pickup_date ?? $o->assigned_at ?? $o->created_date)->startOfDay();
                $cle = $jour->toDateString();

                $convient = fn (Vehicle $v) => $v->capacity_tonnes * 1000 >= $o->weight
                    && ($o->volume === null || $v->capacity_volume === null || (float) $v->capacity_volume >= (float) $o->volume)
                    && (! $o->needs_tail_lift || $v->has_tail_lift)
                    && (! $o->is_hazardous || $v->adr_equipe)
                    && self::chargeable($o, $v)
                    && ! isset($occupe['v'][$v->registration][$cle]);

                $vehicule = $vehicules->firstWhere('registration', $o->vehicle_registration);
                $vehicule = $vehicule !== null && $convient($vehicule)
                    ? $vehicule
                    : $this->choisir($vehicules, $convient, $o->id);

                $chauffeur = $vehicule === null ? null : $this->choisir($chauffeurs, fn (Driver $d) => $d->motifPermis($vehicule) === null
                    && (! $o->is_hazardous || $d->adr_certified)
                    && ($d->hired_on === null || $d->hired_on->lte($jour))
                    && ($d->left_on === null || $d->left_on->gt($jour))
                    && ! isset($occupe['d'][$d->id][$cle]), $o->id, $o->driver_id);

                if ($vehicule === null || $chauffeur === null) {
                    return;
                }

                $occupe['v'][$vehicule->registration][$cle] = true;
                $occupe['d'][$chauffeur->id][$cle] = true;

                // Une livraison a eu lieu : chargement et remise sont dates,
                // l'historique du chauffeur se trie dessus.
                $depart = $o->pickup_date ?? $jour->copy()->setTime(7, 0);
                $livre = Carbon::parse($o->actual_delivery_date ?? $jour->copy()->addDays(TempsDeConduite::journees($o->distance_km) - 1))->setTime(15, 0);

                $o->forceFill([
                    'vehicle_registration' => $vehicule->registration,
                    'driver_id' => $chauffeur->id,
                    'pickup_date' => $depart,
                    'picked_up_at' => $o->picked_up_at ?? $depart,
                    'delivered_at' => $o->delivered_at ?? ($livre->lt($depart) ? $depart->copy()->addHours(6) : $livre),
                ])->saveQuietly();
            });
    }

    /**
     * Les quelques expeditions en route sont parties hier ou aujourd'hui,
     * avec un couple que l'ecran de planification accepterait.
     */
    private function recalerLesMissionsEnRoute(Collection $vehicules, Collection $chauffeurs): void
    {
        $demo = $chauffeurs->first(fn (Driver $d) => $d->user?->email === self::CHAUFFEUR_DEMO);
        $demoEnRoute = false;

        TransportOrder::where('status', 'IN_PROGRESS')->orderBy('id')->get()
            ->each(function (TransportOrder $o) use ($vehicules, $chauffeurs, $demo, &$demoEnRoute) {
                // Jamais un chargement dans le futur : semé a 7 h, un depart
                // prevu a 9 h 30 aujourd'hui passe a la veille.
                $depart = today()->subDays($o->id % 2)->setTime(6 + $o->id % 4, 30);

                if ($depart->gt(now())) {
                    $depart->subDay();
                }

                $arrivee = $depart->copy()->addDays(TempsDeConduite::journees($o->distance_km));
                $souhaitee = $o->requested_delivery_date;

                $o->forceFill([
                    'pickup_date' => $depart,
                    'picked_up_at' => $depart,
                    'assigned_at' => $depart->copy()->subDay(),
                    'requested_delivery_date' => $souhaitee === null || $souhaitee->lt($arrivee->copy()->startOfDay()) ? $arrivee->toDateString() : $souhaitee,
                    'vehicle_registration' => null,
                    'driver_id' => null,
                ])->saveQuietly();

                // Les controles sans requete d'abord ; les chevauchements et
                // le temps de conduite seulement pour les couples plausibles.
                [, , $fin] = ControleAffectation::periode($o);
                $disponibles = $this->ordonner($vehicules->where('is_available', true), $o->id)
                    ->filter(fn (Vehicle $v) => self::chargeable($o, $v) && ControleAffectation::refusVehiculeCourt($o, $v, $fin) === null);
                $aptes = $this->ordonner($chauffeurs->filter(fn (Driver $d) => $d->is_available && $d->user?->is_active), $o->id)
                    ->filter(fn (Driver $d) => ControleAffectation::refusChauffeurCourt($o, $d, $fin) === null);

                if ($demo !== null && ! $demoEnRoute) {
                    $aptes = $aptes->sortBy(fn (Driver $d) => $d->id === $demo->id ? 0 : 1)->values();
                }

                foreach ($disponibles as $vehicule) {
                    foreach ($aptes as $chauffeur) {
                        if ($chauffeur->motifPermis($vehicule) === null && ControleAffectation::refus($o, $vehicule, $chauffeur) === []) {
                            $o->forceFill([
                                'vehicle_registration' => $vehicule->registration,
                                'driver_id' => $chauffeur->id,
                            ])->saveQuietly();
                            $demoEnRoute = $demoEnRoute || $chauffeur->id === $demo?->id;

                            return;
                        }
                    }
                }

                // Aucun couple possible : l'expedition n'a jamais pu partir.
                $o->forceFill(['status' => 'PENDING', 'pickup_date' => null, 'picked_up_at' => null, 'assigned_at' => null])->saveQuietly();
            });
    }

    /**
     * Une citerne transporte du vrac liquide : pas des colis de pieces
     * automobiles ou de medicaments, meme dangereux.
     */
    private static function chargeable(TransportOrder $o, Vehicle $v): bool
    {
        return $v->vehicle_type !== 'Citerne' || $o->goods_type === 'Produits chimiques';
    }

    /**
     * Le premier element qui convient, en gardant l'actuel s'il convient :
     * l'ordre de parcours, tire de l'identifiant, repartit les missions
     * sur toute la flotte d'une execution a l'autre.
     */
    private function choisir(Collection $liste, callable $convient, int $graine, mixed $actuel = null): mixed
    {
        if ($actuel !== null && ($courant = $liste->first(fn ($e) => $e->getKey() === $actuel)) && $convient($courant)) {
            return $courant;
        }

        return $this->ordonner($liste, $graine)->first($convient);
    }

    private function ordonner(Collection $liste, int $graine): Collection
    {
        $liste = $liste->values();
        $decalage = $liste->isEmpty() ? 0 : ($graine * 7919) % $liste->count();

        return $liste->slice($decalage)->concat($liste->slice(0, $decalage))->values();
    }
}
