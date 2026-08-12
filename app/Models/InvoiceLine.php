<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'invoice_id', 'transport_order_id', 'description', 'amount_excl_tax',
    ];

    protected function casts(): array
    {
        return ['amount_excl_tax' => 'decimal:2'];
    }

    

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }


    public function transportOrder(): BelongsTo
    {
        return $this->belongsTo(TransportOrder::class);
    }

}