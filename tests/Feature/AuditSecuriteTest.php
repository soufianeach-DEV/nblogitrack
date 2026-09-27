<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Page;
use App\Models\PageDocument;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Facturier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuditSecuriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_404_levee_avant_le_groupe_web_porte_les_en_tetes_de_securite(): void
    {
        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('transport-orders.show', 999999))
            ->assertNotFound()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('X-Powered-By');
    }

    public function test_l_api_porte_aussi_les_en_tetes_de_securite(): void
    {
        $this->getJson('/api/v1/expeditions')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_un_identifiant_non_numerique_repond_404_et_non_500(): void
    {
        $this->actingAs(User::factory()->administrateur()->create())
            ->get('/fr/transport-orders/abc')
            ->assertNotFound();
    }

    public function test_un_filtre_en_tableau_est_ignore_sans_erreur(): void
    {
        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('transport-orders.index', ['tracking' => ['x'], 'q' => ['y'], 'client' => ['z']]))
            ->assertOk();
    }

    public function test_la_reinitialisation_ne_revele_pas_si_une_adresse_est_inscrite(): void
    {
        $inscrit = User::factory()->create();

        $messages = [];

        foreach (['personne@exemple.be', $inscrit->email] as $adresse) {
            $this->post(route('password.store'), [
                'token' => 'faux', 'email' => $adresse,
                'password' => 'un-mot-de-passe-long', 'password_confirmation' => 'un-mot-de-passe-long',
            ])->assertSessionHasErrors('email');

            $messages[] = session('errors')->first('email');
        }

        $this->assertSame($messages[0], $messages[1]);
        $this->assertSame(trans('passwords.token'), $messages[0]);
    }

    public function test_un_mot_de_passe_de_moins_de_douze_caracteres_est_refuse(): void
    {
        $compte = User::factory()->create();

        $this->actingAs($compte)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'court-123',
                'password_confirmation' => 'court-123',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_un_compte_est_bloque_apres_quinze_essais_quelle_que_soit_l_adresse(): void
    {
        $compte = User::factory()->create();

        foreach (range(1, 15) as $essai) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$essai])
                ->post(route('login'), ['email' => $compte->email, 'password' => 'mauvais-mot-de-passe']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->post(route('login'), ['email' => $compte->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_inconnu_ne_bloque_pas_le_titulaire_depuis_son_adresse_habituelle(): void
    {
        $compte = User::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('login'), ['email' => $compte->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($compte);
        auth()->logout();

        foreach (range(1, 16) as $essai) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.'.$essai])
                ->post(route('login'), ['email' => $compte->email, 'password' => 'mauvais-mot-de-passe']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('login'), ['email' => $compte->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($compte);
    }

    public function test_un_chauffeur_sans_fiche_ne_voit_aucune_mission(): void
    {
        TransportOrder::factory()->create(['status' => 'CANCELLED', 'driver_id' => null]);

        $this->actingAs(User::factory()->chauffeur()->create())
            ->get(route('missions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('missions', []));
    }

    public function test_le_personnel_ne_cree_pas_de_session_de_paiement(): void
    {
        $client = Client::factory()->create();
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
        ]);
        $facture = app(Facturier::class)->facturer()->first();

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('payments.payer', $facture))
            ->assertNotFound();
    }

    public function test_un_compte_commandes_ne_voit_pas_le_resume_de_facture(): void
    {
        $client = Client::factory()->create();
        $ordre = TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
        ]);
        app(Facturier::class)->facturer();
        $commandes = User::factory()->create(['client_id' => $client->id, 'company_role' => 'ORDERS']);

        $this->actingAs($commandes)
            ->get(route('transport-orders.show', $ordre))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('facture', null));

        $this->actingAs($client->compte())
            ->get(route('transport-orders.show', $ordre))
            ->assertInertia(fn (AssertableInertia $page) => $page->whereNot('facture', null));
    }

    public function test_les_invitations_sont_limitees_par_jour_et_par_entreprise(): void
    {
        $client = Client::factory()->create();
        $admin = $client->compte();

        foreach (range(1, 30) as $n) {
            $this->travel(3)->seconds();
            $this->actingAs($admin)->post(route('company.users.store'), [
                'first_name' => 'Invite', 'last_name' => (string) $n, 'email' => 'invite'.$n.'@exemple.be', 'role' => 'ORDERS',
            ]);
            if ($n % 10 === 0) {
                $this->travel(1)->minutes();
            }
        }

        $this->actingAs($admin)->post(route('company.users.store'), [
            'first_name' => 'Invite', 'last_name' => 'de trop', 'email' => 'invite31@exemple.be', 'role' => 'ORDERS',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'invite31@exemple.be']);
        $this->assertDatabaseHas('users', ['email' => 'invite30@exemple.be']);
    }

    public function test_un_document_cite_par_aucune_page_publiee_reste_prive(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pages/brouillon.pdf', '%PDF-1.4');
        $admin = User::factory()->administrateur()->create();
        $document = PageDocument::create([
            'titre' => 'Brouillon', 'nom_origine' => 'brouillon.pdf', 'chemin' => 'pages/brouillon.pdf',
            'mime' => 'application/pdf', 'taille' => 8, 'uploaded_by' => $admin->id,
        ]);

        $this->get(route('pages.documents.show', $document))->assertNotFound();

        $this->actingAs($admin)
            ->get(route('pages.documents.show', $document))->assertOk();

        auth()->logout();
        Page::create([
            'slug' => 'infos', 'titre_fr' => 'Infos', 'corps_fr' => '[PDF]('.route('pages.documents.show', $document).')',
            'publiee' => true,
        ]);

        $this->get(route('pages.documents.show', $document))->assertOk();
    }

    public function test_la_page_d_inscription_ne_montre_que_les_fonctions_de_reference(): void
    {
        $client = Client::factory()->create();
        ClientContact::create([
            'client_id' => $client->id, 'first_name' => 'A', 'last_name' => 'B',
            'email' => 'a@b.be', 'position' => 'Jean Dupont 0470 12 34 56',
        ]);

        $this->get(route('register'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('fonctions', fn ($f) => ! collect($f)->contains('Jean Dupont 0470 12 34 56')));
    }
}
