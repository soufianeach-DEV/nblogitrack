<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La carte vectorielle charge ses tuiles chez OpenFreeMap et lance un
 * travailleur servi par l'application : la politique de securite doit
 * autoriser l'un et l'autre, et rien de plus.
 */
class PolitiqueDeSecuriteCarteTest extends TestCase
{
    use RefreshDatabase;

    private function politique(): array
    {
        $entete = (string) $this->get('/fr/login')->assertOk()->headers->get('Content-Security-Policy');

        return collect(explode(';', $entete))
            ->map(fn (string $d) => preg_split('/\s+/', trim($d)))
            ->filter(fn (array $d) => $d[0] !== '')
            ->mapWithKeys(fn (array $d) => [$d[0] => array_slice($d, 1)])
            ->all();
    }

    public function test_les_tuiles_vectorielles_et_le_travailleur_sont_autorises(): void
    {
        $politique = $this->politique();

        $this->assertContains('https://tiles.openfreemap.org', $politique['connect-src']);
        $this->assertSame(["'self'"], $politique['worker-src']);
        // Carte de secours sans WebGL 2.
        $this->assertContains('https://tile.openstreetmap.org', $politique['img-src']);
    }

    public function test_aucun_script_exterieur_n_est_autorise(): void
    {
        $politique = $this->politique();

        foreach ($politique['script-src'] as $source) {
            $this->assertTrue($source === "'self'" || str_starts_with($source, "'nonce-"), $source);
        }

        $this->assertNotContains('blob:', $politique['worker-src']);
    }
}
