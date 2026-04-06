<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasUuids, HasFactory, BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'session_number',
        'clock_in',
        'clock_out',
        'note',
        'clock_in_lat',
        'clock_in_lng',
        'clock_out_lat',
        'clock_out_lng',
    ];

    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'session_number' => 'integer',
            'clock_in_lat' => 'float',
            'clock_in_lng' => 'float',
            'clock_out_lat' => 'float',
            'clock_out_lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breakRecords(): HasMany
    {
        return $this->hasMany(BreakRecord::class);
    }

    public function getActiveBreakAttribute(): ?BreakRecord
    {
        return $this->breakRecords->whereNull('break_end')->first();
    }

    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->whereDate('clock_in', Carbon::parse($date)->toDateString());
    }

    public function scopeForToday(Builder $query): Builder
    {
        return $query->whereDate('clock_in', Carbon::today());
    }
}
