<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoice extends Model
{
    public const CATEGORIES = [
        'CARBURANT' => 'Carburant',
        'PEAGE' => 'Péages et taxe kilométrique',
    ];

    public const STATUTS = [
        'TO_PAY' => 'À payer',
        'PAID' => 'Payée',
    ];

    protected $fillable = [
        'supplier_name', 'reference', 'category', 'vehicle_registration',
        'period_start', 'period_end', 'issued_on', 'due_on',
        'liters', 'taxed_km',
        'amount_excl_tax', 'vat_rate', 'vat_amount', 'amount_incl_tax',
        'vat_deductible', 'status', 'paid_on',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
            'paid_on' => 'date',
            'vat_deductible' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_registration', 'registration');
    }
}
