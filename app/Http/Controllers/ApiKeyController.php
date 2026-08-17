<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request): Response
    {
        $filtres = $request->validate([
            'cle' => 'nullable|integer',
            'etat' => 'nullable|in:refuses,servis',
        ]);

        $journal = ApiRequest::with('cle:id,name,prefix')
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => 'required|string|max:80',
            'client_id' => 'nullable|exists:clients,id',
            'permissions' => 'required|array|min:1',
            'permissions.*' => Rule::in(array_keys(ApiKey::PERMISSIONS)),
            'ips' => 'nullable|string|max:500',
            'expire_le' => 'nullable|date|after:today',
        ]);

        $ips = collect(preg_split('/[\s,;]+/', (string) ($donnees['ips'] ?? '')))
            ->filter()->unique()->values();

        $invalides = $ips->reject(fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false);

        if ($invalides->isNotEmpty()) {
            return back()->withErrors([
                'ips' => 'Adresse IP invalide : '.$invalides->implode(', '),
            ])->withInput();
        }

        [$cle, $enClair] = ApiKey::generer([
            'name' => $donnees['nom'],
            'client_id' => $donnees['client_id'] ?? null,
            'abilities' => $donnees['permissions'],
            'allowed_ips' => $ips->isEmpty() ? null : $ips->all(),
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

    public function revoke(Request $request, ApiKey $apiKey): RedirectResponse
    {
        if ($apiKey->revoked_at !== null) {
            return back()->with('error', 'Cette clé est déjà révoquée.');
        }

        $apiKey->update(['revoked_at' => now()]);

        ActivityLog::record(
            'api_key.revoked',
            'Clé API '.$apiKey->prefix.' révoquée',
            $apiKey,
            ['appels_effectues' => $apiKey->requests_count],
        );

        return back()->with('success', 'Clé « '.$apiKey->name.' » révoquée.');
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
