<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BreakController extends Controller
{
    public function start(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $today)
            ->whereNull('clock_out')
            ->with('breakRecords')
            ->first();

        if (!$attendance) {
            return back()->with('error', '出勤記録がありません');
        }

        $activeBreak = $attendance->breakRecords->whereNull('break_end')->first();
        if ($activeBreak) {
            return back()->with('error', '既に休憩中です');
        }

        BreakRecord::create([
            'attendance_id' => $attendance->id,
            'break_start' => Carbon::now(),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ]);

        return back()->with('success', '休憩を開始しました');
    }

    public function end(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $today)
            ->whereNull('clock_out')
            ->with('breakRecords')
            ->first();

        if (!$attendance) {
            return back()->with('error', '出勤記録がありません');
        }

        $activeBreak = $attendance->breakRecords->whereNull('break_end')->first();
        if (!$activeBreak) {
            return back()->with('error', '休憩中ではありません');
        }

        $activeBreak->update([
            'break_end' => Carbon::now(),
            'end_latitude' => $request->input('latitude'),
            'end_longitude' => $request->input('longitude'),
        ]);

        return back()->with('success', '休憩を終了しました');
    }
}
