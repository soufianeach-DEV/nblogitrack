<?php

namespace App\Http\Controllers;

use App\Models\TransportOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        // Un chauffeur n'est le client de personne : ce tableau de bord ne lui
        // montrerait que des compteurs a zero. Sa page, ce sont ses missions.
        if ($request->user()->isDriver()) {
            return redirect()->route('missions.index');
        }

        $query = TransportOrder::query();

        if ($request->user()->cannot('view-all-orders')) {
            $query->where('client_id', $request->user()->id);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'PENDING')->count(),
            'in_progress' => (clone $query)->where('status', 'IN_PROGRESS')->count(),
            'delivered' => (clone $query)->where('status', 'DELIVERED')->count(),
        ];

        $recent = (clone $query)
            ->with('client:id,company_name')
            ->latest('id')
            ->take(5)
            ->get(['id', 'tracking_number', 'client_id', 'delivery_address', 'status']);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recent' => $recent,
        ]);
    }
}
