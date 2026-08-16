<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_ecran_de_connexion_s_affiche(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_un_compte_valide_se_connecte(): void
    {
        $utilisateur = User::factory()->create();

        $this->post(route('login'), [
            'email' => $utilisateur->email,
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_un_mauvais_mot_de_passe_ne_connecte_pas(): void
    {
        $utilisateur = User::factory()->create();

        $this->post(route('login'), [
            'email' => $utilisateur->email,
            'password' => 'mauvais-mot-de-passe',
        ]);

        $this->assertGuest();
    }

    /**
     * Le compte desactive est le scenario du depart conflictuel : on
     * coupe l'acces d'un employe, il doit etre coupe tout de suite.
     */
    public function test_un_compte_desactive_ne_se_connecte_pas(): void
    {
        $utilisateur = User::factory()->desactive()->create();

        $this->post(route('login'), [
            'email' => $utilisateur->email,
            'password' => 'password',
        ])->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_la_deconnexion_ferme_la_session(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)->post(route('logout'));

        $this->assertGuest();
    }
}
