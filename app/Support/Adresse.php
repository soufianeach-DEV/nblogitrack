<?php

namespace App\Support;

class Adresse
{
    /**
     * Le segment « code postal et localite » : « 1000 Bruxelles », « 105 57
     * Athènes », « 1012 LG Amsterdam », « 00-950 Varsovie », « 1000-001
     * Lisbonne », « LV-1050 Riga », « SW1A 2AA Londres » (ou « SW1A
     * Londres », seule partie que publie GeoNames), « D02 X285 Dublin ».
     */
    private const SEGMENT = '/^((?:[A-Z]{1,2}-)?(?:\d{4}\s?[A-Z]{2}|\d{2}-\d{3}|\d{4}-\d{3}|\d{3}\s\d{2}|\d{4,6})|[A-Z]\d[\dW]\s[A-Z\d]{4}|[A-Z]{1,2}\d[A-Z\d]?(?:\s\d[A-Z]{2})?)\s+(.+)$/u';

    public static function localite(string $adresse): string
    {
        $segments = array_map('trim', explode(',', $adresse));

        foreach (array_reverse($segments) as $segment) {
            if (preg_match(self::SEGMENT, $segment, $trouve)) {
                return trim($trouve[2]);
            }
        }

        $dernier = (string) end($segments);

        return $dernier !== '' ? $dernier : $adresse;
    }

    public static function codePostal(string $adresse): ?string
    {
        foreach (array_reverse(array_map('trim', explode(',', $adresse))) as $segment) {
            if (preg_match(self::SEGMENT, $segment, $trouve)) {
                return trim($trouve[1]);
            }
        }

        return null;
    }

    public static function pays(string $adresse): ?string
    {
        $segments = array_values(array_filter(array_map('trim', explode(',', $adresse))));
        $dernier = (string) end($segments);

        if (count($segments) < 2 || $dernier === '' || preg_match('/\d/', $dernier)) {
            return null;
        }

        return $dernier;
    }

    /**
     * Le pays ecrit dans l'adresse est-il celui declare ? C'est la que va le
     * chauffeur : on ne change pas de grille en mentant sur le pays. Sans
     * pays lisible, l'adresse est refusee si $strict (enlevement hors de
     * Belgique), acceptee sinon.
     */
    public static function paysCoherent(string $adresse, string $pays, bool $strict): bool
    {
        $nom = self::pays($adresse);
        $code = $nom === null ? null : Pays::depuisNom($nom);

        if ($code === null) {
            return ! $strict;
        }

        return $code === strtoupper($pays);
    }
}
