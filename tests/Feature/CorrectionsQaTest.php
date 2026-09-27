<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Client;
use App\Models\Page;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Facturier;
use App\Support\Localite;
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
}
