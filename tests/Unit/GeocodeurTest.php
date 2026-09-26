<?php

namespace Tests\Unit;

use App\Support\Geocodeur;
use PHPUnit\Framework\TestCase;

class GeocodeurTest extends TestCase
{
    public function test_l_emprise_grecque_couvre_le_continent_et_les_iles_extremes(): void
    {
        // Athenes, Heraklion, Rhodes, Kastellorizo, Corfou, Gavdos, Ormenio.
        foreach ([[37.9838, 23.7275], [35.3387, 25.1442], [36.4341, 28.2176], [36.1486, 29.5943], [39.6243, 19.9217], [34.8450, 24.0850], [41.7300, 26.2100]] as [$lat, $lng]) {
            $this->assertTrue(Geocodeur::dansLEmprise($lat, $lng, 'GR'), "{$lat},{$lng}");
        }

        // Bruxelles, Rome, Sofia.
        foreach ([[50.8504, 4.3488], [41.9028, 12.4964], [42.6977, 23.3219]] as [$lat, $lng]) {
            $this->assertFalse(Geocodeur::dansLEmprise($lat, $lng, 'GR'), "{$lat},{$lng}");
        }
    }

    public function test_seuls_les_pays_listes_passent_en_ligne(): void
    {
        $this->assertTrue(Geocodeur::couvre('gr'));
        $this->assertFalse(Geocodeur::couvre('BE'));
        $this->assertFalse(Geocodeur::dansLEmprise(37.98, 23.72, 'IT'));
    }
}
