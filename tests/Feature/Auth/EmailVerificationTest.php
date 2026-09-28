<?php

namespace Tests\Feature\Auth;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_ecran_de_verification_s_affiche(): void
    {
        $utilisateur = User::factory()->unverified()->create();

        $this->actingAs($utilisateur)->get(route('verification.notice'))->assertOk();
    }

    public function test_l_adresse_se_verifie_par_le_lien_signe(): void
    {
        Event::fake();

        $utilisateur = User::factory()->unverified()->create();

        $lien = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $utilisateur->id,
            'hash' => sha1($utilisateur->email),
        ]);

        $this->actingAs($utilisateur)->get($lien);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($utilisateur->fresh()->hasVerifiedEmail());
    }

    public function test_un_lien_falsifie_ne_verifie_rien(): void
    {
        $utilisateur = User::factory()->unverified()->create();

        $lien = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $utilisateur->id,
            'hash' => sha1('une-autre-adresse@exemple.be'),
        ]);

        $this->actingAs($utilisateur)->get($lien);

        $this->assertFalse($utilisateur->fresh()->hasVerifiedEmail());
    }

    public function test_le_lien_se_suit_sans_etre_connecte(): void
    {
        $utilisateur = User::factory()->unverified()->create();

        $lien = URL::temporarySignedRoute('verification.verify', now()->addDays(3), [
            'id' => $utilisateur->id,
            'hash' => sha1($utilisateur->email),
        ]);

        $this->get($lien)->assertRedirect(route('login'));

        $this->assertTrue($utilisateur->fresh()->hasVerifiedEmail());
    }

    public function test_une_entreprise_en_attente_apprend_qu_elle_doit_etre_validee(): void
    {
        $client = Client::factory()->enAttente()->create();
        $utilisateur = $client->compte();
        $utilisateur->update(['email_verified_at' => null]);

        $lien = URL::temporarySignedRoute('verification.verify', now()->addDays(3), [
            'id' => $utilisateur->id,
            'hash' => sha1($utilisateur->email),
        ]);

        $this->get($lien)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn (string $message) => str_contains($message, 'validée par un administrateur'));
    }

    public function test_un_compte_actif_peut_se_connecter_apres_confirmation(): void
    {
        $client = Client::factory()->create();
        $utilisateur = $client->compte();
        $utilisateur->update(['email_verified_at' => null]);

        $lien = URL::temporarySignedRoute('verification.verify', now()->addDays(3), [
            'id' => $utilisateur->id,
            'hash' => sha1($utilisateur->email),
        ]);

        $this->get($lien)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Votre adresse e-mail est confirmée. Vous pouvez vous connecter.');
    }

    public function test_une_adresse_non_confirmee_ne_passe_que_par_le_profil(): void
    {
        $utilisateur = User::factory()->unverified()->create();

        $this->actingAs($utilisateur)->get(route('transport-orders.index'))
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($utilisateur)->get(route('profile.edit'))->assertOk();
    }

    public function test_changer_d_adresse_demande_de_la_confirmer(): void
    {
        Notification::fake();
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)->patch(route('profile.update'), [
            'first_name' => $utilisateur->first_name,
            'last_name' => $utilisateur->last_name,
            'email' => 'nouvelle@exemple.be',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertNull($utilisateur->fresh()->email_verified_at);
        Notification::assertSentTo($utilisateur->fresh(), VerifyEmail::class);
        $this->assertDatabaseHas('activity_logs', ['action' => 'profile.email_changed']);
    }

    public function test_le_mot_de_passe_du_profil_ne_se_devine_pas_a_volonte(): void
    {
        $utilisateur = User::factory()->create();

        foreach (range(1, 6) as $essai) {
            $this->actingAs($utilisateur)->patch(route('profile.update'), [
                'first_name' => $utilisateur->first_name, 'last_name' => $utilisateur->last_name,
                'email' => 'autre@exemple.be', 'current_password' => 'mauvais-'.$essai,
            ]);
        }

        $this->actingAs($utilisateur)->patch(route('profile.update'), [
            'first_name' => $utilisateur->first_name, 'last_name' => $utilisateur->last_name,
            'email' => 'autre@exemple.be', 'current_password' => 'password',
        ])->assertStatus(302);

        $this->assertNotSame('autre@exemple.be', $utilisateur->fresh()->email);
    }
}
