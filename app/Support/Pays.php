<?php

namespace App\Support;

use App\Models\Translation;

class Pays
{
    private const HORS_UNION = ['CH' => 'Suisse', 'GB' => 'Royaume-Uni', 'NO' => 'Norvège'];

    /** @var array<string, string>|null */
    private static ?array $index = null;

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

    public static function nom(?string $code): ?string
    {
        return array_flip(self::CODES)[strtoupper((string) $code)]
            ?? self::HORS_UNION[strtoupper((string) $code)]
            ?? null;
    }

    public static function localise(?string $nom): ?string
    {
        if ($nom === null || trim($nom) === '') {
            return $nom;
        }

        $code = self::depuisNom($nom);

        return $code === null ? $nom : \Locale::getDisplayRegion('-'.$code, app()->getLocale());
    }

    public static function libelle(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return self::nom($code) === null ? null : \Locale::getDisplayRegion('-'.$code, app()->getLocale());
    }

    public static function depuisNom(?string $nom): ?string
    {
        return self::index()[Traductions::cleDepuis((string) $nom)] ?? null;
    }

    public static function nomFrancais(string $nom): string
    {
        return self::nom(self::depuisNom($nom)) ?? $nom;
    }

    /**
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
