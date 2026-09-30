<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Numeros d'une rue : le premier serveur Overpass qui repond suffit, et
 * une panne des serveurs n'est pas retenue comme une rue sans numeros.
 */
class NumerosDeRueTest extends TestCase
{
    use RefreshDatabase;

    private const RUE = ['rue' => 'Rue de la Loi', 'lat' => 50.8455, 'lng' => 4.37, 'cp' => '1000'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array<string, mixed> */
    private function elements(): array
    {
        return ['elements' => [
            ['type' => 'node', 'lat' => 50.8457, 'lon' => 4.3695, 'tags' => ['addr:housenumber' => '16', 'addr:postcode' => '1000']],
            ['type' => 'way', 'center' => ['lat' => 50.8453, 'lon' => 4.3712], 'tags' => ['addr:housenumber' => '2;4', 'addr:postcode' => '1040']],
            ['type' => 'node', 'lat' => 50.8458, 'lon' => 4.3690, 'tags' => ['addr:housenumber' => '14A', 'addr:postcode' => '1000']],
        ]];
    }

    private function demander()
    {
        return $this->actingAs(Client::factory()->create()->compte())
            ->getJson(route('geo.numeros', self::RUE));
    }

    public function test_un_serveur_en_panne_ne_prive_pas_des_numeros(): void
    {
        Http::fake(fn (Request $r) => str_contains($r->url(), 'overpass.kumi.systems')
            ? Http::response($this->elements())
            : Http::response('Too Many Requests', 429));

        $this->demander()->assertOk()->assertExactJson([
            ['numero' => '14A', 'lat' => 50.8458, 'lng' => 4.369, 'cp' => '1000'],
            ['numero' => '16', 'lat' => 50.8457, 'lng' => 4.3695, 'cp' => '1000'],
        ]);
    }

    public function test_une_panne_de_tous_les_serveurs_n_est_pas_retenue(): void
    {
        $enPanne = true;
        Http::fake(function () use (&$enPanne) {
            return $enPanne ? Http::response('Gateway Timeout', 504) : Http::response($this->elements());
        });

        $this->demander()->assertOk()->assertExactJson([]);
        Http::assertSentCount(3);

        // Les serveurs sont revenus : la rue n'est pas restee vide.
        $enPanne = false;
        $this->demander()->assertOk()->assertJsonCount(2);
    }

    public function test_une_reponse_est_gardee_en_cache(): void
    {
        Http::fake(['*' => Http::response($this->elements())]);

        $this->demander()->assertOk()->assertJsonCount(2);
        $this->demander()->assertOk()->assertJsonCount(2);

        Http::assertSentCount(3);
    }
}
