<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->user()?->role;

        if (! $userRole) {
            abort(403);
        }

        $allowed = array_filter(array_map(fn($r) => Role::tryFrom($r), $roles));

        if (! in_array($userRole, $allowed)) {
            abort(403);
        }

        return $next($request);
    }
}
