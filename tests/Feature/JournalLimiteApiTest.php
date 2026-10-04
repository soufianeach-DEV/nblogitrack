<?php

namespace Tests\Feature;

use App\Http\Middleware\JournaliserLimiteApi;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\Client;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Les appels arretes par la limite de debit figurent au journal de l'API,
 * comme les autres refus, sans qu'un flot d'appels puisse le remplir.
 */
class JournalLimiteApiTest extends TestCase
{
    use RefreshDatabase;

    /** La limite reelle (120 appels par minute et par cle), ramenee a quelques appels. */
    private function limiterA(int $appels): void
    {
        RateLimiter::for('api', fn (Request $r) => [
            Limit::perMinute($appels)->by('cle'.strtok((string) $r->bearerToken(), '.')),
            Limit::perMinute(300)->by('ip'.$r->ip()),
        ]);
    }

    /** @return array{0: ApiKey, 1: string} */
    private function cle(): array
    {
        return ApiKey::generer([
            'name' => 'ERP',
            'client_id' => Client::factory()->create()->id,
            'abilities' => ['lecture'],
            'created_by' => User::factory()->administrateur()->create()->id,
        ]);
    }

    public function test_le_premier_refus_de_la_minute_est_inscrit_avec_sa_cle(): void
    {
        $this->limiterA(2);
        [$cle, $jeton] = $this->cle();

        $this->withToken($jeton)->getJson('/api/v1/expeditions')->assertOk();
        $this->withToken($jeton)->getJson('/api/v1/expeditions')->assertOk();
        $this->withToken($jeton)->getJson('/api/v1/expeditions')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('motif', 'limite_depassee');
        $this->withToken($jeton)->getJson('/api/v1/expeditions')->assertStatus(429);

        $refus = ApiRequest::where('status', 429)->sole();
        $this->assertSame($cle->id, $refus->api_key_id);
        $this->assertSame('limite_depassee', $refus->refus);
        $this->assertSame('GET', $refus->method);
        $this->assertSame('api/v1/expeditions', $refus->path);

        // Les deux appels servis restent inscrits par le controle de la cle.
        $this->assertSame(2, ApiRequest::whereNull('refus')->count());
    }

    public function test_le_refus_est_de_nouveau_inscrit_la_minute_suivante(): void
    {
        $this->limiterA(1);
        [, $jeton] = $this->cle();

        $this->withToken($jeton)->getJson('/api/v1/expeditions')->assertOk();
        $this->withToken($jeton)->getJson('/api/v1/expeditions')->assertStatus(429);

        $this->travel(61)->seconds();

        $this->withToken($jeton)->getJson('/api/v1/expeditions')->assertOk();
        $this->withToken($jeton)->getJson('/api/v1/expeditions')->assertStatus(429);

        $this->assertSame(2, ApiRequest::where('refus', 'limite_depassee')->count());
    }

    public function test_une_adresse_qui_change_de_cle_a_chaque_appel_ne_remplit_pas_le_journal(): void
    {
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(1)->by('ip'.$r->ip()));

        foreach (range(1, 12) as $i) {
            $this->withToken('nblt_'.Str::lower(Str::random(7)).'.'.Str::random(40))->getJson('/api/v1/expeditions');
        }

        // Le premier appel passe la limite et le controle de la cle le refuse ;
        // des onze suivants, arretes par la limite, cinq sont inscrits.
        $this->assertSame(1, ApiRequest::where('refus', 'cle_inconnue')->count());
        $this->assertSame(JournaliserLimiteApi::PAR_ADRESSE, ApiRequest::where('refus', 'limite_depassee')->count());
        $this->assertSame(0, ApiRequest::where('refus', 'limite_depassee')->whereNotNull('api_key_id')->count());
    }

    public function test_l_administrateur_voit_le_motif_dans_le_journal(): void
    {
        $this->limiterA(1);
        [, $jeton] = $this->cle();

        $this->withToken($jeton)->getJson('/api/v1/expeditions')->assertOk();
        $this->withToken($jeton)->getJson('/api/v1/expeditions')->assertStatus(429);

        $this->actingAs(User::factory()->administrateur()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('api-keys.index', ['langue' => 'fr', 'etat' => 'refuses']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('journal.data', 1)
                ->where('journal.data.0.refus', 'limite_depassee')
                ->where('journal.data.0.refus_libelle', 'Limite de débit dépassée')
                ->where('statistiques.refus_24h', 1));
    }
}
