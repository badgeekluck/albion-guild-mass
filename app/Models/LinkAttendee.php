<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's registration for a party (slot_index = null means waitlist).
 */
class LinkAttendee extends Model
{
    use HasFactory;

    public const ROLE_FILL = 'Fill';

    public const ROLE_FILL_TANK = 'Fill Tank';

    public const ROLE_FILL_DPS = 'Fill DPS';

    public const ROLE_BOMBSQUAD = 'Bombsquad';

    public const SPECIAL_ROLES = [self::ROLE_FILL, self::ROLE_FILL_TANK, self::ROLE_FILL_DPS, self::ROLE_BOMBSQUAD];

    public const FILL_ROLES = [self::ROLE_FILL, self::ROLE_FILL_TANK, self::ROLE_FILL_DPS];

    /** Player took an open slot and waits for the caller (CTA). */
    public const CLAIM_CLAIMED = 'claimed';

    /** Placed by a caller, or confirmed claim. */
    public const CLAIM_CONFIRMED = 'confirmed';

    protected $fillable = [
        'user_id',
        'in_game_name',
        'note',
        'main_role',
        'main_role_id',
        'second_role',
        'second_role_id',
        'third_role',
        'third_role_id',
        'fourth_role',
        'fourth_role_id',
    ];

    protected $casts = [
        'slot_index' => 'integer',
        'is_forced' => 'boolean',
        'claim_locked' => 'boolean',
        'attended' => 'boolean',
        'main_role_id' => 'integer',
        'second_role_id' => 'integer',
        'third_role_id' => 'integer',
        'fourth_role_id' => 'integer',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(SharedLink::class, 'shared_link_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Role preferences in priority order.
     *
     * @return list<array{name: string, id: int|null}>
     */
    public function rolePreferences(): array
    {
        $roles = [];
        foreach (['main', 'second', 'third', 'fourth'] as $prefix) {
            $name = $this->{$prefix.'_role'};
            if ($name) {
                $roles[] = ['name' => $name, 'id' => $this->{$prefix.'_role_id'}];
            }
        }

        return $roles;
    }

    /** @return list<string> */
    public function roleNames(): array
    {
        return array_column($this->rolePreferences(), 'name');
    }

    public function displayName(): string
    {
        return $this->in_game_name ?: ($this->user->name ?? 'Unknown');
    }

    public function isClaimPending(): bool
    {
        return $this->slot_index !== null && $this->claim_status === self::CLAIM_CLAIMED;
    }

    public function isConfirmed(): bool
    {
        return $this->slot_index !== null && $this->claim_status !== self::CLAIM_CLAIMED;
    }

    public function isBombsquad(): bool
    {
        return $this->main_role === self::ROLE_BOMBSQUAD;
    }

    public function hasSpecialMainRole(): bool
    {
        return in_array($this->main_role, self::SPECIAL_ROLES, true);
    }
}
