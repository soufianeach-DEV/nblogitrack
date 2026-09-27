<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Meme adresse en minuscules que celle du compte.
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                // Choisir son mot de passe par le lien recu prouve l'adresse :
                // le compte n'est plus affiche comme « en attente ».
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                // Une reinitialisation ferme toutes les sessions ouvertes :
                // celle d'un intrus qui aurait pris le compte ne survit pas
                // au nouveau mot de passe.
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))
                        ->where('user_id', $user->id)
                        ->delete();
                }

                event(new PasswordReset($user));
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        // Une adresse sans compte recoit le meme message qu'un lien perime :
        // le formulaire ne sert plus a tester quelles adresses sont inscrites.
        if ($status === Password::INVALID_USER) {
            $status = Password::INVALID_TOKEN;
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
