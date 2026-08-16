<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Support\Adresse;
use App\Support\Localite;
use App\Support\Pays;
use App\Support\Tarificateur;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * L'API que consomment les systemes des partenaires (A12).
 *
 * Elle sert le suivi d'expedition et la prise d'ordre, c'est-a-dire ce
 * qu'un client integre a son propre outil de gestion. Elle ne sert ni
 * les tarifs d'achat, ni la marge, ni le parc : une API ouverte n'expose
 * que ce que le partenaire a le droit de connaitre.
 *
 * Les reponses sont en anglais dans leurs cles et en francais dans leurs
 * valeurs libres : une cle de JSON est un identifiant technique, pas du
 * texte d'interface, et elle ne se traduit pas.
 */
class ExpeditionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'statut' => ['nullable', Rule::in(['PENDING', 'IN_PROGRESS', 'DELIVERED', 'CANCELLED'])],
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
            // Le meme 404 pour une expedition inexistante et pour celle
            // d'un autre partenaire : sinon la reponse revele qui est
            // client de qui.
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
            'poids' => 'required|numeric|min:1|max:44000',
            'marchandise' => ['required', Rule::in(TransportOrder::MARCHANDISES)],
            'date_enlevement' => 'required|date|after_or_equal:today',
            'date_livraison' => 'required|date|after_or_equal:date_enlevement',
            'matieres_dangereuses' => 'boolean',
            'hayon' => 'boolean',
            'instructions' => 'nullable|string|max:500',
            // Deux ajouts retrocompatibles : un appelant qui les ignore
            // garde exactement le comportement precedent, en mieux.
            'pays_livraison' => 'nullable|string|size:2|exists:tariff_grids,zone',
            'formule' => ['nullable', Rule::in(['ECO', 'STANDARD', 'EXPRESS'])],
        ]);

        $pays = strtoupper($donnees['pays_livraison'] ?? '')
            ?: (Pays::depuisNom(Adresse::pays($donnees['livraison'])) ?? 'BE');

        $points = $this->situer($donnees['enlevement'], $donnees['livraison'], $pays);

        if (is_string($points)) {
            return response()->json(['message' => $points], 422);
        }

        $grille = $this->grille($pays, $donnees['formule'] ?? null,
            $donnees['date_enlevement'], $donnees['date_livraison']);

        if ($grille === null) {
            return response()->json([
                'message' => 'Aucune formule ne permet de livrer en '.$pays.' à la date demandée.',
            ], 422);
        }

        $km = Tarificateur::distanceRoutiere(
            (float) $points['enlevement']->lat, (float) $points['enlevement']->lng,
            (float) $points['livraison']->lat, (float) $points['livraison']->lng,
        );
        $adr = $request->boolean('matieres_dangereuses');

        $expedition = TransportOrder::deposer([
            'client_id' => $cle->client_id,
            'created_date' => now()->toDateString(),
            'pickup_address' => $donnees['enlevement'],
            'delivery_address' => $donnees['livraison'],
            'pickup_lat' => $points['enlevement']->lat,
            'pickup_lng' => $points['enlevement']->lng,
            'delivery_lat' => $points['livraison']->lat,
            'delivery_lng' => $points['livraison']->lng,
            'weight' => $donnees['poids'],
            'goods_type' => $donnees['marchandise'],
            'is_hazardous' => $adr,
            'needs_tail_lift' => $request->boolean('hayon'),
            'pickup_date' => $donnees['date_enlevement'],
            'requested_delivery_date' => $donnees['date_livraison'],
            'special_instructions' => $donnees['instructions'] ?? null,
            'status' => 'PENDING',
            'priority' => 'NORMAL',
            'tariff_grid_id' => $grille->id,
            'distance_km' => (int) round($km),
            'estimated_cost' => Tarificateur::cout($grille, $km, (float) $donnees['poids'], $pays, $adr),
            'tracking_code' => TransportOrder::prochainCode(),
        ]);

        // Un ordre depose par une machine se trace comme un ordre depose
        // par une personne : le journal d'activite doit pouvoir repondre
        // « qui a cree cette expedition ».
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
     * Place les deux adresses sur la carte, au serveur.
     *
     * Un ordre depose par une machine arrivait sans coordonnees, sans
     * distance, sans prix et sans code de suivi : il echappait donc a la
     * carte, au suivi public et a la facturation, qui somme les prix
     * estimes. Une expedition a moitie nee dans la base est pire qu'une
     * expedition refusee, parce que personne ne s'en apercoit.
     *
     * L'enlevement part de Belgique, comme dans le formulaire web.
     *
     * @return array{enlevement: object, livraison: object}|string
     */
    private function situer(string $enlevement, string $livraison, string $pays): array|string
    {
        $depart = Localite::coordonnees(Adresse::localite($enlevement), 'BE');

        if ($depart === null) {
            return 'L\'adresse d\'enlèvement ne correspond à aucune localité belge connue.';
        }

        $arrivee = Localite::coordonnees(Adresse::localite($livraison), $pays);

        if ($arrivee === null) {
            return 'L\'adresse de livraison ne correspond à aucune localité connue en '.$pays.'.';
        }

        return ['enlevement' => $depart, 'livraison' => $arrivee];
    }

    /**
     * La grille qui tarifera l'expedition.
     *
     * L'appelant peut nommer sa formule. S'il se tait, on prend la moins
     * chere qui tienne le delai qu'il demande lui-meme : entre son
     * enlevement et sa livraison souhaitee. Choisir l'express par defaut
     * ferait payer un client presse a un client qui ne l'est pas.
     */
    private function grille(string $pays, ?string $formule, string $enlevement, string $livraison): ?TariffGrid
    {
        $requete = TariffGrid::where('zone', $pays)->where('is_active', true);

        if ($formule !== null) {
            return $requete->where('service_level', $formule)->first();
        }

        $jours = Carbon::parse($enlevement)->startOfDay()
            ->diffInDays(Carbon::parse($livraison)->startOfDay(), false);

        return $requete->where('delivery_days', '<=', $jours)
            ->orderByDesc('delivery_days')
            ->first();
    }

    /**
     * Ce qu'une cle a le droit de voir : les expeditions de l'entreprise
     * a laquelle elle est rattachee, ou toutes si elle est interne.
     */
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
            'creee_le' => $o->created_date?->toDateString(),
        ];
    }
}
