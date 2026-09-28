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

        return redirect()->route('login')->with('status', $this->message($user));
    }

    /**
     * Le message dit ce qui reste a faire : une entreprise qui vient de
     * s'inscrire attend encore la validation, et « vous pouvez vous
     * connecter » l'envoyait sur un refus a l'ecran de connexion.
     */
    private function message(User $user): string
    {
        $client = $user->isClient() ? $user->client : null;

        if ($client !== null && ! $client->is_validated && $client->rejection_reason === null) {
            return Traductions::t(
                'msg.adresse_confirmee_attente',
                'Votre adresse e-mail est confirmée. Votre entreprise doit encore être validée par un administrateur : vous recevrez un e-mail dès son activation, puis vous pourrez vous connecter.',
            );
        }

        if ($user->is_active && ($client === null || $client->is_validated)) {
            return Traductions::t(
                'msg.adresse_confirmee_connexion',
                'Votre adresse e-mail est confirmée. Vous pouvez vous connecter.',
            );
        }

        return Traductions::t(
            'msg.adresse_confirmee',
            'Votre adresse e-mail est confirmée. Vous pouvez vous connecter dès que votre compte est actif.',
        );
    }
}
