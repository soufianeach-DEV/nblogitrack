<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * La fabrique etait restee celle du squelette : elle produisait une
 * colonne « name » que cette table n'a jamais eue. Les vingt-quatre
 * tests echouaient tous a l'insertion, avant meme d'avoir rien verifie.
 *
 * Le compte cree est un client actif, parce que c'est le role par defaut
 * de la table et le cas le plus courant. Les autres roles passent par
 * les etats nommes ci-dessous, qui disent en une methode ce qu'on est en
 * train de simuler.
 */
class UserFactory extends Factory
{
    /**
     * Le mot de passe, hache une seule fois pour toute la suite.
     * Le hachage est ce qui coute le plus cher dans un test.
     */
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

    /** Un compte dont l'adresse n'a pas ete confirmee. */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Un chauffeur. La fiche metier de la table « drivers » ne suit pas. */
    public function chauffeur(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'DRIVER']);
    }

    /** Un planificateur : il voit tout le carnet, il ne commande pas. */
    public function planificateur(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'PLANNER']);
    }

    /** Un administrateur : le seul a pouvoir creer des comptes. */
    public function administrateur(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'ADMIN']);
    }

    /** Un compte desactive, celui dont l'acces doit tomber tout de suite. */
    public function desactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
