<?php

namespace App\Services;

use App\Models\GameRole;
use App\Models\LinkAttendee;
use App\Models\SavedBuild;
use App\Models\SharedLink;

/**
 * Decides which role a player plays in a slot and whether the placement is
 * "forced" (the player did not register a matching role).
 *
 * Matching is done by GameRole ID (build weapon or template role), so e.g.
 * "Bow" never matches "Crossbow".
 */
class SlotAssigner
{
    /**
     * @return array{slot_index: int|null, assigned_role: string|null, is_forced: bool}
     */
    public function assign(LinkAttendee $attendee, SharedLink $link, int $slot): array
    {
        if ($slot <= 0) {
            return ['slot_index' => null, 'assigned_role' => null, 'is_forced' => false];
        }

        $placed = fn (?string $role, bool $forced = false) => [
            'slot_index' => $slot,
            'assigned_role' => $role,
            'is_forced' => $forced,
        ];

        // Bombsquad keeps its role wherever it is placed.
        if ($attendee->isBombsquad()) {
            return $placed(LinkAttendee::ROLE_BOMBSQUAD);
        }

        $definition = $link->slotDefinition($slot);
        if ($definition['is_extra']) {
            return $placed($attendee->main_role);
        }

        $requiredLabel = $definition['role'];
        $requiredWeaponId = null;

        if ($definition['build_id'] && ($build = SavedBuild::find($definition['build_id']))) {
            $requiredWeaponId = $build->weapon_id;
            $requiredLabel = $requiredLabel !== '' ? $requiredLabel : $build->name;
        }

        $isOpenSlot = $requiredWeaponId === null && SharedLink::isWildcardRole($requiredLabel);
        if ($isOpenSlot) {
            return $placed($attendee->main_role);
        }

        $requiredCategory = null;
        if ($requiredWeaponId === null) {
            $cleanLabel = self::cleanRoleName($requiredLabel);
            $requiredWeaponId = GameRole::query()->weapons()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($cleanLabel)])
                ->value('id');
            $requiredCategory = $requiredWeaponId === null ? self::categoryFromLabel($cleanLabel) : null;
        }

        $preferences = $attendee->rolePreferences();
        $categories = $requiredCategory
            ? GameRole::whereIn('id', array_filter(array_column($preferences, 'id')))->pluck('category', 'id')
            : collect();

        foreach ($preferences as $preference) {
            $name = $preference['name'];
            $id = $preference['id'];

            if ($requiredWeaponId !== null && $id === $requiredWeaponId) {
                return $placed($name);
            }

            if ($requiredCategory !== null && $id !== null && ($categories[$id] ?? null) === $requiredCategory) {
                return $placed($name);
            }

            if ($requiredWeaponId === null && $requiredCategory === null
                && strcasecmp(self::cleanRoleName($name), self::cleanRoleName($requiredLabel)) === 0) {
                return $placed($name);
            }

            if ($name === LinkAttendee::ROLE_FILL
                || ($name === LinkAttendee::ROLE_FILL_TANK && $definition['type'] === 'tank')
                || ($name === LinkAttendee::ROLE_FILL_DPS && $definition['type'] === 'dps')) {
                return $placed($requiredLabel);
            }
        }

        // No registered role fits: the caller forced the player here.
        return $placed($requiredLabel, true);
    }

    /**
     * The role a player takes when claiming a slot: the build's weapon (or mount),
     * else the weapon named by the slot label, else the label itself.
     *
     * @return array{name: string|null, id: int|null}
     */
    public function roleForSlot(SharedLink $link, int $slot): array
    {
        $definition = $link->slotDefinition($slot);
        if ($definition['is_extra']) {
            return ['name' => null, 'id' => null];
        }

        $build = $definition['build_id'] ? SavedBuild::with(['weapon', 'mount'])->find($definition['build_id']) : null;
        $item = $build?->weapon ?? $build?->mount;

        if (! $item && ! SharedLink::isWildcardRole($definition['role'])) {
            $item = GameRole::whereRaw('LOWER(name) = ?', [mb_strtolower(self::cleanRoleName($definition['role']))])->first();
        }

        if ($item) {
            return ['name' => $item->name, 'id' => $item->id];
        }

        return SharedLink::isWildcardRole($definition['role'])
            ? ['name' => null, 'id' => null]
            : ['name' => mb_substr($definition['role'], 0, 100), 'id' => null];
    }

    /** "1h Mace - Engage", "Hallowfall CTA" => "1h Mace", "Hallowfall" */
    public static function cleanRoleName(string $name): string
    {
        return trim((string) preg_replace('/(\s-\s.*|\s(CTA|Mass|Swap))$/i', '', trim($name)));
    }

    /** Template slots labelled with a category ("Tank", "Healer"...) accept any weapon of it. */
    private static function categoryFromLabel(string $label): ?string
    {
        return match (mb_strtolower($label)) {
            'tank' => 'Tank',
            'heal', 'healer' => 'Healer',
            'dps' => 'DPS',
            'support', 'supp' => 'Support',
            default => null,
        };
    }
}
