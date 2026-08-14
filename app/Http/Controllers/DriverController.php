<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function index(Request $request): Response
    {
        $filtres = $request->validate([
            'q' => 'nullable|string|max:60',
            'permis' => 'nullable|string|max:8',
            'etat' => 'nullable|in:disponibles,indisponibles,adr,visite,permis,inaptes,sortis',
        ]);

        $requete = Driver::with('user:id,first_name,last_name,email,phone,is_active');

        if (! empty($filtres['q'])) {
            $terme = '%'.$filtres['q'].'%';
            $requete->where(fn ($q) => $q
                ->where('license_number', 'ilike', $terme)
                ->orWhereHas('user', fn ($u) => $u
                    ->where('first_name', 'ilike', $terme)
                    ->orWhere('last_name', 'ilike', $terme)
                    ->orWhere('email', 'ilike', $terme)));
        }

        if (! empty($filtres['permis'])) {
            $requete->where('license_type', $filtres['permis']);
        }

        $visiteLimite = now()->subYear()->toDateString();
        $permisLimite = now()->addDays(60)->toDateString();
        $aujourdhui = now()->toDateString();

        // Meme definition que Driver::empechements(), traduite en SQL pour
        // filtrer sans charger les 110 lignes.
        $inapte = fn ($q) => $q
            ->whereNotNull('left_on')
            ->orWhere('license_expiry', '<', $aujourdhui)
            ->orWhereNull('medical_exam_date')
            ->orWhere('medical_exam_date', '<', $visiteLimite)
            ->orWhere('cpc_expiry', '<', $aujourdhui)
            ->orWhere('tacho_card_expiry', '<', $aujourdhui);

        match ($filtres['etat'] ?? null) {
            'disponibles' => $requete->where('is_available', true)->whereNot($inapte),
            'indisponibles' => $requete->where('is_available', false),
            'adr' => $requete->where('adr_certified', true),
            'visite' => $requete->where('medical_exam_date', '<', $visiteLimite),
            'permis' => $requete->where('license_expiry', '<=', $permisLimite),
            'inaptes' => $requete->whereNull('left_on')->where($inapte),
            'sortis' => $requete->whereNotNull('left_on'),
            default => null,
        };

        $missions = TransportOrder::whereNotNull('driver_id')
            ->selectRaw('driver_id, count(*) AS nombre')
            ->groupBy('driver_id')
            ->pluck('nombre', 'driver_id');

        $enCours = TransportOrder::whereIn('status', ['PENDING', 'IN_PROGRESS'])
            ->whereNotNull('driver_id')
            ->pluck('driver_id')
            ->unique()
            ->flip();

        return Inertia::render('Parc/Chauffeurs', [
            'chauffeurs' => $requete->get()->map(fn (Driver $d) => [
                'id' => $d->id,
                'nom' => trim(($d->user?->first_name ?? '').' '.($d->user?->last_name ?? '')) ?: 'Compte supprimé',
                'email' => $d->user?->email,
                'telephone' => $d->user?->phone,
                'actif' => (bool) ($d->user?->is_active ?? false),
                'permis' => $d->license_type,
                // Le numero de permis est une donnee personnelle : il reste
                // dans l'application, il ne part pas vers un client.
                'numero_permis' => $d->license_number,
                'permis_echeance' => $d->license_expiry?->format('d/m/Y'),
                'permis_bientot' => $d->license_expiry !== null
                    && $d->license_expiry->lte(now()->addDays(60)),
                'adr' => (bool) $d->adr_certified,
                'visite' => $d->medical_exam_date?->format('Y-m-d'),
                'visite_affichee' => $d->medical_exam_date?->format('d/m/Y'),
                'visite_perimee' => $d->medical_exam_date !== null
                    && $d->medical_exam_date->lt(now()->subYear()),
                'code95' => $d->cpc_expiry?->format('Y-m-d'),
                'code95_affiche' => $d->cpc_expiry?->format('d/m/Y'),
                'tacho' => $d->tacho_card_expiry?->format('Y-m-d'),
                'tacho_affiche' => $d->tacho_card_expiry?->format('d/m/Y'),
                'statut' => Driver::STATUTS[$d->employment_status] ?? $d->employment_status,
                'statut_code' => $d->employment_status,
                'embauche' => $d->hired_on?->format('d/m/Y'),
                'naissance' => $d->birth_date?->format('Y-m-d'),
                'naissance_affichee' => $d->birth_date?->format('d/m/Y'),
                'age' => $d->birth_date?->age,
                'retraite_prevue' => $d->retirement_planned_on?->format('Y-m-d'),
                'retraite_affichee' => $d->retirement_planned_on?->format('d/m/Y'),
                'sorti_le' => $d->left_on?->format('d/m/Y'),
                'motif_sortie' => $d->departure_reason !== null
                    ? (Driver::MOTIFS_SORTIE[$d->departure_reason] ?? $d->departure_reason)
                    : null,
                'motif_sortie_code' => $d->departure_reason,
                'empechements' => $d->empechements(),
                'heures' => (float) $d->daily_driving_hours,
                'disponible' => (bool) $d->is_available,
                'missions' => (int) ($missions[$d->id] ?? 0),
                'engage' => $enCours->has($d->id),
            ])->sortBy('nom')->values()->all(),
            'permis' => Driver::distinct()->orderBy('license_type')->pluck('license_type'),
            'statuts' => Driver::STATUTS,
            'motifsSortie' => Driver::MOTIFS_SORTIE,
            'compteurs' => [
                'total' => Driver::count(),
                'disponibles' => Driver::where('is_available', true)->whereNot($inapte)->count(),
                'adr' => Driver::where('adr_certified', true)->count(),
                'visite' => Driver::where('medical_exam_date', '<', $visiteLimite)->count(),
                'inaptes' => Driver::whereNull('left_on')->where($inapte)->count(),
                'sortis' => Driver::whereNotNull('left_on')->count(),
            ],
            'filtres' => $filtres,
            'peutModifier' => $request->user()->can('manage-fleet'),
        ]);
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $donnees = $request->validate([
            'is_available' => 'required|boolean',
            'adr_certified' => 'required|boolean',
            'medical_exam_date' => 'nullable|date|before_or_equal:today',
            'license_expiry' => 'nullable|date',
            'cpc_expiry' => 'nullable|date',
            'tacho_card_expiry' => 'nullable|date',
            'employment_status' => 'required|in:'.implode(',', array_keys(Driver::STATUTS)),
            'hired_on' => 'nullable|date|before_or_equal:today',
            'birth_date' => 'nullable|date|before:today',
            'retirement_planned_on' => 'nullable|date',
            'left_on' => 'nullable|date',
            'departure_reason' => 'nullable|in:'.implode(',', array_keys(Driver::MOTIFS_SORTIE)),
        ], [
            'medical_exam_date.before_or_equal' => 'La visite médicale ne peut pas être postérieure à aujourd\'hui.',
            'hired_on.before_or_equal' => 'La date d\'entrée en service ne peut pas être dans le futur.',
            'birth_date.before' => 'La date de naissance doit être dans le passé.',
        ]);

        // Une sortie se motive : sans motif, l'historique ne dit pas si le
        // chauffeur est parti a la retraite ou s'il a perdu son permis.
        if (! empty($donnees['left_on']) && empty($donnees['departure_reason'])) {
            return back()->withErrors([
                'departure_reason' => 'Indiquez le motif du départ.',
            ]);
        }

        if (! empty($donnees['left_on'])) {
            $encore = TransportOrder::whereIn('status', ['PENDING', 'IN_PROGRESS'])
                ->where('driver_id', $driver->id)
                ->exists();

            if ($encore) {
                return back()->withErrors([
                    'left_on' => 'Ce chauffeur porte une mission en cours : réaffectez-la avant d\'enregistrer son départ.',
                ]);
            }

            // Le compte est ferme, la ligne reste : les missions passees
            // doivent continuer a porter un nom.
            $donnees['is_available'] = false;
            $driver->user?->update(['is_active' => false]);
        }

        // Un chauffeur engage sur une expedition ne se retire pas du service,
        // et retirer sa certification ADR alors qu'il transporte une matiere
        // dangereuse laisserait une affectation que l'application refuse.
        if ($donnees['is_available'] === false || $donnees['adr_certified'] === false) {
            $encours = TransportOrder::whereIn('status', ['PENDING', 'IN_PROGRESS'])
                ->where('driver_id', $driver->id);

            // Un refus qui part en message flash ne s'affiche pas dans la
            // fiche : il ressemble a une acceptation. Il porte donc sur le
            // champ concerne.
            if ($donnees['is_available'] === false && empty($donnees['left_on']) && (clone $encours)->exists()) {
                return back()->withErrors([
                    'is_available' => 'Ce chauffeur porte une mission en cours : réaffectez-la avant de le retirer du service.',
                ]);
            }

            if ($donnees['adr_certified'] === false && (clone $encours)->where('is_hazardous', true)->exists()) {
                return back()->withErrors([
                    'adr_certified' => 'Ce chauffeur transporte une matière dangereuse : sa certification ADR ne peut pas être retirée maintenant.',
                ]);
            }
        }

        $driver->update($donnees);

        ActivityLog::record(
            ! empty($donnees['left_on']) ? 'driver.left' : 'driver.updated',
            ! empty($donnees['left_on'])
                ? 'Départ de '.trim($driver->user?->first_name.' '.$driver->user?->last_name)
                    .' ('.(Driver::MOTIFS_SORTIE[$donnees['departure_reason']] ?? '—').')'
                : 'Chauffeur '.trim($driver->user?->first_name.' '.$driver->user?->last_name).' mis à jour',
            $driver,
            $donnees,
        );

        return back()->with('success', ! empty($donnees['left_on'])
            ? 'Départ enregistré. La fiche est conservée pour l\'historique.'
            : 'Chauffeur mis à jour.');
    }
}
