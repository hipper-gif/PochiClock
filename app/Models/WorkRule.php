<?php

namespace App\Models;

use App\Enums\WorkRuleScope;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkRule extends Model
{
    use HasUuids, BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'scope',
        'department_id',
        'job_group_id',
        'user_id',
        'work_start_time',
        'work_end_time',
        'default_break_minutes',
        'break_tiers',
        'allow_multiple_clock_ins',
        'rounding_unit',
        'clock_in_rounding',
        'clock_out_rounding',
        'early_clock_in_cutoff',
        'early_clock_in_cutoff_pm',
    ];

    protected function casts(): array
    {
        return [
            'scope' => WorkRuleScope::class,
            'break_tiers' => 'array',
            'allow_multiple_clock_ins' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobGroup(): BelongsTo
    {
        return $this->belongsTo(JobGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
