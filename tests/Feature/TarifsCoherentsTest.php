<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TariffGrid;
use App\Models\User;
use App\Support\Tarificateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TarifsCoherentsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, TariffGrid> */
    private function grillesFrance(): array
    {
        // Les migrations livrent deja des grilles : on repart d une zone vide.
        TariffGrid::where('zone', 'FR')->update(['is_active' => false]);
        $commun = ['zone' => 'FR', 'is_active' => true];

        return [
            'ECO' => TariffGrid::forceCreate($commun + ['label' => 'Export France — Eco', 'service_level' => 'ECO', 'base_rate' => 70, 'price_per_km' => 0.225, 'price_per_kg' => 0.062, 'adr_coefficient' => 1.20, 'delivery_days' => 5]),
            'STANDARD' => TariffGrid::forceCreate($commun + ['label' => 'Export France — Standard', 'service_level' => 'STANDARD', 'base_rate' => 98, 'price_per_km' => 0.3125, 'price_per_kg' => 0.088, 'adr_coefficient' => 1.25, 'delivery_days' => 3]),
            'EXPRESS' => TariffGrid::forceCreate($commun + ['label' => 'Export France — Express', 'service_level' => 'EXPRESS', 'base_rate' => 140, 'price_per_km' => 2.125, 'price_per_kg' => 0.099, 'adr_coefficient' => 1.30, 'delivery_days' => 1]),
        ];
    }

    /** @return array<string, float> */
    private function prix(array $grilles, float $poids, bool $adr = false): array
    {
        return collect($grilles)
            ->map(fn (TariffGrid $g) => Tarificateur::cout($g, 300, $poids, 'FR', $adr))
            ->all();
    }

    public function test_les_formules_restent_dans_l_ordre_quel_que_soit_le_poids(): void
    {
        $grilles = $this->grillesFrance();

        foreach ([100, 1200, 8000, 24000, 44000] as $poids) {
            foreach ([false, true] as $adr) {
                $p = $this->prix($grilles, $poids, $adr);

                $this->assertLessThanOrEqual($p['STANDARD'], $p['ECO'], "Eco > Standard a {$poids} kg");
                $this->assertGreaterThan($p['STANDARD'], $p['EXPRESS'], "Express <= Standard a {$poids} kg");
            }
        }
    }

    public function test_le_groupage_ne_coute_jamais_plus_qu_un_camion_dedie(): void
    {
        $grilles = $this->grillesFrance();
        $dedie = Tarificateur::brut($grilles['EXPRESS'], 300, 44000, 'FR', false);

        $p = $this->prix($grilles, 44000);

        $this->assertLessThanOrEqual($dedie, $p['STANDARD']);
        $this->assertLessThanOrEqual($dedie, $p['ECO']);
        // Sans plafond, le groupage d un camion complet couterait plus de 4 000 EUR.
        $this->assertGreaterThan(4000, Tarificateur::brut($grilles['STANDARD'], 300, 44000, 'FR', false));
    }

    public function test_un_petit_envoi_garde_le_tarif_groupage(): void
    {
        $grilles = $this->grillesFrance();
        $p = $this->prix($grilles, 500);

        $this->assertSame(Tarificateur::brut($grilles['STANDARD'], 300, 500, 'FR', false), $p['STANDARD']);
        $this->assertSame(Tarificateur::brut($grilles['ECO'], 300, 500, 'FR', false), $p['ECO']);
        $this->assertSame(Tarificateur::brut($grilles['EXPRESS'], 300, 500, 'FR', false), $p['EXPRESS']);
    }

    public function test_l_estimation_du_formulaire_donne_le_prix_enregistre(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response(['routes' => [['distance' => 300000]]])]);
        $grilles = $this->grillesFrance();
        $client = Client::factory()->create();

        $reponse = $this->actingAs($client->compte())
            ->postJson(route('transport-orders.estimation'), [
                'delivery_country' => 'FR',
                'pickup_lat' => 50.8504, 'pickup_lng' => 4.3488,
                'delivery_lat' => 48.8534, 'delivery_lng' => 2.3488,
                'weight' => 44000,
                'is_hazardous' => false,
            ])
            ->assertOk()
            ->assertJsonPath('distance_km', 300);

        foreach ($this->prix($grilles, 44000) as $niveau => $montant) {
            $this->assertEquals($montant, $reponse->json('prix.'.$grilles[$niveau]->id));
        }
    }

    public function test_l_estimation_est_reservee_aux_clients(): void
    {
        $this->grillesFrance();

        $this->actingAs(User::factory()->planificateur()->create())
            ->postJson(route('transport-orders.estimation'), [
                'delivery_country' => 'FR',
                'pickup_lat' => 50.85, 'pickup_lng' => 4.35,
                'delivery_lat' => 48.85, 'delivery_lng' => 2.35,
                'weight' => 1000,
            ])
            ->assertForbidden();
    }

    public function test_le_simulateur_tarife_un_import_sur_les_grilles_du_pays_de_depart(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response([], 503)]);
        DB::table('postal_codes')->insert([
            ['country_code' => 'BE', 'code' => '1000', 'city' => 'Bruxelles', 'lat' => 50.8504, 'lng' => 4.3488],
            ['country_code' => 'FR', 'code' => '59000', 'city' => 'Lille', 'lat' => 50.6292, 'lng' => 3.0573],
        ]);
        TariffGrid::factory()->create();
        $grilles = $this->grillesFrance();

        $reponse = $this->postJson(route('tarifs.simuler'), ['depart' => 'Lille', 'pays_depart' => 'FR', 'destination' => 'Bruxelles', 'pays' => 'BE', 'poids' => 500])
            ->assertOk()
            ->assertJsonPath('fret_retour_possible', true);

        $km = Tarificateur::distanceRoutiere(50.6292, 3.0573, 50.8504, 4.3488);
        $attendus = Tarificateur::parFormule(collect($grilles), $km, 500, 'FR', false);
        $this->assertEqualsCanonicalizing(array_values($attendus), array_column($reponse->json('formules'), 'prix'));

        $this->postJson(route('tarifs.simuler'), ['depart' => 'Lille', 'pays_depart' => 'FR', 'destination' => 'Paris', 'pays' => 'FR', 'poids' => 500])->assertStatus(422);
        $this->postJson(route('tarifs.simuler'), ['depart' => 'Londres', 'pays_depart' => 'GB', 'destination' => 'Bruxelles', 'pays' => 'BE', 'poids' => 500])->assertStatus(422);
    }

    public function test_la_page_tarifs_liste_les_pays_d_enlevement(): void
    {
        $departs = AssertableInertia::fromTestResponse($this->get(route('tarifs.index')))->toArray()['props']['departs'];

        $this->assertEqualsCanonicalizing(config('fret.pays_enlevement'), array_column($departs, 'code'));
    }
}
