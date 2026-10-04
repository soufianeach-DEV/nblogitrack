<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ApiKeyRequest;
use App\Models\ApiRequest;
use App\Models\Client;
use App\Models\Driver;
use App\Models\DriverAcknowledgement;
use App\Models\Indisponibilite;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\OrderCharge;
use App\Models\Payment;
use App\Models\QuoteRequest;
use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les pieces se gardent sept ans a compter du 1er janvier qui suit leur
 * annee (Code de la TVA, art. 60 ; Code de droit economique, art. III.88),
 * puis s'effacent avec les donnees personnelles qu'elles portent.
 */
class ConservationLegaleTest extends TestCase
{
    use RefreshDatabase;

    private int $numero = 0;

    protected function setUp(): void
    {
        parent::setUp();
        // En 2034, les pieces de 2026 et d'avant ont fini leur delai.
        $this->travelTo(now()->setDate(2034, 3, 1));
    }

    private function facture(Client $client, string $emise, array $champs = []): Invoice
    {
        return Invoice::create([
            'client_id' => $client->id,
            'reference' => 'FAC-TEST-'.(++$this->numero),
            'issued_on' => $emise,
            'due_on' => $emise,
            'period_start' => $emise,
            'period_end' => $emise,
            'amount_excl_tax' => 100,
            'vat_rate' => 21,
            'vat_amount' => 21,
            'amount_incl_tax' => 121,
            'status' => 'SENT',
            ...$champs,
        ]);
    }

    private function ordreFacture(Client $client, string $livre, string $emise): array
    {
        $ordre = TransportOrder::factory()->create(['client_id' => $client->id, 'status' => 'DELIVERED', 'delivered_at' => $livre]);
        $facture = $this->facture($client, $emise);
        InvoiceLine::create(['invoice_id' => $facture->id, 'transport_order_id' => $ordre->id, 'description' => 'Transport', 'amount_excl_tax' => 100]);

        return [$ordre, $facture];
    }

    public function test_une_facture_de_plus_de_sept_ans_s_efface_avec_son_ordre(): void
    {
        $client = Client::factory()->create();
        [$ordre, $facture] = $this->ordreFacture($client, '2026-12-20 10:00:00', '2026-12-20');
        $supplement = OrderCharge::create(['transport_order_id' => $ordre->id, 'label' => 'Attente', 'amount' => 30]);
        InvoiceLine::create(['invoice_id' => $facture->id, 'order_charge_id' => $supplement->id, 'description' => 'Attente', 'amount_excl_tax' => 30]);
        Payment::create(['invoice_id' => $facture->id, 'amount' => 121, 'paid_on' => '2026-12-28', 'method' => 'TRANSFER']);
        ShipmentPosition::create(['transport_order_id' => $ordre->id, 'type' => ShipmentPosition::JALON, 'lat' => 50.85, 'lng' => 4.35, 'recorded_at' => '2026-12-20 10:00:00']);

        [$ordreGarde, $factureGardee] = $this->ordreFacture($client, '2027-01-04 10:00:00', '2027-01-04');

        $this->artisan('pieces:purger')->assertSuccessful();

        $this->assertNull(Invoice::find($facture->id));
        $this->assertSame(0, Payment::where('invoice_id', $facture->id)->count());
        $this->assertNull(TransportOrder::find($ordre->id));
        $this->assertNull(OrderCharge::find($supplement->id));
        $this->assertSame(0, ShipmentPosition::where('transport_order_id', $ordre->id)->count());

        // Une piece de 2027 se garde jusqu'au 31 decembre 2034.
        $this->assertNotNull(Invoice::find($factureGardee->id));
        $this->assertNotNull(TransportOrder::find($ordreGarde->id));
    }

    public function test_une_facture_reste_tant_que_son_avoir_ou_un_paiement_est_conserve(): void
    {
        $client = Client::factory()->create();
        [, $annulee] = $this->ordreFacture($client, '2026-11-02 10:00:00', '2026-11-02');
        $avoir = $this->facture($client, '2027-02-01', ['type' => Invoice::AVOIR, 'credited_invoice_id' => $annulee->id]);
        $annulee->update(['status' => 'CREDITED']);

        [, $payeeTard] = $this->ordreFacture($client, '2026-12-01 10:00:00', '2026-12-01');
        Payment::create(['invoice_id' => $payeeTard->id, 'amount' => 121, 'paid_on' => '2027-01-15', 'method' => 'TRANSFER']);

        $this->artisan('pieces:purger')->assertSuccessful();

        $this->assertNotNull(Invoice::find($annulee->id));
        $this->assertNotNull(Invoice::find($avoir->id));
        $this->assertNotNull(Invoice::find($payeeTard->id));
    }

