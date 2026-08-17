<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->numerify('+32 4## ## ## ##'),
            'role' => 'CLIENT',
            'is_active' => true,
            'locale' => 'fr',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function chauffeur(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'DRIVER']);
    }

    public function planificateur(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'PLANNER']);
    }

    public function administrateur(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'ADMIN']);
    }

    public function desactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
