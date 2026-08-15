<?php

namespace App\Support;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;

/**
 * Le dictionnaire d'une langue, servi a la page.
 *
 * Une requete par chargement de page serait payee sur chaque écran :
 * le dictionnaire tient en cache et l'administration le vide quand elle
 * enregistre. Le cache porte la langue dans sa cle, sinon le neerlandais
 * servirait du francais au premier visiteur suivant.
 */
class Traductions
{
    public const DUREE_CACHE = 86400;

    /** @return array<string, string> */
    public static function pour(string $langue): array
    {
        return Cache::remember(
            self::cle($langue),
            self::DUREE_CACHE,
            fn () => Translation::all()
                ->mapWithKeys(fn (Translation $t) => [$t->cle => $t->pour($langue)])
                ->all(),
        );
    }

    public static function oublier(): void
    {
        foreach (array_keys(Translation::LANGUES) as $langue) {
            Cache::forget(self::cle($langue));
        }
    }

    public static function estServie(?string $langue): bool
    {
        return $langue !== null && array_key_exists($langue, Translation::LANGUES);
    }

    private static function cle(string $langue): string
    {
        return 'traductions.'.$langue;
    }
}
