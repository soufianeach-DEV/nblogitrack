<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aucun ecran ne lit un filtre d'adresse sous forme de liste. Un
 * ?tracking[]=x forge provoquait une erreur 500 (conversion d'un tableau
 * en texte) dans les ecrans qui lisent un filtre comme du texte : le
 * filtre est ignore avant d'y arriver, comme un filtre mal forme.
 */
class IgnorerFiltresEnTableau
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            foreach ($request->query() as $cle => $valeur) {
                if (is_array($valeur)) {
                    $request->query->remove($cle);
                }
            }
        }

        return $next($request);
    }
}
