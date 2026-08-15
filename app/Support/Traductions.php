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

    /**
     * Traduit une cle cote serveur, dans la langue de la requete.
     *
     * Le second argument est le texte francais ecrit en clair a l'appel :
     * il sert de repli et garde le code lisible, comme le t() de React.
     * Les valeurs entre deux-points sont remplacees.
     *
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

    /**
     * Traduit une valeur de vocabulaire enregistree en base.
     *
     * Certaines colonnes portent du texte francais que l'application a
     * elle-meme propose : la fonction d'un contact, le secteur d'une
     * entreprise, la nature d'une marchandise. Ce sont des listes fermees
     * en pratique, mais saisies en clair, donc impossibles a traiter comme
     * des cles fixes.
     *
     * La cle se derive de la valeur : « Chargé de clientèle » donne
     * vocab.fonction.charge_de_clientele. Une saisie libre inconnue du
     * dictionnaire retombe sur le texte enregistre, ce qui vaut mieux
     * qu'une cle technique sous les yeux du client.
     *
     * Les adresses ne passent jamais par ici : une adresse postale est un
     * identifiant, pas du texte. Traduire un nom de rue produirait une
     * adresse qui ne correspond plus a l'etiquette ni a la lettre de
     * voiture.
     */
    public static function vocabulaire(string $groupe, ?string $valeur): ?string
    {
        if ($valeur === null || trim($valeur) === '') {
            return $valeur;
        }

        return self::t('vocab.'.$groupe.'.'.self::cleDepuis($valeur), $valeur);
    }

    /** Le slug d'une valeur : minuscules, sans accent, separateurs unifies. */
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
