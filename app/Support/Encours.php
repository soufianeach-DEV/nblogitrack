<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\TransportOrder;
use Illuminate\Support\Facades\DB;

/**
 * L'encours d'une entreprise : ce qu'elle doit deja (factures emises non
 * reglees, TTC) et ce qu'elle devra (expeditions non annulees pas encore
 * facturees, HT estime). Trois factures en retard, ou un encours qui
 * depasserait le plafond de credit, bloquent toute nouvelle commande.
 */
class Encours
{
    /** A partir de ce nombre de factures en retard, l'entreprise ne commande plus. */
    public const RETARDS_BLOQUANTS = 3;

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

        if ($encours['en_retard'] >= self::RETARDS_BLOQUANTS) {
            return Traductions::t('msg.encours_retards', ':n factures sont en retard de paiement : réglez-les avant de commander une nouvelle expédition.', ['n' => $encours['en_retard']]);
        }

        if ($client->credit_limit !== null && (float) $client->credit_limit < $encours['total'] + $montant) {
            return Traductions::t('msg.encours_plafond', 'Plafond de crédit atteint : encours de :encours pour un plafond de :plafond. Réglez vos factures ou contactez-nous pour le relever.', [
                'encours' => Formats::montant($encours['total']),
                'plafond' => Formats::montant((float) $client->credit_limit),
            ]);
        }

        return null;
    }

    /**
     * Un rappel sans blocage : une ou deux factures en retard, avant le
     * seuil qui bloque les commandes.
     */
    public static function avertissement(Client $client): ?string
    {
        $retards = self::de($client)['en_retard'];

        return $retards > 0 && $retards < self::RETARDS_BLOQUANTS
            ? Traductions::t('msg.encours_avertissement', ':n facture(s) en retard de paiement. À partir de :seuil, les nouvelles commandes sont bloquées.', ['n' => $retards, 'seuil' => self::RETARDS_BLOQUANTS])
            : null;
    }
}
