<?php

namespace Tests\Unit;

use App\Support\Translitteration;
use PHPUnit\Framework\TestCase;

class TranslitterationTest extends TestCase
{
    public function test_le_bulgare_suit_le_systeme_officiel(): void
    {
        $this->assertSame('A1 Balgaria - EAD', Translitteration::latin('А1 България - ЕАД'));
        $this->assertSame('SOFIA', Translitteration::latin('СОФИЯ'));
        $this->assertSame('Plovdiv', Translitteration::latin('Пловдив'));
        $this->assertSame('Zhar EOOD', Translitteration::latin('Жар ЕООД'));
        $this->assertSame('Shtastie', Translitteration::latin('Щастие'));
    }

    public function test_le_grec_suit_elot_743(): void
    {
        $this->assertSame('Athina', Translitteration::latin('Αθήνα'));
        $this->assertSame('ERMOU 10', Translitteration::latin('ΕΡΜΟΥ 10'));
        $this->assertSame('Evangelismos', Translitteration::latin('Ευαγγελισμός'));
    }

    public function test_la_raison_sociale_garde_l_original_entre_parentheses(): void
    {
        $this->assertSame('A1 Balgaria - EAD (А1 България - ЕАД)', Translitteration::avecOriginal('А1 България - ЕАД'));
        $this->assertSame('Proximus SA', Translitteration::avecOriginal('Proximus SA'));
    }
}
