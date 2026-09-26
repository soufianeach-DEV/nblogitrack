<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Derriere un repartiteur de charge declare, HTTPS et l'adresse du visiteur sont reconnus. */
class ProxysDeConfianceTest extends TestCase
{
    use RefreshDatabase;

    private function visite()
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'])
            ->get('/fr');
    }

    public function test_sans_proxy_declare_les_en_tetes_transmis_sont_ignores(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->visite()->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_un_proxy_declare_retablit_https(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.1']);

        $this->visite()->assertHeader('Strict-Transport-Security');
    }
}
