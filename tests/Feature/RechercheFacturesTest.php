<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Facturier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recherche de l'ecran Facturation, avec suggestions : par le nom de
 * l'acheteur fige sur la facture ou par son numero. Les factures d'une
 * entreprise desinscrite restent trouvables ; un client ne cherche que dans
 * les siennes.
 */
class RechercheFacturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-04-10 10:00');
    }

    private function facturee(string $nom): Client
    {
        $client = Client::factory()->create(['company_name' => $nom]);
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => '2026-03-12',
            'estimated_cost' => 1000,
        ]);
        app(Facturier::class)->facturer();

        return $client;
    }

    /** @return array<string, mixed> */
    private function page(User $qui, array $parametres = []): array
    {
        return $this->actingAs($qui)
            ->get(route('invoices.index', $parametres))
            ->assertOk()
            ->viewData('page')['props'];
    }

    /** @return list<string> */
    private function references(array $props): array
    {
        return array_column($props['factures']['data'], 'reference');
    }

    public function test_l_administrateur_retrouve_les_factures_d_une_entreprise_desinscrite(): void
    {
        $partie = $this->facturee('Meubles Wouters NV');
        $this->facturee('Transports Dubois SA');
        $facture = Invoice::where('client_id', $partie->id)->firstOrFail();

        $this->actingAs($partie->compte())->delete(route('profile.destroy'), ['password' => 'password']);
        $this->assertSoftDeleted('clients', ['id' => $partie->id]);

        $props = $this->page(User::factory()->administrateur()->create(), ['q' => 'wouters']);

        $this->assertSame([$facture->reference], $this->references($props));
        $this->assertSame(['Meubles Wouters NV'], $props['suggestions']);
        $this->assertSame('Meubles Wouters NV', $props['factures']['data'][0]['client']);
    }

    public function test_un_client_ne_cherche_que_dans_ses_factures(): void
    {
        $sien = $this->facturee('Boulangerie Peeters');
        $autre = $this->facturee('Brasserie Peeters');
        $facturesDuClient = Invoice::where('client_id', $sien->id)->pluck('reference')->all();
        $factureDeLAutre = Invoice::where('client_id', $autre->id)->firstOrFail();

        $props = $this->page($sien->compte(), ['q' => 'peeters']);

        $this->assertSame($facturesDuClient, $this->references($props));
        $this->assertSame(['Boulangerie Peeters'], $props['suggestions']);

        // Le numero d'une facture d'une autre entreprise ne donne rien.
        $props = $this->page($sien->compte(), ['q' => $factureDeLAutre->reference]);

        $this->assertSame([], $this->references($props));
        $this->assertSame([], $props['suggestions']);
    }

    public function test_le_numero_de_facture_se_cherche_en_partie(): void
    {
        $this->facturee('Alpha Logistique SRL');
        $this->facturee('Beta Transport SRL');
        $facture = Invoice::orderBy('id')->firstOrFail();

        $props = $this->page(User::factory()->planificateur()->create(), ['q' => substr($facture->reference, -4)]);

        $this->assertSame([$facture->reference], $this->references($props));
        $this->assertSame([$facture->reference], $props['suggestions']);
    }

    public function test_la_recherche_ignore_les_accents_et_la_casse(): void
    {
        $this->facturee('Société Générale de Transport');
        $this->facturee('Transports Dubois SA');

        $props = $this->page(User::factory()->administrateur()->create(), ['q' => 'SOCIETE generale']);

        $this->assertCount(1, $props['factures']['data']);
        $this->assertSame(['Société Générale de Transport'], $props['suggestions']);
        $this->assertSame(['q' => 'SOCIETE generale'], $props['filtres']);
    }
}
