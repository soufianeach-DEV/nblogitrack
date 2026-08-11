<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GeoController extends Controller
{
    public function villes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pays' => 'required|string|size:2',
            'q' => 'required|string|min:2|max:100',
        ]);

        $villes = DB::table('postal_codes')
            ->selectRaw('city AS ville, MIN(region) AS region, AVG(lat) AS lat, AVG(lng) AS lng, COUNT(DISTINCT code) AS nb_codes, MIN(code) AS code')
            ->where('country_code', strtoupper($data['pays']))
            ->where('city', 'ilike', $data['q'].'%')
            ->groupBy('city')
            ->orderByRaw('COUNT(*) DESC, city ASC')
            ->limit(12)
            ->get()
            ->map(fn ($v) => [
                'ville' => $v->ville,
                'region' => $v->region,
                'lat' => (float) $v->lat,
                'lng' => (float) $v->lng,
                'code' => (int) $v->nb_codes === 1 ? $v->code : null,
            ]);

        return response()->json($villes);
    }

    public function codesPostaux(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pays' => 'required|string|size:2',
            'ville' => 'required|string|min:1|max:180',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $codes = DB::table('postal_codes')
            ->selectRaw('code, MIN(city) AS ville, AVG(lat) AS lat, AVG(lng) AS lng')
            ->where('country_code', strtoupper($data['pays']))
            // Chaque localite porte ses propres codes : Gilly a le 6060, Courcelles
            // le 6180. Elargir a la zone geographique ferait remonter les communes voisines.
            ->where('city', 'ilike', $data['ville'])
            ->groupBy('code')
            ->orderBy('code')
            ->limit(60)
            ->get()
            ->map(fn ($c) => [
                'code' => $c->code,
                'ville' => $c->ville,
                'lat' => (float) $c->lat,
                'lng' => (float) $c->lng,
            ]);

        return response()->json($codes);
    }

    public function numeros(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rue' => 'required|string|min:2|max:180',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'cp' => 'nullable|string|max:16',
        ]);

        $lat = round((float) $data['lat'], 4);
        $lng = round((float) $data['lng'], 4);
        $cle = 'numeros:'.md5(mb_strtolower($data['rue']).":{$lat}:{$lng}");

        $numeros = Cache::get($cle);

        if ($numeros === null) {
            $numeros = $this->interrogerOverpass($data['rue'], $lat, $lng);
            if ($numeros !== []) {
                Cache::put($cle, $numeros, now()->addDays(7));
            }
        }

        if (! empty($data['cp'])) {
            $filtres = array_values(array_filter($numeros, fn ($n) => $n['cp'] === '' || $n['cp'] === $data['cp']));
            if ($filtres !== []) {
                $numeros = $filtres;
            }
        }

        return response()->json($numeros);
    }

    private function interrogerOverpass(string $rue, float $lat, float $lng): array
    {
        $motif = preg_replace('/["\\\\]/', ' ', $rue);
        $requete = '[out:json][timeout:10];('
            .'node["addr:housenumber"]["addr:street"~"'.$motif.'",i](around:1500,'.$lat.','.$lng.');'
            .'way["addr:housenumber"]["addr:street"~"'.$motif.'",i](around:1500,'.$lat.','.$lng.');'
            .');out tags center 400;';

        foreach (['https://overpass-api.de/api/interpreter', 'https://overpass.kumi.systems/api/interpreter'] as $hote) {
            try {
                $reponse = Http::timeout(12)
                    ->withHeaders(['User-Agent' => 'NBLogiTrack/1.0 (epreuve integree)'])
                    ->asForm()
                    ->post($hote, ['data' => $requete]);

                if (! $reponse->ok()) {
                    continue;
                }

                $liste = [];
                foreach ($reponse->json('elements', []) as $element) {
                    $tags = $element['tags'] ?? [];
                    $brut = $tags['addr:housenumber'] ?? null;
                    $nlat = $element['lat'] ?? ($element['center']['lat'] ?? null);
                    $nlng = $element['lon'] ?? ($element['center']['lon'] ?? null);
                    if (! $brut || $nlat === null || $nlng === null) {
                        continue;
                    }
                    foreach (preg_split('/[;,]/', $brut) as $part) {
                        $part = trim($part);
                        if ($part !== '' && ! isset($liste[$part])) {
                            $liste[$part] = [
                                'numero' => $part,
                                'lat' => (float) $nlat,
                                'lng' => (float) $nlng,
                                'cp' => $tags['addr:postcode'] ?? '',
                            ];
                        }
                    }
                }

                uksort($liste, fn ($a, $b) => ((int) $a <=> (int) $b) ?: strcmp($a, $b));

                return array_values($liste);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [];
    }
}
