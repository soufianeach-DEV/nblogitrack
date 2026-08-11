<?php

namespace App\Http\Controllers;

use App\Models\TransportOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackingController extends Controller
{
    public function show(Request $request): Response
    {
        $searched = $request->filled('tracking_number') && $request->filled('code');

        // Page accessible sans compte : on n'expose que le strict necessaire au suivi,
        // ni le prix, ni les consignes particulieres, ni l'affectation.
        $order = $searched
            ? TransportOrder::with('client:id,company_name')
                ->where('tracking_number', $request->query('tracking_number'))
                ->where('tracking_code', strtoupper($request->query('code')))
                ->first([
                    'id', 'client_id', 'tracking_number', 'status',
                    'pickup_address', 'delivery_address', 'requested_delivery_date',
                ])
            : null;

        return Inertia::render('Tracking/Show', [
            'searched' => $searched,
            'order' => $order,
        ]);
    }
}
