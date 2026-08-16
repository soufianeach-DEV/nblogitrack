<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le test qui protege tous les autres.
 *
 * Chaque classe de test vide la base avant de commencer. Si la
 * configuration pointait un jour sur « nblogitrack », une seule
 * execution effacerait trois cents expeditions, cent trente-sept
 * factures et cent quarante-neuf entreprises, sans confirmation.
 *
 * Deux protections se superposent. Le fichier phpunit.xml impose le nom
 * de la base avec « force », ce qui empeche une variable du terminal de
 * l'emporter. Et Tests\TestCase refuse de demarrer si ce nom ne se
 * termine pas par « _test », au cas ou quelqu'un modifierait le fichier.
 *
 * Cette classe verifie la premiere. La seconde ne peut pas se tester
 * ici : elle protege justement contre le cas ou ce test tournerait au
 * mauvais endroit.
 */
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
        // Le code ecrit « ilike », « filter (where ...) », « to_char » et
        // un verrou consultatif. Une suite qui passerait sur SQLite ne
        // prouverait rien de ce qui tourne en production.
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_la_base_de_test_part_vide_a_chaque_classe(): void
    {
        $this->assertSame(0, User::count());

        User::factory()->count(3)->create();

        $this->assertSame(3, User::count());
    }
}
