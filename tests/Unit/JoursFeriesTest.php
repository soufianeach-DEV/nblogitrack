<?php

namespace Tests\Unit;

use App\Support\JoursFeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JoursFeriesTest extends TestCase
{
    // Les messages et les feries belges passent par les traductions en base.
    use RefreshDatabase;

    public function test_chaque_pays_a_son_calendrier(): void
    {
        $this->assertTrue(JoursFeries::chome(Carbon::parse('2027-07-14'), 'FR'));
        $this->assertFalse(JoursFeries::chome(Carbon::parse('2027-07-14'), 'BE'));
        $this->assertTrue(JoursFeries::chome(Carbon::parse('2027-07-21'), 'BE'));
        $this->assertFalse(JoursFeries::chome(Carbon::parse('2027-07-21'), 'FR'));
        $this->assertTrue(JoursFeries::est(Carbon::parse('2026-10-03'), 'DE'));
        $this->assertTrue(JoursFeries::est(Carbon::parse('2025-04-26'), 'NL'));
        $this->assertTrue(JoursFeries::est(Carbon::parse('2027-06-23'), 'LU'));
    }

    public function test_les_feries_regionaux_suivent_le_land_et_l_alsace_moselle(): void
    {
        $nrw = JoursFeries::region('DE', null, 'Nordrhein-Westfalen');
        $hambourg = JoursFeries::region('DE', null, 'Hamburg');

        // Fete-Dieu : Rhenanie-du-Nord-Westphalie oui, Hambourg non.
        $this->assertTrue(JoursFeries::est(Carbon::parse('2027-05-27'), 'DE', $nrw));
        $this->assertFalse(JoursFeries::est(Carbon::parse('2027-05-27'), 'DE', $hambourg));
        // Land inconnu : tous les feries de tous les Lander, par prudence.
        $this->assertTrue(JoursFeries::est(Carbon::parse('2027-05-27'), 'DE'));

        // Saint-Etienne : ferie a Strasbourg, pas a Lyon.
        $this->assertTrue(JoursFeries::est(Carbon::parse('2026-12-26'), 'FR', JoursFeries::region('FR', '67000', null)));
        $this->assertFalse(JoursFeries::est(Carbon::parse('2026-12-26'), 'FR', JoursFeries::region('FR', '69001', null)));
    }

    public function test_le_calendrier_belge_ne_change_pas(): void
    {
        $this->assertSame(JoursFeries::pour(2026), JoursFeries::pour(2026, 'BE'));
        $this->assertCount(10, JoursFeries::pour(2026));
    }
}
