<?php

namespace App\Models;

use App\Support\Pays;
use App\Support\Traductions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TariffGrid extends Model
{
    use HasFactory;

    protected $table = 'tariff_grids';

    protected $fillable = [
        'label', 'zone', 'base_rate', 'price_per_km', 'price_per_kg',
        'adr_coefficient', 'is_active',
    ];

    /*
     * Le libelle enregistre (« Export France — Standard ») est en francais :
     * l'interface, le suivi et les courriels le recomposent dans la langue
     * du lecteur, a partir de la zone et du niveau de service.
     */
    protected $appends = ['libelle'];

    public function getLibelleAttribute(): string
    {
        if ($this->zone === null || $this->service_level === null) {
            return (string) $this->label;
        }

        $zone = $this->zone === 'BE'
            ? Traductions::t('grille.national', 'National (BE)')
            : Traductions::t('grille.export', 'Export :pays', ['pays' => Pays::libelle($this->zone) ?? $this->zone]);

        return $zone.' — '.Traductions::t('commande.offre_'.strtolower($this->service_level), ucfirst(strtolower($this->service_level)));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
