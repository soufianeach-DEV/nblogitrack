<?php

namespace Tests\Feature;

use App\Mail\CompteActive;
use App\Mail\FactureEmise;
use App\Mail\InscriptionRefusee;
use App\Mail\OrdreCree;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\TransportOrder;
use App\Models\User;
use App\Support\EnvoiFacture;
use App\Support\Facturier;
use App\Support\FacturePdf;
use App\Support\Traductions;
use Database\Seeders\TranslationSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Chaque courriel part dans la langue choisie par son destinataire.
 */
class CourrielsTraduitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TranslationSeeder::class);
        Traductions::oublier();
    }

    private function client(string $langue): Client
    {
        return Client::factory()->create([
            'id' => User::factory()->create(['locale' => $langue, 'first_name' => 'Nadia'])->id,
        ]);
    }

    /** @return array<string, array{string, string, string}> */
    public static function langues(): array
    {
        return [
            'neerlandais' => ['nl', 'Uw account is geactiveerd', 'Uw zending is geregistreerd'],
            'anglais' => ['en', 'Your account is activated', 'Your shipment is registered'],
            'francais' => ['fr', 'Votre compte est activé', 'Votre expédition est enregistrée'],
        ];
    }

    /** @dataProvider langues */
    public function test_les_courriels_du_client_suivent_sa_langue(string $langue, string $activation, string $ordre): void
    {
        $client = $this->client($langue);
        $utilisateur = User::find($client->id);

        $courriel = new CompteActive($client, $utilisateur);
        $courriel->assertSeeInHtml($activation);
        $this->assertStringContainsString('lang="'.$langue.'"', $courriel->render());

        $commande = TransportOrder::factory()->create(['client_id' => $client->id]);
        (new OrdreCree($commande, $utilisateur))->assertSeeInHtml($ordre);
    }

    public function test_le_refus_d_inscription_part_en_neerlandais(): void
    {
        $client = $this->client('nl');

        $courriel = new InscriptionRefusee($client, User::find($client->id), 'Numéro de TVA inactif');

        $courriel->assertSeeInHtml('Uw aanvraag werd niet aanvaard');
        $courriel->assertDontSeeInHtml('Bonjour');
    }

    public function test_la_facture_et_son_sujet_suivent_la_langue_du_client(): void
    {
        Mail::fake();

        $client = $this->client('en');
        ClientContact::create([
            'client_id' => $client->id,
            'first_name' => 'Tom',
            'last_name' => 'Peeters',
            'email' => 'accounts@example.com',
            'is_primary' => true,
        ]);
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
            'estimated_cost' => 1234.5,
        ]);

        $facture = app(Facturier::class)->facturer()->first();
        EnvoiFacture::envoyer($facture);

        Mail::assertSent(FactureEmise::class, function (FactureEmise $courriel) use ($facture) {
            $courriel->assertSeeInHtml('Your invoice '.$facture->reference);
            $courriel->assertSeeInHtml('Hello Tom,');

            return $courriel->locale === 'en';
        });
    }

    public function test_le_pdf_de_la_facture_se_lit_dans_la_langue_demandee(): void
    {
        $client = $this->client('fr');
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
            'estimated_cost' => 500,
        ]);
        $facture = app(Facturier::class)->facturer()->first();

        app()->setLocale('nl');
        $html = view('pdf.facture', ['facture' => $facture->load('client', 'lines.transportOrder'), 'qr' => null])->render();

        $this->assertStringContainsString('FACTUUR', $html);
        $this->assertStringContainsString('Totaal incl. btw', $html);
        $this->assertStringContainsString(' naar ', $html);
        $this->assertStringNotContainsString('Total TTC', $html);
        $this->assertStringStartsWith('%PDF', FacturePdf::contenu($facture));
    }

    public function test_le_lien_de_mot_de_passe_part_dans_la_langue_du_compte(): void
    {
        Notification::fake();

        $utilisateur = User::factory()->create(['locale' => 'nl', 'first_name' => 'Wim']);

        Password::sendResetLink(['email' => $utilisateur->email]);

        Notification::assertSentTo($utilisateur, ResetPassword::class, function (ResetPassword $notification) use ($utilisateur) {
            app()->setLocale($utilisateur->preferredLocale());
            $message = $notification->toMail($utilisateur);
            $html = view($message->view, $message->viewData)->render();

            $this->assertSame('Kies uw NBLogiTrack-wachtwoord', $message->subject);
            $this->assertStringContainsString('Beste Wim,', $html);
            $this->assertStringContainsString('/nl/reset-password/', $html);

            return true;
        });
    }
}
