<?php

namespace Tests\Unit;

use Database\Seeders\ExploitationSeeder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PositionsDemonstrationTest extends TestCase
{
    private function surLeTrace(array $trace, float $fraction): array
    {
        $methode = new ReflectionMethod(ExploitationSeeder::class, 'surLeTrace');

        return $methode->invoke(new ExploitationSeeder, $trace, $fraction);
    }

    public function test_la_position_suit_les_virages_du_trace(): void
    {
        // Un trace en L pres de l'equateur : deux troncons de meme longueur.
        $trace = [[0.0, 0.0], [0.0, 1.0], [1.0, 1.0]];

        [$lat, $lng] = $this->surLeTrace($trace, 0.25);
        $this->assertEqualsWithDelta(0.0, $lat, 0.01);
        $this->assertEqualsWithDelta(0.5, $lng, 0.01);

        // Aux trois quarts, le camion a tourne : il est sur le second troncon.
        [$lat, $lng] = $this->surLeTrace($trace, 0.75);
        $this->assertEqualsWithDelta(0.5, $lat, 0.01);
        $this->assertEqualsWithDelta(1.0, $lng, 0.01);
    }

    public function test_une_ligne_droite_reste_une_ligne_droite(): void
    {
        [$lat, $lng] = $this->surLeTrace([[50.0, 4.0], [51.0, 5.0]], 0.5);

        $this->assertEqualsWithDelta(50.5, $lat, 0.001);
        $this->assertEqualsWithDelta(4.5, $lng, 0.001);
    }
}
