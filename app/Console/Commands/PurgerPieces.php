<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\OrderCharge;
use App\Models\Payment;
use App\Models\QuoteRequest;
use App\Models\TransportOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Efface les pieces dont la duree legale de conservation est echue.
 *
 * Factures, avoirs et paiements se gardent sept ans a compter du 1er
 * janvier qui suit l'annee de la piece (Code de la TVA, art. 60, tel que
 * modifie par la loi du 18 decembre 2025 ; Code de droit economique,
 * art. III.88 ; CIR 92, art. 315) : une piece de 2026 se garde jusqu'au
 * 31 decembre 2033 et s'efface en 2034. Passe ce delai, rien ne justifie
 * plus de garder les donnees personnelles qu'elles portent (RGPD,
 * art. 5.1.e).
 *
 * L'ordre de transport facture est la piece justificative de sa facture :
 * il s'efface quand plus aucune facture conservee ne le cite, avec ses
 * supplements, ses positions et la demande de devis dont il est issu. Un
 * ordre annule sans frais n'est cite par aucune facture : il se garde trois
 * ans, le plus long delai de prescription de la convention CMR (art. 32).
 *
 * Une entreprise desinscrite (suppression logique) s'efface enfin avec ses
 * comptes, deja anonymises, quand il ne lui reste plus aucune piece ; un
 * chauffeur parti perd son nom quand plus aucun dossier ne le cite.
 */
class PurgerPieces extends Command
{
    public const ANS = 7;

    public const ANNULE_SANS_FRAIS = 3;

    protected $signature = 'pieces:purger {--essai : Compter sans effacer}';

    protected $description = 'Efface les factures, paiements et ordres dont la durée légale de conservation est échue.';

    public function handle(): int
    {
        // Une piece datee avant ce jour a fini son delai de conservation.
        $limite = CarbonImmutable::create(now()->year - self::ANS, 1, 1, 0, 0, 0, 'Europe/Brussels');

        $factures = $this->facturesEchues($limite);
        $paiements = Payment::whereIn('invoice_id', $factures)->count();

        if ($this->option('essai')) {
            $ordres = $this->ordresEchus($limite, $factures);
            $this->line('  Pièces datées avant le '.$limite->format('d/m/Y').' :');
            $this->line('  '.$factures->count().' facture(s) ou avoir(s) et '.$paiements.' paiement(s) seraient effacés.');
            $this->line('  '.$ordres->count().' ordre(s) de transport seraient effacés.');

            return self::SUCCESS;
        }

        [$nombreOrdres, $nombreEntreprises] = DB::transaction(function () use ($limite, $factures) {
            Payment::whereIn('invoice_id', $factures)->delete();
            // Les avoirs d'abord : ils citent la facture qu'ils annulent.
            Invoice::whereIn('id', $factures)->whereNotNull('credited_invoice_id')->delete();
            Invoice::whereIn('id', $factures)->delete();

            $ordres = $this->ordresEchus($limite);
            $this->effacerOrdres($ordres);

            $this->effacerNomsDesChauffeursPartis();

            return [$ordres->count(), $this->effacerEntreprisesParties()];
        });

        $this->info(sprintf(
            '  %d facture(s) ou avoir(s), %d paiement(s), %d ordre(s) de transport et %d entreprise(s) désinscrite(s) effacés.',
            $factures->count(), $paiements, $nombreOrdres, $nombreEntreprises,
        ));

        return self::SUCCESS;
    }

    /**
     * Factures et avoirs emis avant la limite, sans paiement apres elle. Une
     * facture dont l'avoir est encore conserve reste : l'avoir la cite.
     *
     * @return Collection<int, int>
     */
    private function facturesEchues(CarbonImmutable $limite): Collection
    {
        $candidates = Invoice::whereNotNull('issued_on')
            ->where('issued_on', '<', $limite->toDateString())
            ->whereDoesntHave('payments', fn ($p) => $p->where('paid_on', '>=', $limite->toDateString()))
            ->pluck('id');

        $retenues = Invoice::whereIn('credited_invoice_id', $candidates)
            ->whereNotIn('id', $candidates)
            ->pluck('credited_invoice_id');

        return $candidates->diff($retenues)->values();
    }

