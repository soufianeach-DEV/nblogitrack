<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;

/**
 * Une facture emise ne se modifie ni ne se supprime : on l'annule par un
 * avoir, document distinct et numerote (serie AV-), qui reprend ses
 * lignes. « Avec refacturation », les expeditions et supplements sont
 * liberes et repartent aussitot sur une nouvelle facture, avec les
 * donnees corrigees (adresse, regime de TVA, supplement ajoute...).
 */
class Avoirs
{
    /**
     * @return array{avoir: Invoice, nouvelle: ?Invoice}
     */
    public static function emettre(Invoice $facture, string $motif, bool $refacturer, int $auteur): array
    {
        return DB::transaction(function () use ($facture, $motif, $refacturer) {
            $facture = Invoice::whereKey($facture->id)->lockForUpdate()->first();

            if ($facture->estAvoir() || ! in_array($facture->status, ['SENT', 'OVERDUE'], true) || $facture->montantPaye() > 0) {
                throw new \DomainException(Traductions::t('msg.avoir_impossible', 'Seule une facture émise et sans paiement peut être annulée par un avoir.'));
            }

            $facturier = app(Facturier::class);
            DB::select('select pg_advisory_xact_lock(?)', [crc32('avoirs')]);
            $annee = (int) now()->format('Y');
            $dernier = Invoice::where('reference', 'like', 'AV-'.$annee.'-%')
                ->selectRaw("max(cast(split_part(reference, '-', 3) as integer)) as rang")
                ->value('rang');

            $avoir = Invoice::create([
                'client_id' => $facture->client_id,
                'type' => Invoice::AVOIR,
                'credited_invoice_id' => $facture->id,
                'credit_reason' => $motif,
                'reference' => sprintf('AV-%s-%04d', $annee, ((int) $dernier) + 1),
                'issued_on' => now()->toDateString(),
                'due_on' => now()->toDateString(),
                'period_start' => $facture->period_start,
                'period_end' => $facture->period_end,
                'amount_excl_tax' => $facture->amount_excl_tax,
                'vat_rate' => $facture->vat_rate,
                'vat_category' => $facture->vat_category,
                'vat_amount' => $facture->vat_amount,
                'amount_incl_tax' => $facture->amount_incl_tax,
                'reverse_charge' => $facture->reverse_charge,
                'status' => 'SENT',
                'buyer_name' => $facture->buyer_name,
                'buyer_vat_number' => $facture->buyer_vat_number,
                'buyer_peppol_id' => $facture->buyer_peppol_id,
                'buyer_address' => $facture->buyer_address,
                'buyer_postal_code' => $facture->buyer_postal_code,
                'buyer_city' => $facture->buyer_city,
                'buyer_country' => $facture->buyer_country,
            ]);

            foreach ($facture->lines as $ligne) {
                InvoiceLine::create([
                    ...$ligne->only(['kind', 'transport_order_id', 'order_charge_id', 'description', 'quantity', 'unit_price', 'amount_excl_tax', 'vat_category', 'vat_rate']),
                    'invoice_id' => $avoir->id,
                    // Les lignes d'un avoir ne facturent rien : elles ne
                    // retiennent jamais une expedition.
                    'active' => false,
                ]);
            }

            $facture->update(['status' => 'CREDITED']);

            if (! $refacturer) {
                return ['avoir' => $avoir, 'nouvelle' => null];
            }

            $facture->lines()->update(['active' => false]);

            // La facture corrigee est emise aujourd'hui, comme l'avoir : un
            // numero plus grand ne porte jamais une date plus ancienne.
            $nouvelle = $facturier->facturer($facture->period_start->copy(), $facture->client_id, now())->first();

            return ['avoir' => $avoir, 'nouvelle' => $nouvelle];
        });
    }
}
