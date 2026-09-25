<?php

namespace App\Support;

use Carbon\CarbonInterface;
use NumberFormatter;

/**
 * Les montants, les poids et les dates tels qu'on les ecrit dans la langue
 * du lecteur : « 1 234,50 € » en francais et en neerlandais, « €1,234.50 »
 * en anglais. Les courriels et le PDF, rendus hors de React, passent par ici.
 */
class Formats
{
    private const REGIONS = ['fr' => 'fr_BE', 'nl' => 'nl_BE', 'en' => 'en_IE'];

    public static function montant(float|string|null $valeur): string
    {
        $format = new NumberFormatter(self::region(), NumberFormatter::CURRENCY);

        return (string) $format->formatCurrency((float) $valeur, 'EUR');
    }

    public static function nombre(float|string|null $valeur, int $decimales = 0): string
    {
        $format = new NumberFormatter(self::region(), NumberFormatter::DECIMAL);
        $format->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimales);
        $format->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimales);

        return (string) $format->format((float) $valeur);
    }

    public static function date(?CarbonInterface $date): string
    {
        return $date?->format('d/m/Y') ?? '';
    }

    public static function dateHeure(?CarbonInterface $date): string
    {
        if ($date === null) {
            return '';
        }

        return $date->format(Traductions::t('msg.format_date_heure', 'd/m/Y à H\hi'));
    }

    private static function region(): string
    {
        return self::REGIONS[app()->getLocale()] ?? 'fr_BE';
    }
}
