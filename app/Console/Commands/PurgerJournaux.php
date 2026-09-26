<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\ApiRequest;
use App\Models\PageView;
use App\Models\QuoteRequest;
use App\Support\Audience;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

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

        // Le journal d'acces a l'API garde lui aussi des adresses IP, et
        // chaque appel refuse, meme anonyme, y ajoute une ligne. Il suit la
        // meme duree de conservation.
        $appels = ApiRequest::where('created_at', '<', $limite);
        $nombreAppels = $appels->count();

        // Registre RGPD : une demande de devis restee sans suite (devis
        // transmis sans reponse compris) se garde deux ans, puis s'efface
        // avec ses pieces jointes. Transformee en commande, elle suit la
        // commande : cinq ans.
        $devis = QuoteRequest::where(fn ($q) => $q
            ->where(fn ($q) => $q->whereIn('status', ['PENDING', 'PROCESSING', 'QUOTED', 'CLOSED'])
                ->where('created_at', '<', now()->subYears(2)))
            ->orWhere(fn ($q) => $q->where('status', 'ORDERED')
                ->where('created_at', '<', now()->subYears(5))));
        $nombreDevis = $devis->count();

        // Mesure d'audience : treize mois au plus.
        $vues = PageView::where('jour', '<', now()->subMonths(Audience::CONSERVATION)->toDateString());
        $nombreVues = $vues->count();

        if ($this->option('essai')) {
            $this->line("  $nombre entrée(s) antérieures au ".$limite->format('d/m/Y').' seraient effacées.');
            $this->line('  Total actuel : '.ActivityLog::count());
            $this->line("  $nombreAppels appel(s) d'API seraient effacés.");
            $this->line("  $nombreDevis demande(s) de devis sans suite seraient effacées.");
            $this->line("  $nombreVues ligne(s) de mesure d'audience seraient effacées.");

            return self::SUCCESS;
        }

        $requete->delete();
        $appels->delete();
        // Les fichiers d'abord : une ligne effacee ne dirait plus ou ils sont.
        $devis->clone()->select(['id', 'reference'])->chunkById(200, function ($lot) {
            foreach ($lot as $demande) {
                Storage::disk('local')->deleteDirectory('devis/'.$demande->reference);
            }
        });
        $devis->delete();
        $vues->delete();

        $this->info("  $nombre entrée(s) effacée(s), $nombreAppels appel(s) d'API, $nombreDevis demande(s) de devis, $nombreVues ligne(s) d'audience.");
        $this->line('  Reste : '.ActivityLog::count().' entrée(s), la plus ancienne du '
            .(ActivityLog::min('created_at') ?? '—'));

        return self::SUCCESS;
    }
}
