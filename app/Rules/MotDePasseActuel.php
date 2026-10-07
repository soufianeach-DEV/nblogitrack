<?php

namespace App\Rules;

use App\Support\JournalSecurite;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * La regle « current_password » de Laravel, avec le meme message, qui
 * inscrit en plus au journal chaque mot de passe actuel errone.
 */
class MotDePasseActuel implements ValidationRule
{
    public function __construct(private string $ecran) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        if ($user !== null && Hash::check((string) $value, $user->getAuthPassword())) {
            return;
        }

        if ($user !== null) {
            JournalSecurite::motDePasseRefuse($user, $this->ecran);
        }

        $fail('validation.current_password')->translate();
    }
}
