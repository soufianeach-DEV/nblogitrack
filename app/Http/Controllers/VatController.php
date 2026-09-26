<?php

namespace App\Http\Controllers;

use App\Support\IdentifiantEntreprise;
use App\Support\Traductions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VatController extends Controller
{
    private const DELAI_REGISTRE = 5;

    public function verifier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tva' => 'required|string|max:30',
        ]);

        $identifiant = IdentifiantEntreprise::analyser($data['tva']);

        if ($identifiant['tva'] === null) {
            return response()->json([
                'statut' => 'format',
                'message' => Traductions::t('msg.tva_format', 'Saisis un numéro de TVA (ex. BE0123456749) ou un SIREN/SIRET français.'),
            ]);
        }

        $tva = $identifiant['tva'];

        // Format ou cle de controle faux : le numero n'existe pas, inutile
        // d'interroger le registre.
        if (! IdentifiantEntreprise::controleLocal($tva)) {
            return response()->json([
                'statut' => 'format',
                'message' => $identifiant['pays'] === 'BE'
                    ? Traductions::t('msg.tva_belge_invalide', 'Ce numéro de TVA belge n\'est pas valide : vérifiez-le. Il compte 10 chiffres après BE et commence par 0 ou 1 (ex. BE0123456749).')
                    : Traductions::t('msg.tva_format_pays', 'Ce numéro ne respecte pas le format de son pays : vérifiez-le (ex. :exemple).', [
                        'exemple' => IdentifiantEntreprise::exemple($tva) ?? 'BE0123456749',
                    ]),
            ]);
        }

        $cle = 'vies:'.$tva;

        if ($cache = Cache::get($cle)) {
            return response()->json($this->traduire($cache));
        }

        // Suisse, Royaume-Uni et Norvege ne sont pas dans VIES : chacun
        // son registre.
        $resultat = match ($identifiant['pays']) {
            'CH' => $this->registreSuisse($identifiant),
            'NO' => $this->registreNorvegien($identifiant),
            'GB' => $this->registreBritannique($identifiant),
            default => $this->interrogerVies($tva, $identifiant),
        };

        if (in_array($resultat['statut'], ['valide', 'invalide'], true)) {
            Cache::put($cle, $resultat, now()->addDay());
        }

        return response()->json($this->traduire($resultat));
    }

    /**
     * Le resultat est mis en cache en francais : les textes affiches
     * sont traduits a la sortie, dans la langue de l'utilisateur.
     *
     * @param  array<string, mixed>  $resultat
     * @return array<string, mixed>
     */
    private function traduire(array $resultat): array
    {
        $message = match ($resultat['statut'] ?? null) {
            'invalide' => ($resultat['registre'] ?? 'VIES') === 'VIES'
                ? Traductions::t('msg.tva_inactive', 'Ce numéro n\'est pas actif dans le registre européen.')
                : Traductions::t('msg.tva_inactive_registre', 'Ce numéro n\'est pas actif dans le registre :registre.', ['registre' => $resultat['registre']]),
            'non_verifie' => Traductions::t('msg.tva_non_verifiee', 'Format valide. Le registre :registre n\'est pas interrogé : complétez les informations vous-même.', ['registre' => $resultat['registre'] ?? '']),
            'indisponible' => Traductions::t('msg.tva_registre_sature', 'Le registre européen est momentanément saturé. Réessaie dans un instant ou saisis les informations manuellement.'),
            default => null,
        };

        if ($message !== null) {
            $resultat['message'] = $message;
        }

        $libelle = $resultat['entreprise']['situation']['libelle'] ?? null;

        if ($libelle === 'Entreprise active') {
            $resultat['entreprise']['situation']['libelle'] = Traductions::t('msg.entreprise_active', 'Entreprise active');
        } elseif ($libelle === 'Entreprise cessée') {
            $resultat['entreprise']['situation']['libelle'] = Traductions::t('msg.entreprise_cessee', 'Entreprise cessée');
        }

        if (isset($resultat['entreprise']['secteur'])) {
            $resultat['entreprise']['secteur'] = Traductions::vocabulaire('secteur', $resultat['entreprise']['secteur']);
        }

        if (isset($resultat['entreprise']['dirigeant']['fonction'])) {
            $resultat['entreprise']['dirigeant']['fonction'] = Traductions::vocabulaire('fonction', $resultat['entreprise']['dirigeant']['fonction']);
        }

        return $resultat;
    }

    /**
     * @param  array{pays: ?string, tva: ?string, national: ?string, peppol: ?string}  $identifiant
     * @return array<string, mixed>
     */
    private function interrogerVies(string $tva, array $identifiant, int $tentative = 1): array
    {
        $pays = substr($tva, 0, 2);
        $numero = substr($tva, 2);

        try {
            $reponse = Http::timeout(self::DELAI_REGISTRE)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{$pays}/vat/{$numero}");

            if (! $reponse->ok()) {
                return $this->indisponible();
            }

            $corps = $reponse->json();

            if ($corps['isValid'] ?? false) {
                return [
                    'statut' => 'valide',
                    'registre' => 'VIES',
                    'nom' => $this->nettoyer($corps['name'] ?? ''),
                    'adresse' => [...$this->decomposerAdresse($corps['address'] ?? ''), 'pays' => $identifiant['pays']],
                    'tva' => $identifiant['tva'],
                    'peppol' => $identifiant['peppol'],
                    'entreprise' => match ($identifiant['pays']) {
                        'FR' => $this->registreFrancais($identifiant['national']),
                        'BE' => $this->registreBelge($identifiant['national']),
                        default => null,
                    },
                ];
            }

            $pannes = ['SERVICE_UNAVAILABLE', 'MS_UNAVAILABLE', 'MS_MAX_CONCURRENT_REQ',
                'GLOBAL_MAX_CONCURRENT_REQ', 'TIMEOUT', 'IP_BLOCKED', 'VAT_BLOCKED'];

            if (in_array($corps['userError'] ?? '', $pannes, true)) {
                if ($tentative < 2) {
                    usleep(400_000);

                    return $this->interrogerVies($tva, $identifiant, $tentative + 1);
                }

                return $this->indisponible();
            }

            return ['statut' => 'invalide', 'message' => 'Ce numéro n\'est pas actif dans le registre européen.'];
        } catch (\Throwable $e) {
            return $this->indisponible();
        }
    }

    /**
     * Registre suisse des entreprises (UID), service public sans cle.
     *
     * @param  array{pays: ?string, tva: ?string, national: ?string, peppol: ?string}  $identifiant
     * @return array<string, mixed>
     */
    private function registreSuisse(array $identifiant): array
    {
        $enveloppe = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:uid="http://www.uid.admin.ch/xmlns/uid-wse" xmlns:ns="http://www.ech.ch/xmlns/eCH-0097/5">'
            .'<soapenv:Body><uid:GetByUID><uid:uid><ns:uidOrganisationIdCategorie>CHE</ns:uidOrganisationIdCategorie>'
            .'<ns:uidOrganisationId>'.$identifiant['national'].'</ns:uidOrganisationId></uid:uid></uid:GetByUID></soapenv:Body></soapenv:Envelope>';

        try {
            $reponse = Http::timeout(self::DELAI_REGISTRE)
                ->withHeaders(['SOAPAction' => 'http://www.uid.admin.ch/xmlns/uid-wse/IPublicServices/GetByUID'])
                ->withBody($enveloppe, 'text/xml; charset=utf-8')
                ->post('https://www.uid-wse.admin.ch/V5.0/PublicServices.svc');

            if ($reponse->serverError() && ! str_contains($reponse->body(), 'Data_validation_failed')) {
                return $this->indisponible();
            }

            $xml = $reponse->body();
            $valeur = fn (string $balise) => preg_match('/<(?:\w+:)?'.$balise.'>([^<]*)</u', $xml, $m) ? $this->nettoyer(html_entity_decode($m[1])) : '';
            $nom = $valeur('organisationName');

            if ($nom === '') {
                return ['statut' => 'invalide', 'registre' => 'UID (Suisse)'];
            }

            return [
                'statut' => 'valide',
                'registre' => 'UID (Suisse)',
                'nom' => $nom,
                'adresse' => [
                    'rue' => trim($valeur('street').' '.$valeur('houseNumber')),
                    'code_postal' => $valeur('swissZipCode'),
                    'ville' => $valeur('town'),
                    'pays' => 'CH',
                ],
                'tva' => $identifiant['tva'],
                'peppol' => $identifiant['peppol'],
                'entreprise' => [
                    'dirigeant' => null,
                    'secteur' => null,
                    'forme_juridique' => $valeur('legalForm') ?: null,
                    // 2 : inscrite ; 3 : radiee (eCH-0097).
                    'situation' => $valeur('uidregStatusEnterpriseDetail') === '3'
                        ? ['libelle' => 'Entreprise cessée', 'acceptable' => false]
                        : ['libelle' => 'Entreprise active', 'acceptable' => true],
                ],
            ];
        } catch (\Throwable) {
            return $this->indisponible();
        }
    }

    /**
     * Registre norvegien des entites (Bronnoysund), service public sans cle.
     *
     * @param  array{pays: ?string, tva: ?string, national: ?string, peppol: ?string}  $identifiant
     * @return array<string, mixed>
     */
    private function registreNorvegien(array $identifiant): array
    {
        try {
            $reponse = Http::timeout(self::DELAI_REGISTRE)
                ->acceptJson()
                ->get('https://data.brreg.no/enhetsregisteret/api/enheter/'.$identifiant['national']);

            if ($reponse->status() === 404 || $reponse->status() === 410) {
                return ['statut' => 'invalide', 'registre' => 'Brønnøysund (Norvège)'];
            }

            if (! $reponse->ok()) {
                return $this->indisponible();
            }

            $e = $reponse->json();

            // Hors du registre de la TVA, le numero n'est pas un numero de TVA.
            if (! ($e['registrertIMvaregisteret'] ?? false)) {
                return ['statut' => 'invalide', 'registre' => 'Brønnøysund (Norvège)'];
            }

            $cessee = ($e['konkurs'] ?? false) || ($e['underAvvikling'] ?? false) || ($e['underTvangsavviklingEllerTvangsopplosning'] ?? false);

            return [
                'statut' => 'valide',
                'registre' => 'Brønnøysund (Norvège)',
                'nom' => $this->nettoyer($e['navn'] ?? ''),
                'adresse' => [
                    'rue' => $this->nettoyer(implode(', ', $e['forretningsadresse']['adresse'] ?? [])),
                    'code_postal' => (string) ($e['forretningsadresse']['postnummer'] ?? ''),
                    'ville' => $this->casseNom((string) ($e['forretningsadresse']['poststed'] ?? '')),
                    'pays' => 'NO',
                ],
                'tva' => $identifiant['tva'],
                'peppol' => $identifiant['peppol'],
                'entreprise' => [
                    'dirigeant' => null,
                    'secteur' => $this->secteurDepuisNace($e['naeringskode1']['kode'] ?? null),
                    'forme_juridique' => $e['organisasjonsform']['kode'] ?? null,
                    'situation' => $cessee
                        ? ['libelle' => 'Entreprise cessée', 'acceptable' => false]
                        : ['libelle' => 'Entreprise active', 'acceptable' => true],
                ],
            ];
        } catch (\Throwable) {
            return $this->indisponible();
        }
    }

    /**
     * Registre britannique de la TVA (HMRC). Il exige une application
     * declaree : sans identifiants, seul le format est controle.
     *
     * @param  array{pays: ?string, tva: ?string, national: ?string, peppol: ?string}  $identifiant
     * @return array<string, mixed>
     */
    private function registreBritannique(array $identifiant): array
    {
        $client = config('services.hmrc.client_id');
        $secret = config('services.hmrc.client_secret');

        if (! $client || ! $secret) {
            return ['statut' => 'non_verifie', 'registre' => 'HMRC (Royaume-Uni)', 'tva' => $identifiant['tva'], 'peppol' => $identifiant['peppol']];
        }

        $base = rtrim((string) config('services.hmrc.base', 'https://api.service.hmrc.gov.uk'), '/');

        try {
            $jeton = Cache::remember('hmrc:jeton', now()->addMinutes(200), fn () => Http::asForm()
                ->timeout(self::DELAI_REGISTRE)
                ->post($base.'/oauth/token', ['client_id' => $client, 'client_secret' => $secret, 'grant_type' => 'client_credentials'])
                ->throw()
                ->json('access_token'));

            $reponse = Http::timeout(self::DELAI_REGISTRE)
                ->withToken($jeton)
                ->accept('application/vnd.hmrc.2.0+json')
                ->get($base.'/organisations/vat/check-vat-number/lookup/'.substr((string) $identifiant['tva'], 2));

            if ($reponse->status() === 404) {
                return ['statut' => 'invalide', 'registre' => 'HMRC (Royaume-Uni)'];
            }

            if (! $reponse->ok()) {
                return $this->indisponible();
            }

            $adresse = $reponse->json('target.address', []);

            return [
                'statut' => 'valide',
                'registre' => 'HMRC (Royaume-Uni)',
                'nom' => $this->nettoyer((string) $reponse->json('target.name', '')),
                'adresse' => [
                    'rue' => $this->nettoyer(implode(', ', array_filter([$adresse['line1'] ?? null, $adresse['line2'] ?? null]))),
                    'code_postal' => (string) ($adresse['postcode'] ?? ''),
                    'ville' => $this->nettoyer((string) ($adresse['line3'] ?? $adresse['line4'] ?? '')),
                    'pays' => 'GB',
                ],
                'tva' => $identifiant['tva'],
                'peppol' => $identifiant['peppol'],
                'entreprise' => null,
            ];
        } catch (\Throwable) {
            Cache::forget('hmrc:jeton');

            return $this->indisponible();
        }
    }

    /** Categories juridiques INSEE les plus courantes. */
    private const FORMES_FRANCAISES = [
        '1000' => 'Entrepreneur individuel', '5410' => 'SARL', '5498' => 'EURL', '5499' => 'SARL',
        '5599' => 'SA', '5710' => 'SAS', '5720' => 'SASU', '6540' => 'SCI', '9220' => 'Association',
    ];

    private const SECTEURS_NACE = [
        '01' => 'Agriculture', '02' => 'Agriculture', '03' => 'Agriculture',
        '10' => 'Agroalimentaire', '11' => 'Agroalimentaire', '12' => 'Agroalimentaire',
        '13' => 'Textile', '14' => 'Textile', '15' => 'Textile',
        '16' => 'Bois et papier', '17' => 'Bois et papier', '18' => 'Bois et papier',
        '19' => 'Énergie', '20' => 'Chimie', '21' => 'Pharmaceutique', '22' => 'Plasturgie',
        '23' => 'Matériaux de construction', '24' => 'Métallurgie', '25' => 'Métallurgie',
        '26' => 'Électronique', '27' => 'Électronique', '28' => 'Machines et équipements',
        '29' => 'Automobile', '30' => 'Automobile', '31' => 'Mobilier', '32' => 'Cosmétique',
        '35' => 'Énergie', '36' => 'Énergie', '37' => 'Recyclage', '38' => 'Recyclage', '39' => 'Recyclage',
        '41' => 'Construction', '42' => 'Construction', '43' => 'Construction',
        '45' => 'Automobile', '46' => 'Distribution', '47' => 'Grande distribution',
        '49' => 'Transport', '50' => 'Transport', '51' => 'Transport',
        '52' => 'Logistique', '53' => 'Logistique',
        '62' => 'Électronique', '63' => 'Électronique',
        '86' => 'Santé', '87' => 'Santé', '88' => 'Santé',
    ];

    /**
     * @return array{dirigeant: ?array{prenom: string, nom: string, fonction: string}, secteur: ?string}|null
     */
    private function registreFrancais(?string $identifiantNational): ?array
    {
        if ($identifiantNational === null) {
            return null;
        }

        $siren = substr(preg_replace('/\D/', '', $identifiantNational), 0, 9);

        if (strlen($siren) !== 9) {
            return null;
        }

        try {
            $reponse = Http::timeout(self::DELAI_REGISTRE)
                ->get('https://recherche-entreprises.api.gouv.fr/search', ['q' => $siren, 'per_page' => 1]);

            if (! $reponse->ok()) {
                return null;
            }

            $etat = $reponse->json('results.0.etat_administratif');

            return [
                'dirigeant' => $this->premierDirigeant($reponse->json('results.0.dirigeants', [])),
                'secteur' => $this->secteurDepuisNace($reponse->json('results.0.activite_principale')),
                'forme_juridique' => self::FORMES_FRANCAISES[(string) $reponse->json('results.0.nature_juridique')] ?? null,
                'situation' => $etat === null ? null : [
                    'libelle' => $etat === 'A' ? 'Entreprise active' : 'Entreprise cessée',
                    'acceptable' => $etat === 'A',
                ],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $dirigeants
     * @return array{prenom: string, nom: string, fonction: string}|null
     */
    private function premierDirigeant(array $dirigeants): ?array
    {
        foreach ($dirigeants as $dirigeant) {
            if (($dirigeant['type_dirigeant'] ?? '') !== 'personne physique') {
                continue;
            }

            $prenom = $this->casseNom($dirigeant['prenoms'] ?? '');
            $nom = $this->casseNom($dirigeant['nom'] ?? '');

            if ($prenom === '' && $nom === '') {
                continue;
            }

            return [
                'prenom' => $prenom,
                'nom' => $nom,
                'fonction' => $this->nettoyer($dirigeant['qualite'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * @return array{dirigeant: ?array{prenom: string, nom: string, fonction: string}, secteur: ?string}|null
     */
    private function registreBelge(?string $numeroEntreprise): ?array
    {
        if ($numeroEntreprise === null) {
            return null;
        }

        $numero = preg_replace('/\D/', '', $numeroEntreprise);

        if (strlen($numero) !== 10) {
            return null;
        }

        try {
            $reponse = Http::timeout(self::DELAI_REGISTRE)
                ->withHeaders(['User-Agent' => 'NBLogiTrack/1.0 (epreuve integree)'])
                ->get('https://kbopub.economie.fgov.be/kbopub/toonondernemingps.html', [
                    'lang' => 'fr',
                    'ondernemingsnummer' => $numero,
                ]);

            if (! $reponse->ok()) {
                return null;
            }

            $texte = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($reponse->body())));

            preg_match_all('/(\d{2})\.\d{2,3}\s*-\s*/u', $texte, $codes);

            return [
                'dirigeant' => $this->dirigeantBelge($texte),
                'secteur' => $this->premierSecteurConnu($codes[1] ?? []),
                'situation' => $this->situationBelge($texte),
                'forme_juridique' => preg_match('/Forme l[ée]gale\s*:?\s*([\p{L}\'\- ]{2,80}?)\s*(?:Depuis|Type|Situation|$)/u', $texte, $f) ? $this->nettoyer($f[1]) : null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private const SITUATIONS_BLOQUANTES = [
        'faillite', 'liquidation', 'dissolution', 'clôture', 'cloture',
        'cessation', 'cessé', 'cesse', 'réorganisation judiciaire', 'reorganisation judiciaire',
        'radiation', 'fermeture', 'concordat', 'sursis', 'fusion', 'scission',
        'absorption', 'transfert', 'arrêt', 'arret',
    ];

    /**
     * @return array{libelle: string, acceptable: bool}|null
     */
    private function situationBelge(string $texte): ?array
    {
        if (! preg_match('/Situation juridique\s*:?\s*([\p{L}\'\- ]{4,60}?)\s*(?:Depuis|Début|$)/u', $texte, $m)) {
            return null;
        }

        $libelle = $this->nettoyer($m[1]);

        return [
            'libelle' => $libelle,
            'acceptable' => ! $this->situationBloquante($libelle),
        ];
    }

    private function situationBloquante(string $libelle): bool
    {
        $normalise = mb_strtolower($libelle);

        foreach (self::SITUATIONS_BLOQUANTES as $motif) {
            if (str_contains($normalise, $motif)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{prenom: string, nom: string, fonction: string}|null
     */
    private function dirigeantBelge(string $texte): ?array
    {
        $fonctions = 'Administrateur délégué|Administrateur|Gérant|Représentant permanent|Président|Liquidateur';

        if (! preg_match('/('.$fonctions.')\s+([\p{L}\'\- ]{2,40}?)\s*,\s*([\p{L}\'\- ]{2,40}?)\s+(?:Depuis|Fonction|$)/u', $texte, $m)) {
            return null;
        }

        return [
            'prenom' => $this->casseNom($m[3]),
            'nom' => $this->casseNom($m[2]),
            'fonction' => $this->nettoyer($m[1]),
        ];
    }

    private function secteurDepuisNace(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return self::SECTEURS_NACE[substr(preg_replace('/\D/', '', $code), 0, 2)] ?? null;
    }

    /**
     * @param  array<int, string>  $divisions
     */
    private function premierSecteurConnu(array $divisions): ?string
    {
        foreach ($divisions as $division) {
            if (isset(self::SECTEURS_NACE[$division])) {
                return self::SECTEURS_NACE[$division];
            }
        }

        return null;
    }

    private function casseNom(string $valeur): string
    {
        $valeur = $this->nettoyer($valeur);

        return $valeur === '' ? '' : mb_convert_case($valeur, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @return array{statut: string, message: string}
     */
    private function indisponible(): array
    {
        return [
            'statut' => 'indisponible',
            'message' => 'Le registre européen est momentanément saturé. Réessaie dans un instant ou saisis les informations manuellement.',
        ];
    }

    private function nettoyer(string $valeur): string
    {
        $valeur = trim(preg_replace('/\s+/', ' ', $valeur));

        return $valeur === '---' ? '' : $valeur;
    }

    /**
     * @return array{rue: string, code_postal: string, ville: string}
     */
    private function decomposerAdresse(string $brut): array
    {
        $lignes = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $brut))));

        if ($lignes === [] || $lignes === ['---']) {
            return ['rue' => '', 'code_postal' => '', 'ville' => ''];
        }

        // Adresse sur une seule ligne (Bulgarie, Roumanie...) : la localite
        // est le dernier segment apres une virgule. « ул. КУКУШ №1
        // обл.СОФИЯ, гр.СОФИЯ 1309 » : rue, puis ville et code postal.
        if (count($lignes) === 1 && str_contains($lignes[0], ',')) {
            $segments = array_map('trim', explode(',', $lignes[0]));
            $lignes = [implode(', ', array_slice($segments, 0, -1)), end($segments)];
        }

        $derniere = array_pop($lignes);
        $codePostal = '';
        $ville = $derniere;

        if (preg_match('/^([A-Z]{0,2}[\s-]?\d{4,6}(?:\s?[A-Z]{2})?)\s+(.+)$/u', $derniere, $m)) {
            $codePostal = trim($m[1]);
            $ville = trim($m[2]);
        } elseif (preg_match('/^(.+?)\s+([A-Z]{0,2}[\s-]?\d{4,6}(?:\s?[A-Z]{2})?)$/u', $derniere, $m)) {
            $ville = trim($m[1]);
            $codePostal = trim($m[2]);
        }

        // Abreviations bulgares : « гр. » (ville) devant la localite,
        // « обл. » (region) qui n'a rien a faire dans la rue.
        $ville = preg_replace('/^(?:гр\.|с\.)\s*/u', '', $ville);
        $rue = preg_replace('/\s*,?\s*обл\.\s*\S+$/u', '', implode(', ', $lignes));

        return [
            'rue' => $this->nettoyer($rue),
            'code_postal' => $this->nettoyer($codePostal),
            'ville' => $this->nettoyer($ville),
        ];
    }
}
