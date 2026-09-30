<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

use function Illuminate\Support\defer;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Les adresses sont rangees en minuscules : « Jean@Exemple.be »
        // ne trouvait aucun compte et aucun lien ne partait.
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $request->validate([
            'email' => 'required|email',
        ]);

        // L'envoi part apres la reponse : sa duree (connexion au serveur de
        // courriel) ne trahit plus qu'un compte existe pour cette adresse.
        $adresse = $request->only('email');
        defer(fn () => Password::sendResetLink($adresse));

        // La reponse est la meme que l'adresse ait un compte ou non, et
        // que l'envoi ait ete retenu par la limite ou pas : une reponse
        // differente disait a n'importe qui quelles adresses sont inscrites.
        return back()->with('status', __('passwords.user'));
    }
}
