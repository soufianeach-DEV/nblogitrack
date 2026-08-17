<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 *
 * L'heritage est par table jointe : une entreprise et son compte
 * partagent le meme identifiant. La fabrique cree donc les deux, sinon
 * la cle etrangere refuse la ligne.
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->numerify('0#########');

        return [
            'id' => User::factory(),
            'company_name' => fake()->company().' '.fake()->randomElement(['SA', 'SRL', 'BV', 'SC']),
            'vat_number' => 'BE'.$numero,
            'enterprise_number' => substr($numero, 0, 4).'.'.substr($numero, 4, 3).'.'.substr($numero, 7),
            'peppol_id' => '0208:'.$numero,
            'billing_address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Bruxelles', 'Anvers', 'Gand', 'Liège', 'Charleroi']),
            'postal_code' => fake()->randomElement(['1000', '2000', '9000', '4000', '6000']),
            'country' => 'Belgique',
            'is_validated' => true,
            'business_sector' => fake()->randomElement(['Transport', 'Construction', 'Distribution', 'Chimie']),
            'credit_limit' => fake()->numberBetween(5, 50) * 1000,
            'payment_terms' => '30 jours',
        ];
    }

    /** Une entreprise inscrite mais pas encore validee par un administrateur. */
    public function enAttente(): static
    {
        return $this->state(fn (array $attributs) => [
            'is_validated' => false,
            'validated_at' => null,
            'validated_by' => null,
        ]);
    }
}
