<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityRule>
 */
class AvailabilityRuleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AvailabilityRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startHour = fake()->numberBetween(8, 15);
        $endHour = fake()->numberBetween($startHour + 1, 18);
        $slotMinutes = fake()->randomElement([15, 20, 30, 45, 60]);

        // Ensure the window is a multiple of slot_minutes
        $windowMinutes = $endHour * 60 - $startHour * 60;
        $remainder = $windowMinutes % $slotMinutes;
        if ($remainder !== 0) {
            $endHour = $startHour + (int) ceil($windowMinutes / $slotMinutes) * $slotMinutes / 60;
        }

        return [
            'doctor_id' => Doctor::factory(),
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $endHour),
            'slot_minutes' => $slotMinutes,
        ];
    }
}
