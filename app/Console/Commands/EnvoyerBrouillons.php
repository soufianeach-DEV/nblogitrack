<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Support\EnvoiFacture;
use Illuminate\Console\Command;

/**
 * Une facture datee dans le futur reste en brouillon : ni payable, ni
 * envoyee. Rien ne la faisait passer ensuite a « envoyee » le jour venu.
 * Cette tache, lancee chaque matin, s'en charge et envoie le courriel.
 */
class EnvoyerBrouillons extends Command
{
    protected $signature = 'factures:envoyer-brouillons {--sans-envoi : Change le statut sans envoyer de courriel.}';

    protected $description = 'Passe a « envoyee » les factures en brouillon dont la date d\'emission est arrivee.';

    public function handle(): int
    {
        $factures = Invoice::where('status', 'DRAFT')
            ->whereDate('issued_on', '<=', today())
            ->orderBy('issued_on')
            ->get();

        foreach ($factures as $facture) {
            $facture->update(['status' => 'SENT']);

            $envoi = $this->option('sans-envoi') ? null : EnvoiFacture::envoyer($facture);

            $this->line(sprintf('  %s envoyee%s', $facture->reference, $envoi ? ' -> '.$envoi : ''));
        }

        if ($factures->isNotEmpty()) {
            ActivityLog::record(
                'invoices.drafts_sent',
                $factures->count().' facture(s) en brouillon envoyée(s) à leur date d\'émission',
                null,
                ['factures' => $factures->pluck('reference')->all()],
            );
        }

        $this->info(sprintf('  %d brouillon(s) envoye(s).', $factures->count()));

        return self::SUCCESS;
    }
}
