<?php

namespace Tests\Feature;

use App\Support\Osrm;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ItineraireSecoursTest extends TestCase
{
    public function test_le_serveur_de_secours_prend_le_relais(): void
    {
        config(['services.osrm.serveurs' => ['https://router.project-osrm.org', 'https://routing.openstreetmap.de/routed-car']]);

        Http::fake([
            'router.project-osrm.org/*' => Http::response([], 503),
            'routing.openstreetmap.de/*' => Http::response(['routes' => [[
                'distance' => 90000,
                'duration' => 3600,
                'geometry' => ['coordinates' => [[3.12, 50.94], [3.39, 50.6]]],
            ]]]),
        ]);

        $route = Osrm::route(50.94, 3.12, 50.6, 3.39, trace: true);

        $this->assertSame(90000, $route['distance']);
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://routing.openstreetmap.de/routed-car/route/v1/driving/3.12,50.94;3.39,50.6')
            && str_starts_with($r->header('User-Agent')[0] ?? '', 'NBLogiTrack/'));
    }

    public function test_sans_aucun_serveur_le_resultat_est_nul(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $this->assertNull(Osrm::route(50.94, 3.12, 50.6, 3.39));
    }
}
