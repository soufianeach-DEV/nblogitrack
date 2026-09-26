<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\QuoteRequest;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrillesDeDemonstration;
use Tests\TestCase;

/**
 * La demande de devis complete : societe, contacts sur place, colis,
 * temperature, ADR, pieces jointes, puis transformation en commande.
 */
class DevisCompletTest extends TestCase
{
    use GrillesDeDemonstration;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function demande(array $plus = []): array
    {
        return [
            'company_name' => 'Essai SRL', 'contact_name' => 'Nadia Peeters', 'email' => 'nadia@exemple.be',
            'phone' => '+32 470 00 00 00', 'customer_type' => 'Nouvelle entreprise',
            'vat_number' => 'BE0123456749', 'legal_form' => 'SRL', 'contact_function' => 'Responsable logistique',
            'billing_street' => 'Rue Neuve 43', 'billing_postal_code' => '1000', 'billing_city' => 'Bruxelles', 'billing_country' => 'BE',
            'correspondence_language' => 'nl', 'preferred_channel' => 'phone', 'callback_slot' => 'matin',
            'pickup_address' => 'Rue Neuve 43, 1000 Bruxelles, Belgique', 'pickup_country' => 'BE',
            'pickup_lat' => 50.8504, 'pickup_lng' => 4.3488,
            'delivery_address' => 'Meir 50, 2000 Anvers, Belgique', 'delivery_country' => 'BE',
            'delivery_lat' => 51.2194, 'delivery_lng' => 4.4025,
            'pickup_date' => now()->addWeek()->toDateString(), 'delivery_date' => now()->addWeek()->addDay()->toDateString(),
            'trip_type' => 'National (Belgique)', 'frequency' => 'Transport ponctuel', 'date_flexibility' => 'Flexible',
            'monthly_volume' => '6 à 20 envois par mois',
            'pickup_contact_name' => 'Marc Dubois', 'pickup_contact_phone' => '+32 2 000 00 00', 'pickup_opening_hours' => 'lun-ven 7 h - 16 h',
            'pickup_time_slot' => 'matin', 'pickup_has_dock' => false, 'pickup_appointment' => true, 'pickup_access' => ['centre_ville', 'zone_basses_emissions'],
            'goods_type' => 'Palettes', 'vehicle_type' => 'Porteur', 'insurance_value' => 'Standard',
            'packages' => [
                ['type' => 'palette_europe', 'quantite' => 6, 'longueur' => 120, 'largeur' => 80, 'hauteur' => 150, 'poids_unitaire' => 400, 'empilable' => false],
                ['type' => 'colis', 'quantite' => 10, 'longueur' => 60, 'largeur' => 40, 'hauteur' => 40, 'poids_unitaire' => 20, 'empilable' => true],
            ],
            'declared_value' => 25000, 'budget' => 450, 'response_deadline' => now()->addDays(3)->toDateString(),
            'privacy' => true,
            ...$plus,
        ];
    }

