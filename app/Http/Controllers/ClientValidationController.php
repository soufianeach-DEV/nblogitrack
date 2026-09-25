<?php

namespace App\Http\Controllers;

use App\Mail\CompteActive;
use App\Mail\InscriptionRefusee;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\User;
use App\Support\Pays;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ClientValidationController extends Controller
{
    public function index(Request $request): Response
    {
        $etat = $request->query('etat', 'attente');

        $query = Client::with([
            'user:id,first_name,last_name,email',
            'contacts',
            'validator:id,first_name,last_name',
        ]);

        match ($etat) {
            'tout' => $query,
            'validees' => $query->where('is_validated', true),
            'refusees' => $query->where('is_validated', false)->whereNotNull('rejection_reason'),
            default => $query->where('is_validated', false)->whereNull('rejection_reason'),
        };

        $filtres = [
            'q' => trim((string) $request->query('q', '')),
            'pays' => trim((string) $request->query('pays', '')),
            'secteur' => trim((string) $request->query('secteur', '')),
        ];

        if ($filtres['q'] !== '') {
            $query->where(function ($q) use ($filtres) {
                foreach (['company_name', 'vat_number', 'peppol_id', 'city'] as $colonne) {
                    $q->orWhere($colonne, 'ilike', '%'.$filtres['q'].'%');
                }
            });
        }

        if ($filtres['pays'] !== '') {
            $query->where('country', Pays::nomFrancais($filtres['pays']));
        }

        if ($filtres['secteur'] !== '') {
            $query->where('business_sector', Traductions::vocabulaireEnFrancais('secteur', $filtres['secteur']));
        }

        return Inertia::render('Clients/Index', [
            'clients' => $query->orderBy('company_name')->paginate(10)->withQueryString(),
            'etat' => $etat,
            'filtres' => $filtres,
            'suggestions' => [
                'entreprises' => Client::orderBy('company_name')->distinct()->limit(300)->pluck('company_name'),
                'pays' => Client::whereNotNull('country')->distinct()->orderBy('country')
                    ->pluck('country')->map(fn (string $p) => Pays::localise($p))->sort()->values(),
                'secteurs' => Client::whereNotNull('business_sector')->distinct()->orderBy('business_sector')
                    ->pluck('business_sector')->map(fn (string $s) => Traductions::vocabulaire('secteur', $s))->sort()->values(),
            ],
            'compteurs' => [
                'tout' => Client::count(),
                'attente' => Client::where('is_validated', false)->whereNull('rejection_reason')->count(),
                'validees' => Client::where('is_validated', true)->count(),
                'refusees' => Client::where('is_validated', false)->whereNotNull('rejection_reason')->count(),
            ],
        ]);
    }

    public function approve(Client $client): RedirectResponse
    {
        if ($client->is_validated) {
            return back()->withErrors(['client' => Traductions::t('msg.entreprise_deja_validee', 'Cette entreprise est déjà validée.')]);
        }

        $utilisateur = User::find($client->id);
        $refusPrecedent = $client->rejection_reason;

        DB::transaction(function () use ($client, $utilisateur) {
            $client->update([
                'is_validated' => true,
                'validated_at' => now(),
                'validated_by' => Auth::id(),
                'rejection_reason' => null,
            ]);

            $utilisateur?->update(['is_active' => true]);
        });

        // La validation est deja enregistree. Un courriel qui ne part pas
        // ne doit ni la masquer derriere une erreur cinq cents, ni empecher
        // le journal de la retenir.
        $envoye = $utilisateur === null
            || $this->envoyer(fn () => Mail::to($utilisateur->email)->send(new CompteActive($client, $utilisateur)));

        ActivityLog::record(
            'client.validated',
            ($refusPrecedent ? 'Revalidation' : 'Validation').' de l\'entreprise '.$client->company_name,
            $client,
            array_filter([
                'tva' => $client->vat_number,
                'pays' => $client->country,
                'refus_leve' => $refusPrecedent,
            ]),
        );

        if (! $envoye) {
            return back()->with('error', Traductions::t(
                'msg.entreprise_validee_sans_courriel',
                ':entreprise est validée, mais l\'e-mail d\'activation n\'a pas pu partir : prévenez le contact.',
                ['entreprise' => $client->company_name],
            ));
        }

        return back()->with('success', $refusPrecedent
            ? Traductions::t(
                'msg.entreprise_revalidee',
                ':entreprise est revalidée, le refus est levé et le contact a reçu son e-mail d\'activation.',
                ['entreprise' => $client->company_name],
            )
            : Traductions::t(
                'msg.entreprise_validee',
                ':entreprise est validée, le contact a reçu son e-mail d\'activation.',
                ['entreprise' => $client->company_name],
            ));
    }

    public function reject(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'motif' => 'required|string|min:10|max:255',
        ], [
            'motif.required' => Traductions::t('msg.motif_refus_requis', 'Indiquez le motif : il sera envoyé à l\'entreprise.'),
            'motif.min' => Traductions::t('msg.motif_refus_court', 'Le motif doit être explicite, 10 caractères au minimum.'),
        ]);

        if ($client->is_validated) {
            return back()->withErrors(['motif' => Traductions::t('msg.entreprise_deja_validee', 'Cette entreprise est déjà validée.')]);
        }

        $utilisateur = User::find($client->id);

        DB::transaction(function () use ($client, $data, $utilisateur) {
            $client->update([
                'validated_at' => now(),
                'validated_by' => Auth::id(),
                'rejection_reason' => $data['motif'],
            ]);

            $utilisateur?->update(['is_active' => false]);
        });

        $envoye = $utilisateur === null
            || $this->envoyer(fn () => Mail::to($utilisateur->email)->send(new InscriptionRefusee($client, $utilisateur, $data['motif'])));

        ActivityLog::record(
            'client.rejected',
            'Refus de l\'entreprise '.$client->company_name,
            $client,
            ['motif' => $data['motif']],
        );

        if (! $envoye) {
            return back()->with('error', Traductions::t(
                'msg.demande_refusee_sans_courriel',
                'Demande refusée, mais l\'e-mail n\'a pas pu partir : prévenez :entreprise.',
                ['entreprise' => $client->company_name],
            ));
        }

        return back()->with('success', Traductions::t(
            'msg.demande_refusee',
            'Demande refusée, :entreprise en a été informée.',
            ['entreprise' => $client->company_name],
        ));
    }

    private function envoyer(callable $envoi): bool
    {
        try {
            $envoi();

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
