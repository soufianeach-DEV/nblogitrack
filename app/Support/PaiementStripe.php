<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Stripe\Exception\ExceptionInterface;
use Stripe\StripeClient;

/**
 * Le reglement d'une facture par Stripe Checkout (carte, Bancontact et les
 * autres moyens actives dans le tableau de bord Stripe).
 *
 * Un paiement s'enregistre par deux chemins, qui se rejoignent ici : la
 * notification signee (webhook) et le retour du client sur le site. Le
 * premier arrive meme si le client ferme son navigateur, le second meme si
 * le webhook n'est pas encore configure. La reference de session est
 * unique : un paiement n'est jamais compte deux fois.
 */
class PaiementStripe
{
    public static function actif(): bool
    {
        return ! empty(config('services.stripe.secret'));
    }

    public static function client(): object
    {
        // Les tests remplacent le client par un double.
        if (app()->bound('stripe.client')) {
            return app('stripe.client');
        }

        $options = ['api_key' => config('services.stripe.secret')];

        // Un emulateur local (stripe-mock, tests de bout en bout) remplace
        // l'API reelle quand STRIPE_API_BASE est renseigne.
        if ($base = config('services.stripe.api_base')) {
            $options['api_base'] = $base;
        }

        return new StripeClient($options);
    }

    /**
     * La session a presenter au client : la session encore ouverte de la
     * facture si elle porte le bon montant (deux onglets, un clic
     * repete : une seule session, donc un seul paiement possible), sinon
     * une nouvelle. L'ancienne session, perimee, est fermee chez Stripe.
     */
    public static function sessionPourPayer(Invoice $facture, User $payeur): object
    {
        if ($facture->stripe_session_id) {
            try {
                $ancienne = self::lireSession($facture->stripe_session_id);

                if ($ancienne->status === 'open') {
                    if ((int) $ancienne->amount_total === (int) round($facture->solde() * 100)) {
                        return $ancienne;
                    }

                    self::client()->checkout->sessions->expire($ancienne->id);
                }
            } catch (ExceptionInterface $e) {
                report($e);
            }
        }

        $session = self::ouvrirSession($facture, $payeur);
        $facture->update(['stripe_session_id' => $session->id]);

        return $session;
    }

