<?php

namespace App\Http\Controllers;

use App\Support\IdentifiantEntreprise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VatController extends Controller
{
    /**
     * Vérifie un numéro de TVA auprès du service VIES de la Commission européenne
     * et retourne les données officielles de l'entreprise.
     */
    public function verifier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tva' => 'required|string|max:30',
        ]);

        $identifiant = IdentifiantEntreprise::analyser($data['tva']);

        if ($identifiant['tva'] === null) {
            return response()->json([
                'statut' => 'format',
                'message' => 'Saisis un numéro de TVA (ex. BE0123456789) ou un SIREN/SIRET français.',
            ]);
        }

        $tva = $identifiant['tva'];
        $cle = 'vies:'.$tva;

        if ($cache = Cache::get($cle)) {
            return response()->json($cache);
        }

        $resultat = $this->interrogerVies($tva, $identifiant);

        // Seules les réponses définitives sont mémorisées : une indisponibilité
        // temporaire du registre ne doit pas être figée pendant 24 heures.
        if (in_array($resultat['statut'], ['valide', 'invalide'], true)) {
            Cache::put($cle, $resultat, now()->addDay());
        }

        return response()->json($resultat);
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
            $reponse = Http::timeout(12)
                ->withHeaders(['Accept' => 'application/json'])
                ->get("https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{$pays}/vat/{$numero}");

            if (! $reponse->ok()) {
                return $this->indisponible();
            }

            $corps = $reponse->json();

            if ($corps['isValid'] ?? false) {
                return [
                    'statut' => 'valide',
                    'nom' => $this->nettoyer($corps['name'] ?? ''),
                    'adresse' => $this->decomposerAdresse($corps['address'] ?? ''),
                    'tva' => $identifiant['tva'],
                    'peppol' => $identifiant['peppol'],
                    'entreprise' => match ($identifiant['pays']) {
                        'FR' => $this->registreFrancais($identifiant['national']),
                        'BE' => $this->registreBelge($identifiant['national']),
                        default => null,
                    },
                ];
            }

            // Une panne du registre national ne doit pas être confondue avec un numéro inconnu.
            $pannes = ['SERVICE_UNAVAILABLE', 'MS_UNAVAILABLE', 'MS_MAX_CONCURRENT_REQ',
                'GLOBAL_MAX_CONCURRENT_REQ', 'TIMEOUT', 'IP_BLOCKED', 'VAT_BLOCKED'];

            if (in_array($corps['userError'] ?? '', $pannes, true)) {
                if ($tentative < 2) {
                    sleep(1);

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
     * Secteur d'activité déduit de la division NACE, nomenclature commune à l'Union européenne.
     */
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
     * Interroge le registre national des entreprises françaises (données publiques,
     * sans authentification) pour le dirigeant et le secteur d'activité.
     *
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
            $reponse = Http::timeout(8)
                ->get('https://recherche-entreprises.api.gouv.fr/search', ['q' => $siren, 'per_page' => 1]);

            if (! $reponse->ok()) {
                return null;
            }

            $etat = $reponse->json('results.0.etat_administratif');

            return [
                'dirigeant' => $this->premierDirigeant($reponse->json('results.0.dirigeants', [])),
                'secteur' => $this->secteurDepuisNace($reponse->json('results.0.activite_principale')),
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
     * Consulte la fiche publique de la Banque-Carrefour des Entreprises.
     * La BCE ne propose pas d'interface programmable ouverte : les informations
     * sont lues sur la page de consultation publique, une requête par entreprise.
     *
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
            $reponse = Http::timeout(10)
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
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Situations juridiques qui interdisent l'ouverture d'un compte client.
     */
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
     * La BCE publie les mandats sous la forme « Fonction NOM , Prénom ».
     *
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
     * Une entreprise déclare souvent plusieurs activités : on retient la première
     * qui correspond à un secteur du référentiel.
     *
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

    /**
     * Les registres publient les noms en capitales : on rétablit une casse lisible.
     */
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
     * Sépare l'adresse VIES en rue, code postal et localité.
     *
     * @return array{rue: string, code_postal: string, ville: string}
     */
    private function decomposerAdresse(string $brut): array
    {
        $lignes = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $brut))));

        if ($lignes === [] || $lignes === ['---']) {
            return ['rue' => '', 'code_postal' => '', 'ville' => ''];
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

        return [
            'rue' => $this->nettoyer(implode(', ', $lignes)),
            'code_postal' => $this->nettoyer($codePostal),
            'ville' => $this->nettoyer($ville),
        ];
    }
}
