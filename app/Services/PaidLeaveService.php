<?php

namespace App\Services;

use App\Models\PaidLeaveBalance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PaidLeaveService
{
    /**
     * 法定有給付与日数テーブル（通常労働者: 週5日以上 or 年217日以上）
     */
    const STANDARD_GRANT_TABLE = [
        0.5 => 10, // 6ヶ月
        1.5 => 11,
        2.5 => 12,
        3.5 => 14,
        4.5 => 16,
        5.5 => 18,
        6.5 => 20, // 以降毎年20日
    ];

    /**
     * パート比例付与テーブル（週所定労働日数別）
     * [週労働日数 => [勤続年数 => 付与日数]]
     */
    const PART_TIME_GRANT_TABLE = [
        4 => [0.5 => 7, 1.5 => 8, 2.5 => 9, 3.5 => 10, 4.5 => 12, 5.5 => 13, 6.5 => 15],
        3 => [0.5 => 5, 1.5 => 6, 2.5 => 6, 3.5 => 8, 4.5 => 9, 5.5 => 10, 6.5 => 11],
        2 => [0.5 => 3, 1.5 => 4, 2.5 => 4, 3.5 => 5, 4.5 => 6, 5.5 => 6, 6.5 => 7],
        1 => [0.5 => 1, 1.5 => 2, 2.5 => 2, 3.5 => 2, 4.5 => 3, 5.5 => 3, 6.5 => 3],
    ];

    /**
     * 入社日と週所定労働日数から法定付与日数を計算
     */
    public function calculateGrantDays(User $user, Carbon $grantDate): int
    {
        if (!$user->hire_date) {
            return 0;
        }

        $yearsWorked = $user->hire_date->diffInMonths($grantDate) / 12;
        $weeklyDays = $user->weekly_work_days ?? 5;

        if ($weeklyDays >= 5) {
            $table = self::STANDARD_GRANT_TABLE;
        } else {
            $table = self::PART_TIME_GRANT_TABLE[(int) $weeklyDays] ?? [];
        }

        $grantDays = 0;
        foreach ($table as $years => $days) {
            if ($yearsWorked >= $years) {
                $grantDays = $days;
            }
        }

        return $grantDays;
    }

    /**
     * ユーザーの有効残日数合計を取得
     */
    public function getRemainingDays(User $user): float
    {
        return PaidLeaveBalance::where('user_id', $user->id)
            ->active()
            ->get()
            ->sum(fn ($b) => $b->remainingDays());
    }

    /**
     * 有給消化（古い残高から順に消費 - FIFO）
     */
    public function useDays(User $user, float $days): void
    {
        DB::transaction(function () use ($user, $days) {
            $remaining = $days;
            $balances = PaidLeaveBalance::where('user_id', $user->id)
                ->active()
                ->whereColumn('used_days', '<', 'granted_days')
                ->orderBy('grant_date')
                ->lockForUpdate()
                ->get();

            foreach ($balances as $balance) {
                $available = (float) $balance->granted_days - (float) $balance->used_days;
                $use = min($remaining, $available);
                $balance->increment('used_days', $use);
                $remaining -= $use;
                if ($remaining <= 0) {
                    break;
                }
            }
        });
    }

    /**
     * 有給消化を戻す（却下・取消時）
     */
    public function returnDays(User $user, float $days): void
    {
        DB::transaction(function () use ($user, $days) {
            $remaining = $days;
            $balances = PaidLeaveBalance::where('user_id', $user->id)
                ->active()
                ->where('used_days', '>', 0)
                ->orderByDesc('grant_date')
                ->lockForUpdate()
                ->get();

            foreach ($balances as $balance) {
                $canReturn = (float) $balance->used_days;
                $returnAmount = min($remaining, $canReturn);
                $balance->decrement('used_days', $returnAmount);
                $remaining -= $returnAmount;
                if ($remaining <= 0) {
                    break;
                }
            }
        });
    }
}
