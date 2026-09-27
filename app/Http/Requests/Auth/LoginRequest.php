<?php

namespace App\Http\Requests\Auth;

use App\Models\Client;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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
        $email = mb_strtolower(trim((string) $this->input('email')));

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt([
            'email' => $email,
            'password' => $this->input('password'),
        ], $this->boolean('remember'))) {
            // Une adresse inconnue coute le meme calcul qu'un mot de passe
            // faux : le temps de reponse ne dit plus si le compte existe.
            if (! User::where('email', $email)->exists()) {
                Hash::check((string) $this->input('password'), Cache::rememberForever('connexion.leurre', fn () => Hash::make(Str::random(40))));
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $this->ensureAccountIsUsable();

        RateLimiter::clear($this->throttleKey());
        Cache::put($this->cleAdresseConnue(), true, now()->addDays(180));
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
        // Le compteur avance avant la verification du mot de passe : des
        // essais envoyes en rafale ne passent plus tous sous la limite.
        //
        // Cinq essais par adresse IP. Et quinze par compte sur un quart
        // d'heure, toutes adresses confondues, pour qu'on ne devine pas un
        // mot de passe en changeant d'adresse. Ce second compteur ignore les
        // adresses d'ou le compte s'est deja connecte : un inconnu qui
        // l'epuise n'empeche plus son titulaire d'entrer.
        $cle = match (true) {
            RateLimiter::hit($this->throttleKey()) > 5 => $this->throttleKey(),
            ! Cache::has($this->cleAdresseConnue()) && RateLimiter::hit($this->cleDuCompte(), 900) > 15 => $this->cleDuCompte(),
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

    private function cleAdresseConnue(): string
    {
        return 'connexion.adresse|'.sha1(Str::lower(trim((string) $this->string('email'))).'|'.$this->ip());
    }

    private function cleDuCompte(): string
    {
        return 'compte|'.Str::transliterate(Str::lower(trim((string) $this->string('email'))));
    }
}