    public static function ouvrirSession(Invoice $facture, User $payeur): object
    {
        return self::client()->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => (string) $facture->id,
            'customer_email' => $payeur->email,
            // La page de paiement parle la langue de l'ecran.
            'locale' => in_array(app()->getLocale(), ['fr', 'nl', 'en'], true) ? app()->getLocale() : 'auto',
            'metadata' => ['facture' => $facture->reference, 'facture_id' => (string) $facture->id],
            'payment_intent_data' => [
                'description' => Traductions::t('msg.stripe_facture', 'Facture :reference', ['reference' => $facture->reference]),
                'metadata' => ['facture' => $facture->reference, 'facture_id' => (string) $facture->id],
            ],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    // Le client regle ce qui reste du, deduction faite
                    // d'un eventuel paiement partiel.
                    'unit_amount' => (int) round($facture->solde() * 100),
                    'product_data' => [
                        'name' => Traductions::t('msg.stripe_facture', 'Facture :reference', ['reference' => $facture->reference]),
                        'description' => Traductions::t('msg.stripe_periode', 'Transport du :debut au :fin', [
                            'debut' => $facture->period_start->format('d/m/Y'),
                            'fin' => $facture->period_end->format('d/m/Y'),
                        ]),
                    ],
                ],
            ]],
            'success_url' => route('payments.retour', $facture).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('invoices.show', $facture),
        ]);
    }

    public static function lireSession(string $identifiant): object
    {
        return self::client()->checkout->sessions->retrieve($identifiant);
    }

    /** Un paiement differe a echoue : la facture redevient payable. */
    public static function echec(object $session, Invoice $facture): void
    {
        if ((string) $session->client_reference_id === (string) $facture->id) {
            $facture->forceFill(['online_payment_pending_at' => null])->save();
        }
    }

    /**
     * Les paiements en ligne recus en trop pour une facture, a rembourser
     * depuis le tableau de bord Stripe.
     *
     * @return Collection<int, array{session: string, montant: string, date: CarbonInterface}>
     */
    public static function excedentsARembourser(Invoice $facture)
    {
        return ActivityLog::where('action', 'invoice.payment_duplicate')
            ->where('subject_type', 'Invoice')
            ->where('subject_id', (string) $facture->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (ActivityLog $l) => [
                'session' => (string) ($l->properties['session_stripe'] ?? ''),
                'montant' => (string) ($l->properties['montant'] ?? ''),
                'date' => $l->created_at,
            ])
            ->unique('session')
            ->values();
    }

    /**
     * @return 'enregistre'|'deja'|'excedent'|'en_attente'|'refuse'
     */
    public static function enregistrer(object $session, Invoice $facture): string
    {
        // Un virement SEPA, par exemple, ne sera paye que plus tard :
        // Stripe enverra alors async_payment_succeeded. D'ici la, la
        // facture l'annonce et n'accepte pas un second paiement en ligne.
        if ($session->payment_status !== 'paid') {
            if ($session->status === 'complete' && (string) $session->client_reference_id === (string) $facture->id) {
                $facture->forceFill(['online_payment_pending_at' => $facture->online_payment_pending_at ?? now()])->save();
            }

            return 'en_attente';
        }

        if ($ecart = self::discordance($session, $facture)) {
            ActivityLog::record(
                'invoice.payment_rejected',
                'Notification de paiement refusée pour '.$facture->reference.' : '.$ecart,
                $facture,
                ['session_stripe' => $session->id, 'motif' => $ecart],
            );

            return 'refuse';
        }

        if (Payment::where('reference', (string) $session->id)->exists()) {
            return 'deja';
        }

        $facture->forceFill(['online_payment_pending_at' => null])->save();

        $montant = ((int) $session->amount_total) / 100;

        // L'encaissement verrouille la facture : deux enregistrements
        // simultanes (webhook et retour du client) ne l'ecrivent qu'une fois.
        // Un paiement au-dela du solde (facture deja reglee par virement,
        // seconde session) n'est pas perdu : il est journalise, a rembourser.
        $paiement = Encaissement::enregistrer($facture, $montant, now(), 'STRIPE', (string) $session->id);

        if ($paiement === null) {
            if (Payment::where('reference', (string) $session->id)->exists()) {
                return 'deja';
            }

            // Le webhook et le retour du client signalent le meme exces :
            // il n'est journalise qu'une fois, pour un seul remboursement.
            if (! self::excedentsARembourser($facture)->contains('session', (string) $session->id)) {
                ActivityLog::record(
                    'invoice.payment_duplicate',
                    'Paiement en ligne reçu pour '.$facture->reference.', déjà réglée : à rembourser',
                    $facture,
                    ['montant' => (string) $montant, 'session_stripe' => $session->id],
                );
            }

            return 'excedent';
        }

        ActivityLog::record(
            'invoice.paid_online',
            'Paiement en ligne reçu pour '.$facture->reference,
            $facture,
            ['montant' => (string) $paiement->amount, 'session_stripe' => $session->id],
        );

        return 'enregistre';
    }

    private static function discordance(object $session, Invoice $facture): ?string
    {
        if ((string) $session->client_reference_id !== (string) $facture->id) {
            return 'session d\'une autre facture';
        }

        $reel = str_starts_with((string) config('services.stripe.secret'), 'sk_live_');

        if ((bool) ($session->livemode ?? false) !== $reel) {
            return 'mode '.(($session->livemode ?? false) ? 'réel' : 'test')
                .' alors que l\'application est en '.($reel ? 'réel' : 'test');
        }

        // La session porte le solde du au moment du paiement : jamais plus
        // que le montant TTC, jamais zero.
        $plafond = (int) round((float) $facture->amount_incl_tax * 100);

        if ((int) $session->amount_total <= 0 || (int) $session->amount_total > $plafond) {
            return 'montant reçu '.$session->amount_total.' pour un total de '.$plafond;
        }

        if (strtolower((string) $session->currency) !== 'eur') {
            return 'devise '.$session->currency.' au lieu de eur';
        }

        return null;
    }
}
