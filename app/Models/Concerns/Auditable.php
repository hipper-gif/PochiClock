<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::updated(function ($model) {
            $dirty = $model->getDirty();
            $original = array_intersect_key($model->getOriginal(), $dirty);

            if (empty($dirty)) {
                return;
            }

            $user = Auth::user();

            AuditLog::create([
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->getKey(),
                'user_id'        => $user?->id,
                'user_name'      => $user?->name,
                'action'         => 'updated',
                'old_values'     => $original,
                'new_values'     => $dirty,
            ]);
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
