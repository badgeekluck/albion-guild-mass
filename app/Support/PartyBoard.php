<?php

namespace App\Support;

use App\Models\GameRole;
use App\Models\LinkAttendee;
use App\Models\PartyTemplate;
use App\Models\SavedBuild;
use App\Models\SharedLink;
use App\Models\SlotHistory;
use App\Models\User;
use App\Services\Albion\GuildMemberDirectory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the party screen needs, computed once in PHP instead of inside Blade.
 */
class PartyBoard
{
    public const TYPE_LABELS = [
        'tank' => ['label' => 'Tank', 'icon' => '🛡️'],
        'heal' => ['label' => 'Healer', 'icon' => '🩹'],
        'dps' => ['label' => 'DPS', 'icon' => '⚔️'],
        'supp' => ['label' => 'Support', 'icon' => '🖐️'],
        'mount' => ['label' => 'Mount', 'icon' => '🐘'],
        'any' => ['label' => 'Flex', 'icon' => '⚪'],
    ];

    /** @var array<int, array<string, mixed>> */
    public array $slots = [];

    /** @var list<array{number: int, start: int, end: int, filled: int, total: int}> */
    public array $parties = [];

    /** @var Collection<int, LinkAttendee> */
    public Collection $waitlist;

    /** @var list<array{type: string, label: string, icon: string, filled: int, total: int}> */
    public array $summary = [];

    public bool $canArrange;

    public bool $canManage;

    public bool $canSaveTemplate;

    public ?LinkAttendee $myAttendance;

    public int $inPartyCount;

    /** The viewer may claim open slots. */
    public bool $canClaim;

    public int $pendingClaimCount = 0;

    /** @var array<string, true>|null lower-cased guild member names (callers only; null = unknown) */
    private ?array $memberNames = null;

    /** @var array<string, true> lower-cased IGNs of this party that other Discord accounts have used too */
    private array $sharedIgns = [];

    /** @return Collection<int, PartyTemplate> templates a manager can (re)apply */
    public function templateOptions()
    {
        return $this->canManage && $this->link->acceptsChanges()
            ? PartyTemplate::orderBy('name')->get(['id', 'name', 'size'])
            : collect();
    }

    /** Other parties at the same time the viewer is registered to. */
    public EloquentCollection $overlapping;

    public function __construct(public SharedLink $link, public ?User $viewer)
    {
        $link->loadMissing('attendees.user');
        $this->overlapping = $viewer ? $link->overlappingFor($viewer) : new EloquentCollection;

        $this->canArrange = (bool) $viewer?->can('arrange', $link);
        $this->canManage = (bool) $viewer?->can('manage', $link);
        $this->canSaveTemplate = (bool) $viewer?->can('saveAsTemplate', $link);

        if ($this->canArrange) {
            $names = app(GuildMemberDirectory::class)->names($link->regear_include_alliance);
            $this->memberNames = $names === null ? null : array_fill_keys(array_map('mb_strtolower', $names), true);
            $this->sharedIgns = $this->findSharedIgns($link);
        }

        $attendees = $link->attendees;
        $bySlot = $attendees->whereNotNull('slot_index')->keyBy('slot_index');
        $this->waitlist = $attendees->whereNull('slot_index')->sortBy('created_at')->values();
        $this->myAttendance = $viewer ? $attendees->firstWhere('user_id', $viewer->id) : null;

        $maxSlots = $link->maxSlots();
        $this->inPartyCount = $bySlot->filter(fn ($a) => $a->slot_index <= $maxSlots)->count();
        $this->pendingClaimCount = $bySlot->filter(fn (LinkAttendee $a) => $a->isClaimPending())->count();

        // In a CTA a caller-confirmed player keeps their slot until they release it.
        $lockedByCaller = $this->myAttendance && ! $link->isContent() && $this->myAttendance->isConfirmed();
        $this->canClaim = $viewer !== null && $link->acceptsRegistrations() && ! $lockedByCaller
            && ! $this->myAttendance?->claim_locked && ! $link->isBlocked($viewer);

        $builds = $this->loadBuilds($link);
        $situation = $link->activeSituation();
        $summary = [];

        for ($i = 1; $i <= $maxSlots; $i++) {
            $definition = $link->slotDefinition($i);
            /** @var LinkAttendee|null $attendee */
            $attendee = $bySlot->get($i);
            $isMe = $attendee && $viewer && $attendee->user_id === $viewer->id;

            $type = $definition['type'];
            $summaryType = $definition['is_extra'] ? 'any' : $type;
            $summary[$summaryType]['total'] = ($summary[$summaryType]['total'] ?? 0) + 1;
            $summary[$summaryType]['filled'] = ($summary[$summaryType]['filled'] ?? 0) + ($attendee ? 1 : 0);

            $classes = ['role-'.($type === 'any' ? 'any' : $type)];
            if ($definition['is_extra'] || $attendee?->isBombsquad()) {
                $classes = ['role-bombsquad'];
            }
            if ($isMe) {
                $classes[] = 'my-own-slot';
            }
            if (! $attendee) {
                $classes[] = 'is-empty-slot';
            }

            $build = $definition['build_id'] ? ($builds[$definition['build_id']] ?? null) : null;
            $activeSwaps = $situation && $build
                ? array_values(array_filter($build['swaps'], fn ($swap) => $swap['situation'] === $situation->value))
                : [];
            if ($activeSwaps) {
                $classes[] = 'swap-active';
            }

            $this->slots[$i] = [
                'index' => $i,
                'definition' => $definition,
                'label' => $definition['role'] !== '' ? $definition['role'] : 'Any',
                'build' => $build,
                'active_swaps' => $activeSwaps,
                'swap_summary' => implode(', ', array_map(
                    fn ($swap) => trim(($swap['slot_label'] ? $swap['slot_label'].' → ' : '').($swap['item']['name'] ?? $swap['text'])),
                    $activeSwaps,
                )),
                'attendee' => $attendee,
                'is_me' => $isMe,
                'classes' => implode(' ', $classes),
                'can_drag' => $attendee && $this->canDrag($attendee),
                'claimable' => ! $attendee && $this->canClaim,
                'claim_pending' => (bool) $attendee?->isClaimPending(),
                'can_decide' => $attendee && $attendee->isClaimPending() && $this->canArrange && $link->acceptsChanges(),
                'can_bench' => $attendee && ! $attendee->isClaimPending() && $this->canArrange && $link->acceptsChanges(),
                'can_release' => $isMe && $link->acceptsChanges(),
                'show_warning' => $attendee
                    && ! $definition['is_extra']
                    && $attendee->is_forced
                    && ! $attendee->hasSpecialMainRole()
                    && ($this->canArrange || $isMe),
            ];
        }

        for ($start = 1, $n = 1; $start <= $maxSlots; $start += SharedLink::PARTY_SIZE, $n++) {
            $end = min($start + SharedLink::PARTY_SIZE - 1, $maxSlots);
            $this->parties[] = [
                'number' => $n,
                'start' => $start,
                'end' => $end,
                'filled' => $bySlot->filter(fn ($a) => $a->slot_index >= $start && $a->slot_index <= $end)->count(),
                'total' => $end - $start + 1,
            ];
        }

        foreach (self::TYPE_LABELS as $type => $meta) {
            if (! empty($summary[$type]['total'])) {
                $this->summary[] = $meta + ['type' => $type] + $summary[$type];
            }
        }
    }

