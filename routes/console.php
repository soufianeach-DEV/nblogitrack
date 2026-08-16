<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * La duree de conservation d'une position de route ne peut pas dependre
 * de la bonne volonte d'un administrateur : une purge qu'il faut penser
 * a lancer n'est pas une duree de conservation, c'est une intention.
 *
 * Elle tourne donc chaque nuit, quand personne ne roule.
 */
Schedule::command('positions:purger --jours=7')
    ->dailyAt('03:30')
    ->onOneServer();
