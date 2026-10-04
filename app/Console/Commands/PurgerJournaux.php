<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\ApiKeyRequest;
use App\Models\ApiRequest;
use App\Models\Client;
use App\Models\PageView;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Support\Audience;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
        // commande et s'efface avec elle (pieces:purger) ; sans commande
        // rattachee, elle redevient une demande sans suite.
        $devis = QuoteRequest::where('created_at', '<', now()->subYears(2))
            ->where(fn ($q) => $q->whereIn('status', ['PENDING', 'PROCESSING', 'QUOTED', 'CLOSED'])
                ->orWhere(fn ($q) => $q->where('status', 'ORDERED')->whereNull('converted_order_id')));
        $nombreDevis = $devis->count();

        // Inscription refusee : six mois (le temps d'une contestation),
        // puis l'entreprise et ses comptes s'effacent.
        $refusees = Client::whereNotNull('rejection_reason')
            ->where('is_validated', false)
            ->where('validated_at', '<', now()->subMonths(6)); // date de la decision de refus
        $nombreRefusees = $refusees->count();

        // Cle d'API revoquee ou expiree depuis la meme duree, et dont le
        // journal ne garde plus aucun appel : elle ne sert plus a rien. Une
        // demande d'acces traitee (accordee ou refusee) suit cette duree.
        $cles = fn () => ApiKey::where(fn ($q) => $q->where('revoked_at', '<', $limite)->orWhere('expires_at', '<', $limite))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('api_requests')
                ->whereColumn('api_requests.api_key_id', 'api_keys.id')
                ->where('api_requests.created_at', '>=', $limite));
        $demandes = ApiKeyRequest::where('status', '!=', ApiKeyRequest::EN_ATTENTE)->where('handled_at', '<', $limite);
        $nombreCles = $cles()->count();
        $nombreDemandes = $demandes->count();

        // Mesure d'audience : treize mois au plus.
        $vues = PageView::where('jour', '<', now()->subMonths(Audience::CONSERVATION)->toDateString());
        $nombreVues = $vues->count();

        if ($this->option('essai')) {
            $this->line("  $nombre entrée(s) antérieures au ".$limite->format('d/m/Y').' seraient effacées.');
            $this->line('  Total actuel : '.ActivityLog::count());
            $this->line("  $nombreAppels appel(s) d'API seraient effacés.");
            $this->line("  $nombreDevis demande(s) de devis sans suite seraient effacées.");
            $this->line("  $nombreVues ligne(s) de mesure d'audience seraient effacées.");
            $this->line("  $nombreRefusees inscription(s) refusée(s) seraient effacées.");
            $this->line("  $nombreCles clé(s) d'API révoquée(s) ou expirée(s) et $nombreDemandes demande(s) d'accès traitée(s) seraient effacées.");

            return self::SUCCESS;
        }

        $requete->delete();
        $appels->delete();
        $cles()->delete();
        $demandes->delete();
        // Les fichiers d'abord : une ligne effacee ne dirait plus ou ils sont.
        $devis->clone()->select(['id', 'reference'])->chunkById(200, function ($lot) {
            foreach ($lot as $demande) {
                Storage::disk('local')->deleteDirectory('devis/'.$demande->reference);
            }
        });
        $devis->delete();
        $vues->delete();

        $refusees->clone()->pluck('id')->each(function (int $id) {
            User::withTrashed()->where('client_id', $id)->forceDelete();
            Client::withTrashed()->whereKey($id)->forceDelete();
        });

        $this->info("  $nombre entrée(s) effacée(s), $nombreAppels appel(s) d'API, $nombreCles clé(s) et $nombreDemandes demande(s) d'accès, $nombreDevis demande(s) de devis, $nombreVues ligne(s) d'audience, $nombreRefusees inscription(s) refusée(s).");
        $this->line('  Reste : '.ActivityLog::count().' entrée(s), la plus ancienne du '
            .(ActivityLog::min('created_at') ?? '—'));

        return self::SUCCESS;
    }
}
