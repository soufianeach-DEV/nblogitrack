<?php

namespace App\Http\Controllers;

use App\Mail\DemandeAccesApi;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\ApiKeyRequest;
use App\Models\User;
use App\Support\JournalSecurite;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'administrateur d'une entreprise cliente demande un acces a l'API, suit
 * sa demande et affiche une seule fois la cle accordee.
 */
class AccesApiController extends Controller
{
    public function index(Request $request): Response
    {
        $entreprise = $request->user()->client_id;

        return Inertia::render('Entreprise/AccesApi', [
            'demandes' => ApiKeyRequest::where('client_id', $entreprise)
                ->with('demandeur:id,first_name,last_name')
                ->latest('id')->limit(10)->get()
                ->map(fn (ApiKeyRequest $d) => [
                    'id' => $d->id,
                    'statut' => $d->status,
                    'permissions' => $d->abilities,
                    'ips' => $d->allowed_ips ?? [],
                    'message' => $d->message,
                    'motif_refus' => $d->refusal_reason,
                    'demandeur' => trim(($d->demandeur?->first_name ?? '').' '.($d->demandeur?->last_name ?? '')),
                    'demandee_le' => $d->created_at?->format('d/m/Y H:i'),
                    'traitee_le' => $d->handled_at?->format('d/m/Y H:i'),
                    'cle_a_afficher' => $d->cleAAfficher(),
                    'affichee_le' => $d->revealed_at?->format('d/m/Y H:i'),
                ]),
            'cles' => ApiKey::where('client_id', $entreprise)->orderByDesc('id')->get()
                ->map(fn (ApiKey $c) => [
                    'id' => $c->id,
                    'nom' => $c->name,
                    'prefixe' => $c->prefix,
                    'permissions' => $c->abilities,
                    'ips' => $c->allowed_ips ?? [],
                    'active' => $c->estActive(),
                    'expire_le' => $c->expires_at?->format('d/m/Y'),
                    'revoquee_le' => $c->revoked_at?->format('d/m/Y'),
                    'appels' => $c->requests_count,
                    'dernier_usage' => $c->last_used_at?->format('d/m/Y H:i'),
                ]),
            'permissions' => ApiKey::PERMISSIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $moi = $request->user();

        $donnees = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => Rule::in(array_keys(ApiKey::PERMISSIONS)),
            'ips' => 'nullable|string|max:500',
            'message' => 'nullable|string|max:500',
        ]);

        $ips = ApiKeyController::adressesIp($donnees['ips'] ?? null);

        if (is_string($ips)) {
            return back()->withErrors(['ips' => $ips])->withInput();
        }

        // Une seule demande en attente par entreprise : un double clic ou
        // une relance ne cree pas de doublon chez l'administration.
        $demande = DB::transaction(function () use ($moi, $donnees, $ips) {
            DB::table('clients')->where('id', $moi->client_id)->lockForUpdate()->first();

            if (ApiKeyRequest::where('client_id', $moi->client_id)->where('status', ApiKeyRequest::EN_ATTENTE)->exists()) {
                return null;
            }

            return ApiKeyRequest::create([
                'client_id' => $moi->client_id,
                'requested_by' => $moi->id,
                'abilities' => array_values(array_unique($donnees['permissions'])),
                'allowed_ips' => $ips === [] ? null : $ips,
                'message' => filled($donnees['message'] ?? null) ? trim($donnees['message']) : null,
                'status' => ApiKeyRequest::EN_ATTENTE,
            ]);
        });

        if ($demande === null) {
            return back()->with('error', Traductions::t('msg.demande_api_en_cours', 'Une demande d\'accès à l\'API est déjà en cours d\'examen.'));
        }

        ActivityLog::record(
            'api_key_request.created',
            'Demande d\'accès à l\'API de '.$moi->client?->company_name,
            $demande,
            ['permissions' => $demande->abilities, 'ips' => $demande->allowed_ips],
        );

        // Les administrateurs sont prevenus, un envoi par langue, les autres
        // en copie cachee.
        User::where('role', 'ADMIN')->where('is_active', true)
            ->get(['id', 'email', 'locale'])
            ->groupBy(fn (User $u) => $u->locale ?: 'fr')
            ->each(function ($groupe, string $langue) use ($demande) {
                try {
                    Mail::to($groupe->first()->email)
                        ->bcc($groupe->slice(1)->pluck('email')->all())
                        ->send(new DemandeAccesApi($demande, $langue));
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return back()->with('success', Traductions::t('msg.demande_api_envoyee', 'Demande envoyée. Vous serez prévenu par e-mail dès qu\'elle sera traitée.'));
    }

    /**
     * Affiche la cle accordee, une seule fois : elle est ensuite effacee de
     * la demande et seule son empreinte reste en base.
     */
    public function reveler(Request $request, ApiKeyRequest $demande): RedirectResponse
    {
        if ($demande->client_id !== $request->user()->client_id) {
            JournalSecurite::introuvable($request);
        }

        $enClair = DB::transaction(function () use ($demande) {
            $verrouillee = ApiKeyRequest::whereKey($demande->id)->lockForUpdate()->first();

            if (! $verrouillee->cleAAfficher()) {
                return null;
            }

            $valeur = $verrouillee->key_ciphertext;
            $verrouillee->update(['key_ciphertext' => null, 'revealed_at' => now()]);

            return $valeur;
        });

        if ($enClair === null) {
            return back()->with('error', Traductions::t('msg.cle_deja_affichee', 'Cette clé a déjà été affichée. Si vous l\'avez perdue, faites une nouvelle demande.'));
        }

        ActivityLog::record(
            'api_key_request.revealed',
            'Clé API '.$demande->cle?->prefix.' affichée au client',
            $demande,
        );

        return back()->with('cle_en_clair', [
            'valeur' => $enClair,
            'nom' => $demande->cle?->name,
        ]);
    }
}
