<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BaseDeTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_tests_tournent_sur_une_base_jetable(): void
    {
        $nom = DB::connection()->getDatabaseName();

        $this->assertStringEndsWith('_test', $nom,
            'La suite tourne sur « '.$nom.' », qui n\'est pas une base jetable.');
        $this->assertNotSame('nblogitrack', $nom);
    }

    public function test_les_tests_tournent_bien_sur_postgresql(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_la_base_de_test_part_vide_a_chaque_classe(): void
    {
        $this->assertSame(0, User::count());

        User::factory()->count(3)->create();

        $this->assertSame(3, User::count());
    }
}