    public function test_un_ordre_annule_sans_frais_se_garde_trois_ans(): void
    {
        $ancien = TransportOrder::factory()->create(['status' => 'CANCELLED', 'cancelled_at' => now()->subYears(3)->subDay()]);
        $recent = TransportOrder::factory()->create(['status' => 'CANCELLED', 'cancelled_at' => now()->subYears(2)]);

        // Annule avec frais : l'indemnite est facturee, l'ordre suit sa facture.
        $avecFrais = TransportOrder::factory()->create(['status' => 'CANCELLED', 'cancelled_at' => now()->subYears(4), 'cancellation_fee' => 50]);
        $facture = $this->facture($avecFrais->client, now()->subYears(4)->toDateString());
        InvoiceLine::create(['invoice_id' => $facture->id, 'transport_order_id' => $avecFrais->id, 'description' => 'Indemnité', 'amount_excl_tax' => 50]);

        $this->artisan('pieces:purger')->assertSuccessful();

        $this->assertNull(TransportOrder::find($ancien->id));
        $this->assertNotNull(TransportOrder::find($recent->id));
        $this->assertNotNull(TransportOrder::find($avecFrais->id));
    }

    public function test_la_demande_de_devis_devenue_commande_s_efface_avec_elle(): void
    {
        Storage::fake('local');
        $client = Client::factory()->create();
        [$ordre] = $this->ordreFacture($client, '2026-06-01 10:00:00', '2026-06-01');
        $devis = QuoteRequest::create([
            'reference' => 'DEV-2026-0042', 'company_name' => 'Essai SRL', 'contact_name' => 'Nadia Peeters',
            'email' => 'nadia@exemple.be', 'phone' => '+32 470 00 00 00', 'customer_type' => 'Entreprise',
            'pickup_address' => 'Rue Neuve 43, 1000 Bruxelles', 'pickup_lat' => 50.85, 'pickup_lng' => 4.35,
            'delivery_address' => 'Meir 50, 2000 Anvers', 'delivery_lat' => 51.22, 'delivery_lng' => 4.40,
            'delivery_country' => 'BE', 'pickup_date' => '2026-05-20', 'trip_type' => 'Aller simple',
            'frequency' => 'Ponctuel', 'date_flexibility' => 'Date fixe', 'goods_type' => TransportOrder::MARCHANDISES[0],
            'vehicle_type' => 'Porteur', 'insurance_value' => 'Standard', 'weight' => 1000,
            'status' => 'ORDERED', 'converted_order_id' => $ordre->id,
        ]);
        Storage::disk('local')->put('devis/DEV-2026-0042/bon.pdf', 'pdf');

        $this->artisan('pieces:purger')->assertSuccessful();

        $this->assertNull(QuoteRequest::find($devis->id));
        Storage::disk('local')->assertMissing('devis/DEV-2026-0042');
    }

    public function test_une_entreprise_desinscrite_s_efface_quand_il_ne_lui_reste_aucune_piece(): void
    {
        $partie = Client::factory()->create();
        $this->ordreFacture($partie, '2026-03-01 10:00:00', '2026-03-01');
        $partie->users()->update(['email' => 'supprime-test@anonyme.invalid']);
        User::where('client_id', $partie->id)->get()->each->delete();
        $partie->delete();

        $gardee = Client::factory()->create();
        $this->ordreFacture($gardee, '2027-03-01 10:00:00', '2027-03-01');
        $gardee->delete();

        $this->artisan('pieces:purger')->assertSuccessful();

        $this->assertNull(Client::withTrashed()->find($partie->id));
        $this->assertSame(0, User::withTrashed()->where('client_id', $partie->id)->count());
        $this->assertNotNull(Client::withTrashed()->find($gardee->id));
    }

    public function test_les_jalons_s_effacent_un_an_apres_la_livraison(): void
    {
        $ancien = TransportOrder::factory()->create(['status' => 'DELIVERED', 'delivered_at' => now()->subYear()->subDay()]);
        $recent = TransportOrder::factory()->create(['status' => 'DELIVERED', 'delivered_at' => now()->subMonths(6)]);
        foreach ([$ancien, $recent] as $ordre) {
            ShipmentPosition::create(['transport_order_id' => $ordre->id, 'type' => ShipmentPosition::JALON, 'lat' => 50.85, 'lng' => 4.35, 'recorded_at' => $ordre->delivered_at]);
        }

        $this->artisan('positions:purger')->assertSuccessful();

        $this->assertSame(0, ShipmentPosition::where('transport_order_id', $ancien->id)->count());
        $this->assertSame(1, ShipmentPosition::where('transport_order_id', $recent->id)->count());
        $this->assertNotNull($ancien->fresh()->delivered_at);
    }

