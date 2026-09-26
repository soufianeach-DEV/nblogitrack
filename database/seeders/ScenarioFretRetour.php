<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Driver;
use App\Models\TariffGrid;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use App\Support\Chronologie;
use App\Support\ControleAffectation;
use App\Support\FretRetour;
use App\Support\Tarificateur;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Un exemple de fret retour, date par rapport a aujourd'hui : un aller
 * Bruxelles -> Lyon affecte, un import Villeurbanne -> Anvers vendu au
 * tarif fret retour sur son retour, et un import Lille -> Liege sans camion
 * a proximite (depart du depot a prevoir).
 */
final class ScenarioFretRetour
{
    public function __invoke(): void
    {
        // Le fichier SQL insere ses identifiants : la sequence reprend apres.
        DB::statement("SELECT setval('transport_orders_id_seq', (SELECT COALESCE(MAX(id), 1) FROM transport_orders))");

        $lundi = today()->addDays(2)->next(Carbon::MONDAY);
        $clients = Client::orderBy('id')->take(3)->get();

        if ($clients->count() < 3) {
            return;
        }

        $aller = $this->ordre($clients[0], [
            'pickup_address' => 'Rue Haute 100, 1000 Bruxelles, Belgique',
            'delivery_address' => 'Rue de la République 1, 69002 Lyon, France',
            'pickup_country' => 'BE', 'delivery_country' => 'FR',
            'pickup_lat' => 50.8504, 'pickup_lng' => 4.3488, 'delivery_lat' => 45.764, 'delivery_lng' => 4.8357,
            'pickup_date' => $lundi->copy()->setTime(7, 0),
            'weight' => 6000, 'goods_type' => 'Machines',
        ]);

        if (! $this->affecter($aller)) {
            return;
        }

        $this->ordre($clients[1], [
            'pickup_address' => 'Rue Francis de Pressensé 10, 69100 Villeurbanne, France',
            'delivery_address' => 'Noorderlaan 100, 2030 Antwerpen, Belgique',
            'pickup_country' => 'FR', 'delivery_country' => 'BE',
            'pickup_lat' => 45.7719, 'pickup_lng' => 4.8902, 'delivery_lat' => 51.2199, 'delivery_lng' => 4.4035,
            'pickup_date' => $lundi->copy()->addDays(2)->setTime(9, 0),
            'weight' => 8000, 'goods_type' => 'Palettes',
            'shipper_name' => 'Expéditeur démo Villeurbanne', 'shipper_phone' => '+33 4 72 00 00 00',
        ]);

        $this->ordre($clients[2], [
            'pickup_address' => 'Rue Nationale 1, 59000 Lille, France',
            'delivery_address' => 'Boulevard d\'Avroy 1, 4000 Liège, Belgique',
            'pickup_country' => 'FR', 'delivery_country' => 'BE',
            'pickup_lat' => 50.6292, 'pickup_lng' => 3.0573, 'delivery_lat' => 50.6326, 'delivery_lng' => 5.5797,
            'pickup_date' => $lundi->copy()->addDays(3)->setTime(9, 0),
            'weight' => 2500, 'goods_type' => 'Textile',
            'shipper_name' => 'Expéditeur démo Lille', 'shipper_phone' => '+33 3 20 00 00 00',
        ]);
    }

    /** L'ordre, tarife comme le ferait le formulaire de commande. */
    private function ordre(Client $client, array $champs): TransportOrder
    {
        $demande = new TransportOrder(['client_id' => $client->id, ...$champs]);
        $trajet = $demande->trajet();
        $km = Tarificateur::distanceVol($champs['pickup_lat'], $champs['pickup_lng'], $champs['delivery_lat'], $champs['delivery_lng']) * 1.3;
        $demande->distance_km = (int) round($km);
        $offre = Tarificateur::offre($trajet, $demande, $km);
        $grille = TariffGrid::where('zone', $trajet->zone())->where('service_level', 'STANDARD')->where('is_active', true)->firstOrFail();

        return TransportOrder::deposer([
            ...$champs,
            'client_id' => $client->id,
            'created_date' => now(),
            'status' => 'PENDING',
            'priority' => 'NORMAL',
            'tariff_grid_id' => $grille->id,
            'distance_km' => (int) round($km),
            'approche_km' => $champs['pickup_country'] === 'BE' ? null : (int) round($offre['porteuse'] !== null
                ? FretRetour::approche($offre['porteuse'], $champs['pickup_lat'], $champs['pickup_lng'])
                : Chronologie::kmDepuisDepot($champs['pickup_lat'], $champs['pickup_lng'])),
            'estimated_cost' => $offre['prix'][$grille->id],
            'pricing_basis' => $offre['base'],
            'backhaul_order_id' => $offre['porteuse']?->id,
            'tracking_code' => TransportOrder::prochainCode(),
        ]);
    }

    /** Le premier binome que l'ecran de planification accepterait. */
    private function affecter(TransportOrder $ordre): bool
    {
        $camions = Vehicle::with('indisponibilites')->where('is_available', true)->where('vehicle_type', 'Semi-remorque')->orderBy('registration')->get();
        $chauffeurs = Driver::with(['user', 'indisponibilites'])->where('is_available', true)->where('license_type', 'CE')->orderBy('id')->get()
            ->filter(fn (Driver $d) => $d->user?->is_active);

        foreach ($camions as $camion) {
            foreach ($chauffeurs as $chauffeur) {
                if (ControleAffectation::refus($ordre, $camion, $chauffeur) === []) {
                    $ordre->forceFill([
                        'status' => 'ASSIGNED',
                        'vehicle_registration' => $camion->registration,
                        'driver_id' => $chauffeur->id,
                        'assigned_at' => now(),
                    ])->saveQuietly();

                    return true;
                }
            }
        }

        return false;
    }
}
