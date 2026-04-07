<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasCuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidLeaveBalance extends Model
{
    use HasCuids, BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'grant_date',
        'expiry_date',
        'granted_days',
        'used_days',
        'grant_reason',
    ];

    protected function casts(): array
    {
        return [
            'grant_date' => 'date',
            'expiry_date' => 'date',
            'granted_days' => 'decimal:1',
            'used_days' => 'decimal:1',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 残日数
     */
    public function remainingDays(): float
    {
        return (float) $this->granted_days - (float) $this->used_days;
    }

    /**
     * 有効な残高のみ取得（失効日が今日以降）
     */
    public function scopeActive($query)
    {
        return $query->where('expiry_date', '>=', now()->toDateString());
    }
}
