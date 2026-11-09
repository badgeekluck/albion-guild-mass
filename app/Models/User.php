<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CREATOR = 'content-creator';

    public const ROLE_USER = 'user';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_CREATOR, self::ROLE_USER];

    public const STAFF_ROLES = [self::ROLE_ADMIN, self::ROLE_CREATOR];

    /** Discord IDs of passwordless local test accounts start with this prefix. */
    public const DEV_DISCORD_PREFIX = 'dev-';

    /**
     * "role" is intentionally not fillable: it is only changed via `php artisan app:set-role`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'discord_id',
        'avatar',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(LinkAttendee::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Admins and content creators (callers). */
    public function isStaff(): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }

    public function isDevAccount(): bool
    {
        return str_starts_with((string) $this->discord_id, self::DEV_DISCORD_PREFIX);
    }
}
