<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Un depart de chauffeur enregistre a l'avance : il ne compte comme
 * « sorti » qu'a sa date, et l'annuler ne rouvre pas un compte ferme pour
 * une autre raison.
 */
class DepartsChauffeursTest extends TestCase
{
    use RefreshDatabase;

    private function chauffeur(array $champs = []): Driver
    {
        $utilisateur = User::factory()->chauffeur()->create();

        return Driver::create([
            'user_id' => $utilisateur->id,
            'license_number' => 'PERMIS-'.$utilisateur->id,
            'license_type' => 'CE',
            'license_expiry' => now()->addYears(3)->toDateString(),
            'medical_exam_date' => now()->subMonths(2)->toDateString(),
            'is_available' => true,
            ...$champs,
        ]);
    }

    /** @return array<string, mixed> */
    private function fiche(array $champs = []): array
    {
        return [
            'is_available' => true,
            'adr_certified' => false,
            'employment_status' => array_key_first(Driver::STATUTS),
            ...$champs,
        ];
    }

    public function test_un_depart_futur_n_est_pas_encore_une_sortie(): void
    {
        $this->chauffeur(['left_on' => now()->addMonth()->toDateString(), 'departure_reason' => 'RETRAITE']);
        $this->chauffeur(['left_on' => now()->subMonth()->toDateString(), 'departure_reason' => 'RETRAITE']);

        $this->actingAs(User::factory()->administrateur()->create())
            ->get(route('drivers.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('compteurs.sortis', 1)
                ->where('chauffeurs', fn ($liste) => collect($liste)->where('depart_futur', true)->count() === 1)
                ->etc());
    }

    public function test_annuler_un_depart_futur_ne_rouvre_pas_un_compte_ferme_a_part(): void
    {
        $chauffeur = $this->chauffeur(['left_on' => now()->addMonth()->toDateString(), 'departure_reason' => 'RETRAITE']);
        $chauffeur->user->update(['is_active' => false]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('drivers.update', $chauffeur), $this->fiche(['left_on' => null, 'departure_reason' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull($chauffeur->fresh()->left_on);
        $this->assertFalse($chauffeur->user()->first()->is_active);
    }

    public function test_annuler_un_depart_passe_rend_l_acces(): void
    {
        $chauffeur = $this->chauffeur(['left_on' => now()->subDay()->toDateString(), 'departure_reason' => 'RETRAITE', 'is_available' => false]);
        $chauffeur->user->update(['is_active' => false]);

        $this->actingAs(User::factory()->administrateur()->create())
            ->patch(route('drivers.update', $chauffeur), $this->fiche(['left_on' => null, 'departure_reason' => null]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($chauffeur->user()->first()->is_active);
        $this->assertNull($chauffeur->fresh()->departure_reason);
    }
}
