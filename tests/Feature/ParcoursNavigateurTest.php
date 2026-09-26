<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ApiKey;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\QuoteRequest;
use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\FactureUbl;
use App\Support\Facturier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Les bugs trouves en parcourant l'application dans un navigateur, role par
 * role. Chaque test reproduit un parcours qui echouait.
 */
class ParcoursNavigateurTest extends TestCase
{
    use RefreshDatabase;

    private function chauffeur(array $champs = []): Driver
    {
        $utilisateur = User::factory()->chauffeur()->create();

        return Driver::create([
            'id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
            ...$champs,
        ]);
    }

    private function camion(string $immatriculation = '1-ABC-123', array $champs = []): Vehicle
    {
        return Vehicle::create([
            'registration' => $immatriculation,
            'vin' => 'VF1'.str_pad((string) crc32($immatriculation), 14, '0'),
            'vehicle_type' => 'Porteur',
            'brand' => 'Volvo',
            'model' => 'FH',
            'capacity_tonnes' => 12,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
            ...$champs,
        ]);
    }

    private function affecter(TransportOrder $ordre, Vehicle $camion, Driver $chauffeur)
    {
        return $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), [
                'vehicle_registration' => $camion->registration,
                'driver_id' => $chauffeur->id,
            ]);
    }

    public function test_une_expedition_sans_date_d_enlevement_s_affecte(): void
    {
        $ordre = TransportOrder::factory()->create(['pickup_date' => null, 'weight' => 1000, 'distance_km' => 80]);

        $this->affecter($ordre, $this->camion(), $this->chauffeur())->assertSessionHasNoErrors();

        $ordre->refresh();
        $this->assertSame('ASSIGNED', $ordre->status);
        $this->assertTrue($ordre->pickup_date->isToday());
    }

    public function test_les_documents_du_chauffeur_valent_jusqu_au_jour_de_la_mission(): void
    {
        $ordre = TransportOrder::factory()->create([
            'pickup_date' => now()->addDays(20)->setTime(8, 0),
            'weight' => 1000,
            'distance_km' => 80,
        ]);
        $chauffeur = $this->chauffeur(['tacho_card_expiry' => now()->addDays(10)->toDateString()]);

        $this->affecter($ordre, $this->camion(), $chauffeur)->assertSessionHasErrors('driver_id');

        $this->assertSame('PENDING', $ordre->refresh()->status);
    }

    public function test_une_semi_remorque_exige_le_permis_ce(): void
    {
        $ordre = TransportOrder::factory()->create(['pickup_date' => now()->addDay(), 'weight' => 1000, 'distance_km' => 80]);
        $semi = $this->camion('1-SEM-001', ['vehicle_type' => 'Semi-remorque', 'capacity_tonnes' => 24]);

        $this->affecter($ordre, $semi, $this->chauffeur(['license_type' => 'C1E']))
            ->assertSessionHasErrors('driver_id');

        $this->affecter($ordre, $semi, $this->chauffeur(['license_type' => 'CE']))
            ->assertSessionHasNoErrors();
    }

    public function test_une_mission_de_plusieurs_jours_occupe_le_chauffeur_jusqu_au_bout(): void
    {
        $chauffeur = $this->chauffeur();
        $jour = now()->addDays(3)->setTime(6, 0);

        // Lyon : plus de 9 h de conduite, donc deux journees.
        TransportOrder::factory()->create([
            'status' => 'ASSIGNED',
            'driver_id' => $chauffeur->id,
            'vehicle_registration' => $this->camion('1-LYO-001')->registration,
            'pickup_date' => $jour,
            'distance_km' => 800,
            'weight' => 1000,
        ]);

        $lendemain = TransportOrder::factory()->create([
            'pickup_date' => $jour->copy()->addDay()->setTime(8, 0),
            'distance_km' => 80,
            'weight' => 1000,
        ]);

        $this->affecter($lendemain, $this->camion('1-AUT-002'), $chauffeur)
            ->assertSessionHasErrors('driver_id');
    }

    public function test_le_suivi_direct_ne_s_ouvre_pas_sur_une_expedition_en_attente(): void
    {
        $ordre = TransportOrder::factory()->create(['status' => 'PENDING']);

        $this->actingAs(User::factory()->planificateur()->create())
            ->patch(route('planning.tracking', $ordre))
            ->assertSessionHas('error');

        $this->assertFalse((bool) $ordre->refresh()->suivi_direct);
    }

    public function test_l_annulation_par_le_planificateur_garde_sa_trace(): void
    {
        $planificateur = User::factory()->planificateur()->create();
        $ordre = TransportOrder::factory()->create(['status' => 'PENDING', 'suivi_direct' => false]);

        $this->actingAs($planificateur)
            ->patch(route('planning.status', $ordre), ['status' => 'CANCELLED'])
            ->assertSessionHasNoErrors();

        $ordre->refresh();
        $this->assertSame('CANCELLED', $ordre->status);
        $this->assertNotNull($ordre->cancelled_at);
        $this->assertSame($planificateur->id, $ordre->cancelled_by);
        $this->assertNull($ordre->cancellation_fee);
    }

    public function test_un_devis_transmis_ne_revient_pas_en_arriere(): void
    {
        $demande = QuoteRequest::create([
            'reference' => 'DEV-2026-0001',
            'company_name' => 'Essai SRL',
            'contact_name' => 'Nadia Peeters',
            'email' => 'nadia@exemple.be',
            'phone' => '+32 470 00 00 00',
            'customer_type' => 'Entreprise',
            'pickup_address' => 'Rue Neuve 43, 1000 Bruxelles',
            'pickup_lat' => 50.8504,
            'pickup_lng' => 4.3488,
            'delivery_address' => 'Meir 50, 2000 Anvers',
            'delivery_lat' => 51.2194,
            'delivery_lng' => 4.4025,
            'delivery_country' => 'BE',
            'pickup_date' => now()->addWeek()->toDateString(),
            'trip_type' => 'Aller simple',
            'frequency' => 'Ponctuel',
            'date_flexibility' => 'Date fixe',
            'goods_type' => TransportOrder::MARCHANDISES[0],
            'vehicle_type' => 'Porteur',
            'insurance_value' => 'Standard',
            'weight' => 1000,
            'status' => 'QUOTED',
        ]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->patch(route('quotes.status', $demande), ['status' => 'PENDING'])
            ->assertSessionHas('error');

        $this->assertSame('QUOTED', $demande->refresh()->status);
    }

    public function test_l_enlevement_ne_se_confirme_pas_des_jours_a_l_avance(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->affectee()->create([
            'driver_id' => $chauffeur->id,
            'pickup_date' => now()->addDays(3)->setTime(8, 0),
        ]);

        $this->actingAs($chauffeur->user)
            ->patch(route('missions.status', $ordre), ['statut' => 'IN_PROGRESS'])
            ->assertSessionHas('error');

        $this->assertSame('ASSIGNED', $ordre->refresh()->status);
    }

    public function test_les_limites_de_debit_ne_se_partagent_pas(): void
    {
        // Vingt suggestions de villes pendant la saisie d'une adresse ne
        // doivent pas epuiser le quota, bien plus serre, de l'annulation.
        $ordre = TransportOrder::factory()->create(['status' => 'PENDING']);
        $client = User::find($ordre->client_id);

        foreach (range(1, 20) as $i) {
            $this->actingAs($client)->getJson('/geo/villes?q=Bru');
        }

        $this->actingAs($client)
            ->patch(route('transport-orders.cancel', $ordre), ['frais' => 0])
            ->assertSessionHas('success');
    }

    // --- Deuxieme serie : back-office ---------------------------------------

    public function test_un_vehicule_s_enregistre_sans_toucher_au_kilometrage(): void
    {
        $camion = $this->camion('1-KMS-001', ['mileage' => 458099.64]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('vehicles.update', $camion->registration), [
                'mileage' => 458099,
                'is_available' => false,
                'inspection_date' => now()->subMonth()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $camion->refresh();
        $this->assertFalse((bool) $camion->is_available);
        $this->assertEquals(458099.64, (float) $camion->mileage);
    }

    public function test_un_second_lien_dans_la_minute_n_est_pas_annonce_comme_envoye(): void
    {
        $admin = User::factory()->administrateur()->create();
        $planificateur = User::factory()->planificateur()->create();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('staff.reset-link', $planificateur))
            ->assertSessionHas('success');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('staff.reset-link', $planificateur))
            ->assertSessionHas('error');
    }

    public function test_un_chauffeur_sorti_ne_se_reactive_pas_par_le_personnel(): void
    {
        $chauffeur = $this->chauffeur(['left_on' => now()->subDays(3)->toDateString()]);
        $chauffeur->user->update(['is_active' => false]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('staff.toggle', $chauffeur->user))
            ->assertSessionHasErrors('is_active');

        $this->assertFalse((bool) $chauffeur->user->refresh()->is_active);
    }

    public function test_le_planificateur_ne_voit_pas_les_validations_d_entreprises(): void
    {
        Client::factory()->enAttente()->create(['company_name' => 'Attente SRL']);
        $planificateur = User::factory()->planificateur()->create();

        $this->actingAs($planificateur)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('validations', null));

        $suggestions = $this->actingAs($planificateur)
            ->getJson(route('recherche.suggestions', ['q' => 'Attente']))
            ->json('suggestions');

        foreach ($suggestions as $suggestion) {
            $this->assertStringNotContainsString('/entreprises', $suggestion['url']);
        }
    }

    public function test_une_date_illisible_dans_le_journal_est_ignoree(): void
    {
        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('activity-logs.index', ['du' => 'abc', 'au' => '2026-99-99']))
            ->assertOk();
    }

    public function test_une_cle_d_ecriture_doit_avoir_une_entreprise(): void
    {
        $this->actingAs(User::factory()->administrateur()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-keys.store'), ['nom' => 'Sans entreprise', 'permissions' => ['ecriture']])
            ->assertSessionHasErrors('client_id');
    }

    public function test_une_entreprise_refusee_apprend_que_sa_demande_n_est_pas_retenue(): void
    {
        $client = Client::factory()->create(['is_validated' => false, 'rejection_reason' => 'Numéro de TVA inactif']);
        User::find($client->id)->update(['is_active' => false]);

        $this->post(route('login'), ['email' => User::find($client->id)->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Votre demande d\'inscription n\'a pas été retenue. Le motif vous a été envoyé par e-mail.']);
    }

    public function test_un_refus_pendant_la_navigation_reste_sur_la_page(): void
    {
        $this->actingAs(User::factory()->planificateur()->create())
            ->from(route('dashboard'))
            ->get(route('clients.index'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    // --- Troisieme serie : parcours client et conception ------------------

    public function test_sans_cle_stripe_le_paiement_en_ligne_ne_plante_pas(): void
    {
        config(['services.stripe.secret' => null]);

        $client = Client::factory()->create();
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
        ]);
        $facture = app(Facturier::class)->facturer()->first();

        $this->actingAs(User::find($client->id))
            ->get(route('invoices.show', $facture))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('peutPayerEnLigne', false));

        $this->actingAs(User::find($client->id))
            ->post(route('payments.payer', $facture))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_changer_d_adresse_change_celle_des_factures(): void
    {
        $client = Client::factory()->create();
        $compte = User::find($client->id);
        ClientContact::create([
            'client_id' => $client->id, 'first_name' => $compte->first_name, 'last_name' => $compte->last_name,
            'email' => $compte->email, 'is_primary' => true,
        ]);

        $this->actingAs($compte)->patch(route('profile.update'), [
            'first_name' => 'Nadia', 'last_name' => 'Peeters',
            'email' => 'nouvelle@exemple.be', 'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame('nouvelle@exemple.be', ClientContact::where('client_id', $client->id)->value('email'));
    }

    public function test_une_adresse_en_majuscules_se_connecte(): void
    {
        $utilisateur = User::factory()->create(['email' => 'nadia@exemple.be']);

        $this->post(route('login'), ['email' => 'Nadia@Exemple.BE', 'password' => 'password']);

        $this->assertAuthenticatedAs($utilisateur);
    }

    public function test_le_suivi_public_accepte_un_numero_en_minuscules(): void
    {
        $ordre = TransportOrder::factory()->create();

        $this->get(route('tracking.show', [
            'tracking_number' => strtolower($ordre->tracking_number),
            'code' => strtolower($ordre->tracking_code),
        ]))->assertInertia(fn (AssertableInertia $page) => $page->where('order.tracking_number', $ordre->tracking_number));
    }

    public function test_deux_factures_du_meme_client_ont_des_communications_distinctes(): void
    {
        $client = Client::factory()->create();

        foreach (['2026-03-04', '2026-04-04'] as $jour) {
            TransportOrder::factory()->livree()->create(['client_id' => $client->id, 'actual_delivery_date' => $jour]);
        }

        $communications = app(Facturier::class)->facturer()->pluck('payment_reference');

        $this->assertCount(2, $communications->unique());
    }

    public function test_la_numerotation_depasse_9999_sans_se_repeter(): void
    {
        $client = Client::factory()->create();

        foreach (['FAC-2027-9999', 'FAC-2027-10000'] as $reference) {
            Invoice::create([
                'client_id' => $client->id, 'reference' => $reference, 'issued_on' => '2027-01-01', 'due_on' => '2027-01-31',
                'period_start' => '2026-12-01', 'period_end' => '2026-12-31', 'amount_excl_tax' => 1, 'vat_rate' => 21,
                'vat_amount' => 0.21, 'amount_incl_tax' => 1.21, 'status' => 'SENT',
            ]);
        }

        $this->assertSame(10001, app(Facturier::class)->prochainRang(2027));
    }

    public function test_un_camion_au_controle_technique_echu_n_est_pas_affecte(): void
    {
        $ordre = TransportOrder::factory()->create(['pickup_date' => now()->addDays(10), 'weight' => 1000, 'distance_km' => 80]);
        $camion = $this->camion('1-CTE-001', ['inspection_valid_until' => now()->addDays(5)->toDateString()]);

        $this->affecter($ordre, $camion, $this->chauffeur())->assertSessionHasErrors('vehicle_registration');
    }

    public function test_la_cle_d_une_entreprise_desactivee_est_refusee(): void
    {
        $client = Client::factory()->create();
        [, $jeton] = ApiKey::generer([
            'name' => 'Partenaire', 'client_id' => $client->id, 'abilities' => ['lecture'],
            'created_by' => User::factory()->administrateur()->create()->id,
        ]);

        User::find($client->id)->update(['is_active' => false]);

        $this->getJson('/api/v1/expeditions', ['Authorization' => 'Bearer '.$jeton])->assertForbidden();
    }

    public function test_un_client_suisse_est_facture_hors_champ_et_non_en_autoliquidation(): void
    {
        $client = Client::factory()->create(['country' => 'Suisse']);
        TransportOrder::factory()->livree()->create(['client_id' => $client->id, 'actual_delivery_date' => '2026-03-04']);

        $facture = app(Facturier::class)->facturer()->first();
        $xml = FactureUbl::pour($facture->load('client', 'lines'));

        $this->assertStringContainsString('VATEX-EU-O', $xml);
        $this->assertStringNotContainsString('VATEX-EU-AE', $xml);
    }

    public function test_une_marchandise_chargee_ne_revient_pas_en_attente(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->enRoute()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.desaffecter', $ordre), ['motif' => 'Panne moteur'])
            ->assertSessionHasErrors('motif');

        $ordre->refresh();
        $this->assertSame('IN_PROGRESS', $ordre->status);
        $this->assertSame($chauffeur->id, $ordre->driver_id);

        $this->actingAs(User::find($ordre->client_id))
            ->patch(route('transport-orders.cancel', $ordre), ['frais' => 0])
            ->assertSessionHas('error');
    }

    public function test_les_positions_d_une_expedition_annulee_sont_purgees(): void
    {
        $ordre = TransportOrder::factory()->create(['status' => 'CANCELLED']);
        $ordre->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();

        ShipmentPosition::create([
            'transport_order_id' => $ordre->id, 'driver_id' => $this->chauffeur()->id,
            'type' => ShipmentPosition::ROUTE, 'lat' => 50.85, 'lng' => 4.35, 'recorded_at' => now()->subDays(10),
        ]);

        $this->artisan('positions:purger', ['--jours' => 7])->assertSuccessful();

        $this->assertSame(0, ShipmentPosition::where('transport_order_id', $ordre->id)->count());
    }

    // --- Chauffeur (deuxieme parcours) ---------------------------------------

    public function test_une_mission_retiree_renvoie_le_chauffeur_a_sa_liste_avec_un_message(): void
    {
        $ancien = $this->chauffeur();
        $ordre = TransportOrder::factory()->affectee()->create(['driver_id' => $this->chauffeur()->id]);

        $this->actingAs(User::find($ancien->id))
            ->patch(route('missions.status', $ordre), ['statut' => 'IN_PROGRESS'])
            ->assertRedirect(route('missions.index'))
            ->assertSessionHas('error');

        $this->actingAs(User::find($ancien->id))
            ->postJson(route('missions.position', $ordre), ['lat' => 50.85, 'lng' => 4.35])
            ->assertOk()
            ->assertJson(['suivi' => false, 'motif' => 'retiree']);
    }

    public function test_une_mission_annulee_le_dit_au_chauffeur(): void
    {
        $chauffeur = $this->chauffeur();
        $ordre = TransportOrder::factory()->create([
            'driver_id' => $chauffeur->id,
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
        ]);

        $this->actingAs(User::find($chauffeur->id))
            ->patch(route('missions.status', $ordre), ['statut' => 'IN_PROGRESS'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'annulée'));
    }

    public function test_la_deconnexion_garde_la_langue(): void
    {
        $this->actingAs(User::factory()->planificateur()->create())
            ->post('/nl/logout')
            ->assertRedirect('/nl');
    }

    public function test_l_historique_du_chauffeur_montre_les_plus_recentes_d_abord(): void
    {
        $chauffeur = $this->chauffeur();
        $ancienne = TransportOrder::factory()->livree()->create(['driver_id' => $chauffeur->id, 'delivered_at' => now()->subDays(10)]);
        $recente = TransportOrder::factory()->livree()->create(['driver_id' => $chauffeur->id, 'delivered_at' => now()->subDay()]);
        $aFaire = TransportOrder::factory()->affectee()->create(['driver_id' => $chauffeur->id]);

        $this->actingAs(User::find($chauffeur->id))
            ->get(route('missions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('missions.0.id', $aFaire->id)
                ->where('missions.1.id', $recente->id)
                ->where('missions.2.id', $ancienne->id));
    }
}
