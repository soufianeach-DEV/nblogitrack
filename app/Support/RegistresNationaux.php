<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Registres nationaux publics et sans cle qui completent VIES : forme
 * juridique et activite principale (code NACE), que le registre europeen
 * ne donne pas. Tcheque (ARES), Finlande (PRH), Pologne (liste blanche du
 * fisc puis KRS), Roumanie (ANAF).
 *
 * Chaque registre renvoie null s'il ne repond pas : la verification TVA
 * reste valable, seuls ces complements manquent.
 */
final class RegistresNationaux
{
    private const DELAI = 6;

    /** Formes juridiques tcheques (ciselnik ARES), les plus courantes. */
    private const FORMES_TCHEQUES = [
        '101' => 'Entrepreneur individuel', '111' => 'v.o.s.', '112' => 's.r.o.', '113' => 'k.s.',
        '121' => 'a.s.', '205' => 'Družstvo', '301' => 'Entreprise d\'État', '706' => 'Association',
    ];

    /** Formes polonaises (KRS) et roumaines (ANAF), en toutes lettres. */
    private const FORMES_ECRITES = [
        'SPÓŁKA AKCYJNA' => 'S.A.',
        'SPÓŁKA Z OGRANICZONĄ ODPOWIEDZIALNOŚCIĄ' => 'Sp. z o.o.',
        'SPÓŁKA KOMANDYTOWA' => 'Sp.k.',
        'SPÓŁKA JAWNA' => 'Sp.j.',
        'SPÓŁKA KOMANDYTOWO-AKCYJNA' => 'S.K.A.',
        'SOCIETATE PE ACŢIUNI' => 'SA', 'SOCIETATE PE ACȚIUNI' => 'SA',
        'SOCIETATE COMERCIALĂ PE ACŢIUNI' => 'SA', 'SOCIETATE COMERCIALĂ PE ACȚIUNI' => 'SA',
        'SOCIETATE CU RĂSPUNDERE LIMITATĂ' => 'SRL',
        'SOCIETATE COMERCIALĂ CU RĂSPUNDERE LIMITATĂ' => 'SRL',
    ];

