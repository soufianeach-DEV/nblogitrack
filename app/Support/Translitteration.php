<?php

namespace App\Support;

/**
 * Lettres latines pour les noms et adresses que VIES renvoie en
 * cyrillique (Bulgarie) ou en grec (Grece) : c'est la forme que le
 * chauffeur, la lettre de voiture et la facture peuvent utiliser.
 *
 * Bulgare : systeme officiel de la loi sur la translitteration (2009),
 * « София » -> « Sofia », « България » -> « Balgaria ». Grec : norme
 * ELOT 743, « Αθήνα » -> « Athina ».
 */
final class Translitteration
{
    private const BULGARE = [
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ж' => 'Zh', 'З' => 'Z',
        'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P',
        'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
        'Ш' => 'Sh', 'Щ' => 'Sht', 'Ъ' => 'A', 'Ь' => 'Y', 'Ю' => 'Yu', 'Я' => 'Ya',
    ];

    private const GREC = [
        'Α' => 'A', 'Β' => 'V', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'I', 'Θ' => 'Th',
        'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => 'X', 'Ο' => 'O', 'Π' => 'P',
        'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'Ch', 'Ψ' => 'Ps', 'Ω' => 'O',
    ];

    /** Le texte contient-il des lettres cyrilliques ou grecques ? */
    public static function aTranslitterer(?string $texte): bool
    {
        return $texte !== null && preg_match('/[\p{Cyrillic}\p{Greek}]/u', $texte) === 1;
    }

    public static function latin(?string $texte): string
    {
        if (! self::aTranslitterer($texte)) {
            return (string) $texte;
        }

        // Bulgare : « ия » en fin de mot s'ecrit « ia » (София -> Sofia).
        $texte = preg_replace_callback('/(и)(я)(?=\P{L}|$)/iu', fn ($m) => (mb_strtoupper($m[1]) === $m[1] ? 'I' : 'i').(mb_strtoupper($m[2]) === $m[2] ? 'A' : 'a'), $texte);

        // Grec : accents retires, puis digrammes (ου -> ou, αυ -> av...).
        $texte = self::sansAccentsGrecs((string) $texte);
        $texte = preg_replace_callback('/(ου|ΟΥ|Ου|αυ|ΑΥ|Αυ|ευ|ΕΥ|Ευ|μπ|ΜΠ|Μπ|ντ|ΝΤ|Ντ|γκ|ΓΚ|Γκ|γγ|ΓΓ)/u', fn ($m) => [
            'γγ' => 'ng', 'ΓΓ' => 'NG',
            'ου' => 'ou', 'ΟΥ' => 'OU', 'Ου' => 'Ou', 'αυ' => 'av', 'ΑΥ' => 'AV', 'Αυ' => 'Av',
            'ευ' => 'ev', 'ΕΥ' => 'EV', 'Ευ' => 'Ev', 'μπ' => 'b', 'ΜΠ' => 'B', 'Μπ' => 'B',
            'ντ' => 'nt', 'ΝΤ' => 'NT', 'Ντ' => 'Nt', 'γκ' => 'gk', 'ΓΚ' => 'GK', 'Γκ' => 'Gk',
        ][$m[1]], $texte);

        // Mot par mot : un mot tout en capitales reste en capitales
        // (« КУКУШ » -> « KUKUSH », « ЕАД » -> « EAD »), sinon seule
        // l'initiale (« Жар » -> « Zhar »).
        return preg_replace_callback('/\p{L}+/u', function (array $m) {
            $mot = $m[0];
            $capitales = mb_strlen($mot) > 1 && mb_strtoupper($mot) === $mot;
            $resultat = '';

            foreach (mb_str_split($mot) as $lettre) {
                $majuscule = mb_strtoupper($lettre);
                $latin = self::BULGARE[$majuscule] ?? self::GREC[$majuscule] ?? ($lettre === 'ς' ? 'S' : null);
                $resultat .= $latin === null ? $lettre : ($majuscule === $lettre ? $latin : mb_strtolower($latin));
            }

            return $capitales ? mb_strtoupper($resultat) : $resultat;
        }, (string) $texte);
    }

    /** « Nom latin (nom original) », ou le nom tel quel s'il est deja latin. */
    public static function avecOriginal(?string $texte): string
    {
        return self::aTranslitterer($texte) ? self::latin($texte).' ('.$texte.')' : (string) $texte;
    }

    private static function sansAccentsGrecs(string $texte): string
    {
        return strtr($texte, [
            'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω', 'ϊ' => 'ι', 'ϋ' => 'υ', 'ΐ' => 'ι', 'ΰ' => 'υ',
            'Ά' => 'Α', 'Έ' => 'Ε', 'Ή' => 'Η', 'Ί' => 'Ι', 'Ό' => 'Ο', 'Ύ' => 'Υ', 'Ώ' => 'Ω',
        ]);
    }
}
