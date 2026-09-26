<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use App\Support\Adresse;
use App\Support\Formats;
use App\Support\TempsDeConduite;
use App\Support\Traductions;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanningController extends Controller
{
    private const TRANSITIONS = [
        // Un ordre en attente ne passe en cours que par l'affectation, qui
        // controle le chauffeur, le vehicule, l'ADR et les temps de
        // conduite. Le permettre ici ouvrait un raccourci vers DELIVERED,
        // donc vers la facture, sans chauffeur ni vehicule.
        'PENDING' => ['CANCELLED'],
        // Une mission affectee n'est pas encore partie : seul le chauffeur
        // la fait passer en cours, en confirmant l'enlevement.
        'ASSIGNED' => ['CANCELLED'],
        'IN_PROGRESS' => ['DELIVERED', 'CANCELLED'],
        'DELIVERED' => [],
        'CANCELLED' => [],
    ];

    public function index(Request $request): Response
    {
        $statut = $request->query('status', 'PENDING');
        if (! array_key_exists($statut, self::TRANSITIONS)) {
            $statut = 'PENDING';
        }

        $priorite = $request->query('priorite');
        if (! in_array($priorite, TransportOrder::PRIORITES, true)) {
            $priorite = null;
        }

        $contrainte = $request->query('contrainte');
        if (! in_array($contrainte, ['adr', 'hayon'], true)) {
            $contrainte = null;
        }

        $colonneContrainte = ['adr' => 'is_hazardous', 'hayon' => 'needs_tail_lift'];

        $q = trim((string) $request->query('q', ''));

        $recherche = fn ($requete) => $requete->where(fn ($w) => $w
            ->where('tracking_number', 'ilike', '%'.$q.'%')
            ->orWhere('pickup_address', 'ilike', '%'.$q.'%')
            ->orWhere('delivery_address', 'ilike', '%'.$q.'%')
            ->orWhereHas('client', fn ($c) => $c->where('company_name', 'ilike', '%'.$q.'%')));

        $orders = TransportOrder::with([
            'client:id,company_name',
            'vehicle:registration,brand,model,capacity_tonnes',
            'driver.user:id,first_name,last_name',
        ])
            ->where('status', $statut)
            ->when($priorite, fn ($q) => $q->where('priority', $priorite))
            ->when($contrainte, fn ($q) => $q->where($colonneContrainte[$contrainte], true))
            ->when($q !== '', $recherche)
            ->orderByRaw("CASE priority WHEN 'URGENT' THEN 1 WHEN 'HIGH' THEN 2 WHEN 'NORMAL' THEN 3 ELSE 4 END")
            ->orderBy('pickup_date')
            ->paginate(15)
            ->withQueryString()
            ->through(function (TransportOrder $o) {
                $o->setAttribute('conduite', TempsDeConduite::resume($o->distance_km));
                $o->setAttribute('conduite_heures', TempsDeConduite::heuresDeConduite($o->distance_km));

                return $o;
            });

        $parPriorite = TransportOrder::where('status', $statut)
            ->when($contrainte, fn ($q) => $q->where($colonneContrainte[$contrainte], true))
            ->when($q !== '', $recherche)
            ->selectRaw('priority, count(*) AS total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $parContrainte = TransportOrder::where('status', $statut)
            ->when($priorite, fn ($q) => $q->where('priority', $priorite))
            ->when($q !== '', $recherche)
            ->selectRaw('count(*) filter (where is_hazardous) AS adr, count(*) filter (where needs_tail_lift) AS hayon')
            ->first();

        $vehicles = Vehicle::where('is_available', true)
            ->orderBy('registration')
            ->get(['registration', 'brand', 'model', 'vehicle_type', 'capacity_tonnes', 'capacity_volume', 'has_tail_lift']);

        $conduiteSemaine = TransportOrder::whereNotNull('driver_id')
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('pickup_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->selectRaw('driver_id, sum(distance_km) AS km')
            ->groupBy('driver_id')
            ->pluck('km', 'driver_id');

        $drivers = Driver::with('user:id,first_name,last_name')
            ->where('is_available', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->get()
            ->map(fn (Driver $d) => [
                'id' => $d->id,
                'nom' => $d->user ? $d->user->first_name.' '.$d->user->last_name : 'Chauffeur '.$d->id,
                'license_type' => $d->license_type,
                'adr_certified' => $d->adr_certified,
                'empechements' => $d->empechements(),
                'conduite_semaine' => TempsDeConduite::heuresDeConduite((int) ($conduiteSemaine[$d->id] ?? 0)),
            ])
            ->sortBy('nom')
            ->values();

        return Inertia::render('Planning/Index', [
            'orders' => $orders,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'statut' => $statut,
            'priorite' => $priorite,
            'contrainte' => $contrainte,
            'priorites' => collect(TransportOrder::PRIORITES)
                ->map(fn (string $p) => ['valeur' => $p, 'nombre' => (int) ($parPriorite[$p] ?? 0)])
                ->all(),
            'contraintes' => [
                ['valeur' => 'adr', 'nombre' => (int) $parContrainte->adr],
                ['valeur' => 'hayon', 'nombre' => (int) $parContrainte->hayon],
            ],
            'compteurs' => TransportOrder::when($q !== '', $recherche)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'q' => $q,
            'suggestions' => $this->suggestions($q, $statut),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function suggestions(string $q, string $statut): array
    {
        if (mb_strlen($q) < 2) {
            return [];
        }

        $filtre = '%'.$q.'%';

        $numeros = TransportOrder::where('status', $statut)
            ->where('tracking_number', 'ilike', $filtre)
            ->orderBy('tracking_number')
            ->limit(8)
            ->pluck('tracking_number');

        $entreprises = TransportOrder::where('status', $statut)
            ->whereHas('client', fn ($c) => $c->where('company_name', 'ilike', $filtre))
            ->with('client:id,company_name')
            ->limit(40)
            ->get()
            ->pluck('client.company_name')
            ->filter()
            ->unique()
            ->sort()
            ->take(6);

        $villes = TransportOrder::where('status', $statut)
            ->where(fn ($w) => $w
                ->where('pickup_address', 'ilike', $filtre)
                ->orWhere('delivery_address', 'ilike', $filtre))
            ->limit(60)
            ->get(['pickup_address', 'delivery_address'])
            ->flatMap(fn (TransportOrder $o) => [
                Adresse::localite($o->pickup_address),
                Adresse::localite($o->delivery_address),
            ])
            ->filter(fn (string $ville) => mb_stripos($ville, $q) !== false)
            ->unique()
            ->sort()
            ->take(6);

        return $numeros->merge($entreprises)->merge($villes)->unique()->take(12)->values()->all();
    }

    public function assign(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_registration' => 'required|exists:vehicles,registration',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($transportOrder->status !== 'PENDING') {
            return back()->withErrors(['vehicle_registration' => Traductions::t('msg.planif_ordre_non_attente', 'Seul un ordre en attente peut être affecté.')]);
        }

        $vehicle = Vehicle::find($data['vehicle_registration']);
        $driver = Driver::find($data['driver_id']);

        if (! $vehicle->is_available) {
            return back()->withErrors(['vehicle_registration' => Traductions::t('msg.planif_vehicule_indisponible', 'Ce véhicule n\'est plus disponible.')]);
        }

        if (! $driver->is_available || ! $driver->user?->is_active) {
            return back()->withErrors(['driver_id' => Traductions::t('msg.planif_chauffeur_indisponible', 'Ce chauffeur n\'est plus disponible.')]);
        }

        // Sans date d'enlevement, l'expedition part « des que possible » :
        // l'affecter, c'est la planifier aujourd'hui. Elle faisait tomber
        // l'affectation en erreur 500.
        if ($transportOrder->pickup_date === null) {
            $transportOrder->pickup_date = now();
        }

        $debut = $transportOrder->pickup_date->copy()->startOfDay();
        $fin = $debut->copy()->addDays(TempsDeConduite::journees($transportOrder->distance_km) - 1);

        if ($empechements = $driver->empechements($fin)) {
            return back()->withErrors([
                'driver_id' => Traductions::t('msg.planif_chauffeur_empeche', 'Ce chauffeur ne peut pas prendre la route : :motifs.', ['motifs' => implode(', ', $empechements)]),
            ]);
        }

        if ($vehicle->capacity_tonnes * 1000 < $transportOrder->weight) {
            return back()->withErrors([
                'vehicle_registration' => Traductions::t('msg.planif_capacite', 'Capacité insuffisante : :capacite t pour :poids kg.', [
                    'capacite' => Formats::nombre($vehicle->capacity_tonnes, 1),
                    'poids' => Formats::nombre($transportOrder->weight),
                ]),
            ]);
        }

        if ($transportOrder->needs_tail_lift && ! $vehicle->has_tail_lift) {
            return back()->withErrors([
                'vehicle_registration' => Traductions::t('msg.planif_hayon', 'Cette expédition demande un hayon élévateur : ce véhicule n\'en a pas.'),
            ]);
        }

        if ($transportOrder->is_hazardous && ! $driver->adr_certified) {
            return back()->withErrors(['driver_id' => Traductions::t('msg.planif_adr', 'Marchandise dangereuse : ce chauffeur n\'a pas la certification ADR.')]);
        }

        if ($motif = $driver->motifPermis($vehicle)) {
            return back()->withErrors([
                'driver_id' => Traductions::t('msg.planif_permis', 'Permis inadapté : :motif.', ['motif' => $motif]),
            ]);
        }

        // Une mission de plusieurs jours occupe chauffeur et camion sur
        // toute sa duree : on compare des intervalles, pas le seul jour
        // d'enlevement.
        $conflitChauffeur = $this->chevauche(
            TransportOrder::where('driver_id', $driver->id)
                ->where('vehicle_registration', '!=', $vehicle->registration),
            $debut, $fin, $transportOrder->id,
        );

        if ($conflitChauffeur) {
            return back()->withErrors([
                'driver_id' => Traductions::t('msg.planif_chauffeur_occupe', 'Ce chauffeur a déjà une mission ce jour-là avec un autre camion.'),
            ]);
        }

        $conflitCamion = $this->chevauche(
            TransportOrder::where('vehicle_registration', $vehicle->registration)
                ->where('driver_id', '!=', $driver->id),
            $debut, $fin, $transportOrder->id,
        );

        if ($conflitCamion) {
            return back()->withErrors([
                'vehicle_registration' => Traductions::t('msg.planif_camion_occupe', 'Ce camion est déjà affecté à un autre chauffeur ce jour-là.'),
            ]);
        }

        $conduite = TempsDeConduite::empechements(
            $driver->id,
            $transportOrder->distance_km,
            $transportOrder->pickup_date,
            $transportOrder->id,
        );

        if ($conduite !== []) {
            return back()->withErrors([
                'driver_id' => Traductions::t('msg.planif_temps_conduite', 'Temps de conduite : :motifs.', ['motifs' => implode(' ; ', $conduite)]),
            ]);
        }

        $transportOrder->update([
            'vehicle_registration' => $vehicle->registration,
            'driver_id' => $driver->id,
            'assigned_at' => now(),
            'pickup_date' => $transportOrder->pickup_date,
            'status' => 'ASSIGNED',
        ]);

        ActivityLog::record(
            'order.assigned',
            'Affectation de l\'ordre '.$transportOrder->tracking_number.' au véhicule '.$vehicle->registration,
            $transportOrder,
            [
                'vehicule' => $vehicle->registration.' '.$vehicle->brand.' '.$vehicle->model,
                'chauffeur_id' => $driver->id,
                'statut' => 'PENDING → ASSIGNED',
            ],
        );

        return back()->with('success', Traductions::t('msg.planif_ordre_affecte', 'Ordre :numero affecté au véhicule :vehicule.', [
            'numero' => $transportOrder->tracking_number,
            'vehicule' => $vehicle->registration,
        ]));
    }

    /**
     * Une mission deja engagee occupe-t-elle un des jours [debut, fin] ?
     */
    private function chevauche($requete, CarbonInterface $debut, CarbonInterface $fin, int $exclu): bool
    {
        return $requete->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->where('id', '!=', $exclu)
            ->whereNotNull('pickup_date')
            ->where('pickup_date', '<=', $fin->copy()->endOfDay())
            ->where('pickup_date', '>=', $debut->copy()->subDays(7))
            ->get(['pickup_date', 'distance_km'])
            ->contains(function (TransportOrder $mission) use ($debut) {
                $finMission = $mission->pickup_date->copy()->startOfDay()
                    ->addDays(TempsDeConduite::journees($mission->distance_km) - 1);

                return $finMission->gte($debut);
            });
    }

    public function suiviDirect(TransportOrder $transportOrder): RedirectResponse
    {
        $ouvert = ! $transportOrder->suivi_direct;

        // Suivre un camion n'a de sens que pour une mission affectee ou en
        // route. On peut toujours le couper.
        if ($ouvert && ! in_array($transportOrder->status, ['ASSIGNED', 'IN_PROGRESS'], true)) {
            return back()->with('error', Traductions::t('msg.planif_suivi_impossible', 'Le suivi de position ne s\'ouvre que pour une mission affectée ou en cours.'));
        }

        $transportOrder->update(['suivi_direct' => $ouvert]);

        ActivityLog::record(
            $ouvert ? 'order.tracking_opened' : 'order.tracking_closed',
            ($ouvert ? 'Suivi de position ouvert' : 'Suivi de position fermé')
                .' pour '.$transportOrder->tracking_number,
            $transportOrder,
            ['chauffeur_id' => $transportOrder->driver_id],
        );

        return back()->with('success', $ouvert
            ? Traductions::t('msg.planif_suivi_active', 'Suivi de position activé pour cette mission. Le chauffeur en est averti sur son écran.')
            : Traductions::t('msg.planif_suivi_desactive', 'Suivi de position désactivé.'));
    }

    public function updateStatus(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(TransportOrder::STATUTS)],
        ]);

        $autorises = self::TRANSITIONS[$transportOrder->status] ?? [];

        if (! in_array($data['status'], $autorises, true)) {
            return back()->withErrors(['status' => Traductions::t('msg.planif_transition_impossible', 'Transition impossible depuis le statut :statut.', ['statut' => $transportOrder->status])]);
        }

        $ancien = $transportOrder->status;
        $champs = ['status' => $data['status']];

        if ($data['status'] === 'DELIVERED') {
            $champs['actual_delivery_date'] = now();
        }

        // Une mission terminee ne se suit plus, et une annulation garde la
        // trace de son auteur, comme celle que fait le client.
        if (in_array($data['status'], ['DELIVERED', 'CANCELLED'], true)) {
            $champs['suivi_direct'] = false;
        }

        if ($data['status'] === 'CANCELLED') {
            $champs['cancelled_at'] = now();
            $champs['cancelled_by'] = $request->user()->id;
        }

        $transportOrder->update($champs);

        ActivityLog::record(
            'order.status_changed',
            'Ordre '.$transportOrder->tracking_number.' : statut '.$ancien.' → '.$data['status'],
            $transportOrder,
            ['avant' => $ancien, 'apres' => $data['status']],
        );

        return back()->with('success', Traductions::t('msg.planif_statut_mis_a_jour', 'Ordre :numero : statut mis à jour.', ['numero' => $transportOrder->tracking_number]));
    }

    public function desaffecter(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        $donnees = $request->validate([
            'motif' => 'required|string|min:5|max:200',
        ], [
            'motif.required' => Traductions::t('msg.planif_motif_requis', 'Indiquez le motif de la désaffectation.'),
            'motif.min' => Traductions::t('msg.planif_motif_court', 'Le motif doit faire au moins 5 caractères.'),
        ]);

        if (! in_array($transportOrder->status, ['ASSIGNED', 'IN_PROGRESS'], true)) {
            return back()->withErrors(['motif' => Traductions::t('msg.planif_desaffectation_affectee', 'Seule une mission affectée ou en cours peut être désaffectée.')]);
        }

        $ancien = $transportOrder->status;
        $camion = $transportOrder->vehicle_registration;
        $chauffeur = $transportOrder->driver_id;

        $transportOrder->update([
            'status' => 'PENDING',
            'vehicle_registration' => null,
            'driver_id' => null,
            'assigned_at' => null,
            'picked_up_at' => null,
            'suivi_direct' => false,
        ]);

        ActivityLog::record(
            'order.unassigned',
            'Ordre '.$transportOrder->tracking_number.' désaffecté : '.$donnees['motif'],
            $transportOrder,
            [
                'motif' => $donnees['motif'],
                'camion' => $camion,
                'chauffeur_id' => $chauffeur,
                'statut' => $ancien.' → PENDING',
            ],
        );

        return back()->with('success', Traductions::t('msg.planif_ordre_desaffecte', 'Ordre :numero remis en attente d\'affectation.', ['numero' => $transportOrder->tracking_number]));
    }
}
