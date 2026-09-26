<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\OrderCharge;
use App\Models\TransportOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Facturier
{
    /**
     * @return Collection<int, Invoice>
     */
    public function facturer(?Carbon $periode = null, ?int $clientId = null): Collection
    {
        $emises = collect();

        foreach ($this->aFacturer($periode, $clientId) as $cle => $elements) {
            [$client, $mois] = explode('|', (string) $cle);
            $client = Client::find((int) $client);

            if ($client === null) {
                continue;
            }

            $emises->push($this->emettre($client, $mois, $elements));
        }

        return $emises;
    }

    /**
     * Ce qui reste a facturer, par client et par mois. Trois choses se
     * facturent : un transport livre, au mois de sa livraison ;
     * l'indemnite d'une annulation tardive, au mois de l'annulation
     * (article 8 bis des conditions generales) ; un supplement, une fois
     * son expedition terminee, au plus tard des deux dates.
     *
     * @return Collection<string, Collection<int, array{date: Carbon, client_id: int, kind: string, transport_order_id: ?int, order_charge_id: ?int, description: string, montant: float}>>
     */
    public function aFacturer(?Carbon $periode = null, ?int $clientId = null): Collection
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

        $expeditions = TransportOrder::whereDoesntHave('invoiceLine')
            ->when($clientId !== null, fn ($q) => $q->where('client_id', $clientId))
            ->where(fn ($q) => $q
                ->where(fn ($livre) => $livre->where('status', 'DELIVERED')
                    ->whereNotNull('actual_delivery_date')
                    ->whereNotNull('estimated_cost'))
                ->orWhere(fn ($annule) => $annule->where('status', 'CANCELLED')
                    ->where('cancellation_fee', '>', 0)))
            ->get()
            ->map(fn (TransportOrder $o) => [
                'date' => Carbon::instance($o->dateFacturable()),
                'client_id' => $o->client_id,
                'kind' => $o->status === 'CANCELLED' ? InvoiceLine::ANNULATION : InvoiceLine::TRANSPORT,
                'transport_order_id' => $o->id,
                'order_charge_id' => null,
                'description' => $o->status === 'CANCELLED'
                    ? 'Indemnité d\'annulation '.$o->tracking_number
                    : 'Transport '.$o->pickup_address.' vers '.$o->delivery_address,
                'montant' => round($o->montantFacturable(), 2),
            ]);

        $supplements = OrderCharge::whereDoesntHave('invoiceLine')
            ->whereHas('transportOrder', fn ($q) => $q->whereIn('status', ['DELIVERED', 'CANCELLED'])
                ->when($clientId !== null, fn ($c) => $c->where('client_id', $clientId)))
            ->with('transportOrder')
            ->get()
            ->map(fn (OrderCharge $c) => [
                'date' => Carbon::instance($c->created_at)->max(
                    Carbon::instance($c->transportOrder->dateFacturable() ?? $c->transportOrder->cancelled_at ?? $c->created_at)
                ),
                'client_id' => $c->transportOrder->client_id,
                'kind' => InvoiceLine::SUPPLEMENT,
                'transport_order_id' => $c->transport_order_id,
                'order_charge_id' => $c->id,
                'description' => $c->label.' — '.$c->transportOrder->tracking_number,
                'montant' => round((float) $c->amount, 2),
            ]);

        return $expeditions->concat($supplements)
            ->filter(fn (array $e) => $e['date']->lt($limite)
                && ($bornes === null || $e['date']->between($bornes[0], $bornes[1])))
            ->sortBy(fn (array $e) => $e['date']->getTimestamp())
            ->values()
            ->groupBy(fn (array $e) => $e['client_id'].'|'.$e['date']->format('Y-m'));
    }

    /**
     * @param  Collection<int, array{kind: string, transport_order_id: ?int, order_charge_id: ?int, description: string, montant: float}>  $elements
     */
    public function emettre(Client $client, string $mois, Collection $elements): Invoice
    {
        return DB::transaction(function () use ($client, $mois, $elements) {
            $periode = Carbon::createFromFormat('Y-m-d', $mois.'-01')->startOfMonth();
            $emission = $periode->copy()->addMonth()->startOfMonth();
            $regime = RegimeTva::pour($client);

            $horsTva = round((float) $elements->sum('montant'), 2);
            $tva = round($horsTva * $regime->taux / 100, 2);

            $rang = $this->prochainRang((int) $emission->format('Y'));

            $facture = Invoice::create([
                'client_id' => $client->id,
                'type' => Invoice::FACTURE,
                'reference' => sprintf('FAC-%s-%04d', $emission->format('Y'), $rang),
                'issued_on' => $emission,
                // Emise en retard, une facture garde sa date du 1er du mois,
                // mais le delai de paiement court a partir d'aujourd'hui :
                // sinon, lancee le 26, elle laissait cinq jours au client.
                'due_on' => $this->echeance($emission->copy()->max(now()->startOfDay()), $client->payment_terms),
                'period_start' => $periode,
                'period_end' => $periode->copy()->endOfMonth(),
                'amount_excl_tax' => $horsTva,
                'vat_rate' => $regime->taux,
                'vat_category' => $regime->categorie,
                'vat_amount' => $tva,
                'amount_incl_tax' => round($horsTva + $tva, 2),
                'reverse_charge' => $regime->autoliquidation(),
                'status' => $emission->isFuture() ? 'DRAFT' : 'SENT',
                ...self::acheteur($client, $regime),
            ]);

            // Construite sur l'identifiant de la facture, la communication
            // est unique : deux factures du meme client ne partagent jamais
            // la meme reference de paiement.
            $facture->update(['payment_reference' => $this->communicationStructuree($facture->id)]);

            foreach ($elements as $element) {
                InvoiceLine::create([
                    'invoice_id' => $facture->id,
                    'kind' => $element['kind'],
                    'transport_order_id' => $element['transport_order_id'],
                    'order_charge_id' => $element['order_charge_id'],
                    'description' => $element['description'],
                    'quantity' => 1,
                    'unit_price' => $element['montant'],
                    'amount_excl_tax' => $element['montant'],
                    'vat_category' => $regime->categorie,
                    'vat_rate' => $regime->taux,
                ]);
            }

            return $facture;
        });
    }

    /**
     * L'identite de l'acheteur, figee sur la facture a l'emission.
     *
     * @return array<string, string|null>
     */
    public static function acheteur(Client $client, RegimeTva $regime): array
    {
        return [
            'buyer_name' => $client->company_name,
            'buyer_vat_number' => $client->vat_number,
            'buyer_peppol_id' => $client->peppol_id,
            'buyer_address' => $client->billing_address,
            'buyer_postal_code' => $client->postal_code,
            'buyer_city' => $client->city,
            'buyer_country' => $regime->pays,
        ];
    }

    public function prochainRang(int $annee): int
    {
        // Deux emissions simultanees (la tache planifiee et un lancement a
        // la main) ne prennent pas le meme rang : le verrou tient jusqu'a la
        // fin de la transaction qui cree la facture.
        DB::select('select pg_advisory_xact_lock(?)', [crc32('factures-'.$annee)]);

        // Le plus grand rang se lit comme un nombre : trie comme un texte,
        // « FAC-2027-10000 » passait avant « FAC-2027-9999 ».
        $dernier = Invoice::where('reference', 'like', 'FAC-'.$annee.'-%')
            ->selectRaw("max(cast(split_part(reference, '-', 3) as integer)) as rang")
            ->value('rang');

        return $dernier === null ? 1 : ((int) $dernier) + 1;
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

    public function communicationStructuree(int $factureId): string
    {
        $base = sprintf('%010d', $factureId % 10_000_000_000);
        $controle = (int) $base % 97;
        $controle = $controle === 0 ? 97 : $controle;

        $complet = $base.str_pad((string) $controle, 2, '0', STR_PAD_LEFT);

        return sprintf('+++%s/%s/%s+++',
            substr($complet, 0, 3), substr($complet, 3, 4), substr($complet, 7, 5));
    }
}
