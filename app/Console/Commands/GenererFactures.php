<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Support\EnvoiFacture;
use App\Support\Facturier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenererFactures extends Command
{
    protected $signature = 'factures:generer
                            {--mois= : Le mois a facturer, au format AAAA-MM. Par defaut, le mois ecoule.}
                            {--tout : Facture tout ce qui reste, sans limite de mois.}
                            {--essai : Montre ce qui serait emis, sans rien ecrire.}
                            {--sans-envoi : Emet les factures sans les envoyer par courriel.}';

    protected $description = 'Emet une facture par client et par mois pour les transports livres.';

    public function handle(Facturier $facturier): int
    {
        $periode = match (true) {
            $this->option('tout') => null,
            $this->option('mois') !== null => Carbon::createFromFormat('Y-m-d', $this->option('mois').'-01'),
            default => now()->subMonth(),
        };

        $this->line($periode === null
            ? '  Toutes les livraisons non facturees.'
            : '  Mois facture : '.$periode->format('m/Y'));

        $groupes = $facturier->aFacturer($periode);

        if ($groupes->isEmpty()) {
            $this->info('  Rien a facturer.');

            return self::SUCCESS;
        }

        $expeditions = $groupes->sum(fn ($g) => $g->count());
        $montant = $groupes->sum(fn ($g) => $g->sum('estimated_cost'));

        $this->line(sprintf('  %d facture(s) a emettre, %d expedition(s), %s EUR hors TVA.',
            $groupes->count(), $expeditions, number_format($montant, 2, ',', ' ')));

        if ($this->option('essai')) {
            foreach ($groupes as $cle => $lot) {
                [$clientId, $mois] = explode('|', (string) $cle);
                $this->line(sprintf('    client %-4s %s  %d expedition(s)',
                    $clientId, $mois, $lot->count()));
            }

            $this->comment('  Essai : rien n\'a ete ecrit.');

            return self::SUCCESS;
        }

        $emises = $facturier->facturer($periode);
        $envoyer = ! $this->option('sans-envoi');
        $echecs = 0;

        foreach ($emises as $facture) {
            // Une facture emise est deja enregistree : si son courriel ne
            // part pas, on le note et on passe a la suivante. Elle se
            // renvoie ensuite depuis son ecran.
            $envoi = '';

            if ($envoyer && $facture->status === 'SENT') {
                $destinataire = EnvoiFacture::envoyer($facture);
                $envoi = $destinataire === null ? '  ENVOI ECHOUE' : '  -> '.$destinataire;
                $echecs += $destinataire === null ? 1 : 0;
            }

            $this->line(sprintf('    %s  client %-4s %s EUR%s',
                $facture->reference, $facture->client_id,
                number_format((float) $facture->amount_incl_tax, 2, ',', ' '), $envoi));
        }

        ActivityLog::record(
            'invoices.generated',
            $emises->count().' facture(s) émise(s)'.($periode ? ' pour '.$periode->format('m/Y') : ''),
            null,
            ['factures' => $emises->pluck('reference')->all()],
        );

        $this->info(sprintf('  %d facture(s) emise(s).', $emises->count()));

        if ($echecs > 0) {
            $this->warn(sprintf('  %d courriel(s) non envoye(s) : renvoyez-les depuis l\'ecran de la facture.', $echecs));
        }

        return self::SUCCESS;
    }
}
