<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\JournalSecurite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les tentatives que le journal ne voyait pas : acces refuses, mots de
 * passe actuels errones, et connexions refusees a un compte bloque, qui
 * passaient pour des connexions reussies.
 */
class JournalSecuriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_acces_refuse_est_journalise(): void
    {
        $client = User::factory()->create();

        $this->actingAs($client)->get(route('activity-logs.index'))->assertForbidden();

        $ligne = ActivityLog::where('action', 'auth.access_denied')->sole();
        $this->assertSame($client->id, $ligne->user_id);
        $this->assertStringContainsString($client->email, $ligne->description);
        $this->assertStringStartsWith('GET /', $ligne->properties['requete']);
    }

    public function test_un_flot_de_refus_n_ecrit_pas_une_ligne_par_refus(): void
    {
        $client = User::factory()->create();

        // La meme adresse refusee trois fois : une ligne.
        foreach (range(1, 3) as $essai) {
            $this->actingAs($client)->get(route('activity-logs.index'))->assertForbidden();
        }

        $this->assertSame(1, ActivityLog::where('action', 'auth.access_denied')->count());

        // Les commandes d'une autre entreprise essayees une a une : chacune
        // repond « introuvable », et dix lignes au plus par minute.
        $entreprise = Client::factory()->create();
        $client->update(['client_id' => $entreprise->id]);

        foreach (TransportOrder::factory()->count(JournalSecurite::PAR_MINUTE + 5)->create() as $ordre) {
            $this->actingAs($client)->get(route('transport-orders.show', $ordre))->assertNotFound();
        }

        $this->assertSame(JournalSecurite::PAR_MINUTE, ActivityLog::where('action', 'auth.access_denied')->count());
    }

    public function test_l_expedition_d_une_autre_entreprise_reste_introuvable_mais_journalisee(): void
    {
        $sienne = Client::factory()->create();
        $gestionnaire = $sienne->users()->sole();
        $autre = TransportOrder::factory()->create();
        $propre = TransportOrder::factory()->create(['client_id' => $sienne->id]);

        $this->actingAs($gestionnaire)->get(route('transport-orders.show', $autre))->assertNotFound();
        $this->actingAs($gestionnaire)->get(route('transport-orders.show', $propre))->assertOk();
        $this->actingAs($gestionnaire)->get(route('transport-orders.show', 999999))->assertNotFound();

        // Seul l'essai sur l'expedition d'autrui est inscrit : une adresse
        // qui n'existe pas n'est pas un refus.
        $ligne = ActivityLog::where('action', 'auth.access_denied')->sole();
        $this->assertSame($gestionnaire->id, $ligne->user_id);
        $this->assertStringEndsWith('/'.$autre->getRouteKey(), $ligne->properties['requete']);
    }

    public function test_un_mauvais_mot_de_passe_a_la_confirmation_est_journalise(): void
    {
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin)->post(route('password.confirm'), ['password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrors('password');

        $ligne = ActivityLog::where('action', 'auth.password_rejected')->sole();
        $this->assertSame($admin->id, $ligne->user_id);
        $this->assertStringContainsString('confirmation du mot de passe', $ligne->description);
    }

    public function test_un_mauvais_mot_de_passe_actuel_est_journalise_avec_le_meme_message(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)->from(route('profile.edit'))->put(route('password.update'), [
            'current_password' => 'mauvais-mot-de-passe',
            'password' => 'nouveau-mot-de-passe-long',
            'password_confirmation' => 'nouveau-mot-de-passe-long',
        ])->assertSessionHasErrors(['current_password' => trans('validation.current_password')]);

        $this->assertStringContainsString(
            'changement du mot de passe',
            ActivityLog::where('action', 'auth.password_rejected')->sole()->description,
        );

        // Le bon mot de passe passe toujours, sans trace d'erreur.
        $this->actingAs($utilisateur)->from(route('profile.edit'))->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'nouveau-mot-de-passe-long',
            'password_confirmation' => 'nouveau-mot-de-passe-long',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ActivityLog::where('action', 'auth.password_rejected')->count());
    }

    public function test_un_compte_desactive_n_apparait_pas_comme_connecte(): void
    {
        $utilisateur = User::factory()->desactive()->create();

        $this->post(route('login'), ['email' => $utilisateur->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Ce compte est désactivé. Contactez votre administrateur.']);

        $this->assertGuest();
        $this->assertSame(['auth.blocked'], ActivityLog::pluck('action')->all());
        $this->assertStringContainsString('compte désactivé', ActivityLog::sole()->description);
    }

    public function test_une_entreprise_en_attente_n_apparait_pas_comme_connectee(): void
    {
        $entreprise = Client::factory()->enAttente()->create();
        $gestionnaire = $entreprise->users()->sole();

        $this->post(route('login'), ['email' => $gestionnaire->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(['auth.blocked'], ActivityLog::pluck('action')->all());
        $this->assertStringContainsString('en attente de validation', ActivityLog::sole()->description);
    }

    public function test_connexion_et_echec_restent_journalises(): void
    {
        $utilisateur = User::factory()->create();

        $this->post(route('login'), ['email' => $utilisateur->email, 'password' => 'mauvais-mot-de-passe']);
        $this->assertGuest();

        $this->post(route('login'), ['email' => $utilisateur->email, 'password' => 'password', 'remember' => true])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($utilisateur);

        $this->assertSame(['auth.failed', 'auth.login'], ActivityLog::orderBy('id')->pluck('action')->all());
        $this->assertSame($utilisateur->id, ActivityLog::where('action', 'auth.failed')->sole()->user_id);
        $this->assertNotNull($utilisateur->fresh()->remember_token);
    }
}
