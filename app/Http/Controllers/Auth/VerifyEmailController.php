<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Le lien signe suffit : il ne demande pas d'etre connecte. Une
     * entreprise qui vient de s'inscrire ne peut pas encore se connecter
     * (validation en attente) et doit pourtant pouvoir confirmer son
     * adresse depuis le courriel recu.
     */
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        abort_if($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            ActivityLog::record('auth.email_verified', 'Adresse confirmée : '.$user->email, $user, [], $user->id);
        }

        if ($request->user()?->is($user)) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        return redirect()->route('login')->with('status', Traductions::t(
            'msg.adresse_confirmee',
            'Votre adresse e-mail est confirmée. Vous pouvez vous connecter dès que votre compte est actif.',
        ));
    }
}
