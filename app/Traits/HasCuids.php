<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Extends HasUuids but skips UUID format validation in route model binding.
 * Our IDs are CUIDs (e.g., cmmerf9jd0001cgsk9k7896tz), not standard UUIDs.
 */
trait HasCuids
{
    use HasUuids;

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->where($field ?? $this->getRouteKeyName(), $value);
    }
}
