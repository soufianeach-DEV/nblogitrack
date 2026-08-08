<?php

namespace App\Http\Controllers;

use App\Models\TariffGrid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use App\Models\TransportOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransportOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TransportOrder::with('client:id,company_name')->orderBy('id', 'desc');

        // Filtre par rôle
        if ($request->user()->cannot('view-all-orders')) {
            $query->where('client_id', $request->user()->id);
        }

        // Recherche par colonne (ilike = insensible à la casse en PostgreSQL)
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
            'tariffGrids' => TariffGrid::where('is_active', true)->get(['id', 'label', 'zone', 'base_rate', 'price_per_kg']),        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pickup_address' => 'required|string|max:255',
            'delivery_address' => 'required|string|max:255',
            'weight' => 'required|numeric|min:0',
            'goods_type' => 'nullable|string|max:255',
            'priority' => 'required|in:LOW,NORMAL,HIGH,URGENT',
            'requested_delivery_date' => 'nullable|date|after_or_equal:today',
            'tariff_grid_id' => 'required|exists:tariff_grids,id',
            'special_instructions' => 'nullable|string',
            'pickup_date' => 'nullable|date|after_or_equal:today',
        ]);

        $grid = TariffGrid::find($data['tariff_grid_id']);
        $nextId = TransportOrder::max('id') + 1;

        $order = TransportOrder::create(array_merge($data, [
            'client_id' => $request->user()->id,
            'status' => 'PENDING',
            'created_date' => now(),
            'estimated_cost' => round($grid->base_rate + $grid->price_per_kg * $data['weight'], 2),
            'tracking_code' => strtoupper(Str::random(12)),
            'tracking_number' => 'TRK-'.now()->year.'-'.str_pad($nextId, 5, '0', STR_PAD_LEFT),
        ]));

        return redirect()->route('transport-orders.index')
            ->with('success', 'Ordre créé : '.$order->tracking_number);
    }
}
