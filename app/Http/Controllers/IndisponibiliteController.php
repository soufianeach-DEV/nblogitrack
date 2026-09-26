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

        ActivityLog::record('unavailability.removed', 'Indisponibilité supprimée : '.$indisponibilite->resume(), $indisponibilite->driver ?? $indisponibilite->vehicle);

        return back()->with('success', Traductions::t('msg.indispo_supprimee', 'Indisponibilité supprimée.'));
    }

    /**
     * @param  list<string>  $motifs
     * @return array<string, mixed>
     */
    private function valider(Request $request, array $motifs): array
    {
        return $request->validate([
            'du' => 'required|date|after_or_equal:today',
            'au' => 'required|date|after_or_equal:du',
            'motif' => 'required|in:'.implode(',', $motifs),
            'commentaire' => 'nullable|string|max:200',
        ], [
            'du.after_or_equal' => Traductions::t('msg.indispo_passee', 'Une indisponibilité ne commence pas dans le passé.'),
            'au.after_or_equal' => Traductions::t('msg.indispo_fin_avant_debut', 'La fin ne peut pas précéder le début.'),
        ]);
    }

    /**
     * Une absence posee sur une mission deja affectee la rend non
     * conforme : le planificateur le lit tout de suite.
     */
    private function enregistre(Indisponibilite $periode, $missions): RedirectResponse
    {
        ActivityLog::record('unavailability.created', 'Indisponibilité enregistrée : '.$periode->resume(), $periode->driver ?? $periode->vehicle);

        $reponse = back()->with('success', Traductions::t('msg.indispo_enregistree', 'Indisponibilité enregistrée : :periode.', ['periode' => $periode->resume()]));
        $aReaffecter = ControleAffectation::missionsNonConformes($missions);

        return $aReaffecter === [] ? $reponse : $reponse->with('error', Traductions::t('msg.missions_a_reaffecter', 'Attention : ces missions ne sont plus conformes et doivent être réaffectées : :missions.', [
            'missions' => implode(', ', $aReaffecter),
        ]));
    }
}
