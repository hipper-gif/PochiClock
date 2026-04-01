<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidLeave extends Model
{
    use HasUuids;

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

    /**
     * 種別の日本語ラベル
     */
    public function getLeaveTypeLabelAttribute(): string
    {
        return match ($this->leave_type) {
            'full' => '全休',
            'half_am' => '半休（午前）',
            'half_pm' => '半休（午後）',
            default => $this->leave_type,
        };
    }

    /**
     * ステータスの日本語ラベル
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => '申請中',
            'approved' => '承認済',
            'rejected' => '却下',
            default => $this->status,
        };
    }

    /**
     * 消費日数（全休=1.0, 半休=0.5）
     */
    public function getConsumeDaysAttribute(): float
    {
        return $this->leave_type === 'full' ? 1.0 : 0.5;
    }
}
