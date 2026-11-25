<?php

namespace Database\Factories;

use App\Enums\Specialty;
use App\Models\Center;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Doctor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'center_id' => Center::factory(),
            'specialty' => fake()->randomElement(Specialty::cases()),
            'name' => fake()->name(),
            'bio' => fake()->optional()->paragraph(),
            'photo_path' => null,
            'is_active' => true,
        ];
    }
}
