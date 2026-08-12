<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const STATUTS = [
        'DRAFT' => 'Brouillon',
        'SENT' => 'Envoyée',
        'PAID' => 'Payée',
        'OVERDUE' => 'En retard',
    ];

    public const TAUX_TVA = 21.00;

    protected $fillable = [
        'client_id', 'reference', 'issued_on', 'due_on', 'period_start', 'period_end',
        'amount_excl_tax', 'vat_rate', 'vat_amount', 'amount_incl_tax',
        'reverse_charge', 'status', 'paid_on', 'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_on' => 'date',
            'reverse_charge' => 'boolean',
            'amount_excl_tax' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'amount_incl_tax' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function estEnRetard(): bool
    {
        return $this->status !== 'PAID' && $this->due_on->isPast();
    }
}
