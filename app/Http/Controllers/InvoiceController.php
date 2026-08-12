<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
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

    public function show(Request $request, Invoice $invoice): Response
    {
        $utilisateur = $request->user();

        abort_if($utilisateur->isDriver(), 403);
        abort_if(
            $utilisateur->cannot('view-all-orders') && $invoice->client_id !== $utilisateur->id,
            404,
        );

        $invoice->load([
            'client:id,company_name,vat_number,billing_address,postal_code,city,country',
            'lines.transportOrder:id,tracking_number',
        ]);

        return Inertia::render('Factures/Show', [
            'facture' => [
                'id' => $invoice->id,
                'reference' => $invoice->reference,
                'etat' => $invoice->estEnRetard() ? 'OVERDUE' : $invoice->status,
                'periode_debut' => $invoice->period_start->format('d/m/Y'),
                'periode_fin' => $invoice->period_end->format('d/m/Y'),
                'emise_le' => $invoice->issued_on->format('d/m/Y'),
                'echeance' => $invoice->due_on->format('d/m/Y'),
                'payee_le' => $invoice->paid_on?->format('d/m/Y'),
                'ht' => (float) $invoice->amount_excl_tax,
                'taux' => (float) $invoice->vat_rate,
                'tva' => (float) $invoice->vat_amount,
                'ttc' => (float) $invoice->amount_incl_tax,
                'autoliquidation' => (bool) $invoice->reverse_charge,
                'communication' => $invoice->payment_reference,
                'client' => [
                    'nom' => $invoice->client->company_name,
                    'tva' => $invoice->client->vat_number,
                    'adresse' => $invoice->client->billing_address,
                    'localite' => trim($invoice->client->postal_code.' '.$invoice->client->city),
                    'pays' => $invoice->client->country,
                ],
                'lignes' => $invoice->lines->map(fn ($ligne) => [
                    'id' => $ligne->id,
                    'ordre_id' => $ligne->transport_order_id,
                    'numero' => $ligne->transportOrder?->tracking_number,
                    'description' => $ligne->description,
                    'ht' => (float) $ligne->amount_excl_tax,
                ])->all(),
            ],
            'peutMarquerPayee' => $utilisateur->can('control-payments') && $invoice->status === 'SENT',
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== 'SENT') {
            return back()->with('error', "Cette facture n'est pas en attente de paiement.");
        }

        $invoice->update([
            'status' => 'PAID',
            'paid_on' => now(),
        ]);

        ActivityLog::record(
            'invoice.paid',
            'Paiement enregistré pour '.$invoice->reference,
            $invoice,
            [
                'montant' => (string) $invoice->amount_incl_tax,
                'client_id' => $invoice->client_id,
            ],
        );

        return back()->with('success', 'Paiement enregistré.');
    }
}
