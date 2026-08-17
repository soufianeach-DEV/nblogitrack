<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverAcknowledgement extends Model
{
    public const NOTE = 'information-chauffeurs';

    protected $fillable = ['user_id', 'version', 'acknowledged_at', 'ip_address'];

    protected function casts(): array
    {
        return [
            'version' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public static function note(): ?Page
    {
        return Page::where('slug', self::NOTE)->first();
    }

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
