<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_la_page_swagger_s_affiche_sans_cle(): void
    {
        $this->get('/api/docs')
            ->assertOk()
            ->assertSee(route('api.docs.specification'), false);
    }

    public function test_la_specification_annonce_ce_serveur_en_premier(): void
    {
        $reponse = $this->get('/api/docs/openapi.yaml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/yaml; charset=UTF-8');

        $this->assertStringContainsString("servers:\n  - url: ".url('/api/v1')."\n", $reponse->getContent());
        $this->assertStringContainsString('/expeditions/{numero}:', $reponse->getContent());
    }
}
