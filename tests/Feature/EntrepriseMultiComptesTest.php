<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Facturier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Une entreprise, plusieurs comptes : chacun voit les expeditions de son
 * entreprise et agit selon son role.
 */
class EntrepriseMultiComptesTest extends TestCase
{
    use RefreshDatabase;

    private function collegue(Client $client, string $role): User
    {
        return User::factory()->create(['role' => 'CLIENT', 'client_id' => $client->id, 'company_role' => $role]);
    }

    public function test_un_collegue_voit_les_expeditions_de_son_entreprise(): void
    {
        $client = Client::factory()->create();
        $ordre = TransportOrder::factory()->create(['client_id' => $client->id]);
        $autre = TransportOrder::factory()->create();

        $collegue = $this->collegue($client, 'ORDERS');

        $this->actingAs($collegue)->get(route('transport-orders.show', $ordre))->assertOk();
        $this->actingAs($collegue)->get(route('transport-orders.show', $autre))->assertNotFound();
    }

    public function test_la_comptabilite_ne_commande_pas_et_les_commandes_ne_voient_pas_les_factures(): void
    {
        $client = Client::factory()->create();
        $this->travelTo('2026-04-10');
        TransportOrder::factory()->livree()->create(['client_id' => $client->id, 'actual_delivery_date' => '2026-03-10', 'estimated_cost' => 500]);
        $facture = app(Facturier::class)->facturer()->first();

        $compta = $this->collegue($client, 'BILLING');
        $commandes = $this->collegue($client, 'ORDERS');

        $this->actingAs($compta)->get(route('transport-orders.create'))->assertForbidden();
        $this->actingAs($compta)->get(route('invoices.show', $facture))->assertOk();

        $this->actingAs($commandes)->get(route('transport-orders.create'))->assertOk();
        $this->actingAs($commandes)->get(route('invoices.index'))->assertForbidden();
        $this->assertInstanceOf(Invoice::class, $facture);
    }

    public function test_l_administrateur_invite_un_collegue(): void
    {
        Notification::fake();
        $client = Client::factory()->create();

        $this->actingAs($client->compte())
            ->post(route('company.users.store'), [
                'first_name' => 'Lotte', 'last_name' => 'Peeters', 'email' => 'lotte@exemple.be', 'role' => 'BILLING',
            ])
            ->assertSessionHas('success');

        $invite = User::where('email', 'lotte@exemple.be')->first();
        $this->assertSame($client->id, $invite->client_id);
        $this->assertSame('BILLING', $invite->company_role);
        $this->assertSame('CLIENT', $invite->role);
    }

    public function test_seul_l_administrateur_gere_les_comptes(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->collegue($client, 'ORDERS'))
            ->get(route('company.users.index'))
            ->assertForbidden();
    }

    public function test_l_entreprise_garde_un_administrateur_actif(): void
    {
        $client = Client::factory()->create();
        $admin = $client->compte();

        $this->actingAs($admin)
            ->patch(route('company.users.update', $admin), ['role' => 'ORDERS'])
            ->assertSessionHas('error');

        $this->assertSame('ADMIN', $admin->fresh()->company_role);
    }

    public function test_on_ne_gere_pas_les_comptes_d_une_autre_entreprise(): void
    {
        $client = Client::factory()->create();
        $etranger = Client::factory()->create()->compte();

        $this->actingAs($client->compte())
            ->patch(route('company.users.update', $etranger), ['actif' => false])
            ->assertNotFound();

        $this->assertTrue($etranger->fresh()->is_active);
    }

    public function test_supprimer_un_compte_ne_supprime_pas_l_entreprise(): void
    {
        $client = Client::factory()->create();
        $collegue = $this->collegue($client, 'ORDERS');

        $this->actingAs($collegue)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertNull(User::find($collegue->id));
        $this->assertNotNull(Client::find($client->id));
    }
}
