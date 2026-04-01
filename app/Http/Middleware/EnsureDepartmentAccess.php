<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDepartmentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admin can access any department — pass through
        if ($user && $user->isAdmin()) {
            return $next($request);
        }

        // Manager: if a department_id query param is provided and doesn't match
        // their own department, redirect to their own department
        if ($user && $user->isManager()) {
            $requestedDept = $request->query('department_id');
            if ($requestedDept && $requestedDept !== $user->department_id) {
                return redirect()->back()->with('error', '他部署のデータにはアクセスできません');
            }
        }

        return $next($request);
    }
}
