<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Department extends Model
{
    use HasUuids;
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workRule(): HasOne
    {
        return $this->hasOne(WorkRule::class);
    }
}
