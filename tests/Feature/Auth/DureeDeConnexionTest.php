<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Le temps de reponse d'une connexion refusee ne dit pas si l'adresse a
 * un compte. En production, un hachage bcrypt dure plus longtemps que la
 * temporisation de Laravel (200 ms) : le test le simule en ralentissant
 * chaque verification de mot de passe.
 */
class DureeDeConnexionTest extends TestCase
{
    use RefreshDatabase;

    private const HACHAGE_MS = 300;

    public function test_une_adresse_inconnue_repond_dans_le_meme_temps_qu_un_mot_de_passe_faux(): void
    {
        $connue = User::factory()->create()->email;
        $this->hachageLent();

        // Le leurre se calcule une fois pour toutes : hors mesure.
        $this->post(route('login'), ['email' => 'premier@inconnu.be', 'password' => 'mot-de-passe-faux']);

        $inconnue = $this->duree('personne@inconnu.be');
        $fausse = $this->duree($connue);

        $this->assertGreaterThanOrEqual(self::HACHAGE_MS, $fausse);
        $this->assertLessThan(100, abs($inconnue - $fausse), "Adresse inconnue : {$inconnue} ms ; mot de passe faux : {$fausse} ms.");
    }

    /** La plus courte de trois tentatives refusees, en millisecondes. */
    private function duree(string $email): float
    {
        $durees = [];

        foreach (range(1, 3) as $essai) {
            $debut = hrtime(true);
            $this->post(route('login'), ['email' => $email, 'password' => 'mot-de-passe-faux'])
                ->assertSessionHasErrors('email');
            $durees[] = (hrtime(true) - $debut) / 1e6;
        }

        return min($durees);
    }

    private function hachageLent(): void
    {
        Hash::swap(new class(Hash::getFacadeRoot(), self::HACHAGE_MS) implements Hasher
        {
            public function __construct(private $hacheur, private int $ms) {}

            public function info($hashedValue)
            {
                return $this->hacheur->info($hashedValue);
            }

            public function make($value, array $options = [])
            {
                return $this->hacheur->make($value, $options);
            }

            public function check($value, $hashedValue, array $options = [])
            {
                usleep($this->ms * 1000);

                return $this->hacheur->check($value, $hashedValue, $options);
            }

            public function needsRehash($hashedValue, array $options = [])
            {
                return $this->hacheur->needsRehash($hashedValue, $options);
            }

            public function __call($methode, $arguments)
            {
                return $this->hacheur->{$methode}(...$arguments);
            }
        });
    }
}
