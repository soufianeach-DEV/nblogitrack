<?php

namespace App\Http\Middleware;

use App\Support\Traductions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Choisit la langue de la requete.
 *
 * L'utilisateur connecte impose la sienne, elle le suit d'un poste a
 * l'autre. Le visiteur garde la sienne en session, le temps de sa
 * visite. A defaut le francais, langue de l'entreprise.
 */
class DefinirLangue
{
    public function handle(Request $request, Closure $suivant): Response
    {
        $langue = $request->user()?->locale
            ?? $request->session()->get('langue');

        App::setLocale(Traductions::estServie($langue) ? $langue : 'fr');

        return $suivant($request);
    }
}
