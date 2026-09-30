<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Support\Traductions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le dictionnaire part une fois, puis le navigateur le garde. */
class DictionnaireUneFoisTest extends TestCase
{
    use RefreshDatabase;

    private function visite(array $entetes = [])
    {
        $version = app(HandleInertiaRequests::class)->version(request());

        return $this->actingAs(User::factory()->planificateur()->create())
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version, ...$entetes])
            ->get(route('dashboard'));
    }

    public function test_le_dictionnaire_deja_recu_n_est_pas_renvoye(): void
    {
        $cle = 'dictionnaire.fr.'.Traductions::version();

        $this->assertArrayHasKey('dictionnaire', $this->visite()->json('props'));
        $this->assertArrayNotHasKey('dictionnaire', $this->visite(['X-Inertia-Except-Once-Props' => $cle])->json('props'));
    }

    public function test_une_modification_du_dictionnaire_le_renvoie(): void
    {
        $ancienne = 'dictionnaire.fr.'.Traductions::version();
        Traductions::oublier();

        $this->assertNotSame($ancienne, 'dictionnaire.fr.'.Traductions::version());
        $this->assertArrayHasKey('dictionnaire', $this->visite(['X-Inertia-Except-Once-Props' => $ancienne])->json('props'));
    }
}
