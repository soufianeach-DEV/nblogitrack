<?php

namespace Tests\Unit;

use App\Support\Adresse;
use App\Support\Trajet;
use Tests\TestCase;

class TrajetTest extends TestCase
{
    public function test_la_zone_est_le_pays_etranger_du_trajet(): void
    {
        $this->assertSame([Trajet::NATIONAL, 'BE'], [(new Trajet('BE', 'BE'))->type(), (new Trajet('BE', 'BE'))->zone()]);
        $this->assertSame([Trajet::EXPORT, 'FR'], [(new Trajet('BE', 'FR'))->type(), (new Trajet('BE', 'FR'))->zone()]);
        $this->assertSame([Trajet::IMPORT, 'FR'], [(new Trajet('FR', 'BE'))->type(), (new Trajet('FR', 'BE'))->zone()]);
        $this->assertSame('FR', (new Trajet('fr', 'be'))->zone());
    }

    public function test_un_trajet_entre_deux_pays_etrangers_se_traite_sur_devis(): void
    {
        $this->assertNull((new Trajet('FR', 'DE'))->zone());
        $this->assertSame(Trajet::CABOTAGE, (new Trajet('FR', 'FR'))->type());
        $this->assertNull((new Trajet('FR', 'FR'))->zone());
        $this->assertStringContainsString('ni ne finit en Belgique', (string) (new Trajet('FR', 'DE'))->refus());
    }

    public function test_les_pays_sur_devis_et_non_desservis_sont_refuses(): void
    {
        $this->assertStringContainsString('sur devis', (string) (new Trajet('GB', 'BE'))->refus());
        $this->assertStringContainsString('pas encore en ligne', (string) (new Trajet('TR', 'BE'))->refus());
        $this->assertNull((new Trajet('PT', 'BE'))->refus());
        $this->assertNull((new Trajet('PL', 'BE'))->refus());
    }

    public function test_les_iles_passent_par_un_devis(): void
    {
        $this->assertTrue(Trajet::codePostalExclu('FR', '20000'));
        $this->assertTrue(Trajet::codePostalExclu('ES', '07001'));
        $this->assertTrue(Trajet::codePostalExclu('PT', '9000-001'));
        $this->assertFalse(Trajet::codePostalExclu('FR', '69001'));
        $this->assertFalse(Trajet::codePostalExclu('ES', '28001'));
    }

    public function test_le_pays_ecrit_dans_l_adresse_doit_etre_celui_declare(): void
    {
        $this->assertTrue(Adresse::paysCoherent('Rue X 1, 59000 Lille, France', 'FR', true));
        $this->assertFalse(Adresse::paysCoherent('Rue X 1, 59000 Lille, France', 'BE', true));
        $this->assertFalse(Adresse::paysCoherent('Rue X 1, 59000 Lille', 'FR', true));
        $this->assertTrue(Adresse::paysCoherent('Rue X 1, 59000 Lille', 'FR', false));
    }
}
