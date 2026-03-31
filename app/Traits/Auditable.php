<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->logAudit('created', [], $model->getAuditableAttributes());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $dirty);
            $model->logAudit('updated', $old, $dirty);
        });

        static::deleted(function ($model) {
            $model->logAudit('deleted', $model->getAuditableAttributes(), []);
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    protected function logAudit(string $action, array $oldValues, array $newValues): void
    {
        // Skip if running in console (migrations, seeders) unless explicitly enabled
        if (app()->runningInConsole() && ! app()->bound('audit_enabled')) {
            return;
        }

        $user = auth()->user();

        AuditLog::create([
            'tenant_id' => $this->tenant_id ?? $user?->tenant_id ?? null,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();

        // Exclude sensitive and system fields
        $excluded = ['password', 'remember_token', 'created_at', 'updated_at'];

        return array_diff_key($attributes, array_flip($excluded));
    }
}
