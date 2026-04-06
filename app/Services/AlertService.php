<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AlertService
{
    public function __construct(
        private WorkRuleService $workRuleService,
        private TimeService $timeService,
    ) {}

    /**
     * Get attendance records from a given date where clock_out is null.
     */
    public function getMissingClockOuts(?string $date = null): Collection
    {
        if ($date) {
            return Attendance::whereDate('clock_in', Carbon::parse($date))
                ->whereNull('clock_out')
                ->with('user', 'user.department')
                ->get();
        }

        // Default: past 3 days (covers weekends)
        return Attendance::where('clock_in', '>=', Carbon::today()->subDays(3))
            ->where('clock_in', '<', Carbon::today())
            ->whereNull('clock_out')
            ->with('user', 'user.department')
            ->get();
    }

    /**
     * Get attendances where actual clock-out significantly exceeded scheduled end.
     * Threshold: 15+ minutes over work_end_time.
     */
    public function getShiftOvertime(?string $date = null): Collection
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::yesterday();

        $attendances = Attendance::whereDate('clock_in', $targetDate)
            ->whereNotNull('clock_out')
            ->with('user', 'user.department')
            ->get();

        return $attendances->map(function ($att) {
            $rule = $this->workRuleService->resolve($att->user_id);
            $alerts = $this->timeService->detectAttendanceAlerts(
                $att->clock_in, $att->clock_out, $rule
            );
            $overtimeAlert = collect($alerts)->firstWhere('type', 'overtime');
            if ($overtimeAlert && $overtimeAlert['minutes'] >= 15) {
                return [
                    'attendance' => $att,
                    'overtime_minutes' => $overtimeAlert['minutes'],
                    'work_end_time' => $rule['work_end_time'],
                ];
            }
            return null;
        })->filter()->values();
    }

    /**
     * Get active users who were expected to work but have no clock-in for the date.
     *
     * Detection logic:
     * 1. If a shift assignment exists for the date → should have worked
     * 2. Otherwise, if the date is a weekday → should have worked (default)
     * 3. Exclude users who have any attendance record for the date
     */
    public function getMissingClockIns(?string $date = null): Collection
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::yesterday();

        $activeUsers = User::where('is_active', true)
            ->with('department')
            ->get();

        // Users who clocked in on the target date
        $clockedInUserIds = Attendance::whereDate('clock_in', $targetDate)
            ->pluck('user_id')
            ->unique()
            ->toArray();

        // Users with shift assignments on the target date
        $shiftUserIds = ShiftAssignment::whereDate('date', $targetDate)
            ->pluck('user_id')
            ->unique()
            ->toArray();

        $isWeekday = $targetDate->isWeekday();

        return $activeUsers->filter(function ($user) use ($clockedInUserIds, $shiftUserIds, $isWeekday) {
            // Already clocked in → not missing
            if (in_array($user->id, $clockedInUserIds)) {
                return false;
            }

            // Has shift assignment → should have worked
            if (in_array($user->id, $shiftUserIds)) {
                return true;
            }

            // No shift assignment but it's a weekday → default expected
            return $isWeekday;
        })->values();
    }

    /**
     * Returns alert counts for navbar badge (yesterday's alerts).
     */
    public function getAlertCounts(): array
    {
        return [
            'missing_clock_ins' => $this->getMissingClockIns()->count(),
            'missing_clock_outs' => $this->getMissingClockOuts()->count(),
            'shift_overtime' => $this->getShiftOvertime()->count(),
        ];
    }
}
