<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\User;
use App\Support\EnvoiFacture;
use App\Support\FacturePdf;
use App\Support\FactureUbl;
use App\Support\LigneFacture;
use App\Support\Pays;
use App\Support\Traductions;
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

        $estClient = $utilisateur->cannot('view-all-orders');

        $perimetre = fn () => Invoice::query()
            ->when($estClient, fn ($q) => $q->where('client_id', $utilisateur->id));

        return Inertia::render('Factures/Index', [
            'factures' => $perimetre()
                ->with('client:id,company_name')
                ->orderByDesc('issued_on')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Invoice $facture) => [
                    'id' => $facture->id,
                    'reference' => $facture->reference,
                    'client' => $facture->client?->company_name,
                    'periode' => $facture->period_start->locale(app()->getLocale())->isoFormat('MMMM YYYY'),
                    'emise_le' => $facture->issued_on->format('d/m/Y'),
                    'echeance' => $facture->due_on->format('d/m/Y'),
                    'ttc' => (float) $facture->amount_incl_tax,
                    'autoliquidation' => (bool) $facture->reverse_charge,
                    'etat' => $facture->estEnRetard() ? 'OVERDUE' : $facture->status,
                    'payee_le' => $facture->paid_on?->format('d/m/Y'),
                    'peut_payer' => $estClient && $facture->status === 'SENT',
                ]),
            'cartes' => [
                'du' => (float) $perimetre()->where('status', '!=', 'PAID')->sum('amount_incl_tax'),
                'paye' => (float) $perimetre()->where('status', 'PAID')->sum('amount_incl_tax'),
                'en_retard' => $perimetre()->where('status', '!=', 'PAID')->where('due_on', '<', now())->count(),
            ],
            'colonnePaiement' => $estClient && $perimetre()->where('status', 'SENT')->exists(),
            'peutGererAchats' => $utilisateur->can('control-payments'),
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $utilisateur = $request->user();

        $this->autoriserLecture($utilisateur, $invoice);

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
                'envoyee_le' => $invoice->sent_at?->format(Traductions::t('msg.format_date_heure', 'd/m/Y à H\hi')),
                'ht' => (float) $invoice->amount_excl_tax,
                'taux' => (float) $invoice->vat_rate,
                'tva' => (float) $invoice->vat_amount,
                'ttc' => (float) $invoice->amount_incl_tax,
                'autoliquidation' => (bool) $invoice->reverse_charge,
                'communication' => $invoice->payment_reference,
                'iban' => config('entreprise.iban'),
                'qr' => FacturePdf::qr($invoice),
                'client' => [
                    'nom' => $invoice->client->company_name,
                    'tva' => $invoice->client->vat_number,
                    'adresse' => $invoice->client->billing_address,
                    'localite' => trim($invoice->client->postal_code.' '.$invoice->client->city),
                    'pays' => Pays::localise($invoice->client->country),
                ],
                'lignes' => $invoice->lines->map(fn ($ligne) => [
                    'id' => $ligne->id,
                    'ordre_id' => $ligne->transport_order_id,
                    'numero' => $ligne->transportOrder?->tracking_number,
                    'description' => LigneFacture::libelle($ligne->description),
                    'ht' => (float) $ligne->amount_excl_tax,
                ])->all(),
            ],
            'peutMarquerPayee' => $utilisateur->can('control-payments') && $invoice->status === 'SENT',
            'peutEnvoyer' => $utilisateur->can('control-payments') && $invoice->status !== 'DRAFT',
            'peutPayerEnLigne' => $invoice->status === 'SENT'
                && ! empty(config('services.stripe.secret'))
                && $utilisateur->cannot('view-all-orders')
                && $invoice->client_id === $utilisateur->id,
        ]);
    }

    public function envoyer(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === 'DRAFT') {
            return back()->with('error', Traductions::t('msg.brouillon_non_envoyable', 'Un brouillon ne s\'envoie pas.'));
        }

        $destinataire = EnvoiFacture::envoyer($invoice);

        return $destinataire === null
            ? back()->with('error', Traductions::t('msg.courriel_echec', 'Le courriel n\'a pas pu partir. Réessayez dans quelques minutes.'))
            : back()->with('success', Traductions::t('msg.facture_envoyee', 'Facture :reference envoyée à :destinataire.', [
                'reference' => $invoice->reference,
                'destinataire' => $destinataire,
            ]));
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== 'SENT') {
            return back()->with('error', Traductions::t('msg.facture_pas_en_attente', 'Cette facture n\'est pas en attente de paiement.'));
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

        return back()->with('success', Traductions::t('msg.paiement_enregistre', 'Paiement enregistré.'));
    }

    public function pdf(Request $request, Invoice $invoice): \Illuminate\Http\Response
    {
        $this->autoriserLecture($request->user(), $invoice);

        return response(FacturePdf::contenu($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->reference.'.pdf"',
        ]);
    }

    public function ubl(Request $request, Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        $this->autoriserLecture($request->user(), $invoice);

        $invoice->load([
            'client:id,company_name,vat_number,peppol_id,billing_address,postal_code,city,country',
            'lines',
        ]);

        return response(FactureUbl::pour($invoice), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$invoice->reference.'.xml"',
        ]);
    }

    private function autoriserLecture(User $utilisateur, Invoice $invoice): void
    {
        abort_if($utilisateur->isDriver(), 403);
        abort_if(
            $utilisateur->cannot('view-all-orders') && $invoice->client_id !== $utilisateur->id,
            404,
        );
    }
}
