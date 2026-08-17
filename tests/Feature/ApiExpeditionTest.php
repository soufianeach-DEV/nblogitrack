<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Client;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiExpeditionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('postal_codes')->insert([
            ['country_code' => 'BE', 'code' => '3500', 'city' => 'Hasselt', 'lat' => 50.9311, 'lng' => 5.3378],
            ['country_code' => 'BE', 'code' => '1000', 'city' => 'Bruxelles', 'lat' => 50.8504, 'lng' => 4.3488],
            ['country_code' => 'FR', 'code' => '75001', 'city' => 'Paris', 'lat' => 48.8534, 'lng' => 2.3488],
        ]);

        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 84000]],
            ]),
            '*' => Http::response([], 200),
        ]);
    }

    /** @return array{0: ApiKey, 1: string} */
    private function cle(?int $entreprise, array $droits = ['lecture', 'ecriture']): array
    {
        return ApiKey::generer([
            'name' => 'Cle d\'essai',
            'client_id' => $entreprise,
            'abilities' => $droits,
            'created_by' => User::factory()->administrateur()->create()->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function corps(array $remplace = []): array
    {
        return array_merge([
            'enlevement' => 'Rue Neuve 43, 3500 Hasselt',
            'livraison' => 'Avenue Louise 200, 1000 Bruxelles',
            'poids' => 850,
            'marchandise' => TransportOrder::MARCHANDISES[0],
            'date_enlevement' => now()->addDays(2)->toDateString(),
            'date_livraison' => now()->addDays(9)->toDateString(),
        ], $remplace);
    }

    public function test_sans_jeton_l_api_refuse(): void
    {
        $this->getJson('/api/v1/expeditions')->assertUnauthorized();
    }

    public function test_un_jeton_invente_recoit_la_meme_reponse_qu_un_secret_errone(): void
    {
        [$cle, $jeton] = $this->cle(Client::factory()->create()->id);
        $prefixe = explode('.', $jeton)[0];

        $invente = $this->getJson('/api/v1/expeditions', ['Authorization' => 'Bearer nblt_zzzzzzz.mauvais'])
            ->assertUnauthorized()->json();

        $errone = $this->getJson('/api/v1/expeditions', ['Authorization' => 'Bearer '.$prefixe.'.mauvais'])
            ->assertUnauthorized()->json();

        $this->assertSame($invente['message'], $errone['message']);
        $this->assertSame($invente['motif'], $errone['motif']);
    }

    public function test_une_cle_ne_voit_que_les_expeditions_de_son_entreprise(): void
    {
        $sienne = Client::factory()->create();
        $autre = Client::factory()->create();

        TransportOrder::factory()->count(2)->create(['client_id' => $sienne->id]);
        TransportOrder::factory()->count(3)->create(['client_id' => $autre->id]);

        [$cle, $jeton] = $this->cle($sienne->id);

        $reponse = $this->getJson('/api/v1/expeditions', ['Authorization' => 'Bearer '.$jeton]);

        $reponse->assertOk();
        $this->assertSame(2, $reponse->json('meta.total') ?? count($reponse->json('data')));
    }

    public function test_une_cle_interne_lit_tout_mais_ne_depose_rien(): void
    {
        TransportOrder::factory()->count(3)->create();

        [$cle, $jeton] = $this->cle(null);

        $this->getJson('/api/v1/expeditions', ['Authorization' => 'Bearer '.$jeton])
            ->assertOk();

        $this->postJson('/api/v1/expeditions', $this->corps(), ['Authorization' => 'Bearer '.$jeton])
            ->assertStatus(422);
    }

    public function test_un_ordre_depose_par_l_api_nait_complet(): void
    {
        TariffGrid::factory()->create();
        $entreprise = Client::factory()->create();
        [$cle, $jeton] = $this->cle($entreprise->id);

        $this->postJson('/api/v1/expeditions', $this->corps(), ['Authorization' => 'Bearer '.$jeton])
            ->assertCreated();

        $ordre = TransportOrder::where('client_id', $entreprise->id)->firstOrFail();

        foreach (['tracking_number', 'tracking_code', 'tariff_grid_id', 'distance_km',
            'estimated_cost', 'pickup_lat', 'pickup_lng', 'delivery_lat', 'delivery_lng'] as $champ) {
            $this->assertNotNull($ordre->$champ, 'Le champ « '.$champ.' » est reste vide.');
        }

        $this->assertGreaterThan(0, $ordre->estimated_cost);
    }

    public function test_sans_formule_l_api_prend_la_moins_chere_qui_tient_le_delai(): void
    {
        TariffGrid::factory()->create();
        $express = TariffGrid::factory()->express()->create();
        $entreprise = Client::factory()->create();
        [$cle, $jeton] = $this->cle($entreprise->id);

        $this->postJson('/api/v1/expeditions', $this->corps(), ['Authorization' => 'Bearer '.$jeton])
            ->assertCreated();

        $ordre = TransportOrder::where('client_id', $entreprise->id)->firstOrFail();

        $this->assertNotSame($express->id, $ordre->tariff_grid_id);
        $this->assertSame(3, $ordre->tariffGrid->delivery_days);
    }

    public function test_une_formule_nommee_est_respectee(): void
    {
        TariffGrid::factory()->create();
        $express = TariffGrid::factory()->express()->create();
        $entreprise = Client::factory()->create();
        [$cle, $jeton] = $this->cle($entreprise->id);

        $this->postJson('/api/v1/expeditions', $this->corps(['formule' => 'EXPRESS']),
            ['Authorization' => 'Bearer '.$jeton])->assertCreated();

        $this->assertSame($express->id,
            TransportOrder::where('client_id', $entreprise->id)->value('tariff_grid_id'));
    }

    public function test_un_delai_intenable_est_refuse(): void
    {
        TariffGrid::factory()->create();
        $entreprise = Client::factory()->create();
        [$cle, $jeton] = $this->cle($entreprise->id);

        $this->postJson('/api/v1/expeditions', $this->corps([
            'date_livraison' => now()->addDays(2)->toDateString(),
        ]), ['Authorization' => 'Bearer '.$jeton])->assertStatus(422);

        $this->assertSame(0, TransportOrder::count());
    }

    public function test_une_localite_inconnue_est_refusee(): void
    {
        TariffGrid::factory()->create();
        $entreprise = Client::factory()->create();
        [$cle, $jeton] = $this->cle($entreprise->id);

        $this->postJson('/api/v1/expeditions', $this->corps([
            'livraison' => 'Rue Machin 1, 9999 Zzzzville',
        ]), ['Authorization' => 'Bearer '.$jeton])->assertStatus(422);

        $this->assertSame(0, TransportOrder::count());
    }

    public function test_une_cle_revoquee_ne_passe_plus(): void
    {
        [$cle, $jeton] = $this->cle(Client::factory()->create()->id);

        $cle->forceFill(['revoked_at' => now()])->save();

        $this->getJson('/api/v1/expeditions', ['Authorization' => 'Bearer '.$jeton])
            ->assertUnauthorized();
    }
}
