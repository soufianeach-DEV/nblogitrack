<?php

namespace Tests\Feature;

use App\Mail\FactureEmise;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\Facturier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnvoiFactureTest extends TestCase
{
    use RefreshDatabase;

    private function facture(?Client $client = null): Invoice
    {
        $client ??= Client::factory()->create();

        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
            'estimated_cost' => 500,
        ]);

        return app(Facturier::class)->facturer()->first();
    }

    public function test_la_commande_envoie_chaque_facture_avec_le_pdf_et_le_xml(): void
    {
        Mail::fake();

        $client = Client::factory()->create();
        ClientContact::create([
            'client_id' => $client->id,
            'first_name' => 'Nadia',
            'last_name' => 'Peeters',
            'email' => 'compta@exemple.be',
            'is_primary' => true,
        ]);
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
            'estimated_cost' => 500,
        ]);

        $this->artisan('factures:generer')->assertSuccessful();

        $facture = Invoice::first();

        Mail::assertSent(FactureEmise::class, fn (FactureEmise $courriel) => $courriel->hasTo('compta@exemple.be')
            && $courriel->facture->is($facture));

        $courriel = Mail::sent(FactureEmise::class)->first();
        $noms = collect($courriel->attachments())->map(fn ($piece) => $piece->as)->all();

        $this->assertSame([$facture->reference.'.pdf', $facture->reference.'.xml'], $noms);
        $this->assertNotNull($facture->sent_at);
        $this->assertTrue(ActivityLog::where('action', 'invoice.sent')->exists());
    }

    public function test_les_pieces_jointes_se_construisent(): void
    {
        $facture = $this->facture();
        $courriel = new FactureEmise($facture, 'Nadia');

        $pdf = null;
        $xml = null;

        foreach ($courriel->attachments() as $piece) {
            $piece->attachWith(
                fn () => null,
                function ($donnees) use ($piece, &$pdf, &$xml) {
                    str_ends_with($piece->as, '.pdf') ? $pdf = $donnees() : $xml = $donnees();
                },
            );
        }

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString($facture->reference, $xml);
        $this->assertStringContainsString($facture->reference, $courriel->render());
    }

    public function test_sans_contact_la_facture_part_a_l_adresse_du_compte(): void
    {
        Mail::fake();

        $facture = $this->facture();

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('invoices.send', $facture))
            ->assertSessionHas('success');

        Mail::assertSent(FactureEmise::class, fn ($courriel) => $courriel->hasTo(User::find($facture->client_id)->email));
    }

    public function test_une_panne_de_courriel_n_empeche_pas_l_emission(): void
    {
        $client = Client::factory()->create();
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
            'estimated_cost' => 500,
        ]);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('serveur de courriel injoignable'));

        $this->artisan('factures:generer')->assertSuccessful();

        $facture = Invoice::first();
        $this->assertNotNull($facture);
        $this->assertNull($facture->sent_at);
        $this->assertTrue(ActivityLog::where('action', 'invoice.send_failed')->exists());
    }

    public function test_sans_envoi_la_commande_n_envoie_rien(): void
    {
        Mail::fake();

        $client = Client::factory()->create();
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
            'estimated_cost' => 500,
        ]);

        $this->artisan('factures:generer --sans-envoi')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull(Invoice::first()->sent_at);
    }

    public function test_le_personnel_renvoie_une_facture(): void
    {
        Mail::fake();

        $facture = $this->facture();

        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('invoices.send', $facture))
            ->assertSessionHas('success');

        Mail::assertSent(FactureEmise::class);
        $this->assertNotNull($facture->refresh()->sent_at);
    }

    public function test_un_client_ne_declenche_pas_l_envoi(): void
    {
        Mail::fake();

        $facture = $this->facture();

        $this->actingAs(User::find($facture->client_id))
            ->post(route('invoices.send', $facture))
            ->assertForbidden();

        Mail::assertNothingSent();
    }
}
