<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated Legacy middleware - replaced by EnsureRole middleware.
 * Kept because bootstrap/app.php still registers it as 'admin' alias.
 * Safe to remove once all 'admin' middleware references are migrated to 'role:admin'.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->role !== Role::ADMIN) {
            abort(403, '管理者権限が必要です');
        }

        return $next($request);
    }
}
