<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Le garde-fou qui protege la base de travail.
     *
     * Chaque classe de test vide la base avant de commencer. Si la
     * connexion pointait sur « nblogitrack », une seule execution
     * effacerait trois cents expeditions, cent trente-sept factures et
     * cent quarante-neuf entreprises, sans confirmation.
     *
     * Ce controle interroge la connexion telle que le cadriciel l'a
     * resolue, et rien d'autre. Une premiere version lisait la variable
     * d'environnement : elle donnait une fausse assurance, parce que
     * phpunit.xml peut poser une valeur dans $_ENV pendant que le
     * cadriciel en resout une autre depuis l'environnement reel. La
     * seule verite est ici.
     *
     * setUpTraits() s'execute apres le demarrage de l'application et
     * avant que RefreshDatabase ne migre : c'est le dernier moment ou
     * l'on peut encore refuser sans avoir rien detruit.
     *
     * La convention est volontairement rigide : le nom doit se terminer
     * par « _test ». Aucune base de travail ne portera ce suffixe.
     */
    protected function setUpTraits(): array
    {
        $base = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($base, '_test')) {
            throw new RuntimeException(
                'Les tests refusent de demarrer sur la base « '.($base ?: '(vide)').' ». '
                .'Ils effacent tout ce qu\'ils touchent, et seule une base dont le nom se '
                .'termine par « _test » est consideree comme jetable. Verifiez DB_DATABASE, '
                .'dans phpunit.xml comme dans votre environnement.'
            );
        }

        // Toutes les routes vivent sous un prefixe de langue. Sans cette
        // valeur par defaut, chaque appel a route() reclamerait le
        // parametre et un chemin ecrit en dur repondrait une redirection.
        URL::defaults(['langue' => 'fr']);

        return parent::setUpTraits();
    }
}
