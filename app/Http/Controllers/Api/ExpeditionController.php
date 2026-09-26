<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TransportOrderController;
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
use App\Support\Trajet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return response()->json([
            'data' => $expeditions->getCollection()->map(fn (TransportOrder $o) => $this->format($o))->all(),
            'meta' => [
                'page' => $expeditions->currentPage(),
                'par_page' => $expeditions->perPage(),
                'total' => $expeditions->total(),
                'pages' => $expeditions->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $numero): JsonResponse
    {
        $expedition = $this->perimetre($request)->where('tracking_number', $numero)->first();

        if ($expedition === null) {
            return response()->json(['message' => 'Expédition introuvable.'], 404);
        }

        return response()->json(['data' => $this->format($expedition, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $cle = $request->attributes->get('cle_api');

        if ($cle->client_id === null) {
            return response()->json([
                'message' => 'Cette clé n\'est rattachée à aucune entreprise : elle ne peut pas déposer d\'ordre.',
            ], 422);
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
            'matieres_dangereuses' => [Rule::requiredIf(fn () => in_array($request->input('marchandise'), TransportOrder::MARCHANDISES_ADR, true)), 'boolean'],
            'hayon' => 'boolean',
            'instructions' => 'nullable|string|max:500',
            'pays_livraison' => 'nullable|string|size:2|exists:tariff_grids,zone',
            'formule' => ['nullable', Rule::in(['ECO', 'STANDARD', 'EXPRESS'])],
            'pays_enlevement' => 'nullable|string|size:2',
            'expediteur' => 'nullable|string|max:150',
            'telephone_expediteur' => 'nullable|string|max:30',
            'reference_chargement' => 'nullable|string|max:60',
        ]);

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

        // Pays d'enlevement : le parametre, sinon celui ecrit dans l'adresse,
        // sinon la Belgique (les appels existants ne changent pas).
        $nomDepart = Adresse::pays($donnees['enlevement']);
        $paysDepart = strtoupper($donnees['pays_enlevement'] ?? '') ?: ($nomDepart === null ? 'BE' : (Pays::depuisNom($nomDepart) ?? ''));

        if ($paysDepart === '') {
            return response()->json(['message' => 'Pays d\'enlèvement non reconnu : utilisez pays_enlevement.'], 422);
        }

        if (! Adresse::paysCoherent($donnees['enlevement'], $paysDepart, false)) {
            return response()->json(['message' => 'Le pays écrit dans l\'adresse d\'enlèvement ne correspond pas à pays_enlevement.'], 422);
        }

        $trajet = new Trajet($paysDepart, $pays);

        if ($refus = TransportOrderController::refusDuTrajet($trajet, $donnees['enlevement'])) {
            return response()->json(['message' => $refus['message']], 422);
        }

        if ($paysDepart !== 'BE' && (empty($donnees['expediteur']) || empty($donnees['telephone_expediteur']))) {
            return response()->json(['message' => 'Hors de Belgique, indiquez expediteur et telephone_expediteur (le chauffeur charge chez un tiers).'], 422);
        }

        $region = FretRetour::regionDe($paysDepart, $donnees['enlevement']);
        $jourEnlevement = Carbon::parse($donnees['date_enlevement'], Trajet::fuseau($paysDepart))->startOfDay();

        if (JoursFeries::chome($jourEnlevement, $paysDepart, $region)) {
            return response()->json([
                'message' => 'Aucun enlèvement ce jour-là (dimanche ou jour férié en '.$paysDepart.'). Premier jour ouvrable : '
                    .JoursFeries::prochainJourOuvrable($jourEnlevement, $paysDepart, $region)->toDateString().'.',
            ], 422);
        }

        try {
            $points = $this->situer($donnees['enlevement'], $donnees['livraison'], $pays, $paysDepart);
        } catch (GeocodageIndisponible) {
            return response()->json([
                'message' => 'La vérification de l\'adresse de livraison est momentanément indisponible. Réessayez dans quelques minutes.',
            ], 503)->header('Retry-After', '120');
        }

        if (is_string($points)) {
            return response()->json(['message' => $points], 422);
        }

        // Hors de Belgique : un camion doit pouvoir y etre, route depuis
        // Bruxelles et repos compris. Le chargement se prevoit a l'ouverture
        // du quai, ou au premier instant possible ce jour-la.
        $enlevement = Carbon::parse($donnees['date_enlevement']);

        if ($paysDepart !== 'BE') {
            $premier = FretRetour::premierEnlevement($paysDepart, (float) $points['enlevement']->lat, (float) $points['enlevement']->lng, $donnees['enlevement']);
            $premierLocal = $premier->copy()->setTimezone(Trajet::fuseau($paysDepart));

            if ($jourEnlevement->lt($premierLocal->copy()->startOfDay())) {
                return response()->json(['message' => 'Hors de Belgique, l\'enlèvement est possible au plus tôt le '.$premierLocal->toDateString().' (route depuis Bruxelles et repos du chauffeur compris).'], 422);
            }

            [$h, $m] = array_map('intval', explode(':', (string) config('fret.chrono.quai.0', '07:00')));
            $enlevement = $jourEnlevement->copy()->setTime($h, $m)->setTimezone(config('app.timezone'));
            $enlevement = $enlevement->lt($premier) ? $premier : $enlevement;
        }

        $km = Tarificateur::distanceRoutiere(
            (float) $points['enlevement']->lat, (float) $points['enlevement']->lng,
            (float) $points['livraison']->lat, (float) $points['livraison']->lng,
        );

        $grille = $this->grille($trajet, $donnees['formule'] ?? null,
            $donnees['date_enlevement'], $donnees['date_livraison'], $km);

        if ($grille === null) {
            return response()->json([
                'message' => 'Aucune formule ne permet de livrer en '.$pays.' à la date demandée.',
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

        $expedition = DB::transaction(function () use ($trajet, $demande, $km, $grille, $cle, $donnees, $adr, $request, $enlevement, $paysDepart, $pays, $points) {
            DB::statement('select pg_advisory_xact_lock(?)', [FretRetour::VERROU]);
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
                'priority' => 'NORMAL',
                'tariff_grid_id' => $grille->id,
                'distance_km' => (int) round($km),
                'estimated_cost' => $offre['prix'][$grille->id],
                'tracking_code' => TransportOrder::prochainCode(),
            ]);
        });

        ActivityLog::record(
            'order.created_api',
            'Expédition '.$expedition->tracking_number.' déposée par l\'API',
            $expedition,
            ['cle' => $cle->prefix, 'entreprise' => $cle->client_id],
            $cle->created_by,
        );

        return response()->json(['data' => $this->format($expedition, true)], 201);
    }

    /**
     * @return array{enlevement: object, livraison: object}|string
     */
    private function situer(string $enlevement, string $livraison, string $pays, string $paysDepart = 'BE'): array|string
    {
        $depart = Localite::coordonnees(Adresse::localite($enlevement), $paysDepart);

        if ($depart === null) {
            return 'L\'adresse d\'enlèvement ne correspond à aucune localité connue en '.$paysDepart.'.';
        }

        $arrivee = Localite::coordonnees(Adresse::localite($livraison), $pays);

        if ($arrivee === null) {
            return 'L\'adresse de livraison ne correspond à aucune localité connue en '.$pays.'.';
        }

        return ['enlevement' => $depart, 'livraison' => $arrivee];
    }

    /**
     * La formule du trajet : celle demandee, sinon la moins rapide qui tient
     * le delai, route comprise (9 h de conduite par jour).
     */
    private function grille(Trajet $trajet, ?string $formule, string $enlevement, string $livraison, float $km): ?TariffGrid
    {
        $grilles = $trajet->grilles();

        if ($formule !== null) {
            return $grilles->firstWhere('service_level', $formule);
        }

        $jours = Carbon::parse($enlevement)->startOfDay()
            ->diffInDays(Carbon::parse($livraison)->startOfDay(), false);

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
            'creee_le' => $o->created_date?->toDateString(),
        ];
    }
}
