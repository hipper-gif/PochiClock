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
     * Apply early clock-in cutoff.
     * If clock-in is before the cutoff time, round it forward to the cutoff.
     */
    public function applyEarlyCutoff(Carbon $clockIn, ?string $cutoff, ?string $cutoffPm = null, int $sessionNumber = 1): Carbon
    {
        if ($sessionNumber >= 2 && $cutoffPm) {
            $cutoffTime = $cutoffPm;
        } elseif ($cutoff) {
            $cutoffTime = $cutoff;
        } else {
            return $clockIn->copy();
        }

        $cutoffCarbon = $clockIn->copy()->setTimeFromTimeString($cutoffTime);

        if ($clockIn->lt($cutoffCarbon)) {
            return $cutoffCarbon;
        }

        return $clockIn->copy();
    }

    /**
     * Get effective clock-in time after applying early cutoff.
     */
    public function getEffectiveClockIn(Carbon $clockIn, array $rule, int $sessionNumber = 1): Carbon
    {
        return $this->applyEarlyCutoff(
            $clockIn,
            $rule['early_clock_in_cutoff'] ?? null,
            $rule['early_clock_in_cutoff_pm'] ?? null,
            $sessionNumber
        );
    }

    /**
     * Get rounded times with early cutoff applied first.
     */
    public function getRoundedTimesWithCutoff(Carbon $clockIn, ?Carbon $clockOut, array $rounding, array $rule, int $sessionNumber = 1): array
    {
        $effectiveClockIn = $this->getEffectiveClockIn($clockIn, $rule, $sessionNumber);

        return [
            'rounded_clock_in' => $this->roundTime($effectiveClockIn, $rounding['rounding_unit'], $rounding['clock_in_rounding']),
            'rounded_clock_out' => $clockOut
                ? $this->roundTime($clockOut, $rounding['rounding_unit'], $rounding['clock_out_rounding'])
                : null,
            'cutoff_applied' => !$effectiveClockIn->eq($clockIn),
            'actual_clock_in' => $clockIn->copy(),
        ];
    }

    /**
     * Calculate working minutes with early cutoff and rounding applied.
     */
    public function calculateWorkingMinutesWithCutoff(
        Carbon $clockIn,
        ?Carbon $clockOut,
        Collection $breaks,
        array $rounding,
        array $rule,
        int $sessionNumber = 1
    ): ?int {
        if (!$clockOut) {
            return null;
        }

        $effectiveClockIn = $this->getEffectiveClockIn($clockIn, $rule, $sessionNumber);

        $roundedIn = $this->roundTime($effectiveClockIn, $rounding['rounding_unit'], $rounding['clock_in_rounding']);
        $roundedOut = $this->roundTime($clockOut, $rounding['rounding_unit'], $rounding['clock_out_rounding']);

        $totalMinutes = $roundedIn->diffInMinutes($roundedOut);
        $breakMinutes = $this->calculateBreakMinutes($breaks);

        return max(0, $totalMinutes - $breakMinutes);
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
