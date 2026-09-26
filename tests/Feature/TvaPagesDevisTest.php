<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Page;
use App\Models\QuoteRequest;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\IdentifiantEntreprise;
use App\Support\Traductions;
use Database\Seeders\TranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Numeros de TVA belges controles sur place, titres des pages, choix des
 * demandes de devis et historique du suivi dans la langue de l'ecran.
 */
class TvaPagesDevisTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function inscription(array $remplace = []): array
    {
        return array_merge([
            'company_name' => 'Transports Essai SRL',
            'vat_number' => 'BE0203201340',
            'billing_address' => 'Avenue Louise 100',
            'postal_code' => '1050',
            'city' => 'Bruxelles',
            'country' => 'Belgique',
            'business_sector' => 'Transport',
            'first_name' => 'Soufiane',
            'last_name' => 'Achraa',
            'phone' => '+32 470 00 00 00',
            'email' => 'contact@transports-essai.be',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
            'marque_declaree' => true,
            'conditions_acceptees' => true,
        ], $remplace);
    }

    private function registreRepond(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response([
                'isValid' => true,
                'name' => 'TRANSPORTS ESSAI SRL',
                'address' => 'AVENUE LOUISE 100, 1050 BRUXELLES',
            ]),
            'kbopub.economie.fgov.be/*' => Http::response('<html><body></body></html>'),
            '*' => Http::response([], 200),
        ]);
    }

    private function traductions(): void
    {
        $this->seed(TranslationSeeder::class);
        Traductions::oublier();
    }

    public function test_le_controle_belge_verifie_la_longueur_le_premier_chiffre_et_la_cle(): void
    {
        foreach (['BE0203201340', 'BE0123456749', 'BE1000000021', 'BE203201340', 'be 0203.201.340'] as $valide) {
            $this->assertTrue(IdentifiantEntreprise::controleLocal($valide), $valide);
        }

        // Onze chiffres, cle fausse, premier chiffre 2, lettres.
        foreach (['BE02921506871', 'BE0203201341', 'BE2203201340', 'BE0203201ABC'] as $invalide) {
            $this->assertFalse(IdentifiantEntreprise::controleLocal($invalide), $invalide);
        }
    }

    public function test_les_autres_pays_ne_sont_pas_controles_sur_place(): void
    {
        foreach (['FR12345678901', 'NL123456789B01', 'DE123456789', '123456789'] as $numero) {
            $this->assertTrue(IdentifiantEntreprise::controleLocal($numero), $numero);
        }
    }

    public function test_un_ancien_numero_a_neuf_chiffres_prend_sa_forme_actuelle(): void
    {
        $identifiant = IdentifiantEntreprise::analyser('BE203201340');

        $this->assertSame('BE0203201340', $identifiant['tva']);
        $this->assertSame('0203201340', $identifiant['national']);
    }

    public function test_l_inscription_refuse_un_numero_belge_invalide_sans_interroger_le_registre(): void
    {
        Http::fake();

        foreach (['BE02921506871', 'BE0203201341'] as $numero) {
            $this->post(route('register'), $this->inscription(['vat_number' => $numero]))
                ->assertSessionHasErrors('vat_number');
        }

        Http::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'contact@transports-essai.be']);
        $this->assertSame(0, Client::count());
    }

    public function test_l_inscription_range_un_ancien_numero_sous_sa_forme_actuelle(): void
    {
        $this->registreRepond();

        $this->post(route('register'), $this->inscription(['vat_number' => 'BE203201340']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clients', ['vat_number' => 'BE0203201340', 'enterprise_number' => '0203201340']);
    }

    public function test_la_liste_des_pages_montre_le_titre_dans_la_langue_de_l_ecran(): void
    {
        Page::create([
            'slug' => 'conditions-generales',
            'titre_fr' => 'Conditions générales',
            'titre_nl' => 'Algemene voorwaarden',
            'corps_fr' => 'Texte',
            'rang' => 1,
        ]);
        Page::create([
            'slug' => 'mentions-legales',
            'titre_fr' => 'Mentions légales',
            'corps_fr' => 'Texte',
            'rang' => 2,
        ]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('pages.index', ['langue' => 'nl']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Pages/Index')
                ->where('pages.0.titre', 'Algemene voorwaarden')
                // Sans titre neerlandais, le francais sert de repli.
                ->where('pages.1.titre', 'Mentions légales')
                ->where('pages.0.titre_fr', 'Conditions générales'));
    }

    public function test_les_choix_d_une_demande_de_devis_sont_traduits_pour_le_personnel(): void
    {
        $this->traductions();

        QuoteRequest::create([
            'reference' => 'DEV-2026-0001',
            'company_name' => 'Essai SRL',
            'contact_name' => 'Nadia Peeters',
            'email' => 'nadia@exemple.be',
            'phone' => '+32 470 00 00 00',
            'customer_type' => 'Nouvelle entreprise',
            'pickup_address' => 'Rue Neuve 43, 1000 Bruxelles',
            'pickup_lat' => 50.8504,
            'pickup_lng' => 4.3488,
            'delivery_address' => 'Meir 50, 2000 Anvers',
            'delivery_lat' => 51.2194,
            'delivery_lng' => 4.4025,
            'delivery_country' => 'BE',
            'pickup_date' => now()->addWeek()->toDateString(),
            'trip_type' => 'National (Belgique)',
            'frequency' => 'Transport ponctuel',
            'date_flexibility' => 'Flexible',
            'goods_type' => TransportOrder::MARCHANDISES[0],
            'vehicle_type' => 'Porteur',
            'insurance_value' => 'Plus de 50 000 €',
            'weight' => 1000,
            'status' => 'PENDING',
        ]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('quotes.index', ['langue' => 'nl']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Devis/Index')
                ->where('demandes.data.0.customer_type', 'Nouvelle entreprise')
                ->where('libelles.Nouvelle entreprise', 'Nieuwe onderneming')
                ->where('libelles.Client régulier / entreprise', 'Vaste klant / onderneming')
                ->where('libelles.National (Belgique)', 'Nationaal (België)')
                ->where('libelles.Import vers la Belgique', 'Import naar België')
                ->where('libelles.Transport ponctuel', 'Eenmalig transport')
                ->where('libelles.Flexible', 'Flexibel')
                ->where('libelles.Date fixe', 'Vaste datum')
                ->where('libelles.Porteur', 'Bakwagen')
                ->where('libelles.À conseiller selon la marchandise', 'Te adviseren volgens de goederen')
                ->where('libelles.Plus de 50 000 €', 'Meer dan 50 000 €'));
    }

    public function test_l_historique_du_suivi_est_traduit_sans_identifiant_interne(): void
    {
        $this->traductions();

        $ordre = TransportOrder::factory()->livree()->create();

        $this->travelTo(now()->subHours(3), fn () => ActivityLog::record(
            'order.assigned',
            'Affectation de l\'ordre '.$ordre->tracking_number.' au véhicule 1-ABC-123',
            $ordre,
            ['vehicule' => '1-ABC-123 Volvo FH', 'chauffeur_id' => 4242, 'statut' => 'PENDING → ASSIGNED'],
        ));
        $this->travelTo(now()->subHours(2), fn () => ActivityLog::record(
            'order.reassigned',
            'Réaffectation de l\'ordre '.$ordre->tracking_number.' au véhicule 2-DEF-456 : panne',
            $ordre,
            [
                'motif' => 'Panne moteur',
                'statut' => 'ASSIGNED',
                'ancien_camion' => '1-ABC-123',
                'ancien_chauffeur_id' => 4242,
                'vehicule' => '2-DEF-456 Volvo FH',
                'chauffeur_id' => 4343,
            ],
        ));
        $this->travelTo(now()->subHour(), fn () => ActivityLog::record(
            'order.status_changed',
            'Ordre '.$ordre->tracking_number.' : statut IN_PROGRESS → DELIVERED',
            $ordre,
            ['avant' => 'IN_PROGRESS', 'apres' => 'DELIVERED'],
        ));

        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('tracking.show', ['langue' => 'nl', 'tracking_number' => $ordre->tracking_number]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tracking/Show')
                ->has('historique', 3)
                ->missing('historique.0.description')
                ->where('historique.0.libelle', 'Toewijzing')
                ->where('historique.0.detail', '1-ABC-123 Volvo FH')
                ->where('historique.1.libelle', 'Hertoewijzing')
                ->where('historique.1.detail', '1-ABC-123 → 2-DEF-456 Volvo FH · Panne moteur')
                ->where('historique.2.libelle', 'Statuswijziging')
                ->where('historique.2.detail', 'Onderweg → Geleverd'));
    }

    public function test_une_action_inconnue_du_journal_garde_sa_description(): void
    {
        $ordre = TransportOrder::factory()->create();

        ActivityLog::record('order.inconnue', 'Note libre sur l\'ordre', $ordre);

        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('tracking.show', ['tracking_number' => $ordre->tracking_number]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('historique.0.libelle', 'Note libre sur l\'ordre')
                ->where('historique.0.detail', null));
    }

    public function test_la_verification_en_direct_refuse_un_numero_belge_invalide_sans_appeler_vies(): void
    {
        Http::fake();

        $this->getJson(route('vat.verify', ['tva' => 'BE0123456789']))
            ->assertOk()
            ->assertJson(['statut' => 'format']);

        Http::assertNothingSent();
    }

    public function test_le_type_de_trajet_d_un_devis_se_deduit_des_deux_pays(): void
    {
        $this->post(route('devis.store'), [
            'company_name' => 'Essai SRL', 'contact_name' => 'Nadia Peeters', 'email' => 'nadia@exemple.be',
            'phone' => '+32 470 00 00 00', 'customer_type' => 'Nouvelle entreprise',
            'pickup_address' => 'Rue Nationale 1, 59000 Lille, France', 'pickup_country' => 'FR',
            'pickup_lat' => 50.6292, 'pickup_lng' => 3.0573,
            'delivery_address' => 'Meir 50, 2000 Anvers, Belgique', 'delivery_country' => 'BE',
            'delivery_lat' => 51.2194, 'delivery_lng' => 4.4025,
            'pickup_date' => now()->addWeek()->toDateString(),
            // Le formulaire a envoye « National » : le serveur a le dernier mot.
            'trip_type' => 'National (Belgique)',
            'frequency' => 'Transport ponctuel', 'date_flexibility' => 'Flexible',
            'goods_type' => TransportOrder::MARCHANDISES[0], 'vehicle_type' => 'Porteur',
            'insurance_value' => 'Plus de 50 000 €', 'weight' => 1000,
        ])->assertSessionHasNoErrors();

        $devis = QuoteRequest::firstOrFail();
        $this->assertSame('Import vers la Belgique', $devis->trip_type);
        $this->assertSame('FR', $devis->pickup_country);
    }
}
