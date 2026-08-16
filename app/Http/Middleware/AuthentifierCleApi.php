<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiRequest;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A12 : le controle d'acces a l'API REST.
 *
 * Quatre verrous, dans cet ordre : la cle existe, elle est encore
 * valable, elle vient d'une adresse autorisee, elle porte la permission
 * que la route exige. Chaque appel est journalise, refus compris — un
 * journal qui ne garde que les succes ne sert a rien le jour ou l'on
 * cherche qui a essaye d'entrer.
 *
 * La permission se declare sur la route : cle.api:lecture.
 */
class AuthentifierCleApi
{
    public function handle(Request $request, Closure $next, string $permission = 'lecture'): Response
    {
        $depart = microtime(true);
        $jeton = $request->bearerToken();

        if ($jeton === null || $jeton === '') {
            return $this->refuser($request, null, 'jeton_absent', 401, $depart);
        }

        $cle = ApiKey::depuisJeton($jeton);

        if ($cle === null) {
            // On ne dit pas si le prefixe existe : la reponse est la meme
            // pour une cle inventee et pour un secret errone.
            return $this->refuser($request, null, 'cle_inconnue', 401, $depart);
        }

        if ($motif = $cle->empechement($request->ip(), $permission)) {
            $code = $motif === 'permission_absente' || $motif === 'adresse_refusee' ? 403 : 401;

            return $this->refuser($request, $cle, $motif, $code, $depart);
        }

        // La cle resolue accompagne la requete : les controleurs s'en
        // servent pour restreindre les donnees au partenaire concerne.
        $request->attributes->set('cle_api', $cle);

        $reponse = $next($request);

        $cle->forceFill([
            'last_used_at' => now(),
            'requests_count' => $cle->requests_count + 1,
        ])->save();

        $this->journaliser($request, $cle, $reponse->getStatusCode(), null, $depart);

        return $reponse;
    }

    private function refuser(Request $request, ?ApiKey $cle, string $motif, int $code, float $depart): JsonResponse
    {
        $this->journaliser($request, $cle, $code, $motif, $depart);

        return response()->json([
            'message' => ApiRequest::MOTIFS[$motif] ?? 'Accès refusé.',
            'motif' => $motif,
        ], $code);
    }

    private function journaliser(Request $request, ?ApiKey $cle, int $statut, ?string $motif, float $depart): void
    {
        ApiRequest::create([
            'api_key_id' => $cle?->id,
            'method' => $request->method(),
            'path' => mb_substr($request->path(), 0, 255),
            'status' => $statut,
            'ip_address' => $request->ip(),
            'duration_ms' => (int) round((microtime(true) - $depart) * 1000),
            'refus' => $motif,
            'created_at' => now(),
        ]);
    }
}
