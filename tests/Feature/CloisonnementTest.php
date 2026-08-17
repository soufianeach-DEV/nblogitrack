<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Qui voit quoi, et qui peut quoi.
 *
 * Mon audit du 15 aout avait trouve ici le seul trou d'autorisation de
 * l'application : un chauffeur atteignait le carnet de commandes du
 * client en tapant l'adresse a la main. Ces tests fixent la frontiere
 * pour qu'elle ne se rouvre pas.
 */
class CloisonnementTest extends TestCase
{
    use RefreshDatabase;

    private function client(): Client
    {
        return Client::factory()->create();
    }

    public function test_un_client_ne_voit_que_ses_propres_expeditions(): void
    {
        $sien = $this->client();
        $autre = $this->client();

        TransportOrder::factory()->count(3)->create(['client_id' => $sien->id]);
        TransportOrder::factory()->count(2)->create(['client_id' => $autre->id]);

        $reponse = $this->actingAs(User::find($sien->id))
            ->get(route('transport-orders.index'));

        $reponse->assertOk();

        $vues = collect($reponse->viewData('page')['props']['orders']['data']);

        $this->assertCount(3, $vues);
        $this->assertTrue($vues->every(fn ($o) => $o['client_id'] === $sien->id));
    }

    /**
     * La reponse est « introuvable » et non « interdit » : les
     * identifiants se suivent, et un refus distinct du neant permettrait
     * de denombrer les expeditions des autres en changeant le numero
     * dans l'adresse.
     */
    public function test_l_expedition_d_un_autre_client_n_existe_pas(): void
    {
        $sien = $this->client();
        $autre = $this->client();
        $ordre = TransportOrder::factory()->create(['client_id' => $autre->id]);

        $this->actingAs(User::find($sien->id))
            ->get(route('transport-orders.show', $ordre))
            ->assertNotFound();
    }

    public function test_le_personnel_voit_toutes_les_expeditions(): void
    {
        TransportOrder::factory()->count(4)->create();

        $reponse = $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('transport-orders.index'));

        $reponse->assertOk();
        $this->assertCount(4, $reponse->viewData('page')['props']['orders']['data']);
    }

    /**
     * Le trou que l'audit avait trouve. Le chauffeur a ses missions,
     * pas le carnet de commandes.
     */
    public function test_un_chauffeur_n_atteint_pas_les_ecrans_du_client(): void
    {
        $chauffeur = User::factory()->chauffeur()->create();

        $this->actingAs($chauffeur)->get(route('transport-orders.index'))->assertForbidden();
        $this->actingAs($chauffeur)->get(route('transport-orders.create'))->assertForbidden();
        $this->actingAs($chauffeur)->get(route('invoices.index'))->assertForbidden();
    }

    /**
     * Deposer une commande, c'est signer en son nom. Seul un compte
     * client porte une ligne dans « clients », que la commande
     * reference : le personnel qui deposait declenchait une erreur
     * serveur sur la cle etrangere.
     */
    public function test_seul_un_client_depose_une_commande(): void
    {
        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('transport-orders.create'))->assertForbidden();

        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('transport-orders.create'))->assertForbidden();

        $this->actingAs(User::find($this->client()->id))
            ->get(route('transport-orders.create'))->assertOk();
    }

    public function test_un_client_ne_voit_que_ses_propres_factures(): void
    {
        $sien = $this->client();
        $autre = $this->client();

        $this->factures($sien, 2);
        $this->factures($autre, 3);

        $reponse = $this->actingAs(User::find($sien->id))->get(route('invoices.index'));

        $reponse->assertOk();
        $this->assertSame(2, $reponse->viewData('page')['props']['factures']['total']);
    }

    public function test_le_personnel_voit_toutes_les_factures(): void
    {
        $this->factures($this->client(), 2);
        $this->factures($this->client(), 3);

        $reponse = $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('invoices.index'));

        $this->assertSame(5, $reponse->viewData('page')['props']['factures']['total']);
    }

    /** Quelques factures pour une entreprise, le minimum exigible. */
    private function factures(Client $client, int $combien): void
    {
        foreach (range(1, $combien) as $i) {
            Invoice::create([
                'client_id' => $client->id,
                'reference' => 'FAC-'.now()->year.'-'.fake()->unique()->numerify('####'),
                'issued_on' => now()->subDays(20),
                'due_on' => now()->addDays(10),
                'period_start' => now()->subMonth()->startOfMonth(),
                'period_end' => now()->subMonth()->endOfMonth(),
                'amount_excl_tax' => 1000,
                'vat_rate' => 21,
                'vat_amount' => 210,
                'amount_incl_tax' => 1210,
                'reverse_charge' => false,
                'status' => 'SENT',
            ]);
        }
    }
}
