<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\Client;
use App\Models\Driver;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Facturier;
use App\Support\Pays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Les constats de l'audit de securite, chacun fixe par un test pour
 * qu'une modification future ne le rouvre pas.
 */
class CorrectionsAuditTest extends TestCase
{
    use RefreshDatabase;

    private function livree(Client $client, string $quand, float $prix = 500): TransportOrder
    {
        return TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => $quand,
            'estimated_cost' => $prix,
        ]);
    }

    private function chauffeur(): Driver
    {
        $utilisateur = User::factory()->chauffeur()->create();

        return Driver::create([
            'id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'birth_date' => '1980-05-12',
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'departure_reason' => null,
            'is_available' => true,
        ]);
    }

    // --- API ---------------------------------------------------------------

    public function test_la_cle_d_une_entreprise_supprimee_part_avec_elle(): void
    {
        $client = Client::factory()->create();
        [$cle, $jeton] = ApiKey::generer([
            'name' => 'Cle du partenaire',
            'client_id' => $client->id,
            'abilities' => ['lecture'],
            'created_by' => User::factory()->administrateur()->create()->id,
        ]);

        TransportOrder::factory()->create();

        User::find($client->id)->delete();

        $this->assertNull(ApiKey::find($cle->id));
        $this->getJson('/api/v1/expeditions', ['Authorization' => 'Bearer '.$jeton])
            ->assertUnauthorized();
    }

    public function test_le_journal_des_appels_d_api_est_purge(): void
    {
        $ancien = ApiRequest::create([
            'method' => 'GET', 'path' => 'api/v1/expeditions', 'status' => 401,
            'ip_address' => '203.0.113.9', 'duration_ms' => 3, 'refus' => 'jeton_absent',
        ]);
        $ancien->forceFill(['created_at' => now()->subMonths(13)])->save();

        $recent = ApiRequest::create([
            'method' => 'GET', 'path' => 'api/v1/expeditions', 'status' => 401,
            'ip_address' => '203.0.113.9', 'duration_ms' => 3, 'refus' => 'jeton_absent',
        ]);

        $this->artisan('journaux:purger', ['--mois' => 12])->assertSuccessful();

        $this->assertNull(ApiRequest::find($ancien->id));
        $this->assertNotNull(ApiRequest::find($recent->id));
    }

    // --- Argent ------------------------------------------------------------

    public function test_un_client_belge_inscrit_dans_une_autre_langue_paie_la_tva(): void
    {
        foreach (['België', 'Belgium'] as $pays) {
            $client = Client::factory()->create(['country' => $pays]);
            $this->livree($client, '2026-03-04');
        }

        foreach (app(Facturier::class)->facturer() as $facture) {
            $this->assertFalse((bool) $facture->reverse_charge);
            $this->assertEquals(21, (float) $facture->vat_rate);
        }
    }

    public function test_le_pays_est_range_sous_son_nom_francais(): void
    {
        $this->assertSame('Belgique', Pays::nomFrancais('België'));
        $this->assertSame('Belgique', Pays::nomFrancais('Belgium'));
        $this->assertSame('Pays-Bas', Pays::nomFrancais('Nederland'));
    }

    public function test_la_communication_structuree_reste_valide_apres_le_client_mille(): void
    {
        $client = Client::factory()->create([
            'id' => User::factory()->create(['id' => 12345])->id,
        ]);
        $this->livree($client, '2026-03-04');

        $reference = app(Facturier::class)->facturer()->first()->payment_reference;

        $this->assertMatchesRegularExpression('/^\+\+\+\d{3}\/\d{4}\/\d{5}\+\+\+$/', $reference);

        $chiffres = preg_replace('/\D/', '', $reference);
        $attendu = ((int) substr($chiffres, 0, 10)) % 97;

        $this->assertSame($attendu === 0 ? 97 : $attendu, (int) substr($chiffres, -2));
    }

    public function test_le_mois_en_cours_n_est_jamais_facture(): void
    {
        $client = Client::factory()->create();
        $this->livree($client, now()->toDateString());

        $this->assertCount(0, app(Facturier::class)->facturer());
        $this->assertCount(0, app(Facturier::class)->facturer(Carbon::now()));
    }

    public function test_un_poids_nul_est_refuse(): void
    {
        $client = Client::factory()->create();

        $this->actingAs(User::find($client->id))
            ->post(route('transport-orders.store'), ['weight' => 0])
            ->assertSessionHasErrors('weight');
    }

    public function test_une_grille_desactivee_ne_sert_plus_a_commander(): void
    {
        $client = Client::factory()->create();
        $grille = TariffGrid::factory()->create(['is_active' => false]);

        $this->actingAs(User::find($client->id))
            ->post(route('transport-orders.store'), ['tariff_grid_id' => $grille->id])
            ->assertSessionHasErrors('tariff_grid_id');
    }

    public function test_un_second_paiement_en_ligne_est_journalise(): void
    {
        config([
            'services.stripe.secret' => 'sk_test_audit',
            'services.stripe.webhook_secret' => 'whsec_audit',
        ]);

        $client = Client::factory()->create();
        $this->livree($client, '2026-03-04');
        $facture = app(Facturier::class)->facturer()->first();

        $notifier = function (string $session) use ($facture) {
            $corps = json_encode([
                'id' => 'evt_'.$session,
                'object' => 'event',
                'type' => 'checkout.session.completed',
                'livemode' => false,
                'data' => ['object' => [
                    'id' => $session,
                    'object' => 'checkout.session',
                    'client_reference_id' => (string) $facture->id,
                    'payment_status' => 'paid',
                    'amount_total' => (int) round((float) $facture->amount_incl_tax * 100),
                    'currency' => 'eur',
                ]],
            ]);
            $t = time();
            $signature = hash_hmac('sha256', $t.'.'.$corps, 'whsec_audit');

            return $this->call('POST', '/stripe/webhook', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.$t.',v1='.$signature,
            ], $corps);
        };

        $notifier('cs_premier')->assertOk();
        $notifier('cs_second')->assertOk();

        $this->assertSame('PAID', $facture->refresh()->status);
        $this->assertSame(1, ActivityLog::where('action', 'invoice.paid_online')->count());
        $this->assertSame(1, ActivityLog::where('action', 'invoice.payment_duplicate')->count());
    }

    // --- Acces -------------------------------------------------------------

    public function test_le_client_ne_recoit_pas_la_fiche_du_chauffeur(): void
    {
        $client = Client::factory()->create();
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->enRoute()->create([
            'client_id' => $client->id,
            'driver_id' => $chauffeur->id,
        ]);

        $this->actingAs(User::find($client->id))
            ->get(route('transport-orders.show', $ordre))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('order.driver')
                ->has('chauffeur'));

        $this->actingAs(User::find($client->id))
            ->get(route('tracking.show', ['tracking_number' => $ordre->tracking_number]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('order.driver')
                ->where('chauffeur.numero_permis', null));
    }

    public function test_le_suivi_anonyme_ne_montre_pas_les_identifiants_internes(): void
    {
        $ordre = TransportOrder::factory()->create();

        $this->get(route('tracking.show', [
            'tracking_number' => $ordre->tracking_number,
            'code' => $ordre->tracking_code,
        ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('order.tracking_number', $ordre->tracking_number)
                ->missing('order.client_id')
                ->missing('order.client.id')
                ->has('order.client.company_name'));
    }

    public function test_un_ordre_en_attente_ne_passe_en_cours_que_par_l_affectation(): void
    {
        $ordre = TransportOrder::factory()->create(['status' => 'PENDING']);

        $this->actingAs(User::factory()->planificateur()->create())
            ->patch(route('planning.status', $ordre), ['status' => 'IN_PROGRESS'])
            ->assertSessionHasErrors('status');

        $this->assertSame('PENDING', $ordre->refresh()->status);
    }

    public function test_un_chauffeur_au_compte_ferme_n_est_plus_propose(): void
    {
        $chauffeur = $this->chauffeur();
        $chauffeur->user->update(['is_active' => false]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('planning.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('drivers', fn ($liste) => collect($liste)->doesntContain('id', $chauffeur->id)));
    }

    public function test_le_personnel_ne_supprime_pas_son_propre_compte(): void
    {
        $administrateur = User::factory()->administrateur()->create();

        $this->actingAs($administrateur)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertForbidden();

        $this->assertNotNull($administrateur->fresh());
    }

    public function test_un_client_qui_a_un_historique_ne_se_supprime_pas(): void
    {
        $client = Client::factory()->create();
        TransportOrder::factory()->create(['client_id' => $client->id]);

        $this->actingAs(User::find($client->id))
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertAuthenticated();
        $this->assertNotNull(User::find($client->id));
    }

    // --- Authentification --------------------------------------------------

    public function test_les_ecrans_du_personnel_et_des_cles_redemandent_le_mot_de_passe(): void
    {
        $administrateur = User::factory()->administrateur()->create();

        foreach (['staff.index', 'api-keys.index'] as $route) {
            $this->actingAs($administrateur)
                ->get(route($route))
                ->assertRedirect(route('password.confirm'));
        }

        // Une fois le mot de passe confirme, la session le retient.
        $this->withSession(['auth.password_confirmed_at' => time()]);

        foreach (['staff.index', 'api-keys.index'] as $route) {
            $this->actingAs($administrateur)->get(route($route))->assertOk();
        }
    }

    public function test_la_confirmation_du_mot_de_passe_est_limitee(): void
    {
        $utilisateur = User::factory()->create();

        foreach (range(1, 6) as $essai) {
            $this->actingAs($utilisateur)
                ->post(route('password.confirm'), ['password' => 'essai-'.$essai])
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($utilisateur)
            ->post(route('password.confirm'), ['password' => 'essai-7'])
            ->assertTooManyRequests();
    }

    public function test_le_lien_de_reinitialisation_ne_revele_pas_les_comptes(): void
    {
        $existant = User::factory()->create();

        $connu = $this->post(route('password.email'), ['email' => $existant->email]);
        $inconnu = $this->post(route('password.email'), ['email' => 'personne@exemple.be']);

        $connu->assertSessionHasNoErrors()->assertSessionHas('status', __('passwords.user'));
        $inconnu->assertSessionHasNoErrors()->assertSessionHas('status', __('passwords.user'));
    }

    public function test_changer_de_mot_de_passe_ferme_les_autres_sessions(): void
    {
        config(['session.driver' => 'database']);

        $utilisateur = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-de-l-intrus',
            'user_id' => $utilisateur->id,
            'ip_address' => '203.0.113.9',
            'user_agent' => 'navigateur',
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->actingAs($utilisateur)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'Un-nouveau-mot-de-passe-2026!',
                'password_confirmation' => 'Un-nouveau-mot-de-passe-2026!',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(DB::table('sessions')->where('id', 'session-de-l-intrus')->exists());
    }

    // --- Courriels ---------------------------------------------------------

    public function test_un_courriel_en_panne_n_annule_pas_la_validation(): void
    {
        $client = Client::factory()->enAttente()->create();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('serveur de courriel injoignable'));

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('clients.approve', $client))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue((bool) $client->refresh()->is_validated);
        $this->assertTrue(ActivityLog::where('action', 'client.validated')->exists());
    }
}
