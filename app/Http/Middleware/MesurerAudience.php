<?php

namespace App\Http\Middleware;

use App\Support\Audience;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compte une page vue du site vitrine quand le visiteur l'a accepte.
 */
class MesurerAudience
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        if ($reponse->getStatusCode() === 200) {
            try {
                Audience::noterVue($request);
            } catch (\Throwable $e) {
                // La mesure ne doit jamais empecher d'afficher la page.
                report($e);
            }
        }

        return $reponse;
    }
}
