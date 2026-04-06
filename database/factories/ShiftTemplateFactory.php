<?php

namespace Database\Factories;

use App\Models\ShiftTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftTemplate>
 */
class ShiftTemplateFactory extends Factory
{
    protected $model = ShiftTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'name' => fake()->word(),
            'color' => '#3B82F6',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_minutes' => 60,
        ];
    }
}
