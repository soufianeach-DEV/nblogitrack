<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DriverAcknowledgement;
use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use App\Support\Adresse;
use App\Support\Traductions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MissionController extends Controller
{
    private const TRANSITIONS = [
        'IN_PROGRESS' => 'PENDING',
        'DELIVERED' => 'IN_PROGRESS',
    ];

    private const CADENCE_SECONDES = 300;

    public function index(Request $request): Response
    {
        $chauffeur = $request->user();

        $missions = TransportOrder::with([
            'client:id,company_name',
            'vehicle:registration,brand,model,vehicle_type',
        ])
            ->where('driver_id', $chauffeur->id)
            ->orderByRaw("CASE status WHEN 'IN_PROGRESS' THEN 0 WHEN 'PENDING' THEN 1 ELSE 2 END")
            ->orderBy('pickup_date')
            ->get();

        $numero = trim((string) $request->query('mission', ''));
        $ouverte = $numero !== ''
            ? $missions->firstWhere('tracking_number', $numero)
            : null;

        $note = DriverAcknowledgement::note();
        $aInformer = $note !== null && ! DriverAcknowledgement::aJour($chauffeur->id, $note);
        $langue = app()->getLocale();

        return Inertia::render('Chauffeur/Missions', [
            'missions' => $missions->map(fn (TransportOrder $ordre) => $this->carte($ordre))->all(),
            'mission' => $ouverte ? $this->fiche($ouverte) : null,
            'introuvable' => $numero !== '' && $ouverte === null,
            'note' => $aInformer ? [
                'titre' => $note->titre($langue),
                'corps' => $note->corps($langue),
                'version' => $note->updated_at?->toIso8601String(),
                'mise_a_jour' => $note->updated_at?->format('d/m/Y'),
            ] : null,
        ]);
    }

    public function updateStatus(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        abort_if($transportOrder->driver_id !== $request->user()->id, 404);

        $donnees = $request->validate([
            'statut' => 'required|in:'.implode(',', array_keys(self::TRANSITIONS)),
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'precision_m' => 'nullable|integer|min:0|max:100000',
        ]);

        $vise = $donnees['statut'];
        $attendu = self::TRANSITIONS[$vise];

        if ($transportOrder->status !== $attendu) {
            return back()->with('error', "Cette mission n'est plus dans l'état attendu, actualisez la page.");
        }

        $changements = ['status' => $vise];

        if ($vise === 'DELIVERED') {
            $changements['actual_delivery_date'] = now();
        }

        $transportOrder->update($changements);

        $this->poserJalon($transportOrder, $vise, $donnees, $request->user()->id);

        ActivityLog::record(
            $vise === 'DELIVERED' ? 'mission.delivered' : 'mission.started',
            $vise === 'DELIVERED'
                ? 'Livraison effectuée pour '.$transportOrder->tracking_number
                : 'Prise en charge de '.$transportOrder->tracking_number,
            $transportOrder,
            [
                'ancien_statut' => $attendu,
                'nouveau_statut' => $vise,
                'chauffeur' => Auth::id(),
            ],
        );

        return back()->with('success', $vise === 'DELIVERED'
            ? 'Livraison enregistrée.'
            : 'Mission prise en charge.');
    }

    public function accuser(Request $request): RedirectResponse
    {
        $note = DriverAcknowledgement::note();

        if ($note === null) {
            return back();
        }

        DriverAcknowledgement::firstOrCreate(
            ['user_id' => $request->user()->id, 'version' => $note->updated_at],
            ['acknowledged_at' => now(), 'ip_address' => $request->ip()],
        );

        ActivityLog::record(
            'driver.notice_acknowledged',
            'Prise de connaissance de la note d\'information par '.$request->user()->email,
            $note,
            ['version' => $note->updated_at?->toIso8601String()],
        );

        return back()->with('success', 'Prise de connaissance enregistrée.');
    }

    public function position(Request $request, TransportOrder $transportOrder): JsonResponse
    {
        abort_if($transportOrder->driver_id !== $request->user()->id, 404);

        if ($transportOrder->status !== 'IN_PROGRESS' || ! $transportOrder->suivi_direct) {
            return response()->json(['suivi' => false]);
        }

        if (! DriverAcknowledgement::aJour($request->user()->id)) {
            return response()->json(['suivi' => false, 'motif' => 'information_manquante']);
        }

        $donnees = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'precision_m' => 'nullable|integer|min:0|max:100000',
        ]);

        $lat = (float) $donnees['lat'];
        $lng = (float) $donnees['lng'];
        $precision = isset($donnees['precision_m']) ? (int) $donnees['precision_m'] : null;

        if (! ShipmentPosition::utilisable($lat, $lng, $precision)) {
            return response()->json(['suivi' => true, 'retenu' => false]);
        }

        $dernier = ShipmentPosition::where('transport_order_id', $transportOrder->id)
            ->where('type', ShipmentPosition::ROUTE)
            ->latest('recorded_at')->first();

        if ($dernier !== null && $dernier->recorded_at->diffInSeconds(now()) < self::CADENCE_SECONDES) {
            return response()->json(['suivi' => true, 'retenu' => false]);
        }

        ShipmentPosition::create([
            'transport_order_id' => $transportOrder->id,
            'driver_id' => $request->user()->id,
            'type' => ShipmentPosition::ROUTE,
            'lat' => $lat,
            'lng' => $lng,
            'precision_m' => $precision,
            'recorded_at' => now(),
        ]);

        return response()->json(['suivi' => true, 'retenu' => true]);
    }

    private function poserJalon(TransportOrder $ordre, string $statut, array $donnees, int $chauffeur): void
    {
        $lat = isset($donnees['lat']) ? (float) $donnees['lat'] : null;
        $lng = isset($donnees['lng']) ? (float) $donnees['lng'] : null;
        $precision = isset($donnees['precision_m']) ? (int) $donnees['precision_m'] : null;

        if (! ShipmentPosition::utilisable($lat, $lng, $precision)) {
            return;
        }

        ShipmentPosition::create([
            'transport_order_id' => $ordre->id,
            'driver_id' => $chauffeur,
            'type' => ShipmentPosition::JALON,
            'evenement' => $statut === 'DELIVERED' ? 'DELIVERED' : 'PICKED_UP',
            'lat' => $lat,
            'lng' => $lng,
            'precision_m' => $precision,
            'recorded_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function carte(TransportOrder $ordre): array
    {
        return [
            'id' => $ordre->id,
            'numero' => $ordre->tracking_number,
            'statut' => $ordre->status,
            'priorite' => $ordre->priority,
            'enlevement' => Adresse::localite($ordre->pickup_address),
            'livraison' => Adresse::localite($ordre->delivery_address),
            'heure_enlevement' => $ordre->pickup_date?->format('H\hi'),
            'date_enlevement' => $ordre->pickup_date?->format('d/m'),
            'date_livraison' => $ordre->requested_delivery_date?->format('d/m'),
            'marchandise' => $ordre->goods_type,
            'adr' => (bool) $ordre->is_hazardous,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fiche(TransportOrder $ordre): array
    {
        return array_merge($this->carte($ordre), [
            'adresse_enlevement' => $ordre->pickup_address,
            'adresse_livraison' => $ordre->delivery_address,
            'enlevement_prevu' => $ordre->pickup_date?->format('d/m/Y à H\hi'),
            'livraison_prevue' => $ordre->requested_delivery_date?->format('d/m/Y'),
            'livree_le' => $ordre->actual_delivery_date?->format('d/m/Y'),
            'poids' => $ordre->weight,
            'volume' => $ordre->volume,
            'distance_km' => $ordre->distance_km,
            'consignes' => $ordre->special_instructions,
            'suivi_direct' => (bool) $ordre->suivi_direct,
            'client' => $ordre->client?->company_name,
            'vehicule' => $ordre->vehicle ? [
                'immatriculation' => $ordre->vehicle->registration,
                'modele' => trim($ordre->vehicle->brand.' '.$ordre->vehicle->model),
                'type' => $ordre->vehicle->vehicle_type,
            ] : null,
            'action' => match ($ordre->status) {
                'PENDING' => [
                    'statut' => 'IN_PROGRESS',
                    'libelle' => Traductions::t('mission.confirmer_prise', 'Confirmer la prise en charge'),
                ],
                'IN_PROGRESS' => [
                    'statut' => 'DELIVERED',
                    'libelle' => Traductions::t('mission.confirmer_livraison', 'Confirmer la livraison'),
                ],
                default => null,
            },
        ]);
    }
}
