<?php

namespace App\Http\Controllers;

use App\Models\TariffGrid;
use App\Models\TransportOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TransportOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TransportOrder::with('client:id,company_name')->orderBy('id', 'desc');

        if ($request->user()->cannot('view-all-orders')) {
            $query->where('client_id', $request->user()->id);
        }

        if ($request->filled('tracking')) {
            $query->where('tracking_number', 'ilike', '%'.$request->tracking.'%');
        }
        if ($request->filled('destination')) {
            $query->where('delivery_address', 'ilike', '%'.$request->destination.'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client')) {
            $query->whereHas('client', fn ($q) => $q->where('company_name', 'ilike', '%'.$request->client.'%'));
        }

        return Inertia::render('TransportOrders/Index', [
            'orders' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['tracking', 'client', 'destination', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('TransportOrders/Create', [
            'tariffGrids' => TariffGrid::where('is_active', true)->get(['id', 'label', 'zone', 'base_rate', 'price_per_kg', 'price_per_km', 'delivery_days']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pickup_address' => 'required|string|max:255',
            'delivery_address' => 'required|string|max:255',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'weight' => 'required|numeric|min:0',
            'goods_type' => 'nullable|string|max:255',
            'priority' => 'required|in:LOW,NORMAL,HIGH,URGENT',
            'pickup_date' => 'nullable|date|after_or_equal:today',
            'requested_delivery_date' => 'nullable|date|after_or_equal:today',
            'tariff_grid_id' => 'required|exists:tariff_grids,id',
            'special_instructions' => 'nullable|string',
        ]);

        $grid = TariffGrid::find($data['tariff_grid_id']);
        $distanceKm = $this->roadDistanceKm($data['pickup_lat'], $data['pickup_lng'], $data['delivery_lat'], $data['delivery_lng']);
        $nextId = TransportOrder::max('id') + 1;

        $order = TransportOrder::create([
            'client_id' => $request->user()->id,
            'pickup_address' => $data['pickup_address'],
            'delivery_address' => $data['delivery_address'],
            'weight' => $data['weight'],
            'goods_type' => $data['goods_type'] ?? null,
            'priority' => $data['priority'],
            'pickup_date' => $data['pickup_date'] ?? null,
            'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
            'tariff_grid_id' => $data['tariff_grid_id'],
            'special_instructions' => $data['special_instructions'] ?? null,
            'status' => 'PENDING',
            'created_date' => now(),
            'distance_km' => (int) round($distanceKm),
            'estimated_cost' => round($grid->base_rate + $grid->price_per_kg * $data['weight'] + $grid->price_per_km * $distanceKm, 2),
            'tracking_code' => strtoupper(Str::random(12)),
            'tracking_number' => 'TRK-'.now()->year.'-'.str_pad($nextId, 5, '0', STR_PAD_LEFT),
        ]);

        return redirect()->route('transport-orders.index')
            ->with('success', 'Ordre créé : '.$order->tracking_number);
    }

    private function roadDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        try {
            $res = Http::timeout(5)->get("https://router.project-osrm.org/route/v1/driving/{$lng1},{$lat1};{$lng2},{$lat2}", [
                'overview' => 'false',
            ]);
            if ($res->ok() && isset($res->json()['routes'][0]['distance'])) {
                return $res->json()['routes'][0]['distance'] / 1000;
            }
        } catch (\Throwable $e) {
          
        }

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * asin(sqrt($a)) * 1.3;
    }
}