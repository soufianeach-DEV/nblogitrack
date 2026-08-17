<?php

namespace App\Support;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;

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

    /**
     * @param  array<string, string|int>  $valeurs
     */
    public static function t(string $cle, string $defaut, array $valeurs = []): string
    {
        $texte = self::pour(app()->getLocale())[$cle] ?? $defaut;

        foreach ($valeurs as $nom => $valeur) {
            $texte = str_replace(':'.$nom, (string) $valeur, $texte);
        }

        return $texte;
    }

    public static function vocabulaire(string $groupe, ?string $valeur): ?string
    {
        if ($valeur === null || trim($valeur) === '') {
            return $valeur;
        }

        return self::t('vocab.'.$groupe.'.'.self::cleDepuis($valeur), $valeur);
    }

    public static function vocabulaireEnFrancais(string $groupe, string $valeur): string
    {
        $prefixe = 'vocab.'.$groupe.'.';
        $cherche = self::cleDepuis($valeur);

        foreach (array_keys(Translation::LANGUES) as $langue) {
            foreach (self::pour($langue) as $cle => $texte) {
                if (str_starts_with($cle, $prefixe) && self::cleDepuis($texte) === $cherche) {
                    return self::pour('fr')[$cle] ?? $valeur;
                }
            }
        }

        return $valeur;
    }

    public static function cleDepuis(string $valeur): string
    {
        $sansAccent = strtr(
            mb_strtolower($valeur, 'UTF-8'),
            ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e',
                'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
                'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'œ' => 'oe'],
        );

        return trim(preg_replace('/[^a-z0-9]+/', '_', $sansAccent), '_');
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
