<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccueilTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_racine_redirige_vers_une_langue(): void
    {
        $this->get('/')->assertRedirect();
    }

    public function test_la_page_d_accueil_s_affiche(): void
    {
        $this->get('/fr')->assertOk();
    }

    public function test_les_trois_langues_repondent(): void
    {
        foreach (['fr', 'nl', 'en'] as $langue) {
            $this->get('/'.$langue)->assertOk();
        }
    }

    public function test_une_adresse_sans_prefixe_rejoint_la_langue_en_cours(): void
    {
        $this->get('/de')->assertRedirect('/fr/de');
        $this->get('/factures')->assertRedirect('/fr/factures');
    }

    public function test_une_page_inexistante_sous_une_langue_servie_rend_404(): void
    {
        $this->get('/fr/cette-page-n-existe-pas')->assertNotFound();
        $this->get('/nl/deze-pagina-bestaat-niet')->assertNotFound();
    }
}
