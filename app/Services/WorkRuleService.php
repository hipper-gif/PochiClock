<?php

namespace App\Services;

use App\Enums\WorkRuleScope;
use App\Models\User;
use App\Models\WorkRule;

class WorkRuleService
{
    private const DEFAULT_RULE = [
        'work_start_time' => '09:00',
        'work_end_time' => '18:00',
        'default_break_minutes' => 60,
        'break_tiers' => [],
        'allow_multiple_clock_ins' => false,
        'rounding_unit' => 1,
        'clock_in_rounding' => 'none',
        'clock_out_rounding' => 'none',
        'source' => 'DEFAULT',
    ];

    public function resolve(string $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            return self::DEFAULT_RULE;
        }

        // USER レベル
        $rule = WorkRule::where('scope', WorkRuleScope::USER)
            ->where('user_id', $userId)
            ->first();
        if ($rule) {
            return $this->formatRule($rule, 'USER');
        }

        // JOB_GROUP レベル（ユーザーの有効な職種グループで検索）
        $jobGroup = $user->resolvedJobGroup();
        if ($jobGroup) {
            $rule = WorkRule::where('scope', WorkRuleScope::JOB_GROUP)
                ->where('job_group_id', $jobGroup->id)
                ->first();
            if ($rule) {
                return $this->formatRule($rule, 'JOB_GROUP');
            }
        }

        // SYSTEM レベル
        $rule = WorkRule::where('scope', WorkRuleScope::SYSTEM)->first();
        if ($rule) {
            return $this->formatRule($rule, 'SYSTEM');
        }

        return self::DEFAULT_RULE;
    }

    private function formatRule(WorkRule $rule, string $source): array
    {
        return [
            'work_start_time' => $rule->work_start_time,
            'work_end_time' => $rule->work_end_time,
            'default_break_minutes' => $rule->default_break_minutes,
            'break_tiers' => $rule->break_tiers ?? [],
            'allow_multiple_clock_ins' => $rule->allow_multiple_clock_ins,
            'rounding_unit' => $rule->rounding_unit,
            'clock_in_rounding' => $rule->clock_in_rounding,
            'clock_out_rounding' => $rule->clock_out_rounding,
            'source' => $source,
        ];
    }

    public function getSystemRule(): ?WorkRule
    {
        return WorkRule::where('scope', WorkRuleScope::SYSTEM)->first();
    }

    public function getJobGroupRules()
    {
        return WorkRule::where('scope', WorkRuleScope::JOB_GROUP)
            ->with('jobGroup')
            ->get()
            ->sortBy(fn ($r) => $r->jobGroup?->name);
    }

    public function getUserRules()
    {
        return WorkRule::where('scope', WorkRuleScope::USER)
            ->with('user')
            ->get()
            ->sortBy(fn ($r) => $r->user?->name);
    }
}
