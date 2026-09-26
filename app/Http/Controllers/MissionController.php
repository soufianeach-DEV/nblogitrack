<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DriverAcknowledgement;
use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use App\Support\Adresse;
use App\Support\OrderWorkflow;
use App\Support\Traductions;
use App\Support\TransitionRefusee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MissionController extends Controller
{
    private const TRANSITIONS = [
        // Statut vise => statut attendu. Le chauffeur confirme d'abord
        // l'enlevement d'une mission affectee, puis la livraison.
        'IN_PROGRESS' => 'ASSIGNED',
        'DELIVERED' => 'IN_PROGRESS',
    ];

    private const CADENCE_SECONDES = 300;

    public function index(Request $request): Response
    {
        $chauffeur = $request->user();

        $relations = ['client:id,company_name', 'vehicle:registration,brand,model,vehicle_type'];

        // Les missions a faire d'abord, dans l'ordre du chargement ; puis
        // l'historique, du plus recent au plus ancien, limite aux trente
        // dernieres.
        $aFaire = TransportOrder::with($relations)
            ->where('driver_id', $chauffeur->driver?->id)
            ->whereIn('status', ['IN_PROGRESS', 'ASSIGNED'])
            ->orderByRaw("CASE status WHEN 'IN_PROGRESS' THEN 0 ELSE 1 END")
            ->orderBy('pickup_date')
            ->get();

        $terminees = TransportOrder::with($relations)
            ->where('driver_id', $chauffeur->driver?->id)
            ->whereIn('status', ['DELIVERED', 'CANCELLED'])
            ->orderByRaw('COALESCE(delivered_at, cancelled_at, updated_at) DESC')
            ->limit(30)
            ->get();

        $missions = $aFaire->concat($terminees);

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
            'note' => $note === null ? null : [
                'titre' => $note->titre($langue),
                'corps' => $note->corps($langue),
                'version' => $note->updated_at?->toIso8601String(),
                'mise_a_jour' => $note->updated_at?->format('d/m/Y'),
                'a_accuser' => $aInformer,
            ],
        ]);
    }

    public function updateStatus(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        // Le planificateur a pu retirer ou reaffecter la mission pendant
        // que le chauffeur l'avait a l'ecran : il revient a sa liste avec
        // une explication, pas sur une erreur 404.
        if ($transportOrder->driver_id === null || $transportOrder->driver_id !== $request->user()->driver?->id) {
            return redirect()->route('missions.index')->with('error', Traductions::t('msg.mission_retiree', 'Cette mission ne vous est plus affectée : le planificateur l\'a confiée à un autre chauffeur ou remise en attente.'));
        }

        if ($transportOrder->status === 'CANCELLED') {
            return redirect()->route('missions.index', ['mission' => $transportOrder->tracking_number])
                ->with('error', Traductions::t('msg.mission_annulee', 'Cette mission a été annulée : ne chargez pas la marchandise.'));
        }

        $donnees = $request->validate([
            'statut' => 'required|in:'.implode(',', array_keys(self::TRANSITIONS)),
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'precision_m' => 'nullable|integer|min:0|max:100000',
            'receptionnaire' => 'nullable|string|max:120',
            'reserves' => 'nullable|string|max:1000',
        ]);

        $vise = $donnees['statut'];
        $attendu = self::TRANSITIONS[$vise];

        if ($transportOrder->status !== $attendu) {
            return back()->with('error', Traductions::t('msg.mission_etat_change', 'Cette mission n\'est plus dans l\'état attendu, actualisez la page.'));
        }

        // L'enlevement ne se confirme pas plusieurs jours a l'avance : le
        // client verrait sa marchandise « en route » alors qu'elle attend
        // encore sur son quai. La veille reste permise, pour un chargement
        // en fin de journee.
        if ($vise === 'IN_PROGRESS' && $transportOrder->pickup_date !== null
            && $transportOrder->pickup_date->copy()->startOfDay()->gt(now()->addDay()->endOfDay())) {
            return back()->with('error', Traductions::t('msg.mission_trop_tot', 'L\'enlèvement est prévu le :date : il ne peut pas être confirmé plus tôt que la veille.', [
                'date' => $transportOrder->pickup_date->format('d/m/Y'),
            ]));
        }

        try {
            $vise === 'DELIVERED'
                ? OrderWorkflow::livrer($transportOrder, $donnees['receptionnaire'] ?? null, $donnees['reserves'] ?? null)
                : OrderWorkflow::enlever($transportOrder);
        } catch (TransitionRefusee) {
            return back()->with('error', Traductions::t('msg.mission_etat_change', 'Cette mission n\'est plus dans l\'état attendu, actualisez la page.'));
        }

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
            ? Traductions::t('msg.mission_livree', 'Livraison enregistrée.')
            : Traductions::t('msg.mission_prise', 'Mission prise en charge.'));
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

        return back()->with('success', Traductions::t('msg.note_accusee', 'Prise de connaissance enregistrée.'));
    }

    public function position(Request $request, TransportOrder $transportOrder): JsonResponse
    {
        // Mission retiree en route : le telephone arrete de partager.
        if ($transportOrder->driver_id === null || $transportOrder->driver_id !== $request->user()->driver?->id) {
            return response()->json(['suivi' => false, 'motif' => 'retiree']);
        }

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
            'enlevement' => Traductions::vocabulaire('ville', Adresse::localite($ordre->pickup_address)),
            'livraison' => Traductions::vocabulaire('ville', Adresse::localite($ordre->delivery_address)),
            // L'heure et la date se mettent en forme dans la langue du
            // telephone ; la carte montre la date quand ce n'est pas
            // aujourd'hui.
            'enlevement_iso' => $ordre->pickup_date?->toIso8601String(),
            'date_livraison' => $ordre->requested_delivery_date?->toDateString(),
            'annulee_le' => $ordre->status === 'CANCELLED' ? $ordre->cancelled_at?->toIso8601String() : null,
            'suivi_direct' => (bool) $ordre->suivi_direct,
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
            'livree_le' => $ordre->delivered_at?->toIso8601String() ?? $ordre->actual_delivery_date?->toDateString(),
            'receptionnaire' => $ordre->received_by,
            'reserves' => $ordre->delivery_reserves,
            'poids' => $ordre->weight,
            'volume' => $ordre->volume,
            'distance_km' => $ordre->distance_km,
            'consignes' => $ordre->special_instructions,
            'client' => $ordre->client?->company_name,
            'vehicule' => $ordre->vehicle ? [
                'immatriculation' => $ordre->vehicle->registration,
                'modele' => trim($ordre->vehicle->brand.' '.$ordre->vehicle->model),
                'type' => Traductions::vocabulaire('vehicule', $ordre->vehicle->vehicle_type),
            ] : null,
            'action' => match ($ordre->status) {
                'ASSIGNED' => [
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
