<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Encaissement;
use App\Support\Facturier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $facturier = app(Facturier::class);
        $factures = $facturier->facturer();

        // Une facture emise aujourd'hui pour un transport de 2024 recoit une
        // echeance future (on ne met pas un client en retard le jour meme).
        // L'historique de demonstration est celui d'une facturation tenue
        // chaque mois : l'echeance suit l'emission.
        foreach ($factures as $facture) {
            $facture->loadMissing('client');
            $facture->update(['due_on' => $facturier->echeance($facture->issued_on, $facture->client?->payment_terms)]);
        }

        $this->encaisser($factures);
    }

    /**
     * La plupart des factures echues sont reglees, parfois en deux fois ;
     * une sur dix reste impayee (relance), comme dans une vraie
     * comptabilite.
     *
     * @param  Collection<int, Invoice>  $factures
     */
    private function encaisser(Collection $factures): void
    {
        $comptable = User::where('role', 'ADMIN')->orderBy('id')->value('id');
        $methodes = ['TRANSFER', 'TRANSFER', 'TRANSFER', 'STRIPE', 'TRANSFER'];

        foreach ($factures->values() as $rang => $facture) {
            if (! $facture->due_on->isPast() || ($rang + 1) % 10 === 0) {
                continue;
            }

            $total = (float) $facture->amount_incl_tax;
            $methode = $methodes[$rang % count($methodes)];
            $date = $facture->due_on->copy()->subDays($rang % 12)->max($facture->issued_on);

            // Un client sur six paie en deux fois : acompte puis solde.
            if ($rang % 6 === 0 && $total > 100) {
                $acompte = round($total * 0.4, 2);
                Encaissement::enregistrer($facture, $acompte, $date->copy()->subDays(10)->max($facture->issued_on), $methode, 'ACOMPTE-'.$facture->reference, $comptable);
                Encaissement::enregistrer($facture, round($total - $acompte, 2), $date, $methode, 'SOLDE-'.$facture->reference, $comptable);

                continue;
            }

            Encaissement::enregistrer($facture, $total, $date, $methode, $methode === 'STRIPE' ? 'cs_demo_'.$facture->id : null, $comptable);
        }
    }
}
