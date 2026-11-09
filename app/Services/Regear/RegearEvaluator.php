<?php

namespace App\Services\Regear;

use App\Models\GameRole;
use App\Models\LinkAttendee;
use App\Models\RegearClaim;
use App\Models\SavedBuild;
use App\Models\Setting;
use App\Models\SharedLink;
use App\Models\SlotHistory;
use App\Services\Albion\ItemType;

/**
 * Compares what a player died with (killboard) against what their slot required
 * and suggests a payout. The caller always has the final say on the review screen.
 *
 * Bag, mount, potion and food are neither checked nor paid.
 */
class RegearEvaluator
{
    /** Killboard equipment key => build slot. */
    public const CHECKED_SLOTS = [
        'MainHand' => 'weapon', 'OffHand' => 'offhand', 'Head' => 'head',
        'Armor' => 'armor', 'Shoes' => 'shoe', 'Cape' => 'cape',
    ];

    /** Labels for the (Turkish) review screen. */
    public const SLOT_LABELS_TR = ['weapon' => 'Silah', 'offhand' => 'Offhand', 'head' => 'Kafa', 'armor' => 'Zırh', 'shoe' => 'Ayakkabı', 'cape' => 'Pelerin'];

    /** Capes are usually worn below the gear tier, so they are not tier-checked. */
    private const TIER_CHECKED = ['weapon', 'offhand', 'head', 'armor', 'shoe'];

    /** @var array<string, SavedBuild|null> */
    private array $builds = [];

    /** @var array<int, string|null> GameRole id => unique name */
    private array $uniqueNames = [];

    /**
     * Evaluate using the attendee's current slot (no slot history available).
     *
     * @param  array<string, array<string, mixed>|null>  $equipment  killboard Victim.Equipment
     * @return array{checks: list<array<string, mixed>>, warnings: list<string>, role_type: string|null, suggested_amount: int, slot_index: int|null}
     */
    public function evaluate(SharedLink $link, ?LinkAttendee $attendee, array $equipment): array
    {
        return $this->evaluateAt($link, $attendee, $attendee?->slot_index, (bool) $attendee?->isClaimPending(), $equipment);
    }

