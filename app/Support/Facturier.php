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
        // Le mois en cours n'est jamais facture, meme avec --tout ou
        // --mois. Sa facture porterait une date d'emission future et
        // resterait en brouillon : ni payable, ni comptee dans la TVA, et
        // ses expeditions, deja rattachees a une ligne, ne seraient plus
        // jamais reprises.
        $limite = now()->startOfMonth();
        $bornes = $periode === null ? null : [
            $periode->copy()->startOfMonth(),
            $periode->copy()->endOfMonth()->endOfDay(),
        ];

        // Deux choses se facturent : un transport livre, au mois de sa
        // livraison, et l'indemnite d'une annulation tardive, au mois de
        // l'annulation (article 8 bis des conditions generales).
        $requete = TransportOrder::whereDoesntHave('invoiceLine')
            ->where(fn ($q) => $q
                ->where(function ($livre) use ($limite, $bornes) {
                    $livre->where('status', 'DELIVERED')
                        ->whereNotNull('actual_delivery_date')
                        ->whereNotNull('estimated_cost')
                        ->where('actual_delivery_date', '<', $limite->toDateString());

                    if ($bornes !== null) {
                        $livre->whereBetween('actual_delivery_date', [
                            $bornes[0]->toDateString(), $bornes[1]->toDateString(),
                        ]);
                    }
                })
                ->orWhere(function ($annule) use ($limite, $bornes) {
                    $annule->where('status', 'CANCELLED')
                        ->where('cancellation_fee', '>', 0)
                        ->where('cancelled_at', '<', $limite);

                    if ($bornes !== null) {
                        $annule->whereBetween('cancelled_at', $bornes);
                    }
                }));

        return $requete->get()
            ->sortBy(fn (TransportOrder $o) => $o->dateFacturable()->getTimestamp())
            ->values()
            ->groupBy(fn (TransportOrder $o) => $o->client_id.'|'.$o->dateFacturable()->format('Y-m'));
    }

    /**
     * @param  Collection<int, TransportOrder>  $expeditions
     */
    public function emettre(Client $client, string $mois, Collection $expeditions): Invoice
    {
        return DB::transaction(function () use ($client, $mois, $expeditions) {
            $periode = Carbon::createFromFormat('Y-m-d', $mois.'-01')->startOfMonth();
            $emission = $periode->copy()->addMonth()->startOfMonth();

            // Le pays est compare par son code et non par son libelle :
            // une entreprise inscrite depuis l'interface neerlandaise ou
            // anglaise porte « België » ou « Belgium », et la comparaison
            // au seul « Belgique » la facturait sans TVA, en
            // autoliquidation. A defaut de pays reconnu, le prefixe du
            // numero de TVA tranche.
            $pays = Pays::depuisNom($client->country)
                ?? strtoupper(substr((string) $client->vat_number, 0, 2));
            $autoliquidation = $pays !== 'BE';
            $taux = $autoliquidation ? 0.00 : Invoice::TAUX_TVA;

            $horsTva = round((float) $expeditions->sum(fn (TransportOrder $o) => round($o->montantFacturable(), 2)), 2);
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
                    'description' => $ordre->status === 'CANCELLED'
                        ? 'Indemnité d\'annulation '.$ordre->tracking_number
                        : 'Transport '.$ordre->pickup_address.' vers '.$ordre->delivery_address,
                    'amount_excl_tax' => round($ordre->montantFacturable(), 2),
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
        // Une communication structuree compte dix chiffres, plus deux de
        // controle. sprintf ne fixe qu'une largeur minimale : a partir du
        // client 1000 ou de la millieme facture de l'annee, la base
        // debordait et le decoupage tronquait le chiffre de controle.
        // Chaque champ est donc ramene a sa largeur.
        $base = sprintf('%03d%04d%03d', $numero % 1000, $annee % 10000, $clientId % 1000);
        $controle = (int) $base % 97;
        $controle = $controle === 0 ? 97 : $controle;

        $complet = $base.str_pad((string) $controle, 2, '0', STR_PAD_LEFT);

        return sprintf('+++%s/%s/%s+++',
            substr($complet, 0, 3), substr($complet, 3, 4), substr($complet, 7, 5));
    }
}
