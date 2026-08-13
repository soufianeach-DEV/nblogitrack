<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $table = 'vehicles';

    protected $primaryKey = 'registration';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'registration', 'vin', 'vehicle_type', 'brand', 'model',
        'euro_standard', 'capacity_tonnes', 'capacity_volume', 'has_tail_lift',
        'mileage', 'is_available', 'inspection_date', 'fuel_type',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'has_tail_lift' => 'boolean',
            'inspection_date' => 'date',
        ];
    }
}
