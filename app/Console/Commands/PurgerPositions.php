<?php

namespace App\Console\Commands;

use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use Illuminate\Console\Command;

/**
 * Efface les positions intermediaires des expeditions livrees.
 *
 * Une position de route sert a informer un client pendant que sa
 * marchandise roule. Une fois livree, elle ne renseigne plus sur
 * l'envoi : elle ne renseigne plus que sur les deplacements d'un
 * conducteur un jour donne. La conserver serait constituer un
 * historique de travailleur, ce que rien ne justifie.
 *
 * Les jalons, eux, restent : ils disent ou la marchandise a ete prise et
 * remise, comme une mention de lettre de voiture.
 */
class PurgerPositions extends Command
{
    protected $signature = 'positions:purger {--jours=7 : Delai apres livraison} {--essai : Compter sans effacer}';

    protected $description = 'Efface les positions de route des expéditions livrées.';

    public function handle(): int
    {
        $jours = max(0, (int) $this->option('jours'));
        $limite = now()->subDays($jours);

        // Les expeditions concernees : livrees, et livrees depuis assez
        // longtemps. Une expedition encore en cours n'est jamais touchee.
        $livrees = TransportOrder::where('status', 'DELIVERED')
            ->whereNotNull('actual_delivery_date')
            ->where('actual_delivery_date', '<=', $limite)
            ->pluck('id');

        $requete = ShipmentPosition::where('type', ShipmentPosition::ROUTE)
            ->whereIn('transport_order_id', $livrees);

        $nombre = $requete->count();

        if ($this->option('essai')) {
            $this->line("  $nombre position(s) de route seraient effacées.");
            $this->line('  Expéditions livrées depuis plus de '.$jours.' jour(s) : '.$livrees->count());

            return self::SUCCESS;
        }

        $requete->delete();

        $this->info("  $nombre position(s) de route effacée(s).");
        $this->line('  Jalons conservés : '.ShipmentPosition::where('type', ShipmentPosition::JALON)->count());

        return self::SUCCESS;
    }
}
