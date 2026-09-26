<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
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
