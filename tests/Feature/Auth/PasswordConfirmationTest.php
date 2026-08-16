<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_ecran_de_confirmation_s_affiche(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)->get(route('password.confirm'))->assertOk();
    }

    public function test_le_mot_de_passe_se_confirme(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->post(route('password.confirm'), ['password' => 'password'])
            ->assertSessionHasNoErrors();
    }

    public function test_un_mauvais_mot_de_passe_ne_confirme_rien(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->post(route('password.confirm'), ['password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrors();
    }
}
