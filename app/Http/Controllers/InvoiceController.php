<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateur = $request->user();

        abort_if($utilisateur->isDriver(), 403);

        $requete = Invoice::with('client:id,company_name');

        if ($utilisateur->cannot('view-all-orders')) {
            $requete->where('client_id', $utilisateur->id);
        }

        return Inertia::render('Factures/Index', [
            'factures' => $requete
                ->orderByDesc('issued_on')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Invoice $facture) => [
                    'id' => $facture->id,
                    'reference' => $facture->reference,
                    'client' => $facture->client?->company_name,
                    'periode' => $facture->period_start->locale('fr')->isoFormat('MMMM YYYY'),
                    'emise_le' => $facture->issued_on->format('d/m/Y'),
                    'echeance' => $facture->due_on->format('d/m/Y'),
                    'ttc' => (float) $facture->amount_incl_tax,
                    'autoliquidation' => (bool) $facture->reverse_charge,
                    'etat' => $facture->estEnRetard() ? 'OVERDUE' : $facture->status,
                    'payee_le' => $facture->paid_on?->format('d/m/Y'),
                ])
                ->all(),
        ]);
    }
}
