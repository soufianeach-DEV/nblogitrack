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
     * Format de chaque numero, prefixe compris (formats publies par la
     * Commission pour VIES ; Suisse, Royaume-Uni et Norvege par leurs
     * registres). Une faute de frappe se voit avant tout appel.
     */
    public const FORMATS = [
        'AT' => '/^ATU\d{8}$/', 'BE' => '/^BE[01]\d{9}$/', 'BG' => '/^BG\d{9,10}$/',
        'CY' => '/^CY\d{8}[A-Z]$/', 'CZ' => '/^CZ\d{8,10}$/', 'DE' => '/^DE\d{9}$/',
        'DK' => '/^DK\d{8}$/', 'EE' => '/^EE\d{9}$/', 'EL' => '/^EL\d{9}$/',
        'ES' => '/^ES[A-Z0-9]\d{7}[A-Z0-9]$/', 'FI' => '/^FI\d{8}$/', 'FR' => '/^FR[0-9A-Z]{2}\d{9}$/',
        'HR' => '/^HR\d{11}$/', 'HU' => '/^HU\d{8}$/', 'IE' => '/^IE(\d{7}[A-W][A-I]?|\d[A-Z+*]\d{5}[A-W])$/',
        'IT' => '/^IT\d{11}$/', 'LT' => '/^LT(\d{9}|\d{12})$/', 'LU' => '/^LU\d{8}$/',
        'LV' => '/^LV\d{11}$/', 'MT' => '/^MT\d{8}$/', 'NL' => '/^NL\d{9}B\d{2}$/',
        'PL' => '/^PL\d{10}$/', 'PT' => '/^PT\d{9}$/', 'RO' => '/^RO\d{2,10}$/',
        'SE' => '/^SE\d{10}01$/', 'SI' => '/^SI\d{8}$/', 'SK' => '/^SK\d{10}$/',
        'XI' => '/^XI(\d{9}|\d{12}|GD\d{3}|HA\d{3})$/',
        'CH' => '/^CHE\d{9}$/', 'GB' => '/^GB(\d{9}|\d{12}|GD\d{3}|HA\d{3})$/', 'NO' => '/^NO\d{9}(MVA)?$/',
    ];

    /** Exemple affiche quand le format ne correspond pas. */
    public const EXEMPLES = [
        'AT' => 'ATU12345678', 'BE' => 'BE0123456749', 'BG' => 'BG123456789', 'CY' => 'CY12345678L',
        'CZ' => 'CZ12345678', 'DE' => 'DE123456789', 'DK' => 'DK12345678', 'EE' => 'EE123456789',
        'EL' => 'EL123456789', 'ES' => 'ESB12345678', 'FI' => 'FI12345678', 'FR' => 'FR40303265045',
        'HR' => 'HR12345678901', 'HU' => 'HU12345678', 'IE' => 'IE1234567T', 'IT' => 'IT12345678901',
        'LT' => 'LT123456789', 'LU' => 'LU12345678', 'LV' => 'LV12345678901', 'MT' => 'MT12345678',
        'NL' => 'NL123456789B01', 'PL' => 'PL1234567890', 'PT' => 'PT123456789', 'RO' => 'RO1234567',
        'SE' => 'SE123456789001', 'SI' => 'SI12345678', 'SK' => 'SK1234567890', 'XI' => 'XI123456789',
        'CH' => 'CHE-123.456.788', 'GB' => 'GB123456789', 'NO' => 'NO923609016MVA',
    ];

    /**
     * @return array{pays: ?string, tva: ?string, national: ?string, peppol: ?string}
     */
    public static function analyser(string $saisie): array
    {
        // Les minuscules se mettent en capitales avant le nettoyage : un
        // « be0123... » tape tel quel perdait sinon son prefixe.
        $valeur = preg_replace('/[^0-9A-Z]/', '', strtoupper($saisie));

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

        // Suisse : CHE-123.456.789 (MWST, TVA, IVA). Norvege : 9 chiffres,
        // suivis de MVA s'ils sont inscrits a la TVA.
        if ($prefixe === 'CH') {
            $chiffres = preg_replace('/\D/', '', $numero);

            return ['pays' => 'CH', 'tva' => 'CHE'.$chiffres, 'national' => $chiffres, 'peppol' => '9927:CHE'.$chiffres];
        }

        if ($prefixe === 'NO' && preg_match('/^(\d{9})(MVA)?$/', $numero, $n)) {
            return ['pays' => 'NO', 'tva' => 'NO'.$n[1].'MVA', 'national' => $n[1], 'peppol' => '0192:'.$n[1]];
        }
        $pays = self::PREFIXES_PARTICULIERS[$prefixe] ?? $prefixe;
        $schema = self::SCHEMAS[$prefixe] ?? null;

        if ($schema === null) {
            return ['pays' => $pays, 'tva' => $valeur, 'national' => null, 'peppol' => null];
        }

        if ($prefixe === 'BE') {
            $entreprise = str_pad($numero, 10, '0', STR_PAD_LEFT);

            // Un ancien numero a neuf chiffres se range sous sa forme
            // actuelle : sinon BE123456749 et BE0123456749 passeraient
            // pour deux entreprises differentes.
            $tva = preg_match('/^\d{9}$/', $numero) ? 'BE'.$entreprise : $valeur;

            return ['pays' => 'BE', 'tva' => $tva, 'national' => $entreprise, 'peppol' => '0208:'.$entreprise];
        }

        if ($prefixe === 'FR' && preg_match('/(\d{9})$/', $numero, $s)) {
            return ['pays' => 'FR', 'tva' => $valeur, 'national' => $s[1], 'peppol' => '0002:'.$s[1]];
        }

        if (in_array($prefixe, self::SANS_PREFIXE, true)) {
            return ['pays' => $pays, 'tva' => $valeur, 'national' => $numero, 'peppol' => $schema.':'.$numero];
        }

        return ['pays' => $pays, 'tva' => $valeur, 'national' => null, 'peppol' => $schema.':'.$valeur];
    }

    /**
     * Controle local, avant tout appel au registre europeen. Seuls les
     * numeros belges sont verifies : dix chiffres (un ancien numero a
     * neuf chiffres se lit precede d'un 0), le premier valant 0 ou 1, et
     * les deux derniers egaux a 97 moins le reste des huit premiers par
     * 97. Les autres pays restent a l'appreciation de VIES.
     */
    public static function controleLocal(string $saisie): bool
    {
        $identifiant = self::analyser($saisie);
        $tva = (string) $identifiant['tva'];
        $prefixe = substr($tva, 0, 2);
        $format = self::FORMATS[$prefixe] ?? null;

        // Prefixe inconnu (hors Europe) : a l'appreciation du registre.
        if ($format !== null && ! preg_match($format, $tva)) {
            return false;
        }

        return match ($identifiant['pays']) {
            'BE' => self::numeroBelgeValide(substr($tva, 2)),
            'FR' => self::cleFrancaiseValide($tva),
            'CH', 'NO' => self::moduloOnze((string) $identifiant['national'], $identifiant['pays'] === 'CH' ? [5, 4, 3, 2, 7, 6, 5, 4] : [3, 2, 7, 6, 5, 4, 3, 2]),
            default => true,
        };
    }

    /** Exemple de numero pour le pays du prefixe saisi. */
    public static function exemple(string $saisie): ?string
    {
        $valeur = preg_replace('/[^0-9A-Z]/', '', strtoupper($saisie));

        return self::EXEMPLES[substr($valeur, 0, 2)] ?? null;
    }

    /** Cle francaise : (12 + 3 x (SIREN mod 97)) mod 97, si elle est numerique. */
    private static function cleFrancaiseValide(string $tva): bool
    {
        $cle = substr($tva, 2, 2);

        return ! ctype_digit($cle) || self::tvaFrancaise(substr($tva, 4, 9)) === $tva;
    }

    /**
     * Cle de controle modulo 11 des numeros suisse (UID) et norvegien
     * (organisasjonsnummer) : le dernier chiffre.
     *
     * @param  list<int>  $poids
     */
    private static function moduloOnze(string $numero, array $poids): bool
    {
        if (! preg_match('/^\d{9}$/', $numero)) {
            return false;
        }

        $somme = 0;

        foreach ($poids as $i => $p) {
            $somme += (int) $numero[$i] * $p;
        }

        $cle = 11 - $somme % 11;
        $cle = $cle === 11 ? 0 : $cle;

        return $cle !== 10 && $cle === (int) $numero[8];
    }

    public static function numeroBelgeValide(string $numero): bool
    {
        if (preg_match('/^\d{9}$/', $numero)) {
            $numero = '0'.$numero;
        }

        if (! preg_match('/^[01]\d{9}$/', $numero)) {
            return false;
        }

        return 97 - ((int) substr($numero, 0, 8) % 97) === (int) substr($numero, 8, 2);
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
