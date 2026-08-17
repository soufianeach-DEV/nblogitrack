<?php

namespace App\Support;

class IdentifiantEntreprise
{
    public const SCHEMAS = [
        'AT' => '9914', 'BE' => '0208', 'BG' => '9926', 'CH' => '9927', 'CY' => '9928',
        'CZ' => '9929', 'DE' => '9930', 'DK' => '0096', 'EE' => '9931', 'EL' => '9933',
        'ES' => '9920', 'FI' => '0213', 'FR' => '0002', 'GB' => '9932', 'GR' => '9933',
        'HR' => '9934', 'HU' => '9910', 'IE' => '9935', 'IT' => '0211', 'LT' => '9937',
        'LU' => '9938', 'LV' => '9939', 'MT' => '9943', 'NL' => '9944', 'NO' => '0192',
        'PL' => '9945', 'PT' => '9946', 'RO' => '9947', 'SE' => '0007', 'SI' => '9949',
        'SK' => '9950', 'XI' => '9932',
    ];

    private const PREFIXES_PARTICULIERS = ['EL' => 'GR', 'XI' => 'GB'];

    private const SANS_PREFIXE = ['BE', 'DK', 'NO', 'SE'];

    /**
     * @return array{pays: ?string, tva: ?string, national: ?string, peppol: ?string}
     */
    public static function analyser(string $saisie): array
    {
        $valeur = strtoupper(preg_replace('/[^0-9A-Z]/', '', $saisie));

        if ($valeur === '') {
            return self::vide();
        }

        if (preg_match('/^\d{9}$|^\d{14}$/', $valeur)) {
            return [
                'pays' => 'FR',
                'tva' => self::tvaFrancaise(substr($valeur, 0, 9)),
                'national' => $valeur,
                'peppol' => '0002:'.$valeur,
            ];
        }

        if (! preg_match('/^([A-Z]{2})([0-9A-Z]+)$/', $valeur, $m)) {
            return self::vide();
        }

        [$prefixe, $numero] = [$m[1], $m[2]];
        $pays = self::PREFIXES_PARTICULIERS[$prefixe] ?? $prefixe;
        $schema = self::SCHEMAS[$prefixe] ?? null;

        if ($schema === null) {
            return ['pays' => $pays, 'tva' => $valeur, 'national' => null, 'peppol' => null];
        }

        if ($prefixe === 'BE') {
            $entreprise = str_pad($numero, 10, '0', STR_PAD_LEFT);

            return ['pays' => 'BE', 'tva' => $valeur, 'national' => $entreprise, 'peppol' => '0208:'.$entreprise];
        }

        if ($prefixe === 'FR' && preg_match('/(\d{9})$/', $numero, $s)) {
            return ['pays' => 'FR', 'tva' => $valeur, 'national' => $s[1], 'peppol' => '0002:'.$s[1]];
        }

        if (in_array($prefixe, self::SANS_PREFIXE, true)) {
            return ['pays' => $pays, 'tva' => $valeur, 'national' => $numero, 'peppol' => $schema.':'.$numero];
        }

        return ['pays' => $pays, 'tva' => $valeur, 'national' => null, 'peppol' => $schema.':'.$valeur];
    }

    private static function tvaFrancaise(string $siren): string
    {
        $cle = (12 + 3 * ((int) $siren % 97)) % 97;

        return 'FR'.str_pad((string) $cle, 2, '0', STR_PAD_LEFT).$siren;
    }

    /**
     * @return array{pays: null, tva: null, national: null, peppol: null}
     */
    private static function vide(): array
    {
        return ['pays' => null, 'tva' => null, 'national' => null, 'peppol' => null];
    }
}
