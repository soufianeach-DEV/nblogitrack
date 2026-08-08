<?php

namespace App\Models;

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

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
