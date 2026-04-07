<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasCuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidLeave extends Model
{
    use HasCuids, BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'leave_date',
        'leave_type',
        'status',
        'reason',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
            'leave_type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getConsumeDaysAttribute(): float
    {
        return $this->leave_type === LeaveType::FULL ? 1.0 : 0.5;
    }
}
