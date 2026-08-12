<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public const ACTIONS = [
        'order.created' => 'Création d\'ordre',
        'order.assigned' => 'Affectation',
        'order.status_changed' => 'Changement de statut',
        'auth.login' => 'Connexion',
        'auth.logout' => 'Déconnexion',
        'auth.failed' => 'Échec de connexion',
        'auth.lockout' => 'Blocage temporaire',
        'client.registered' => 'Inscription entreprise',
        'client.validated' => 'Validation entreprise',
        'client.rejected' => 'Refus entreprise',
        'quote.handled' => 'Traitement de devis',
    ];

    public function index(Request $request): Response
    {
        $query = ActivityLog::with('user:id,first_name,last_name,email,role')->latest('created_at');

        if ($request->filled('utilisateur')) {
            $recherche = $request->string('utilisateur')->toString();
            $query->whereHas('user', function ($q) use ($recherche) {
                $q->where('email', 'ilike', '%'.$recherche.'%')
                    ->orWhere('first_name', 'ilike', '%'.$recherche.'%')
                    ->orWhere('last_name', 'ilike', '%'.$recherche.'%');
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('ip')) {
            $query->where('ip_address', 'ilike', '%'.$request->query('ip').'%');
        }

        if ($request->filled('du')) {
            $query->whereDate('created_at', '>=', $request->query('du'));
        }

        if ($request->filled('au')) {
            $query->whereDate('created_at', '<=', $request->query('au'));
        }

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $query->paginate(30)->withQueryString(),
            'actions' => self::ACTIONS,
            'filtres' => $request->only(['utilisateur', 'action', 'ip', 'du', 'au']),
            'stats' => [
                'total' => ActivityLog::count(),
                'aujourdhui' => ActivityLog::whereDate('created_at', today())->count(),
                'echecs' => ActivityLog::where('action', 'auth.failed')->count(),
            ],
        ]);
    }
}
