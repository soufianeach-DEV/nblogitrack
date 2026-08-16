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

        // L'ecran propose les libelles traduits, la base ne connait que le
        // francais : on repasse par lui avant d'interroger la colonne.
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
            return back()->withErrors(['client' => 'Cette entreprise est déjà validée.']);
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

        if ($utilisateur) {
            Mail::to($utilisateur->email)->send(new CompteActive($client, $utilisateur));
        }

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

        return back()->with('success', $client->company_name.($refusPrecedent
            ? ' est revalidée, le refus est levé et le contact a reçu son e-mail d\'activation.'
            : ' est validée, le contact a reçu son e-mail d\'activation.'));
    }

    public function reject(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'motif' => 'required|string|min:10|max:255',
        ], [
            'motif.required' => 'Indiquez le motif : il sera envoyé à l\'entreprise.',
            'motif.min' => 'Le motif doit être explicite, 10 caractères au minimum.',
        ]);

        if ($client->is_validated) {
            return back()->withErrors(['motif' => 'Cette entreprise est déjà validée.']);
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

        if ($utilisateur) {
            Mail::to($utilisateur->email)->send(new InscriptionRefusee($client, $utilisateur, $data['motif']));
        }

        ActivityLog::record(
            'client.rejected',
            'Refus de l\'entreprise '.$client->company_name,
            $client,
            ['motif' => $data['motif']],
        );

        return back()->with('success', 'Demande refusée, '.$client->company_name.' en a été informée.');
    }
}
