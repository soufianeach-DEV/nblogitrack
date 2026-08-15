<?php

namespace App\Support;

use App\Models\Translation;

class Pays
{
    /**
     * Les voisins hors Union que couvrent les grilles tarifaires.
     */
    private const HORS_UNION = ['CH' => 'Suisse', 'GB' => 'Royaume-Uni', 'NO' => 'Norvège'];

    /** @var array<string, string>|null */
    private static ?array $index = null;

    /**
     * Les vingt-sept Etats membres, du nom francais vers le code ISO 3166-1
     * alpha-2 qu'attend la norme europeenne de facturation.
     *
     * La Grece fait exception dans le monde de la TVA : son code pays est GR
     * mais son prefixe de numero de TVA est EL. C'est bien le code pays qui
     * va dans l'adresse d'une facture.
     */
    private const CODES = [
        'Allemagne' => 'DE',
        'Autriche' => 'AT',
        'Belgique' => 'BE',
        'Bulgarie' => 'BG',
        'Chypre' => 'CY',
        'Croatie' => 'HR',
        'Danemark' => 'DK',
        'Espagne' => 'ES',
        'Estonie' => 'EE',
        'Finlande' => 'FI',
        'France' => 'FR',
        'Grèce' => 'GR',
        'Hongrie' => 'HU',
        'Irlande' => 'IE',
        'Italie' => 'IT',
        'Lettonie' => 'LV',
        'Lituanie' => 'LT',
        'Luxembourg' => 'LU',
        'Malte' => 'MT',
        'Pays-Bas' => 'NL',
        'Pologne' => 'PL',
        'Portugal' => 'PT',
        'Roumanie' => 'RO',
        'Slovaquie' => 'SK',
        'Slovénie' => 'SI',
        'Suède' => 'SE',
        'Tchéquie' => 'CZ',
    ];

    public static function code(?string $nom): string
    {
        return self::CODES[trim((string) $nom)] ?? 'BE';
    }

    /**
     * Le chemin inverse : du code ISO vers le nom francais. Les grilles
     * tarifaires couvrent aussi des pays hors Union, absents de la table
     * ci-dessus.
     */
    public static function nom(?string $code): ?string
    {
        return array_flip(self::CODES)[strtoupper((string) $code)]
            ?? self::HORS_UNION[strtoupper((string) $code)]
            ?? null;
    }

    /**
     * Le nom d'un pays dans la langue de la page.
     *
     * Contrairement a une rue, un pays a une forme officielle par langue :
     * « Pays-Bas », « Nederland » et « Netherlands » designent la meme
     * entite sans ambiguite pour personne. Le traduire ne cree donc pas
     * la divergence qu'on refuse pour les adresses.
     *
     * Les libelles viennent de l'ICU, pas du dictionnaire : c'est lui qui
     * suit les changements de nom, et il les connait dans toutes les
     * langues. Une valeur qu'il ne reconnait pas s'affiche telle quelle.
     */
    public static function localise(?string $nom): ?string
    {
        if ($nom === null || trim($nom) === '') {
            return $nom;
        }

        $code = self::depuisNom($nom);

        return $code === null ? $nom : \Locale::getDisplayRegion('-'.$code, app()->getLocale());
    }

    /**
     * Le nom d'un pays a partir de son code, dans la langue de la page.
     *
     * Le detour par nom() n'est pas decoratif : il limite le resultat aux
     * pays que l'application dessert. L'ICU rendrait volontiers « Japan »,
     * mais aucune grille tarifaire n'y mene.
     */
    public static function libelle(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return self::nom($code) === null ? null : \Locale::getDisplayRegion('-'.$code, app()->getLocale());
    }

    /**
     * Le code ISO d'un nom de pays ecrit dans n'importe quelle langue
     * servie. Sert aux filtres : l'ecran propose « Nederland », la
     * colonne ne connait que « Pays-Bas ».
     */
    public static function depuisNom(?string $nom): ?string
    {
        return self::index()[Traductions::cleDepuis((string) $nom)] ?? null;
    }

    /** Ramene un nom de pays a la forme francaise, celle qui est en base. */
    public static function nomFrancais(string $nom): string
    {
        return self::nom(self::depuisNom($nom)) ?? $nom;
    }

    /**
     * Tous les noms de pays reconnus, ramenes a leur code.
     *
     * Trente pays fois trois langues font quatre-vingt-dix appels a
     * l'ICU : ils sont payes une fois par requete, pas une fois par
     * ligne de tableau.
     *
     * La table francaise est reappliquee en dernier : ce sont ses noms
     * qui sont en base, ils doivent etre reconnus meme le jour ou l'ICU
     * en ecrit un autrement.
     *
     * @return array<string, string>
     */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        self::$index = [];
        $codes = array_merge(array_values(self::CODES), array_keys(self::HORS_UNION));

        foreach ($codes as $code) {
            foreach (array_keys(Translation::LANGUES) as $langue) {
                self::$index[Traductions::cleDepuis(\Locale::getDisplayRegion('-'.$code, $langue))] = $code;
            }
        }

        foreach (self::CODES + array_flip(self::HORS_UNION) as $nom => $code) {
            self::$index[Traductions::cleDepuis((string) $nom)] = $code;
        }

        return self::$index;
    }
}
