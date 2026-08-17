<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    public const PERMISSIONS = [
        'lecture' => 'Lecture',
        'ecriture' => 'Écriture',
    ];

    public const MARQUE = 'nblt';

    protected $fillable = [
        'name', 'prefix', 'token_hash', 'client_id', 'abilities',
        'allowed_ips', 'expires_at', 'revoked_at', 'created_by',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'allowed_ips' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return array{0: self, 1: string}
     */
    public static function generer(array $attributs): array
    {
        $prefixe = self::MARQUE.'_'.Str::lower(Str::random(7));
        $secret = Str::random(40);

        $cle = self::create([
            ...$attributs,
            'prefix' => $prefixe,
            'token_hash' => hash('sha256', $secret),
        ]);

        return [$cle, $prefixe.'.'.$secret];
    }

    public static function depuisJeton(string $jeton): ?self
    {
        if (! str_contains($jeton, '.')) {
            return null;
        }

        [$prefixe, $secret] = explode('.', $jeton, 2);

        $cle = self::where('prefix', $prefixe)->first();

        if ($cle === null || ! hash_equals($cle->token_hash, hash('sha256', $secret))) {
            return null;
        }

        return $cle;
    }

    public function empechement(?string $ip, string $permission): ?string
    {
        if ($this->revoked_at !== null) {
            return 'revoquee';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expiree';
        }

        if (! $this->autoriseIp($ip)) {
            return 'adresse_refusee';
        }

        if (! in_array($permission, $this->abilities ?? [], true)) {
            return 'permission_absente';
        }

        return null;
    }

    public function autoriseIp(?string $ip): bool
    {
        $autorisees = $this->allowed_ips ?? [];

        return $autorisees === [] || ($ip !== null && in_array($ip, $autorisees, true));
    }

    public function estActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appels(): HasMany
    {
        return $this->hasMany(ApiRequest::class);
    }
}
