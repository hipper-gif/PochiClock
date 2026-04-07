<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasCuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasCuids, HasFactory, BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'job_group_id'];

    public function jobGroup(): BelongsTo
    {
        return $this->belongsTo(JobGroup::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

}
