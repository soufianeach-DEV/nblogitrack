<?php

namespace App\Http\Middleware;

use App\Support\Traductions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Choisit la langue de la requete.
 *
 * L'adresse fait foi : /nl/tarieven est en neerlandais, quel que soit le
 * compte qui la demande. C'est ce qui rend un lien partageable. A defaut
 * de prefixe, sur les points d'entree JSON et les redirections, on
 * retombe sur la langue du compte, puis sur celle de la session, puis
 * sur le francais, langue de l'entreprise.
 */
class DefinirLangue
{
    public function handle(Request $request, Closure $suivant): Response
    {
        $langue = $request->route('langue')
            ?? $request->user()?->locale
            ?? $request->session()->get('langue');

        $langue = Traductions::estServie($langue) ? $langue : 'fr';

        App::setLocale($langue);

        // Sans cela chaque route() reclamerait la langue en argument. Le
        // parametre se remplit tout seul, les appels existants ne changent
        // pas et Ziggy reprend la meme valeur cote navigateur.
        URL::defaults(['langue' => $langue]);

        // Le prefixe a fait son office : il sort des parametres de route.
        //
        // Laravel n'injecte un modele deja resolu qu'une fois, puis remplit
        // les arguments restants du controleur avec les parametres de route
        // dans l'ordre. Laisser « langue » en tete revenait a passer « nl »
        // la ou la methode attend une facture ou une expedition, sur chaque
        // action qui repose sur la liaison implicite.
        $request->route()?->forgetParameter('langue');

        return $suivant($request);
    }
}
