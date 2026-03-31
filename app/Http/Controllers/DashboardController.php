<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\TimeService;
use App\Services\WorkRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private TimeService $timeService,
        private WorkRuleService $workRuleService,
    ) {}

    public function index()
    {
        $user = Auth::user();
        $today = Carbon::today();

        $todayAttendances = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $today)
            ->with('breakRecords')
            ->orderBy('clock_in')
            ->get();

        $activeAttendance = $todayAttendances->whereNull('clock_out')->first();
        $attendance = $activeAttendance ?? $todayAttendances->last();

        $status = 'not_started';
        $workingMinutes = null;
        $breakMinutes = 0;
        $breakCount = 0;
        $alerts = [];
        $totalDailyWorkingMinutes = 0;

        $rule = $this->workRuleService->resolve($user->id);
        $rounding = [
            'rounding_unit' => $rule['rounding_unit'],
            'clock_in_rounding' => $rule['clock_in_rounding'],
            'clock_out_rounding' => $rule['clock_out_rounding'],
        ];

        if ($activeAttendance) {
            $activeBreak = $activeAttendance->breakRecords->whereNull('break_end')->first();
            $status = $activeBreak ? 'on_break' : 'clocked_in';
        } elseif ($todayAttendances->isNotEmpty()) {
            $status = 'clocked_out';
        }

        // Calculate totals across all sessions
        $totalDailyWorkingMinutes = $this->timeService->calculateDailyWorkingMinutes($todayAttendances, $rounding);

        if ($attendance) {
            $breakMinutes = $this->timeService->calculateBreakMinutes($attendance->breakRecords);
            $breakCount = $attendance->breakRecords->count();

            if ($attendance->clock_out) {
                $workingMinutes = $this->timeService->calculateWorkingMinutesWithRounding(
                    $attendance->clock_in,
                    $attendance->clock_out,
                    $attendance->breakRecords,
                    $rounding
                );
            }

            $alerts = $this->timeService->detectAttendanceAlerts(
                $attendance->clock_in,
                $attendance->clock_out,
                $rule
            );
        }

        $roundedTimes = null;
        if ($attendance) {
            $roundedTimes = $this->timeService->getRoundedTimes(
                $attendance->clock_in,
                $attendance->clock_out,
                $rounding
            );
        }

        return view('dashboard.index', compact(
            'user', 'attendance', 'todayAttendances', 'status', 'workingMinutes',
            'breakMinutes', 'breakCount', 'alerts', 'rule', 'roundedTimes',
            'totalDailyWorkingMinutes'
        ));
    }

    public function history(Request $request)
    {
        $user = Auth::user();
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        $records = Attendance::where('user_id', $user->id)
            ->whereBetween('clock_in', [$startOfMonth, $endOfMonth])
            ->with('breakRecords')
            ->orderBy('clock_in')
            ->get();

        $rule = $this->workRuleService->resolve($user->id);
        $rounding = [
            'rounding_unit' => $rule['rounding_unit'],
            'clock_in_rounding' => $rule['clock_in_rounding'],
            'clock_out_rounding' => $rule['clock_out_rounding'],
        ];

        // Group records by date for multi-session support
        $groupedRecords = $records->groupBy(fn ($r) => $r->clock_in->toDateString());

        $totalWorkDays = 0;
        $totalBindingMinutes = 0;
        $totalWorkingMinutes = 0;
        $totalBreakMinutes = 0;

        foreach ($groupedRecords as $date => $dayRecords) {
            $dayHasCompleted = $dayRecords->whereNotNull('clock_out')->isNotEmpty();
            if ($dayHasCompleted) {
                $totalWorkDays++;
            }
            foreach ($dayRecords as $record) {
                if ($record->clock_out) {
                    $rounded = $this->timeService->getRoundedTimes($record->clock_in, $record->clock_out, $rounding);
                    $bindingMin = $rounded['rounded_clock_in']->diffInMinutes($rounded['rounded_clock_out']);
                    $breakMin = $this->timeService->calculateBreakMinutes($record->breakRecords);
                    $totalBindingMinutes += $bindingMin;
                    $totalBreakMinutes += $breakMin;
                    $totalWorkingMinutes += max(0, $bindingMin - $breakMin);
                }
            }
        }

        return view('dashboard.history', compact(
            'records', 'groupedRecords', 'year', 'month', 'rounding', 'rule',
            'totalWorkDays', 'totalBindingMinutes', 'totalWorkingMinutes', 'totalBreakMinutes'
        ));
    }
}
