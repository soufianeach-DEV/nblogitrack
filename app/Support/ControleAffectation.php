<?php

namespace App\Support;

use App\Models\Driver;
use App\Models\TransportOrder;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Une seule source de verite pour le couple commande - vehicule -
 * chauffeur. L'affectation, la reaffectation, la prise en charge par le
 * chauffeur et les alertes de la planification posent les memes
 * questions : ces regles ne vivaient que dans PlanningController::assign,
 * et plus rien ne les reposait une fois la mission affectee.
 */
final class ControleAffectation
{
    /**
     * Jours ou la mission mobilise camion et chauffeur. En route, ou
     * quand l'enlevement prevu est passe (ou absent), elle les mobilise a
     * partir d'aujourd'hui : verifier les documents a une date revolue
     * laissait partir un chauffeur au permis expire.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: CarbonInterface} depart, premier jour, dernier jour
     */
    public static function periode(TransportOrder $ordre): array
    {
        // En route : de maintenant a la fin reelle du voyage, pas une
        // mission entiere recommencee aujourd'hui (fausses alertes sur un
        // conge qui commence apres la livraison).
        if ($ordre->status === 'IN_PROGRESS') {
            return [now(), today(), self::occupation($ordre)[1]];
        }

        $depart = $ordre->pickup_date;

        if ($depart === null || $depart->lt(today())) {
            $depart = now();
        }

        $debut = $depart->copy()->startOfDay();
        $fin = $debut->copy()->addDays(TempsDeConduite::journees($ordre->distance_km) - 1);

        return [$depart, $debut, $fin];
    }

