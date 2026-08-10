<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class JournaliserAuthentification
{
    public function connexion(Login $event): void
    {
        ActivityLog::record(
            'auth.login',
            'Connexion de '.$event->user->email,
            $event->user,
            ['role' => $event->user->role],
            $event->user->id,
        );
    }

    public function deconnexion(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        ActivityLog::record(
            'auth.logout',
            'Déconnexion de '.$event->user->email,
            $event->user,
            [],
            $event->user->id,
        );
    }

    public function echec(Failed $event): void
    {
        ActivityLog::record(
            'auth.failed',
            'Échec de connexion pour '.($event->credentials['email'] ?? 'adresse inconnue'),
            null,
            ['email' => $event->credentials['email'] ?? null],
            $event->user?->getAuthIdentifier(),
        );
    }

    public function blocage(Lockout $event): void
    {
        ActivityLog::record(
            'auth.lockout',
            'Trop de tentatives : accès temporairement bloqué pour '.$event->request->input('email', 'adresse inconnue'),
            null,
            ['email' => $event->request->input('email')],
        );
    }

    public function subscribe(): array
    {
        return [
            Login::class => 'connexion',
            Logout::class => 'deconnexion',
            Failed::class => 'echec',
            Lockout::class => 'blocage',
        ];
    }
}
