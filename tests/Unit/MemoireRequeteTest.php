<?php

namespace Tests\Unit;

use App\Support\MemoireRequete;
use Tests\TestCase;

class MemoireRequeteTest extends TestCase
{
    /** Inactive, elle recalcule toujours : une ecriture relit la base. */
    public function test_inactive_elle_ne_garde_rien(): void
    {
        $appels = 0;
        MemoireRequete::retenir('a', function () use (&$appels) {
            return ++$appels;
        });
        MemoireRequete::retenir('a', function () use (&$appels) {
            return ++$appels;
        });

        $this->assertSame(2, $appels);
    }

    /** Active, un meme calcul ne se fait qu'une fois, et rien ne survit a la requete. */
    public function test_active_elle_calcule_une_fois_par_requete(): void
    {
        MemoireRequete::activer();
        $appels = 0;
        MemoireRequete::retenir('a', function () use (&$appels) {
            return ++$appels;
        });
        $this->assertSame(1, MemoireRequete::retenir('a', function () use (&$appels) {
            return ++$appels;
        }));

        $this->app->forgetScopedInstances();
        MemoireRequete::retenir('a', function () use (&$appels) {
            return ++$appels;
        });
        $this->assertSame(2, $appels);
    }
}
