<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Client;
use App\Models\Driver;
use App\Models\Page;
use App\Models\PurchaseInvoice;
use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Facturier;
use App\Support\Localite;
use App\Support\Traductions;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Defauts trouves en parcourant l'application role par role, chacun
 * verrouille par un test.
 */
class CorrectionsQaTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_ville_enregistree_sous_son_nom_francais_est_trouvee(): void
    {
        DB::table('postal_codes')->insert([
            ['country_code' => 'BE', 'code' => '9000', 'city' => 'Gand', 'lat' => 51.05, 'lng' => 3.72],
            ['country_code' => 'BE', 'code' => '2000', 'city' => 'Antwerpen', 'lat' => 51.22, 'lng' => 4.40],
        ]);

        $this->assertSame('Gand', Localite::coordonnees('Gand', 'BE')->ville);
        $this->assertSame('Gand', Localite::coordonnees('gand', 'BE', 51.05, 3.72)->ville);
        // L'equivalent local reste trouve quand seul lui est enregistre.
        $this->assertSame('Antwerpen', Localite::coordonnees('Anvers', 'BE')->ville);
        $this->assertNull(Localite::coordonnees('Courtrai', 'BE'));
    }

    public function test_le_lien_de_suivi_bloque_par_la_limite_ne_boucle_pas(): void
    {
        $adresse = route('tracking.show', ['tracking_number' => 'TRK-2026-00001', 'code' => 'ABCDEF']);

        for ($i = 0; $i < 10; $i++) {
            $this->get($adresse)->assertOk();
        }

        // Ouvert depuis le courriel, la page precedente est l'adresse elle-meme.
        $this->withHeader('Referer', $adresse)
            ->get($adresse)
            ->assertRedirect(route('tracking.show'))
            ->assertSessionHas('error');

        $this->get(route('tracking.show'))->assertOk();
    }

    public function test_le_suivi_public_ne_donne_pas_l_identifiant_interne_et_montre_la_date_de_livraison(): void
    {
        $ordre = TransportOrder::factory()->create([
            'status' => 'DELIVERED',
            'delivered_at' => now()->subHour(),
        ]);

        $this->get(route('tracking.show', ['tracking_number' => $ordre->tracking_number, 'code' => $ordre->tracking_code]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('order.id')
                ->where('order.tracking_number', $ordre->tracking_number)
                ->has('order.delivered_at'));
    }

    public function test_un_ecran_refuse_ouvert_directement_ne_boucle_pas(): void
    {
        $client = User::factory()->create();
        $adresse = route('planning.index');

        $this->actingAs($client)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()), 'Referer' => $adresse])
            ->get($adresse)
            ->assertRedirect(route('dashboard'));
    }

    public function test_une_adresse_avec_majuscules_recoit_le_lien_de_reinitialisation(): void
    {
        Notification::fake();
        $compte = User::factory()->create(['email' => 'jean.dupont@exemple.be']);

        $this->post(route('password.email'), ['email' => '  Jean.Dupont@Exemple.BE '])->assertSessionHas('status');

        Notification::assertSentTo($compte, ResetPassword::class);
    }

    public function test_le_personnel_est_cree_avec_une_adresse_en_minuscules(): void
    {
        User::factory()->create(['email' => 'marc@nblogitrack.be']);

        $this->actingAs(User::factory()->administrateur()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('staff.store'), [
                'first_name' => 'Marc', 'last_name' => 'Doublon', 'email' => 'Marc@NBLogiTrack.be', 'role' => 'PLANNER',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_le_profil_accepte_une_adresse_tapee_avec_des_majuscules(): void
    {
        $compte = User::factory()->create(['email' => 'lea@exemple.be']);

        $this->actingAs($compte)
            ->patch(route('profile.update'), ['first_name' => 'Léa', 'last_name' => 'Martin', 'email' => 'Lea@Exemple.be'])
            ->assertSessionHasNoErrors();

        $this->assertSame('lea@exemple.be', $compte->fresh()->email);
    }

    public function test_une_page_sans_adresse_utilisable_est_refusee_et_ne_fait_pas_tomber_le_site(): void
    {
        $this->actingAs(User::factory()->administrateur()->create())
            ->post(route('pages.store'), ['slug' => '-', 'titre_fr' => 'Vide', 'corps_fr' => 'Texte'])
            ->assertSessionHasErrors('slug');

        // Une ligne ancienne restee sans adresse est ecartee du pied du site.
        Page::create(['slug' => '', 'titre_fr' => 'Vide', 'corps_fr' => 'Texte', 'publiee' => true, 'au_pied' => true]);

        $this->get(route('accueil'))->assertOk();
    }

    public function test_des_identifiants_mal_formes_ne_font_pas_d_erreur_500(): void
    {
        $ordre = TransportOrder::factory()->create(['status' => 'PENDING']);
        $vehicule = Vehicle::create([
            'registration' => '1-QAA-001', 'vin' => 'VF1ABCDEF12345678', 'vehicle_type' => 'Porteur',
            'brand' => 'Volvo', 'model' => 'FH', 'capacity_tonnes' => 26, 'is_available' => true, 'mileage' => 1000,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
        ]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), ['vehicle_registration' => $vehicule->registration, 'driver_id' => 'abc'])
            ->assertSessionHasErrors('driver_id');

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('vehicles.update', $vehicule), ['is_available' => true])
            ->assertSessionHasNoErrors();
    }

    public function test_un_mois_facture_apres_coup_garde_la_numerotation_chronologique(): void
    {
        $client = Client::factory()->create();
        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
        ]);
        $recente = app(Facturier::class)->facturer()->first();

        TransportOrder::factory()->livree()->create([
            'client_id' => $client->id,
            'actual_delivery_date' => now()->subMonths(3)->startOfMonth()->addDays(3)->toDateString(),
        ]);
        $oubliee = app(Facturier::class)->facturer(now()->subMonths(3))->first();

        $this->assertGreaterThan($recente->reference, $oubliee->reference);
        $this->assertTrue($oubliee->issued_on->gte($recente->issued_on));
    }

    public function test_un_brouillon_part_a_sa_date_d_emission(): void
    {
        Mail::fake();
        foreach ([1, 2] as $n) {
            TransportOrder::factory()->livree()->create([
                'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays($n)->toDateString(),
            ]);
        }
        [$facture, $future] = app(Facturier::class)->facturer()->all();
        $facture->update(['status' => 'DRAFT', 'issued_on' => today()]);
        $future->update(['status' => 'DRAFT', 'issued_on' => today()->addDays(3)]);

        $this->artisan('factures:envoyer-brouillons')->assertSuccessful();

        $this->assertSame('SENT', $facture->fresh()->status);
        $this->assertSame('DRAFT', $future->fresh()->status);
    }

    public function test_un_mois_mal_forme_est_refuse(): void
    {
        $this->artisan('factures:generer', ['--mois' => '2026-13'])->assertFailed();
        $this->artisan('factures:generer', ['--mois' => now()->format('Y-m')])->assertFailed();
    }

    private function camion(string $plaque = '1-QAB-001'): Vehicle
    {
        return Vehicle::create([
            'registration' => $plaque, 'vin' => 'VF1ABCDEF1234'.substr(md5($plaque), 0, 4), 'vehicle_type' => 'Porteur',
            'brand' => 'Volvo', 'model' => 'FH', 'capacity_tonnes' => 26, 'is_available' => true, 'mileage' => 100000,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
        ]);
    }

    public function test_une_facture_n_est_pas_en_retard_le_jour_de_son_echeance(): void
    {
        TransportOrder::factory()->livree()->create([
            'actual_delivery_date' => now()->subMonth()->startOfMonth()->addDays(3)->toDateString(),
        ]);
        $facture = app(Facturier::class)->facturer()->first();

        $facture->update(['due_on' => today()]);
        $this->assertFalse($facture->fresh()->estEnRetard());

        $facture->update(['due_on' => today()->subDay()]);
        $this->assertTrue($facture->fresh()->estEnRetard());
    }

    public function test_l_api_refuse_dans_la_langue_demandee(): void
    {
        $this->getJson('/api/v1/expeditions', ['Accept-Language' => 'en'])
            ->assertUnauthorized()
            ->assertJsonPath('message', Traductions::t('api_motif.jeton_absent', 'Aucune clé présentée'))
            ->assertJsonPath('motif', 'jeton_absent');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_une_position_aberrante_n_est_pas_retenue(): void
    {
        $this->assertFalse(ShipmentPosition::utilisable(0.0, 0.0, 5));
        $this->assertTrue(ShipmentPosition::utilisable(50.85, 4.35, 5));

        $precedent = new ShipmentPosition(['lat' => 48.85, 'lng' => 2.35, 'recorded_at' => now()->subMinutes(5)]);
        $this->assertFalse(ShipmentPosition::vraisemblable($precedent, 50.85, 4.35));
        $this->assertTrue(ShipmentPosition::vraisemblable($precedent, 48.86, 2.36));
    }

    public function test_les_dates_contradictoires_du_chauffeur_sont_refusees(): void
    {
        $utilisateur = User::factory()->chauffeur()->create();
        $fiche = Driver::create([
            'user_id' => $utilisateur->id, 'license_number' => 'P-'.$utilisateur->id, 'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(), 'is_available' => true, 'employment_status' => 'CDI',
        ]);
        $base = ['is_available' => true, 'adr_certified' => false, 'employment_status' => 'CDI'];

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('drivers.update', $fiche), $base + ['hired_on' => '2020-01-01', 'left_on' => '2010-01-01', 'departure_reason' => array_key_first(Driver::MOTIFS_SORTIE)])
            ->assertSessionHasErrors('left_on');

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('drivers.update', $fiche), $base + ['birth_date' => now()->subYears(6)->toDateString()])
            ->assertSessionHasErrors('birth_date');
    }

    public function test_une_facture_d_achat_en_double_est_reperee_sans_tenir_compte_de_la_casse(): void
    {
        $camion = $this->camion();
        $facture = [
            'supplier_name' => 'Shell Belgique', 'reference' => 'FAC-12', 'category' => array_key_first(PurchaseInvoice::CATEGORIES),
            'vehicle_registration' => $camion->registration, 'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->subMonth()->toDateString(), 'issued_on' => now()->subDays(10)->toDateString(),
            'due_on' => now()->addDays(20)->toDateString(), 'amount_excl_tax' => 100, 'vat_rate' => 21,
        ];
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin)->post(route('purchases.store'), $facture)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('purchases.store'), ['supplier_name' => 'SHELL belgique', 'reference' => 'fac-12'] + $facture)
            ->assertSessionHasErrors('reference');
        $this->actingAs($admin)->post(route('purchases.store'), ['reference' => 'FAC-13', 'issued_on' => '1900-01-01', 'amount_excl_tax' => 0] + $facture)
            ->assertSessionHasErrors(['issued_on', 'amount_excl_tax']);
    }

    public function test_l_administrateur_corrige_une_faute_de_frappe_sur_le_kilometrage(): void
    {
        $camion = $this->camion();
        $camion->update(['mileage' => 1000000]);
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin)->patch(route('vehicles.update', $camion), ['is_available' => true, 'mileage' => 100000])
            ->assertSessionHasErrors('mileage');

        $this->actingAs($admin)->patch(route('vehicles.update', $camion), ['is_available' => true, 'mileage' => 100000, 'corriger_kilometrage' => true])
            ->assertSessionHasNoErrors();

        $this->assertEquals(100000, (float) $camion->fresh()->mileage);
    }

    public function test_une_langue_tapee_en_majuscules_est_reconnue(): void
    {
        $this->get('/FR/tarifs')->assertRedirect('/fr/tarifs');
    }

    public function test_l_alerte_des_enlevements_imminents_mene_a_la_meme_liste(): void
    {
        TransportOrder::factory()->count(2)->create(['status' => 'PENDING', 'vehicle_registration' => null, 'pickup_date' => now()->addDay()]);
        TransportOrder::factory()->count(3)->create(['status' => 'PENDING', 'vehicle_registration' => null, 'pickup_date' => now()->addDays(10)]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->get(route('planning.index', ['status' => 'PENDING', 'imminent' => 1]))
            ->assertInertia(fn ($page) => $page->where('orders.total', 2)->where('imminent', true));
    }
}
