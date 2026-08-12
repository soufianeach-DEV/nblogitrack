<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
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

        $recent = (clone $query)
            ->with('client:id,company_name')
            ->latest('id')
            ->take(5)
            ->get(['id', 'tracking_number', 'client_id', 'delivery_address', 'status', 'created_date']);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'performance' => $this->performance(clone $query, $stats),
            'volume' => $this->volume(clone $query),
            'marchandises' => $this->marchandises(clone $query, $stats['total']),
            // Ces trois blocs relevent de l'exploitation, pas du dossier d'un
            // client : ils ne partent que vers le personnel.
            'exploitation' => $personnel ? [
                'entreprises_a_valider' => Client::where('is_validated', false)->whereNull('rejection_reason')->count(),
                'chauffeurs_disponibles' => Driver::where('is_available', true)->count(),
                'chauffeurs_total' => Driver::count(),
                'vehicules_disponibles' => Vehicle::where('is_available', true)->count(),
                'vehicules_total' => Vehicle::count(),
            ] : null,
            'validations' => $personnel ? $this->validations() : null,
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

        // Nombre et tonnage dans la meme requete : deux requetes separees
        // risquaient de perdre le cloisonnement au client en chemin.
        $releve = $query
            ->where('created_date', '>=', $debut->toDateString())
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
            $cle = $curseur->format('Y-m');

            $ligne = $releve[$cle] ?? null;

            $mois[] = [
                'cle' => $cle,
                'libelle' => $curseur->locale('fr')->isoFormat('MMM'),
                'nombre' => (int) ($ligne->nombre ?? 0),
                'tonnes' => round(((float) ($ligne->poids ?? 0)) / 1000, 1),
            ];
        }

        return $mois;
    }

    /**
     * La repartition des marchandises. Le prototype montre une repartition
     * par mode aerien, maritime et routier : l'entreprise ne fait que du
     * transport routier, cette repartition n'aurait qu'une barre. Celle des
     * marchandises dit la meme chose de l'activite, et repose sur une
     * donnee que la base porte vraiment.
     *
     * @return array<int, array<string, mixed>>
     */
    private function marchandises(Builder $query, int $total): array
    {
        if ($total === 0) {
            return [];
        }

        return $query
            ->selectRaw('goods_type, count(*) AS nombre')
            ->groupBy('goods_type')
            ->orderByDesc('nombre')
            ->take(5)
            ->get()
            ->map(fn ($ligne) => [
                'libelle' => $ligne->goods_type,
                'nombre' => (int) $ligne->nombre,
                'part' => round($ligne->nombre / $total * 100, 1),
            ])
            ->all();
    }

    /**
     * Les entreprises qui attendent une decision.
     *
     * @return array<int, array<string, mixed>>
     */
    private function validations(): array
    {
        return Client::where('is_validated', false)
            ->whereNull('rejection_reason')
            ->orderByDesc('id')
            ->take(4)
            ->get(['id', 'company_name', 'country', 'business_sector', 'vat_number'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'entreprise' => $client->company_name,
                'pays' => $client->country,
                'secteur' => $client->business_sector,
                'tva' => $client->vat_number,
            ])
            ->all();
    }

    /**
     * Les dernieres traces du journal d'activite.
     *
     * @return array<int, array<string, string>>
     */
    private function journal(): array
    {
        $lignes = ActivityLog::orderByDesc('id')->take(6)->get();
        $auteurs = User::whereIn('id', $lignes->pluck('user_id')->filter()->unique())
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        return $lignes->map(fn (ActivityLog $ligne) => [
            'action' => $ligne->action,
            'description' => $ligne->description,
            'auteur' => $auteurs[$ligne->user_id] ?? null
                ? $auteurs[$ligne->user_id]->first_name.' '.$auteurs[$ligne->user_id]->last_name
                : 'Système',
            'horodatage' => $ligne->created_at->format('d/m/Y à H\hi'),
        ])->all();
    }
}
