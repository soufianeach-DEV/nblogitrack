<?php

namespace App\Console\Commands;

use App\Models\Driver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Un depart enregistre a l'avance prend effet a sa date : le chauffeur
 * sort du service et son compte se ferme ce jour-la.
 *
 * Un an plus tard, delai de prescription des actions nees du contrat de
 * travail (loi du 3 juillet 1978, art. 15), ses donnees de gestion
 * s'effacent : permis, examens, cartes, dates, coordonnees. Son nom reste
 * attache aux dossiers de transport conserves (voir pieces:purger).
 */
class CloturerDeparts extends Command
{
    public const EFFACE = 'EFFACE-';

    protected $signature = 'chauffeurs:cloturer-departs';

    protected $description = 'Ferme les comptes des chauffeurs dont la date de depart est arrivee.';

    public function handle(): int
    {
        $partis = Driver::whereNotNull('left_on')->where('left_on', '<=', today())->get();
        $fermes = 0;

        foreach ($partis as $chauffeur) {
            $chauffeur->update(['is_available' => false]);
            $utilisateur = $chauffeur->user;

            if ($utilisateur?->is_active) {
                $utilisateur->forceFill(['is_active' => false, 'remember_token' => null])->save();
                DB::table(config('session.table', 'sessions'))->where('user_id', $utilisateur->id)->delete();
                $fermes++;
            }
        }

        $effaces = 0;

        Driver::whereNotNull('left_on')
            ->where('left_on', '<=', today()->subYear())
            ->where('license_number', 'not like', self::EFFACE.'%')
            ->each(function (Driver $chauffeur) use (&$effaces) {
                $chauffeur->forceFill([
                    'license_number' => self::EFFACE.$chauffeur->id,
                    'medical_exam_date' => null,
                    'birth_date' => null,
                    'hired_on' => null,
                    'retirement_planned_on' => null,
                    'cpc_expiry' => null,
                    'tacho_card_expiry' => null,
                    'adr_expiry' => null,
                    'departure_reason' => null,
                ])->save();

                $chauffeur->user?->forceFill([
                    'email' => 'ancien-chauffeur-'.$chauffeur->user->id.'@anonyme.invalid',
                    'phone' => null,
                    'password' => Hash::make(Str::random(40)),
                    'remember_token' => null,
                ])->save();

                $effaces++;
            });

        $this->line(sprintf('  %d compte(s) ferme(s), %d fiche(s) de chauffeur parti depuis un an effacée(s).', $fermes, $effaces));

        return self::SUCCESS;
    }
}
