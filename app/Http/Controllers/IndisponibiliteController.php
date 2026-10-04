<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Indisponibilite;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use App\Support\ControleAffectation;
use App\Support\Traductions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Conges, formations, passages au garage : dates a l'avance, ils
 * ecartent le chauffeur ou le camion des missions qui les croisent.
 */
class IndisponibiliteController extends Controller
{
    public function chauffeur(Request $request, Driver $driver): RedirectResponse
    {
        $donnees = $this->valider($request, array_keys(Indisponibilite::MOTIFS_CHAUFFEUR));
        $absence = $driver->indisponibilites()->create($donnees + ['created_by' => $request->user()->id]);

        return $this->enregistre($absence, TransportOrder::where('driver_id', $driver->id));
    }

    public function vehicule(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $donnees = $this->valider($request, array_keys(Indisponibilite::MOTIFS_VEHICULE));
        $immobilisation = $vehicle->indisponibilites()->create($donnees + ['created_by' => $request->user()->id]);

        return $this->enregistre($immobilisation, TransportOrder::where('vehicle_registration', $vehicle->registration));
    }

    public function destroy(Indisponibilite $indisponibilite): RedirectResponse
    {
        $indisponibilite->delete();

        ActivityLog::record('unavailability.removed', 'Indisponibilité supprimée ('.self::concerne($indisponibilite).') : '.self::periodeAuJournal($indisponibilite), $indisponibilite->driver ?? $indisponibilite->vehicle, ['concerne' => self::concerne($indisponibilite)]);

        return back()->with('success', Traductions::t('msg.indispo_supprimee', 'Indisponibilité supprimée.'));
    }

    /**
     * La periode telle que le journal la garde. Pour un chauffeur, sans
     * son motif : « maladie » est une donnee de sante, et la ligne du
     * journal survit a la periode comme a l'effacement de la fiche
     * (chauffeurs:cloturer-departs). Le motif d'un camion reste.
     */
    private static function periodeAuJournal(Indisponibilite $periode): string
    {
        return $periode->driver_id !== null
            ? 'du '.$periode->du->format('d/m/Y').' au '.$periode->au->format('d/m/Y')
            : $periode->resume();
    }

    /** Le chauffeur (par son nom) ou le camion (par sa plaque) concerne. */
    private static function concerne(Indisponibilite $periode): string
    {
        if ($periode->driver) {
            $nom = trim(($periode->driver->user?->first_name ?? '').' '.($periode->driver->user?->last_name ?? ''));

            return 'chauffeur '.($nom !== '' ? $nom : '#'.$periode->driver->id);
        }

        return 'véhicule '.($periode->vehicle?->registration ?? '—');
    }

    /**
     * @param  list<string>  $motifs
     * @return array<string, mixed>
     */
    private function valider(Request $request, array $motifs): array
    {
        return $request->validate([
            'du' => 'required|date|after_or_equal:today',
            // Borne haute : une fin tapee « 9999 » immobilisait le camion
            // pour toujours.
            'au' => 'required|date|after_or_equal:du|before_or_equal:'.today()->addYears(2)->toDateString(),
            'motif' => 'required|in:'.implode(',', $motifs),
            'commentaire' => 'nullable|string|max:200',
        ], [
            'du.after_or_equal' => Traductions::t('msg.indispo_passee', 'Une indisponibilité ne commence pas dans le passé.'),
            'au.after_or_equal' => Traductions::t('msg.indispo_fin_avant_debut', 'La fin ne peut pas précéder le début.'),
            'au.before_or_equal' => Traductions::t('msg.indispo_trop_longue', 'Une indisponibilité se termine au plus tard dans deux ans.'),
        ]);
    }

    /**
     * Une absence posee sur une mission deja affectee la rend non
     * conforme : le planificateur le lit tout de suite.
     */
    private function enregistre(Indisponibilite $periode, $missions): RedirectResponse
    {
        ActivityLog::record('unavailability.created', 'Indisponibilité enregistrée ('.self::concerne($periode).') : '.self::periodeAuJournal($periode), $periode->driver ?? $periode->vehicle, ['concerne' => self::concerne($periode)]);

        $reponse = back()->with('success', Traductions::t('msg.indispo_enregistree', 'Indisponibilité enregistrée : :periode.', ['periode' => $periode->resume()]));
        $aReaffecter = ControleAffectation::missionsNonConformes($missions);

        return $aReaffecter === [] ? $reponse : $reponse->with('error', Traductions::t('msg.missions_a_reaffecter', 'Attention : ces missions ne sont plus conformes et doivent être réaffectées : :missions.', [
            'missions' => implode(', ', $aReaffecter),
        ]));
    }
}
