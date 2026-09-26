<?php

namespace App\Http\Controllers;

use App\Support\FormeJuridique;
use App\Support\IdentifiantEntreprise;
use App\Support\RegistresNationaux;
use App\Support\Secteurs;
use App\Support\Traductions;
use App\Support\Translitteration;
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

        // Le secteur reste en francais : c'est la valeur de la liste, que
        // le formulaire affiche dans la langue de l'interface.

        if (isset($resultat['entreprise']['dirigeant']['fonction'])) {
            $resultat['entreprise']['dirigeant']['fonction'] = Traductions::vocabulaire('fonction', $resultat['entreprise']['dirigeant']['fonction']);
        }

        if (($resultat['statut'] ?? null) === 'valide') {
            $resultat['entreprise'] ??= ['dirigeant' => null, 'secteur' => null];

            // Forme absente du registre : lue dans la raison sociale.
            if (empty($resultat['entreprise']['forme_juridique']) && ($forme = FormeJuridique::depuisNom($resultat['nom'] ?? null))) {
                $resultat['entreprise']['forme_juridique'] = $forme;
                $resultat['entreprise']['forme_deduite'] = true;
            }

            // Ce que le registre de ce pays ne publie pas : a completer.
            $resultat['non_publie'] = array_keys(array_filter([
                'nom' => ($resultat['nom'] ?? '') === '',
                'adresse' => ($resultat['adresse']['rue'] ?? '') === '' && ($resultat['adresse']['ville'] ?? '') === '',
                'forme_juridique' => empty($resultat['entreprise']['forme_juridique']),
                'secteur' => empty($resultat['entreprise']['secteur']),
            ]));
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
                // Tchequie, Finlande, Pologne, Roumanie : forme juridique et
                // activite depuis le registre national ; l'ANAF roumaine
                // donne aussi l'adresse deja decoupee.
                $complement = RegistresNationaux::completer($identifiant['pays'], $tva);
                $adresse = [...array_map(fn (string $v) => Translitteration::latin($v), $this->decomposerAdresse($corps['address'] ?? '', $identifiant['pays'])), 'pays' => $identifiant['pays']];

                if (isset($complement['adresse'])) {
                    $adresse = [...$complement['adresse'], 'pays' => $identifiant['pays']];
                }

                return [
                    'statut' => 'valide',
                    'registre' => 'VIES',
                    // Cyrillique (Bulgarie) ou grec (Grece) : en lettres latines,
                    // la raison sociale garde l'original entre parentheses.
                    'nom' => Translitteration::avecOriginal($this->nettoyer($corps['name'] ?? '')),
                    'adresse' => $adresse,
                    'tva' => $identifiant['tva'],
                    'peppol' => $identifiant['peppol'],
                    'entreprise' => match ($identifiant['pays']) {
                        'FR' => $this->registreFrancais($identifiant['national']),
                        'BE' => $this->registreBelge($identifiant['national']),
                        default => $complement === null ? null : [
                            'dirigeant' => null,
                            'secteur' => $this->secteurDepuisNace($complement['nace']),
                            'forme_juridique' => $complement['forme_juridique'],
                        ],
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

            // Seul un numero inconnu (reponse sans organisation, ou
            // Data_validation_failed) est refuse ; toute autre erreur du
            // service (quota, maintenance) n'accuse pas le numero.
            if ($reponse->failed() && ! str_contains($reponse->body(), 'Data_validation_failed')) {
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
                    'forme_juridique' => self::FORMES_SUISSES[$valeur('legalForm')] ?? ($valeur('legalForm') ?: null),
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

    /** Formes juridiques du registre UID (eCH-0097), en francais. */
    private const FORMES_SUISSES = [
        '0101' => 'Entreprise individuelle', '0103' => 'Société en nom collectif', '0104' => 'Société en commandite',
        '0106' => 'SA', '0107' => 'Sàrl', '0108' => 'Société coopérative', '0109' => 'Association', '0110' => 'Fondation',
        '0111' => 'Succursale étrangère', '0151' => 'Succursale suisse',
    ];

    /** Categories juridiques INSEE les plus courantes. */
    private const FORMES_FRANCAISES = [
        '1000' => 'Entrepreneur individuel', '5410' => 'SARL', '5498' => 'EURL', '5499' => 'SARL',
        '5599' => 'SA', '5710' => 'SAS', '5720' => 'SASU', '6540' => 'SCI', '9220' => 'Association',
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
        return Secteurs::depuisNace($code);
    }

    /**
     * @param  array<int, string>  $divisions
     */
    private function premierSecteurConnu(array $divisions): ?string
    {
        // La BCE liste d'abord l'activite principale.
        return Secteurs::depuisNace($divisions[0] ?? null);
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
     * Codes postaux europeens tels que VIES les renvoie : 1000, 75002,
     * 1012 LG, 110 00, 00-950, 1100-048, L-1234, LV-1050, VLT 1234 (Malte),
     * D02 X285 (Irlande).
     */
    private const CODE_POSTAL = '(?:[A-Z]{1,2}-)?(?:\d{4}\s?[A-Z]{2}(?![A-Z])|\d{3}\s\d{2}(?!\d)|\d{2}-\d{3}(?!\d)|\d{4}-\d{3}(?!\d)|\d{4,6}(?!\d)|[A-Z]{3}\s?\d{4}|[A-Z]\d{2}\s[A-Z0-9]{4})';

    /**
     * L'adresse de VIES, dans la forme de chaque pays : sur plusieurs
     * lignes ou une seule, code postal avant ou apres la localite. La ligne
     * qui porte le code postal donne la localite ; la premiere des autres
     * lignes est la rue.
     *
     * @return array{rue: string, code_postal: string, ville: string}
     */
    /** « 3RD FLOOR », « 2ÈME ÉTAGE », « UNIT 4 », « SUITE 200 » : pas une rue. */
    private const ETAGE = '/^(?:(?:\d+\s*(?:ST|ND|RD|TH|E|ÈME|EME|ER)?|GROUND|FIRST|SECOND|THIRD|FOURTH|FIFTH|TOP)\s+(?:FLOOR|ÉTAGE|ETAGE)|(?:FLOOR|UNIT|SUITE|FLAT|APT\.?|APARTMENT)\s+\S+)$/iu';

    /** Type de voie d'une adresse anglaise. */
    private const VOIE_ANGLAISE = '/\b(?:STREET|ST\.?|ROAD|RD\.?|AVENUE|AVE\.?|LANE|QUAY|PLACE|SQUARE|DRIVE|TERRACE|CRESCENT|PARADE|ROW|WAY|WALK|HILL|GREEN|MALL|BOULEVARD)$/iu';

    private function decomposerAdresse(string $brut, string $pays = ''): array
    {
        $lignes = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $brut)), fn ($l) => $l !== '' && $l !== '---'));

        if ($lignes === []) {
            return ['rue' => '', 'code_postal' => '', 'ville' => ''];
        }

        // Une seule ligne : les virgules separent rue, quartier et localite.
        if (count($lignes) === 1 && str_contains($lignes[0], ',')) {
            $lignes = array_values(array_filter(array_map('trim', explode(',', $lignes[0]))));
        }

        // Roumanie : « LOC. MIOVENI - ORŞ. MIOVENI 115400 STR. UZINEI Nr. 1 »,
        // localite, code postal puis rue sur une seule ligne.
        if ($pays === 'RO') {
            foreach ($lignes as $i => $ligne) {
                if (preg_match('/^(.*\S)\s+(\d{6})\s+(\S.*)$/u', $ligne, $m)) {
                    $segments = preg_split('/\s+-\s+|,/u', $m[1]);
                    array_splice($lignes, $i, 1, [$m[3], $m[2].' '.trim((string) end($segments))]);
                    break;
                }
            }
        }

        $codePostal = '';
        $ville = '';
        $rang = null;

        foreach (array_reverse(array_keys($lignes)) as $i) {
            $ligne = $lignes[$i];

            // Code postal seul (Estonie, Lettonie) : la localite est le
            // segment precedent qui n'est ni un comte ni un quartier.
            if (preg_match('/^('.self::CODE_POSTAL.')$/u', $ligne)) {
                $codePostal = $ligne;

                for ($j = $i - 1; $j > 0; $j--) {
                    if (! preg_match('/maakond|linnaosa|vald|novads|apskritis|county/iu', $lignes[$j])) {
                        [$ville, $rang] = [$lignes[$j], $j];
                        break;
                    }
                }

                $rang ??= $i;
                break;
            }

            if (preg_match('/^('.self::CODE_POSTAL.')\s+(\D.*)$/u', $ligne, $m)
                || preg_match('/^(.*\D)\s+('.self::CODE_POSTAL.')$/u', $ligne, $n)) {
                [$codePostal, $ville] = isset($m[1]) ? [$m[1], $m[2]] : [$n[2], $n[1]];
                $rang = $i;
                break;
            }
        }

        // Sans code postal (Roumanie, Irlande sans Eircode) : la localite est
        // le « municipiul » roumain, sinon le dernier segment.
        if ($rang === null) {
            foreach ($lignes as $i => $ligne) {
                if (preg_match('/^(?:MUNICIPIUL|MUN\.|ORAŞ|ORAS|JUD\.)\s+(.+)$/iu', $ligne, $m)) {
                    [$ville, $rang] = [$m[1], $i];
                    break;
                }
            }

            if ($rang === null && count($lignes) > 1) {
                $rang = count($lignes) - 1;
                $ville = $lignes[$rang];
            }
        }

        // La rue : la premiere ligne qui n'est ni la localite, ni un quartier
        // repetant la localite, ni un secteur administratif, ni un etage.
        $rue = '';
        $restantes = [];

        foreach ($lignes as $i => $ligne) {
            if ($i === $rang || mb_strtolower($ligne) === mb_strtolower($ville) || $ligne === $codePostal
                || preg_match('/^(?:SECTOR|SECTORUL|обл\.)\s*\S+$/iu', $ligne)
                || preg_match('/maakond|linnaosa|vald|novads|apskritis/iu', $ligne)
                || preg_match(self::ETAGE, $ligne)) {
                continue;
            }

            $restantes[] = $ligne;
        }

        // Adresse anglaise (Irlande, Malte, Chypre) : « GORDON HOUSE,
        // BARROW STREET » garde le nom du batiment devant la rue.
        foreach (in_array($pays, ['IE', 'MT', 'CY', 'GB'], true) ? $restantes : [] as $k => $ligne) {
            if ($k > 0 && preg_match(self::VOIE_ANGLAISE, $ligne)) {
                $restantes = [implode(', ', array_slice($restantes, 0, $k + 1)), ...array_slice($restantes, $k + 1)];
                break;
            }
        }

        foreach ($restantes as $ligne) {

            $rue = $rue === '' ? $ligne : $rue;

            // Rue et numero sur deux segments (« STR. X », « NR. 1 »).
            if (preg_match('/^(?:NR\.?|NO\.?|N°)\s*\S+$/iu', $ligne) && $rue !== $ligne) {
                $rue .= ' '.$ligne;
            }
        }

        // « ORŞ. MIOVENI », « MUN. PITEŞTI » : le type de localite roumain.
        $ville = preg_replace('/^(?:LOC\.|LOCALITATEA|ORŞ\.|ORAŞ|ORAS|ORS\.|MUN\.|MUNICIPIUL|COM\.|COMUNA|SAT)\s*/iu', '', $ville);

        // « AT-5330 » : le prefixe du pays n'appartient au code postal
        // qu'au Luxembourg, en Lettonie et en Lituanie.
        if (! in_array($pays, ['LU', 'LV', 'LT'], true)) {
            $codePostal = preg_replace('/^[A-Z]{1,2}-(?=\d)/', '', $codePostal);
        }

        // « ROMA RM » : le sigle de la province italienne suit la localite.
        if ($pays === 'IT') {
            $ville = preg_replace('/\s+[A-Z]{2}$/u', '', $ville);
        }

        // « 10563 - ΑΘΗΝΑ » (Grece) : le tiret n'appartient pas a la localite.
        $ville = preg_replace('/^[\s\-–]+|[\s\-–]+$/u', '', $ville);

        // Abreviations bulgares : « гр. » (ville) ou « с. » (village) devant
        // la localite, « обл. » (region) en fin de rue.
        $ville = preg_replace('/^(?:гр\.|с\.)\s*/u', '', $ville);
        $rue = preg_replace('/\s*,?\s*обл\.\s*\S+$/u', '', $rue);

        return [
            'rue' => $this->nettoyer($rue),
            'code_postal' => $this->nettoyer($codePostal),
            'ville' => $this->nettoyer($ville),
        ];
    }
}
