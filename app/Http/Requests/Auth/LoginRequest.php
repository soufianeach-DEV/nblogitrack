<?php

namespace App\Http\Requests\Auth;

use App\Models\Client;
use App\Support\Traductions;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'password' => $this->input('password'),
        ], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->cleDuCompte(), 900);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $this->ensureAccountIsUsable();

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->cleDuCompte());
    }

    /**
     * @throws ValidationException
     */
    protected function ensureAccountIsUsable(): void
    {
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $this->session()->invalidate();

            // Une entreprise refusee n'a pas d'administrateur a contacter :
            // on lui dit que sa demande n'a pas ete retenue, comme dans le
            // courriel qu'elle a recu.
            $refusee = $user->isClient()
                && Client::where('id', $user->client_id)->whereNotNull('rejection_reason')->exists();

            throw ValidationException::withMessages([
                'email' => $refusee
                    ? Traductions::t('msg.inscription_refusee', 'Votre demande d\'inscription n\'a pas été retenue. Le motif vous a été envoyé par e-mail.')
                    : Traductions::t('msg.compte_desactive', 'Ce compte est désactivé. Contactez votre administrateur.'),
            ]);
        }

        if ($user->isClient() && Client::where('id', $user->client_id)->where('is_validated', false)->exists()) {
            Auth::logout();
            $this->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => Traductions::t('msg.entreprise_en_attente', 'Votre entreprise est en attente de validation. Vous recevrez un e-mail dès son activation.'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        // Cinq essais par adresse IP, et quinze par compte toutes adresses
        // confondues sur un quart d'heure : changer d'adresse ne suffit plus
        // pour deviner le mot de passe d'un compte.
        $cle = match (true) {
            RateLimiter::tooManyAttempts($this->throttleKey(), 5) => $this->throttleKey(),
            RateLimiter::tooManyAttempts($this->cleDuCompte(), 15) => $this->cleDuCompte(),
            default => null,
        };

        if ($cle === null) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($cle);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    private function cleDuCompte(): string
    {
        return 'compte|'.Str::transliterate(Str::lower(trim((string) $this->string('email'))));
    }
}
