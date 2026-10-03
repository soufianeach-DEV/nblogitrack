<?php

namespace Tests\Feature;

use App\Mail\AccesApiTraite;
use App\Mail\DemandeAccesApi;
use App\Models\ApiKey;
use App\Models\ApiKeyRequest;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Une entreprise demande un acces a l'API ; l'administration l'accorde sans
 * voir la cle, que le client affiche une seule fois dans son espace.
 */
class DemandeAccesApiTest extends TestCase
{
    use RefreshDatabase;

    private function compte(Client $client, string $role = 'ADMIN'): User
    {
        return User::factory()->create(['role' => 'CLIENT', 'client_id' => $client->id, 'company_role' => $role]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'ADMIN']);
    }

    private function demander(User $client, array $donnees = []): TestResponse
    {
        return $this->actingAs($client)->post(route('company.api.store', ['langue' => 'fr']), $donnees + [
            'permissions' => ['lecture', 'ecriture'],
            'ips' => '203.0.113.7',
            'message' => 'Integration Odoo',
        ]);
    }

    private function accorder(User $admin, ApiKeyRequest $demande): TestResponse
    {
        return $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-keys.grant', ['langue' => 'fr', 'demande' => $demande]), [
                'nom' => 'Integration Odoo',
                'permissions' => ['lecture', 'ecriture'],
                'ips' => '',
            ]);
    }

    public function test_l_administrateur_de_l_entreprise_demande_un_acces_et_les_admins_sont_prevenus(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $client = $this->compte(Client::factory()->create());

        $this->demander($client)->assertRedirect()->assertSessionHas('success');

        $demande = ApiKeyRequest::sole();
        $this->assertSame(ApiKeyRequest::EN_ATTENTE, $demande->status);
        $this->assertSame(['lecture', 'ecriture'], $demande->abilities);
        $this->assertSame(['203.0.113.7'], $demande->allowed_ips);
        Mail::assertSent(DemandeAccesApi::class, fn ($m) => $m->hasTo($admin->email));

        // Une seconde demande pendant l'examen est refusee.
        $this->demander($client)->assertSessionHas('error');
        $this->assertSame(1, ApiKeyRequest::count());
    }

    public function test_un_compte_commandes_ne_peut_pas_demander(): void
    {
        $commandes = $this->compte(Client::factory()->create(), 'ORDERS');

        $this->actingAs($commandes)->get(route('company.api.index', ['langue' => 'fr']))->assertForbidden();
        $this->demander($commandes)->assertForbidden();
    }

    public function test_une_adresse_ip_invalide_est_refusee(): void
    {
        $client = $this->compte(Client::factory()->create());

        $this->demander($client, ['ips' => 'pas-une-ip'])->assertSessionHasErrors('ips');
        $this->assertSame(0, ApiKeyRequest::count());
    }

    public function test_l_admin_accorde_sans_voir_la_cle_et_le_client_l_affiche_une_seule_fois(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $entreprise = Client::factory()->create();
        $client = $this->compte($entreprise);
        $this->demander($client);
        $demande = ApiKeyRequest::sole();

        $this->accorder($admin, $demande)
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('cle_en_clair');

        $demande->refresh();
        $this->assertSame(ApiKeyRequest::ACCORDEE, $demande->status);
        $this->assertSame($entreprise->id, $demande->cle->client_id);
        $this->assertTrue($demande->cleAAfficher());
        Mail::assertSent(AccesApiTraite::class, fn ($m) => $m->hasTo($client->email));

        // L'e-mail ne contient pas la cle.
        $courriel = (new AccesApiTraite($demande, $client))->render();
        $this->assertStringNotContainsString($demande->key_ciphertext, $courriel);

        $reponse = $this->actingAs($client)->post(route('company.api.reveal', ['langue' => 'fr', 'demande' => $demande]));
        $reponse->assertSessionHas('cle_en_clair');
        $jeton = session('cle_en_clair')['valeur'];

        $this->assertSame($demande->api_key_id, ApiKey::depuisJeton($jeton)?->id);
        $this->assertNull($demande->fresh()->key_ciphertext);
        $this->assertNotNull($demande->fresh()->revealed_at);

        // La cle fonctionne sur l'API.
        $this->withHeader('Authorization', 'Bearer '.$jeton)->getJson('/api/v1/expeditions')->assertOk();

        // Elle ne s'affiche pas une seconde fois.
        $this->actingAs($client)->post(route('company.api.reveal', ['langue' => 'fr', 'demande' => $demande]))
            ->assertSessionHas('error')
            ->assertSessionMissing('cle_en_clair');
    }

    public function test_une_autre_entreprise_ne_peut_pas_afficher_la_cle(): void
    {
        Mail::fake();
        $client = $this->compte(Client::factory()->create());
        $intrus = $this->compte(Client::factory()->create());
        $this->demander($client);
        $demande = ApiKeyRequest::sole();
        $this->accorder($this->admin(), $demande);

        $this->actingAs($intrus)->post(route('company.api.reveal', ['langue' => 'fr', 'demande' => $demande]))->assertNotFound();
        $this->assertTrue($demande->fresh()->cleAAfficher());
    }

    public function test_l_admin_refuse_avec_un_motif(): void
    {
        Mail::fake();
        $client = $this->compte(Client::factory()->create());
        $this->demander($client);
        $demande = ApiKeyRequest::sole();

        $this->actingAs($this->admin())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('api-keys.refuse', ['langue' => 'fr', 'demande' => $demande]), ['motif' => 'Contrat cadre non signé'])
            ->assertSessionHas('success');

        $demande->refresh();
        $this->assertSame(ApiKeyRequest::REFUSEE, $demande->status);
        $this->assertSame('Contrat cadre non signé', $demande->refusal_reason);
        $this->assertSame(0, ApiKey::count());
        Mail::assertSent(AccesApiTraite::class, fn ($m) => $m->hasTo($client->email));

        // Une demande traitee ne s'accorde plus.
        $this->accorder($this->admin(), $demande)->assertSessionHas('error');
        $this->assertSame(0, ApiKey::count());
    }

    public function test_un_client_ne_peut_pas_accorder(): void
    {
        $client = $this->compte(Client::factory()->create());
        $this->demander($client);

        $this->accorder($client, ApiKeyRequest::sole())->assertForbidden();
        $this->assertSame(0, ApiKey::count());
    }
}
