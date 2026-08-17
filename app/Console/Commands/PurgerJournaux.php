<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class PurgerJournaux extends Command
{
    protected $signature = 'journaux:purger {--mois=12 : Duree de conservation} {--essai : Compter sans effacer}';

    protected $description = 'Efface les entrées du journal d\'activité passé leur durée de conservation.';

    public function handle(): int
    {
        $mois = max(1, (int) $this->option('mois'));
        $limite = now()->subMonths($mois);

        $requete = ActivityLog::where('created_at', '<', $limite);
        $nombre = $requete->count();

        if ($this->option('essai')) {
            $this->line("  $nombre entrée(s) antérieures au ".$limite->format('d/m/Y').' seraient effacées.');
            $this->line('  Total actuel : '.ActivityLog::count());

            return self::SUCCESS;
        }

        $requete->delete();

        $this->info("  $nombre entrée(s) effacée(s).");
        $this->line('  Reste : '.ActivityLog::count().' entrée(s), la plus ancienne du '
            .(ActivityLog::min('created_at') ?? '—'));

        return self::SUCCESS;
    }
}
