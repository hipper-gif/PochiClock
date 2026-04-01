<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimeService
{
    public function roundTime(Carbon $date, int $unitMinutes, string $direction): Carbon
    {
        if ($direction === 'none' || $unitMinutes <= 1) {
            return $date->copy();
        }

        $ms = $unitMinutes * 60;
        $timestamp = $date->timestamp;

        if ($direction === 'floor') {
            $rounded = intdiv($timestamp, $ms) * $ms;
        } else {
            $rounded = (int) ceil($timestamp / $ms) * $ms;
        }

        return Carbon::createFromTimestamp($rounded, $date->timezone);
    }

    public function calculateBreakMinutes(Collection $breaks): int
    {
        $total = 0;
        foreach ($breaks as $br) {
            if ($br->break_end) {
                $total += $br->break_start->diffInMinutes($br->break_end);
            }
        }
        return $total;
    }

    public function calculateWorkingMinutes(Carbon $clockIn, ?Carbon $clockOut, Collection $breaks): ?int
    {
        if (!$clockOut) {
            return null;
        }

        $totalMinutes = $clockIn->diffInMinutes($clockOut);
        $breakMinutes = $this->calculateBreakMinutes($breaks);

        return max(0, $totalMinutes - $breakMinutes);
    }

    public function calculateWorkingMinutesWithRounding(
        Carbon $clockIn,
        ?Carbon $clockOut,
        Collection $breaks,
        array $rounding
    ): ?int {
        if (!$clockOut) {
            return null;
        }

        $roundedIn = $this->roundTime($clockIn, $rounding['rounding_unit'], $rounding['clock_in_rounding']);
        $roundedOut = $this->roundTime($clockOut, $rounding['rounding_unit'], $rounding['clock_out_rounding']);

        $totalMinutes = $roundedIn->diffInMinutes($roundedOut);
        $breakMinutes = $this->calculateBreakMinutes($breaks);

        return max(0, $totalMinutes - $breakMinutes);
    }

    public function getRoundedTimes(Carbon $clockIn, ?Carbon $clockOut, array $rounding): array
    {
        return [
            'rounded_clock_in' => $this->roundTime($clockIn, $rounding['rounding_unit'], $rounding['clock_in_rounding']),
            'rounded_clock_out' => $clockOut
                ? $this->roundTime($clockOut, $rounding['rounding_unit'], $rounding['clock_out_rounding'])
                : null,
        ];
    }

    /**
     * Calculate overtime minutes for a single attendance session.
     */
    public function calculateOvertimeMinutes(
        Carbon $clockIn,
        ?Carbon $clockOut,
        Collection $breaks,
        array $rounding,
        array $rule,
        int $sessionNumber = 1
    ): int {
        if (!$clockOut) return 0;

        $wm = $this->calculateWorkingMinutesWithRounding($clockIn, $clockOut, $breaks, $rounding);
        if ($wm === null) return 0;

        [$sh, $sm] = explode(':', $rule['work_start_time']);
        [$eh, $em] = explode(':', $rule['work_end_time']);
        $standardMinutes = ((int)$eh * 60 + (int)$em) - ((int)$sh * 60 + (int)$sm) - (int)($rule['default_break_minutes'] ?? 60);

        return max(0, $wm - $standardMinutes);
    }

    /**
     * Calculate total overtime for a collection of attendances (cross-day).
     */
    public function calculateTotalOvertimeMinutes(
        Collection $attendances,
        array $rounding,
        array $rule
    ): int {
        $total = 0;
        foreach ($attendances as $att) {
            if ($att->clock_out) {
                $total += $this->calculateOvertimeMinutes(
                    $att->clock_in, $att->clock_out, $att->breakRecords,
                    $rounding, $rule, $att->session_number ?? 1
                );
            }
        }
        return $total;
    }

    public function detectAttendanceAlerts(Carbon $clockIn, ?Carbon $clockOut, array $rule): array
    {
        $alerts = [];

        $cinMinutes = $clockIn->hour * 60 + $clockIn->minute;
        [$startH, $startM] = explode(':', $rule['work_start_time']);
        $startMinutes = (int) $startH * 60 + (int) $startM;

        if ($cinMinutes - $startMinutes >= 5) {
            $alerts[] = ['type' => 'late', 'minutes' => $cinMinutes - $startMinutes];
        }

        if ($clockOut) {
            $coutMinutes = $clockOut->hour * 60 + $clockOut->minute;
            [$endH, $endM] = explode(':', $rule['work_end_time']);
            $endMinutes = (int) $endH * 60 + (int) $endM;

            if ($endMinutes - $coutMinutes >= 5) {
                $alerts[] = ['type' => 'early_leave', 'minutes' => $endMinutes - $coutMinutes];
            }

            if ($coutMinutes - $endMinutes >= 15) {
                $alerts[] = ['type' => 'overtime', 'minutes' => $coutMinutes - $endMinutes];
            }
        }

        return $alerts;
    }
}
