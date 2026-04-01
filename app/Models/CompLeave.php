<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompLeave extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'user_id', 'leave_date', 'hours', 'note', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
            'hours' => 'decimal:1',
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
}
