<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDepartmentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admin can access everything
        if ($user->role === Role::ADMIN) {
            return $next($request);
        }

        // Manager can only access their own department
        if ($user->role === Role::MANAGER) {
            $targetUser = $request->route('user');
            if ($targetUser && $targetUser->department_id !== $user->department_id) {
                abort(403);
            }

            $department = $request->route('department');
            if ($department && $department->id !== $user->department_id) {
                abort(403);
            }

            return $next($request);
        }

        // Employee should not access admin routes at all
        abort(403);
    }
}
