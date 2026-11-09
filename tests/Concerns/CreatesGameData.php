<?php

namespace Tests\Concerns;

use App\Models\GameRole;
use App\Models\LinkAttendee;
use App\Models\SharedLink;
use App\Models\User;

trait CreatesGameData
{
    /** @var array<string, GameRole> */
    protected array $roles = [];

    protected function createGameRoles(): void
    {
        $items = [
            ['1h Mace', 'Tank'], ['Heavy Mace', 'Tank'],
            ['Hallowfall', 'Healer'], ['Fallen Staff', 'Healer'],
            ['Bow', 'DPS'], ['Crossbow', 'DPS'], ['Longbow', 'DPS'], ['Great Frost Staff', 'DPS'],
            ['Locus', 'Support'],
            ['Guardian Helmet', 'Plate Armor Helmet'], ['Martlock Cape', 'Cape'],
        ];

        foreach ($items as [$name, $category]) {
            $this->roles[$name] = GameRole::create(['name' => $name, 'category' => $category, 'icon' => "Elder's {$name}"]);
        }
    }

    /**
     * @param  list<string>  $roles
     */
    protected function registerAttendee(SharedLink $link, array $roles, ?User $user = null, ?int $slot = null): LinkAttendee
    {
        $attributes = ['user_id' => ($user ?? User::factory()->create())->id, 'in_game_name' => 'Player'.fake()->unique()->numberBetween(1, 99999)];
        foreach (['main', 'second', 'third', 'fourth'] as $i => $prefix) {
            $name = $roles[$i] ?? null;
            $attributes[$prefix.'_role'] = $name;
            $attributes[$prefix.'_role_id'] = $name ? ($this->roles[$name]->id ?? null) : null;
        }

        $attendee = $link->attendees()->create($attributes);

        if ($slot !== null) {
            $attendee->forceFill(['slot_index' => $slot, 'assigned_role' => $roles[0]])->save();
        }

        return $attendee;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2?: int|null}>  $slots  index => [role, type, build_id]
     * @return array<int, array<string, mixed>>
     */
    protected function template(array $slots, int $size = 20): array
    {
        $structure = [];
        for ($i = 1; $i <= $size; $i++) {
            [$role, $type, $buildId] = ($slots[$i] ?? ['', 'any']) + [2 => null];
            $structure[$i] = ['role' => $role, 'note' => '', 'type' => $type, 'build_id' => $buildId];
        }

        return $structure;
    }
}
