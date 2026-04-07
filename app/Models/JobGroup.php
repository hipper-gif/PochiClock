<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasCuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobGroup extends Model
{
    use HasCuids, HasFactory, BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'description'];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workRule(): HasOne
    {
        return $this->hasOne(WorkRule::class);
    }
}
