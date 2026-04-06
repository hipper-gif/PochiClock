<?php

namespace Database\Factories;

use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftAssignment>
 */
class ShiftAssignmentFactory extends Factory
{
    protected $model = ShiftAssignment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'user_id' => User::factory(),
            'shift_template_id' => null,
            'date' => today(),
            'note' => null,
        ];
    }
}
