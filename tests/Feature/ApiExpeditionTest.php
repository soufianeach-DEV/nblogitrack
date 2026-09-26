<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Client;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\JoursFeries;
use Database\Seeders\TranslationSeeder;
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
            ['country_code' => 'FR', 'code' => '59000', 'city' => 'Lille', 'lat' => 50.6292, 'lng' => 3.0573],
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
            // Un jour ouvrable : un dimanche, l'enlevement est refuse.
            'date_enlevement' => JoursFeries::prochainJourOuvrable(now()->addDays(2))->toDateString(),
            'date_livraison' => now()->addDays(12)->toDateString(),
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

    /** Lundi 5 octobre 2026 : un camion peut etre a Lille le mardi. */
    private function importLille(array $remplace = []): array
    {
        $this->travelTo('2026-10-05 10:00');
        TariffGrid::factory()->zone('FR', 'France')->create();

        return $this->corps([
            'enlevement' => 'Rue Nationale 1, 59000 Lille, France',
            'livraison' => 'Avenue Louise 200, 1000 Bruxelles',
            'expediteur' => 'Entrepôt Lille Sud',
            'telephone_expediteur' => '+33 3 20 00 00 00',
            'date_enlevement' => '2026-10-08',
            'date_livraison' => '2026-10-15',
            ...$remplace,
        ]);
    }

    public function test_un_import_se_depose_par_l_api(): void
    {
        [, $jeton] = $this->cle(Client::factory()->create()->id);

        $this->postJson('/api/v1/expeditions', $this->importLille(['pays_enlevement' => 'FR']), ['Authorization' => 'Bearer '.$jeton])
            ->assertCreated()
            ->assertJsonPath('data.pays_depart', 'FR')
            ->assertJsonPath('data.pays_arrivee', 'BE')
            ->assertJsonPath('data.tarif', 'ligne');

        $ordre = TransportOrder::firstOrFail();
        $this->assertSame('FR', $ordre->tariffGrid->zone);
        $this->assertGreaterThan(0, (float) $ordre->estimated_cost);
    }

    public function test_le_pays_d_enlevement_se_deduit_de_l_adresse(): void
    {
        [, $jeton] = $this->cle(Client::factory()->create()->id);

        $this->postJson('/api/v1/expeditions', $this->importLille(), ['Authorization' => 'Bearer '.$jeton])
            ->assertCreated()
            ->assertJsonPath('data.pays_depart', 'FR');
    }

    public function test_l_api_refuse_les_enlevements_etrangers_hors_regles(): void
    {
        [, $jeton] = $this->cle(Client::factory()->create()->id);
        $refus = fn (array $corps) => $this->postJson('/api/v1/expeditions', $corps, ['Authorization' => 'Bearer '.$jeton])->assertStatus(422);

        $refus($this->importLille(['pays_enlevement' => 'GB']));
        $refus($this->importLille(['pays_enlevement' => 'BE']));
        $refus($this->importLille(['date_enlevement' => '2026-10-05']));
        $refus($this->importLille(['date_enlevement' => '2026-10-11']));
        $refus($this->importLille(['expediteur' => null]));
        $refus($this->importLille(['enlevement' => 'Theaterplatz 1, 52062 Aachen, Deutschland-Nord']));
        $refus($this->importLille(['livraison' => 'Domkloster 4, 50667 Köln', 'pays_livraison' => 'DE']));

        $this->assertSame(0, TransportOrder::count());
    }

    /** Les messages suivent Accept-Language, sinon la langue du compte de l'entreprise. */
    public function test_les_messages_suivent_la_langue_de_l_integrateur(): void
    {
        $this->seed(TranslationSeeder::class);
        $client = Client::factory()->create();
        $client->users()->update(['locale' => 'nl']);
        [, $jeton] = $this->cle($client->id);

        $this->getJson('/api/v1/expeditions/INCONNU', ['Authorization' => 'Bearer '.$jeton, 'Accept-Language' => ''])
            ->assertNotFound()->assertJsonPath('message', 'Zending niet gevonden.');

        $this->getJson('/api/v1/expeditions/INCONNU', ['Authorization' => 'Bearer '.$jeton, 'Accept-Language' => 'en-GB,en;q=0.9'])
            ->assertNotFound()->assertJsonPath('message', 'Shipment not found.');
    }
}
