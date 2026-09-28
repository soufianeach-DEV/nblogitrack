<?php

namespace Tests\Feature;

use App\Mail\OrdreAffecte;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AvisAffectationTest extends TestCase
{
    use RefreshDatabase;

    private function binome(): array
    {
        $chauffeur = User::factory()->chauffeur()->create();
        $driver = Driver::create([
            'user_id' => $chauffeur->id,
            'license_number' => 'PERMIS-'.$chauffeur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'cpc_expiry' => now()->addYears(2)->toDateString(),
            'tacho_card_expiry' => now()->addYears(2)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
        ]);
        $vehicle = Vehicle::create([
            'registration' => '1-ABC-123',
            'vin' => 'VF1'.str_pad('1', 14, '0'),
            'vehicle_type' => 'Porteur',
            'brand' => 'Volvo',
            'model' => 'FH',
            'capacity_tonnes' => 12,
            'is_available' => true,
            'inspection_date' => now()->subMonths(3)->toDateString(),
            'inspection_valid_until' => now()->addMonths(9)->toDateString(),
        ]);

        return ['vehicle_registration' => $vehicle->registration, 'driver_id' => $driver->id];
    }

    public function test_le_client_qui_a_commande_apprend_l_affectation(): void
    {
        Mail::fake();

        $client = Client::factory()->create();
        $commanditaire = User::factory()->create(['client_id' => $client->id, 'company_role' => 'ORDERS']);
        $ordre = TransportOrder::factory()->create(['client_id' => $client->id, 'weight' => 1000, 'pickup_date' => now()->addDay()]);
        ActivityLog::record('order.created', 'Création', $ordre, [], $commanditaire->id);

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), $this->binome())
            ->assertSessionHasNoErrors();

        $this->assertSame('ASSIGNED', $ordre->fresh()->status);
        Mail::assertSent(OrdreAffecte::class, fn (OrdreAffecte $m) => $m->hasTo($commanditaire->email) && $m->ordre->is($ordre));
        Mail::assertSent(OrdreAffecte::class, 1);
    }

    public function test_sans_commanditaire_connu_les_comptes_qui_commandent_sont_prevenus(): void
    {
        Mail::fake();

        $client = Client::factory()->create();
        $comptable = User::factory()->create(['client_id' => $client->id, 'company_role' => 'BILLING']);
        $ordre = TransportOrder::factory()->create(['client_id' => $client->id, 'weight' => 1000, 'pickup_date' => now()->addDay()]);

        $this->actingAs(User::factory()->planificateur()->create())
            ->post(route('planning.assign', $ordre), $this->binome())
            ->assertSessionHasNoErrors();

        Mail::assertSent(OrdreAffecte::class, fn (OrdreAffecte $m) => $m->hasTo($client->compte()->email));
        Mail::assertNotSent(OrdreAffecte::class, fn (OrdreAffecte $m) => $m->hasTo($comptable->email));
    }

    public function test_le_courriel_se_construit_avec_la_date_d_enlevement(): void
    {
        $ordre = TransportOrder::factory()->create(['pickup_date' => now()->addDay()->setTime(8, 30), 'vehicle_registration' => null]);
        $html = (new OrdreAffecte($ordre, $ordre->client->compte()))->render();

        $this->assertStringContainsString($ordre->tracking_number, $html);
        $this->assertStringContainsString('Enlèvement prévu', $html);
    }
}
