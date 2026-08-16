<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L'accuse de prise de connaissance de la note d'information.
 *
 * Le mot compte : ce n'est pas un consentement. Dans une relation de
 * travail, le consentement n'est pas librement donne et ne peut pas
 * fonder le traitement. La base legale reste l'execution du contrat et
 * l'interet legitime ; ceci ne prouve que l'information prealable.
 */
class DriverAcknowledgement extends Model
{
    /** Le slug de la note dans les pages administrees. */
    public const NOTE = 'information-chauffeurs';

    protected $fillable = ['user_id', 'version', 'acknowledged_at', 'ip_address'];

    protected function casts(): array
    {
        return [
            'version' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * La note en vigueur, ou null si l'administration n'en a pas encore
     * redige. Sans note, il n'y a rien a faire accuser.
     */
    public static function note(): ?Page
    {
        return Page::where('slug', self::NOTE)->first();
    }

    /**
     * Vrai quand ce conducteur a pris connaissance de la version en
     * cours. Une note reecrite invalide les accuses precedents : le
     * texte a change, la connaissance qu'on en avait aussi.
     */
    public static function aJour(int $utilisateur, ?Page $note = null): bool
    {
        $note ??= self::note();

        if ($note === null) {
            return false;
        }

        return self::where('user_id', $utilisateur)
            ->where('version', $note->updated_at)
            ->exists();
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