    /**
     * Premier et dernier jour ou une mission engagee mobilise son camion
     * et son chauffeur. La meme regle sert aux chevauchements, au groupage,
     * aux temps de conduite et aux alertes :
     * - en route : du chargement (ou de l'enlevement prevu, si le chauffeur
     *   a charge la veille) a la fin du voyage, et au moins jusqu'a
     *   aujourd'hui tant que la livraison n'est pas enregistree ;
     * - affectee : a partir de l'enlevement prevu, ou d'aujourd'hui si cette
     *   date est passee ou absente (la mission n'est pas partie, elle partira).
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function occupation(TransportOrder $mission): array
    {
        $journees = TempsDeConduite::journees($mission->distance_km);

        if ($mission->status === 'IN_PROGRESS') {
            $departs = collect([$mission->picked_up_at, $mission->pickup_date])
                ->filter()
                ->map(fn ($d) => Carbon::instance($d)->startOfDay());
            $debut = $departs->min() ?? today();
            $fin = ($departs->max() ?? today())->copy()->addDays($journees - 1);

            return [$debut, $fin->lt(today()) ? today() : $fin];
        }

        $prevu = $mission->pickup_date?->copy()->startOfDay();
        $debut = $prevu !== null && $prevu->gte(today()) ? $prevu : today();

        return [$debut, $debut->copy()->addDays($journees - 1)];
    }

    /**
     * Le couple convient-il a la marchandise et aux documents, sur toute
     * la duree de la mission ? Aucune requete : utilisable pour chaque
     * mission affichee.
     *
     * @return list<array{champ: string, message: string}>
     */
    public static function conformite(TransportOrder $ordre, Vehicle $vehicule, Driver $chauffeur, CarbonInterface $fin): array
    {
        $refus = [];
        $debut = self::debut($ordre, $fin);

        if ($empechements = $chauffeur->empechements($fin, $vehicule)) {
            $refus[] = ['champ' => 'driver_id', 'message' => Traductions::t('msg.planif_chauffeur_empeche', 'Ce chauffeur ne peut pas prendre la route : :motifs.', ['motifs' => implode(', ', $empechements)])];
        }

        if ($absence = $chauffeur->indisponibleEntre($debut, $fin)) {
            $refus[] = ['champ' => 'driver_id', 'message' => Traductions::t('msg.planif_chauffeur_absent', 'Ce chauffeur est indisponible pendant la mission : :periode.', ['periode' => $absence->resume()])];
        }

        if ($immobilisation = $vehicule->indisponibleEntre($debut, $fin)) {
            $refus[] = ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_vehicule_immobilise', 'Ce véhicule est immobilisé pendant la mission : :periode.', ['periode' => $immobilisation->resume()])];
        }

        if ($vehicule->capacity_tonnes * 1000 < $ordre->weight) {
            $refus[] = ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_capacite', 'Capacité insuffisante : :capacite t pour :poids kg.', [
                'capacite' => Formats::nombre($vehicule->capacity_tonnes, 1),
                'poids' => Formats::nombre($ordre->weight),
            ])];
        }

        if ($ordre->volume !== null && $vehicule->capacity_volume !== null && (float) $vehicule->capacity_volume < (float) $ordre->volume) {
            $refus[] = ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_volume', 'Volume insuffisant : :capacite m³ pour :volume m³.', [
                'capacite' => Formats::nombre($vehicule->capacity_volume, 1),
                'volume' => Formats::nombre($ordre->volume, 1),
            ])];
        }

        if ($ordre->needs_tail_lift && ! $vehicule->has_tail_lift) {
            $refus[] = ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_hayon', 'Cette expédition demande un hayon élévateur : ce véhicule n\'en a pas.')];
        }

        if ($ordre->is_hazardous) {
            if ($motif = $chauffeur->motifAdr($fin)) {
                $refus[] = ['champ' => 'driver_id', 'message' => $chauffeur->adr_certified
                    ? Traductions::t('msg.planif_adr_certificat', 'Marchandise dangereuse : :motif.', ['motif' => $motif])
                    : Traductions::t('msg.planif_adr', 'Marchandise dangereuse : ce chauffeur n\'a pas la certification ADR.')];
            }

            if (! $vehicule->adr_equipe) {
                $refus[] = ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_adr_vehicule', 'Marchandise dangereuse : ce véhicule n\'est pas équipé ADR (plaques orange, extincteurs, lot de bord).')];
            }
        }

        // Le controle technique doit etre valable jusqu'au dernier jour de
        // la mission.
        if ($vehicule->inspection_valid_until !== null && $vehicule->inspection_valid_until->lt($fin)) {
            $refus[] = ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_controle_technique', 'Le contrôle technique de ce véhicule expire le :date, avant la fin de la mission.', [
                'date' => $vehicule->inspection_valid_until->format('d/m/Y'),
            ])];
        }

        if ($motif = $chauffeur->motifPermis($vehicule)) {
            $refus[] = ['champ' => 'driver_id', 'message' => Traductions::t('msg.planif_permis', 'Permis inadapté : :motif.', ['motif' => $motif])];
        }

        return $refus;
    }

    /**
     * Pourquoi ce vehicule ne convient pas a cette mission, en quelques
     * mots pour la liste de l'ecran de planification (le permis, qui
     * depend du couple, se compare a part).
     */
    public static function refusVehiculeCourt(TransportOrder $ordre, Vehicle $vehicule, CarbonInterface $fin): ?string
    {
        if ($immobilisation = $vehicule->indisponibleEntre(self::debut($ordre, $fin), $fin)) {
            return $immobilisation->resume();
        }

        return match (true) {
            $vehicule->capacity_tonnes * 1000 < $ordre->weight => Traductions::t('planif.capacite_insuffisante', 'capacité insuffisante'),
            $ordre->volume !== null && $vehicule->capacity_volume !== null && (float) $vehicule->capacity_volume < (float) $ordre->volume => Traductions::t('planif.volume_insuffisant', 'volume insuffisant'),
            $ordre->needs_tail_lift && ! $vehicule->has_tail_lift => Traductions::t('planif.sans_hayon', 'sans hayon'),
            $ordre->is_hazardous && ! $vehicule->adr_equipe => Traductions::t('planif.vehicule_non_adr', 'non équipé ADR'),
            $vehicule->inspection_valid_until !== null && $vehicule->inspection_valid_until->lt($fin) => Traductions::t('planif.controle_expire', 'contrôle technique jusqu\'au :date', ['date' => $vehicule->inspection_valid_until->format('d/m/Y')]),
            default => null,
        };
    }

    /**
     * Pourquoi ce chauffeur ne peut pas assurer cette mission, a la date
     * de la mission : l'ecran grisait d'apres les documents du jour, le
     * serveur refusait d'apres ceux du dernier jour.
     */
    public static function refusChauffeurCourt(TransportOrder $ordre, Driver $chauffeur, CarbonInterface $fin): ?string
    {
        if ($empechements = $chauffeur->empechementsDeBase($fin)) {
            return $empechements[0];
        }

        if ($absence = $chauffeur->indisponibleEntre(self::debut($ordre, $fin), $fin)) {
            return $absence->resume();
        }

        return $ordre->is_hazardous ? $chauffeur->motifAdr($fin) : null;
    }

    /**
     * Code 95 ou carte tachygraphe manquants : ils n'empechent que la
     * conduite d'un vehicule de plus de 3,5 t, l'ecran les applique
     * selon le camion choisi.
     */
    public static function refusChauffeurProfessionnel(Driver $chauffeur, CarbonInterface $fin): ?string
    {
        return $chauffeur->empechementsProfessionnels($fin)[0] ?? null;
    }

    /**
     * Premier jour controle d'une mission qui se termine a $fin : jamais
     * avant aujourd'hui (une absence passee n'empeche rien).
     */
    private static function debut(TransportOrder $ordre, CarbonInterface $fin): CarbonInterface
    {
        $debut = $fin->copy()->startOfDay()->subDays(TempsDeConduite::journees($ordre->distance_km) - 1);

        return $debut->lt(today()) ? today() : $debut;
    }

    /**
     * Camion et chauffeur sont-ils libres, et le camion peut-il encore
     * charger ? Chevauchements, charge cumulee du groupage, temps de
     * conduite.
     *
     * @return list<array{champ: string, message: string}>
     */
    public static function disponibilite(TransportOrder $ordre, Vehicle $vehicule, Driver $chauffeur, CarbonInterface $depart, CarbonInterface $debut, CarbonInterface $fin): array
    {
        $refus = [];

        // Le message nomme la mission qui bloque : une mission restee « en
        // route » faute de livraison enregistree se retrouve ainsi.
        if ($autre = self::missionsSurLaPeriode(TransportOrder::where('driver_id', $chauffeur->id)->where('vehicle_registration', '!=', $vehicule->registration), $debut, $fin, $ordre->id)->first()) {
            $refus[] = ['champ' => 'driver_id', 'message' => Traductions::t('msg.planif_chauffeur_occupe_par', 'Ce chauffeur a déjà une mission ce jour-là avec un autre camion (:mission).', ['mission' => $autre->tracking_number])];
        }

        // Le meme binome peut charger plusieurs envois le meme jour
        // (groupage), mais pas partir sur une autre mission pendant qu'il
        // roule encore : le deuxieme jour d'un Bruxelles-Lyon, il n'est pas
        // a Namur.
        $autresJours = TransportOrder::where('driver_id', $chauffeur->id)
            ->where('vehicle_registration', $vehicule->registration)
            ->where(fn ($q) => $q->whereNull('pickup_date')->orWhereDate('pickup_date', '!=', $debut->toDateString()));

        if ($autre = self::missionsSurLaPeriode($autresJours, $debut, $fin, $ordre->id)->first()) {
            $refus[] = ['champ' => 'driver_id', 'message' => Traductions::t('msg.planif_binome_en_route_par', 'Ce camion et ce chauffeur sont encore en route ce jour-là pour une autre mission (:mission).', ['mission' => $autre->tracking_number])];
        }

        if ($autre = self::missionsSurLaPeriode(TransportOrder::where('vehicle_registration', $vehicule->registration)->where('driver_id', '!=', $chauffeur->id), $debut, $fin, $ordre->id)->first()) {
            $refus[] = ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_camion_occupe_par', 'Ce camion est déjà affecté à un autre chauffeur ce jour-là (:mission).', ['mission' => $autre->tracking_number])];
        }

        // Groupage : la charge se verifie en cumul. Deux envois de 19,7 t
        // passaient chacun dans une semi de 26,8 t.
        $abord = self::missionsSurLaPeriode(TransportOrder::where('vehicle_registration', $vehicule->registration), $debut, $fin, $ordre->id);
        $poids = (float) $abord->sum('weight');

        if ($poids > 0 && $poids + (float) $ordre->weight > $vehicule->capacity_tonnes * 1000) {
            $refus[] = ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_charge_cumulee', 'Charge cumulée trop lourde : :abord kg déjà prévus à bord, plus :poids kg, pour :capacite t de charge utile.', [
                'abord' => Formats::nombre($poids),
                'poids' => Formats::nombre($ordre->weight),
                'capacite' => Formats::nombre($vehicule->capacity_tonnes, 1),
            ])];
        }

        $volume = (float) $abord->sum('volume');

        if ($volume > 0 && $ordre->volume !== null && $vehicule->capacity_volume !== null
            && $volume + (float) $ordre->volume > (float) $vehicule->capacity_volume) {
            $refus[] = ['champ' => 'vehicle_registration', 'message' => Traductions::t('msg.planif_volume_cumule', 'Volume cumulé trop grand : :abord m³ déjà prévus à bord, plus :volume m³, pour :capacite m³.', [
                'abord' => Formats::nombre($volume, 1),
                'volume' => Formats::nombre($ordre->volume, 1),
                'capacite' => Formats::nombre($vehicule->capacity_volume, 1),
            ])];
        }

        $conduite = TempsDeConduite::empechements($chauffeur->id, $ordre->distance_km, $depart, $ordre->id, $ordre, $vehicule->registration);

        if ($conduite !== []) {
            $refus[] = ['champ' => 'driver_id', 'message' => Traductions::t('msg.planif_temps_conduite', 'Temps de conduite : :motifs.', ['motifs' => implode(' ; ', $conduite)])];
        }

        return $refus;
    }

    /**
     * Tous les refus, conformite d'abord.
     *
     * @return list<array{champ: string, message: string}>
     */
    public static function refus(TransportOrder $ordre, Vehicle $vehicule, Driver $chauffeur): array
    {
        [$depart, $debut, $fin] = self::periode($ordre);

        return [
            ...self::conformite($ordre, $vehicule, $chauffeur, $fin),
            ...self::disponibilite($ordre, $vehicule, $chauffeur, $depart, $debut, $fin),
        ];
    }

    /**
     * Numeros des missions engagees de la requete dont le couple n'est
     * plus conforme : apres la correction d'une fiche chauffeur ou
     * vehicule, le planificateur doit savoir quoi reaffecter.
     *
     * @return list<string>
     */
    public static function missionsNonConformes($requete): array
    {
        return $requete->with(['vehicle', 'driver'])
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->get()
            ->filter(fn (TransportOrder $o) => $o->vehicle !== null && $o->driver !== null
                && self::conformite($o, $o->vehicle, $o->driver, self::periode($o)[2]) !== [])
            ->pluck('tracking_number')
            ->values()
            ->all();
    }

    /**
     * Une mission deja engagee occupe-t-elle un des jours [debut, fin] ?
     */
    public static function occupe($requete, CarbonInterface $debut, CarbonInterface $fin, ?int $exclu): bool
    {
        return self::missionsSurLaPeriode($requete, $debut, $fin, $exclu)->isNotEmpty();
    }

    /**
     * Les missions engagees dont la periode d'occupation croise
     * [debut, fin] (voir occupation()).
     */
    private static function missionsSurLaPeriode($requete, CarbonInterface $debut, CarbonInterface $fin, ?int $exclu)
    {
        return $requete->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->when($exclu !== null, fn ($q) => $q->where('id', '!=', $exclu))
            ->where(fn ($q) => $q->where('status', 'IN_PROGRESS')
                ->orWhereNull('pickup_date')
                ->orWhere('pickup_date', '<=', $fin->copy()->endOfDay()))
            ->get(['id', 'tracking_number', 'status', 'pickup_date', 'picked_up_at', 'assigned_at', 'distance_km', 'weight', 'volume'])
            ->filter(function (TransportOrder $mission) use ($debut, $fin) {
                [$premier, $dernier] = self::occupation($mission);

                return $premier->lte($fin) && $dernier->gte($debut);
            })
            ->values();
    }
}
