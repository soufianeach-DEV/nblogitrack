<?php

namespace Tests\Feature;

use App\Mail\FactureEmise;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * « Facturer maintenant » : pour la demonstration, l'administrateur emet
 * tout de suite la facture d'une livraison du jour, sans attendre le 1er
 * du mois.
 */
class FacturerMaintenantTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->client = Client::factory()->create();
        ClientContact::create([
            'client_id' => $this->client->id,
            'first_name' => 'Nadia',
            'last_name' => 'Peeters',
            'email' => 'compta@exemple.be',
            'is_primary' => true,
        ]);
        TransportOrder::factory()->livree()->create([
            'client_id' => $this->client->id,
            'actual_delivery_date' => today()->toDateString(),
            'delivered_at' => now()->subMinutes(5),
            'estimated_cost' => 500,
        ]);
    }

    public function test_une_livraison_du_jour_est_facturee_et_envoyee_tout_de_suite(): void
    {
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin)->get(route('invoices.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('aFacturer', 1));

        $this->actingAs($admin)->post(route('invoices.now'))->assertSessionHas('success');

        $facture = Invoice::sole();
        $this->assertSame(today()->toDateString(), $facture->issued_on->toDateString());
        $this->assertSame('SENT', $facture->status);
        Mail::assertSent(FactureEmise::class, fn (FactureEmise $m) => $m->hasTo('compta@exemple.be'));

        // Deuxieme clic : plus rien a facturer, pas de doublon.
        $this->actingAs($admin)->post(route('invoices.now'))->assertSessionHas('error');
        $this->assertSame(1, Invoice::count());
    }

    public function test_la_facturation_mensuelle_ne_touche_toujours_pas_au_mois_en_cours(): void
    {
        $this->artisan('factures:generer', ['--tout' => true])->assertSuccessful();

        $this->assertSame(0, Invoice::count());
    }

    public function test_le_planificateur_ne_peut_pas_facturer(): void
    {
        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('invoices.now'))
            ->assertForbidden();

        $this->assertSame(0, Invoice::count());
    }
}
