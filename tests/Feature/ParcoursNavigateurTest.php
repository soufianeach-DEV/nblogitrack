<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\QuoteRequest;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
