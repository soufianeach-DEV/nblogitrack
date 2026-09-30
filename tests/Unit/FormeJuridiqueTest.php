<?php

namespace Tests\Unit;

use App\Support\FormeJuridique;
use PHPUnit\Framework\TestCase;

class FormeJuridiqueTest extends TestCase
{
    public function test_la_forme_se_lit_en_fin_ou_en_debut_de_nom(): void
    {
        foreach ([
            'Spotify AB' => 'AB', 'Škoda Auto a.s.' => 'a.s.', 'EDP, S.A.' => 'SA', 'ORLEN SPÓŁKA AKCYJNA' => 'S.A.',
            'Nokia Oyj' => 'Oyj', 'Red Bull GmbH' => 'GmbH', 'GOOGLE IRELAND LIMITED' => 'Ltd', 'ENI SPA' => 'S.p.A.',
            'Novo Nordisk A/S' => 'A/S', 'Mueller GmbH & Co. KG' => 'GmbH & Co. KG', 'Firma Sp. z o.o.' => 'Sp. z o.o.',
            'MOL Nyrt.' => 'Nyrt.', 'SIA PIEMĒRS' => 'SIA', 'UAB Pavyzdys' => 'UAB',
            // La partie cyrillique entre parentheses est ignoree.
            'Vivakom Balgaria - EAD (Виваком България - ЕАД)' => 'EAD',
        ] as $nom => $forme) {
            $this->assertSame($forme, FormeJuridique::depuisNom($nom), $nom);
        }
    }

    public function test_un_nom_sans_forme_ne_devine_rien(): void
    {
        foreach (['Proximus', 'Transports Dupont', 'MEGAS', 'Pieces Auto', '', null] as $nom) {
            $this->assertNull(FormeJuridique::depuisNom($nom), (string) $nom);
        }
    }
}
