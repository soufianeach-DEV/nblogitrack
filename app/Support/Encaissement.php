<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tout encaissement passe par ici : un virement saisi par la comptabilite
 * comme un paiement en ligne. La facture est soldee quand la somme de ses
 * paiements atteint son montant TTC ; un paiement partiel la laisse a
 * payer, pour le reste du.
 */
class Encaissement
{
    /**
     * @return Payment|null null si le paiement depasse le solde (a
     *                      rembourser) ou s'il a deja ete enregistre
     */
    public static function enregistrer(
        Invoice $facture,
        float $montant,
        Carbon $date,
        string $methode,
        ?string $reference = null,
        ?int $auteur = null,
    ): ?Payment {
        try {
            return DB::transaction(function () use ($facture, $montant, $date, $methode, $reference, $auteur) {
                // Le verrou sur la facture serialise deux encaissements
                // simultanes : chacun voit le solde laisse par l'autre.
                $verrouillee = Invoice::whereKey($facture->id)->lockForUpdate()->first();

                if (! $verrouillee->estAPayer() || round($montant, 2) > $verrouillee->solde() + 0.001) {
                    return null;
                }

                $paiement = Payment::create([
                    'invoice_id' => $facture->id,
                    'amount' => round($montant, 2),
                    'paid_on' => $date->toDateString(),
                    'method' => $methode,
                    'reference' => $reference,
                    'recorded_by' => $auteur,
                ]);

                if ($verrouillee->solde() <= 0.0) {
                    $verrouillee->update(['status' => 'PAID', 'paid_on' => $date->toDateString()]);
                }

                $facture->refresh();

                return $paiement;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
