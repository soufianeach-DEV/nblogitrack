<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * L'itineraire routier, demande aux serveurs OSRM publics.
 *
 * Le serveur de demonstration d'OSRM est souvent sature : un second
 * serveur, celui de l'association FOSSGIS, prend le relais. Chaque appel
 * nomme l'application, comme le demandent leurs conditions d'usage.
 */
class Osrm
{
    /**
     * Le premier itineraire trouve (distance en metres, duree en secondes,
     * trace GeoJSON si demande), ou null si aucun serveur n'a repondu.
     *
     * @return array<string, mixed>|null
     */
    public static function route(float $latDepart, float $lngDepart, float $latArrivee, float $lngArrivee, bool $trace = false, int $delai = 10): ?array
    {
        $parametres = $trace
            ? ['overview' => 'full', 'geometries' => 'geojson']
            : ['overview' => 'false'];

        foreach ((array) config('services.osrm.serveurs', []) as $serveur) {
            try {
                $reponse = Http::withUserAgent('NBLogiTrack/1.0 (+'.config('app.url').')')
                    ->connectTimeout(5)
                    ->timeout($delai)
                    ->get(rtrim((string) $serveur, '/')."/route/v1/driving/{$lngDepart},{$latDepart};{$lngArrivee},{$latArrivee}", $parametres);

                $route = $reponse->ok() ? $reponse->json('routes.0') : null;

                if (is_array($route) && isset($route['distance'])
                    && (! $trace || isset($route['geometry']['coordinates']))) {
                    return $route;
                }
            } catch (\Throwable $e) {
                // Serveur injoignable : le suivant prend le relais.
            }
        }

        return null;
    }
}
