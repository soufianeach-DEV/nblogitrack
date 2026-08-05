<?php

namespace App\Http\Controllers;

use App\Models\TransportOrder;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

class TransportOrderController extends Controller
{
    public function index(Request $request): Response
{
    $query = TransportOrder::with('client:id,company_name')->orderBy('id', 'desc');

    if ($request->user()->cannot('view-all-orders')) {
        $query->where('client_id', $request->user()->id);
    }

    return Inertia::render('TransportOrders/Index', [
        'orders' => $query->paginate(15),
    ]);
}
}