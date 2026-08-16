<?php

namespace Tests\Unit;

use App\Support\Adresse;
use PHPUnit\Framework\TestCase;

/**
 * Ces deux fonctions ne touchent ni la base ni le reseau : elles lisent
 * une chaine et rendent une chaine. C'est le seul endroit de
 * l'application ou un test unitaire au sens strict a du sens.
 */
class AdresseTest extends TestCase
{
    /**
     * Le jeu de donnees s'arrete a la localite, le formulaire ajoute le
     * pays. Prendre le dernier segment rendait donc « Belgique » pour
     * toutes les commandes passees en ligne, d'ou la recherche du
     * segment qui porte le code postal.
     */
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

    /**
     * Sans pays ecrit, on rend null : prendre la ville pour un pays
     * ferait tarifer un Bruxelles-Paris comme un trajet national.
     */
    public function test_le_pays_est_nul_quand_l_adresse_ne_le_porte_pas(): void
    {
        $this->assertNull(Adresse::pays('Rue Neuve 43, 3500 Hasselt'));
        $this->assertNull(Adresse::pays('Hasselt'));
        $this->assertNull(Adresse::pays(''));
    }
}
