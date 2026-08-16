<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_de_profil_s_affiche(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_le_profil_se_met_a_jour(): void
    {
        $utilisateur = User::factory()->create();

        $reponse = $this->actingAs($utilisateur)
            ->patch(route('profile.update'), [
                'first_name' => 'Soufiane',
                'last_name' => 'Achraa',
                'email' => 'nouvelle@exemple.be',
            ]);

        $reponse->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $utilisateur->refresh();

        $this->assertSame('Soufiane', $utilisateur->first_name);
        $this->assertSame('Achraa', $utilisateur->last_name);
        $this->assertSame('nouvelle@exemple.be', $utilisateur->email);

        // Changer d'adresse annule la verification : la nouvelle n'a pas
        // encore prouve qu'elle appartient a la meme personne.
        $this->assertNull($utilisateur->email_verified_at);
    }

    public function test_la_verification_reste_acquise_si_l_adresse_ne_change_pas(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->patch(route('profile.update'), [
                'first_name' => 'Soufiane',
                'last_name' => 'Achraa',
                'email' => $utilisateur->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($utilisateur->refresh()->email_verified_at);
    }

    public function test_un_compte_se_supprime_avec_son_mot_de_passe(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($utilisateur->fresh());
    }

    public function test_un_mauvais_mot_de_passe_ne_supprime_rien(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($utilisateur->fresh());
    }
}
