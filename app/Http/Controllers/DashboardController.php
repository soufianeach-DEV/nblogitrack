<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Adresse;
use App\Support\JoursFeries;
use App\Support\Traductions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        // Un chauffeur n'est le client de personne : ce tableau de bord ne lui
        // montrerait que des compteurs a zero. Sa page, ce sont ses missions.
        if ($request->user()->isDriver()) {
            return redirect()->route('missions.index');
        }

        $utilisateur = $request->user();
        $personnel = $utilisateur->can('view-all-orders');
        $query = TransportOrder::query();

        if (! $personnel) {
            $query->where('client_id', $utilisateur->id);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'PENDING')->count(),
            'in_progress' => (clone $query)->where('status', 'IN_PROGRESS')->count(),
            'delivered' => (clone $query)->where('status', 'DELIVERED')->count(),
        ];

        // Le cadre s'aligne sur la carte et les alertes : il faut treize
        // lignes pour le remplir.
        $recent = (clone $query)
            ->with('client:id,company_name')
            ->latest('id')
            ->take(13)
            ->get(['id', 'tracking_number', 'client_id', 'delivery_address', 'status', 'created_date']);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'performance' => $this->performance(clone $query, $stats),
            'volume' => $this->volume(clone $query),
            'carte' => $this->carte(clone $query),
            'carteTotal' => (clone $query)->where('status', 'IN_PROGRESS')->count(),
            'alertes' => $this->alertes(clone $query, $personnel),
            'facturation' => $this->facturation($utilisateur, $personnel),
            // Ces trois blocs relevent de l'exploitation, pas du dossier d'un
            // client : ils ne partent que vers le personnel.
            'exploitation' => $personnel ? [
                'entreprises_a_valider' => Client::where('is_validated', false)->whereNull('rejection_reason')->count(),
                'chauffeurs_disponibles' => Driver::where('is_available', true)->count(),
                'chauffeurs_total' => Driver::count(),
                'vehicules_disponibles' => Vehicle::where('is_available', true)->count(),
                'vehicules_total' => Vehicle::count(),
            ] : null,
            'calendrier' => $utilisateur->can('plan-orders') ? $this->calendrier() : null,
            'validations' => $personnel ? $this->validations() : null,
            'conformite' => $personnel ? $this->conformite() : null,
            'journal' => $utilisateur->can('view-logs') ? $this->journal() : null,
        ]);
    }

    /**
     * Ce que la ponctualite raconte, calcule sur les expeditions reellement
     * closes.
     *
     * @param  array<string, int>  $stats
     * @return array<string, mixed>
     */
    private function performance(Builder $query, array $stats): array
    {
        $annulees = (clone $query)->where('status', 'CANCELLED')->count();
        $closes = $stats['delivered'] + $annulees;

        // Le delai est celui qui separe la commande de la livraison reelle.
        // PostgreSQL soustrait deux dates en jours, la moyenne suit.
        $delai = (clone $query)
            ->where('status', 'DELIVERED')
            ->whereNotNull('actual_delivery_date')
            ->selectRaw('avg(actual_delivery_date - created_date) AS jours')
            ->value('jours');

        return [
            'actives' => $stats['pending'] + $stats['in_progress'],
            'annulees' => $annulees,
            'delai_moyen' => $delai === null ? null : round((float) $delai, 1),
            // Parmi les expeditions qui ont abouti d'une facon ou d'une autre,
            // la part qui est arrivee a destination. Rapporter les livraisons
            // au total gonflerait le taux avec des expeditions encore en
            // route, qui n'ont rien prouve.
            'taux_livraison' => $closes === 0 ? null : round($stats['delivered'] / $closes * 100, 1),
        ];
    }

    /**
     * Le volume des sept derniers mois, en nombre d'expeditions et en tonnes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function volume(Builder $query): array
    {
        $debut = now()->startOfMonth()->subMonths(6);

        // Une seule requete couvre les sept mois et les memes sept mois un an
        // plus tot : deux requetes separees risquaient de perdre le
        // cloisonnement au client en chemin.
        $releve = $query
            ->where('created_date', '>=', $debut->copy()->subYear()->toDateString())
            ->where('created_date', '<=', now()->endOfMonth()->toDateString())
            ->selectRaw("to_char(date_trunc('month', created_date), 'YYYY-MM') AS mois")
            ->selectRaw('count(*) AS nombre, coalesce(sum(weight), 0) AS poids')
            ->groupBy('mois')
            ->get()
            ->keyBy('mois');

        $mois = [];

        // Un mois sans expedition doit apparaitre a zero, sinon l'histogramme
        // rapproche deux mois qui ne se suivent pas.
        for ($i = 0; $i < 7; $i++) {
            $curseur = $debut->copy()->addMonths($i);
            $ligne = $releve[$curseur->format('Y-m')] ?? null;
            $precedent = $releve[$curseur->copy()->subYear()->format('Y-m')] ?? null;

            $mois[] = [
                'cle' => $curseur->format('Y-m'),
                'libelle' => $curseur->locale(app()->getLocale())->isoFormat('MMM'),
                'annee' => $curseur->format('Y'),
                'nombre' => (int) ($ligne->nombre ?? 0),
                'tonnes' => round(((float) ($ligne->poids ?? 0)) / 1000, 1),
                'nombre_n1' => (int) ($precedent->nombre ?? 0),
                'tonnes_n1' => round(((float) ($precedent->poids ?? 0)) / 1000, 1),
            ];
        }

        return $mois;
    }

    /**
     * Les expeditions en circulation, pour la carte du tableau de bord.
     *
     * Huit au plus : chacune demande son itineraire routier, et quarante
     * traces superposees ne se liraient de toute facon pas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function carte(Builder $query): array
    {
        return $query
            ->where('status', 'IN_PROGRESS')
            ->whereNotNull('pickup_lat')
            ->whereNotNull('delivery_lat')
            ->latest('id')
            ->take(8)
            ->get(['id', 'tracking_number', 'status', 'pickup_address', 'delivery_address',
                'pickup_lat', 'pickup_lng', 'delivery_lat', 'delivery_lng'])
            ->map(fn (TransportOrder $ordre) => [
                'id' => $ordre->id,
                'numero' => $ordre->tracking_number,
                'statut' => $ordre->status,
                'depart' => Adresse::localite($ordre->pickup_address),
                'arrivee' => Adresse::localite($ordre->delivery_address),
                'coordonnees' => [
                    [(float) $ordre->pickup_lat, (float) $ordre->pickup_lng],
                    [(float) $ordre->delivery_lat, (float) $ordre->delivery_lng],
                ],
            ])
            ->all();
    }

    /**
     * Les alertes du tableau de bord.
     *
     * Le prototype en montre deux, ecrites en dur : une congestion du canal
     * de Suez et une route ferroviaire de substitution. Elles n'ont aucune
     * source, et une alerte inventee ne sert a rien : celles-ci sortent des
     * donnees, et disparaissent quand le probleme est regle.
     *
     * @return array<int, array<string, mixed>>
     */
    private function alertes(Builder $query, bool $personnel): array
    {
        $alertes = [];
        $aujourdhui = now()->toDateString();

        $retard = (clone $query)
            ->whereIn('status', ['PENDING', 'IN_PROGRESS'])
            ->whereNotNull('requested_delivery_date')
            ->where('requested_delivery_date', '<', $aujourdhui)
            ->count();

        if ($retard > 0) {
            $alertes[] = [
                'niveau' => 'grave',
                'titre' => self::phrase($retard, 'alerte.retard_un', ':n expÃ©dition en retard', 'alerte.retard_n', ':n expÃ©ditions en retard'),
                'detail' => Traductions::t('alerte.retard_detail', 'La date de livraison souhaitÃ©e est dÃ©passÃ©e et la marchandise n\'est pas arrivÃ©e.'),
                'lien' => route('transport-orders.index'),
            ];
        }

        if (! $personnel) {
            $attente = (clone $query)->where('status', 'PENDING')->whereNull('vehicle_registration')->count();

            if ($attente > 0) {
                $alertes[] = [
                    'niveau' => 'info',
                    'titre' => self::phrase($attente, 'alerte.attente_un', ':n expÃ©dition en attente d\'affectation', 'alerte.attente_n', ':n expÃ©ditions en attente d\'affectation'),
                    'detail' => Traductions::t('alerte.attente_detail', 'Un vÃ©hicule leur sera affectÃ© par la planification.'),
                    'lien' => route('transport-orders.index'),
                ];
            }

            return $alertes;
        }

        // La suite releve de l'exploitation : elle ne concerne pas un client.
        $adr = TransportOrder::where('is_hazardous', true)
            ->whereIn('status', ['PENDING', 'IN_PROGRESS'])
            ->whereNull('driver_id')
            ->count();

        if ($adr > 0) {
            $alertes[] = [
                'niveau' => 'grave',
                'titre' => self::phrase($adr, 'alerte.adr_un', ':n matiÃ¨re dangereuse sans chauffeur', 'alerte.adr_n', ':n matiÃ¨res dangereuses sans chauffeur'),
                'detail' => Traductions::t('alerte.adr_detail', 'Ces expÃ©ditions exigent un chauffeur certifiÃ© ADR.'),
                'lien' => route('planning.index'),
            ];
        }

        $imminent = TransportOrder::where('status', 'PENDING')
            ->whereNull('vehicle_registration')
            ->whereNotNull('pickup_date')
            ->where('pickup_date', '<=', now()->addDays(3))
            ->count();

        if ($imminent > 0) {
            $alertes[] = [
                'niveau' => 'attention',
                'titre' => self::phrase($imminent, 'alerte.imminent_un', ':n enlÃ¨vement sous trois jours sans vÃ©hicule', 'alerte.imminent_n', ':n enlÃ¨vements sous trois jours sans vÃ©hicule'),
                'detail' => Traductions::t('alerte.imminent_detail', 'Ã€ affecter avant la date d\'enlÃ¨vement prÃ©vue.'),
                'lien' => route('planning.index'),
            ];
        }

        $permis = Driver::whereNotNull('license_expiry')
            ->where('license_expiry', '<=', now()->addDays(60)->toDateString())
            ->count();

        if ($permis > 0) {
            $alertes[] = [
                'niveau' => 'attention',
                'titre' => self::phrase($permis, 'alerte.permis_un', ':n permis arrive Ã  Ã©chÃ©ance', 'alerte.permis_n', ':n permis arrivent Ã  Ã©chÃ©ance'),
                'detail' => Traductions::t('alerte.permis_detail', 'ValiditÃ© infÃ©rieure Ã  soixante jours.'),
                'lien' => route('drivers.index', ['etat' => 'permis']),
            ];
        }

        $visite = Driver::whereNotNull('medical_exam_date')
            ->where('medical_exam_date', '<', now()->subYear()->toDateString())
            ->count();

        if ($visite > 0) {
            $alertes[] = [
                'niveau' => 'attention',
                'titre' => self::phrase($visite, 'alerte.visite_un', ':n visite mÃ©dicale Ã  renouveler', 'alerte.visite_n', ':n visites mÃ©dicales Ã  renouveler'),
                'detail' => Traductions::t('alerte.visite_detail', 'Dernier examen il y a plus d\'un an.'),
                'lien' => route('drivers.index', ['etat' => 'visite']),
            ];
        }

        $controle = Vehicle::whereNotNull('inspection_valid_until')
            ->where('inspection_valid_until', '<', now()->toDateString())
            ->count();

        if ($controle > 0) {
            $alertes[] = [
                'niveau' => 'attention',
                'titre' => self::phrase($controle, 'alerte.controle_un', ':n contrÃ´le technique dÃ©passÃ©', 'alerte.controle_n', ':n contrÃ´les techniques dÃ©passÃ©s'),
                'detail' => Traductions::t('alerte.controle_detail', 'Dernier passage il y a plus d\'un an.'),
                'lien' => route('vehicles.index', ['etat' => 'controle']),
            ];
        }

        return $alertes;
    }

    /**
     * Une phrase au singulier ou au pluriel, traduite.
     *
     * Concatener un Â« s Â» conditionnel ne se traduit pas : le pluriel ne
     * se forme pas de la meme facon d'une langue a l'autre. Chaque forme
     * porte donc sa propre cle.
     */
    private static function phrase(int $nombre, string $cleUn, string $un, string $clePlusieurs, string $plusieurs): string
    {
        return $nombre > 1
            ? Traductions::t($clePlusieurs, $plusieurs, ['n' => $nombre])
            : Traductions::t($cleUn, $un, ['n' => $nombre]);
    }

    /**
     * Ce que le client a paye, ce qu'il doit encore, et ses dernieres
     * factures. Le personnel voit les memes chiffres pour tout le monde.
     *
     * @return array<string, mixed>|null
     */
    private function facturation(User $utilisateur, bool $personnel): ?array
    {
        $requete = Invoice::query();

        if (! $personnel) {
            $requete->where('client_id', $utilisateur->id);
        }

        if ((clone $requete)->doesntExist()) {
            return null;
        }

        $impayees = (clone $requete)->where('status', '!=', 'PAID');

        return [
            'paye' => round((float) (clone $requete)->where('status', 'PAID')->sum('amount_incl_tax'), 2),
            'du' => round((float) (clone $impayees)->sum('amount_incl_tax'), 2),
            'en_retard' => (clone $impayees)->where('due_on', '<', now()->toDateString())->count(),
            'dernieres' => (clone $requete)
                ->orderByDesc('issued_on')
                ->orderByDesc('id')
                ->take(5)
                ->get()
                ->map(fn (Invoice $facture) => [
                    'id' => $facture->id,
                    'reference' => $facture->reference,
                    'montant' => number_format((float) $facture->amount_incl_tax, 2, ',', ' ').' â‚¬',
                    'etat' => $facture->estEnRetard() ? 'En retard' : Invoice::STATUTS[$facture->status],
                ])
                ->all(),
        ];
    }

    /**
     * Les entreprises qui attendent une decision.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Le plan de charge des quinze prochains jours.
     *
     * Le planificateur ne se demande pas combien d'ordres restent en attente,
     * il se demande s'il peut accepter une livraison jeudi. La reponse tient
     * dans la confrontation, jour par jour, de ce qui est promis et de ce qui
     * est disponible pour le tenir.
     *
     * @return array<string, mixed>
     */
    private function calendrier(): array
    {
        // Quatorze jours et pas quinze : la grille se lit en deux semaines
        // de sept, un jour de plus casserait l'alignement des colonnes.
        $debut = now()->startOfDay();
        $fin = $debut->copy()->addDays(13);

        $enlevements = TransportOrder::whereIn('status', ['PENDING', 'IN_PROGRESS'])
            ->whereBetween('pickup_date', [$debut, $fin->copy()->endOfDay()])
            ->selectRaw('pickup_date::date AS jour, count(*) AS nombre, count(driver_id) AS affectes')
            ->groupBy('jour')->get()->keyBy(fn ($l) => (string) $l->jour);

        $livraisons = TransportOrder::whereIn('status', ['PENDING', 'IN_PROGRESS'])
            ->whereBetween('requested_delivery_date', [$debut->toDateString(), $fin->toDateString()])
            ->selectRaw('requested_delivery_date AS jour, count(*) AS nombre')
            ->groupBy('jour')->get()->keyBy(fn ($l) => (string) $l->jour);

        // La capacite du jour n'est pas le parc entier : un camion deja parti
        // et un chauffeur inapte n'y comptent pas.
        $camions = Vehicle::where('is_available', true)
            ->where(fn ($q) => $q->whereNull('inspection_valid_until')
                ->orWhere('inspection_valid_until', '>=', $debut->toDateString()))
            ->count();

        $conducteurs = Driver::where('is_available', true)->get()
            ->filter(fn (Driver $d) => $d->estApte())
            ->count();

        $capacite = min($camions, $conducteurs);
        $jours = [];

        for ($date = $debut->copy(); $date->lte($fin); $date->addDay()) {
            $cle = $date->toDateString();
            $enlevement = (int) ($enlevements[$cle]->nombre ?? 0);
            $aAffecter = $enlevement - (int) ($enlevements[$cle]->affectes ?? 0);

            $jours[] = [
                'date' => $cle,
                'jour' => $date->locale(app()->getLocale())->isoFormat('ddd'),
                'numero' => $date->day,
                'mois' => $date->locale(app()->getLocale())->isoFormat('MMM'),
                'weekend' => $date->isWeekend(),
                'aujourdhui' => $date->isToday(),
                // Un quai ferme n'est pas un jour creux : le planificateur
                // doit voir pourquoi il ne peut rien y poser.
                'ferie' => JoursFeries::nom($date),
                'chome' => JoursFeries::chome($date),
                'enlevements' => $enlevement,
                'a_affecter' => $aAffecter,
                'livraisons' => (int) ($livraisons[$cle]->nombre ?? 0),
                'sature' => $enlevement > $capacite,
            ];
        }

        return [
            'jours' => $jours,
            'capacite' => $capacite,
            'camions' => $camions,
            'conducteurs' => $conducteurs,
            'a_affecter' => array_sum(array_column($jours, 'a_affecter')),
        ];
    }

    private function validations(): array
    {
        return Client::with('user:id,first_name,last_name,email,phone')
            ->where('is_validated', false)
            ->whereNull('rejection_reason')
            ->orderByDesc('id')
            ->take(4)
            ->get()
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'entreprise' => $client->company_name,
                'pays' => $client->country,
                'secteur' => $client->business_sector,
                'tva' => $client->vat_number,
                'entreprise_numero' => $client->enterprise_number,
                'peppol' => $client->peppol_id,
                'adresse' => $client->billing_address,
                'localite' => trim($client->postal_code.' '.$client->city),
                'delai' => $client->payment_terms,
                'plafond' => $client->credit_limit === null ? null : (float) $client->credit_limit,
                'contact' => trim(($client->user?->first_name ?? '').' '.($client->user?->last_name ?? '')),
                'courriel' => $client->user?->email,
                'telephone' => $client->user?->phone,
            ])
            ->all();
    }

    /**
     * Ce qui n'est plus en regle dans la flotte.
     *
     * Un chauffeur dont la visite medicale a plus d'un an ou dont le permis
     * arrive a echeance ne devrait plus prendre la route, et un vehicule dont
     * le controle technique est depasse non plus. Le tableau de bord les
     * nomme au lieu de se contenter de les compter.
     *
     * @return array<string, mixed>
     */
    private function conformite(): array
    {
        $visite = now()->subYear()->toDateString();
        $echeance = now()->addDays(60)->toDateString();

        // Seuls ceux qui roulent encore appellent une intervention. Un
        // chauffeur deja retire du service ou parti n'est pas un risque :
        // le melanger aux autres noie ce qui demande une action aujourd'hui.
        $chauffeursAlerte = fn ($q) => $q
            ->where('is_available', true)
            ->whereNull('left_on')
            ->where(fn ($e) => $e
                ->where('medical_exam_date', '<', $visite)
                ->orWhereNull('medical_exam_date')
                ->orWhere('license_expiry', '<=', $echeance)
                ->orWhere('cpc_expiry', '<', now()->toDateString())
                ->orWhere('tacho_card_expiry', '<', now()->toDateString()));

        $chauffeurs = Driver::with('user:id,first_name,last_name')
            ->where($chauffeursAlerte)
            ->orderBy('medical_exam_date')
            ->take(5)
            ->get()
            ->map(function (Driver $chauffeur) use ($echeance) {
                // Les empechements du modele disent deja pourquoi il ne peut
                // pas rouler ; le permis qui approche s'y ajoute, parce qu'il
                // se renouvelle avant d'expirer.
                $motifs = $chauffeur->empechements();

                if ($chauffeur->license_expiry !== null
                    && $chauffeur->license_expiry->gte(now()->startOfDay())
                    && $chauffeur->license_expiry->lte($echeance)) {
                    $motifs[] = Traductions::t('empechement.permis_bientot', 'permis expirant le :date',
                        ['date' => $chauffeur->license_expiry->format('d/m/Y')]);
                }

                return [
                    'id' => $chauffeur->id,
                    'nom' => trim(($chauffeur->user?->first_name ?? '').' '.($chauffeur->user?->last_name ?? '')),
                    'motif' => ucfirst(implode(' Â· ', $motifs)),
                    'disponible' => (bool) $chauffeur->is_available,
                ];
            })
            ->all();

        // Meme regle pour le parc : un camion hors service attend deja au
        // garage, il n'y a rien a decider a son sujet. C'est l'echeance de
        // validite qui tranche, pas la date du dernier passage.
        $vehiculesAlerte = fn ($q) => $q
            ->where('is_available', true)
            ->where('inspection_valid_until', '<', now()->toDateString());

        $vehicules = Vehicle::where($vehiculesAlerte)
            ->orderBy('inspection_valid_until')
            ->take(5)
            ->get()
            ->map(fn (Vehicle $vehicule) => [
                'immatriculation' => $vehicule->registration,
                'modele' => trim($vehicule->brand.' '.$vehicule->model),
                'motif' => Traductions::t('empechement.controle', 'ContrÃ´le Ã©chu depuis le :date',
                    ['date' => $vehicule->inspection_valid_until->format('d/m/Y')]),
                'disponible' => (bool) $vehicule->is_available,
            ])
            ->all();

        return [
            'chauffeurs' => $chauffeurs,
            'vehicules' => $vehicules,
            'total_chauffeurs' => Driver::where($chauffeursAlerte)->count(),
            'total_vehicules' => Vehicle::where($vehiculesAlerte)->count(),
        ];
    }

    /**
     * Les dernieres traces du journal d'activite.
     *
     * @return array<int, array<string, string>>
     */
    private function journal(): array
    {
        $lignes = ActivityLog::orderByDesc('id')->take(8)->get();
        $auteurs = User::whereIn('id', $lignes->pluck('user_id')->filter()->unique())
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        return $lignes->map(fn (ActivityLog $ligne) => [
            'action' => $ligne->action,
            'description' => $ligne->description,
            'auteur' => $auteurs[$ligne->user_id] ?? null
                ? $auteurs[$ligne->user_id]->first_name.' '.$auteurs[$ligne->user_id]->last_name
                : 'SystÃ¨me',
            'horodatage' => $ligne->created_at->format('d/m/Y Ã  H\hi'),
        ])->all();
    }
}
