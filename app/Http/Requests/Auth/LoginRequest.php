<?php

namespace App\Http\Requests\Auth;

use App\Models\Client;
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

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $this->ensureAccountIsUsable();

        RateLimiter::clear($this->throttleKey());
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

            throw ValidationException::withMessages([
                'email' => 'Ce compte est désactivé. Contactez votre administrateur.',
            ]);
        }

        if ($user->isClient() && Client::where('id', $user->id)->where('is_validated', false)->exists()) {
            Auth::logout();
            $this->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => 'Votre entreprise est en attente de validation. Vous recevrez un e-mail dès son activation.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

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
}
