<?php

namespace Tests\Unit;

use App\Support\Secteurs;
use PHPUnit\Framework\TestCase;

class SecteursTest extends TestCase
{
    public function test_un_code_nace_donne_son_secteur(): void
    {
        $this->assertSame('Industrie automobile', Secteurs::depuisNace('29.100'));
        $this->assertSame('Télécommunications', Secteurs::depuisNace('61'));
        // Une classe plus fine que sa division quand elle est listee.
        $this->assertSame('Vente à distance (e-commerce)', Secteurs::depuisNace('47.910'));
        $this->assertSame('Commerce de détail', Secteurs::depuisNace('47.110'));
        $this->assertNull(Secteurs::depuisNace('4'));
        $this->assertNull(Secteurs::depuisNace(null));
    }

    public function test_chaque_division_nace_est_proposee_une_fois(): void
    {
        $valeurs = array_merge(...array_map(fn ($g) => array_column($g['secteurs'], 'valeur'), Secteurs::groupes('fr')));

        // Les 88 divisions NACE Rev. 2 et la vente a distance.
        $this->assertCount(89, $valeurs);
        $this->assertSame($valeurs, array_values(array_unique($valeurs)));
        $this->assertEqualsCanonicalizing(Secteurs::valeurs(), $valeurs);
        $this->assertCount(21, Secteurs::groupes('en'));
    }

    public function test_les_anciens_secteurs_ont_tous_leur_secteur_nace(): void
    {
        foreach (Secteurs::ANCIENS as $ancien => $nouveau) {
            $this->assertContains($nouveau, Secteurs::valeurs(), $ancien);
        }
    }
}
