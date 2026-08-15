<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Une cle d'acces a l'API REST (A12).
 *
 * La valeur en clair n'existe qu'une fois, au moment de la generation :
 * la base ne garde que son empreinte. C'est le meme raisonnement que
 * pour un mot de passe, et il a la meme consequence — une cle perdue ne
 * se retrouve pas, elle se remplace.
 */
class ApiKey extends Model
{
    /** Ce qu'une cle peut faire. « Acces limite » se dit ici lecture seule. */
    public const PERMISSIONS = [
        'lecture' => 'Lecture',
        'ecriture' => 'Écriture',
    ];

    /** Le prefixe commun a toutes les cles : reconnaissable dans un journal. */
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
     * Fabrique une cle et rend sa valeur en clair, une seule fois.
     *
     * @return array{0: self, 1: string}
     */
    public static function generer(array $attributs): array
    {
        // Le prefixe identifie la cle, le secret l'authentifie. Les deux
        // voyagent ensemble mais seul le prefixe est conserve lisible.
        $prefixe = self::MARQUE.'_'.Str::lower(Str::random(7));
        $secret = Str::random(40);

        $cle = self::create([
            ...$attributs,
            'prefix' => $prefixe,
            'token_hash' => hash('sha256', $secret),
        ]);

        return [$cle, $prefixe.'.'.$secret];
    }

    /**
     * Retrouve une cle a partir de la valeur presentee.
     *
     * La comparaison passe par hash_equals : une comparaison ordinaire
     * s'arrete au premier caractere different, ce qui laisse mesurer le
     * secret au chronometre.
     */
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

    /** Le motif de refus, ou null si la cle est utilisable. */
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

    /** Une liste vide vaut « aucune restriction », choix explicite. */
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
