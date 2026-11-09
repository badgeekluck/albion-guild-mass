<?php

namespace App\Models;

use App\Enums\Situation;
use App\Services\SlotAssigner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A party (event). Called "shared link" for historical reasons.
 */
class SharedLink extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const TYPE_CTA = 'cta';

    public const TYPE_CONTENT = 'content';

    public const TYPES = [self::TYPE_CTA, self::TYPE_CONTENT];

    /** Slot count when a party is created without a template. */
    public const DEFAULT_SLOTS = 20;

    public const PARTY_SIZE = 20;

    /** Upper bound for template + extra slots (15 parties). */
    public const MAX_TOTAL_SLOTS = 300;

    public const DEFAULT_REGISTRATION_HOURS = 8;

    /** Slot labels that accept any player. */
    public const WILDCARD_ROLES = ['', 'any', 'flex', 'empty slot', 'bomb squad / flex'];

    protected $fillable = [
        'creator_id',
        'title',
        'briefing',
        'type',
        'expires_at',
        'template_snapshot',
        'party_template_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'regear_rules' => 'array',
        'regear_scanned_at' => 'datetime',
        'blocked_user_ids' => 'array',
        'regear_min_battle_size' => 'integer',
        'regear_include_alliance' => 'boolean',
        'template_snapshot' => 'array',
        'build_snapshots' => 'array',
        'battle_players' => 'array',
        'extra_slots' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'type' => self::TYPE_CTA,
        'extra_slots' => 0,
        'regear_include_alliance' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (SharedLink $link) {
            if (empty($link->slug)) {
                $link->slug = static::generateUniqueSlug();
            }
            if (empty($link->expires_at)) {
                $link->expires_at = now()->addHours(self::DEFAULT_REGISTRATION_HOURS);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function generateUniqueSlug(int $length = 8): string
    {
        do {
            $slug = Str::random($length);
        } while (static::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(LinkAttendee::class);
    }

    public function regearClaims(): HasMany
    {
        return $this->hasMany(RegearClaim::class);
    }

    /** Regear role types: slot types that get a payout amount. */
    public const REGEAR_TYPES = ['tank', 'heal', 'supp', 'dps', 'mount', 'any'];

    /** Roles with their own minimum tiers. */
    public const TIER_ROLES = ['tank', 'heal', 'supp', 'dps'];

    /** Gear groups with a minimum tier (the offhand follows the weapon). */
    public const TIER_SLOTS = ['weapon' => 'Silah', 'head' => 'Kafa', 'armor' => 'Göğüslük', 'shoe' => 'Ayakkabı'];

    /** Tier-equivalents (tier + enchant): 8.4 = 12. */
    public const TIER_RANGE = [4, 5, 6, 7, 8, 9, 10, 11, 12];

    /**
     * Payout per role and minimum tier-equivalent per role and gear slot, with defaults.
     *
     * min_tier = ['default' => 7, 'tank' => ['default' => null, 'weapon' => 7, 'head' => null, 'armor' => 9, 'shoe' => null], ...]
     *
     * limits = ['max_per_player' => ?int, 'min_item_power' => ?int] (null = no limit)
     *
     * @return array{min_tier: array<string, mixed>, payouts: array<string, int>, limits: array{max_per_player: int|null, min_item_power: int|null}}
     */
    public function regearRules(): array
    {
        $rules = self::mergeRegearRules(self::defaultRegearRules(), (array) Setting::get('regear', []));

        // A party may override the guild defaults for one event.
        return self::mergeRegearRules($rules, (array) $this->regear_rules);
    }

    /** Smallest battle (players on all sides) counted as part of the event: by default the party size. */
    public function minBattleSize(): int
    {
        return $this->regear_min_battle_size ?: $this->maxSlots();
    }

    public function hasRegearOverride(): bool
    {
        return ! empty($this->regear_rules);
    }

    public static function defaultRegearRules(): array
    {
        return [
            'min_tier' => self::normalizeMinTier([]),
            'payouts' => array_fill_keys(self::REGEAR_TYPES, 0),
            'limits' => self::normalizeLimits([]),
        ];
    }

    /** @return array{max_per_player: int|null, min_item_power: int|null} */
    public static function normalizeLimits(array $limits): array
    {
        $positive = fn ($value) => is_numeric($value) && (int) $value > 0 ? (int) $value : null;

        return [
            'max_per_player' => $positive($limits['max_per_player'] ?? null),
            'min_item_power' => $positive($limits['min_item_power'] ?? null),
        ];
    }

    /**
     * Minimum tiers and limits are replaced as a whole set (a saved form is complete), payouts per key.
     */
    public static function mergeRegearRules(array $base, array $override): array
    {
        if (isset($override['min_tier'])) {
            $base['min_tier'] = self::normalizeMinTier((array) $override['min_tier']);
        }
        if (isset($override['limits'])) {
            $base['limits'] = self::normalizeLimits((array) $override['limits']);
        }
        foreach ((array) ($override['payouts'] ?? []) as $key => $value) {
            if (array_key_exists($key, $base['payouts']) && $value !== null) {
                $base['payouts'][$key] = (int) $value;
            }
        }

        return $base;
    }

    /** Accepts the old shape (role => int) as well as role => [slot => tier]. */
    public static function normalizeMinTier(array $minTier): array
    {
        $tier = fn ($value) => in_array((int) $value, self::TIER_RANGE, true) ? (int) $value : null;
        $normalized = ['default' => $tier($minTier['default'] ?? null) ?? 7];

        foreach (self::TIER_ROLES as $role) {
            $value = $minTier[$role] ?? null;
            $roleRules = is_array($value) ? $value : ['default' => $value];
            $normalized[$role] = ['default' => $tier($roleRules['default'] ?? null)];
            foreach (array_keys(self::TIER_SLOTS) as $slot) {
                $normalized[$role][$slot] = $tier($roleRules[$slot] ?? null);
            }
        }

        return $normalized;
    }

    /**
     * Minimum tier-equivalent for a role and gear slot: slot rule, else role default, else guild default.
     */
    public function minTierFor(?string $type, string $slot = 'weapon'): int
    {
        $rules = $this->regearRules()['min_tier'];
        $slot = $slot === 'offhand' ? 'weapon' : $slot;
        $role = $type !== null && isset($rules[$type]) ? $rules[$type] : [];

        return (int) ($role[$slot] ?? $role['default'] ?? $rules['default']);
    }

    public function payoutFor(?string $type): int
    {
        return (int) ($this->regearRules()['payouts'][$type ?? 'any'] ?? 0);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function activeSituation(): ?Situation
    {
        return Situation::tryFrom((string) $this->situation);
    }

    /** Another Discord account already registered this character name in the party. */
    public function ignTakenByOther(string $ign, int $userId): bool
    {
        return $this->attendees()
            ->whereRaw('LOWER(in_game_name) = ?', [mb_strtolower(trim($ign))])
            ->where('user_id', '!=', $userId)
            ->exists();
    }

    public const IGN_TAKEN_MESSAGE = 'This character is already registered in this party by another player.';

    /** Kicked by a caller: may not sign up to this party again. */
    public function isBlocked(?User $user): bool
    {
        return $user !== null && in_array($user->id, (array) $this->blocked_user_ids, true);
    }

    public function isContent(): bool
    {
        return $this->type === self::TYPE_CONTENT;
    }

    public function registrationClosed(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Roster changes (moves, slot counts) are allowed until the event is finished. */
    public function acceptsChanges(): bool
    {
        return ! $this->isCompleted();
    }

    /** New sign-ups need an active event whose registration window is still open. */
    public function acceptsRegistrations(): bool
    {
        return $this->acceptsChanges() && ! $this->registrationClosed();
    }

    /**
     * Template slots keyed 1..N (older snapshots may be 0-indexed lists).
     *
     * @return array<int, array<string, mixed>>
     */
    public function templateSlots(): array
    {
        $snapshot = $this->template_snapshot;
        if (! is_array($snapshot) || $snapshot === []) {
            return [];
        }

        if (array_is_list($snapshot)) {
            return array_combine(range(1, count($snapshot)), $snapshot);
        }

        ksort($snapshot);

        return $snapshot;
    }

    /**
     * Other open parties at the same time (by event window) that the user is registered to.
     * Parties without an event time can't be compared and are ignored.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SharedLink>
     */
    public function overlappingFor(User $user)
    {
        if (! $this->starts_at || ! $this->ends_at) {
            return $this->newCollection();
        }

        return self::query()
            ->whereKeyNot($this->getKey())
            ->where('status', '!=', self::STATUS_COMPLETED)
            ->where('starts_at', '<', $this->ends_at)
            ->where('ends_at', '>', $this->starts_at)
            ->whereHas('attendees', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('starts_at')
            ->get(['id', 'slug', 'title', 'starts_at', 'ends_at']);
    }

    /** Short notice for flash messages, or null. */
    public function overlapNotice(User $user): ?string
    {
        $others = $this->overlappingFor($user);

        return $others->isEmpty() ? null
            : ' ⚠️ Aynı saatte '.$others->map(fn (SharedLink $l) => "\"{$l->title}\"")->implode(', ').' partisine de kayıtlısın.';
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PartyTemplate::class, 'party_template_id');
    }

    /** Template edits reach this party automatically: open and not started yet. */
    public function followsTemplateEdits(): bool
    {
        return ! $this->isCompleted() && (! $this->starts_at || $this->starts_at->isFuture());
    }

    /**
     * Replace the slots and briefing with the template's current version. Players stay in
     * their slot (their role label is re-evaluated); slots that no longer exist send their
     * players to the waitlist.
     *
     * @return int players moved to the waitlist
     */
    public function applyTemplate(PartyTemplate $template): int
    {
        return DB::transaction(function () use ($template) {
            $locked = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'party_template_id' => $template->id,
                'template_snapshot' => $template->structure,
                'briefing' => $template->briefing,
            ])->save();

            $benched = 0;
            $assigner = app(SlotAssigner::class);
            foreach ($locked->attendees()->whereNotNull('slot_index')->get() as $attendee) {
                if ($attendee->slot_index > $locked->maxSlots()) {
                    $attendee->forceFill(['slot_index' => null, 'assigned_role' => null, 'is_forced' => false, 'claim_status' => null])->save();
                    $benched++;
                } else {
                    $attendee->forceFill(array_intersect_key($assigner->assign($attendee, $locked, $attendee->slot_index), array_flip(['assigned_role', 'is_forced'])))->save();
                }
            }

            $this->setRawAttributes($locked->getAttributes(), true);
            $this->unsetRelation('attendees');

            return $benched;
        });
    }

    /** Killboard data for this event exists (a regear scan ran). */
    public function hasBattlePlayers(): bool
    {
        return is_array($this->battle_players);
    }

    /** @return array{name: string, battles: int, kills: int, deaths: int}|null */
    public function battlePlayer(?string $ign): ?array
    {
        return $ign === null ? null : ($this->battle_players[mb_strtolower($ign)] ?? null);
    }

    /**
     * Players seen fighting in the event's battles attended, even if they sat on the waitlist.
     * Never marks anyone absent: the killboard only lists players who killed, died or assisted.
     *
     * @return int number of players newly marked as attended
     */
    public function applyBattleAttendance(): int
    {
        if (! $this->hasBattlePlayers()) {
            return 0;
        }

        $marked = 0;
        foreach ($this->attendees()->where('attended', false)->get() as $attendee) {
            if ($this->battlePlayer($attendee->in_game_name)) {
                $attendee->forceFill(['attended' => true])->save();
                $marked++;
            }
        }

        return $marked;
    }

    /** @return list<array{name: string, battles: int, kills: int, deaths: int}> fought in the event but never signed up */
    public function unregisteredFighters(): array
    {
        $roster = $this->attendees->map(fn ($a) => mb_strtolower((string) $a->in_game_name))->flip();

        return array_values(array_filter((array) $this->battle_players, fn ($player, $key) => ! $roster->has($key), ARRAY_FILTER_USE_BOTH));
    }

    /** Build ids used by the template slots. @return list<int> */
    public function buildIds(): array
    {
        return collect($this->templateSlots())->pluck('build_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** The event is over: its builds are frozen when they get edited or deleted. */
    public function eventIsOver(): bool
    {
        return $this->isCompleted() || ($this->ends_at && $this->ends_at->isPast());
    }

    /**
     * Builds of this party: the frozen version if the build changed after the event, the live one otherwise.
     *
     * @param  list<int>|null  $ids  defaults to every build of the template
     * @return Collection<int, SavedBuild> keyed by build id
     */
    public function resolveBuilds(?array $ids = null): Collection
    {
        $ids ??= $this->buildIds();
        $frozen = (array) $this->build_snapshots;
        $builds = collect();

        foreach ($ids as $id) {
            if (isset($frozen[$id])) {
                $builds[$id] = (new SavedBuild)->newFromBuilder($frozen[$id]);
            }
        }

        $live = array_values(array_diff($ids, $builds->keys()->all()));
        if ($live) {
            SavedBuild::whereIn('id', $live)->get()->each(fn (SavedBuild $b) => $builds[$b->id] = $b);
        }

        return $builds;
    }

    /** Keep the given version of a build on this party (first version wins: the one used at the event). */
    public function freezeBuild(int $buildId, array $rawAttributes): void
    {
        $frozen = (array) $this->build_snapshots;
        if (isset($frozen[$buildId]) || ! in_array($buildId, $this->buildIds(), true)) {
            return;
        }

        $frozen[$buildId] = $rawAttributes;
        $this->forceFill(['build_snapshots' => $frozen])->saveQuietly();
    }

    public function templateSlotCount(): int
    {
        $count = count($this->templateSlots());

        return $count > 0 ? $count : self::DEFAULT_SLOTS;
    }

    public function maxSlots(): int
    {
        return $this->templateSlotCount() + (int) $this->extra_slots;
    }

    public function isExtraSlot(int $index): bool
    {
        return $index > $this->templateSlotCount();
    }

    /**
     * @return array{index: int, role: string, note: string, type: string, build_id: int|null, is_extra: bool}
     */
    public function slotDefinition(int $index): array
    {
        if ($this->isExtraSlot($index)) {
            return [
                'index' => $index,
                'role' => 'Bomb Squad / Flex',
                'note' => 'Flexible Slot',
                'type' => 'dps',
                'build_id' => null,
                'is_extra' => true,
            ];
        }

        $data = $this->templateSlots()[$index] ?? [];
        $type = strtolower((string) ($data['type'] ?? 'any'));

        return [
            'index' => $index,
            'role' => trim((string) ($data['role'] ?? '')),
            'note' => trim((string) ($data['note'] ?? '')),
            'type' => in_array($type, PartyTemplate::SLOT_TYPES, true) ? $type : 'any',
            'build_id' => ! empty($data['build_id']) ? (int) $data['build_id'] : null,
            'is_extra' => false,
        ];
    }

    public static function isWildcardRole(?string $role): bool
    {
        return in_array(mb_strtolower(trim((string) $role)), self::WILDCARD_ROLES, true);
    }
}
