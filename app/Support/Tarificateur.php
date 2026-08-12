<?php

namespace App\Support;

use App\Models\TariffGrid;
use Illuminate\Support\Facades\Http;

class Tarificateur
{
    /**
     * Le prix d'un transport.
     *
     * Deux modeles selon la formule. L'express affrete un vehicule dedie :
     * son prix suit les couts reels du trajet — carburant, peages du pays
     * traverse, chauffeur, vehicule — majores de la marge. Les autres
     * formules groupent la marchandise avec celle d'autres clients : leur
     * prix suit le poids et la distance, sans qu'un camion soit reserve.
     */
    public static function cout(TariffGrid $grille, float $km, float $poids, string $pays, bool $adr): float
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

    /**
     * La distance par la route entre deux points.
     *
     * Si le service d'itineraire ne repond pas, la distance a vol d'oiseau
     * majoree de trente pour cent prend le relais : c'est le detour moyen
     * qu'impose un reseau routier, et un prix approche vaut mieux qu'une
     * page en erreur.
     */
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
            // Service indisponible : on bascule sur l'approximation.
        }

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * asin(sqrt($a)) * 1.3;
    }
}
