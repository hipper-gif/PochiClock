<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasUuids;

    protected $fillable = [
        'employee_number',
        'name',
        'email',
        'password',
        'kiosk_code',
        'role',
        'is_active',
        'department_id',
        'job_group_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobGroup(): BelongsTo
    {
        return $this->belongsTo(JobGroup::class);
    }

    /**
     * ユーザーの有効な職種グループを返す。
     * ユーザー個人に設定があればそれを、なければ所属部署の職種グループを返す。
     */
    public function resolvedJobGroup(): ?JobGroup
    {
        if ($this->job_group_id) {
            return $this->jobGroup;
        }

        return $this->department?->jobGroup;
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function workRule(): HasOne
    {
        return $this->hasOne(WorkRule::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }
}
