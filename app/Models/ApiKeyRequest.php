<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande d'acces a l'API faite par une entreprise cliente. Accordee, elle
 * garde la cle chiffree jusqu'a ce que le client l'affiche une seule fois.
 */
class ApiKeyRequest extends Model
{
    use DatesHeureDeBruxelles;

    public const EN_ATTENTE = 'PENDING';

    public const ACCORDEE = 'GRANTED';

    public const REFUSEE = 'REFUSED';

    protected $fillable = [
        'client_id', 'requested_by', 'abilities', 'allowed_ips', 'message',
        'status', 'refusal_reason', 'handled_by', 'handled_at',
        'api_key_id', 'key_ciphertext', 'revealed_at',
    ];

    protected $hidden = ['key_ciphertext'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'allowed_ips' => 'array',
            'key_ciphertext' => 'encrypted',
            'handled_at' => 'datetime',
            'revealed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function cle(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }

    public function enAttente(): bool
    {
        return $this->status === self::EN_ATTENTE;
    }

    /** La cle accordee attend encore d'etre affichee au client. */
    public function cleAAfficher(): bool
    {
        return $this->status === self::ACCORDEE && $this->key_ciphertext !== null;
    }
}