    /**
     * Ordres qu'aucune facture conservee ne cite : livres avant la limite,
     * ou annules (sans frais depuis trois ans, avec frais avant la limite).
     *
     * @param  Collection<int, int>|null  $partantes  factures sur le point d'etre effacees (mode essai)
     * @return Collection<int, int>
     */
    private function ordresEchus(CarbonImmutable $limite, ?Collection $partantes = null): Collection
    {
        $citeParUneFacture = fn ($q) => $q->select(DB::raw(1))
            ->from('invoice_lines')
            ->when($partantes, fn ($l) => $l->whereNotIn('invoice_lines.invoice_id', $partantes))
            ->where(fn ($l) => $l->whereColumn('invoice_lines.transport_order_id', 'transport_orders.id')
                ->orWhereIn('invoice_lines.order_charge_id', fn ($c) => $c->select('id')
                    ->from('order_charges')
                    ->whereColumn('order_charges.transport_order_id', 'transport_orders.id')));

        return TransportOrder::whereNotExists($citeParUneFacture)
            ->where(fn ($q) => $q
                ->where(fn ($l) => $l->where('status', 'DELIVERED')
                    ->whereRaw('COALESCE(delivered_at, actual_delivery_date, updated_at) < ?', [$limite]))
                ->orWhere(fn ($a) => $a->where('status', 'CANCELLED')
                    ->where(fn ($d) => $d
                        ->where(fn ($s) => $s->whereRaw('COALESCE(cancellation_fee, 0) = 0')
                            ->whereRaw('COALESCE(cancelled_at, updated_at) < ?', [now()->subYears(self::ANNULE_SANS_FRAIS)]))
                        ->orWhereRaw('COALESCE(cancelled_at, updated_at) < ?', [$limite]))))
            ->pluck('id');
    }

    /** @param  Collection<int, int>  $ordres */
    private function effacerOrdres(Collection $ordres): void
    {
        // La demande de devis devenue commande suit la commande, ses pieces
        // jointes comprises (les fichiers d'abord : une ligne effacee ne
        // dirait plus ou ils sont).
        QuoteRequest::whereIn('converted_order_id', $ordres)->get(['id', 'reference'])
            ->each(function (QuoteRequest $demande) {
                Storage::disk('local')->deleteDirectory('devis/'.$demande->reference);
                $demande->delete();
            });

        OrderCharge::whereIn('transport_order_id', $ordres)->delete();
        // Les positions suivent l'ordre (suppression en cascade).
        TransportOrder::whereIn('id', $ordres)->delete();
    }

    /**
     * Un chauffeur parti garde son nom tant qu'un dossier de transport
     * conserve le cite (voir chauffeurs:cloturer-departs). Le dernier
     * efface, son compte ferme ne dit plus qui il etait.
     */
    private function effacerNomsDesChauffeursPartis(): void
    {
        User::whereIn('id', Driver::whereNotNull('left_on')
            ->where('left_on', '<=', today()->subYear())
            ->whereNotNull('user_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('transport_orders')
                ->whereColumn('transport_orders.driver_id', 'drivers.id'))
            ->select('user_id'))
            ->where('first_name', '!=', 'Ancien')
            ->update(['first_name' => 'Ancien', 'last_name' => 'chauffeur']);
    }

    /** Entreprises desinscrites a qui il ne reste ni ordre ni facture. */
    private function effacerEntreprisesParties(): int
    {
        $parties = Client::onlyTrashed()
            ->whereDoesntHave('invoices')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('transport_orders')
                ->whereColumn('transport_orders.client_id', 'clients.id'))
            ->pluck('id');

        foreach ($parties as $id) {
            User::withTrashed()->where('client_id', $id)->forceDelete();
            Client::withTrashed()->whereKey($id)->forceDelete();
        }

        return $parties->count();
    }
}
