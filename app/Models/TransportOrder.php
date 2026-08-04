<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportOrder extends Model
{
    use HasFactory;

    protected $table = 'transport_orders';

    protected $fillable = [
        'client_id', 'created_date', 'pickup_address', 'delivery_address',
        'weight', 'volume', 'goods_type', 'is_hazardous', 'status', 'priority',
        'tracking_number', 'special_instructions', 'requested_delivery_date',
        'actual_delivery_date', 'estimated_cost', 'tariff_grid_id',
    ];

    protected function casts(): array
    {
        return [
            'is_hazardous' => 'boolean',
            'created_date' => 'date',
            'requested_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
        ];
    }
}