<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $table = 'clients';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id', 'company_name', 'vat_number', 'enterprise_number', 'peppol_id',
        'billing_address', 'city', 'postal_code', 'country',
        'is_validated', 'business_sector', 'credit_limit', 'payment_terms',
        'validated_at', 'validated_by', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_validated' => 'boolean',
            'validated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class, 'client_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
