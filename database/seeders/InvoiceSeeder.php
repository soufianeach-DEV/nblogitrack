<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\TransportOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $clients = Client::all()->keyBy('id');
        $numero = 0;
        $serie = [];

        // Une facture par client et par mois. En B2B on ne facture pas chaque
        // expedition separement a un client qui en confie plusieurs par
        // semaine : on regroupe sur une periode, et le detail reste ligne a
        // ligne pour qu'il puisse verifier.
        $groupes = TransportOrder::where('status', 'DELIVERED')
            ->whereNotNull('actual_delivery_date')
            ->whereNotNull('estimated_cost')
            ->orderBy('actual_delivery_date')
            ->get()
            ->groupBy(fn (TransportOrder $ordre) => $ordre->client_id.'|'.$ordre->actual_delivery_date->format('Y-m'));

        foreach ($groupes as $cle => $expeditions) {
            [$clientId, $mois] = explode('|', $cle);
            $client = $clients[(int) $clientId] ?? null;

            if (! $client) {
                continue;
            }

            $periode = Carbon::createFromFormat('Y-m-d', $mois.'-01')->startOfMonth();
            $emission = $periode->copy()->addMonth()->startOfMonth();

            // Un transport entre assujettis de deux Etats membres differents
            // est localise chez le preneur : la TVA est due par le client.
            $autoliquidation = $client->country !== 'Belgique';
            $taux = $autoliquidation ? 0.00 : Invoice::TAUX_TVA;

            $horsTva = round((float) $expeditions->sum('estimated_cost'), 2);
            $tva = round($horsTva * $taux / 100, 2);

            $numero++;
            $annee = $emission->format('Y');
            $serie[$annee] = ($serie[$annee] ?? 0) + 1;
            $echeance = $this->echeance($emission, $client->payment_terms);

            // Quatre factures echues sur cinq sont payees : la cinquieme
            // laisse exister l'etat de retard, sans quoi la page ne pourrait
            // pas etre montree. Un modulo plutot qu'un tirage aleatoire, pour
            // que deux executions du seeder donnent le meme jeu.
            $echue = $echeance->isPast();
            $payee = $echue && $numero % 5 !== 0;

            $facture = Invoice::create([
                'client_id' => $client->id,
                'reference' => sprintf('FAC-%s-%04d', $annee, $serie[$annee]),
                'issued_on' => $emission,
                'due_on' => $echeance,
                'period_start' => $periode,
                'period_end' => $periode->copy()->endOfMonth(),
                'amount_excl_tax' => $horsTva,
                'vat_rate' => $taux,
                'vat_amount' => $tva,
                'amount_incl_tax' => round($horsTva + $tva, 2),
                'reverse_charge' => $autoliquidation,
                'status' => $payee ? 'PAID' : 'SENT',
                'paid_on' => $payee ? $echeance->copy()->subDays(3) : null,
                'payment_reference' => $this->communicationStructuree((int) $annee, $serie[$annee]),
            ]);

            foreach ($expeditions as $ordre) {
                InvoiceLine::create([
                    'invoice_id' => $facture->id,
                    'transport_order_id' => $ordre->id,
                    'description' => 'Transport '.$ordre->pickup_address
                        .' vers '.$ordre->delivery_address,
                    'amount_excl_tax' => round((float) $ordre->estimated_cost, 2),
                ]);
            }
        }
    }

    /**
     * L'echeance decoule du delai convenu avec le client.
     */
    private function echeance(Carbon $emission, ?string $delai): Carbon
    {
        return match (trim((string) $delai)) {
            'Comptant' => $emission->copy(),
            '30 jours' => $emission->copy()->addDays(30),
            '45 jours' => $emission->copy()->addDays(45),
            '60 jours' => $emission->copy()->addDays(60),
            'Fin de mois' => $emission->copy()->endOfMonth(),

            default => $emission->copy()->addDays(30),
        };
    }

    private function communicationStructuree(int $annee, int $numero): string
    {
        $base = sprintf('%04d%06d', $annee, $numero);
        $controle = (int) $base % 97;
        $controle = $controle === 0 ? 97 : $controle;

        $complet = $base.str_pad((string) $controle, 2, '0', STR_PAD_LEFT);

        return sprintf(
            '+++%s/%s/%s+++',
            substr($complet, 0, 3),
            substr($complet, 3, 4),
            substr($complet, 7, 5),
        );
    }
}
