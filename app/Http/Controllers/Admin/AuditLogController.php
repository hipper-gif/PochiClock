<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $query = AuditLog::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('auditable_type', $request->type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->paginate(50);

        $users = User::orderBy('name')->get();

        $auditableTypes = [
            'App\\Models\\Attendance' => '勤怠',
            'App\\Models\\BreakRecord' => '休憩',
            'App\\Models\\WorkRule' => '勤務ルール',
            'App\\Models\\User' => 'ユーザー',
        ];

        return view('admin.audit-logs.index', compact('logs', 'users', 'auditableTypes'));
    }
}
