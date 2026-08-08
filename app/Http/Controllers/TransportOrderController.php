<?php

namespace App\Http\Controllers;

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
}
