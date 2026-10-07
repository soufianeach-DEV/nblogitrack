<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Les tentatives que le journal ne voyait pas : un acces refuse (403) et
 * un mot de passe actuel errone sur un ecran ou l'on est deja connecte.
 * Quelqu'un qui essaie les numeros de commande d'autres entreprises, ou
 * qui devine le mot de passe depuis une session volee, laisse une trace.
 *
 * Un flot de refus n'ecrit pas une ligne par refus : une meme requete
 * refusee n'est inscrite qu'une fois par minute, et dix lignes au plus par
 * minute pour un meme compte (ou une meme adresse IP, sans compte). Chaque
 * mot de passe errone compte, dans cette meme limite : les ecrans qui le
 * demandent limitent deja les essais.
 */
class JournalSecurite
{
    public const PAR_MINUTE = 10;

    public static function accesRefuse(Request $request): void
    {
        $user = $request->user();
        $requete = $request->method().' /'.ltrim($request->path(), '/');

        if (! self::aInscrire($user, $request->ip(), $requete)) {
            return;
        }

        ActivityLog::record(
            'auth.access_denied',
            'Accès refusé à '.($user?->email ?? 'un visiteur').' : '.$requete,
            null,
            ['requete' => mb_substr($requete, 0, 200)],
        );
    }

    /**
     * Un refus qui repond « introuvable », pour ne pas dire a un autre
     * client que l'expedition ou la facture existe : il est inscrit au
     * journal comme un acces refuse.
     */
    public static function introuvable(Request $request): never
    {
        rescue(fn () => self::accesRefuse($request));

        abort(404);
    }

    /** L'ecran est nomme en clair : « confirmation du mot de passe »... */
    public static function motDePasseRefuse(User $user, string $ecran): void
    {
        if (! self::aInscrire($user, request()->ip())) {
            return;
        }

        ActivityLog::record(
            'auth.password_rejected',
            'Mot de passe actuel erroné pour '.$user->email.' ('.$ecran.')',
            $user,
            [],
            $user->id,
        );
    }

    private static function aInscrire(?User $user, ?string $ip, ?string $requete = null): bool
    {
        $auteur = $user !== null ? 'compte:'.$user->id : 'ip:'.$ip;
        $minute = intdiv(now()->getTimestamp(), 60);

        if ($requete !== null && ! Cache::add('journal-securite:'.sha1($auteur.'|'.$requete).':'.$minute, true, 120)) {
            return false;
        }

        if (RateLimiter::tooManyAttempts('journal-securite:'.$auteur, self::PAR_MINUTE)) {
            return false;
        }

        RateLimiter::hit('journal-securite:'.$auteur, 60);

        return true;
    }
}
