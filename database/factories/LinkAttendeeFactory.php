<?php

namespace Database\Factories;

use App\Models\LinkAttendee;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkAttendee>
 */
class LinkAttendeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shared_link_id' => SharedLink::factory(),
            'user_id' => User::factory(),
            'in_game_name' => fake()->unique()->regexify('[A-Za-z]{8}'),
            'main_role' => LinkAttendee::ROLE_FILL,
        ];
    }

    public function inSlot(int $slot, ?string $assignedRole = null): static
    {
        return $this->afterCreating(fn (LinkAttendee $a) => $a->forceFill([
            'slot_index' => $slot,
            'assigned_role' => $assignedRole ?? $a->main_role,
        ])->save());
    }
}
