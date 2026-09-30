<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Les caracteres de controle invisibles (hors tabulation et retours a la
 * ligne) n'ont leur place dans aucun champ. Colles dans un nom ou une
 * adresse, ils rendaient invalides les factures XML de l'entreprise.
 */
class RetirerCaracteresDeControle extends TransformsRequest
{
    /** @var list<string> */
    protected array $sauf = ['password', 'password_confirmation', 'current_password'];

    protected function transform($key, $value)
    {
        if (! is_string($value) || in_array($key, $this->sauf, true)) {
            return $value;
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? $value;
    }
}
