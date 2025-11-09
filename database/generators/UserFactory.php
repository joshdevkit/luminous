<?php

namespace Database\Generators;

use Core\Database\Generators\Factory;
use Core\Support\Carbon;

/**
 * @extends Factory<\App\Entities\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'status' => $this->faker->randomElement(['active', 'inactive', 'suspended']),
            'email_verified_at' => Carbon::now(),
        ];
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(function () {
            return [
                'role' => 'admin',
                'status' => 'active',
            ];
        });
    }

    /**
     * Indicate that the user is unverified.
     */
    public function unverified(): static
    {
        return $this->state([
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is suspended.
     */
    public function suspended(): static
    {
        return $this->state([
            'status' => 'suspended',
        ]);
    }

    /**
     * Set a specific email.
     */
    public function withEmail(string $email): static
    {
        return $this->state([
            'email' => $email,
        ]);
    }
}