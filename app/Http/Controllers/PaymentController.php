<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class PaymentController extends Controller
{
    public function payer(Request $request, Invoice $invoice): BaseResponse
    {
        $this->autoriserPaiement($request, $invoice);

        if ($invoice->status !== 'SENT') {
            return back()->with('error', 'Cette facture ne peut pas être réglée en ligne.');
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        // Le montant vient de la base et jamais du navigateur, comme le
        // calcul du prix. Stripe compte en centimes.
        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => (string) $invoice->id,
            'customer_email' => $request->user()->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => (int) round((float) $invoice->amount_incl_tax * 100),
                    'product_data' => [
                        'name' => 'Facture '.$invoice->reference,
                        'description' => 'Transport du '.$invoice->period_start->format('d/m/Y')
                            .' au '.$invoice->period_end->format('d/m/Y'),
                    ],
                ],
            ]],
            'success_url' => route('payments.retour', $invoice).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('invoices.show', $invoice),
        ]);

        // Un XHR Inertia ne peut pas suivre une redirection vers un autre
        // domaine : Inertia::location fait naviguer le navigateur lui-meme.
        return Inertia::location($session->url);
    }

    /**
     * Le retour du navigateur ne prouve rien : n'importe qui peut taper
     * cette adresse. Elle informe l'utilisateur, elle ne change aucun
     * etat. Seul le webhook signe marque la facture payee.
     */
    public function retour(Request $request, Invoice $invoice): Response
    {
        $this->autoriserPaiement($request, $invoice);

        $regle = $invoice->status === 'PAID';

        if (! $regle && $request->filled('session_id')) {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->retrieve($request->query('session_id'));
            $regle = $session->payment_status === 'paid';
        }

        return Inertia::render('Factures/Paiement', [
            'reference' => $invoice->reference,
            'facture_id' => $invoice->id,
            'montant' => (float) $invoice->amount_incl_tax,
            'regle' => $regle,
            'enregistre' => $invoice->status === 'PAID',
        ]);
    }

    /**
     * La seule source qui fait foi. Stripe signe chaque notification avec
     * un secret partage : sans cette signature, n'importe qui pourrait
     * annoncer un paiement en appelant cette adresse.
     */
    public function webhook(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        if (empty($secret)) {
            return response()->json(['message' => 'Webhook non configuré.'], 500);
        }

        try {
            $evenement = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret,
            );
        } catch (SignatureVerificationException) {
            return response()->json(['message' => 'Signature invalide.'], 400);
        }

        if ($evenement->type !== 'checkout.session.completed') {
            // Les autres evenements sont acceptes sans traitement : repondre
            // une erreur ferait recommencer Stripe indefiniment.
            return response()->json(['message' => 'Ignoré.']);
        }

        $session = $evenement->data->object;
        $invoice = Invoice::find($session->client_reference_id);

        if ($invoice === null || $session->payment_status !== 'paid') {
            return response()->json(['message' => 'Sans effet.']);
        }

        // Stripe peut renvoyer la meme notification plusieurs fois : une
        // facture deja payee ne se repaie pas.
        if ($invoice->status === 'PAID') {
            return response()->json(['message' => 'Déjà enregistré.']);
        }

        $invoice->update(['status' => 'PAID', 'paid_on' => now()]);

        ActivityLog::record(
            'invoice.paid_online',
            'Paiement en ligne reçu pour '.$invoice->reference,
            $invoice,
            [
                'montant' => (string) $invoice->amount_incl_tax,
                'session_stripe' => $session->id,
            ],
        );

        return response()->json(['message' => 'Enregistré.']);
    }

    private function autoriserPaiement(Request $request, Invoice $invoice): void
    {
        // Une facture qui n'est pas la sienne n'existe pas : les references
        // sont sequentielles, un refus confirmerait son existence.
        abort_if($request->user()->cannot('view-all-orders')
            && $invoice->client_id !== $request->user()->id, 404);
    }
}
