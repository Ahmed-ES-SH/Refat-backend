<?php

namespace Database\Factories;

use App\Enums\FamilyRelation;
use App\Enums\Gender;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'relation' => fake()->randomElement(FamilyRelation::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-1 year'),
            'gender' => fake()->randomElement(Gender::cases()),
        ];
    }
}
