<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Facturier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Desinscription d'un membre qui a deja fait des transactions : suppression
 * logique (soft delete). Le compte est anonymise et marque supprime, les
 * commandes et les factures restent, comme la loi l'impose.
 */
class DesinscriptionLogiqueTest extends TestCase
{
    use RefreshDatabase;

    private function entrepriseAvecHistorique(): Client
    {
        $client = Client::factory()->create();
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
            'estimated_cost' => 500,
        ]);
        app(Facturier::class)->facturer();

        return $client;
    }

    public function test_un_client_avec_des_transactions_se_desinscrit_par_suppression_logique(): void
    {
        $client = $this->entrepriseAvecHistorique();
        $compte = $client->compte();
        $email = $compte->email;
        ClientContact::create(['client_id' => $client->id, 'first_name' => 'Nadia', 'last_name' => 'Peeters', 'email' => 'nadia@exemple.be', 'is_primary' => true]);
        [$cle] = ApiKey::generer([
            'name' => 'Partenaire',
            'client_id' => $client->id,
            'abilities' => ['lecture'],
            'created_by' => User::factory()->administrateur()->create()->id,
        ]);

        $this->actingAs($compte)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();

        // Invisible pour l'application, mais toujours en base.
        $this->assertNull(User::find($compte->id));
        $this->assertNull(Client::find($client->id));
        $this->assertSoftDeleted('users', ['id' => $compte->id]);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);

        // Identite effacee.
        $anonyme = User::withTrashed()->find($compte->id);
        $this->assertNotSame($email, $anonyme->email);
        $this->assertStringEndsWith('@anonyme.invalid', $anonyme->email);
        $this->assertNull($anonyme->phone);
        $this->assertFalse($anonyme->is_active);
        $this->assertSame(0, ClientContact::where('client_id', $client->id)->count());
        $this->assertNotNull($cle->fresh()->revoked_at);

        // Pieces conservees, toujours reliees a leur entreprise.
        $ordre = TransportOrder::where('client_id', $client->id)->firstOrFail();
        $this->assertSame($client->company_name, $ordre->client->company_name);
        $this->assertSame(1, Invoice::where('client_id', $client->id)->count());

        $this->assertTrue(ActivityLog::where('action', 'profile.unsubscribed')->exists());
    }

    public function test_l_ancienne_adresse_ne_permet_plus_de_se_connecter(): void
    {
        $client = $this->entrepriseAvecHistorique();
        $compte = $client->compte();
        $email = $compte->email;

        $this->actingAs($compte)->delete(route('profile.destroy'), ['password' => 'password']);

        $this->post(route('login'), ['email' => $email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_la_facture_d_une_entreprise_desinscrite_reste_consultable(): void
    {
        $client = $this->entrepriseAvecHistorique();
        $facture = Invoice::where('client_id', $client->id)->firstOrFail();

        $this->actingAs($client->compte())->delete(route('profile.destroy'), ['password' => 'password']);

        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('invoices.show', $facture))
            ->assertOk();
    }

    public function test_un_collegue_qui_part_est_anonymise_et_l_entreprise_reste(): void
    {
        $client = $this->entrepriseAvecHistorique();
        $collegue = User::factory()->create(['role' => 'CLIENT', 'client_id' => $client->id, 'company_role' => 'ORDERS']);

        $this->actingAs($collegue)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('users', ['id' => $collegue->id]);
        $this->assertNotNull(Client::find($client->id));
        $this->assertNotNull($client->compte());
    }

    public function test_sans_transaction_la_suppression_est_reelle(): void
    {
        $client = Client::factory()->create();
        $compte = $client->compte();

        $this->actingAs($compte)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertNull(User::withTrashed()->find($compte->id));
        $this->assertNull(Client::withTrashed()->find($client->id));
    }

    public function test_une_entreprise_desinscrite_peut_se_reinscrire_avec_son_numero_de_tva(): void
    {
        $client = $this->entrepriseAvecHistorique();
        $tva = $client->vat_number;

        $this->actingAs($client->compte())->delete(route('profile.destroy'), ['password' => 'password']);

        $nouvelle = Client::factory()->create(['vat_number' => $tva]);
        $this->assertSame($tva, $nouvelle->vat_number);
    }
}
