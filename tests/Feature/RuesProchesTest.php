<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Rues autour d'une localite, chargees une fois pour la saisie de la rue. */
class RuesProchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_un_nom_une_fois_le_troncon_le_plus_proche_d_abord(): void
    {
        Http::fake(['*' => Http::response(['elements' => [
            ['type' => 'way', 'center' => ['lat' => 50.86, 'lon' => 4.36], 'tags' => ['name' => 'Rue Royale']],
            ['type' => 'way', 'center' => ['lat' => 50.8501, 'lon' => 4.3501], 'tags' => ['name' => 'Rue de la Loi']],
            ['type' => 'way', 'center' => ['lat' => 50.8502, 'lon' => 4.3502], 'tags' => ['name' => 'Rue Royale']],
            ['type' => 'way', 'center' => ['lat' => 50.851, 'lon' => 4.351], 'tags' => []],
        ]])]);

        $this->getJson('/geo/rues?lat=50.85&lng=4.35')->assertOk()->assertExactJson([
            ['nom' => 'Rue de la Loi', 'lat' => 50.8501, 'lng' => 4.3501],
            ['nom' => 'Rue Royale', 'lat' => 50.8502, 'lng' => 4.3502],
        ]);

        // Deuxieme demande : le cache repond, Overpass n'est pas rappele.
        Http::fake(fn () => throw new \RuntimeException('Overpass ne doit pas etre rappele'));
        $this->getJson('/geo/rues?lat=50.851&lng=4.349')->assertOk()->assertJsonCount(2);
    }

    public function test_une_panne_d_overpass_n_est_pas_retenue(): void
    {
        $enPanne = true;
        Http::fake(function () use (&$enPanne) {
            return $enPanne
                ? Http::response('Too Many Requests', 429)
                : Http::response(['elements' => [
                    ['type' => 'way', 'center' => ['lat' => 50.85, 'lon' => 4.35], 'tags' => ['name' => 'Rue Neuve']],
                ]]);
        });
        $this->getJson('/geo/rues?lat=50.85&lng=4.35')->assertOk()->assertExactJson([]);

        $enPanne = false;
        $this->getJson('/geo/rues?lat=50.85&lng=4.35')->assertOk()->assertJsonCount(1);
    }
}
