<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\TransportOrder;
use Illuminate\Support\Facades\DB;

/**
 * L'encours d'une entreprise : ce qu'elle doit deja (factures emises non
 * reglees, TTC) et ce qu'elle devra (expeditions non annulees pas encore
 * facturees, HT estime). Une facture en retard, ou un encours qui
 * depasserait le plafond de credit, bloque toute nouvelle commande.
 */
class Encours
{
    /** @return array{factures: float, a_facturer: float, total: float, en_retard: int} */
    public static function de(Client $client): array
    {
        $dues = Invoice::where('client_id', $client->id)
            ->where('type', Invoice::FACTURE)
            ->whereIn('status', ['SENT', 'OVERDUE'])
            ->withSum('payments', 'amount')
            ->get(['id', 'amount_incl_tax', 'due_on', 'status']);

        $factures = round($dues->sum(fn (Invoice $f) => max(0, (float) $f->amount_incl_tax - (float) $f->payments_sum_amount)), 2);

        $enRetard = $dues->filter(fn (Invoice $f) => $f->status === 'OVERDUE' || $f->due_on?->lt(today()))->count();

        $aFacturer = round((float) TransportOrder::where('client_id', $client->id)
            ->where('status', '!=', 'CANCELLED')
            ->whereDoesntHave('invoiceLine')
            ->sum(DB::raw('coalesce(estimated_cost, 0)')), 2);

        return [
            'factures' => $factures,
            'a_facturer' => $aFacturer,
            'total' => round($factures + $aFacturer, 2),
            'en_retard' => $enRetard,
        ];
    }

    /**
     * Le motif qui empeche l'entreprise de commander une expedition de ce
     * montant (HT), ou null si elle le peut.
     */
    public static function refus(Client $client, float $montant): ?string
    {
        $encours = self::de($client);

        if ($encours['en_retard'] > 0) {
            return Traductions::t('msg.encours_retard', 'Une facture est en retard de paiement : réglez-la avant de commander une nouvelle expédition.');
        }

        if ($client->credit_limit !== null && (float) $client->credit_limit < $encours['total'] + $montant) {
            return Traductions::t('msg.encours_plafond', 'Plafond de crédit atteint : encours de :encours pour un plafond de :plafond. Réglez vos factures ou contactez-nous pour le relever.', [
                'encours' => Formats::montant($encours['total']),
                'plafond' => Formats::montant((float) $client->credit_limit),
            ]);
        }

        return null;
    }
}