    /**
     * @return array{forme_juridique: ?string, nace: ?string, adresse?: array{rue: string, code_postal: string, ville: string}}|null
     */
    public static function completer(string $pays, string $tva): ?array
    {
        $numero = substr($tva, 2);

        try {
            return match ($pays) {
                'CZ' => self::tcheque($numero),
                'FI' => self::finlandais($numero),
                'PL' => self::polonais($numero),
                'RO' => self::roumain($numero),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** ARES, registre economique tcheque. L'ICO est le numero de TVA d'une societe. */
    private static function tcheque(string $ico): ?array
    {
        if (! preg_match('/^\d{8}$/', $ico)) {
            return null;
        }

        $reponse = Http::timeout(self::DELAI)->acceptJson()
            ->get("https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{$ico}");

        if (! $reponse->ok()) {
            return null;
        }

        $forme = (string) $reponse->json('pravniForma');
        $nace = $reponse->json('czNacePrevazujici') ?? ($reponse->json('czNace') ?? [])[0] ?? null;

        return [
            'forme_juridique' => self::FORMES_TCHEQUES[$forme] ?? null,
            'nace' => $nace === null ? null : (string) $nace,
        ];
    }

    /** PRH / YTJ, registre finlandais : 01120389 devient 0112038-9. */
    private static function finlandais(string $numero): ?array
    {
        if (! preg_match('/^\d{8}$/', $numero)) {
            return null;
        }

        $reponse = Http::timeout(self::DELAI)->acceptJson()
            ->get('https://avoindata.prh.fi/opendata-ytj-api/v3/companies', ['businessId' => substr($numero, 0, 7).'-'.substr($numero, 7)]);

        if (! $reponse->ok() || $reponse->json('companies.0') === null) {
            return null;
        }

        $formes = collect($reponse->json('companies.0.companyForms', []))->filter(fn ($f) => empty($f['endDate']));
        $description = collect($formes->first()['descriptions'] ?? [])->firstWhere('languageCode', '1')['description'] ?? '';

        return [
            'forme_juridique' => match (mb_strtolower(trim($description))) {
                'julkinen osakeyhtiö' => 'Oyj',
                'osakeyhtiö' => 'Oy',
                'osuuskunta' => 'Osuuskunta',
                'avoin yhtiö' => 'Ay',
                'kommandiittiyhtiö' => 'Ky',
                '' => null,
                default => $description,
            },
            'nace' => $reponse->json('companies.0.mainBusinessLine.type'),
        ];
    }

    /** Liste blanche du fisc polonais (numero KRS), puis registre KRS. */
    private static function polonais(string $nip): ?array
    {
        if (! preg_match('/^\d{10}$/', $nip)) {
            return null;
        }

        $fisc = Http::timeout(self::DELAI)->acceptJson()
            ->get("https://wl-api.mf.gov.pl/api/search/nip/{$nip}", ['date' => now()->toDateString()]);

        $krs = $fisc->ok() ? $fisc->json('result.subject.krs') : null;

        // Le numero KRS vient d'une reponse externe : dix chiffres, rien
        // d'autre, avant de le placer dans une adresse.
        if (! is_string($krs) || ! preg_match('/^\d{10}$/', $krs)) {
            return null;
        }

        $odpis = Http::timeout(self::DELAI)->acceptJson()
            ->get("https://api-krs.ms.gov.pl/api/krs/OdpisAktualny/{$krs}", ['rejestr' => 'P', 'format' => 'json']);

        if (! $odpis->ok()) {
            return null;
        }

        $forme = mb_strtoupper(trim((string) $odpis->json('odpis.dane.dzial1.danePodmiotu.formaPrawna')));
        $activite = $odpis->json('odpis.dane.dzial3.przedmiotDzialalnosci.przedmiotPrzewazajacejDzialalnosci.0');

        return [
            'forme_juridique' => self::FORMES_ECRITES[$forme] ?? ($forme !== '' ? $forme : null),
            'nace' => isset($activite['kodDzial']) ? $activite['kodDzial'].($activite['kodKlasa'] ?? '') : null,
        ];
    }

    /** ANAF, fisc roumain : l'adresse du siege y est deja decoupee. */
    private static function roumain(string $cui): ?array
    {
        if (! preg_match('/^\d{2,10}$/', $cui)) {
            return null;
        }

        $reponse = Http::timeout(self::DELAI)->acceptJson()
            ->post('https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva', [['cui' => (int) $cui, 'data' => now()->toDateString()]]);

        $general = $reponse->ok() ? $reponse->json('found.0.date_generale') : null;

        if (! is_array($general)) {
            return null;
        }

        $forme = mb_strtoupper(trim((string) ($general['forma_juridica'] ?? '')));
        $siege = $reponse->json('found.0.adresa_sediu_social') ?? [];
        $rue = trim(implode(' ', array_filter([$siege['sdenumire_Strada'] ?? '', isset($siege['snumar_Strada']) && $siege['snumar_Strada'] !== '' ? 'Nr. '.$siege['snumar_Strada'] : ''])));
        $ville = trim((string) ($siege['sdenumire_Localitate'] ?? ''));

        return [
            'forme_juridique' => self::FORMES_ECRITES[$forme] ?? ($forme !== '' ? $forme : null),
            'nace' => ($general['cod_CAEN'] ?? '') !== '' ? (string) $general['cod_CAEN'] : null,
            ...($rue !== '' && $ville !== '' ? ['adresse' => [
                'rue' => $rue,
                'code_postal' => trim((string) ($siege['scod_Postal'] ?? '')),
                'ville' => preg_replace('/^(?:MUN\.|MUNICIPIUL|ORŞ\.|ORAŞ|LOC\.|COM\.)\s*/iu', '', $ville),
            ]] : []),
        ];
    }
}
