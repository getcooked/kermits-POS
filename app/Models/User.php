<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CASHIER = 'cashier';

    public const ROLE_CUSTOMER = 'customer';

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'role',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function homeRoute(): string
    {
        return match ($this->role) {
            self::ROLE_CUSTOMER => route('shop'),
            self::ROLE_CASHIER => route('cashier'),
            self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN => route('dashboard'),
            default => route('home'),
        };
    }
}
