<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        $filtres = $request->validate([
            'q' => 'nullable|string|max:60',
            'type' => 'nullable|string|max:40',
            'etat' => 'nullable|in:disponibles,indisponibles,controle',
        ]);

        $requete = Vehicle::query();

        if (! empty($filtres['q'])) {
            $terme = '%'.$filtres['q'].'%';
            $requete->where(fn ($q) => $q
                ->where('registration', 'ilike', $terme)
                ->orWhere('brand', 'ilike', $terme)
                ->orWhere('model', 'ilike', $terme)
                ->orWhere('vin', 'ilike', $terme));
        }

        if (! empty($filtres['type'])) {
            $requete->where('vehicle_type', $filtres['type']);
        }

        $limite = now()->subYear()->toDateString();

        match ($filtres['etat'] ?? null) {
            'disponibles' => $requete->where('is_available', true),
            'indisponibles' => $requete->where('is_available', false),
            'controle' => $requete->where('inspection_date', '<', $limite),
            default => null,
        };

        $engages = TransportOrder::whereIn('status', ['PENDING', 'IN_PROGRESS'])
            ->whereNotNull('vehicle_registration')
            ->pluck('vehicle_registration')
            ->unique()
            ->flip();

        return Inertia::render('Parc/Vehicules', [
            'vehicules' => $requete->orderBy('registration')->get()->map(fn (Vehicle $v) => [
                'immatriculation' => $v->registration,
                'marque' => trim($v->brand.' '.$v->model),
                'type' => $v->vehicle_type,
                'norme' => $v->euro_standard,
                'carburant' => $v->fuel_type,
                'capacite' => (float) $v->capacity_tonnes,
                'volume' => (float) $v->capacity_volume,
                'kilometrage' => (int) $v->mileage,
                'controle' => $v->inspection_date?->format('Y-m-d'),
                'controle_affiche' => $v->inspection_date?->format('d/m/Y'),
                'controle_depasse' => $v->inspection_date !== null
                    && $v->inspection_date->lt(now()->subYear()),
                'disponible' => (bool) $v->is_available,
                'engage' => $engages->has($v->registration),
                'vin' => $v->vin,
            ])->all(),
            'types' => Vehicle::distinct()->orderBy('vehicle_type')->pluck('vehicle_type'),
            'compteurs' => [
                'total' => Vehicle::count(),
                'disponibles' => Vehicle::where('is_available', true)->count(),
                'controle' => Vehicle::where('inspection_date', '<', $limite)->count(),
            ],
            'filtres' => $filtres,
            'peutModifier' => $request->user()->can('manage-fleet'),
        ]);
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $donnees = $request->validate([
            'is_available' => 'required|boolean',
            'inspection_date' => 'nullable|date|before_or_equal:'.now()->addYear()->toDateString(),
            'mileage' => 'nullable|numeric|min:0|max:9999999',
        ]);

        // Retirer du service un camion qui porte une expedition en cours
        // ferait perdre a la planification la trace de ce qui lui est confie.
        // La base ne peut pas l'interdire : c'est une regle metier.
        if ($donnees['is_available'] === false) {
            $engage = TransportOrder::whereIn('status', ['PENDING', 'IN_PROGRESS'])
                ->where('vehicle_registration', $vehicle->registration)
                ->exists();

            if ($engage) {
                return back()->with('error',
                    'Ce véhicule porte une expédition en cours : réaffectez-la avant de le retirer du service.');
            }
        }

        // Le kilometrage d'un camion ne diminue pas.
        if ($donnees['mileage'] !== null && (float) $donnees['mileage'] < (float) $vehicle->mileage) {
            return back()->with('error', 'Le kilométrage ne peut pas être inférieur au relevé actuel.');
        }

        $vehicle->update($donnees);

        ActivityLog::record(
            'vehicle.updated',
            'Véhicule '.$vehicle->registration.' mis à jour',
            $vehicle,
            $donnees,
        );

        return back()->with('success', 'Véhicule mis à jour.');
    }
}
