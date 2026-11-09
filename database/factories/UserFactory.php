<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'discord_id' => (string) fake()->unique()->numberBetween(100000000000000000, 999999999999999999),
            'avatar' => null,
            'role' => User::ROLE_USER,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => User::ROLE_ADMIN]);
    }

    public function creator(): static
    {
        return $this->state(['role' => User::ROLE_CREATOR]);
    }
}
