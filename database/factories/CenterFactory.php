<?php

namespace Database\Factories;

use App\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Center>
 */
class CenterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Medical Center',
            'description' => fake()->paragraph(),
            'phone' => fake()->numerify('05########'),
            'email' => fake()->unique()->companyEmail(),
            'country_code' => 'SA',
            'city' => fake()->randomElement(['Riyadh', 'Jeddah', 'Dammam', 'Makkah', 'Madinah', 'Khobar']),
            'address' => fake()->streetAddress(),
            'timezone' => 'Asia/Riyadh',
            'status' => 'pending',
        ];
    }

    /**
     * An approved center that appears in the public directory.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'approved_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }
}
