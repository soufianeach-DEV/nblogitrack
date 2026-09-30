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

    public function test_un_code_postal_a_espace_ne_masque_pas_la_localite(): void
    {
        $cas = [
            'Ermou 10, 105 57 Athènes, Grèce' => 'Athènes',
            'Ermou 10, 10557 Athènes, Grèce' => 'Athènes',
            'Tsimiski 5, 546 24 Thessalonique, Grèce' => 'Thessalonique',
            'Václavské náměstí 1, 110 00 Prague, Tchéquie' => 'Prague',
            'Drottninggatan 1, 111 51 Stockholm, Suède' => 'Stockholm',
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

    public function test_les_codes_postaux_de_chaque_pays_se_lisent(): void
    {
        $cas = [
            'Damrak 1, 1012 LG Amsterdam, Pays-Bas' => ['1012 LG', 'Amsterdam'],
            'Damrak 1, 1012LG Amsterdam, Pays-Bas' => ['1012LG', 'Amsterdam'],
            'Marszałkowska 1, 00-950 Varsovie, Pologne' => ['00-950', 'Varsovie'],
            'Rua Augusta 1, 1000-001 Lisbonne, Portugal' => ['1000-001', 'Lisbonne'],
            'Brīvības iela 1, LV-1050 Riga, Lettonie' => ['LV-1050', 'Riga'],
            'Václavské náměstí 1, 110 00 Prague, Tchéquie' => ['110 00', 'Prague'],
            'Rue Haute 1, 1000 Bruxelles, Belgique' => ['1000', 'Bruxelles'],
        ];

        foreach ($cas as $adresse => [$cp, $ville]) {
            $this->assertSame($ville, Adresse::localite($adresse), $adresse);
            $this->assertSame($cp, Adresse::codePostal($adresse), $adresse);
        }
    }
}
