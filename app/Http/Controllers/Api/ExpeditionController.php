<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\TransportOrder;
use App\Support\Adresse;
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
        ]);

        $expedition = TransportOrder::deposer([
            'client_id' => $cle->client_id,
            'created_date' => now()->toDateString(),
            'pickup_address' => $donnees['enlevement'],
            'delivery_address' => $donnees['livraison'],
            'weight' => $donnees['poids'],
            'goods_type' => $donnees['marchandise'],
            'is_hazardous' => $request->boolean('matieres_dangereuses'),
            'needs_tail_lift' => $request->boolean('hayon'),
            'pickup_date' => $donnees['date_enlevement'],
            'requested_delivery_date' => $donnees['date_livraison'],
            'special_instructions' => $donnees['instructions'] ?? null,
            'status' => 'PENDING',
            'priority' => 'NORMAL',
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
