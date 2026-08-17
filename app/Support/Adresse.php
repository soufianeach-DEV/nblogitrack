<?php

namespace App\Support;

class Adresse
{
    public static function localite(string $adresse): string
    {
        $segments = array_map('trim', explode(',', $adresse));

        foreach (array_reverse($segments) as $segment) {
            if (preg_match('/^\d{4,6}\s+(.+)$/u', $segment, $trouve)) {
                return trim($trouve[1]);
            }
        }

        $dernier = (string) end($segments);

        return $dernier !== '' ? $dernier : $adresse;
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
}
