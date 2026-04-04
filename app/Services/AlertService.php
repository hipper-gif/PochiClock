<?php

namespace App\Services;

use App\Models\Attendance;
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
     * Returns alert counts for navbar badge (yesterday's alerts).
     */
    public function getAlertCounts(): array
    {
        return [
            'missing_clock_outs' => $this->getMissingClockOuts()->count(),
            'shift_overtime' => $this->getShiftOvertime()->count(),
        ];
    }
}
