<?php

namespace Tests\Unit;

use App\Support\Chronologie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Premier enlevement possible a l'etranger : route depuis Bruxelles,
 * pauses et repos du reglement 561/2006, quais, feries et interdictions
 * de circuler. Commande le mardi 6 octobre 2026 a 10 h : depart le
 * mercredi a 6 h.
 */
class ChronologieTest extends TestCase
{
    // Les messages et les feries belges passent par les traductions en base.
    use RefreshDatabase;

    private function premier(string $pays, float $lat, float $lng, string $commande = '2026-10-06 10:00'): string
    {
        return Chronologie::premierEnlevement($pays, $lat, $lng, Carbon::parse($commande))->format('D d/m H:i');
    }

    public function test_une_courte_approche_charge_le_jour_meme(): void
    {
        $this->assertSame('Wed 07/10 08:00', $this->premier('FR', 50.6292, 3.0573));
        $this->assertSame('Wed 07/10 09:45', $this->premier('DE', 50.9375, 6.9603));
    }

    public function test_une_longue_approche_ajoute_le_repos_de_nuit(): void
    {
        // Lyon : 9 h de conduite le mercredi, repos, 2 h 20 le jeudi.
        $this->assertSame('Thu 08/10 08:30', $this->premier('FR', 45.764, 4.8357));
    }

    public function test_une_commande_apres_16_h_part_un_jour_plus_tard(): void
    {
        $this->assertSame('Thu 08/10 08:00', $this->premier('FR', 50.6292, 3.0573, '2026-10-06 16:30'));
    }

    public function test_pas_de_depart_le_samedi(): void
    {
        $this->assertSame('Tue 13/10 08:30', $this->premier('FR', 45.764, 4.8357, '2026-10-09 10:00'));
    }

    public function test_l_interdiction_de_circuler_d_un_ferie_retarde_l_arrivee(): void
    {
        // 14 juillet 2027 : pas de poids lourd en France du 13 a 22 h au
        // 14 a 22 h.
        $this->assertSame('Thu 15/07 08:30', $this->premier('FR', 45.764, 4.8357, '2027-07-12 10:00'));
    }

    public function test_le_fret_retour_attend_le_repos_du_chauffeur(): void
    {
        // Livre a Lyon le jeudi a 16 h : Villeurbanne le vendredi a 7 h.
        $this->assertSame('Fri 09/10 07:00', Chronologie::rechargementApres(Carbon::parse('2026-10-08 16:00'), 5.6, 'FR')->format('D d/m H:i'));
    }

    public function test_repos_systematique_si_l_entreprise_l_exige(): void
    {
        config(['fret.chrono.repos_apres_arrivee' => 'toujours']);

        $this->assertSame('Thu 08/10 07:00', $this->premier('FR', 50.6292, 3.0573));
    }
}
