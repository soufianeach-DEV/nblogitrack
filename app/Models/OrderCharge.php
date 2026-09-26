<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un supplement pose sur une expedition (temps d'attente, manutention,
 * peage exceptionnel...). Il part sur la facture du mois ou il a ete
 * saisi.
 */
class OrderCharge extends Model
{
    protected $fillable = ['transport_order_id', 'label', 'amount', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function transportOrder(): BelongsTo
    {
        return $this->belongsTo(TransportOrder::class);
    }

    public function invoiceLine(): HasOne
    {
        return $this->hasOne(InvoiceLine::class)->where('active', true);
    }
}
