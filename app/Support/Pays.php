<?php

namespace App\Support;

class Pays
{
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
        $hors = ['CH' => 'Suisse', 'GB' => 'Royaume-Uni', 'NO' => 'Norvège'];

        return array_flip(self::CODES)[strtoupper((string) $code)]
            ?? $hors[strtoupper((string) $code)]
            ?? null;
    }
}
