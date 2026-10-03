<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Le journal d'acces se filtre comme la liste des cles : recherche (nom,
 * prefixe, entreprise, IP), entreprise et etat.
 */
class JournalApiFiltresTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> les chemins des appels affiches */
    private function journal(array $filtres): array
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $chemins = [];

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('api-keys.index', ['langue' => 'fr'] + $filtres))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$chemins) {
                $chemins = collect($page->toArray()['props']['journal']['data'])->pluck('chemin')->sort()->values()->all();
            });

        return $chemins;
    }

    public function test_recherche_entreprise_et_etat(): void
    {
        $mertens = Client::factory()->create(['company_name' => 'Matériaux Mertens NV']);
        $autre = Client::factory()->create(['company_name' => 'Pharma Simon SC']);
        $auteur = User::factory()->create(['role' => 'ADMIN']);

        [$cleMertens] = ApiKey::generer(['name' => 'ERP SAP', 'client_id' => $mertens->id, 'abilities' => ['lecture'], 'created_by' => $auteur->id]);
        [$cleSimon] = ApiKey::generer(['name' => 'Boutique', 'client_id' => $autre->id, 'abilities' => ['lecture'], 'created_by' => $auteur->id]);
        [$interne] = ApiKey::generer(['name' => 'Supervision', 'client_id' => null, 'abilities' => ['lecture'], 'created_by' => $auteur->id]);

        $appel = fn (?ApiKey $c, string $chemin, ?string $refus = null, string $ip = '198.51.100.1') => ApiRequest::create([
            'api_key_id' => $c?->id, 'method' => 'GET', 'path' => $chemin, 'status' => $refus ? 401 : 200,
            'ip_address' => $ip, 'duration_ms' => 10, 'refus' => $refus,
        ]);

        $appel($cleMertens, 'api/v1/a');
        $appel($cleMertens, 'api/v1/b', 'revoquee');
        $appel($cleSimon, 'api/v1/c', null, '203.0.113.9');
        $appel($interne, 'api/v1/d');

        $this->assertSame(['api/v1/a', 'api/v1/b'], $this->journal(['q' => 'mertens']));
        $this->assertSame(['api/v1/a', 'api/v1/b'], $this->journal(['q' => substr($cleMertens->prefix, 0, 9)]));
        $this->assertSame(['api/v1/c'], $this->journal(['q' => '203.0.113']));
        $this->assertSame(['api/v1/c'], $this->journal(['entreprise' => (string) $autre->id]));
        $this->assertSame(['api/v1/d'], $this->journal(['entreprise' => 'interne']));
        $this->assertSame(['api/v1/b'], $this->journal(['entreprise' => (string) $mertens->id, 'etat' => 'refuses']));
    }
}
