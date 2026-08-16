<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'phone', 'role', 'is_active', 'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
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
}
