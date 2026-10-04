<?php

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/** La politique des cookies nomme les temoins que l'application depose. */
class PolitiqueCookiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_politique_nomme_les_temoins_de_session_et_de_connexion_dans_les_trois_langues(): void
    {
        $this->seed(PageSeeder::class);
        $politique = Page::where('slug', 'politique-cookies')->sole();

        // remember_web_ suivi de l'empreinte de la classe du garde.
        $souvenir = Str::beforeLast(Auth::guard('web')->getRecallerName(), '_').'_';

        foreach (['corps_fr', 'corps_nl', 'corps_en'] as $colonne) {
            $this->assertStringContainsString('- '.config('session.cookie').' —', $politique->{$colonne});
            $this->assertStringContainsString('- '.$souvenir.' ', $politique->{$colonne});
            $this->assertStringNotContainsString('nblogitrack_session', $politique->{$colonne});
        }
    }
}
