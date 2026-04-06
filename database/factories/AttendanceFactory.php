<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'user_id' => User::factory(),
            'session_number' => 1,
            'clock_in' => now(),
            'clock_out' => null,
            'note' => null,
            'clock_in_lat' => null,
            'clock_in_lng' => null,
            'clock_out_lat' => null,
            'clock_out_lng' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_out' => now()->addHours(8),
        ]);
    }

    public function withGps(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_in_lat' => fake()->latitude(34.7, 34.8),
            'clock_in_lng' => fake()->longitude(135.5, 135.7),
            'clock_out_lat' => fake()->latitude(34.7, 34.8),
            'clock_out_lng' => fake()->longitude(135.5, 135.7),
        ]);
    }

    public function session(int $n): static
    {
        return $this->state(fn (array $attributes) => [
            'session_number' => $n,
        ]);
    }

    public function morningShift(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_in' => today()->setTime(9, 0),
            'clock_out' => today()->setTime(12, 0),
        ]);
    }

    public function afternoonShift(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_in' => today()->setTime(14, 0),
            'clock_out' => today()->setTime(18, 0),
        ]);
    }
}
