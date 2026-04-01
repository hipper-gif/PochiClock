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

class MonthSummaryController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $departmentId = $request->input('department_id');

        $authUser = auth()->user();

        // Manager scope: locked to their department
        if ($authUser->isManager()) {
            $departmentId = $authUser->department_id;
        }

        $departments = Department::orderBy('name')->get();

        $usersQuery = User::active()->with('department');
        if ($departmentId) {
            $usersQuery->where('department_id', $departmentId);
        }
        $users = $usersQuery->orderBy('name')->get();

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        $timeService = app(TimeService::class);
        $workRuleService = app(WorkRuleService::class);

        // Fetch all attendances for the period at once
        $allAttendances = Attendance::whereBetween('clock_in', [$startOfMonth, $endOfMonth])
            ->whereIn('user_id', $users->pluck('id'))
            ->with('breakRecords')
            ->get()
            ->groupBy('user_id');

        $summaries = [];
        foreach ($users as $user) {
            $userAttendances = $allAttendances->get($user->id, collect());
            $rule = $workRuleService->resolve($user->id);
            $rounding = [
                'rounding_unit' => $rule['rounding_unit'],
                'clock_in_rounding' => $rule['clock_in_rounding'],
                'clock_out_rounding' => $rule['clock_out_rounding'],
            ];

            $workDays = $userAttendances->groupBy(fn($a) => $a->clock_in->toDateString())
                ->filter(fn($dayAtts) => $dayAtts->whereNotNull('clock_out')->isNotEmpty())
                ->count();

            $totalWorkingMinutes = 0;
            $totalBreakMinutes = 0;
            $lateCount = 0;
            $earlyLeaveCount = 0;
            $overtimeMinutes = 0;

            // Standard minutes per day
            [$sh, $sm] = explode(':', $rule['work_start_time']);
            [$eh, $em] = explode(':', $rule['work_end_time']);
            $standardDayMinutes = ((int)$eh * 60 + (int)$em) - ((int)$sh * 60 + (int)$sm) - (int)$rule['default_break_minutes'];

            foreach ($userAttendances as $att) {
                if ($att->clock_out) {
                    $wm = $timeService->calculateWorkingMinutesWithRounding(
                        $att->clock_in, $att->clock_out, $att->breakRecords, $rounding
                    );
                    if ($wm !== null) {
                        $totalWorkingMinutes += $wm;
                        $overtimeMinutes += max(0, $wm - $standardDayMinutes);
                    }
                    $totalBreakMinutes += $timeService->calculateBreakMinutes($att->breakRecords);
                    $alerts = $timeService->detectAttendanceAlerts($att->clock_in, $att->clock_out, $rule);
                    foreach ($alerts as $alert) {
                        if ($alert['type'] === 'late') $lateCount++;
                        if ($alert['type'] === 'early_leave') $earlyLeaveCount++;
                    }
                }
            }

            $summaries[] = [
                'user' => $user,
                'work_days' => $workDays,
                'total_working_minutes' => $totalWorkingMinutes,
                'total_break_minutes' => $totalBreakMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'late_count' => $lateCount,
                'early_leave_count' => $earlyLeaveCount,
                'overtime_warning' => $overtimeMinutes >= 45 * 60,
            ];
        }

        return view('admin.month-summary.index', compact(
            'summaries', 'departments', 'departmentId', 'year', 'month'
        ));
    }
}
