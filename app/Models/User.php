<?php

namespace App\Models;

use App\Enums\Role;
use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasUuids, BelongsToTenant, Auditable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'employee_number',
        'name',
        'email',
        'password',
        'kiosk_code',
        'qr_token',
        'role',
        'is_active',
        'department_id',
        'job_group_id',
        'hire_date',
        'weekly_work_days',
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
            'hire_date' => 'date',
            'weekly_work_days' => 'decimal:1',
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

    public function paidLeaves(): HasMany
    {
        return $this->hasMany(PaidLeave::class);
    }

    public function paidLeaveBalances(): HasMany
    {
        return $this->hasMany(PaidLeaveBalance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === Role::MANAGER;
    }

    public function isAdminOrManager(): bool
    {
        return in_array($this->role, [Role::ADMIN, Role::MANAGER]);
    }

    public function generateQrToken(): string
    {
        $this->qr_token = Str::random(32);
        $this->save();

        return $this->qr_token;
    }

    public function getQrCodeUrl(): string
    {
        return config('app.url') . '/qr-verify/' . $this->qr_token;
    }
}
