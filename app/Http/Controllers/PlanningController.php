<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use App\Support\Adresse;
use App\Support\ControleAffectation;
use App\Support\Formats;
use App\Support\FretRetour;
use App\Support\MemoireRequete;
use App\Support\OrderWorkflow;
use App\Support\TempsDeConduite;
use App\Support\Traductions;
use App\Support\TransitionRefusee;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanningController extends Controller
{
    /** Au-dela, une mission « en route » est signalee : livraison oubliee ? */
    private const EN_ROUTE_MAX_JOURS = 7;

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
        // Ecran de lecture : les candidats au fret retour et les missions
        // par camion se calculent une fois pour toute la page.
        MemoireRequete::activer();

        $statut = $request->query('status', 'PENDING');
        if (! array_key_exists($statut, self::TRANSITIONS)) {
            $statut = 'PENDING';
        }

        $priorite = $request->query('priorite');
        if (! in_array($priorite, TransportOrder::PRIORITES, true)) {
            $priorite = null;
        }

        $contrainte = $request->query('contrainte');
        if (! in_array($contrainte, ['adr', 'hayon', 'etranger'], true)) {
            $contrainte = null;
        }

        $colonneContrainte = ['adr' => 'is_hazardous', 'hayon' => 'needs_tail_lift'];
        $filtreContrainte = fn ($requete) => $contrainte === 'etranger'
            ? $requete->where('pickup_country', '!=', 'BE')
            : $requete->where($colonneContrainte[$contrainte], true);

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

        // Les indisponibilites a venir sont chargees une fois : le grisage
        // de chaque mission les consulte sans requete supplementaire.
        $aVenir = fn ($q) => $q->where('au', '>=', today()->toDateString());
        $parcDisponible = Vehicle::with(['indisponibilites' => $aVenir])->where('is_available', true)->orderBy('registration')->get();

        $chauffeursDisponibles = Driver::with(['user:id,first_name,last_name,is_active', 'indisponibilites' => $aVenir])
            ->where('is_available', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->get();

        $orders = TransportOrder::with([
            'client:id,company_name',
            'vehicle',
            'vehicle.indisponibilites' => $aVenir,
            'driver.user:id,first_name,last_name',
            'driver.indisponibilites' => $aVenir,
            'porteuse:id,tracking_number,status,vehicle_registration,driver_id,delivery_lat,delivery_lng,delivery_address,delivery_country,delivered_at,picked_up_at,pickup_date,distance_km,client_id',
        ])
            ->where('status', $statut)
            ->when($priorite, fn ($q) => $q->where('priority', $priorite))
            ->when($contrainte, $filtreContrainte)
            ->when($jour, $duJour)
            ->when($q !== '', $recherche)
            ->orderByRaw("CASE priority WHEN 'URGENT' THEN 1 WHEN 'HIGH' THEN 2 WHEN 'NORMAL' THEN 3 ELSE 4 END")
            ->orderBy('pickup_date')
            ->paginate(15)
            ->withQueryString()
            ->through(function (TransportOrder $o) use ($parcDisponible, $chauffeursDisponibles) {
                $o->setAttribute('conduite', TempsDeConduite::resume($o->distance_km));
                $o->setAttribute('trajet', $o->trajet()->court());

                // Fret retour : les binomes qui livrent a cote a la bonne date,
                // sinon le depart du depot a prevoir.
                if ($o->status === 'PENDING' && FretRetour::concerne($o)) {
                    $candidats = FretRetour::porteuses($o, false, 3);
                    $o->setAttribute('fret_retour', $candidats->map(fn (array $c) => [
                        'numero' => $c['mission']->tracking_number,
                        'vehicle_registration' => $c['mission']->vehicle_registration,
                        'driver_id' => $c['mission']->driver_id,
                        'chauffeur' => trim(($c['mission']->driver?->user?->first_name ?? '').' '.($c['mission']->driver?->user?->last_name ?? '')),
                        'ville' => Adresse::localite((string) $c['mission']->delivery_address),
                        'arrivee' => $c['arrivee']->format('d/m/Y'),
                        'approche_km' => Formats::nombre($c['approche_km'], 1),
                        'reste_t' => Formats::nombre($c['reste_kg'] / 1000, 1),
                    ])->all());

                    if ($candidats->isEmpty()) {
                        $o->setAttribute('positionnement', FretRetour::positionnement($o));
                    }
                }

                // Vendu au tarif fret retour : la mission porteuse est-elle
                // toujours la ? Le prix reste acquis au client, mais il faut
                // alors un autre camion.
                if ($o->pricing_basis === 'BACKHAUL' && in_array($o->status, ['PENDING', 'ASSIGNED'], true)) {
                    $porteuse = $o->porteuse;
                    $o->setAttribute('porteuse_info', [
                        'numero' => $porteuse?->tracking_number,
                        'valide' => $porteuse !== null && in_array($porteuse->status, ['ASSIGNED', 'IN_PROGRESS'], true)
                            && ($o->status !== 'PENDING' || FretRetour::porteuses($o, false, 5)->contains(fn (array $c) => $c['mission']->id === $porteuse->id)),
                    ]);
                }

                if (in_array($o->status, ['ASSIGNED', 'IN_PROGRESS'], true) && $o->delivery_country !== 'BE') {
                    $o->setAttribute('retours_possibles', FretRetour::chargements($o, 3)->map(fn (array $c) => [
                        'numero' => $c['ordre']->tracking_number,
                        'ville' => Adresse::localite((string) $c['ordre']->pickup_address),
                        'date' => $c['ordre']->pickup_date?->format('d/m/Y'),
                        'approche_km' => Formats::nombre($c['approche_km'], 1),
                        'poids' => (float) $c['ordre']->weight,
                    ])->all());
                }
                $o->setAttribute('conduite_heures', TempsDeConduite::heuresDeConduite($o->distance_km));

                // Grisage calcule par le serveur, a la date de la mission :
                // l'ecran et l'affectation disent la meme chose.
                [, , $fin] = ControleAffectation::periode($o);

                if (in_array($o->status, ['PENDING', 'ASSIGNED', 'IN_PROGRESS'], true)) {
                    $o->setAttribute('refus_vehicules', (object) $parcDisponible
                        ->mapWithKeys(fn (Vehicle $v) => [$v->registration => ControleAffectation::refusVehiculeCourt($o, $v, $fin)])
                        ->filter()->all());
                    $o->setAttribute('refus_chauffeurs', (object) $chauffeursDisponibles
                        ->mapWithKeys(fn (Driver $d) => [$d->id => ControleAffectation::refusChauffeurCourt($o, $d, $fin)])
                        ->filter()->all());
                    $o->setAttribute('refus_chauffeurs_pro', (object) $chauffeursDisponibles
                        ->mapWithKeys(fn (Driver $d) => [$d->id => ControleAffectation::refusChauffeurProfessionnel($d, $fin)])
                        ->filter()->all());
                }

                // Une mission deja affectee se recontrole : un document
                // expire ou une fiche corrigee depuis l'affectation la rend
                // non conforme, et le planificateur doit le voir.
                if (in_array($o->status, ['ASSIGNED', 'IN_PROGRESS'], true) && $o->vehicle && $o->driver) {
                    $o->setAttribute('alertes', array_column(ControleAffectation::conformite($o, $o->vehicle, $o->driver, $fin), 'message'));
                }

                // Une mission « en route » depuis plus d'une semaine a sans
                // doute ete livree sans que le chauffeur l'enregistre : elle
                // occupe son camion et son chauffeur jusqu'a ce qu'on la solde.
                $charge = $o->picked_up_at ?? $o->pickup_date;

                if ($o->status === 'IN_PROGRESS' && $charge !== null && $charge->lt(today()->subDays(self::EN_ROUTE_MAX_JOURS))) {
                    $o->setAttribute('en_route_depuis', $charge->format('d/m/Y'));
                }

                return $o;
            });

        $parPriorite = TransportOrder::where('status', $statut)
            ->when($contrainte, $filtreContrainte)
            ->when($jour, $duJour)
            ->when($q !== '', $recherche)
            ->selectRaw('priority, count(*) AS total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $parContrainte = TransportOrder::where('status', $statut)
            ->when($priorite, fn ($q) => $q->where('priority', $priorite))
            ->when($jour, $duJour)
            ->when($q !== '', $recherche)
            ->selectRaw("count(*) filter (where is_hazardous) AS adr, count(*) filter (where needs_tail_lift) AS hayon, count(*) filter (where pickup_country <> 'BE') AS etranger")
            ->first();

        $conduiteSemaine = TransportOrder::whereNotNull('driver_id')
            ->where('status', '!=', 'CANCELLED')
            ->whereBetween('pickup_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->selectRaw('driver_id, sum(distance_km) AS km')
            ->groupBy('driver_id')
            ->pluck('km', 'driver_id');

        return Inertia::render('Planning/Index', [
            'orders' => $orders,
            'vehicles' => $parcDisponible->map(fn (Vehicle $v) => [
                'registration' => $v->registration,
                'brand' => $v->brand,
                'model' => $v->model,
                'vehicle_type' => $v->vehicle_type,
                'capacity_tonnes' => $v->capacity_tonnes,
                'capacity_volume' => $v->capacity_volume,
                'has_tail_lift' => $v->has_tail_lift,
                'adr_equipe' => $v->adr_equipe,
                'permis_requis' => $v->permisRequis(),
            ])->values(),
            'drivers' => $chauffeursDisponibles
                ->map(fn (Driver $d) => [
                    'id' => $d->id,
                    'nom' => $d->user ? $d->user->first_name.' '.$d->user->last_name : 'Chauffeur '.$d->id,
                    'license_type' => $d->license_type,
                    'adr_certified' => $d->adr_certified,
                    'conduite_semaine' => TempsDeConduite::heuresDeConduite((int) ($conduiteSemaine[$d->id] ?? 0)),
                    // Une retraite prevue passee sans depart enregistre
                    // n'interdit pas de conduire, mais la fiche est a revoir.
                    'retraite_passee' => $d->left_on === null && $d->retirement_planned_on?->lt(today())
                        ? $d->retirement_planned_on->format('d/m/Y')
                        : null,
                ])
                ->sortBy('nom')
                ->values(),
            // Ce que couvre chaque permis : l'ecran compare le permis du
            // chauffeur a celui qu'exige le camion, sans recopier la regle.
            'couverture' => Driver::COUVERTURE,
            'statut' => $statut,
            'priorite' => $priorite,
            'contrainte' => $contrainte,
            'priorites' => collect(TransportOrder::PRIORITES)
                ->map(fn (string $p) => ['valeur' => $p, 'nombre' => (int) ($parPriorite[$p] ?? 0)])
                ->all(),
            'contraintes' => [
                ['valeur' => 'adr', 'nombre' => (int) $parContrainte->adr],
                ['valeur' => 'hayon', 'nombre' => (int) $parContrainte->hayon],
                ['valeur' => 'etranger', 'nombre' => (int) $parContrainte->etranger],
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

        $avant = [
            'statut' => $transportOrder->status,
            'camion' => $transportOrder->vehicle_registration,
            'chauffeur_id' => $transportOrder->driver_id,
            'enlevement' => $transportOrder->pickup_date?->format('Y-m-d H:i'),
        ];

        // Deux planificateurs qui reservent au meme instant le meme chauffeur
        // ou le meme camion : le verrou sur leurs fiches fait passer le
        // second apres le premier, qui voit alors la mission deja posee.
        $resultat = DB::transaction(function () use ($transportOrder, $data, $reaffectation) {
            $vehicle = Vehicle::whereKey($data['vehicle_registration'])->lockForUpdate()->first();
            $driver = Driver::whereKey($data['driver_id'])->lockForUpdate()->first();

            if (! $vehicle->is_available) {
                return ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_vehicule_indisponible', 'Ce véhicule n\'est plus disponible.')];
            }

            if (! $driver->is_available || ! $driver->user?->is_active) {
                return ['champ' => 'driver_id', 'message' => Traductions::t('msg.planif_chauffeur_indisponible', 'Ce chauffeur n\'est plus disponible.')];
            }

            // Reaffecter au meme camion et au meme chauffeur ne change rien.
            if ($reaffectation
                && $transportOrder->vehicle_registration === $vehicle->registration
                && $transportOrder->driver_id === $driver->id) {
                return ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_reaffectation_identique', 'Cette mission a déjà ce camion et ce chauffeur.')];
            }

            // Sans date d'enlevement, ou avec une date deja passee (mission
            // en attente, ou affectee mais jamais partie), l'expedition part
            // aujourd'hui : les documents se verifient a partir d'aujourd'hui,
            // pas a une date revolue.
            $enlevement = $transportOrder->pickup_date;

            if ($transportOrder->status !== 'IN_PROGRESS' && ($enlevement === null || $enlevement->lt(today()))) {
                $enlevement = now();
                $transportOrder->pickup_date = $enlevement;
            }

            // Enlevement a l'etranger : les kilometres a vide depuis la
            // derniere livraison du camion, ou depuis le depot, comptent
            // dans l'occupation et le temps de conduite.
            if ($transportOrder->status !== 'IN_PROGRESS') {
                $transportOrder->approche_km = FretRetour::approchePour($transportOrder, $vehicle);
            }

            if ($refus = ControleAffectation::refus($transportOrder, $vehicle, $driver)) {
                return $refus[0];
            }

            try {
                $reaffectation
                    ? OrderWorkflow::reaffecter($transportOrder, $vehicle, $driver, $transportOrder->status === 'ASSIGNED' ? $enlevement : null)
                    : OrderWorkflow::affecter($transportOrder, $vehicle, $driver, $enlevement);
                if ($transportOrder->status === 'ASSIGNED' && $transportOrder->pickup_country !== 'BE') {
                    TransportOrder::whereKey($transportOrder->id)->update(['approche_km' => FretRetour::approchePour($transportOrder, $vehicle)]);
                }
            } catch (TransitionRefusee $e) {
                // L'ordre a change d'etat entre-temps : sa carte change
                // d'onglet au retour, le message doit rester visible.
                return ['bandeau' => $e->getMessage()];
            }

            return ['vehicle' => $vehicle, 'driver' => $driver];
        });

        if (isset($resultat['bandeau'])) {
            return back()->with('error', $resultat['bandeau']);
        }

        if (isset($resultat['champ'])) {
            return back()->withErrors([$resultat['champ'] => $resultat['message']]);
        }

        ['vehicle' => $vehicle, 'driver' => $driver] = $resultat;

        if ($reaffectation) {
            // Une mission en retard repart aujourd'hui avec son nouveau
            // binome : le journal garde la date d'enlevement qu'elle avait.
            $enlevement = $transportOrder->pickup_date?->format('Y-m-d H:i');
            $reportee = $enlevement !== $avant['enlevement'];

            ActivityLog::record(
                'order.reassigned',
                'Réaffectation de l\'ordre '.$transportOrder->tracking_number.' au véhicule '.$vehicle->registration.' : '.$data['motif']
                    .($reportee ? ' (enlèvement du '.($avant['enlevement'] ?? '—').' reporté au '.$enlevement.')' : ''),
                $transportOrder,
                [
                    'motif' => $data['motif'],
                    'statut' => $avant['statut'],
                    'ancien_camion' => $avant['camion'],
                    'ancien_chauffeur_id' => $avant['chauffeur_id'],
                    'vehicule' => $vehicle->registration.' '.$vehicle->brand.' '.$vehicle->model,
                    'chauffeur_id' => $driver->id,
                    ...($reportee ? ['ancien_enlevement' => $avant['enlevement'], 'enlevement' => $enlevement] : []),
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