    public function test_une_demande_complete_est_enregistree_avec_ses_pieces_jointes(): void
    {
        Storage::fake('local');

        $this->post(route('devis.store'), $this->demande([
            'attachments' => [UploadedFile::fake()->create('bon.pdf', 200, 'application/pdf'), UploadedFile::fake()->image('quai.jpg')],
        ]))->assertSessionHasNoErrors();

        $devis = QuoteRequest::firstOrFail();

        // Poids et volume laisses vides : la somme des colis.
        $this->assertSame(2600, (int) $devis->weight);
        $this->assertSame('9,6 m³', $devis->volume);
        $this->assertSame('nl', $devis->correspondence_language);
        $this->assertSame(['centre_ville', 'zone_basses_emissions'], $devis->pickup_access);
        $this->assertFalse($devis->pickup_has_dock);
        $this->assertCount(2, $devis->packages);
        $this->assertNotNull($devis->privacy_accepted_at);
        $this->assertCount(2, $devis->attachments);
        Storage::disk('local')->assertExists($devis->attachments[0]['chemin']);

        // La piece jointe ne se telecharge que par le personnel.
        $this->get(route('quotes.piece', ['quoteRequest' => $devis->id, 'rang' => 0]))->assertRedirect();
        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('quotes.piece', ['quoteRequest' => $devis->id, 'rang' => 0]))
            ->assertOk()
            ->assertDownload('bon.pdf');
    }

    public function test_les_champs_conditionnels_sont_exiges(): void
    {
        // Plus de cinq envois : sans la limite de debit du formulaire public.
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->post(route('devis.store'), $this->demande(['privacy' => false]))->assertSessionHasErrors('privacy');
        $this->post(route('devis.store'), $this->demande(['needs_temperature' => '1']))->assertSessionHasErrors(['temperature_min', 'temperature_max']);
        $this->post(route('devis.store'), $this->demande(['needs_temperature' => '1', 'temperature_min' => 8, 'temperature_max' => 2]))->assertSessionHasErrors('temperature_max');
        $this->post(route('devis.store'), $this->demande(['is_hazardous' => '1']))->assertSessionHasErrors(['un_number', 'adr_class']);
        $this->post(route('devis.store'), $this->demande(['is_hazardous' => '1', 'un_number' => '12', 'adr_class' => '3']))->assertSessionHasErrors('un_number');

        // Livraison en Suisse : la douane exige l'EORI.
        $suisse = ['delivery_address' => 'Bahnhofstrasse 1, 8001 Zurich, Suisse', 'delivery_country' => 'CH', 'delivery_lat' => 47.37, 'delivery_lng' => 8.54];
        $this->post(route('devis.store'), $this->demande($suisse))->assertSessionHasErrors('eori_number');
        $this->post(route('devis.store'), $this->demande([...$suisse, 'eori_number' => 'BE0123456749']))->assertSessionHasNoErrors();

        $this->assertSame(1, QuoteRequest::count());
    }

    public function test_les_fichiers_hors_format_sont_refuses(): void
    {
        Storage::fake('local');

        $this->post(route('devis.store'), $this->demande([
            'attachments' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')],
        ]))->assertSessionHasErrors('attachments.0');
    }

    public function test_un_devis_accepte_devient_une_commande_du_client(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response([], 503)]);
        $this->creerLesGrillesDeDemonstration();
        $client = Client::factory()->create(['vat_number' => 'BE0123456749']);

        $this->post(route('devis.store'), $this->demande(['needs_temperature' => '1', 'temperature_min' => 2, 'temperature_max' => 8]))->assertSessionHasNoErrors();
        $devis = QuoteRequest::firstOrFail();
        $planificateur = User::factory()->planificateur()->create();

        $this->actingAs($planificateur)->post(route('quotes.order', $devis))->assertRedirect();

        $ordre = TransportOrder::firstOrFail();
        $this->assertSame($client->id, $ordre->client_id);
        $this->assertSame(2600.0, (float) $ordre->weight);
        $this->assertSame('PENDING', $ordre->status);
        $this->assertGreaterThan(0, (float) $ordre->estimated_cost);
        $this->assertStringContainsString('Marc Dubois', $ordre->special_instructions);
        $this->assertStringContainsString('de 2.0 à 8.0 °C', $ordre->special_instructions);
        $this->assertSame(['ORDERED', $ordre->id], [$devis->fresh()->status, $devis->fresh()->converted_order_id]);

        // Une seconde fois : refuse.
        $this->actingAs($planificateur)->post(route('quotes.order', $devis))->assertSessionHas('error');
        $this->assertSame(1, TransportOrder::count());
    }

    public function test_sans_entreprise_cliente_la_transformation_est_refusee(): void
    {
        $this->creerLesGrillesDeDemonstration();
        $this->post(route('devis.store'), $this->demande())->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('quotes.order', QuoteRequest::firstOrFail()))
            ->assertSessionHas('error');

        $this->assertSame(0, TransportOrder::count());
    }

    public function test_le_statut_transformee_ne_se_pose_pas_a_la_main(): void
    {
        $this->post(route('devis.store'), $this->demande())->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->planificateur()->create())
            ->patch(route('quotes.status', QuoteRequest::firstOrFail()), ['status' => 'ORDERED'])
            ->assertSessionHasErrors('status');
    }

    public function test_les_registres_suisse_norvegien_et_britannique_sont_interroges(): void
    {
        Http::fake([
            'www.uid-wse.admin.ch/*' => Http::response('<s:Envelope><s:Body><GetByUIDResponse><GetByUIDResult><organisation><organisationName>Muster AG</organisationName><legalForm>0106</legalForm><street>Bahnhofstrasse</street><houseNumber>1</houseNumber><swissZipCode>8001</swissZipCode><town>Zürich</town><uidregStatusEnterpriseDetail>2</uidregStatusEnterpriseDetail></organisation></GetByUIDResult></GetByUIDResponse></s:Body></s:Envelope>'),
            'data.brreg.no/*' => Http::response(['navn' => 'NORSK FRAKT AS', 'registrertIMvaregisteret' => true, 'konkurs' => false,
                'forretningsadresse' => ['adresse' => ['Karl Johans gate 1'], 'postnummer' => '0154', 'poststed' => 'OSLO'],
                'organisasjonsform' => ['kode' => 'AS'], 'naeringskode1' => ['kode' => '49.410']]),
        ]);

        $this->getJson('/verification-tva?tva=CHE-116.281.710')
            ->assertJsonPath('statut', 'valide')
            ->assertJsonPath('nom', 'Muster AG')
            ->assertJsonPath('adresse.ville', 'Zürich')
            ->assertJsonPath('adresse.pays', 'CH');

        $this->getJson('/verification-tva?tva=NO923609016MVA')
            ->assertJsonPath('statut', 'valide')
            ->assertJsonPath('nom', 'NORSK FRAKT AS')
            ->assertJsonPath('adresse.code_postal', '0154')
            ->assertJsonPath('entreprise.secteur', 'Transport');

        // Sans application declaree chez HMRC : format seulement.
        $this->getJson('/verification-tva?tva=GB123456789')->assertJsonPath('statut', 'non_verifie');

        // Faute de frappe : refusee avant tout appel, avec un exemple.
        $this->getJson('/verification-tva?tva=DE12345')
            ->assertJsonPath('statut', 'format')
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'DE123456789'));
    }

    public function test_le_registre_britannique_est_interroge_avec_une_application_declaree(): void
    {
        config(['services.hmrc.client_id' => 'id', 'services.hmrc.client_secret' => 'secret']);
        Http::fake([
            'api.service.hmrc.gov.uk/oauth/token' => Http::response(['access_token' => 'jeton']),
            'api.service.hmrc.gov.uk/organisations/*' => Http::response(['target' => ['name' => 'British Haulage Ltd', 'vatNumber' => '123456789',
                'address' => ['line1' => '1 High Street', 'line3' => 'London', 'postcode' => 'SW1A 1AA', 'countryCode' => 'GB']]]),
        ]);

        $this->getJson('/verification-tva?tva=GB123456789')
            ->assertJsonPath('statut', 'valide')
            ->assertJsonPath('nom', 'British Haulage Ltd')
            ->assertJsonPath('adresse.code_postal', 'SW1A 1AA');
    }
}
