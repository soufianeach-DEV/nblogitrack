<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PageView;
use App\Models\User;
use App\Support\Audience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Mesure d'audience soumise au consentement, ecran d'audience,
 * referencement (balises, plan du site, robots.txt).
 */
class AudienceReferencementTest extends TestCase
{
    use RefreshDatabase;

    private function avecAccord()
    {
        return $this->withUnencryptedCookie(Audience::TEMOIN, 'audience');
    }

    public function test_sans_accord_rien_n_est_mesure(): void
    {
        $this->get('/fr/tarifs')->assertOk();
        $this->withUnencryptedCookie(Audience::TEMOIN, 'essentiels')->get('/fr/devis')->assertOk();

        $this->assertSame(0, PageView::count());
    }

    public function test_une_arrivee_depuis_google_est_comptee_avec_sa_provenance(): void
    {
        $this->avecAccord()->withHeader('Referer', 'https://www.google.be/')->get('/fr/tarifs')->assertOk();

        $vue = PageView::sole();
        $this->assertSame('/tarifs', $vue->chemin);
        $this->assertSame('fr', $vue->langue);
        $this->assertTrue($vue->entree);
        $this->assertSame('Google', $vue->source);
        $this->assertNull($vue->evenement);
    }

    public function test_une_campagne_est_reconnue_et_la_navigation_interne_n_est_pas_une_arrivee(): void
    {
        $this->avecAccord()->get('/nl/devis?utm_source=linkedin&utm_medium=social&utm_campaign=lancement')->assertOk();
        $this->avecAccord()->withHeader('X-Inertia', 'true')->withHeader('Referer', 'http://localhost/nl/devis')->get('/nl/tarifs');

        [$arrivee, $interne] = PageView::orderBy('id')->get()->all();
        $this->assertSame(['LinkedIn', 'social', 'lancement', true], [$arrivee->source, $arrivee->support, $arrivee->campagne, $arrivee->entree]);
        $this->assertFalse($interne->entree);
        $this->assertNull($interne->source);
    }

    public function test_comptes_connectes_robots_et_pages_privees_ne_sont_pas_comptes(): void
    {
        $this->avecAccord()->actingAs(Client::factory()->create()->compte())->get('/fr/tarifs')->assertOk();
        $this->avecAccord()->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get('/fr/tarifs')->assertOk();
        $this->avecAccord()->get('/robots.txt')->assertOk();

        $this->assertSame(0, PageView::count());
    }

    public function test_une_conversion_est_comptee_avec_l_accord(): void
    {
        $requete = Request::create('/fr/devis', 'POST', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone)']);
        $requete->cookies->set(Audience::TEMOIN, 'audience');
        Audience::noterEvenement($requete, 'devis');

        $sansAccord = Request::create('/fr/devis', 'POST');
        Audience::noterEvenement($sansAccord, 'devis');

        $this->assertSame(1, PageView::where('evenement', 'devis')->count());
        $this->assertSame('mobile', PageView::sole()->appareil);
    }

    public function test_l_administrateur_voit_l_audience_et_le_planificateur_non(): void
    {
        PageView::create(['jour' => today(), 'chemin' => '/', 'langue' => 'fr', 'entree' => true, 'source' => 'Google', 'appareil' => 'ordinateur']);
        PageView::create(['jour' => today(), 'chemin' => '/tarifs', 'langue' => 'fr', 'entree' => false, 'appareil' => 'mobile']);
        PageView::create(['jour' => today(), 'chemin' => '/devis', 'langue' => 'fr', 'evenement' => 'devis', 'appareil' => 'mobile']);

        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('audience.index', ['jours' => 7]))
            ->assertInertia(fn (Assert $page) => $page->component('Audience/Index')
                ->where('totaux.vues', 2)
                ->where('totaux.entrees', 1)
                ->where('totaux.devis', 1)
                ->where('totaux.conversion', 100)
                ->where('sources.0.libelle', 'Google')
                ->has('serie', 7));

        $this->actingAs(User::factory()->planificateur()->create())->get(route('audience.index'))->assertForbidden();
    }

    public function test_les_pages_publiques_portent_titre_description_et_apercu_de_partage(): void
    {
        $html = $this->get('/fr/tarifs')->assertOk()->getContent();

        $this->assertStringContainsString('<title data-inertia>Tarifs de transport et simulateur de prix - ', $html);
        $this->assertStringContainsString('<meta name="description" content="Simulez le prix', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.url('/images/partage.png').'">', $html);
        $this->assertStringNotContainsString('noindex', $html);

        // L'accueil porte la fiche de l'entreprise pour les moteurs.
        $this->assertStringContainsString('"@type":"Organization"', $this->get('/fr')->getContent());
    }

    public function test_l_espace_client_n_est_pas_indexe(): void
    {
        $html = $this->actingAs(Client::factory()->create()->compte())->get('/fr/dashboard')->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
    }

    public function test_plan_du_site_et_robots(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('/sitemap.xml'), false)->assertSee('Disallow: /api/', false);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('<loc>'.url('/nl/tarifs').'</loc>', $xml);
        $this->assertStringContainsString('hreflang="en-BE" href="'.url('/en/devis').'"', $xml);
        $this->assertStringNotContainsString('/dashboard', $xml);
    }

    public function test_la_mesure_est_effacee_apres_treize_mois(): void
    {
        PageView::create(['jour' => now()->subMonths(14), 'chemin' => '/', 'langue' => 'fr', 'appareil' => 'mobile']);
        PageView::create(['jour' => now()->subMonths(2), 'chemin' => '/', 'langue' => 'fr', 'appareil' => 'mobile']);

        $this->artisan('journaux:purger')->assertSuccessful();

        $this->assertSame(1, PageView::count());
    }
}
