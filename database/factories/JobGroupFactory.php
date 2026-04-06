<?php

namespace Database\Factories;

use App\Models\JobGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobGroup>
 */
class JobGroupFactory extends Factory
{
    protected $model = JobGroup::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->sentence(),
        ];
    }
}
