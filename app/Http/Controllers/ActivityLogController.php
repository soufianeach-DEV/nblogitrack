<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\Traductions;
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
        'order.created_api' => 'Création d\'ordre par API',
        'order.unassigned' => 'Désaffectation',
        'order.reassigned' => 'Réaffectation',
        'order.cancelled_by_client' => 'Annulation par le client',
        'order.tracking_opened' => 'Suivi direct ouvert',
        'order.tracking_closed' => 'Suivi direct fermé',
        'mission.started' => 'Enlèvement confirmé',
        'mission.delivered' => 'Livraison confirmée',
        'invoices.generated' => 'Émission des factures',
        'invoice.sent' => 'Facture envoyée',
        'invoice.send_failed' => 'Échec d\'envoi de facture',
        'invoice.paid' => 'Facture payée',
        'invoice.paid_online' => 'Paiement en ligne',
        'invoice.payment_duplicate' => 'Paiement en double',
        'invoice.payment_rejected' => 'Paiement refusé',
        'purchase.created' => 'Facture d\'achat encodée',
        'purchase.paid' => 'Facture d\'achat payée',
        'staff.created' => 'Compte du personnel créé',
        'staff.enabled' => 'Compte réactivé',
        'staff.disabled' => 'Compte désactivé',
        'staff.reset_link' => 'Lien de mot de passe',
        'driver.updated' => 'Fiche chauffeur modifiée',
        'driver.left' => 'Sortie d\'un chauffeur',
        'driver.notice_sent' => 'Note aux conducteurs envoyée',
        'driver.notice_acknowledged' => 'Prise de connaissance de la note',
        'vehicle.updated' => 'Véhicule modifié',
        'page.created' => 'Page créée',
        'page.updated' => 'Page modifiée',
        'page.published' => 'Page publiée',
        'page.unpublished' => 'Page dépubliée',
        'page.deleted' => 'Page supprimée',
        'document.uploaded' => 'Document ajouté',
        'document.deleted' => 'Document supprimé',
        'translation.updated' => 'Traduction modifiée',
        'api_key.created' => 'Clé d\'API créée',
        'api_key.revoked' => 'Clé d\'API révoquée',
        'processing_record.updated' => 'Registre RGPD modifié',
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

        // Une date mal formee dans l'adresse faisait tomber la page en
        // erreur 500 : elle est simplement ignoree.
        $date = function (?string $valeur): ?string {
            $d = \DateTime::createFromFormat('!Y-m-d', (string) $valeur);

            return $d && $d->format('Y-m-d') === $valeur ? $valeur : null;
        };

        if ($du = $date($request->query('du'))) {
            $query->whereDate('created_at', '>=', $du);
        }

        if ($au = $date($request->query('au'))) {
            $query->whereDate('created_at', '<=', $au);
        }

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $query->paginate(30)->withQueryString(),
            'actions' => collect(self::ACTIONS)
                ->map(fn (string $libelle, string $action) => Traductions::t('journal.action_'.str_replace('.', '_', $action), $libelle))
                ->all(),
            'filtres' => $request->only(['utilisateur', 'action', 'ip', 'du', 'au']),
            'stats' => [
                'total' => ActivityLog::count(),
                'aujourdhui' => ActivityLog::whereDate('created_at', today())->count(),
                'echecs' => ActivityLog::where('action', 'auth.failed')->count(),
            ],
        ]);
    }
}
