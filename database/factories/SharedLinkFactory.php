<?php

namespace Database\Factories;

use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SharedLink>
 */
class SharedLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'creator_id' => User::factory()->creator(),
            'title' => fake()->sentence(3),
            'type' => SharedLink::TYPE_CTA,
            'expires_at' => now()->addHours(8),
        ];
    }

    public function content(): static
    {
        return $this->state(['type' => SharedLink::TYPE_CONTENT]);
    }

    public function completed(): static
    {
        return $this->afterCreating(fn (SharedLink $link) => $link->forceFill(['status' => SharedLink::STATUS_COMPLETED])->save());
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinute()]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $slots  1-based slot definitions
     */
    public function withTemplate(array $slots): static
    {
        return $this->state(['template_snapshot' => $slots]);
    }
}
