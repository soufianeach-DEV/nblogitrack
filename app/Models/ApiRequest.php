<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le journal d'acces a l'API (A12).
 *
 * Les refus y figurent au meme titre que les appels servis : une cle
 * inconnue essayee vingt fois depuis la meme adresse est precisement ce
 * qu'un administrateur doit pouvoir voir.
 */
class ApiRequest extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'api_key_id', 'method', 'path', 'status', 'ip_address', 'duration_ms', 'refus',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Les motifs de refus, pour l'ecran d'administration. */
    public const MOTIFS = [
        'jeton_absent' => 'Aucune clé présentée',
        'cle_inconnue' => 'Clé inconnue',
        'revoquee' => 'Clé révoquée',
        'expiree' => 'Clé expirée',
        'adresse_refusee' => 'Adresse IP non autorisée',
        'permission_absente' => 'Permission absente',
    ];

    public function cle(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }
}
