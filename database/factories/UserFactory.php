<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Center;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Patient,
            'status' => UserStatus::Active,
            'locale' => Locale::Ar,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A suspended account, which may not sign in.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * A staff account bound to a center (created when none is given).
     */
    public function centerStaff(?Center $center = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::CenterStaff,
            'center_id' => $center?->id ?? Center::factory(),
        ]);
    }

    /**
     * A Google-only account: no password, email already trusted.
     */
    public function googleOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'google_id' => (string) fake()->unique()->numerify('################'),
        ]);
    }
}
