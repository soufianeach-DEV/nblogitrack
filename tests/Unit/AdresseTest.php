<?php

namespace Tests\Unit;

use App\Support\Adresse;
use PHPUnit\Framework\TestCase;

class AdresseTest extends TestCase
{
    public function test_la_localite_se_lit_apres_le_code_postal(): void
    {
        $cas = [
            'Rue Neuve 43, 3500 Hasselt' => 'Hasselt',
            'Rue Haute 100, 1000 Bruxelles, Belgique' => 'Bruxelles',
            'Rue de Rivoli 10, 75001 Paris, France' => 'Paris',
            'Grote Markt 1, 2000 Antwerpen' => 'Antwerpen',
            'Avenue Louise 200, 1050 Ixelles, Belgique' => 'Ixelles',
        ];

        foreach ($cas as $adresse => $attendu) {
            $this->assertSame($attendu, Adresse::localite($adresse), $adresse);
        }
    }

    public function test_la_localite_se_rabat_sur_le_dernier_segment(): void
    {
        $this->assertSame('Bruxelles', Adresse::localite('Rue Haute 100, Bruxelles'));
        $this->assertSame('Hasselt', Adresse::localite('Hasselt'));
    }

    public function test_le_pays_se_lit_quand_l_adresse_le_porte(): void
    {
        $this->assertSame('Belgique', Adresse::pays('Rue Haute 100, 1000 Bruxelles, Belgique'));
        $this->assertSame('France', Adresse::pays('Rue de Rivoli 10, 75001 Paris, France'));
    }

    public function test_le_pays_est_nul_quand_l_adresse_ne_le_porte_pas(): void
    {
        $this->assertNull(Adresse::pays('Rue Neuve 43, 3500 Hasselt'));
        $this->assertNull(Adresse::pays('Hasselt'));
        $this->assertNull(Adresse::pays(''));
    }
}
