<?php

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les conditions generales annoncent l'absence de droit de retractation. */
class DroitDeRetractationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_20_100000_annoncer_l_absence_de_droit_de_retractation.php');
    }

    public function test_les_conditions_generales_contiennent_l_article_dans_les_trois_langues(): void
    {
        $this->seed(PageSeeder::class);
        $cgv = Page::where('slug', 'conditions-generales')->sole();

        $this->assertStringContainsString('## Article 8 ter — Droit de rétractation', $cgv->corps_fr);
        $this->assertStringContainsString('VI.53, 12°', $cgv->corps_fr);
        $this->assertStringContainsString('## Artikel 8 ter — Herroepingsrecht', $cgv->corps_nl);
        $this->assertStringContainsString('## Article 8b — Right of withdrawal', $cgv->corps_en);
    }

    public function test_la_migration_ajoute_l_article_une_seule_fois_aux_conditions_deja_en_base(): void
    {
        $this->seed(PageSeeder::class);
        $cgv = Page::where('slug', 'conditions-generales')->sole();

        // Conditions publiees avant l'article 8 ter.
        $avant = fn (string $texte) => preg_replace('/\n\n## (Article|Artikel) 8 ?(ter|b) —.*?(?=\n\n## )/su', '', $texte);
        $cgv->forceFill([
            'corps_fr' => $avant($cgv->corps_fr),
            'corps_nl' => $avant($cgv->corps_nl),
            'corps_en' => $avant($cgv->corps_en),
        ])->saveQuietly();
        $this->assertStringNotContainsString('8 ter', $cgv->fresh()->corps_fr);

        $this->migration()->up();
        $this->migration()->up();

        $cgv = $cgv->fresh();
        $this->assertSame(1, substr_count($cgv->corps_fr, '## Article 8 ter'));
        $this->assertSame(1, substr_count($cgv->corps_nl, '## Artikel 8 ter'));
        $this->assertSame(1, substr_count($cgv->corps_en, '## Article 8b'));
        // Juste apres l'article 8 bis, avant l'article 9.
        $this->assertLessThan(strpos($cgv->corps_fr, '## Article 9'), strpos($cgv->corps_fr, '## Article 8 ter'));
        $this->assertGreaterThan(strpos($cgv->corps_fr, '## Article 8 bis'), strpos($cgv->corps_fr, '## Article 8 ter'));
    }
}
