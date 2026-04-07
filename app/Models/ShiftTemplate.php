<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasCuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftTemplate extends Model
{
    use HasCuids, HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'color',
        'start_time',
        'end_time',
        'break_minutes',
    ];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }
}