    /** For callers: the IGN is not in the guild member list, so regear won't find this player's deaths. */
    public function isUnknownMember(LinkAttendee $attendee): bool
    {
        return $this->memberNames !== null && $attendee->in_game_name
            && ! isset($this->memberNames[mb_strtolower($attendee->in_game_name)]);
    }

    /** For callers: this character name was also used by another Discord account (any party). */
    public function isSharedIgn(LinkAttendee $attendee): bool
    {
        return isset($this->sharedIgns[mb_strtolower((string) $attendee->in_game_name)]);
    }

    /** @return array<string, true> */
    private function findSharedIgns(SharedLink $link): array
    {
        $igns = $link->attendees->pluck('in_game_name')->filter()->map(fn ($n) => mb_strtolower($n))->unique()->values()->all();
        if (! $igns) {
            return [];
        }

        // Registrations plus slot history (it survives kicks and leaves).
        $pairs = LinkAttendee::query()->whereIn(DB::raw('LOWER(in_game_name)'), $igns)->get(['in_game_name', 'user_id'])
            ->concat(SlotHistory::query()->whereIn(DB::raw('LOWER(in_game_name)'), $igns)->whereNotNull('user_id')->get(['in_game_name', 'user_id']));

        return $pairs->groupBy(fn ($p) => mb_strtolower($p->in_game_name))
            ->filter(fn ($group) => $group->pluck('user_id')->unique()->count() > 1)
            ->map(fn () => true)
            ->all();
    }

    public function canDrag(LinkAttendee $attendee): bool
    {
        if (! $this->viewer || ! $this->link->acceptsChanges()) {
            return false;
        }

        return $this->canArrange || ($this->link->isContent() && $attendee->user_id === $this->viewer->id && ! $attendee->claim_locked);
    }

    public function maxSlots(): int
    {
        return $this->link->maxSlots();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadBuilds(SharedLink $link): array
    {
        $builds = $link->resolveBuilds()->values();
        if ($builds->isEmpty()) {
            return [];
        }
        $builds = (new EloquentCollection($builds->all()))->load(array_keys(SavedBuild::SLOT_CATEGORIES));

        // Load every swap item once instead of per build.
        $swapItemIds = $builds->flatMap(fn (SavedBuild $b) => array_column(array_filter((array) $b->swaps, 'is_array'), 'item_id'))->filter()->unique();
        $swapItems = $swapItemIds->isEmpty() ? collect() : GameRole::whereIn('id', $swapItemIds)->get()->keyBy('id');

        return $builds->mapWithKeys(fn (SavedBuild $build) => [$build->id => $build->toModalArray($swapItems)])->all();
    }
}
