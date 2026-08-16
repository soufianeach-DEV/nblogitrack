<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_mot_de_passe_se_change(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'nouveau-mot-de-passe',
                'password_confirmation' => 'nouveau-mot-de-passe',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $utilisateur->refresh()->password));
    }

    public function test_l_ancien_mot_de_passe_doit_etre_juste(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->from(route('profile.edit'))
            ->put(route('password.update'), [
                'current_password' => 'mauvais-mot-de-passe',
                'password' => 'nouveau-mot-de-passe',
                'password_confirmation' => 'nouveau-mot-de-passe',
            ])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('password', $utilisateur->refresh()->password));
    }
}
