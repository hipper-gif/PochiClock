<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\WorkRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function __construct(private WorkRuleService $workRuleService) {}

    public function clockIn(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today();

        $rule = $this->workRuleService->resolve($user->id);

        $existing = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $today)
            ->whereNull('clock_out')
            ->first();

        if ($existing) {
            return back()->with('error', '既に出勤中です');
        }

        if (!$rule['allow_multiple_clock_ins']) {
            $todayRecord = Attendance::where('user_id', $user->id)
                ->whereDate('clock_in', $today)
                ->exists();
            if ($todayRecord) {
                return back()->with('error', '本日は既に打刻済みです');
            }
        }

        Attendance::create([
            'user_id' => $user->id,
            'clock_in' => Carbon::now(),
            'clock_in_lat' => $request->input('latitude'),
            'clock_in_lng' => $request->input('longitude'),
        ]);

        return back()->with('success', '出勤しました');
    }

    public function clockOut(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $today)
            ->whereNull('clock_out')
            ->orderBy('clock_in', 'desc')
            ->first();

        if (!$attendance) {
            return back()->with('error', '出勤記録がありません');
        }

        // アクティブな休憩を自動終了
        $attendance->load('breakRecords');
        $activeBreak = $attendance->breakRecords->whereNull('break_end')->first();
        if ($activeBreak) {
            $activeBreak->update([
                'break_end' => Carbon::now(),
                'end_latitude' => $request->input('latitude'),
                'end_longitude' => $request->input('longitude'),
            ]);
        }

        $attendance->update([
            'clock_out' => Carbon::now(),
            'clock_out_lat' => $request->input('latitude'),
            'clock_out_lng' => $request->input('longitude'),
        ]);

        return back()->with('success', '退勤しました');
    }
}
