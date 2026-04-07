<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use App\Traits\HasCuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakRecord extends Model
{
    use HasCuids, HasFactory, BelongsToTenant, Auditable;

    protected $table = 'break_records';

    protected $fillable = [
        'tenant_id',
        'attendance_id',
        'break_start',
        'break_end',
        'latitude',
        'longitude',
        'end_latitude',
        'end_longitude',
    ];

    protected function casts(): array
    {
        return [
            'break_start' => 'datetime',
            'break_end' => 'datetime',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
