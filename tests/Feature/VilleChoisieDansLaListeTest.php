<?php

namespace Tests\Feature;

use App\Models\TariffGrid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Le simulateur de tarif propose les grandes villes sous leur nom
 * francais, comme les autres formulaires : le point de la ville choisie
 * la retrouve dans le referentiel, ou elle porte son nom local.
 */
class VilleChoisieDansLaListeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['router.project-osrm.org/*' => Http::response([], 503)]);
        DB::table('postal_codes')->insert([
            ['country_code' => 'BE', 'code' => '1000', 'city' => 'Bruxelles', 'lat' => 50.8504, 'lng' => 4.3488],
            ['country_code' => 'DE', 'code' => '50667', 'city' => 'Köln', 'lat' => 50.9384, 'lng' => 6.9599],
        ]);
        TariffGrid::factory()->create();
        TariffGrid::factory()->create(['zone' => 'DE', 'label' => 'Export Allemagne — Standard']);
    }

    private function simuler(array $champs): TestResponse
    {
        return $this->postJson(route('tarifs.simuler'), $champs + [
            'depart' => 'Bruxelles',
            'pays_depart' => 'BE',
            'pays' => 'DE',
            'poids' => 500,
        ]);
    }

    public function test_une_grande_ville_choisie_sous_son_nom_francais_est_tarifee(): void
    {
        $this->simuler(['destination' => 'Cologne', 'destination_lat' => 50.9375, 'destination_lng' => 6.9603])
            ->assertOk()
            ->assertJsonPath('arrivee', 'Köln');
    }

    public function test_un_nom_inconnu_sans_point_reste_refuse(): void
    {
        $this->simuler(['destination' => 'Cologne'])
            ->assertStatus(422)
            ->assertJsonPath('erreur', 'Localité de destination introuvable dans ce pays.');
    }

    public function test_un_point_loin_de_toute_localite_est_refuse(): void
    {
        $this->simuler(['destination' => 'Cologne', 'destination_lat' => 52.52, 'destination_lng' => 13.405])
            ->assertStatus(422);
    }

    public function test_un_nom_du_referentiel_garde_sa_localite_malgre_le_point(): void
    {
        $this->simuler(['destination' => 'Köln', 'destination_lat' => 50.9, 'destination_lng' => 6.9])
            ->assertOk()
            ->assertJsonPath('arrivee', 'Köln');
    }
}
