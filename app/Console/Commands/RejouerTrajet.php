<?php

namespace App\Console\Commands;

use App\Models\DriverAcknowledgement;
use App\Models\ShipmentPosition;
use App\Models\TransportOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Rejoue un trajet le long de son itineraire reel, pour une
 * demonstration.
 *
 * Un camion ne roule pas pendant une soutenance. Cette commande depose
 * donc des positions le long du trace que le service d'itineraire rend
 * pour cette expedition : le meme trace que celui deja dessine sur la
 * carte. Rien n'est invente, un trajet enregistre est rejoue.
 *
 * Elle vit dans les commandes et porte « demo » dans son nom : ce n'est
 * pas une fonctionnalite de l'application, et personne ne doit la
 * prendre pour telle.
 *
 * Elle ne contourne aucun garde-fou. Le suivi doit avoir ete ouvert
 * pour la mission, la mission doit etre en cours, et le conducteur doit
 * avoir pris connaissance de la note. La commande refuse comme le
 * serveur refuserait, et dit ce qui manque.
 */
class RejouerTrajet extends Command
{
    protected $signature = 'demo:trajet
        {numero : Le numero de suivi, par exemple TRK-2024-00223}
        {--pas=12 : Nombre de positions a deposer sur le trajet}
        {--intervalle=5 : Secondes entre deux positions}
        {--effacer : Retirer les positions de route deposees, sans rien deposer}';

    protected $description = 'Rejoue un trajet le long de son itinéraire, pour une démonstration.';

    public function handle(): int
    {
        $ordre = TransportOrder::where('tracking_number', $this->argument('numero'))->first();

        if ($ordre === null) {
            $this->error('  Aucune expédition ne porte ce numéro.');

            return self::FAILURE;
        }

        if ($this->option('effacer')) {
            $efaces = ShipmentPosition::where('transport_order_id', $ordre->id)
                ->where('type', ShipmentPosition::ROUTE)->delete();

            $this->info("  $efaces position(s) de route retirée(s) pour ".$ordre->tracking_number.'.');

            return self::SUCCESS;
        }

        if ($manque = $this->cequiManque($ordre)) {
            $this->error('  '.$manque);

            return self::FAILURE;
        }

        $trace = $this->itineraire($ordre);

        if ($trace === []) {
            $this->error('  Le service d\'itinéraire n\'a rien rendu pour cette expédition.');

            return self::FAILURE;
        }

        $pas = max(2, (int) $this->option('pas'));
        $intervalle = max(0, (int) $this->option('intervalle'));

        $this->line('  '.$ordre->tracking_number.' — '.count($trace).' points d\'itinéraire, '
            .$pas.' positions déposées toutes les '.$intervalle.' s.');
        $this->line('  Ouvrez le suivi de cette expédition pour voir le marqueur avancer.');
        $this->newLine();

        $barre = $this->output->createProgressBar($pas);
        $barre->start();

        for ($i = 0; $i < $pas; $i++) {
            // On avance regulierement le long du trace plutot que par
            // point : un itineraire compte des milliers de points, tres
            // serres dans les villes et espaces sur autoroute.
            $rang = (int) round($i * (count($trace) - 1) / ($pas - 1));
            [$lat, $lng] = $trace[$rang];

            ShipmentPosition::create([
                'transport_order_id' => $ordre->id,
                'driver_id' => $ordre->driver_id,
                'type' => ShipmentPosition::ROUTE,
                'lat' => $lat,
                'lng' => $lng,
                'precision_m' => 20,
                'recorded_at' => now(),
            ]);

            $barre->advance();

            if ($intervalle > 0 && $i < $pas - 1) {
                sleep($intervalle);
            }
        }

        $barre->finish();
        $this->newLine(2);
        $this->info('  Trajet rejoué. « demo:trajet '.$ordre->tracking_number.' --effacer » retire ces positions.');

        return self::SUCCESS;
    }

    /**
     * Ce qui empeche la demonstration, dit dans les memes termes que le
     * serveur refuserait a un vrai conducteur.
     */
    private function cequiManque(TransportOrder $ordre): ?string
    {
        if ($ordre->status !== 'IN_PROGRESS') {
            return 'Cette expédition est en état '.$ordre->status.' : le suivi ne relève que pendant une mission en cours.';
        }

        if ($ordre->driver_id === null) {
            return 'Aucun conducteur n\'est affecté à cette expédition.';
        }

        if (! $ordre->suivi_direct) {
            return 'Le suivi de position n\'est pas ouvert pour cette mission. Ouvrez-le depuis la planification.';
        }

        if (! DriverAcknowledgement::aJour($ordre->driver_id)) {
            return 'Le conducteur n\'a pas pris connaissance de la note d\'information : aucune position ne serait relevée.';
        }

        if ($ordre->pickup_lat === null || $ordre->delivery_lat === null) {
            return 'Cette expédition n\'a pas de coordonnées d\'enlèvement ou de livraison.';
        }

        return null;
    }

    /**
     * Le trace routier, demande au meme service que la carte de suivi.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function itineraire(TransportOrder $ordre): array
    {
        try {
            $reponse = Http::timeout(20)->get(sprintf(
                'https://router.project-osrm.org/route/v1/driving/%s,%s;%s,%s',
                $ordre->pickup_lng, $ordre->pickup_lat, $ordre->delivery_lng, $ordre->delivery_lat,
            ), ['overview' => 'full', 'geometries' => 'geojson']);

            $points = $reponse->ok() ? $reponse->json('routes.0.geometry.coordinates') : null;

            if (is_array($points) && $points !== []) {
                // GeoJSON ordonne longitude puis latitude.
                return array_map(fn (array $p) => [(float) $p[1], (float) $p[0]], $points);
            }
        } catch (\Throwable $e) {
            $this->warn('  Service d\'itinéraire injoignable : '.$e->getMessage());
        }

        return [];
    }
}
