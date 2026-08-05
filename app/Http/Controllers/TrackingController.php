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

        $order = $searched
            ? TransportOrder::with('client:id,company_name')
                ->where('tracking_number', $request->query('tracking_number'))
                ->where('tracking_code', strtoupper($request->query('code')))
                ->first()
            : null;

        return Inertia::render('Tracking/Show', [
            'searched' => $searched,
            'order' => $order,
        ]);
    }
}