<?php

namespace Database\Factories;

use App\Models\TariffGrid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TariffGrid>
 */
class TariffGridFactory extends Factory
{
    protected $model = TariffGrid::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => 'National (BE) — Standard',
            'zone' => 'BE',
            'service_level' => 'STANDARD',
            'base_rate' => 75.00,
            'price_per_km' => 0.85,
            'price_per_kg' => 0.02,
            'adr_coefficient' => 1.25,
            'delivery_days' => 3,
            'is_active' => true,
        ];
    }

    public function express(): static
    {
        return $this->state(fn (array $a) => [
            'label' => 'National (BE) — Express',
            'service_level' => 'EXPRESS',
            'delivery_days' => 1,
        ]);
    }

    public function zone(string $code, string $libelle): static
    {
        return $this->state(fn (array $a) => [
            'zone' => $code,
            'label' => 'Export '.$libelle.' — Standard',
        ]);
    }
}
