<?php

namespace App\Services\Regear;

use App\Models\GameRole;
use App\Models\LinkAttendee;
use App\Models\SavedBuild;
use App\Models\SharedLink;
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

    /** @var array<int, SavedBuild|null> */
    private array $builds = [];

    /** @var array<int, string|null> GameRole id => unique name */
    private array $uniqueNames = [];

    /**
     * @param  array<string, array<string, mixed>|null>  $equipment  killboard Victim.Equipment
     * @return array{checks: list<array<string, mixed>>, warnings: list<string>, role_type: string|null, suggested_amount: int}
     */
    public function evaluate(SharedLink $link, ?LinkAttendee $attendee, array $equipment): array
    {
        $warnings = [];
        $roleType = null;
        $build = null;

        if (! $attendee) {
            $warnings[] = 'Roster\'da yok (bu partiye kayıtlı değil).';
        } elseif ($attendee->slot_index === null) {
            $warnings[] = 'Slotu yoktu (waitlist\'teydi).';
        } else {
            $definition = $link->slotDefinition($attendee->slot_index);
            $roleType = $definition['type'];
            $build = $definition['build_id'] ? $this->build($definition['build_id']) : null;
            if (! $build) {
                $warnings[] = 'Slotun build\'i yok; ekipman eşleşmesi kontrol edilmedi, sadece tier kontrol edildi.';
            }
            if ($attendee->isClaimPending()) {
                $warnings[] = 'Slot claim\'i caller tarafından onaylanmamıştı.';
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
            'suggested_amount' => $attendee && $attendee->slot_index !== null ? $link->payoutFor($roleType) : 0,
        ];
    }

    private function build(int $id): ?SavedBuild
    {
        if (! array_key_exists($id, $this->builds)) {
            $this->builds[$id] = SavedBuild::with(['weapon', 'offhand', 'head', 'armor', 'shoe', 'cape'])->find($id);
        }

        return $this->builds[$id];
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
