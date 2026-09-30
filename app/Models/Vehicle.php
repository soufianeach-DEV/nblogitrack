<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use App\Support\Formats;
use App\Support\Traductions;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use DatesHeureDeBruxelles;
    use HasFactory;

    protected $table = 'vehicles';

    protected $primaryKey = 'registration';

    public $incrementing = false;

    protected $keyType = 'string';

    /** Categories de permis, de la plus petite a la plus grande. */
    public const PERMIS = ['B', 'C1', 'C1E', 'C', 'CE'];

    protected $fillable = [
        'registration', 'vin', 'vehicle_type', 'permis_requis', 'brand', 'model',
        'euro_standard', 'capacity_tonnes', 'capacity_volume', 'has_tail_lift', 'adr_equipe',
        'mileage', 'is_available', 'inspection_date', 'inspection_valid_until', 'fuel_type',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'has_tail_lift' => 'boolean',
            'adr_equipe' => 'boolean',
            'inspection_date' => 'date',
            'inspection_valid_until' => 'date',
        ];
    }

    /**
     * Le permis qu'exige ce vehicule : celui de sa fiche, sinon celui que
     * donne son gabarit. Une semi-remorque, ou une charge utile de
     * tracteur, exige le CE, quelle que soit la carrosserie.
     */
    public function permisRequis(): string
    {
        return $this->permis_requis ?? self::permisDeduit((string) $this->vehicle_type, (float) $this->capacity_tonnes);
    }

    public function indisponibilites(): HasMany
    {
        return $this->hasMany(Indisponibilite::class, 'vehicle_registration', 'registration');
    }

    /**
     * La premiere immobilisation (entretien, reparation...) qui croise la
     * periode.
     */
    public function indisponibleEntre(CarbonInterface $debut, CarbonInterface $fin): ?Indisponibilite
    {
        return $this->indisponibilites->first(fn (Indisponibilite $i) => $i->croise($debut, $fin));
    }

    /**
     * La plus lourde charge que la flotte sait porter, en kilos : au-dela,
     * aucune affectation ne sera jamais possible, autant le dire a la
     * commande (les camions au garage comptent, ils reviendront).
     */
    public static function chargeUtileMaxKg(): float
    {
        $max = (float) self::max('capacity_tonnes');

        return $max > 0 ? $max * 1000 : 44000.0;
    }

    /**
     * Le plus grand volume que la flotte sait charger, en m³.
     */
    public static function volumeMaxM3(): float
    {
        $max = (float) self::max('capacity_volume');

        return $max > 0 ? $max : 120.0;
    }

    /**
     * Ce que chaque camion de la flotte sait porter, sans doublons : le
     * formulaire de commande dit tout de suite si un envoi ADR avec hayon
     * de 20 t trouvera un camion.
     *
     * @return list<array{kg: float, m3: float|null, adr: bool, hayon: bool}>
     */
    public static function profils(): array
    {
        return self::query()
            ->select(['capacity_tonnes', 'capacity_volume', 'adr_equipe', 'has_tail_lift'])
            ->distinct()
            ->get()
            ->map(fn (Vehicle $v) => [
                'kg' => (float) $v->capacity_tonnes * 1000,
                'm3' => $v->capacity_volume === null ? null : (float) $v->capacity_volume,
                'adr' => (bool) $v->adr_equipe,
                'hayon' => (bool) $v->has_tail_lift,
            ])
            ->values()
            ->all();
    }

    /**
     * Un camion de la flotte (au garage compris : il reviendra) peut-il
     * prendre cet envoi ? Sinon, aucune affectation ne sera jamais
     * possible : autant le dire a la commande.
     */
    public static function peutPorter(float $kg, ?float $m3, bool $adr, bool $hayon): bool
    {
        // Sans flotte enregistree, rien a comparer (comme chargeUtileMaxKg).
        if (! self::query()->exists()) {
            return true;
        }

        return self::where('capacity_tonnes', '>=', $kg / 1000)
            ->when($m3 !== null, fn ($q) => $q->where(fn ($v) => $v->whereNull('capacity_volume')->orWhere('capacity_volume', '>=', $m3)))
            ->when($adr, fn ($q) => $q->where('adr_equipe', true))
            ->when($hayon, fn ($q) => $q->where('has_tail_lift', true))
            ->exists();
    }

    /**
     * Le message du refus, avec ce que l'envoi demande.
     */
    public static function refusFlotte(float $kg, ?float $m3, bool $adr, bool $hayon): string
    {
        $conditions = array_filter([
            Formats::nombre($kg).' kg',
            $m3 !== null ? Formats::nombre($m3, 1).' m³' : null,
            $adr ? Traductions::t('commande.cond_adr', 'équipement ADR') : null,
            $hayon ? Traductions::t('commande.cond_hayon', 'hayon élévateur') : null,
        ]);

        return Traductions::t('msg.flotte_incapable', 'Aucun camion de notre flotte ne réunit ces conditions (:conditions) : demandez un devis.', [
            'conditions' => implode(', ', $conditions),
        ]);
    }

    public static function permisDeduit(string $type, float $chargeUtile): string
    {
        return match (true) {
            $type === 'Semi-remorque' || $chargeUtile >= 18 => 'CE',
            $chargeUtile > 3.5 => 'C',
            $chargeUtile > 1.5 => 'C1',
            default => 'B',
        };
    }
}
