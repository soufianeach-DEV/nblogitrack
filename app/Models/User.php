<?php

namespace App\Models;

use App\Models\Concerns\DatesHeureDeBruxelles;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use DatesHeureDeBruxelles, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'phone', 'role', 'is_active', 'locale',
        'client_id', 'company_role',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** La fiche chauffeur d'un compte chauffeur. */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    /** L'entreprise pour laquelle travaille un compte client. */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Passer et annuler des commandes. */
    public function peutCommander(): bool
    {
        return $this->isClient() && $this->client_id !== null
            && in_array($this->company_role, ['ADMIN', 'ORDERS'], true);
    }

    /** Voir et regler les factures de l'entreprise. */
    public function voitFacturesEntreprise(): bool
    {
        return $this->isClient() && $this->client_id !== null
            && in_array($this->company_role, ['ADMIN', 'BILLING'], true);
    }

    /** Gerer les comptes de l'entreprise. */
    public function gereEntreprise(): bool
    {
        return $this->isClient() && $this->client_id !== null && $this->company_role === 'ADMIN';
    }

    public function isClient(): bool
    {
        return $this->role === 'CLIENT';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['PLANNER', 'ADMIN']);
    }

    public function isPlanner(): bool
    {
        return $this->role === 'PLANNER';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isDriver(): bool
    {
        return $this->role === 'DRIVER';
    }

    /**
     * La langue choisie par l'utilisateur : ses notifications, dont le lien
     * de mot de passe, lui parviennent dans cette langue.
     */
    public function preferredLocale(): string
    {
        return $this->locale ?: 'fr';
    }
}
