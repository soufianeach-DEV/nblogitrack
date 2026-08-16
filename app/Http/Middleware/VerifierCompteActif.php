<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse la suite a un compte desactive pendant sa session.
 *
 * La verification existait deja, mais uniquement a la connexion. Une
 * session ouverte survivait donc a la desactivation : l'administrateur
 * fermait un compte et son titulaire continuait de travailler jusqu'a
 * ce qu'il se deconnecte de lui-meme. Le cookie « se souvenir de moi »
 * le reconnectait ensuite.
 *
 * Fermer un compte doit produire son effet tout de suite. La question
 * se repose donc a chaque requete.
 */
class VerifierCompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = Auth::user();

        if ($utilisateur !== null && ! $utilisateur->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', 'Ce compte est désactivé. Contactez votre administrateur.');
        }

        return $next($request);
    }
}
