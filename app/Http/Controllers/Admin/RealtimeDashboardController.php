<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RealtimeDashboardController extends Controller
{
    public function index()
    {
        return view('admin.realtime.index', [
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function data(Request $request)
    {
        $today = Carbon::today();

        // All active users grouped by department
        $users = User::active()
            ->with(['department', 'attendances' => function ($q) use ($today) {
                $q->whereDate('clock_in', $today)
                  ->with('breakRecords')
                  ->orderByDesc('clock_in');
            }])
            ->orderBy('name')
            ->get();

        $departments = [];
        foreach ($users->groupBy('department_id') as $deptId => $deptUsers) {
            $dept = $deptUsers->first()->department;
            $stats = ['present' => 0, 'on_break' => 0, 'left' => 0, 'absent' => 0];
            $userList = [];

            foreach ($deptUsers as $user) {
                $latestAtt = $user->attendances->first();
                $status = 'absent';
                $clockIn = null;
                $clockOut = null;

                if ($latestAtt) {
                    $clockIn = $latestAtt->clock_in->format('H:i');
                    if ($latestAtt->clock_out) {
                        $status = 'left';
                        $clockOut = $latestAtt->clock_out->format('H:i');
                    } elseif ($latestAtt->breakRecords->whereNull('break_end')->isNotEmpty()) {
                        $status = 'on_break';
                    } else {
                        $status = 'present';
                    }
                }

                $stats[$status]++;
                $userList[] = [
                    'name' => $user->name,
                    'status' => $status,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                ];
            }

            $departments[] = [
                'name' => $dept?->name ?? '未所属',
                'stats' => $stats,
                'users' => $userList,
            ];
        }

        return response()->json([
            'departments' => $departments,
            'updated_at' => now()->format('H:i:s'),
        ]);
    }
}
