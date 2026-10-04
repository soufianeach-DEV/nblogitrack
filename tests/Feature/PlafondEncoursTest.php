<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Client;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Encours;
use App\Support\Facturier;
use App\Support\JoursFeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Une facture en retard, ou un encours qui depasserait le plafond de credit,
 * bloque toute nouvelle commande.
 */
class PlafondEncoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('postal_codes')->insert([
            ['country_code' => 'BE', 'code' => '3500', 'city' => 'Hasselt', 'lat' => 50.9311, 'lng' => 5.3378],
            ['country_code' => 'BE', 'code' => '1000', 'city' => 'Bruxelles', 'lat' => 50.8504, 'lng' => 4.3488],
        ]);

        Http::fake([
            'router.project-osrm.org/*' => Http::response(['code' => 'Ok', 'routes' => [['distance' => 84000]]]),
            '*' => Http::response([], 200),
        ]);
    }

    private function deposer(Client $entreprise): TestResponse
    {
        [, $jeton] = ApiKey::generer([
            'name' => 'Essai', 'client_id' => $entreprise->id, 'abilities' => ['lecture', 'ecriture'],
            'created_by' => User::factory()->administrateur()->create()->id,
        ]);

        return $this->postJson('/api/v1/expeditions', [
            'enlevement' => 'Rue Neuve 43, 3500 Hasselt',
            'livraison' => 'Avenue Louise 200, 1000 Bruxelles',
            'poids' => 850,
            'marchandise' => TransportOrder::MARCHANDISES[0],
            'date_enlevement' => JoursFeries::prochainJourOuvrable(now()->addDays(2))->toDateString(),
            'date_livraison' => now()->addDays(12)->toDateString(),
        ], ['Authorization' => 'Bearer '.$jeton]);
    }

    /** Une facture de 500 € HT emise le 10 avril, a 30 jours. */
    private function facturer(Client $entreprise): void
    {
        $this->travelTo('2026-04-10');
        TransportOrder::factory()->livree()->create(['client_id' => $entreprise->id, 'actual_delivery_date' => '2026-03-10', 'estimated_cost' => 500]);
        app(Facturier::class)->facturer();
    }

    public function test_l_encours_additionne_factures_dues_et_courses_a_facturer(): void
    {
        $entreprise = Client::factory()->create(['credit_limit' => null, 'payment_terms' => '30 jours']);
        $this->facturer($entreprise);
        TransportOrder::factory()->create(['client_id' => $entreprise->id, 'status' => 'PENDING', 'estimated_cost' => 200]);
        TransportOrder::factory()->create(['client_id' => $entreprise->id, 'status' => 'CANCELLED', 'estimated_cost' => 999]);

        $encours = Encours::de($entreprise);

        $this->assertSame(605.0, $encours['factures']);
        $this->assertSame(200.0, $encours['a_facturer']);
        $this->assertSame(805.0, $encours['total']);
        $this->assertSame(0, $encours['en_retard']);
    }

    public function test_une_facture_en_retard_bloque_les_commandes(): void
    {
        TariffGrid::factory()->create();
        $entreprise = Client::factory()->create(['credit_limit' => null, 'payment_terms' => '30 jours']);
        $this->facturer($entreprise);

        // Echeance le 10 mai : le 20 mai, la facture est en retard.
        $this->travelTo('2026-05-20');
        $avant = TransportOrder::count();

        $this->deposer($entreprise)->assertStatus(422)->assertJsonPath('motif', 'encours');
        $this->assertSame($avant, TransportOrder::count());

        $client = User::factory()->create(['role' => 'CLIENT', 'client_id' => $entreprise->id, 'company_role' => 'ADMIN']);
        $this->actingAs($client)->get(route('transport-orders.create', ['langue' => 'fr']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('blocageEncours', fn ($m) => is_string($m) && str_contains($m, 'retard')));
    }

    public function test_le_plafond_bloque_au_dela_et_laisse_passer_en_dessous(): void
    {
        TariffGrid::factory()->create();

        $serree = Client::factory()->create(['credit_limit' => 10]);
        $this->deposer($serree)->assertStatus(422)->assertJsonPath('motif', 'encours');
        $this->assertSame(0, TransportOrder::where('client_id', $serree->id)->count());

        $large = Client::factory()->create(['credit_limit' => 100000]);
        $this->deposer($large)->assertCreated();

        $sansPlafond = Client::factory()->create(['credit_limit' => null]);
        $this->deposer($sansPlafond)->assertCreated();
    }

    public function test_l_admin_regle_delai_et_plafond(): void
    {
        $entreprise = Client::factory()->create(['is_validated' => true, 'credit_limit' => 5000, 'payment_terms' => '30 jours']);
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin)->patch(route('clients.terms', ['langue' => 'fr', 'client' => $entreprise]), [
            'payment_terms' => '60 jours',
            'credit_limit' => '',
        ])->assertSessionHas('success');

        $entreprise->refresh();
        $this->assertSame('60 jours', $entreprise->payment_terms);
        $this->assertNull($entreprise->credit_limit);

        $this->actingAs($admin)->patch(route('clients.terms', ['langue' => 'fr', 'client' => $entreprise]), [
            'payment_terms' => '90 jours',
        ])->assertSessionHasErrors('payment_terms');

        $client = User::factory()->create(['role' => 'CLIENT', 'client_id' => $entreprise->id, 'company_role' => 'ADMIN']);
        $this->actingAs($client)->patch(route('clients.terms', ['langue' => 'fr', 'client' => $entreprise]), [
            'payment_terms' => '45 jours', 'credit_limit' => 999999,
        ])->assertForbidden();
    }
}
