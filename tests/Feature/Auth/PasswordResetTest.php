<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_ecran_de_mot_de_passe_oublie_s_affiche(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_un_lien_de_reinitialisation_part_par_courriel(): void
    {
        Notification::fake();

        $utilisateur = User::factory()->create();

        $this->post(route('password.email'), ['email' => $utilisateur->email]);

        Notification::assertSentTo($utilisateur, ResetPassword::class);
    }

    public function test_l_ecran_de_reinitialisation_s_affiche_avec_le_jeton(): void
    {
        Notification::fake();

        $utilisateur = User::factory()->create();

        $this->post(route('password.email'), ['email' => $utilisateur->email]);

        Notification::assertSentTo($utilisateur, ResetPassword::class, function (object $notification) {
            $this->get(route('password.reset', ['token' => $notification->token]))->assertOk();

            return true;
        });
    }

    public function test_le_mot_de_passe_se_reinitialise_avec_le_jeton(): void
    {
        Notification::fake();

        $utilisateur = User::factory()->create();

        $this->post(route('password.email'), ['email' => $utilisateur->email]);

        Notification::assertSentTo($utilisateur, ResetPassword::class, function (object $notification) use ($utilisateur) {
            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $utilisateur->email,
                'password' => 'nouveau-mot-de-passe',
                'password_confirmation' => 'nouveau-mot-de-passe',
            ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });
    }
}
