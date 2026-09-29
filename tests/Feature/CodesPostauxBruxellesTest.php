<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Bruxelles : les codes des dix-neuf communes, pas seulement 1000. */
class CodesPostauxBruxellesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['1000', 'Bruxelles'], ['1030', 'Schaerbeek'], ['1050', 'Ixelles'], ['1210', 'Saint-Josse-ten-Noode'], ['1300', 'Wavre'], ['9000', 'Gent']] as [$code, $ville]) {
            DB::table('postal_codes')->insert(['country_code' => 'BE', 'code' => $code, 'city' => $ville, 'lat' => 50.85, 'lng' => 4.35]);
        }
    }

    private function codes(string $ville): array
    {
        return collect($this->getJson('/geo/codes-postaux?'.http_build_query(['pays' => 'BE', 'ville' => $ville, 'lat' => 50.85, 'lng' => 4.35]))
            ->assertOk()->json())->pluck('code')->all();
    }

    public function test_bruxelles_propose_les_codes_de_toute_la_region(): void
    {
        $this->assertSame(['1000', '1030', '1050', '1210'], $this->codes('Bruxelles'));
        $this->assertSame(['1000', '1030', '1050', '1210'], $this->codes('brussel'));
    }

    public function test_une_autre_ville_garde_ses_seuls_codes(): void
    {
        $this->assertSame(['9000'], $this->codes('Gent'));
        $this->assertSame(['1050'], $this->codes('Ixelles'));
    }

    public function test_bruxelles_ne_remplit_pas_le_code_postal_tout_seul(): void
    {
        $villes = collect($this->getJson('/geo/villes?pays=BE&q=Bruxelles')->assertOk()->json());

        $this->assertNull($villes->firstWhere('ville', 'Bruxelles')['code']);
    }
}
