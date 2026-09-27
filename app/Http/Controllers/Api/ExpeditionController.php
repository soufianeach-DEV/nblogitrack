<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TransportOrderController;
use App\Mail\OrdreCree;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use App\Support\Adresse;
use App\Support\Chronologie;
use App\Support\FretRetour;
use App\Support\GeocodageIndisponible;
use App\Support\JoursFeries;
use App\Support\Localite;
use App\Support\Pays;
use App\Support\Tarificateur;
use App\Support\Traductions;
use App\Support\Trajet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class ExpeditionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'statut' => ['nullable', Rule::in(TransportOrder::STATUTS)],
            'depuis' => 'nullable|date',
            'par_page' => 'nullable|integer|min:1|max:100',
        ]);

        $expeditions = $this->perimetre($request)
            ->when($filtres['statut'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filtres['depuis'] ?? null, fn ($q, $d) => $q->whereDate('created_date', '>=', $d))
            ->orderByDesc('id')
            ->paginate($filtres['par_page'] ?? 25);

        $expeditions->appends($request->only(['statut', 'depuis', 'par_page']));

        return response()->json([
            'data' => $expeditions->getCollection()->map(fn (TransportOrder $o) => $this->format($o))->all(),
            'meta' => [
                'page' => $expeditions->currentPage(),
                'par_page' => $expeditions->perPage(),
                'total' => $expeditions->total(),
                'pages' => $expeditions->lastPage(),
            ],
            'liens' => [
                'suivante' => $expeditions->nextPageUrl(),
                'precedente' => $expeditions->previousPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, string $numero): JsonResponse
    {
        $expedition = $this->perimetre($request)->where('tracking_number', $numero)->first();

        if ($expedition === null) {
            return response()->json(['message' => Traductions::t('api.introuvable', 'Expédition introuvable.')], 404);
        }

        return response()->json(['data' => $this->format($expedition, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $cle = $request->attributes->get('cle_api');

        if ($cle->client_id === null) {
            return response()->json([
                'message' => Traductions::t('api.cle_sans_entreprise', 'Cette clé n\'est rattachée à aucune entreprise : elle ne peut pas déposer d\'ordre.'),
            ], 422);
        }

        // Un code pays en minuscules (« fr ») est accepte tel quel.
        foreach (['pays_livraison', 'pays_enlevement'] as $champ) {
            if (is_string($request->input($champ))) {
                $request->merge([$champ => strtoupper(trim($request->input($champ)))]);
            }
        }

        // Idempotence : le meme envoi, rejoue apres une coupure reseau avec
        // la meme cle, rend l'expedition deja creee au lieu d'en creer une
        // seconde.
        $idempotence = trim((string) $request->header('Idempotency-Key'));

        if (mb_strlen($idempotence) > 100) {
            return response()->json(['message' => Traductions::t('api.idempotence_trop_longue', 'L\'en-tête Idempotency-Key ne dépasse pas 100 caractères.')], 422);
        }

        if ($idempotence !== '' && ($deja = $this->dejaDeposee($cle->client_id, $idempotence))) {
            return $deja;
        }

        $donnees = $request->validate([
            'enlevement' => 'required|string|max:255',
            'livraison' => 'required|string|max:255',
            'poids' => 'required|numeric|min:1|max:'.Vehicle::chargeUtileMaxKg(),
            'volume' => 'nullable|numeric|min:0.1|max:'.Vehicle::volumeMaxM3(),
            'marchandise' => ['required', Rule::in(TransportOrder::MARCHANDISES)],
            'date_enlevement' => 'required|date|after_or_equal:today',
            'date_livraison' => 'required|date|after_or_equal:date_enlevement',
            // Pour les marchandises souvent soumises a l'ADR, l'appelant
            // declare explicitement si l'envoi l'est.
            'matieres_dangereuses' => ['nullable', Rule::requiredIf(fn () => in_array($request->input('marchandise'), TransportOrder::MARCHANDISES_ADR, true)), 'boolean'],
            'hayon' => 'nullable|boolean',
            'instructions' => 'nullable|string|max:500',
            'pays_livraison' => 'nullable|string|size:2|exists:tariff_grids,zone',
            'formule' => ['nullable', Rule::in(['ECO', 'STANDARD', 'EXPRESS'])],
            'pays_enlevement' => 'nullable|string|size:2',
            'expediteur' => 'nullable|string|max:150',
            'telephone_expediteur' => 'nullable|string|max:30',
            'reference_chargement' => 'nullable|string|max:60',
        ], [
            // La regle citait « today » tel quel, dans toutes les langues.
            'date_enlevement.after_or_equal' => Traductions::t('api.date_enlevement_passee', 'La date d\'enlèvement ne peut pas être dans le passé.'),
            'date_livraison.after_or_equal' => Traductions::t('api.date_livraison_avant', 'La date de livraison ne peut pas précéder la date d\'enlèvement.'),
        ]);

        // Une reference de chargement designe un seul envoi : la deposer une
        // seconde fois, c'est presque toujours un appel rejoue.
        if (! empty($donnees['reference_chargement'])) {
            $doublon = TransportOrder::where('client_id', $cle->client_id)
                ->where('loading_reference', $donnees['reference_chargement'])
                ->where('status', '!=', 'CANCELLED')
                ->value('tracking_number');

            if ($doublon !== null) {
                return response()->json([
                    'message' => Traductions::t('api.reference_deja_deposee', 'Cette référence de chargement est déjà déposée (expédition :numero).', ['numero' => $doublon]),
                    'numero' => $doublon,
                ], 409);
            }
        }

        // Un envoi qu'aucun camion ne peut prendre (ADR avec hayon, 20 t...)
        // ne serait jamais affecte : refuse tout de suite.
        $volume = isset($donnees['volume']) ? (float) $donnees['volume'] : null;
        $adr = (bool) ($donnees['matieres_dangereuses'] ?? false);
        $hayon = (bool) ($donnees['hayon'] ?? false);

        if (! Vehicle::peutPorter((float) $donnees['poids'], $volume, $adr, $hayon)) {
            return response()->json(['message' => Vehicle::refusFlotte((float) $donnees['poids'], $volume, $adr, $hayon)], 422);
        }

        $pays = strtoupper($donnees['pays_livraison'] ?? '')
            ?: (Pays::depuisNom(Adresse::pays($donnees['livraison'])) ?? 'BE');

        // Le pays ecrit dans l'adresse de livraison doit etre celui de la
        // grille appliquee, comme pour une commande en ligne.
        if (! Adresse::paysCoherent($donnees['livraison'], $pays, false)) {
            return response()->json(['message' => Traductions::t('api.pays_livraison_incoherent', 'Le pays écrit dans l\'adresse de livraison ne correspond pas à pays_livraison.')], 422);
        }

        // Pays d'enlevement : le parametre, sinon celui ecrit dans l'adresse,
        // sinon la Belgique (les appels existants ne changent pas).
        $nomDepart = Adresse::pays($donnees['enlevement']);
        $paysDepart = strtoupper($donnees['pays_enlevement'] ?? '') ?: ($nomDepart === null ? 'BE' : (Pays::depuisNom($nomDepart) ?? ''));

        if ($paysDepart === '') {
            return response()->json(['message' => Traductions::t('api.pays_inconnu', 'Pays d\'enlèvement non reconnu : utilisez pays_enlevement.')], 422);
        }

        if (! Adresse::paysCoherent($donnees['enlevement'], $paysDepart, false)) {
            return response()->json(['message' => Traductions::t('api.pays_incoherent', 'Le pays écrit dans l\'adresse d\'enlèvement ne correspond pas à pays_enlevement.')], 422);
        }

        $trajet = new Trajet($paysDepart, $pays);

        if ($refus = TransportOrderController::refusDuTrajet($trajet, $donnees['enlevement'])) {
            return response()->json(['message' => $refus['message']], 422);
        }

        if ($paysDepart !== 'BE' && (empty($donnees['expediteur']) || empty($donnees['telephone_expediteur']))) {
            return response()->json(['message' => Traductions::t('api.expediteur_requis', 'Hors de Belgique, indiquez expediteur et telephone_expediteur (le chauffeur charge chez un tiers).')], 422);
        }

        $region = FretRetour::regionDe($paysDepart, $donnees['enlevement']);
        $jourEnlevement = Carbon::parse($donnees['date_enlevement'], Trajet::fuseau($paysDepart))->startOfDay();

        if (JoursFeries::chome($jourEnlevement, $paysDepart, $region)) {
            return response()->json([
                'message' => Traductions::t('api.jour_chome', 'Aucun enlèvement ce jour-là (dimanche ou jour férié en :pays). Premier jour ouvrable : :date.', [
                    'pays' => $paysDepart,
                    'date' => JoursFeries::prochainJourOuvrable($jourEnlevement, $paysDepart, $region)->toDateString(),
                ]),
            ], 422);
        }

        try {
            $points = $this->situer($donnees['enlevement'], $donnees['livraison'], $pays, $paysDepart);
        } catch (GeocodageIndisponible) {
            return response()->json([
                'message' => Traductions::t('api.geocodage_indisponible', 'La vérification de l\'adresse de livraison est momentanément indisponible. Réessayez dans quelques minutes.'),
            ], 503)->header('Retry-After', '120');
        }

        if (is_string($points)) {
            return response()->json(['message' => $points], 422);
        }

        // Hors de Belgique : un camion doit pouvoir y etre, route depuis
        // Bruxelles et repos compris. Le chargement se prevoit a l'ouverture
        // du quai, ou au premier instant possible ce jour-la.
        // En Belgique, le chargement se prevoit a l'ouverture du quai, et
        // jamais dans le passe pour un enlevement demande le jour meme.
        [$h, $m] = array_map('intval', explode(':', (string) config('fret.chrono.quai.0', '07:00')));
        $enlevement = $jourEnlevement->copy()->setTime($h, $m)->setTimezone(config('app.timezone'));
        $enlevement = $enlevement->lt(now()) ? now()->startOfMinute() : $enlevement;

        if ($paysDepart !== 'BE') {
            $premier = FretRetour::premierEnlevement($paysDepart, (float) $points['enlevement']->lat, (float) $points['enlevement']->lng, $donnees['enlevement']);
            $premierLocal = $premier->copy()->setTimezone(Trajet::fuseau($paysDepart));

            if ($jourEnlevement->lt($premierLocal->copy()->startOfDay())) {
                return response()->json(['message' => Traductions::t('api.enlevement_trop_tot', 'Hors de Belgique, l\'enlèvement est possible au plus tôt le :date (route depuis Bruxelles et repos du chauffeur compris).', ['date' => $premierLocal->toDateString()])], 422);
            }

            $enlevement = $jourEnlevement->copy()->setTime($h, $m)->setTimezone(config('app.timezone'));
            $enlevement = $enlevement->lt($premier) ? $premier : $enlevement;
        }

        $km = Tarificateur::distanceRoutiere(
            (float) $points['enlevement']->lat, (float) $points['enlevement']->lng,
            (float) $points['livraison']->lat, (float) $points['livraison']->lng,
        );

        $jours = Carbon::parse($donnees['date_enlevement'])->startOfDay()
            ->diffInDays(Carbon::parse($donnees['date_livraison'])->startOfDay(), false);

        $grille = $this->grille($trajet, $donnees['formule'] ?? null, $jours, $km);

        if ($grille === null) {
            return response()->json([
                'message' => ! empty($donnees['formule'])
                    ? Traductions::t('api.formule_trop_lente', 'La formule :formule ne permet pas de livrer en :pays à la date demandée.', ['formule' => $donnees['formule'], 'pays' => $pays])
                    : Traductions::t('api.aucune_formule', 'Aucune formule ne permet de livrer en :pays à la date demandée.', ['pays' => $pays]),
            ], 422);
        }

        $adr = $request->boolean('matieres_dangereuses');

        $demande = new TransportOrder([
            'client_id' => $cle->client_id,
            'pickup_country' => $paysDepart,
            'delivery_country' => $pays,
            'pickup_address' => $donnees['enlevement'],
            'pickup_lat' => $points['enlevement']->lat,
            'pickup_lng' => $points['enlevement']->lng,
            'delivery_lat' => $points['livraison']->lat,
            'delivery_lng' => $points['livraison']->lng,
            'pickup_date' => $enlevement,
            'weight' => $donnees['poids'],
            'volume' => $donnees['volume'] ?? null,
            'is_hazardous' => $adr,
            'needs_tail_lift' => $request->boolean('hayon'),
            'distance_km' => (int) round($km),
        ]);

        $expedition = DB::transaction(function () use ($trajet, $demande, $km, $grille, $cle, $donnees, $adr, $request, $enlevement, $paysDepart, $pays, $points, $idempotence, $jours) {
            DB::statement('select pg_advisory_xact_lock(?)', [FretRetour::VERROU]);

            // Deux envois simultanes de la meme cle : le second attend le
            // verrou, puis retrouve l'ordre du premier.
            if ($idempotence !== '' && ($deja = TransportOrder::where('client_id', $cle->client_id)->where('idempotency_key', $idempotence)->first())) {
                return $deja->setAttribute('rejoue', true);
            }

            $offre = Tarificateur::offre($trajet, $demande, $km);

            return TransportOrder::deposer([
                'client_id' => $cle->client_id,
                'created_date' => now()->toDateString(),
                'pickup_address' => $donnees['enlevement'],
                'pickup_country' => $paysDepart,
                'delivery_address' => $donnees['livraison'],
                'delivery_country' => $pays,
                'shipper_name' => $donnees['expediteur'] ?? null,
                'shipper_phone' => $donnees['telephone_expediteur'] ?? null,
                'loading_reference' => $donnees['reference_chargement'] ?? null,
                'pricing_basis' => $offre['base'],
                'backhaul_order_id' => $offre['porteuse']?->id,
                'approche_km' => $paysDepart === 'BE' ? null : (int) round($offre['porteuse'] !== null
                    ? FretRetour::approche($offre['porteuse'], (float) $points['enlevement']->lat, (float) $points['enlevement']->lng)
                    : Chronologie::kmDepuisDepot((float) $points['enlevement']->lat, (float) $points['enlevement']->lng)),
                'pickup_lat' => $points['enlevement']->lat,
                'pickup_lng' => $points['enlevement']->lng,
                'delivery_lat' => $points['livraison']->lat,
                'delivery_lng' => $points['livraison']->lng,
                'weight' => $donnees['poids'],
                'volume' => $donnees['volume'] ?? null,
                'goods_type' => $donnees['marchandise'],
                'is_hazardous' => $adr,
                'needs_tail_lift' => $request->boolean('hayon'),
                'pickup_date' => $enlevement,
                'requested_delivery_date' => $donnees['date_livraison'],
                'special_instructions' => $donnees['instructions'] ?? null,
                'status' => 'PENDING',
                // Meme regle que la commande en ligne : deux jours ou moins
                // pour livrer, l'ordre passe en tete du planning.
                'priority' => $jours <= 2 ? 'URGENT' : 'NORMAL',
                'tariff_grid_id' => $grille->id,
                'distance_km' => (int) round($km),
                'estimated_cost' => $offre['prix'][$grille->id],
                'tracking_code' => TransportOrder::prochainCode(),
                'idempotency_key' => $idempotence !== '' ? $idempotence : null,
            ]);
        });

        if ($expedition->getAttribute('rejoue')) {
            return $this->dejaDeposee($cle->client_id, $idempotence);
        }

        ActivityLog::record(
            'order.created_api',
            'Expédition '.$expedition->tracking_number.' déposée par l\'API',
            $expedition,
            ['cle' => $cle->prefix, 'entreprise' => $cle->client_id],
            $cle->created_by,
        );

        // L'entreprise recoit le code de suivi par e-mail, comme pour une
        // commande passee en ligne.
        foreach ($cle->client->commanditaires() as $compte) {
            try {
                Mail::to($compte->email)->send(new OrdreCree($expedition, $compte, $grille));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['data' => $this->format($expedition, true)], 201)
            ->header('Location', route('api.expeditions.show', $expedition->tracking_number));
    }

    /**
     * L'expedition deja deposee sous cette cle d'idempotence, rendue telle
     * qu'au premier appel.
     */
    private function dejaDeposee(int $entreprise, string $idempotence): ?JsonResponse
    {
        $expedition = TransportOrder::where('client_id', $entreprise)
            ->where('idempotency_key', $idempotence)
            ->first();

        if ($expedition === null) {
            return null;
        }

        return response()->json(['data' => $this->format($expedition, true)], 201)
            ->header('Location', route('api.expeditions.show', $expedition->tracking_number))
            ->header('Idempotent-Replayed', 'true');
    }

    /**
     * @return array{enlevement: object, livraison: object}|string
     */
    private function situer(string $enlevement, string $livraison, string $pays, string $paysDepart = 'BE'): array|string
    {
        $depart = Localite::coordonnees(Adresse::localite($enlevement), $paysDepart);

        if ($depart === null) {
            return Traductions::t('api.enlevement_inconnu', 'L\'adresse d\'enlèvement ne correspond à aucune localité connue en :pays.', ['pays' => $paysDepart]);
        }

        $arrivee = Localite::coordonnees(Adresse::localite($livraison), $pays);

        if ($arrivee === null) {
            return Traductions::t('api.livraison_inconnue', 'L\'adresse de livraison ne correspond à aucune localité connue en :pays.', ['pays' => $pays]);
        }

        return ['enlevement' => $depart, 'livraison' => $arrivee];
    }

    /**
     * La formule du trajet : celle demandee si elle tient le delai, sinon
     * la moins rapide qui le tient, route comprise (9 h de conduite par
     * jour).
     */
    private function grille(Trajet $trajet, ?string $formule, float $jours, float $km): ?TariffGrid
    {
        $grilles = $trajet->grilles();

        if ($formule !== null) {
            $grille = $grilles->firstWhere('service_level', $formule);

            return $grille !== null && Tarificateur::delai($grille, $km) <= $jours ? $grille : null;
        }

        return $grilles->filter(fn (TariffGrid $g) => Tarificateur::delai($g, $km) <= $jours)
            ->sortByDesc(fn (TariffGrid $g) => Tarificateur::delai($g, $km))
            ->first();
    }

    private function perimetre(Request $request)
    {
        $cle = $request->attributes->get('cle_api');

        return TransportOrder::query()
            ->when($cle instanceof ApiKey && $cle->client_id !== null,
                fn ($q) => $q->where('client_id', $cle->client_id));
    }

    /** @return array<string, mixed> */
    private function format(TransportOrder $o, bool $detaille = false): array
    {
        $base = [
            'numero' => $o->tracking_number,
            'statut' => $o->status,
            'priorite' => $o->priority,
            'depart' => Adresse::localite($o->pickup_address),
            'pays_depart' => $o->pickup_country ?? 'BE',
            'pays_arrivee' => $o->delivery_country ?? 'BE',
            'arrivee' => Adresse::localite($o->delivery_address),
            'enlevement_prevu' => $o->pickup_date?->toDateString(),
            // L'heure exacte, avec son fuseau.
            'enlevement_prevu_a' => $o->pickup_date?->toIso8601String(),
            'livraison_prevue' => $o->requested_delivery_date?->toDateString(),
            'livraison_reelle' => $o->actual_delivery_date?->toDateString(),
        ];

        if (! $detaille) {
            return $base;
        }

        return $base + [
            'adresse_enlevement' => $o->pickup_address,
            'adresse_livraison' => $o->delivery_address,
            'poids_kg' => (float) $o->weight,
            'marchandise' => $o->goods_type,
            'matieres_dangereuses' => (bool) $o->is_hazardous,
            'hayon' => (bool) $o->needs_tail_lift,
            'distance_km' => $o->distance_km !== null ? (float) $o->distance_km : null,
            'prix_ht' => $o->estimated_cost !== null ? (float) $o->estimated_cost : null,
            'tarif' => $o->pricing_basis === 'BACKHAUL' ? 'fret_retour' : 'ligne',
            'expediteur' => $o->shipper_name,
            'reference_chargement' => $o->loading_reference,
            'creee_le' => $o->created_date?->toDateString(),
            // Le code et le lien de la page de suivi publique, a transmettre
            // au destinataire.
            'code_suivi' => $o->tracking_code,
            'suivi_url' => $o->tracking_code ? route('tracking.show', [
                'langue' => app()->getLocale(),
                'tracking_number' => $o->tracking_number,
                'code' => $o->tracking_code,
            ]) : null,
        ];
    }
}
