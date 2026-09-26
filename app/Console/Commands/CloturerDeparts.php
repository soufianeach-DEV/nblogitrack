<?php

namespace App\Console\Commands;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Un depart enregistre a l'avance prend effet a sa date : le chauffeur
 * sort du service et son compte se ferme ce jour-la.
 */
class CloturerDeparts extends Command
{
    protected $signature = 'chauffeurs:cloturer-departs';

    protected $description = 'Ferme les comptes des chauffeurs dont la date de depart est arrivee.';

    public function handle(): int
    {
        $partis = Driver::whereNotNull('left_on')->where('left_on', '<=', today())->get();
        $fermes = 0;

        foreach ($partis as $chauffeur) {
            $chauffeur->update(['is_available' => false]);
            $utilisateur = User::find($chauffeur->user_id ?? $chauffeur->id);

            if ($utilisateur?->is_active) {
                $utilisateur->forceFill(['is_active' => false, 'remember_token' => null])->save();
                DB::table(config('session.table', 'sessions'))->where('user_id', $utilisateur->id)->delete();
                $fermes++;
            }
        }

        $this->line(sprintf('  %d compte(s) ferme(s).', $fermes));

        return self::SUCCESS;
    }
}
