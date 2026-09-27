<?php

namespace App\Http\Middleware;

use App\Support\Traductions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class VerifierCompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = Auth::user();

        if ($utilisateur !== null && ! $utilisateur->is_active) {
            // Ce controle passe avant le choix de la langue : on la fixe ici,
            // celle de l'adresse ou du compte, pour que l'ecran de connexion
            // et le message ne sortent pas en francais pour tout le monde.
            $langue = $request->route('langue') ?? $utilisateur->locale;
            $langue = Traductions::estServie($langue) ? $langue : 'fr';
            App::setLocale($langue);
            URL::defaults(['langue' => $langue]);

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', Traductions::t('msg.compte_desactive', 'Ce compte est désactivé. Contactez votre administrateur.'));
        }

        return $next($request);
    }
}
