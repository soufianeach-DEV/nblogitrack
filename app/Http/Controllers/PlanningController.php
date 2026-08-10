<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanningController extends Controller
{
    private const TRANSITIONS = [
        'PENDING' => ['IN_PROGRESS', 'CANCELLED'],
        'IN_PROGRESS' => ['DELIVERED', 'CANCELLED'],
        'DELIVERED' => [],
        'CANCELLED' => [],
    ];

    public function index(Request $request): Response
    {
        $statut = $request->query('status', 'PENDING');
        if (! array_key_exists($statut, self::TRANSITIONS)) {
            $statut = 'PENDING';
        }

        $orders = TransportOrder::with([
            'client:id,company_name',
            'vehicle:registration,brand,model,capacity_tonnes',
            'driver.user:id,first_name,last_name',
        ])
            ->where('status', $statut)
            ->orderByRaw("CASE priority WHEN 'URGENT' THEN 1 WHEN 'HIGH' THEN 2 WHEN 'NORMAL' THEN 3 ELSE 4 END")
            ->orderBy('pickup_date')
            ->paginate(15)
            ->withQueryString();

        $vehicles = Vehicle::where('is_available', true)
            ->orderBy('registration')
            ->get(['registration', 'brand', 'model', 'vehicle_type', 'capacity_tonnes', 'capacity_volume']);

        $drivers = Driver::with('user:id,first_name,last_name')
            ->where('is_available', true)
            ->get(['id', 'license_type', 'adr_certified'])
            ->map(fn ($d) => [
                'id' => $d->id,
                'nom' => $d->user ? $d->user->first_name.' '.$d->user->last_name : 'Chauffeur '.$d->id,
                'license_type' => $d->license_type,
                'adr_certified' => $d->adr_certified,
            ])
            ->sortBy('nom')
            ->values();

        return Inertia::render('Planning/Index', [
            'orders' => $orders,
            'vehicles' => $vehicles,
            'drivers' => $drivers,
            'statut' => $statut,
            'compteurs' => TransportOrder::selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function assign(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_registration' => 'required|exists:vehicles,registration',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        if ($transportOrder->status !== 'PENDING') {
            return back()->withErrors(['vehicle_registration' => 'Seul un ordre en attente peut être affecté.']);
        }

        $vehicle = Vehicle::find($data['vehicle_registration']);
        $driver = Driver::find($data['driver_id']);

        if (! $vehicle->is_available) {
            return back()->withErrors(['vehicle_registration' => 'Ce véhicule n\'est plus disponible.']);
        }

        if (! $driver->is_available) {
            return back()->withErrors(['driver_id' => 'Ce chauffeur n\'est plus disponible.']);
        }

        if ($vehicle->capacity_tonnes * 1000 < $transportOrder->weight) {
            return back()->withErrors([
                'vehicle_registration' => 'Capacité insuffisante : '.$vehicle->capacity_tonnes.' t pour '.$transportOrder->weight.' kg.',
            ]);
        }

        if ($transportOrder->is_hazardous && ! $driver->adr_certified) {
            return back()->withErrors(['driver_id' => 'Marchandise dangereuse : ce chauffeur n\'a pas la certification ADR.']);
        }

        $transportOrder->update([
            'vehicle_registration' => $vehicle->registration,
            'driver_id' => $driver->id,
            'assigned_at' => now(),
            'status' => 'IN_PROGRESS',
        ]);

        ActivityLog::record(
            'order.assigned',
            'Affectation de l\'ordre '.$transportOrder->tracking_number.' au véhicule '.$vehicle->registration,
            $transportOrder,
            [
                'vehicule' => $vehicle->registration.' '.$vehicle->brand.' '.$vehicle->model,
                'chauffeur_id' => $driver->id,
                'statut' => 'PENDING → IN_PROGRESS',
            ],
        );

        return back()->with('success', 'Ordre '.$transportOrder->tracking_number.' affecté au véhicule '.$vehicle->registration.'.');
    }

    public function updateStatus(Request $request, TransportOrder $transportOrder): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:PENDING,IN_PROGRESS,DELIVERED,CANCELLED',
        ]);

        $autorises = self::TRANSITIONS[$transportOrder->status] ?? [];

        if (! in_array($data['status'], $autorises, true)) {
            return back()->withErrors(['status' => 'Transition impossible depuis le statut '.$transportOrder->status.'.']);
        }

        $ancien = $transportOrder->status;
        $champs = ['status' => $data['status']];

        if ($data['status'] === 'DELIVERED') {
            $champs['actual_delivery_date'] = now();
        }

        $transportOrder->update($champs);

        ActivityLog::record(
            'order.status_changed',
            'Ordre '.$transportOrder->tracking_number.' : statut '.$ancien.' → '.$data['status'],
            $transportOrder,
            ['avant' => $ancien, 'apres' => $data['status']],
        );

        return back()->with('success', 'Ordre '.$transportOrder->tracking_number.' : statut mis à jour.');
    }
}
