<?php

namespace App\Http\Controllers;

use App\Models\TransportOrder;
use Inertia\Inertia;
use Inertia\Response;

class TransportOrderController extends Controller
{
    public function index(): Response
    {
        $orders = TransportOrder::with('client')
            ->orderBy('id', 'desc')
            ->paginate(15);

        return Inertia::render('TransportOrders/Index', [
            'orders' => $orders,
        ]);
    }
}