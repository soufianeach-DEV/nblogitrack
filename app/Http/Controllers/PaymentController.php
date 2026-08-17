<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ExceptionInterface as ErreurStripe;
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

        return Inertia::location($session->url);
    }

    public function retour(Request $request, Invoice $invoice): Response
    {
        $this->autoriserPaiement($request, $invoice);

        $regle = $invoice->status === 'PAID';

        if (! $regle && $request->filled('session_id')) {
            $regle = $this->sessionAcquittee((string) $request->query('session_id'), $invoice);
        }

        return Inertia::render('Factures/Paiement', [
            'reference' => $invoice->reference,
            'facture_id' => $invoice->id,
            'montant' => (float) $invoice->amount_incl_tax,
            'regle' => $regle,
            'enregistre' => $invoice->status === 'PAID',
        ]);
    }

    private function sessionAcquittee(string $identifiant, Invoice $invoice): bool
    {
        try {
            $session = (new StripeClient(config('services.stripe.secret')))
                ->checkout->sessions->retrieve($identifiant);
        } catch (ErreurStripe) {
            return false;
        }

        return $session->payment_status === 'paid'
            && (string) $session->client_reference_id === (string) $invoice->id;
    }

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
            return response()->json(['message' => 'Ignoré.']);
        }

        $session = $evenement->data->object;
        $invoice = Invoice::find($session->client_reference_id);

        if ($invoice === null || $session->payment_status !== 'paid') {
            return response()->json(['message' => 'Sans effet.']);
        }

        if ($ecart = $this->discordance($evenement, $session, $invoice)) {
            ActivityLog::record(
                'invoice.payment_rejected',
                'Notification de paiement refusée pour '.$invoice->reference.' : '.$ecart,
                $invoice,
                ['session_stripe' => $session->id, 'motif' => $ecart],
            );

            return response()->json(['message' => 'Notification incohérente, sans effet.']);
        }

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

    private function discordance(object $evenement, object $session, Invoice $invoice): ?string
    {
        $reel = str_starts_with((string) config('services.stripe.secret'), 'sk_live_');

        if ((bool) $evenement->livemode !== $reel) {
            return 'mode '.($evenement->livemode ? 'réel' : 'test')
                .' alors que l\'application est en '.($reel ? 'réel' : 'test');
        }

        $attendu = (int) round((float) $invoice->amount_incl_tax * 100);

        if ((int) $session->amount_total !== $attendu) {
            return 'montant reçu '.$session->amount_total.' contre '.$attendu.' attendu';
        }

        if (strtolower((string) $session->currency) !== 'eur') {
            return 'devise '.$session->currency.' au lieu de eur';
        }

        return null;
    }

    private function autoriserPaiement(Request $request, Invoice $invoice): void
    {
        abort_if($request->user()->cannot('view-all-orders')
            && $invoice->client_id !== $request->user()->id, 404);
    }
}
