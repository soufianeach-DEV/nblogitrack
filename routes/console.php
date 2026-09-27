<?php

use App\Models\ActivityLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Une tache qui echoue la nuit se voit au journal d'activite, et une
// tache encore en cours n'est pas relancee par-dessus.
$signaler = fn (string $tache) => fn () => ActivityLog::record(
    'schedule.failed',
    'Échec de la tâche planifiée '.$tache,
);

Schedule::command('positions:purger --jours=7')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onFailure($signaler('positions:purger'))
    ->onOneServer();

Schedule::command('journaux:purger --mois=12')
    ->weeklyOn(1, '03:45')
    ->withoutOverlapping()
    ->onFailure($signaler('journaux:purger'))
    ->onOneServer();

Schedule::command('factures:generer')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping(120)
    ->onFailure($signaler('factures:generer'))
    ->onOneServer();

Schedule::command('factures:envoyer-brouillons')
    ->dailyAt('04:15')
    ->withoutOverlapping()
    ->onFailure($signaler('factures:envoyer-brouillons'))
    ->onOneServer();

Schedule::command('chauffeurs:cloturer-departs')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->onFailure($signaler('chauffeurs:cloturer-departs'))
    ->onOneServer();

// Entretien : taches echouees de plus d'une semaine, liens de mot de
// passe expires, entrees de cache perimees (le cache en base ne les
// efface pas de lui-meme).
Schedule::command('queue:prune-failed --hours=168')->daily()->onOneServer();
Schedule::command('auth:clear-resets')->daily()->onOneServer();
Schedule::call(fn () => DB::table('cache')->where('expiration', '<', time())->delete())
    ->name('cache:purger-perimes')
    ->dailyAt('04:30')
    ->onOneServer();

// File d'attente (note aux conducteurs...) : videe chaque minute par la
// meme tache cron que le reste, sans service a installer. Un worker
// permanent (queue:work sous Supervisor) peut la remplacer.
Schedule::command('queue:work --stop-when-empty --tries=1 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
