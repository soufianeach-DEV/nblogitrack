<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use App\Support\Adresse;
use App\Support\Formats;
use App\Support\OrderWorkflow;
use App\Support\TempsDeConduite;
use App\Support\Traductions;
use App\Support\TransitionRefusee;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        // Un jour d'enlevement precis, venu du calendrier du tableau de
        // bord. Une date mal formee ou impossible (31 fevrier) est ignoree
        // plutot que de vider la liste.
        $jour = $request->query('jour');
        $date = is_string($jour) ? DateTimeImmutable::createFromFormat('!Y-m-d', $jour) : false;
        if ($date === false || $date->format('Y-m-d') !== $jour) {
            $jour = null;
        }

        $duJour = fn ($requete) => $requete->whereDate('pickup_date', $jour);

        $q = trim((string) $request->query('q', ''));

        $recherche = fn ($requete) => $requete->where(fn ($w) => $w
            ->whereContient('tracking_number', (string) $q)
            ->orWhereContient('pickup_address', (string) $q)
            ->orWhereContient('delivery_address', (string) $q)
            ->orWhereHas('client', fn ($c) => $c->whereContient('company_name', (string) $q)));

        $orders = TransportOrder::with([
            'client:id,company_name',
            'vehicle:registration,brand,model,capacity_tonnes',
            'driver.user:id,first_name,last_name',
        ])
            ->where('status', $statut)
            ->when($priorite, fn ($q) => $q->where('priority', $priorite))
            ->when($contrainte, fn ($q) => $q->where($colonneContrainte[$contrainte], true))
            ->when($jour, $duJour)
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
            ->when($jour, $duJour)
            ->when($q !== '', $recherche)
            ->selectRaw('priority, count(*) AS total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $parContrainte = TransportOrder::where('status', $statut)
            ->when($priorite, fn ($q) => $q->where('priority', $priorite))
            ->when($jour, $duJour)
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
                ->when($jour, $duJour)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'jour' => $jour,
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

        $filtre = (string) $q;

        // La base cherche sans accents ni casse (unaccent, ILIKE) : le tri
        // des localites doit comparer de meme, sinon « liege » trouve les
        // ordres de Liege mais ne propose pas la ville.
        $normaliser = fn (string $texte) => mb_strtolower(Str::ascii($texte));
        $cherche = $normaliser($q);

        $numeros = TransportOrder::where('status', $statut)
            ->whereContient('tracking_number', $filtre)
            ->orderBy('tracking_number')
            ->limit(8)
            ->pluck('tracking_number');

        $entreprises = TransportOrder::where('status', $statut)
            ->whereHas('client', fn ($c) => $c->whereContient('company_name', $filtre))
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
                ->whereContient('pickup_address', $filtre)
                ->orWhereContient('delivery_address', $filtre))
            ->limit(60)
            ->get(['pickup_address', 'delivery_address'])
            ->flatMap(fn (TransportOrder $o) => [
                Adresse::localite($o->pickup_address),
                Adresse::localite($o->delivery_address),
            ])
            ->filter(fn (string $ville) => str_contains($normaliser($ville), $cherche))
            ->unique()
            ->sort()
            ->take(6);

        return $numeros->merge($entreprises)->merge($villes)->unique()->take(12)->values()->all();
    }

    public function assign(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        // Un ordre en attente s'affecte ; un ordre affecte ou en route se
        // reaffecte (autre camion, autre chauffeur) sans revenir en
        // attente : une marchandise chargee ne redevient jamais « en
        // attente », c'est un transbordement.
        $reaffectation = in_array($transportOrder->status, ['ASSIGNED', 'IN_PROGRESS'], true);

        // Un ordre livre ou annule a quitte l'onglet de la planification :
        // une erreur rattachee aux champs de sa carte ne s'afficherait
        // nulle part apres le retour. Le refus passe par le bandeau.
        if ($transportOrder->status !== 'PENDING' && ! $reaffectation) {
            return back()->with('error', Traductions::t('msg.planif_ordre_non_attente', 'Seul un ordre en attente peut être affecté.'));
        }

        // Le formulaire dit ce qu'il voulait faire : un planificateur qui
        // affecte un ordre qu'un collegue vient d'affecter ne se retrouve
        // pas, sans le savoir, a le reaffecter.
        if ($request->has('reaffectation') && $request->boolean('reaffectation') !== $reaffectation) {
            return back()->with('error', Traductions::t('msg.ordre_etat_change', 'Cet ordre vient de changer d\'état : actualisez la page.'));
        }

        $data = $request->validate([
            'vehicle_registration' => 'required|exists:vehicles,registration',
            'driver_id' => 'required|exists:drivers,id',
            'motif' => $reaffectation ? 'required|string|min:5|max:200' : 'nullable',
        ], [
            'motif.required' => Traductions::t('msg.planif_motif_reaffectation', 'Indiquez le motif du changement d\'affectation.'),
            'motif.min' => Traductions::t('msg.planif_motif_court', 'Le motif doit faire au moins 5 caractères.'),
        ]);

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
        // Une date d'enlevement deja passee aussi : sinon les documents du
        // chauffeur et le controle technique etaient verifies a une date
        // revolue, et la mission restait datee dans le passe.
        if ($transportOrder->pickup_date === null
            || ($transportOrder->status === 'PENDING' && $transportOrder->pickup_date->lt(today()))) {
            $transportOrder->pickup_date = now();
        }

        // Reaffecter au meme camion et au meme chauffeur ne change rien.
        if ($reaffectation
            && $transportOrder->vehicle_registration === $vehicle->registration
            && $transportOrder->driver_id === $driver->id) {
            return back()->withErrors(['vehicle_registration' => Traductions::t('msg.planif_reaffectation_identique', 'Cette mission a déjà ce camion et ce chauffeur.')]);
        }

        // En route, le nouveau camion et le nouveau chauffeur sont mobilises
        // a partir d'aujourd'hui.
        $depart = $transportOrder->status === 'IN_PROGRESS' ? now() : $transportOrder->pickup_date;
        $debut = $depart->copy()->startOfDay();
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

        // Le tableau de bord signalait un controle technique echu, mais
        // rien n'empechait d'affecter le camion : il doit etre valable
        // jusqu'au dernier jour de la mission.
        if ($vehicle->inspection_valid_until !== null && $vehicle->inspection_valid_until->lt($fin)) {
            return back()->withErrors([
                'vehicle_registration' => Traductions::t('msg.planif_controle_technique', 'Le contrôle technique de ce véhicule expire le :date, avant la fin de la mission.', [
                    'date' => $vehicle->inspection_valid_until->format('d/m/Y'),
                ]),
            ]);
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

        // Le meme binome peut charger plusieurs envois le meme jour
        // (groupage), mais pas partir sur une autre mission pendant qu'il
        // roule encore : le deuxieme jour d'un Bruxelles-Lyon, il n'est pas
        // a Namur.
        $conflitBinome = $this->chevauche(
            TransportOrder::where('driver_id', $driver->id)
                ->where('vehicle_registration', $vehicle->registration)
                ->whereDate('pickup_date', '!=', $debut->toDateString()),
            $debut, $fin, $transportOrder->id,
        );

        if ($conflitBinome) {
            return back()->withErrors([
                'driver_id' => Traductions::t('msg.planif_binome_en_route', 'Ce camion et ce chauffeur sont encore en route ce jour-là pour une autre mission.'),
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
            $depart,
            $transportOrder->id,
        );

        if ($conduite !== []) {
            return back()->withErrors([
                'driver_id' => Traductions::t('msg.planif_temps_conduite', 'Temps de conduite : :motifs.', ['motifs' => implode(' ; ', $conduite)]),
            ]);
        }

        $avant = [
            'statut' => $transportOrder->status,
            'camion' => $transportOrder->vehicle_registration,
            'chauffeur_id' => $transportOrder->driver_id,
        ];

        try {
            $reaffectation
                ? OrderWorkflow::reaffecter($transportOrder, $vehicle, $driver)
                : OrderWorkflow::affecter($transportOrder, $vehicle, $driver, $transportOrder->pickup_date);
        } catch (TransitionRefusee $e) {
            // L'ordre a change d'etat entre-temps : sa carte change
            // d'onglet au retour, le message doit rester visible.
            return back()->with('error', $e->getMessage());
        }

        if ($reaffectation) {
            ActivityLog::record(
                'order.reassigned',
                'Réaffectation de l\'ordre '.$transportOrder->tracking_number.' au véhicule '.$vehicle->registration.' : '.$data['motif'],
                $transportOrder,
                [
                    'motif' => $data['motif'],
                    'statut' => $avant['statut'],
                    'ancien_camion' => $avant['camion'],
                    'ancien_chauffeur_id' => $avant['chauffeur_id'],
                    'vehicule' => $vehicle->registration.' '.$vehicle->brand.' '.$vehicle->model,
                    'chauffeur_id' => $driver->id,
                ],
            );

            return back()->with('success', Traductions::t('msg.planif_ordre_reaffecte', 'Ordre :numero réaffecté au véhicule :vehicule.', [
                'numero' => $transportOrder->tracking_number,
                'vehicule' => $vehicle->registration,
            ]));
        }

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

    private static function libelleStatut(string $statut): string
    {
        return match ($statut) {
            'PENDING' => Traductions::t('statut.en_attente', 'En attente'),
            'ASSIGNED' => Traductions::t('statut.affecte', 'Affecté'),
            'IN_PROGRESS' => Traductions::t('statut.en_cours', 'En cours'),
            'DELIVERED' => Traductions::t('statut.livre', 'Livré'),
            'CANCELLED' => Traductions::t('statut.annule', 'Annulé'),
            default => $statut,
        };
    }

    public function updateStatus(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(TransportOrder::STATUTS)],
        ]);

        $autorises = self::TRANSITIONS[$transportOrder->status] ?? [];

        if (! in_array($data['status'], $autorises, true)) {
            return back()->with('error', Traductions::t('msg.planif_transition_impossible', 'Transition impossible depuis le statut :statut.', ['statut' => self::libelleStatut($transportOrder->status)]));
        }

        $ancien = $transportOrder->status;

        try {
            $data['status'] === 'DELIVERED'
                ? OrderWorkflow::livrer($transportOrder)
                : OrderWorkflow::annuler($transportOrder, OrderStatus::from($ancien), $request->user()->id);
        } catch (TransitionRefusee $e) {
            return back()->with('error', $e->getMessage());
        }

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

        // Une marchandise chargee ne revient pas en attente : elle se
        // reaffecte a un autre camion (transbordement).
        if ($transportOrder->status === 'IN_PROGRESS') {
            return back()->with('error', Traductions::t('msg.planif_desaffectation_en_route', 'La marchandise est chargée : réaffectez la mission à un autre camion ou chauffeur au lieu de la remettre en attente.'));
        }

        if ($transportOrder->status !== 'ASSIGNED') {
            return back()->with('error', Traductions::t('msg.planif_desaffectation_affectee', 'Seule une mission affectée peut être désaffectée.'));
        }

        $ancien = $transportOrder->status;
        $camion = $transportOrder->vehicle_registration;
        $chauffeur = $transportOrder->driver_id;

        try {
            OrderWorkflow::desaffecter($transportOrder);
        } catch (TransitionRefusee $e) {
            return back()->with('error', $e->getMessage());
        }

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
