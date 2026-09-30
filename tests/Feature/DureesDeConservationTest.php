<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les durees annoncees au registre sont appliquees par les purges. */
class DureesDeConservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_inscription_refusee_s_efface_apres_six_mois(): void
    {
        $ancienne = Client::factory()->enAttente()->create(['rejection_reason' => 'Refus test', 'validated_at' => now()->subMonths(7)]);
        $recente = Client::factory()->enAttente()->create(['rejection_reason' => 'Refus test', 'validated_at' => now()->subMonth()]);

        $this->artisan('journaux:purger')->assertSuccessful();

        $this->assertNull(Client::find($ancienne->id));
        $this->assertSame(0, User::where('client_id', $ancienne->id)->count());
        $this->assertNotNull(Client::find($recente->id));
    }

    public function test_les_positions_d_une_mission_restee_en_route_s_effacent(): void
    {
        $ordre = TransportOrder::factory()->create(['status' => 'IN_PROGRESS']);
        ShipmentPosition::create([
            'transport_order_id' => $ordre->id, 'type' => ShipmentPosition::ROUTE,
            'lat' => 50.85, 'lng' => 4.35, 'recorded_at' => now()->subDays(40),
        ]);
        TransportOrder::whereKey($ordre->id)->update(['updated_at' => now()->subDays(40)]);

        $this->artisan('positions:purger --jours=7')->assertSuccessful();

        $this->assertSame(0, ShipmentPosition::where('transport_order_id', $ordre->id)->count());
    }
}
