<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\User;
use App\Services\TimeService;
use App\Services\WorkRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OvertimeController extends Controller
{
    public function index(Request $request)
    {
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $departmentId = $request->input('department_id');

        $departments  = Department::orderBy('name')->get();
        $usersQuery   = User::active()->with('department');
        if ($departmentId) {
            $usersQuery->where('department_id', $departmentId);
        }
        $users = $usersQuery->orderBy('name')->get();

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth()->endOfDay();
        $yearStart    = Carbon::create($year, 1, 1)->startOfDay();

        $timeService     = app(TimeService::class);
        $workRuleService = app(WorkRuleService::class);

        $monthlyAtts = Attendance::whereBetween('clock_in', [$startOfMonth, $endOfMonth])
            ->whereIn('user_id', $users->pluck('id'))
            ->with('breakRecords')
            ->get()
            ->groupBy('user_id');

        $yearlyAtts = Attendance::whereBetween('clock_in', [$yearStart, $endOfMonth])
            ->whereIn('user_id', $users->pluck('id'))
            ->with('breakRecords')
            ->get()
            ->groupBy('user_id');

        $overtimeData = [];
        foreach ($users as $user) {
            $rule = $workRuleService->resolve($user->id);
            $rounding = [
                'rounding_unit'      => $rule['rounding_unit'],
                'clock_in_rounding'  => $rule['clock_in_rounding'],
                'clock_out_rounding' => $rule['clock_out_rounding'],
            ];

            $monthAtts   = $monthlyAtts->get($user->id, collect());
            $yearAtts    = $yearlyAtts->get($user->id, collect());
            $monthlyOt   = $timeService->calculateTotalOvertimeMinutes($monthAtts, $rounding, $rule);
            $yearlyOt    = $timeService->calculateTotalOvertimeMinutes($yearAtts, $rounding, $rule);

            $overtimeData[] = [
                'user'                 => $user,
                'monthly_overtime_min' => $monthlyOt,
                'yearly_overtime_min'  => $yearlyOt,
                'monthly_danger'       => $monthlyOt >= 40 * 60,
                'monthly_warning'      => $monthlyOt >= 45 * 60,
                'yearly_warning'       => $yearlyOt  >= 360 * 60,
            ];
        }

        return view('admin.overtime.index', compact(
            'overtimeData', 'departments', 'departmentId', 'year', 'month'
        ));
    }
}
