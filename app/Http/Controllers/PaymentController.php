<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\PaiementStripe;
use App\Support\Traductions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ExceptionInterface as ErreurStripe;
use Stripe\Exception\SignatureVerificationException;
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
        if (! PaiementStripe::actif()) {
            return back()->with('error', Traductions::t('msg.paiement_en_ligne_indisponible', 'Le paiement en ligne est momentanément indisponible. Réglez par virement avec la communication structurée.'));
        }

        // Un virement lance depuis Stripe est en cours : un second paiement
        // ferait payer deux fois la meme facture.
        if ($invoice->online_payment_pending_at !== null) {
            return back()->with('error', Traductions::t('msg.paiement_deja_en_cours', 'Un paiement en ligne de cette facture est en cours de traitement par la banque depuis le :date : il sera enregistré dès sa réception.', [
                'date' => $invoice->online_payment_pending_at->format('d/m/Y'),
            ]));
        }

        try {
            // Le verrou sur la facture : deux clics simultanes ne creent
            // qu'une session.
            $session = DB::transaction(function () use ($invoice, $request) {
                $facture = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

                return PaiementStripe::sessionPourPayer($facture, $request->user());
            });
        } catch (ErreurStripe $e) {
            report($e);

            return back()->with('error', Traductions::t('msg.paiement_en_ligne_indisponible', 'Le paiement en ligne est momentanément indisponible. Réglez par virement avec la communication structurée.'));
        }

        return Inertia::location($session->url);
    }

    public function retour(Request $request, Invoice $invoice): Response
    {
        $this->autoriserPaiement($request, $invoice);

        $etat = null;
        $montant = null;

        // Le paiement s'enregistre des le retour du client, sans attendre
        // la notification de Stripe : il reste compte une seule fois si
        // celle-ci arrive ensuite.
        if ($request->filled('session_id') && PaiementStripe::actif()) {
            try {
                $session = PaiementStripe::lireSession((string) $request->query('session_id'));
                $etat = PaiementStripe::enregistrer($session, $invoice);
                $montant = ((int) $session->amount_total) / 100;
            } catch (ErreurStripe $e) {
                report($e);
            }
        }

        $invoice->refresh();

        return Inertia::render('Factures/Paiement', [
            'reference' => $invoice->reference,
            'facture_id' => $invoice->id,
            'montant' => $montant ?? (float) $invoice->amount_incl_tax,
            'regle' => in_array($etat, ['enregistre', 'deja'], true) || ($etat === null && $invoice->status === 'PAID'),
            'double' => $etat === 'excedent',
            'en_attente' => $etat === 'en_attente',
            'solde' => $invoice->solde(),
            'enregistre' => in_array($etat, ['enregistre', 'deja'], true) || $invoice->status === 'PAID',
        ]);
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
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return response()->json(['message' => 'Signature invalide.'], 400);
        }

        // Un paiement differe (virement SEPA...) arrive par
        // async_payment_succeeded, apres un completed encore impaye.
        if (! in_array($evenement->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed'], true)) {
            return response()->json(['message' => 'Ignoré.']);
        }

        $session = $evenement->data->object;
        $invoice = Invoice::find($session->client_reference_id);

        if ($invoice === null) {
            return response()->json(['message' => 'Sans effet.']);
        }

        // Le virement differe n'est pas arrive : la facture redevient
        // payable.
        if ($evenement->type === 'checkout.session.async_payment_failed') {
            PaiementStripe::echec($session, $invoice);

            return response()->json(['message' => 'Paiement échoué, facture de nouveau payable.']);
        }

        return response()->json(['message' => match (PaiementStripe::enregistrer($session, $invoice)) {
            'enregistre' => 'Enregistré.',
            'deja', 'excedent' => 'Déjà enregistré.',
            'en_attente' => 'En attente du paiement.',
            'refuse' => 'Notification incohérente, sans effet.',
        }]);
    }

    private function autoriserPaiement(Request $request, Invoice $invoice): void
    {
        abort_unless($request->user()->can('view-all-orders') || $request->user()->can('pay', $invoice), 404);
    }
}
