<?php

namespace Database\Factories;

use App\Enums\WorkRuleScope;
use App\Models\JobGroup;
use App\Models\User;
use App\Models\WorkRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkRule>
 */
class WorkRuleFactory extends Factory
{
    protected $model = WorkRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'scope' => WorkRuleScope::SYSTEM,
            'job_group_id' => null,
            'user_id' => null,
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'default_break_minutes' => 60,
            'break_tiers' => null,
            'allow_multiple_clock_ins' => false,
            'rounding_unit' => 1,
            'clock_in_rounding' => 'none',
            'clock_out_rounding' => 'none',
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ];
    }

    public function forJobGroup(JobGroup $jobGroup): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => WorkRuleScope::JOB_GROUP,
            'job_group_id' => $jobGroup->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => WorkRuleScope::USER,
            'user_id' => $user->id,
        ]);
    }

    public function withEarlyCutoff(string $time): static
    {
        return $this->state(fn (array $attributes) => [
            'early_clock_in_cutoff' => $time,
        ]);
    }

    public function withMultipleClockIns(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_multiple_clock_ins' => true,
        ]);
    }

    public function withRounding(int $unit, string $inDir, string $outDir): static
    {
        return $this->state(fn (array $attributes) => [
            'rounding_unit' => $unit,
            'clock_in_rounding' => $inDir,
            'clock_out_rounding' => $outDir,
        ]);
    }
}
