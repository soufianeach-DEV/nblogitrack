<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequest extends Model
{
    use DatesHeureDeBruxelles;

    public const UPDATED_AT = null;

    protected $fillable = [
        'api_key_id', 'method', 'path', 'status', 'ip_address', 'duration_ms', 'refus',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public const MOTIFS = [
        'jeton_absent' => 'Aucune clé présentée',
        'cle_inconnue' => 'Clé inconnue',
        'revoquee' => 'Clé révoquée',
        'expiree' => 'Clé expirée',
        'adresse_refusee' => 'Adresse IP non autorisée',
        'permission_absente' => 'Permission absente',
        'entreprise_inactive' => 'Entreprise non validée ou désactivée',
    ];

    public function cle(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }
}
