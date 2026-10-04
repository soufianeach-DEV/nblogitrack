<?php

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** L'article 9 annonce le plafond de credit et le blocage a trois factures en retard. */
class ConditionsPlafondCreditTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_23_100000_annoncer_le_plafond_de_credit.php');
    }

    public function test_l_article_9_annonce_la_regle_dans_les_trois_langues(): void
    {
        $this->seed(PageSeeder::class);
        $cgv = Page::where('slug', 'conditions-generales')->sole();

        $this->assertStringContainsString('trois factures restent impayées', $cgv->corps_fr);
        $this->assertStringContainsString('drie facturen na hun vervaldag', $cgv->corps_nl);
        $this->assertStringContainsString('three invoices remain unpaid', $cgv->corps_en);
    }

    public function test_la_migration_ajoute_le_paragraphe_une_seule_fois(): void
    {
        $this->seed(PageSeeder::class);
        $cgv = Page::where('slug', 'conditions-generales')->sole();

        // Conditions publiees avant ce paragraphe.
        $avant = fn (string $texte) => preg_replace('/\n\n(Le transporteur peut fixer|De vervoerder kan voor elke|The carrier may set a payment term)[^\n]*/u', '', $texte);
        $cgv->forceFill([
            'corps_fr' => $avant($cgv->corps_fr),
            'corps_nl' => $avant($cgv->corps_nl),
            'corps_en' => $avant($cgv->corps_en),
        ])->save();
        $this->assertStringNotContainsString('trois factures', $cgv->fresh()->corps_fr);

        $this->migration()->up();
        $this->migration()->up();

        $cgv->refresh();
        $this->assertSame(1, substr_count($cgv->corps_fr, 'trois factures restent impayées'));
        $this->assertSame(1, substr_count($cgv->corps_nl, 'drie facturen na hun vervaldag'));
        $this->assertSame(1, substr_count($cgv->corps_en, 'three invoices remain unpaid'));
        $this->assertNotNull($cgv->contenu_modifie_le);

        // Le paragraphe suit la phrase sur la suspension des prestations.
        $this->assertMatchesRegularExpression('/notification écrite\.\n\nLe transporteur peut fixer/u', $cgv->corps_fr);
    }
}
