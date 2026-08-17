<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('positions:purger --jours=7')
    ->dailyAt('03:30')
    ->onOneServer();

Schedule::command('journaux:purger --mois=12')
    ->weeklyOn(1, '03:45')
    ->onOneServer();

Schedule::command('factures:generer')
    ->monthlyOn(1, '04:00')
    ->onOneServer();
