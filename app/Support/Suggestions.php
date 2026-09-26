<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Suggestions d'un champ de recherche : les valeurs existantes qui
 * contiennent le texte tape (sans accents ni casse), par ordre
 * alphabetique. Deux caracteres au moins.
 */
final class Suggestions
{
    /**
     * @param  callable(): Builder  $requete  la requete de depart, sans le filtre de recherche
     * @param  list<string>  $colonnes
     * @return list<string>
     */
    public static function depuis(callable $requete, array $colonnes, ?string $terme, int $limite = 8): array
    {
        $terme = trim((string) $terme);

        if (mb_strlen($terme) < 2) {
            return [];
        }

        $valeurs = [];

        foreach ($colonnes as $colonne) {
            $valeurs = [...$valeurs, ...$requete()
                ->whereContient($colonne, $terme)
                ->whereNotNull($colonne)
                ->distinct()
                ->orderBy($colonne)
                ->limit($limite)
                ->pluck($colonne)
                ->map(fn ($v) => (string) $v)
                ->all()];
        }

        return self::ranger($valeurs, $limite);
    }

    /**
     * @param  list<string>  $valeurs
     * @return list<string>
     */
    public static function ranger(array $valeurs, int $limite = 8): array
    {
        $valeurs = array_values(array_unique(array_filter(array_map('trim', $valeurs))));
        collator_sort(collator_create(app()->getLocale()), $valeurs);

        return array_slice($valeurs, 0, $limite);
    }
}