    /**
     * Evaluate a stored claim against the slot the player held at the moment of death
     * (slot history), so moves, benches, kicks or leaving after the death don't matter.
     */
    public function evaluateClaim(SharedLink $link, RegearClaim $claim, bool $hasHistory): array
    {
        if (! $hasHistory) {
            return $this->evaluate($link, $claim->attendee, (array) $claim->equipment); // parties from before slot history
        }

        $found = SlotHistory::forDeath($link->id, $claim->victim_name, $claim->died_at);
        if (! $found['had_history'] && $claim->attendee) {
            return $this->evaluate($link, $claim->attendee, (array) $claim->equipment); // placed before history existed
        }

        $held = $found['row'];
        $result = $this->evaluateAt($link, $claim->attendee, $held?->slot_index, $held?->claim_status === LinkAttendee::CLAIM_CLAIMED, (array) $claim->equipment, $held !== null);
        if ($found['placed_later']) {
            $result['warnings'][] = "Öldüğü anda slotta değildi; sonradan Slot {$held->slot_index}'e yerleştirildi.";
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $equipment
     */
    private function evaluateAt(SharedLink $link, ?LinkAttendee $attendee, ?int $slotIndex, bool $claimPending, array $equipment, bool $fromHistory = false): array
    {
        $warnings = [];
        $roleType = null;
        $build = null;

        if (! $slotIndex) {
            $warnings[] = $attendee ? 'Öldüğünde slotu yoktu (waitlist\'teydi).' : 'Roster\'da yok (bu partiye kayıtlı değil).';
        } else {
            $definition = $link->slotDefinition($slotIndex);
            $roleType = $definition['type'];
            $build = $definition['build_id'] ? $this->build($link, $definition['build_id']) : null;
            if (! $build) {
                $warnings[] = 'Slotun build\'i yok; ekipman eşleşmesi kontrol edilmedi, sadece tier kontrol edildi.';
            }
            if ($claimPending) {
                $warnings[] = 'Slot claim\'i caller tarafından onaylanmamıştı.';
            }
            if ($fromHistory && ! $attendee) {
                $warnings[] = 'Öldükten sonra partiden ayrıldı ya da çıkarıldı (öldüğünde Slot '.$slotIndex.'\'teydi).';
            }
        }

        $checks = [];

        foreach (self::CHECKED_SLOTS as $key => $slot) {
            $actual = ItemType::parse($equipment[$key]['Type'] ?? null);
            $expectedId = $build ? $this->uniqueName($build->{$slot.'_id'}) : null;
            $alternatives = $build ? $this->alternatives($build, $slot) : [];

            $match = match (true) {
                ! $build || ($expectedId === null && ! $alternatives) => 'unchecked',
                $actual === null => 'missing',
                $actual->sameItemAs($expectedId) => 'ok',
                collect($alternatives)->contains(fn ($alt) => $actual->sameItemAs($alt['unique'])) => 'alt',
                default => 'wrong',
            };

            $minTier = $link->minTierFor($roleType, $slot);
            $tierOk = $actual && in_array($slot, self::TIER_CHECKED, true) ? $actual->equivalentTier() >= $minTier : null;
            $expectedName = $build?->{$slot}?->name;

            if ($match === 'wrong') {
                $warnings[] = self::SLOT_LABELS_TR[$slot].": {$expectedName} bekleniyordu, başka item ile öldü.";
            } elseif ($match === 'missing') {
                $warnings[] = self::SLOT_LABELS_TR[$slot].": {$expectedName} bekleniyordu, slot boş.";
            }
            if ($tierOk === false) {
                $warnings[] = self::SLOT_LABELS_TR[$slot].": {$actual->label()} giyilmiş (T{$actual->equivalentTier()} sayılır), en az T{$minTier} olmalıydı.";
            }

            $checks[] = [
                'slot' => $slot,
                'label' => self::SLOT_LABELS_TR[$slot],
                'expected' => $expectedName,
                'expected_icon' => $expectedId,
                'actual' => $actual?->id,
                'actual_tier' => $actual?->label(),
                'quality' => $equipment[$key]['Quality'] ?? null,
                'match' => $match,
                'tier_ok' => $tierOk,
                'min_tier' => in_array($slot, self::TIER_CHECKED, true) ? $minTier : null,
            ];
        }

        return [
            'checks' => $checks,
            'warnings' => array_values(array_unique($warnings)),
            'role_type' => $roleType,
            'suggested_amount' => $slotIndex ? $link->payoutFor($roleType) : 0,
            'slot_index' => $slotIndex ?: null,
        ];
    }

    /**
     * Re-evaluates every pending claim of a party, including who killed the player:
     * the "main enemy" is whoever made most of the kills in this event; kills by
     * anyone else, by guildless players or with a gank weapon are flagged.
     *
     * @return list<array{label: string, kills: int}> the main enemies (for the review screen)
     */
    public function reevaluate(SharedLink $link): array
    {
        $claims = $link->regearClaims()->with('attendee')->get();
        $mainEnemies = self::mainEnemies($claims->all());
        $hasHistory = SlotHistory::where('shared_link_id', $link->id)->exists();
        $gankBases = self::gankWeaponBases();
        $limits = $link->regearRules()['limits'];
        $deathNumber = self::deathNumbers($claims->all());

        foreach ($claims as $claim) {
            if (! $claim->isPending()) {
                continue;
            }
            $result = $this->evaluateClaim($link, $claim, $hasHistory);
            $result['warnings'] = array_values(array_unique([
                ...$result['warnings'],
                ...self::killWarnings($claim, $mainEnemies, $gankBases),
                ...self::limitWarnings($claim, $limits, $deathNumber[$claim->id] ?? 1),
            ]));
            $claim->fill($result)->save();
        }

        return array_values(array_map(fn ($e) => ['label' => $e['label'], 'kills' => $e['kills']], $mainEnemies));
    }

    /**
     * Which death of the player this is in the event (1 = first), skipping rejected ones:
     * rejecting an earlier death frees the slot for a later one.
     *
     * @param  list<RegearClaim>  $claims
     * @return array<int, int> claim id => number
     */
    public static function deathNumbers(array $claims): array
    {
        $numbers = [];
        $seen = [];
        usort($claims, fn (RegearClaim $a, RegearClaim $b) => [$a->died_at, $a->id] <=> [$b->died_at, $b->id]);
        foreach ($claims as $claim) {
            if ($claim->status === RegearClaim::REJECTED) {
                continue;
            }
            $player = mb_strtolower($claim->victim_name);
            $numbers[$claim->id] = $seen[$player] = ($seen[$player] ?? 0) + 1;
        }

        return $numbers;
    }

    /**
     * @param  array{max_per_player: int|null, min_item_power: int|null}  $limits
     * @return list<string>
     */
    public static function limitWarnings(RegearClaim $claim, array $limits, int $deathNumber): array
    {
        $warnings = [];
        if ($limits['max_per_player'] && $deathNumber > $limits['max_per_player']) {
            $warnings[] = "Bu event'teki {$deathNumber}. ölümü; oyuncu başına en fazla {$limits['max_per_player']} regear.";
        }
        if ($limits['min_item_power'] && $claim->item_power !== null && $claim->item_power < $limits['min_item_power']) {
            $warnings[] = "Ortalama IP {$claim->item_power}, en az {$limits['min_item_power']} olmalıydı.";
        }

        return $warnings;
    }

    /** Alliance if the killer has one, otherwise their guild; null = guildless. */
    public static function sideKey(RegearClaim $claim): ?string
    {
        return $claim->killer_alliance_id ?: ($claim->killer_guild_id ?: null);
    }

    /** Killed by someone of the victim's own guild or alliance (our side is never "the enemy"). */
    public static function killedByOwnSide(RegearClaim $claim): bool
    {
        $side = self::sideKey($claim);

        return $side !== null && in_array($side, array_filter([$claim->victim_alliance_id, $claim->victim_guild_id]), true);
    }

    /**
     * Sides that made a meaningful share of our deaths (at least 2 kills and 15%).
     * With fewer than 4 attributable deaths we can't tell, so nobody is flagged.
     *
     * @param  list<RegearClaim>  $claims
     * @return array<string, array{label: string, kills: int}>
     */
    public static function mainEnemies(array $claims): array
    {
        $sides = [];
        foreach ($claims as $claim) {
            if (($key = self::sideKey($claim)) && ! self::killedByOwnSide($claim)) {
                $sides[$key]['kills'] = ($sides[$key]['kills'] ?? 0) + 1;
                $sides[$key]['label'] = $claim->killer_alliance ? '['.$claim->killer_alliance.']' : (string) $claim->killer_guild;
            }
        }

        $total = array_sum(array_column($sides, 'kills'));
        if ($total < 4) {
            return [];
        }

        $threshold = max(2, (int) ceil($total * 0.15));
        $main = array_filter($sides, fn ($side) => $side['kills'] >= $threshold);
        uasort($main, fn ($a, $b) => $b['kills'] <=> $a['kills']);

        return $main;
    }

    /** @return list<string> base item ids of the weapons marked as gank weapons in the settings */
    public static function gankWeaponBases(): array
    {
        $ids = (array) Setting::get('regear.gank_weapons', []);
        if (! $ids) {
            return [];
        }

        return GameRole::whereIn('id', $ids)->pluck('unique_name')
            ->map(fn ($unique) => ItemType::parse($unique)?->base)->filter()->values()->all();
    }

    /**
     * @param  array<string, array{label: string, kills: int}>  $mainEnemies
     * @param  list<string>  $gankBases
     * @return list<string>
     */
    public static function killWarnings(RegearClaim $claim, array $mainEnemies, array $gankBases): array
    {
        $warnings = [];
        $side = self::sideKey($claim);

        if ($claim->killer_name && $side === null) {
            $warnings[] = "Guildsiz biri öldürdü ({$claim->killer_name}); event dışı bir gank olabilir.";
        } elseif ($side !== null && self::killedByOwnSide($claim)) {
            $warnings[] = "Kendi tarafından biri öldürdü ({$claim->killer_name}); event dışı bir ölüm olabilir.";
        } elseif ($side !== null && $mainEnemies && ! isset($mainEnemies[$side])) {
            $who = $claim->killer_alliance ? $claim->killer_guild.' ['.$claim->killer_alliance.']' : $claim->killer_guild;
            $warnings[] = "Ana rakip dışında bir guild öldürdü ({$who}); event dışı bir ölüm olabilir.";
        }

        $weapon = ItemType::parse($claim->killer_weapon);
        if ($weapon && in_array($weapon->base, $gankBases, true)) {
            $name = GameRole::whereNotNull('unique_name')->get(['name', 'unique_name'])
                ->first(fn (GameRole $role) => ItemType::parse($role->unique_name)?->base === $weapon->base)?->name ?? $weapon->base;
            $warnings[] = "Gank silahıyla öldürüldü ({$name}).";
        }

        return $warnings;
    }

    private function build(SharedLink $link, int $id): ?SavedBuild
    {
        $key = $link->id.':'.$id; // the same build can be frozen differently per party
        if (! array_key_exists($key, $this->builds)) {
            $this->builds[$key] = $link->resolveBuilds([$id])->get($id)?->load(['weapon', 'offhand', 'head', 'armor', 'shoe', 'cape']);
        }

        return $this->builds[$key];
    }

    private function uniqueName(?int $gameRoleId): ?string
    {
        if (! $gameRoleId) {
            return null;
        }
        if (! array_key_exists($gameRoleId, $this->uniqueNames)) {
            $this->uniqueNames[$gameRoleId] = GameRole::whereKey($gameRoleId)->value('unique_name');
        }

        return $this->uniqueNames[$gameRoleId];
    }

    /**
     * Swaps / alternatives defined on the build for this slot are accepted too.
     *
     * @return list<array{unique: string}>
     */
    private function alternatives(SavedBuild $build, string $slot): array
    {
        $alternatives = [];
        foreach ((array) $build->swaps as $swap) {
            if (($swap['slot'] ?? null) === $slot && ! empty($swap['item_id']) && ($unique = $this->uniqueName((int) $swap['item_id']))) {
                $alternatives[] = ['unique' => $unique];
            }
        }

        return $alternatives;
    }
}
