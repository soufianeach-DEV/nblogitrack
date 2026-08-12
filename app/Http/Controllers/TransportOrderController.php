<?php

namespace App\Http\Controllers;

use App\Mail\OrdreCree;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Support\Tarificateur;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

    public function show(Request $request, TransportOrder $transportOrder): Response
    {
        // Un client n'accede qu'a ses propres expeditions.
        if ($request->user()->cannot('view-all-orders') && $transportOrder->client_id !== $request->user()->id) {
            abort(403);
        }

        $transportOrder->load([
            'client:id,company_name,city,country',
            'vehicle:registration,brand,model,vehicle_type,capacity_tonnes',
            'driver.user:id,first_name,last_name',
            'tariffGrid:id,label,service_level,delivery_days', 'invoiceLine:id,invoice_id,transport_order_id',
            'invoiceLine.invoice:id,reference,status,due_on,paid_on,amount_incl_tax',
        ]);

        return Inertia::render('TransportOrders/Show', [
            'order' => $transportOrder,
            'chauffeur' => $transportOrder->driver?->user
                ? $transportOrder->driver->user->first_name.' '.$transportOrder->driver->user->last_name
                : null,
            'facture' => $transportOrder->invoiceLine?->invoice ? [
                'id' => $transportOrder->invoiceLine->invoice->id,
                'reference' => $transportOrder->invoiceLine->invoice->reference,
                'etat' => $transportOrder->invoiceLine->invoice->estEnRetard()
                    ? 'En retard'
                    : Invoice::STATUTS[$transportOrder->invoiceLine->invoice->status],
                'ttc' => (float) $transportOrder->invoiceLine->invoice->amount_incl_tax,
                'echeance' => $transportOrder->invoiceLine->invoice->due_on->format('d/m/Y'),
                'payee_le' => $transportOrder->invoiceLine->invoice->paid_on?->format('d/m/Y'),
            ] : null,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('TransportOrders/Create', [
            'tariffGrids' => TariffGrid::where('is_active', true)->get(['id', 'label', 'zone', 'base_rate', 'price_per_kg', 'price_per_km', 'adr_coefficient', 'delivery_days', 'service_level']),
            'pricing' => config('pricing'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pickup_address' => 'required|string|max:255',
            'delivery_address' => 'required|string|max:255',
            'delivery_country' => 'required|string|size:2|exists:tariff_grids,zone',
            'pickup_lat' => 'required|numeric|between:-90,90',
            'pickup_lng' => 'required|numeric|between:-180,180',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'weight' => 'required|numeric|min:0',
            'goods_type' => 'required|in:'.implode(',', TransportOrder::MARCHANDISES),
            'is_hazardous' => 'boolean',
            'priority' => 'required|in:LOW,NORMAL,HIGH,URGENT',
            'pickup_date' => 'nullable|date|after_or_equal:now',
            'requested_delivery_date' => 'nullable|date|after_or_equal:today',
            'tariff_grid_id' => 'required|exists:tariff_grids,id',
            'special_instructions' => 'nullable|string',
        ], [
            'pickup_lat.required' => 'Sélectionne une adresse de départ dans la liste de suggestions.',
            'pickup_lng.required' => 'Sélectionne une adresse de départ dans la liste de suggestions.',
            'delivery_lat.required' => 'Sélectionne une adresse de destination dans la liste de suggestions.',
            'delivery_lng.required' => 'Sélectionne une adresse de destination dans la liste de suggestions.',
            'delivery_country.required' => 'Sélectionne une adresse de destination dans la liste de suggestions.',
        ]);

        $grid = TariffGrid::find($data['tariff_grid_id']);

        if ($grid->zone !== $data['delivery_country']) {
            return back()->withErrors(['tariff_grid_id' => 'La grille tarifaire ne correspond pas au pays de destination.']);
        }

        if (! empty($data['requested_delivery_date'])) {
            $depart = ! empty($data['pickup_date']) ? Carbon::parse($data['pickup_date'])->startOfDay() : now()->startOfDay();
            $delai = $depart->diffInDays(Carbon::parse($data['requested_delivery_date'])->startOfDay(), false);

            if ($delai <= 2) {
                $data['priority'] = 'URGENT';
            }

            if ($grid->delivery_days > $delai) {
                return back()->withErrors(['tariff_grid_id' => 'La formule choisie ne permet pas de livrer à la date demandée.']);
            }
        }

        $distanceKm = Tarificateur::distanceRoutiere($data['pickup_lat'], $data['pickup_lng'], $data['delivery_lat'], $data['delivery_lng']);
        $hazardous = $request->boolean('is_hazardous');

        $nextId = TransportOrder::max('id') + 1;

        $order = TransportOrder::create([
            'client_id' => $request->user()->id,
            'pickup_address' => $data['pickup_address'],
            'delivery_address' => $data['delivery_address'],
            'pickup_lat' => $data['pickup_lat'],
            'pickup_lng' => $data['pickup_lng'],
            'delivery_lat' => $data['delivery_lat'],
            'delivery_lng' => $data['delivery_lng'],
            'weight' => $data['weight'],
            'goods_type' => $data['goods_type'],
            'is_hazardous' => $hazardous,
            'priority' => $data['priority'],
            'pickup_date' => $data['pickup_date'] ?? null,
            'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
            'tariff_grid_id' => $data['tariff_grid_id'],
            'special_instructions' => $data['special_instructions'] ?? null,
            'status' => 'PENDING',
            'created_date' => now(),
            'distance_km' => (int) round($distanceKm),
            'estimated_cost' => Tarificateur::cout($grid, $distanceKm, (float) $data['weight'], $data['delivery_country'], $hazardous),
            'tracking_code' => strtoupper(Str::random(12)),
            'tracking_number' => 'TRK-'.now()->year.'-'.str_pad($nextId, 5, '0', STR_PAD_LEFT),
        ]);

        ActivityLog::record(
            'order.created',
            'Création de l\'ordre '.$order->tracking_number,
            $order,
            [
                'trajet' => $order->pickup_address.' → '.$order->delivery_address,
                'poids_kg' => (float) $order->weight,
                'distance_km' => $order->distance_km,
                'formule' => $grid->label,
                'prix' => (float) $order->estimated_cost,
            ],
        );

        try {
            Mail::to($request->user()->email)->send(new OrdreCree($order, $request->user(), $grid));
            $confirmation = 'Ordre créé : '.$order->tracking_number.' — le code de suivi vous a été envoyé par e-mail.';
        } catch (\Throwable $e) {
            report($e);
            $confirmation = 'Ordre créé : '.$order->tracking_number.' — l\'e-mail de confirmation n\'a pas pu être envoyé.';
        }

        return redirect()->route('transport-orders.index')
            ->with('success', $confirmation);
    }
}
