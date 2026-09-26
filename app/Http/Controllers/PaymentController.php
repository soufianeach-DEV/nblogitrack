<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Encaissement;
use App\Support\Traductions;
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

        if (! $invoice->estAPayer() || $invoice->solde() <= 0) {
            return back()->with('error', Traductions::t('msg.facture_non_payable', 'Cette facture ne peut pas être réglée en ligne.'));
        }

        // Sans cle Stripe, ou si Stripe refuse, le client reste sur sa
        // facture avec un message, au lieu d'une erreur 500.
        if (empty(config('services.stripe.secret'))) {
            return back()->with('error', Traductions::t('msg.paiement_en_ligne_indisponible', 'Le paiement en ligne est momentanément indisponible. Réglez par virement avec la communication structurée.'));
        }

        try {
            $session = $this->ouvrirSession($request, $invoice);
        } catch (ErreurStripe $e) {
            report($e);

            return back()->with('error', Traductions::t('msg.paiement_en_ligne_indisponible', 'Le paiement en ligne est momentanément indisponible. Réglez par virement avec la communication structurée.'));
        }

        return Inertia::location($session->url);
    }

    private function ouvrirSession(Request $request, Invoice $invoice): object
    {
        $stripe = new StripeClient(config('services.stripe.secret'));

        return $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => (string) $invoice->id,
            'customer_email' => $request->user()->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    // Le client regle ce qui reste du, deduction faite
                    // d'un eventuel paiement partiel.
                    'unit_amount' => (int) round($invoice->solde() * 100),
                    'product_data' => [
                        'name' => Traductions::t('msg.stripe_facture', 'Facture :reference', ['reference' => $invoice->reference]),
                        'description' => Traductions::t('msg.stripe_periode', 'Transport du :debut au :fin', [
                            'debut' => $invoice->period_start->format('d/m/Y'),
                            'fin' => $invoice->period_end->format('d/m/Y'),
                        ]),
                    ],
                ],
            ]],
            'success_url' => route('payments.retour', $invoice).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('invoices.show', $invoice),
        ]);
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

        // L'encaissement verrouille la facture et la reference de session
        // est unique : deux notifications simultanees ne l'ecrivent qu'une
        // fois. Un paiement au-dela du solde (facture deja reglee par
        // virement, seconde session) n'est pas perdu : il est journalise,
        // a rembourser.
        $paiement = Encaissement::enregistrer(
            $invoice,
            ((int) $session->amount_total) / 100,
            now(),
            'STRIPE',
            (string) $session->id,
        );

        if ($paiement === null) {
            if (Payment::where('reference', (string) $session->id)->exists()) {
                return response()->json(['message' => 'Déjà enregistré.']);
            }

            ActivityLog::record(
                'invoice.payment_duplicate',
                'Paiement en ligne reçu pour '.$invoice->reference.', déjà réglée : à rembourser',
                $invoice,
                [
                    'montant' => (string) (((int) $session->amount_total) / 100),
                    'session_stripe' => $session->id,
                ],
            );

            return response()->json(['message' => 'Déjà enregistré.']);
        }

        ActivityLog::record(
            'invoice.paid_online',
            'Paiement en ligne reçu pour '.$invoice->reference,
            $invoice,
            [
                'montant' => (string) $paiement->amount,
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

        // La session porte le solde du au moment du paiement : jamais plus
        // que le montant TTC, jamais zero.
        $plafond = (int) round((float) $invoice->amount_incl_tax * 100);

        if ((int) $session->amount_total <= 0 || (int) $session->amount_total > $plafond) {
            return 'montant reçu '.$session->amount_total.' pour un total de '.$plafond;
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
