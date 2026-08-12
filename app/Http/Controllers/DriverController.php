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
            'etat' => 'nullable|in:disponibles,indisponibles,adr,visite,permis',
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

        match ($filtres['etat'] ?? null) {
            'disponibles' => $requete->where('is_available', true),
            'indisponibles' => $requete->where('is_available', false),
            'adr' => $requete->where('adr_certified', true),
            'visite' => $requete->where('medical_exam_date', '<', $visiteLimite),
            'permis' => $requete->where('license_expiry', '<=', $permisLimite),
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
                'heures' => (float) $d->daily_driving_hours,
                'disponible' => (bool) $d->is_available,
                'missions' => (int) ($missions[$d->id] ?? 0),
                'engage' => $enCours->has($d->id),
            ])->sortBy('nom')->values()->all(),
            'permis' => Driver::distinct()->orderBy('license_type')->pluck('license_type'),
            'compteurs' => [
                'total' => Driver::count(),
                'disponibles' => Driver::where('is_available', true)->count(),
                'adr' => Driver::where('adr_certified', true)->count(),
                'visite' => Driver::where('medical_exam_date', '<', $visiteLimite)->count(),
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
        ]);

        // Un chauffeur engage sur une expedition ne se retire pas du service,
        // et retirer sa certification ADR alors qu'il transporte une matiere
        // dangereuse laisserait une affectation que l'application refuse.
        if ($donnees['is_available'] === false || $donnees['adr_certified'] === false) {
            $encours = TransportOrder::whereIn('status', ['PENDING', 'IN_PROGRESS'])
                ->where('driver_id', $driver->id);

            if ($donnees['is_available'] === false && (clone $encours)->exists()) {
                return back()->with('error',
                    'Ce chauffeur porte une mission en cours : réaffectez-la avant de le retirer du service.');
            }

            if ($donnees['adr_certified'] === false && (clone $encours)->where('is_hazardous', true)->exists()) {
                return back()->with('error',
                    'Ce chauffeur transporte une matière dangereuse : sa certification ADR ne peut pas être retirée maintenant.');
            }
        }

        $driver->update($donnees);

        ActivityLog::record(
            'driver.updated',
            'Chauffeur '.($driver->user?->first_name.' '.$driver->user?->last_name).' mis à jour',
            $driver,
            $donnees,
        );

        return back()->with('success', 'Chauffeur mis à jour.');
    }
}
