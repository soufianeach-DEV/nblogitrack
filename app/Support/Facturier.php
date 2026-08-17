<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\TransportOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Facturier
{
    /**
     * @return Collection<int, Invoice>
     */
    public function facturer(?Carbon $periode = null): Collection
    {
        $emises = collect();

        foreach ($this->aFacturer($periode) as $cle => $expeditions) {
            [$clientId, $mois] = explode('|', (string) $cle);
            $client = Client::find((int) $clientId);

            if ($client === null) {
                continue;
            }

            $emises->push($this->emettre($client, $mois, $expeditions));
        }

        return $emises;
    }

    /**
     * @return Collection<string, Collection<int, TransportOrder>>
     */
    public function aFacturer(?Carbon $periode = null): Collection
    {
        $requete = TransportOrder::where('status', 'DELIVERED')
            ->whereNotNull('actual_delivery_date')
            ->whereNotNull('estimated_cost')
            ->whereDoesntHave('invoiceLine');

        if ($periode !== null) {
            $requete->whereBetween('actual_delivery_date', [
                $periode->copy()->startOfMonth()->toDateString(),
                $periode->copy()->endOfMonth()->toDateString(),
            ]);
        }

        return $requete->orderBy('actual_delivery_date')->get()
            ->groupBy(fn (TransportOrder $o) => $o->client_id.'|'.$o->actual_delivery_date->format('Y-m'));
    }

    /**
     * @param  Collection<int, TransportOrder>  $expeditions
     */
    public function emettre(Client $client, string $mois, Collection $expeditions): Invoice
    {
        return DB::transaction(function () use ($client, $mois, $expeditions) {
            $periode = Carbon::createFromFormat('Y-m-d', $mois.'-01')->startOfMonth();
            $emission = $periode->copy()->addMonth()->startOfMonth();

            $autoliquidation = $client->country !== 'Belgique';
            $taux = $autoliquidation ? 0.00 : Invoice::TAUX_TVA;

            $horsTva = round((float) $expeditions->sum('estimated_cost'), 2);
            $tva = round($horsTva * $taux / 100, 2);

            $rang = $this->prochainRang((int) $emission->format('Y'));

            $facture = Invoice::create([
                'client_id' => $client->id,
                'reference' => sprintf('FAC-%s-%04d', $emission->format('Y'), $rang),
                'issued_on' => $emission,
                'due_on' => $this->echeance($emission, $client->payment_terms),
                'period_start' => $periode,
                'period_end' => $periode->copy()->endOfMonth(),
                'amount_excl_tax' => $horsTva,
                'vat_rate' => $taux,
                'vat_amount' => $tva,
                'amount_incl_tax' => round($horsTva + $tva, 2),
                'reverse_charge' => $autoliquidation,
                'status' => $emission->isFuture() ? 'DRAFT' : 'SENT',
                'payment_reference' => $this->communicationStructuree(
                    (int) $emission->format('Y'), $rang, $client->id
                ),
            ]);

            foreach ($expeditions as $ordre) {
                InvoiceLine::create([
                    'invoice_id' => $facture->id,
                    'transport_order_id' => $ordre->id,
                    'description' => 'Transport '.$ordre->pickup_address.' vers '.$ordre->delivery_address,
                    'amount_excl_tax' => round((float) $ordre->estimated_cost, 2),
                ]);
            }

            return $facture;
        });
    }

    public function prochainRang(int $annee): int
    {
        $dernier = Invoice::where('reference', 'like', 'FAC-'.$annee.'-%')
            ->orderByDesc('reference')
            ->value('reference');

        return $dernier === null ? 1 : ((int) substr($dernier, -4)) + 1;
    }

    public function echeance(Carbon $emission, ?string $delai): Carbon
    {
        return match (trim((string) $delai)) {
            '45 jours' => $emission->copy()->addDays(45),
            '60 jours' => $emission->copy()->addDays(60),
            'Fin de mois' => $emission->copy()->endOfMonth(),
            default => $emission->copy()->addDays(30),
        };
    }

    public function communicationStructuree(int $annee, int $numero, int $clientId): string
    {
        $base = sprintf('%03d%04d%03d', $numero, $annee, $clientId);
        $controle = (int) $base % 97;
        $controle = $controle === 0 ? 97 : $controle;

        $complet = $base.str_pad((string) $controle, 2, '0', STR_PAD_LEFT);

        return sprintf('+++%s/%s/%s+++',
            substr($complet, 0, 3), substr($complet, 3, 4), substr($complet, 7, 5));
    }
}
