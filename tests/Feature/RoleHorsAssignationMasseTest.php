<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * role et is_active sont hors de $fillable : un tableau venu d'un
 * formulaire ne les ecrit jamais. Chaque ecran qui doit les ecrire le fait
 * un champ a la fois, et ces tests verifient qu'il ecrit encore la bonne
 * valeur : un oubli ne plante pas, Laravel jette l'attribut en silence.
 */
class RoleHorsAssignationMasseTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_et_etat_ne_se_remplissent_pas_en_masse(): void
    {
        $compte = User::make(['first_name' => 'Eve', 'role' => 'ADMIN', 'is_active' => true]);

        $this->assertSame('Eve', $compte->first_name);
        $this->assertNull($compte->role);
        $this->assertNull($compte->is_active);
    }

    public function test_le_profil_ne_change_ni_le_role_ni_l_etat(): void
    {
        $compte = User::factory()->create(['first_name' => 'Eve']);

        $this->actingAs($compte)
            ->patch(route('profile.update'), [
                'first_name' => 'Eva',
                'last_name' => $compte->last_name,
                'email' => $compte->email,
                'role' => 'ADMIN',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $compte->refresh();
        $this->assertSame('Eva', $compte->first_name);
        $this->assertSame('CLIENT', $compte->role);
        $this->assertTrue($compte->is_active);
    }

    public function test_le_personnel_cree_recoit_le_role_choisi_et_un_compte_actif(): void
    {
        Notification::fake();
        $this->refuserLesAttributsJetes();
        $admin = User::factory()->administrateur()->create();

        foreach (['PLANNER', 'ADMIN'] as $role) {
            $this->actingAs($admin)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->post(route('staff.store'), [
                    'first_name' => 'Marc', 'last_name' => $role, 'email' => strtolower($role).'@nblogitrack.be', 'role' => $role,
                ])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success');

            $compte = User::where('email', strtolower($role).'@nblogitrack.be')->firstOrFail();
            $this->assertSame($role, $compte->role);
            $this->assertTrue($compte->is_active);
        }
    }

    public function test_l_activation_du_personnel_ferme_puis_rouvre_le_compte(): void
    {
        $this->refuserLesAttributsJetes();
        $admin = User::factory()->administrateur()->create();
        $planificateur = User::factory()->planificateur()->create();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('staff.toggle', $planificateur))
            ->assertSessionHas('success');

        $this->assertFalse($planificateur->fresh()->is_active);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('staff.toggle', $planificateur))
            ->assertSessionHas('success');

        $this->assertTrue($planificateur->fresh()->is_active);
    }

    public function test_un_collegue_invite_est_un_client_actif(): void
    {
        Notification::fake();
        $this->refuserLesAttributsJetes();
        $client = Client::factory()->create();

        $this->actingAs($client->compte())
            ->post(route('company.users.store'), [
                'first_name' => 'Lotte', 'last_name' => 'Peeters', 'email' => 'lotte@exemple.be', 'role' => 'ORDERS',
            ])
            ->assertSessionHas('success');

        $invite = User::where('email', 'lotte@exemple.be')->firstOrFail();
        $this->assertSame('CLIENT', $invite->role);
        $this->assertTrue($invite->is_active);
        $this->assertSame('ORDERS', $invite->company_role);
    }

    public function test_le_gestionnaire_ferme_puis_rouvre_l_acces_d_un_collegue(): void
    {
        $this->refuserLesAttributsJetes();
        $client = Client::factory()->create();
        $collegue = User::factory()->create(['client_id' => $client->id, 'company_role' => 'ORDERS']);

        $this->actingAs($client->compte())
            ->patch(route('company.users.update', $collegue), ['actif' => false])
            ->assertSessionHas('success');

        $this->assertFalse($collegue->fresh()->is_active);
        $this->assertSame('CLIENT', $collegue->fresh()->role);

        $this->actingAs($client->compte())
            ->patch(route('company.users.update', $collegue), ['actif' => true, 'role' => 'BILLING'])
            ->assertSessionHas('success');

        $collegue->refresh();
        $this->assertTrue($collegue->is_active);
        $this->assertSame('BILLING', $collegue->company_role);
        $this->assertSame('CLIENT', $collegue->role);
    }

    public function test_valider_ou_refuser_une_entreprise_ouvre_ou_ferme_ses_comptes(): void
    {
        Mail::fake();
        $this->refuserLesAttributsJetes();
        $admin = User::factory()->administrateur()->create();

        $validee = Client::factory()->enAttente()->create();
        $validee->users()->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('clients.approve', $validee))
            ->assertSessionHas('success');

        $this->assertTrue($validee->compte()->is_active);

        $refusee = Client::factory()->enAttente()->create();

        $this->actingAs($admin)
            ->post(route('clients.reject', $refusee), ['motif' => 'Numéro de TVA inactif au registre'])
            ->assertSessionHas('success');

        $this->assertFalse($refusee->compte()->is_active);
    }
}
