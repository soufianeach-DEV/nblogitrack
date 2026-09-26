<?php

namespace App\Support;

use App\Models\TariffGrid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Tarificateur
{
    /**
     * Le prix d'une formule, coherent avec les autres formules de sa zone :
     *
     * - le groupage (Eco, Standard) ne coute jamais plus qu'un camion dedie
     *   (Express) : pour un chargement complet, le client paierait sinon
     *   plus cher un service plus lent ;
     * - Express ne coute jamais moins que Standard : c'est le service le
     *   plus rapide, avec un supplement minimum ;
     * - Eco ne coute jamais plus que Standard.
     *
     * Le formulaire de commande, le simulateur de tarifs et l'API passent
     * tous par ici : un seul calcul, donc un seul prix.
     */
    public static function cout(TariffGrid $grille, float $km, float $poids, string $pays, bool $adr): float
    {
        $zone = TariffGrid::where('zone', $grille->zone)
            ->where('is_active', true)
            ->get()
            ->reject(fn (TariffGrid $g) => $g->id === $grille->id)
            ->push($grille);

        return self::parFormule($zone, $km, $poids, $pays, $adr)[$grille->id];
    }

    /**
     * @param  Collection<int, TariffGrid>  $grilles  les formules d'une meme zone
     * @return array<int, float> prix par identifiant de formule
     */
    public static function parFormule($grilles, float $km, float $poids, string $pays, bool $adr): array
    {
        $bruts = $grilles->mapWithKeys(fn (TariffGrid $g) => [$g->id => self::brut($g, $km, $poids, $pays, $adr)]);
        $niveau = fn (string $n) => $grilles->firstWhere('service_level', $n);

        $express = $niveau('EXPRESS');
        $dedie = $express ? $bruts[$express->id] : null;

        $standard = $niveau('STANDARD');
        $prixStandard = $standard ? self::plafond($bruts[$standard->id], $dedie) : null;

        $prix = [];

        foreach ($grilles as $g) {
            $prix[$g->id] = match ($g->service_level) {
                'STANDARD' => $prixStandard,
                'ECO' => self::plafond(self::plafond($bruts[$g->id], $dedie), $prixStandard),
                'EXPRESS' => $prixStandard === null
                    ? $bruts[$g->id]
                    : max($bruts[$g->id], $prixStandard * (1 + (float) config('pricing.express_premium_min', 0.10))),
                default => $bruts[$g->id],
            };

            $prix[$g->id] = round($prix[$g->id], 2);
        }

        return $prix;
    }

    private static function plafond(float $prix, ?float $plafond): float
    {
        return $plafond === null ? $prix : min($prix, $plafond);
    }

    /** Le calcul propre a une formule, avant mise en coherence. */
    public static function brut(TariffGrid $grille, float $km, float $poids, string $pays, bool $adr): float
    {
        if ($grille->service_level === 'EXPRESS') {
            $p = config('pricing');

            $cout = $grille->base_rate
                + $km * $p['consumption_l_per_100km'] / 100 * $p['diesel_price']
                + $km * ($p['toll_per_km'][$pays] ?? 0)
                + $km * $p['driver_cost_per_km']
                + $km * $p['vehicle_cost_per_km'];

            $cout *= 1 + $p['margin'];
        } else {
            $cout = $grille->base_rate + $grille->price_per_kg * $poids + $grille->price_per_km * $km;
        }

        if ($adr) {
            $cout *= $grille->adr_coefficient;
        }

        return round($cout, 2);
    }

    public static function distanceRoutiere(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        try {
            $reponse = Http::timeout(5)->get(
                "https://router.project-osrm.org/route/v1/driving/{$lng1},{$lat1};{$lng2},{$lat2}",
                ['overview' => 'false'],
            );

            if ($reponse->ok() && isset($reponse->json()['routes'][0]['distance'])) {
                return $reponse->json()['routes'][0]['distance'] / 1000;
            }
        } catch (\Throwable $e) {
        }

        return self::distanceVol($lat1, $lng1, $lat2, $lng2) * 1.3;
    }

    public static function distanceVol(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * asin(sqrt($a));
    }
}
