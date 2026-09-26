<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Support\Tarificateur;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\GrillesDeDemonstration;
use Tests\TestCase;

/**
 * Commander un enlevement hors de Belgique : import au prix de l'export
 * miroir, trajets entre pays etrangers sur devis, pays de l'adresse,
 * calendrier du pays, premier enlevement possible, expediteur.
 */
class EnlevementEtrangerTest extends TestCase
{
    use GrillesDeDemonstration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Lundi 5 octobre 2026, 10 h : un camion peut etre a Lille le
        // mardi a 8 h.
        $this->travelTo('2026-10-05 10:00');
        Http::fake(['router.project-osrm.org/*' => Http::response([], 503)]);
        $this->creerLesGrillesDeDemonstration();

        DB::table('postal_codes')->insert([
            ['country_code' => 'BE', 'code' => '1000', 'city' => 'Bruxelles', 'lat' => 50.8504, 'lng' => 4.3488],
            ['country_code' => 'BE', 'code' => '4000', 'city' => 'Liège', 'lat' => 50.6326, 'lng' => 5.5797],
            ['country_code' => 'FR', 'code' => '59000', 'city' => 'Lille', 'lat' => 50.6292, 'lng' => 3.0573],
            ['country_code' => 'FR', 'code' => '93200', 'city' => 'Saint-Denis', 'lat' => 48.936, 'lng' => 2.357],
            ['country_code' => 'FR', 'code' => '11310', 'city' => 'Saint-Denis', 'lat' => 43.34, 'lng' => 2.27],
            ['country_code' => 'DE', 'code' => '50667', 'city' => 'Köln', 'lat' => 50.9375, 'lng' => 6.9603],
            ['country_code' => 'PT', 'code' => '1000-001', 'city' => 'Lisboa', 'lat' => 38.7223, 'lng' => -9.1393],
        ]);
    }

    private function grille(string $zone, string $niveau = 'STANDARD'): TariffGrid
    {
        return TariffGrid::where('zone', $zone)->where('service_level', $niveau)->where('is_active', true)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function lilleBruxelles(array $plus = []): array
    {
        return [
            'pickup_address' => 'Rue Nationale 1, 59000 Lille, France',
            'pickup_country' => 'FR',
            'pickup_lat' => 50.6292, 'pickup_lng' => 3.0573,
            'delivery_address' => 'Rue Haute 100, 1000 Bruxelles, Belgique',
            'delivery_country' => 'BE',
            'delivery_lat' => 50.8504, 'delivery_lng' => 4.3488,
            'weight' => 1200,
            'goods_type' => 'Palettes',
            'priority' => 'NORMAL',
            'pickup_date' => '2026-10-08T09:00',
            'tariff_grid_id' => $this->grille('FR')->id,
            'shipper_name' => 'Entrepôt Lille Sud',
            'shipper_phone' => '+33 3 20 00 00 00',
            ...$plus,
        ];
    }

    private function commander(array $donnees)
    {
        return $this->actingAs(Client::factory()->create()->compte())->post(route('transport-orders.store'), $donnees);
    }

    public function test_un_import_est_tarife_sur_la_grille_du_pays_etranger(): void
    {
        $this->commander($this->lilleBruxelles())->assertSessionHasNoErrors();

        $ordre = TransportOrder::firstOrFail();
        $km = Tarificateur::distanceRoutiere(50.6292, 3.0573, 50.8504, 4.3488);
        $attendu = Tarificateur::parFormule(TariffGrid::where('zone', 'FR')->where('is_active', true)->get(), $km, 1200, 'FR', false)[$this->grille('FR')->id];

        $this->assertSame(['FR', 'BE', 'LANE'], [$ordre->pickup_country, $ordre->delivery_country, $ordre->pricing_basis]);
        $this->assertEqualsWithDelta($attendu, (float) $ordre->estimated_cost, 0.001);
        $this->assertSame('Entrepôt Lille Sud', $ordre->shipper_name);
        $this->assertGreaterThan(100, $ordre->approche_km);
        $this->assertStringStartsWith('Import', $ordre->formule());
    }

    public function test_l_import_coute_le_prix_de_l_export_miroir(): void
    {
        $client = Client::factory()->create()->compte();
        $aller = $this->actingAs($client)->postJson(route('transport-orders.estimation'), [
            'pickup_country' => 'BE', 'delivery_country' => 'FR', 'weight' => 900,
            'pickup_lat' => 50.8504, 'pickup_lng' => 4.3488, 'delivery_lat' => 50.6292, 'delivery_lng' => 3.0573,
        ])->assertOk();
        $retour = $this->actingAs($client)->postJson(route('transport-orders.estimation'), [
            'pickup_country' => 'FR', 'delivery_country' => 'BE', 'weight' => 900,
            'pickup_lat' => 50.6292, 'pickup_lng' => 3.0573, 'delivery_lat' => 50.8504, 'delivery_lng' => 4.3488,
        ])->assertOk();

        $this->assertSame('FR', $retour->json('zone'));
        $this->assertSame('IMPORT', $retour->json('type'));
        $this->assertEquals($aller->json('prix'), $retour->json('prix'));
        $this->assertNotNull($retour->json('premier_enlevement.local'));
    }

    public function test_un_import_ne_prend_pas_la_grille_nationale(): void
    {
        $this->commander($this->lilleBruxelles(['tariff_grid_id' => $this->grille('BE')->id]))
            ->assertSessionHasErrors('tariff_grid_id');

        $this->assertSame(0, TransportOrder::count());
    }

    public function test_un_trajet_entre_deux_pays_etrangers_est_renvoye_au_devis(): void
    {
        $this->actingAs(Client::factory()->create()->compte())->postJson(route('transport-orders.estimation'), [
            'pickup_country' => 'FR', 'delivery_country' => 'DE', 'weight' => 900,
            'pickup_lat' => 50.6292, 'pickup_lng' => 3.0573, 'delivery_lat' => 50.9375, 'delivery_lng' => 6.9603,
        ])->assertStatus(422)->assertJsonPath('champ', 'delivery_address');

        $this->commander($this->lilleBruxelles([
            'delivery_address' => 'Domkloster 4, 50667 Köln, Allemagne', 'delivery_country' => 'DE',
            'delivery_lat' => 50.9375, 'delivery_lng' => 6.9603, 'tariff_grid_id' => $this->grille('DE')->id,
        ]))->assertSessionHasErrors('delivery_address');

        $this->assertSame(0, TransportOrder::count());
    }

    public function test_un_pays_sur_devis_ou_une_ile_est_refuse_en_ligne(): void
    {
        $this->commander($this->lilleBruxelles(['pickup_country' => 'GB']))->assertSessionHasErrors('pickup_address');
        $this->commander($this->lilleBruxelles(['pickup_address' => 'Cours Napoléon 1, 20000 Ajaccio, France']))->assertSessionHasErrors('pickup_address');
        $this->assertSame(0, TransportOrder::count());
    }

    public function test_le_pays_declare_doit_etre_celui_de_l_adresse(): void
    {
        $this->commander($this->lilleBruxelles(['pickup_country' => 'BE', 'tariff_grid_id' => $this->grille('BE')->id]))
            ->assertSessionHasErrors('pickup_address');
    }

    public function test_une_ville_homonyme_est_retrouvee_par_le_point(): void
    {
        $this->commander($this->lilleBruxelles([
            'pickup_address' => 'Rue de la République 1, 93200 Saint-Denis, France',
            'pickup_lat' => 48.936, 'pickup_lng' => 2.357,
        ]))->assertSessionHasNoErrors();
    }

    public function test_un_joker_ne_passe_pas_pour_une_localite(): void
    {
        $this->commander($this->lilleBruxelles(['pickup_address' => 'Rue X 1, 59000 %, France']))->assertSessionHasErrors('pickup_address');
        $this->assertSame(0, TransportOrder::count());
    }

    public function test_un_enlevement_etranger_attend_que_le_camion_puisse_y_etre(): void
    {
        $this->commander($this->lilleBruxelles(['pickup_date' => null]))->assertSessionHasErrors('pickup_date');
        // Mardi 7 h : le camion parti de Bruxelles a 6 h n'arrive qu'a 8 h.
        $this->commander($this->lilleBruxelles(['pickup_date' => '2026-10-06T07:00']))->assertSessionHasErrors('pickup_date');
        $this->assertStringContainsString('06/10/2026 08:00', session('errors')->first('pickup_date'));
        $this->commander($this->lilleBruxelles(['pickup_date' => '2026-10-06T08:00']))->assertSessionHasNoErrors();
    }

    public function test_un_enlevement_francais_suit_le_calendrier_francais(): void
    {
        $this->travelTo('2027-07-05 10:00');

        $this->commander($this->lilleBruxelles(['pickup_date' => '2027-07-14T09:00']))->assertSessionHasErrors('pickup_date');
        $this->commander($this->lilleBruxelles(['pickup_date' => '2027-07-21T09:00']))->assertSessionHasNoErrors();
    }

    public function test_l_expediteur_est_requis_hors_de_belgique(): void
    {
        $this->commander($this->lilleBruxelles(['shipper_name' => '']))->assertSessionHasErrors('shipper_name');
        $this->assertSame(0, TransportOrder::count());
    }

    public function test_l_heure_saisie_est_celle_du_pays_d_enlevement(): void
    {
        $this->commander($this->lilleBruxelles([
            'pickup_address' => 'Rua Augusta 1, 1000-001 Lisboa, Portugal', 'pickup_country' => 'PT',
            'pickup_lat' => 38.7223, 'pickup_lng' => -9.1393,
            'pickup_date' => '2026-10-20T08:00', 'tariff_grid_id' => $this->grille('PT')->id,
        ]))->assertSessionHasNoErrors();

        // 8 h a Lisbonne, 9 h a Bruxelles.
        $this->assertSame('2026-10-20 09:00', TransportOrder::firstOrFail()->pickup_date->format('Y-m-d H:i'));
    }

    public function test_le_delai_promis_n_est_jamais_plus_court_que_la_route(): void
    {
        $express = $this->grille('PT', 'EXPRESS');
        $express->delivery_days = 1;

        // 2 000 km : 31 h de conduite, 4 journees, livraison au plus tot a J+3.
        $this->assertSame(3, Tarificateur::delai($express, 2000));
        $this->assertSame(1, Tarificateur::delai($express, 300));
    }

    public function test_la_base_refuse_un_trajet_entre_pays_etrangers(): void
    {
        $this->expectException(QueryException::class);

        TransportOrder::factory()->create(['pickup_country' => 'FR', 'delivery_country' => 'FR', 'tariff_grid_id' => $this->grille('FR')->id]);
    }
}
