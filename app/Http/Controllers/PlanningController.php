<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use App\Support\Adresse;
use App\Support\TempsDeConduite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanningController extends Controller
{
    private const TRANSITIONS = [
        'PENDING' => ['IN_PROGRESS', 'CANCELLED'],
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

        // Une mission dangereuse sur quarante-deux en attente se trouvait au
        // vingt-cinquieme rang, donc en page deux : le badge ne servait que
        // si le planificateur tombait dessus.
        $contrainte = $request->query('contrainte');
        if (! in_array($contrainte, ['adr', 'hayon'], true)) {
            $contrainte = null;
        }

        $colonneContrainte = ['adr' => 'is_hazardous', 'hayon' => 'needs_tail_lift'];

        // Les facettes reduisent par categorie, elles ne retrouvent pas une
        // mission precise. Trente-cinq cartes en attente se parcourent a
        // l'oeil ; cent quarante-six livrees, non.
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

        // Le compte par priorite porte sur le statut affiche : un
        // planificateur veut savoir combien d'urgences restent a traiter
        // dans la colonne ou il travaille, pas dans toute la base. Chaque
        // facette compte sous l'autre filtre, pour ne jamais proposer un
        // bouton qui ne ramene rien.
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

        // La conduite deja engagee cette semaine, en une seule requete. Le
        // plafond reel depend de la semaine de l'enlevement, donc le serveur
        // tranche a l'affectation : ce chiffre-ci informe, il ne decide pas.
        $conduiteSemaine = TransportOrder::whereNotNull('driver_id')
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('pickup_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->selectRaw('driver_id, sum(distance_km) AS km')
            ->groupBy('driver_id')
            ->pluck('km', 'driver_id');

        // Les chauffeurs inaptes restent dans la liste, grises et motives :
        // les faire disparaitre laisserait le planificateur chercher un nom
        // qu'il sait present dans l'entreprise.
        $drivers = Driver::with('user:id,first_name,last_name')
            ->where('is_available', true)
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
            // Les onglets comptent sous la recherche : chercher « Anvers »
            // doit dire combien d'Anvers attendent et combien roulent, sinon
            // le planificateur cherche dans le mauvais onglet sans le savoir.
            'compteurs' => TransportOrder::when($q !== '', $recherche)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'q' => $q,
            'suggestions' => $this->suggestions($q, $statut),
        ]);
    }

    /**
     * Ce que le champ propose pendant la frappe.
     *
     * Numeros de suivi et raisons sociales, pris dans le statut affiche :
     * proposer une expedition livree alors qu'on travaille sur les
     * missions en attente ferait cliquer dans le vide.
     *
     * La liste ne sort qu'a partir de deux caracteres, et s'arrete a
     * douze entrees : au-dela ce n'est plus une aide, c'est une seconde
     * liste a lire.
     *
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

        // On part des expeditions et non des entreprises : seules celles
        // qui ont une mission dans ce statut ont un sens ici.
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

        // Le champ annonce « ville de depart ou d'arrivee » : il doit donc
        // aussi les proposer. La localite s'extrait de l'adresse plutot que
        // de proposer une rue entiere, qu'on ne retape jamais.
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
            return back()->withErrors(['vehicle_registration' => 'Seul un ordre en attente peut être affecté.']);
        }

        $vehicle = Vehicle::find($data['vehicle_registration']);
        $driver = Driver::find($data['driver_id']);

        if (! $vehicle->is_available) {
            return back()->withErrors(['vehicle_registration' => 'Ce véhicule n\'est plus disponible.']);
        }

        if (! $driver->is_available) {
            return back()->withErrors(['driver_id' => 'Ce chauffeur n\'est plus disponible.']);
        }

        // Un titre perime n'est pas une alerte a afficher, c'est un refus :
        // l'entreprise repond de ce qu'elle met sur la route.
        if ($empechements = $driver->empechements()) {
            return back()->withErrors([
                'driver_id' => 'Ce chauffeur ne peut pas prendre la route : '.implode(', ', $empechements).'.',
            ]);
        }

        if ($vehicle->capacity_tonnes * 1000 < $transportOrder->weight) {
            return back()->withErrors([
                'vehicle_registration' => 'Capacité insuffisante : '.$vehicle->capacity_tonnes.' t pour '.$transportOrder->weight.' kg.',
            ]);
        }

        if ($transportOrder->needs_tail_lift && ! $vehicle->has_tail_lift) {
            return back()->withErrors([
                'vehicle_registration' => 'Cette expédition demande un hayon élévateur : ce véhicule n\'en a pas.',
            ]);
        }

        if ($transportOrder->is_hazardous && ! $driver->adr_certified) {
            return back()->withErrors(['driver_id' => 'Marchandise dangereuse : ce chauffeur n\'a pas la certification ADR.']);
        }

        $jour = $transportOrder->pickup_date->toDateString();

        $conflitChauffeur = TransportOrder::where('driver_id', $driver->id)
            ->where('status', 'IN_PROGRESS')
            ->whereDate('pickup_date', $jour)
            ->where('vehicle_registration', '!=', $vehicle->registration)
            ->exists();

        if ($conflitChauffeur) {
            return back()->withErrors([
                'driver_id' => 'Ce chauffeur a déjà une mission ce jour-là avec un autre camion.',
            ]);
        }

        $conflitCamion = TransportOrder::where('vehicle_registration', $vehicle->registration)
            ->where('status', 'IN_PROGRESS')
            ->whereDate('pickup_date', $jour)
            ->where('driver_id', '!=', $driver->id)
            ->exists();

        if ($conflitCamion) {
            return back()->withErrors([
                'vehicle_registration' => 'Ce camion est déjà affecté à un autre chauffeur ce jour-là.',
            ]);
        }

        // Le temps de conduite se refuse comme le reste : c'est un plafond
        // legal, pas un conseil. Le calcul porte sur les missions connues,
        // sans carte tachygraphe, et le refus le dit.
        $conduite = TempsDeConduite::empechements(
            $driver->id,
            $transportOrder->distance_km,
            $transportOrder->pickup_date,
            $transportOrder->id,
        );

        if ($conduite !== []) {
            return back()->withErrors([
                'driver_id' => 'Temps de conduite : '.implode(' ; ', $conduite).'.',
            ]);
        }

        $transportOrder->update([
            'vehicle_registration' => $vehicle->registration,
            'driver_id' => $driver->id,
            'assigned_at' => now(),
            'status' => 'IN_PROGRESS',
        ]);

        ActivityLog::record(
            'order.assigned',
            'Affectation de l\'ordre '.$transportOrder->tracking_number.' au véhicule '.$vehicle->registration,
            $transportOrder,
            [
                'vehicule' => $vehicle->registration.' '.$vehicle->brand.' '.$vehicle->model,
                'chauffeur_id' => $driver->id,
                'statut' => 'PENDING → IN_PROGRESS',
            ],
        );

        return back()->with('success', 'Ordre '.$transportOrder->tracking_number.' affecté au véhicule '.$vehicle->registration.'.');
    }

    /**
     * Ouvre ou ferme le suivi de position pour une mission.
     *
     * C'est une decision, pas un reglage : elle se prend mission par
     * mission, elle se journalise, et elle laisse une trace nominative.
     * Le jour ou un chauffeur demande qui a decide de suivre sa tournee
     * du 15 aout, le journal repond.
     *
     * La fermeture n'efface pas les points deja releves : c'est la purge
     * qui s'en charge, apres la livraison.
     */
    public function suiviDirect(TransportOrder $transportOrder): RedirectResponse
    {
        $ouvert = ! $transportOrder->suivi_direct;

        $transportOrder->update(['suivi_direct' => $ouvert]);

        ActivityLog::record(
            $ouvert ? 'order.tracking_opened' : 'order.tracking_closed',
            ($ouvert ? 'Suivi de position ouvert' : 'Suivi de position fermé')
                .' pour '.$transportOrder->tracking_number,
            $transportOrder,
            ['chauffeur_id' => $transportOrder->driver_id],
        );

        return back()->with('success', $ouvert
            ? 'Suivi de position activé pour cette mission. Le chauffeur en est averti sur son écran.'
            : 'Suivi de position désactivé.');
    }

    public function updateStatus(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:PENDING,IN_PROGRESS,DELIVERED,CANCELLED',
        ]);

        $autorises = self::TRANSITIONS[$transportOrder->status] ?? [];

        if (! in_array($data['status'], $autorises, true)) {
            return back()->withErrors(['status' => 'Transition impossible depuis le statut '.$transportOrder->status.'.']);
        }

        $ancien = $transportOrder->status;
        $champs = ['status' => $data['status']];

        if ($data['status'] === 'DELIVERED') {
            $champs['actual_delivery_date'] = now();
        }

        $transportOrder->update($champs);

        ActivityLog::record(
            'order.status_changed',
            'Ordre '.$transportOrder->tracking_number.' : statut '.$ancien.' → '.$data['status'],
            $transportOrder,
            ['avant' => $ancien, 'apres' => $data['status']],
        );

        return back()->with('success', 'Ordre '.$transportOrder->tracking_number.' : statut mis à jour.');
    }

    /**
     * Rend une mission a la file d'attente : accident, panne, immobilisation.
     *
     * Annuler n'est pas la bonne reponse a un camion arrete : la commande
     * du client reste due. La mission retourne en attente, son numero de
     * suivi et son historique intacts, et se reaffecte avec les memes
     * garde-fous que la premiere fois.
     */
    public function desaffecter(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        $donnees = $request->validate([
            'motif' => 'required|string|min:5|max:200',
        ], [
            'motif.required' => 'Indiquez le motif de la désaffectation.',
            'motif.min' => 'Le motif doit faire au moins 5 caractères.',
        ]);

        if ($transportOrder->status !== 'IN_PROGRESS') {
            return back()->withErrors(['motif' => 'Seule une mission en cours peut être désaffectée.']);
        }

        $camion = $transportOrder->vehicle_registration;
        $chauffeur = $transportOrder->driver_id;

        $transportOrder->update([
            'status' => 'PENDING',
            'vehicle_registration' => null,
            'driver_id' => null,
            'assigned_at' => null,
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
                'statut' => 'IN_PROGRESS → PENDING',
            ],
        );

        return back()->with('success', 'Ordre '.$transportOrder->tracking_number.' remis en attente d\'affectation.');
    }
}
