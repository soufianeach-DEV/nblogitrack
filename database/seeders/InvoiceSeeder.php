<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Support\Facturier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $this->decorer(app(Facturier::class)->facturer());
    }

    /**
     * @param  Collection<int, Invoice>  $factures
     */
    private function decorer(Collection $factures): void
    {
        foreach ($factures->values() as $rang => $facture) {
            if (! $facture->due_on->isPast() || ($rang + 1) % 5 === 0) {
                continue;
            }

            $facture->update([
                'status' => 'PAID',
                'paid_on' => $facture->due_on->copy()->subDays(3)->max($facture->issued_on),
            ]);
        }
    }
}
