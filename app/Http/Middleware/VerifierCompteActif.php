<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

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
