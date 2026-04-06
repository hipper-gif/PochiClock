<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\BreakRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreakRecord>
 */
class BreakRecordFactory extends Factory
{
    protected $model = BreakRecord::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'attendance_id' => Attendance::factory(),
            'break_start' => now(),
            'break_end' => null,
            'latitude' => null,
            'longitude' => null,
            'end_latitude' => null,
            'end_longitude' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'break_end' => now()->addMinutes(60),
        ]);
    }
}
