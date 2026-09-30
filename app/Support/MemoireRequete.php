<?php

namespace App\Support;

/**
 * Resultats gardes le temps d'une requete de lecture. L'ecran de
 * planification recalculait, pour chaque ligne, les memes camions
 * candidats au fret retour et les memes missions par camion : des
 * dizaines de requetes identiques. Inactive par defaut : un calcul fait
 * pendant une ecriture doit toujours relire la base.
 */
class MemoireRequete
{
    private bool $actif = false;

    /** @var array<string, mixed> */
    private array $valeurs = [];

    public static function activer(): void
    {
        app(self::class)->actif = true;
    }

    public static function retenir(string $cle, callable $calcul): mixed
    {
        $memoire = app(self::class);

        if (! $memoire->actif) {
            return $calcul();
        }

        if (! array_key_exists($cle, $memoire->valeurs)) {
            $memoire->valeurs[$cle] = $calcul();
        }

        return $memoire->valeurs[$cle];
    }
}
