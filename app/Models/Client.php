<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use HasFactory;

    protected $table = 'clients';

    public $timestamps = false;

    protected $fillable = [
        'company_name', 'vat_number', 'enterprise_number', 'peppol_id',
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

    // Les roles d'un compte au sein de son entreprise.
    public const ROLES = ['ADMIN', 'ORDERS', 'BILLING'];

    /** Les comptes qui travaillent pour l'entreprise. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class)->orderBy('id');
    }

    /** L'administrateur principal : le premier compte administrateur. */
    public function user(): HasOne
    {
        return $this->hasOne(User::class)->where('company_role', 'ADMIN')->orderBy('id');
    }

    public function compte(): ?User
    {
        return $this->user()->first();
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
