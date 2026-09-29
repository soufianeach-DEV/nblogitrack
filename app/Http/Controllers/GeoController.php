<?php

namespace App\Http\Controllers;

use App\Support\Localite;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Promise\Is;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GeoController extends Controller
{
    /**
     * Villes dont le nom couvre plusieurs communes, chacune avec son code :
     * « Bruxelles » designe les dix-neuf communes de la Region (1000 a
     * 1299), mais seul 1000 porte ce nom dans GeoNames (1050 est Ixelles).
     */
    private const AGGLOMERATIONS = [
        'BE' => [
            [['bruxelles', 'brussel', 'brussels', 'brüssel', 'bruxelles-capitale'], ['1000', '1299']],
        ],
    ];

    /** @return array{0: string, 1: string}|null la plage de codes */
    private static function agglomeration(string $pays, string $ville): ?array
    {
        $ville = mb_strtolower(trim($ville));

        foreach (self::AGGLOMERATIONS[strtoupper($pays)] ?? [] as [$noms, $plage]) {
            if (in_array($ville, $noms, true)) {
                return $plage;
            }
        }

        return null;
    }

    public function villes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pays' => 'required|string|size:2',
            'q' => 'required|string|min:2|max:100',
        ]);

        $noms = array_merge([$data['q']], Localite::suggestions($data['q']));

        $villes = DB::table('postal_codes')
            ->selectRaw('city AS ville, MIN(region) AS region, AVG(lat) AS lat, AVG(lng) AS lng, COUNT(DISTINCT code) AS nb_codes, MIN(code) AS code')
            ->where('country_code', strtoupper($data['pays']))
            ->where(function ($requete) use ($noms) {
                // % et _ sont echappes : « %% » listait toutes les villes du
                // pays et « _%_%_ » forcait des parcours couteux de la table.
                foreach ($noms as $nom) {
                    $requete->orWhere('city', 'ilike', addcslashes($nom, '\\%_').'%');
                }
            })
            ->groupBy('city')
            ->orderByRaw('COUNT(*) DESC, city ASC')
            ->limit(12)
            ->get()
            ->map(fn ($v) => [
                'ville' => $v->ville,
                'region' => $v->region,
                'lat' => (float) $v->lat,
                'lng' => (float) $v->lng,
                // Un seul code : il se remplit tout seul. Pas pour une
                // agglomeration, dont les communes ont chacune le leur.
                'code' => (int) $v->nb_codes === 1 && self::agglomeration($data['pays'], $v->ville) === null ? $v->code : null,
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

        $pays = strtoupper($data['pays']);
        $agglomeration = self::agglomeration($pays, $data['ville']);

        $codes = DB::table('postal_codes')
            ->selectRaw('code, MIN(city) AS ville, AVG(lat) AS lat, AVG(lng) AS lng')
            ->where('country_code', $pays)
            ->where(fn ($requete) => $agglomeration === null
                ? $requete->where('city', 'ilike', addcslashes($data['ville'], '\\%_'))
                : $requete->whereBetween('code', $agglomeration))
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

        // Centre arrondi a 0,01° (environ 1 km) : un point voisin de la
        // meme rue reprend le cache au lieu de relancer trois serveurs
        // Overpass. Le rayon de recherche couvre l'ecart (2 km).
        $lat = round((float) $data['lat'], 2);
        $lng = round((float) $data['lng'], 2);
        $cle = 'numeros:'.md5(mb_strtolower($data['rue']).":{$lat}:{$lng}");

        $numeros = Cache::get($cle);

        if ($numeros === null) {
            $numeros = $this->interrogerOverpass($data['rue'], $lat, $lng);

            // Un echec (serveurs saturés) n'est pas une rue sans numéros :
            // il n'est pas retenu, la prochaine demande réessaie.
            if ($numeros === null) {
                return response()->json([]);
            }

            Cache::put($cle, $numeros, $numeros === [] ? now()->addHours(6) : now()->addDays(7));
        }

        if (! empty($data['cp'])) {
            $filtres = array_values(array_filter($numeros, fn ($n) => $n['cp'] === '' || $n['cp'] === $data['cp']));
            if ($filtres !== []) {
                $numeros = $filtres;
            }
        }

        return response()->json($numeros);
    }

    /** Les serveurs Overpass publics, interrogés ensemble. */
    private const OVERPASS = ['overpass-api.de', 'overpass.kumi.systems', 'overpass.private.coffee'];

    /**
     * Les numéros de la rue dans un rayon de 2 km. Les trois serveurs
     * sont interrogés ensemble et le premier qui répond l'emporte : un
     * serveur lent ou saturé ne fait plus attendre la réponse des autres.
     * Null si aucun n'a répondu.
     *
     * @return list<array{numero: string, lat: float, lng: float, cp: string}>|null
     */
    private function interrogerOverpass(string $rue, float $lat, float $lng): ?array
    {
        $motif = preg_replace('/[^\p{L}\p{N} \'\-]/u', ' ', $rue);
        $requete = '[out:json][timeout:5];('
            .'node["addr:housenumber"]["addr:street"~"'.$motif.'",i](around:2000,'.$lat.','.$lng.');'
            .'way["addr:housenumber"]["addr:street"~"'.$motif.'",i](around:2000,'.$lat.','.$lng.');'
            .');out tags center 400;';

        $boucle = new CurlMultiHandler(['select_timeout' => 0.05]);
        $promesses = array_map(fn (string $hote) => Http::setHandler($boucle)->async()
            ->timeout(6)->connectTimeout(2)
            ->withHeaders(['User-Agent' => 'NBLogiTrack/1.0 (epreuve integree)'])
            ->asForm()->post("https://{$hote}/api/interpreter", ['data' => $requete])
            ->buildPromise()
            ->then(function ($reponse) {
                $elements = $reponse instanceof Response && $reponse->ok() ? $reponse->json('elements') : null;
                if (! is_array($elements)) {
                    throw new \RuntimeException('Overpass indisponible');
                }

                return $this->numerosDe($elements);
            }), self::OVERPASS);

        $premier = PromiseUtils::any($promesses);
        $limite = microtime(true) + 9;

        PromiseUtils::queue()->run();
        while (Is::pending($premier) && microtime(true) < $limite) {
            $boucle->tick();
            PromiseUtils::queue()->run();
        }

        return Is::fulfilled($premier) ? $premier->wait() : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $elements
     * @return list<array{numero: string, lat: float, lng: float, cp: string}>
     */
    private function numerosDe(array $elements): array
    {
        $liste = [];
        foreach ($elements as $element) {
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

        uksort($liste, fn ($a, $b) => ((int) $a <=> (int) $b) ?: strcmp((string) $a, (string) $b));

        return array_values($liste);
    }
}
