<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportOrder>
 */
class TransportOrderFactory extends Factory
{
    protected $model = TransportOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $poids = fake()->numberBetween(500, 20000);
        $km = fake()->numberBetween(20, 900);

        return [
            'client_id' => Client::factory(),
            'tariff_grid_id' => TariffGrid::factory(),
            'created_date' => now()->subDays(fake()->numberBetween(1, 60)),
            'pickup_address' => 'Rue Neuve 43, 3500 Hasselt',
            'delivery_address' => 'Avenue Louise 200, 1000 Bruxelles',
            'pickup_lat' => 50.9307,
            'pickup_lng' => 5.3378,
            'delivery_lat' => 50.8467,
            'delivery_lng' => 4.3676,
            'weight' => $poids,
            'volume' => round($poids / 400, 2),
            'goods_type' => fake()->randomElement(TransportOrder::MARCHANDISES),
            'is_hazardous' => false,
            'needs_tail_lift' => false,
            'status' => 'PENDING',
            'priority' => 'NORMAL',
            'tracking_number' => 'TRK-'.now()->year.'-'.fake()->unique()->numerify('#####'),
            'tracking_code' => strtoupper(fake()->unique()->bothify('????????????')),
            'distance_km' => $km,
            'estimated_cost' => round(75 + $km * 0.85 + $poids * 0.02, 2),
            'requested_delivery_date' => now()->addDays(fake()->numberBetween(3, 10))->toDateString(),
        ];
    }

    public function enRoute(): static
    {
        return $this->state(fn (array $a) => [
            'status' => 'IN_PROGRESS',
            'assigned_at' => now()->subDay(),
        ]);
    }

    public function livree(): static
    {
        return $this->state(fn (array $a) => [
            'status' => 'DELIVERED',
            'assigned_at' => now()->subDays(3),
            'actual_delivery_date' => now()->subDay()->toDateString(),
        ]);
    }

    public function dangereuse(): static
    {
        return $this->state(fn (array $a) => ['is_hazardous' => true]);
    }

    public function suivie(): static
    {
        return $this->state(fn (array $a) => ['suivi_direct' => true]);
    }
}