    public function test_une_cle_revoquee_depuis_douze_mois_s_efface_avec_les_demandes_traitees(): void
    {
        $admin = User::factory()->administrateur()->create();
        $cle = fn (?string $revoquee) => tap(ApiKey::generer(['name' => 'Clé', 'client_id' => null, 'abilities' => ['lecture'], 'created_by' => $admin->id])[0])
            ->update(['revoked_at' => $revoquee]);

        $ancienne = $cle(now()->subMonths(13));
        $recente = $cle(now()->subMonths(2));
        $active = $cle(null);
        // Revoquee depuis longtemps, mais encore presentee le mois dernier :
        // son refus reste au journal, la cle avec lui.
        $encorePresentee = $cle(now()->subMonths(13));
        ApiRequest::create(['api_key_id' => $encorePresentee->id, 'method' => 'GET', 'path' => 'api/v1/expeditions', 'status' => 401,
            'ip_address' => '198.51.100.7', 'duration_ms' => 3, 'refus' => 'revoquee', 'created_at' => now()->subMonth()]);

        $client = Client::factory()->create();
        $traitee = ApiKeyRequest::create(['client_id' => $client->id, 'abilities' => ['lecture'], 'status' => ApiKeyRequest::REFUSEE,
            'refusal_reason' => 'Usage non précisé', 'handled_at' => now()->subMonths(13)]);
        $enAttente = ApiKeyRequest::create(['client_id' => $client->id, 'abilities' => ['lecture']]);
        ApiKeyRequest::whereKey($enAttente->id)->update(['created_at' => now()->subMonths(14)]);

        $this->artisan('journaux:purger')->assertSuccessful();

        $this->assertNull(ApiKey::find($ancienne->id));
        $this->assertNotNull(ApiKey::find($recente->id));
        $this->assertNotNull(ApiKey::find($active->id));
        $this->assertNotNull(ApiKey::find($encorePresentee->id));
        $this->assertNull(ApiKeyRequest::find($traitee->id));
        $this->assertNotNull(ApiKeyRequest::find($enAttente->id));
    }

    private function chauffeur(string $parti): Driver
    {
        $utilisateur = User::factory()->chauffeur()->create(['first_name' => 'Jan', 'last_name' => 'Peeters', 'phone' => '+32 470 11 22 33']);

        return Driver::create([
            'user_id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => '2030-01-01',
            'birth_date' => '1980-05-05',
            'medical_exam_date' => '2031-01-01',
            'left_on' => $parti,
            'departure_reason' => 'DEMISSION',
        ]);
    }

    public function test_les_donnees_d_un_chauffeur_parti_s_effacent_un_an_apres_son_depart(): void
    {
        $ancien = $this->chauffeur(now()->subYear()->subDay()->toDateString());
        $recent = $this->chauffeur(now()->subMonths(6)->toDateString());
        TransportOrder::factory()->create(['driver_id' => $ancien->id, 'status' => 'DELIVERED', 'delivered_at' => now()->subYears(2)]);
        DriverAcknowledgement::create(['user_id' => $ancien->user_id, 'version' => now()->subYears(2), 'acknowledged_at' => now()->subYears(2), 'ip_address' => '198.51.100.20']);
        Indisponibilite::create(['driver_id' => $ancien->id, 'du' => now()->subYears(2)->toDateString(), 'au' => now()->subYears(2)->addDays(3)->toDateString(), 'motif' => 'MALADIE']);

        $this->artisan('chauffeurs:cloturer-departs')->assertSuccessful();

        $this->assertSame(0, DriverAcknowledgement::where('user_id', $ancien->user_id)->count());
        $this->assertSame(0, Indisponibilite::where('driver_id', $ancien->id)->count());

        $ancien->refresh();
        $this->assertSame('EFFACE-'.$ancien->id, $ancien->license_number);
        $this->assertNull($ancien->birth_date);
        $this->assertNull($ancien->medical_exam_date);
        $this->assertNull($ancien->departure_reason);
        $this->assertStringEndsWith('@anonyme.invalid', $ancien->user->email);
        $this->assertNull($ancien->user->phone);
        // Le nom reste attache aux dossiers de transport conserves.
        $this->assertSame('Peeters', $ancien->user->last_name);

        $this->assertSame('PERMIS-'.$recent->user_id, $recent->fresh()->license_number);

        // Plus aucun dossier ne le cite : son nom s'efface aussi.
        TransportOrder::where('driver_id', $ancien->id)->delete();
        $this->artisan('pieces:purger')->assertSuccessful();
        $this->assertSame('chauffeur', $ancien->user->fresh()->last_name);
    }
}
