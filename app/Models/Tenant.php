<?php

namespace App\Models;

use App\Traits\HasCuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasCuids;

    protected $fillable = ['name', 'slug', 'is_active', 'retention_until'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'retention_until' => 'date',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
