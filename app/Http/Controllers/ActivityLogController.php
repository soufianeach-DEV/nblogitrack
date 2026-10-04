<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\JournalLisible;
use App\Support\Traductions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public const ACTIONS = [
        'profile.exported' => 'Export des données d\'un compte',
        'quote.deleted' => 'Demande de devis effacée',
        'schedule.failed' => 'Échec d\'une tâche planifiée',
        'invoice.peppol_sent' => 'Facture transmise sur Peppol',
        'invoice.peppol_failed' => 'Échec de transmission Peppol',
        'order.created' => 'Création d\'ordre',
        'order.assigned' => 'Affectation',
        'order.assigned_mail_failed' => 'Échec de l\'avis d\'affectation',
        'order.delivered_mail_failed' => 'Échec de l\'avis de livraison',
        'order.incident' => 'Incident signalé par le chauffeur',
        'order.incident_resolved' => 'Reprise de la route après incident',
        'order.vehicle_requested' => 'Autre véhicule demandé par le chauffeur',
        'order.status_changed' => 'Changement de statut',
        'auth.login' => 'Connexion',
        'auth.logout' => 'Déconnexion',
        'auth.failed' => 'Échec de connexion',
        'auth.lockout' => 'Blocage temporaire',
        'client.registered' => 'Inscription entreprise',
        'client.validated' => 'Validation entreprise',
        'client.rejected' => 'Refus entreprise',
        'client.terms_updated' => 'Conditions de paiement modifiées',
        'quote.handled' => 'Traitement de devis',
        'order.created_api' => 'Création d\'ordre par API',
        'order.unassigned' => 'Désaffectation',
        'order.reassigned' => 'Réaffectation',
        'order.charge_added' => 'Supplément ajouté',
        'order.charge_removed' => 'Supplément retiré',
        'invoice.credited' => 'Avoir émis',
        'company.user_invited' => 'Collègue invité',
        'company.user_updated' => 'Droits d\'un collègue modifiés',
        'order.cancelled_by_client' => 'Annulation par le client',
        'order.tracking_opened' => 'Suivi direct ouvert',
        'order.tracking_closed' => 'Suivi direct fermé',
        'mission.started' => 'Enlèvement confirmé',
        'mission.delivered' => 'Livraison confirmée',
        'invoices.generated' => 'Émission des factures',
        'invoices.drafts_sent' => 'Envoi des factures en brouillon',
        'invoice.sent' => 'Facture envoyée',
        'invoice.send_failed' => 'Échec d\'envoi de facture',
        'invoice.auto_failed' => 'Échec de la facture à la livraison',
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
        'vehicle.mileage_corrected' => 'Kilométrage corrigé',
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
        'api_key_request.created' => 'Accès API demandé',
        'api_key_request.granted' => 'Accès API accordé',
        'api_key_request.refused' => 'Accès API refusé',
        'api_key_request.revealed' => 'Clé API affichée au client',
        'processing_record.updated' => 'Registre RGPD modifié',
        'auth.email_verified' => 'Adresse e-mail confirmée',
        'auth.password_changed' => 'Mot de passe changé',
        'auth.password_reset' => 'Mot de passe choisi par lien',
        'client.register_existing_email' => 'Inscription avec une adresse déjà inscrite',
        'profile.email_changed' => 'Adresse e-mail changée',
        'profile.deleted' => 'Compte supprimé',
        'profile.unsubscribed' => 'Désinscription (suppression logique)',
        'quote.ordered' => 'Devis transformé en commande',
        'unavailability.created' => 'Indisponibilité ajoutée',
        'unavailability.removed' => 'Indisponibilité retirée',
    ];

    public function index(Request $request): Response
    {
        // Un filtre transmis comme tableau (?ip[]=1) faisait tomber la page
        // en erreur 500 : seuls les textes sont retenus.
        foreach (['utilisateur', 'action', 'ip', 'du', 'au'] as $filtre) {
            if (! is_string($request->query($filtre))) {
                $request->query->remove($filtre);
            }
        }

        $query = ActivityLog::with('user:id,first_name,last_name,email,role')->latest('created_at');

        if ($request->filled('utilisateur')) {
            $recherche = $request->string('utilisateur')->toString();
            $query->whereHas('user', function ($q) use ($recherche) {
                $q->whereContient('email', (string) $recherche)
                    ->orWhereContient('first_name', (string) $recherche)
                    ->orWhereContient('last_name', (string) $recherche);
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('ip')) {
            $query->whereContient('ip_address', (string) $request->query('ip'));
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
            'logs' => tap($query->paginate(30)->withQueryString(), fn ($page) => $page->getCollection()->transform(fn (ActivityLog $ligne) => [
                ...$ligne->toArray(),
                'description' => JournalLisible::resume($ligne),
                'details' => JournalLisible::details($ligne->properties),
            ])),
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
