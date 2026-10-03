<?php

namespace App\Http\Controllers;

use App\Mail\AccesApiTraite;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\ApiKeyRequest;
use App\Models\ApiRequest;
use App\Models\Client;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request): Response
    {
        $filtres = $request->validate([
            'q' => 'nullable|string|max:100',
            'entreprise' => ['nullable', 'regex:/^(interne|\d+)$/'],
            'cle' => 'nullable|integer',
            'etat' => 'nullable|in:refuses,servis',
        ]);

        // Memes filtres que la liste des cles : nom, prefixe ou entreprise de
        // la cle, et aussi l'adresse IP ou le chemin de l'appel.
        $terme = trim((string) ($filtres['q'] ?? ''));

        $journal = ApiRequest::with('cle:id,name,prefix')
            ->when($terme !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('cle', fn ($c) => $c->where(fn ($c) => $c
                    ->whereContient('name', $terme)
                    ->orWhereContient('prefix', $terme)
                    ->orWhereHas('client', fn ($e) => $e->whereContient('company_name', $terme))))
                ->orWhereContient('ip_address', $terme)
                ->orWhereContient('path', $terme)))
            ->when(($filtres['entreprise'] ?? null) === 'interne', fn ($q) => $q->whereHas('cle', fn ($c) => $c->whereNull('client_id')))
            ->when(ctype_digit((string) ($filtres['entreprise'] ?? '')), fn ($q) => $q->whereHas('cle', fn ($c) => $c->where('client_id', (int) $filtres['entreprise'])))
            ->when($filtres['cle'] ?? null, fn ($q, $id) => $q->where('api_key_id', $id))
            ->when(($filtres['etat'] ?? null) === 'refuses', fn ($q) => $q->whereNotNull('refus'))
            ->when(($filtres['etat'] ?? null) === 'servis', fn ($q) => $q->whereNull('refus'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ApiRequest $a) => [
                'id' => $a->id,
                'cle' => $a->cle?->name,
                'prefixe' => $a->cle?->prefix,
                'methode' => $a->method,
                'chemin' => $a->path,
                'statut' => $a->status,
                'ip' => $a->ip_address,
                'duree' => $a->duration_ms,
                'refus' => $a->refus,
                'refus_libelle' => $a->refus ? (ApiRequest::MOTIFS[$a->refus] ?? $a->refus) : null,
                'horodatage' => $a->created_at?->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('Api/Index', [
            'cles' => ApiKey::with('client:id,company_name', 'auteur:id,first_name,last_name')
                ->orderByDesc('id')->get()
                ->map(fn (ApiKey $c) => [
                    'id' => $c->id,
                    'nom' => $c->name,
                    'prefixe' => $c->prefix,
                    'entreprise' => $c->client?->company_name,
                    'entreprise_id' => $c->client_id,
                    'permissions' => $c->abilities,
                    'ips' => $c->allowed_ips ?? [],
                    'expire_le' => $c->expires_at?->format('d/m/Y'),
                    'revoquee_le' => $c->revoked_at?->format('d/m/Y'),
                    'active' => $c->estActive(),
                    'appels' => $c->requests_count,
                    'dernier_usage' => $c->last_used_at?->format('d/m/Y H:i'),
                    'creee_par' => trim(($c->auteur?->first_name ?? '').' '.($c->auteur?->last_name ?? '')),
                    'creee_le' => $c->created_at?->format('d/m/Y'),
                ]),
            'journal' => $journal,
            'filtres' => $filtres,
            'permissions' => ApiKey::PERMISSIONS,
            'entreprises' => Client::orderBy('company_name')->get(['id', 'company_name'])
                ->map(fn (Client $e) => ['valeur' => $e->id, 'libelle' => $e->company_name]),
            'statistiques' => $this->statistiques(),
            'demandes' => ApiKeyRequest::where('status', ApiKeyRequest::EN_ATTENTE)
                ->with('client:id,company_name', 'demandeur:id,first_name,last_name,email')
                ->orderBy('id')->get()
                ->map(fn (ApiKeyRequest $d) => [
                    'id' => $d->id,
                    'entreprise' => $d->client?->company_name,
                    'demandeur' => trim(($d->demandeur?->first_name ?? '').' '.($d->demandeur?->last_name ?? '')),
                    'email' => $d->demandeur?->email,
                    'permissions' => $d->abilities,
                    'ips' => $d->allowed_ips ?? [],
                    'message' => $d->message,
                    'demandee_le' => $d->created_at?->format('d/m/Y H:i'),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => 'required|string|max:80',
            // Une cle interne lit tout mais ne depose rien : sans entreprise
            // a qui rattacher l'expedition, l'ecriture echouait a chaque appel.
            'client_id' => ['nullable', 'integer', 'exists:clients,id', Rule::requiredIf(fn () => in_array('ecriture', (array) $request->input('permissions'), true))],
            'permissions' => 'required|array|min:1',
            'permissions.*' => Rule::in(array_keys(ApiKey::PERMISSIONS)),
            'ips' => 'nullable|string|max:500',
            'expire_le' => 'nullable|date|after:today',
        ], [
            'expire_le.after' => Traductions::t('msg.cle_expiration_passee', 'La date d\'expiration doit être postérieure à aujourd\'hui.'),
            'client_id.required' => Traductions::t('msg.cle_ecriture_sans_entreprise', 'Une clé qui dépose des expéditions doit être rattachée à une entreprise.'),
        ]);

        $ips = self::adressesIp($donnees['ips'] ?? null);

        if (is_string($ips)) {
            return back()->withErrors(['ips' => $ips])->withInput();
        }

        [$cle, $enClair] = ApiKey::generer([
            'name' => $donnees['nom'],
            'client_id' => $donnees['client_id'] ?? null,
            'abilities' => $donnees['permissions'],
            'allowed_ips' => $ips === [] ? null : $ips,
            'expires_at' => $donnees['expire_le'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::record(
            'api_key.created',
            'Clé API '.$cle->prefix.' créée ('.$cle->name.')',
            $cle,
            [
                'permissions' => $cle->abilities,
                'entreprise' => $cle->client_id,
                'ips' => $cle->allowed_ips,
            ],
        );

        return back()->with('cle_en_clair', [
            'valeur' => $enClair,
            'nom' => $cle->name,
        ]);
    }

    /**
     * Accorde la demande d'une entreprise : la cle est generee, mais
     * l'administrateur ne la voit pas. Elle reste chiffree jusqu'a ce que
     * le client l'affiche dans son espace.
     */
    public function accorder(Request $request, ApiKeyRequest $demande): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => 'required|string|max:80',
            'permissions' => 'required|array|min:1',
            'permissions.*' => Rule::in(array_keys(ApiKey::PERMISSIONS)),
            'ips' => 'nullable|string|max:500',
            'expire_le' => 'nullable|date|after:today',
        ], [
            'expire_le.after' => Traductions::t('msg.cle_expiration_passee', 'La date d\'expiration doit être postérieure à aujourd\'hui.'),
        ]);

        $ips = self::adressesIp($donnees['ips'] ?? null);

        if (is_string($ips)) {
            return back()->withErrors(['ips' => $ips])->withInput();
        }

        $cle = DB::transaction(function () use ($request, $demande, $donnees, $ips) {
            $verrouillee = ApiKeyRequest::whereKey($demande->id)->lockForUpdate()->first();

            if (! $verrouillee->enAttente()) {
                return null;
            }

            [$cle, $enClair] = ApiKey::generer([
                'name' => $donnees['nom'],
                'client_id' => $verrouillee->client_id,
                'abilities' => $donnees['permissions'],
                'allowed_ips' => $ips === [] ? null : $ips,
                'expires_at' => $donnees['expire_le'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $verrouillee->update([
                'status' => ApiKeyRequest::ACCORDEE,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
                'api_key_id' => $cle->id,
                'key_ciphertext' => $enClair,
            ]);

            return $cle;
        });

        if ($cle === null) {
            return back()->with('error', Traductions::t('msg.demande_api_deja_traitee', 'Cette demande a déjà été traitée.'));
        }

        ActivityLog::record(
            'api_key_request.granted',
            'Accès API accordé à '.$demande->client?->company_name.' (clé '.$cle->prefix.')',
            $cle,
            ['demande' => $demande->id, 'permissions' => $cle->abilities, 'ips' => $cle->allowed_ips],
        );

        $this->prevenir($demande->fresh());

        return back()->with('success', Traductions::t('msg.demande_api_accordee', 'Accès accordé. Le client affichera lui-même sa clé dans son espace.'));
    }

    public function refuser(Request $request, ApiKeyRequest $demande): RedirectResponse
    {
        $donnees = $request->validate(['motif' => 'required|string|max:300']);

        $refusee = ApiKeyRequest::whereKey($demande->id)
            ->where('status', ApiKeyRequest::EN_ATTENTE)
            ->update([
                'status' => ApiKeyRequest::REFUSEE,
                'refusal_reason' => trim($donnees['motif']),
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ]);

        if ($refusee === 0) {
            return back()->with('error', Traductions::t('msg.demande_api_deja_traitee', 'Cette demande a déjà été traitée.'));
        }

        ActivityLog::record(
            'api_key_request.refused',
            'Accès API refusé à '.$demande->client?->company_name,
            $demande,
            ['motif' => trim($donnees['motif'])],
        );

        $this->prevenir($demande->fresh());

        return back()->with('success', Traductions::t('msg.demande_api_refusee', 'Demande refusée. Le client est prévenu par e-mail.'));
    }

    /**
     * Les adresses IP saisies, separees par des virgules ou des espaces ;
     * le message d'erreur si l'une n'en est pas une.
     *
     * @return list<string>|string
     */
    public static function adressesIp(?string $saisie): array|string
    {
        $ips = collect(preg_split('/[\s,;]+/', (string) $saisie))->filter()->unique()->values();

        $invalides = $ips->reject(fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false);

        return $invalides->isEmpty()
            ? $ips->all()
            : Traductions::t('msg.ip_invalide', 'Adresse IP invalide : :ips', ['ips' => $invalides->implode(', ')]);
    }

    /** Le demandeur, ou a defaut les administrateurs de l'entreprise. */
    private function prevenir(ApiKeyRequest $demande): void
    {
        $destinataires = $demande->demandeur?->is_active
            ? collect([$demande->demandeur])
            : $demande->client->users()->where('is_active', true)->where('company_role', 'ADMIN')->get();

        foreach ($destinataires as $destinataire) {
            try {
                Mail::to($destinataire->email)->send(new AccesApiTraite($demande, $destinataire));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function revoke(Request $request, ApiKey $apiKey): RedirectResponse
    {
        if ($apiKey->revoked_at !== null) {
            return back()->with('error', Traductions::t('msg.cle_deja_revoquee', 'Cette clé est déjà révoquée.'));
        }

        $apiKey->update(['revoked_at' => now()]);

        ActivityLog::record(
            'api_key.revoked',
            'Clé API '.$apiKey->prefix.' révoquée',
            $apiKey,
            ['appels_effectues' => $apiKey->requests_count],
        );

        return back()->with('success', Traductions::t('msg.cle_revoquee', 'Clé « :nom » révoquée.', ['nom' => $apiKey->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function statistiques(): array
    {
        $jour = ApiRequest::where('created_at', '>=', now()->subDay());
        $semaine = ApiRequest::where('created_at', '>=', now()->subWeek());

        return [
            'cles_total' => ApiKey::count(),
            'cles_actives' => ApiKey::whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'appels_24h' => (clone $jour)->count(),
            'refus_24h' => (clone $jour)->whereNotNull('refus')->count(),
            'appels_7j' => (clone $semaine)->count(),
            'refus_7j' => (clone $semaine)->whereNotNull('refus')->count(),
            'duree_moyenne' => (int) round((float) (clone $semaine)->whereNull('refus')->avg('duration_ms')),
            'motifs' => ApiRequest::whereNotNull('refus')
                ->where('created_at', '>=', now()->subWeek())
                ->select('refus', DB::raw('count(*) AS total'))
                ->groupBy('refus')->orderByDesc('total')->get()
                ->map(fn ($m) => [
                    'motif' => $m->refus,
                    'libelle' => ApiRequest::MOTIFS[$m->refus] ?? $m->refus,
                    'total' => $m->total,
                ]),
        ];
    }
}
