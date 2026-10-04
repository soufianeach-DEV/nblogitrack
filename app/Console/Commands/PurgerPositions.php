<?php

namespace App\Console\Commands;

use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use Illuminate\Console\Command;

class PurgerPositions extends Command
{
    protected $signature = 'positions:purger {--jours=7 : Delai apres livraison} {--jalons=365 : Delai avant d\'effacer les jalons} {--essai : Compter sans effacer}';

    protected $description = 'Efface les positions de route des expéditions livrées, puis leurs jalons un an plus tard.';

    public function handle(): int
    {
        $jours = max(0, (int) $this->option('jours'));
        $limite = now()->subDays($jours);

        // Une expedition annulee en cours de route a aussi des positions :
        // elles suivent la meme duree que celles d'une livraison, sinon
        // elles restaient indefiniment.
        $livrees = TransportOrder::where(fn ($q) => $q
            ->where(fn ($l) => $l->where('status', 'DELIVERED')
                ->whereNotNull('actual_delivery_date')
                ->where('actual_delivery_date', '<=', $limite))
            ->orWhere(fn ($a) => $a->where('status', 'CANCELLED')
                ->where('updated_at', '<=', $limite))
            // Livree sans date enregistree : la derniere mise a jour fait foi.
            ->orWhere(fn ($l) => $l->where('status', 'DELIVERED')
                ->whereNull('actual_delivery_date')
                ->where('updated_at', '<=', $limite))
            // Restee « en route » un mois (livraison jamais declaree) : les
            // positions n'ont plus d'usage et ne se gardent pas sans fin.
            ->orWhere(fn ($r) => $r->where('status', 'IN_PROGRESS')
                ->where('updated_at', '<=', now()->subDays(30))))
            ->pluck('id');

        $requete = ShipmentPosition::where('type', ShipmentPosition::ROUTE)
            ->whereIn('transport_order_id', $livrees);

        // Les jalons (enlevement, livraison) prouvent le passage du
        // chauffeur pendant le delai ordinaire de prescription de la
        // convention CMR (art. 32 : un an). Ensuite, l'ordre garde l'heure,
        // le receptionnaire et les reserves, sans la position du chauffeur.
        $jalons = ShipmentPosition::where('type', ShipmentPosition::JALON)
            ->whereIn('transport_order_id', TransportOrder::whereIn('status', ['DELIVERED', 'CANCELLED'])
                ->whereRaw('COALESCE(delivered_at, actual_delivery_date, cancelled_at, updated_at) <= ?', [now()->subDays(max(0, (int) $this->option('jalons')))])
                ->select('id'));

        $nombre = $requete->count();
        $nombreJalons = $jalons->count();

        if ($this->option('essai')) {
            $this->line("  $nombre position(s) de route seraient effacées.");
            $this->line('  Expéditions livrées depuis plus de '.$jours.' jour(s) : '.$livrees->count());
            $this->line("  $nombreJalons jalon(s) de plus d'un an seraient effacés.");

            return self::SUCCESS;
        }

        $requete->delete();
        $jalons->delete();

        $this->info("  $nombre position(s) de route et $nombreJalons jalon(s) effacé(s).");
        $this->line('  Jalons conservés : '.ShipmentPosition::where('type', ShipmentPosition::JALON)->count());

        return self::SUCCESS;
    }
}
