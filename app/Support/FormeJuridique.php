<?php

namespace App\Support;

/**
 * La forme juridique lue dans la raison sociale, quand le registre ne la
 * donne pas : « Spotify AB », « Škoda Auto a.s. », « ORLEN SPÓŁKA
 * AKCYJNA », « SIA Piemērs ». Une indication a verifier, pas une donnee
 * du registre.
 */
final class FormeJuridique
{
    /** Motif en fin de nom => forme ecrite. Les plus longs d'abord. */
    private const SUFFIXES = [
        'GMBH\s*&\s*CO\.?\s*KG' => 'GmbH & Co. KG',
        'SP\.?\s*Z\s*O\.?\s*O\.?|SPÓŁKA\s+Z\s+OGRANICZONĄ\s+ODPOWIEDZIALNOŚCIĄ' => 'Sp. z o.o.',
        'SPÓŁKA\s+AKCYJNA' => 'S.A.',
        'SPOL\.?\s*S\s*R\.?\s*O\.?|S\.\s?R\.\s?O\.|SRO' => 's.r.o.',
        'S\.?\s?P\.?\s?A\.?' => 'S.p.A.',
        'S\.?\s?R\.?\s?L\.?' => 'SRL',
        'SASU' => 'SASU', 'S\.?A\.?S\.?' => 'SAS', 'SARL' => 'SARL', 'EURL' => 'EURL', 'SNC' => 'SNC',
        'S\.?\s?L\.?\s?U\.?' => 'S.L.U.', 'S\.?\s?L\.?' => 'S.L.', 'S\.?\s?A\.?\s?U\.?' => 'S.A.U.',
        'LDA\.?' => 'Lda.', 'UNIPESSOAL' => 'Unipessoal Lda.',
        'GMBH' => 'GmbH', 'AG' => 'AG', 'KG' => 'KG', 'OHG' => 'OHG', 'SE' => 'SE',
        'N\.?\s?V\.?' => 'NV', 'B\.?\s?V\.?' => 'BV', 'BVBA' => 'BVBA', 'CVBA' => 'CVBA', 'VZW' => 'VZW', 'ASBL' => 'ASBL',
        'A\/S' => 'A/S', 'APS' => 'ApS', 'I\/S' => 'I/S',
        'OYJ' => 'Oyj', 'OY' => 'Oy', 'ASA' => 'ASA', 'AS' => 'AS', 'AB' => 'AB', 'HB' => 'HB', 'OÜ' => 'OÜ',
        'A\.\s?S\.' => 'a.s.', 'K\.\s?S\.' => 'k.s.', 'V\.\s?O\.\s?S\.' => 'v.o.s.',
        'D\.\s?O\.\s?O\.' => 'd.o.o.', 'D\.\s?D\.' => 'd.d.',
        'KFT\.?' => 'Kft.', 'ZRT\.?' => 'Zrt.', 'NYRT\.?' => 'Nyrt.', 'BT\.?' => 'Bt.',
        'EOOD' => 'EOOD', 'OOD' => 'OOD', 'EAD' => 'EAD', 'AD' => 'AD',
        'A\.?\s?E\.?' => 'A.E.', 'E\.?\s?P\.?\s?E\.?' => 'E.P.E.', 'I\.?\s?K\.?\s?E\.?' => 'I.K.E.',
        'DAC' => 'DAC', 'PLC' => 'PLC', 'LIMITED|LTD\.?' => 'Ltd',
        'S\.?\s?A\.?' => 'SA',
    ];

    /** Formes ecrites devant le nom (Pays baltes). */
    private const PREFIXES = ['SIA' => 'SIA', 'UAB' => 'UAB', 'AB' => 'AB', 'AS' => 'AS', 'OÜ' => 'OÜ'];

    public static function depuisNom(?string $nom): ?string
    {
        // « Vivakom Balgaria - EAD (Виваком България - ЕАД) » : la forme se
        // lit sur la partie latine.
        $nom = trim(preg_replace('/\s*\([^()]*\)\s*$/u', '', (string) $nom));

        if ($nom === '') {
            return null;
        }

        foreach (self::SUFFIXES as $motif => $forme) {
            if (preg_match('/(?:^|[\s,\-])(?:'.$motif.')$/iu', $nom)) {
                return $forme;
            }
        }

        foreach (self::PREFIXES as $motif => $forme) {
            if (preg_match('/^'.$motif.'\s/u', $nom)) {
                return $forme;
            }
        }

        return null;
    }
}
