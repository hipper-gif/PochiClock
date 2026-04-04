<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\User;
use App\Services\WorkRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class QrScannerController extends Controller
{
    public function __construct(private WorkRuleService $workRuleService) {}

    public function index(Department $department)
    {
        return view('kiosk.qr-scanner', compact('department'));
    }

    public function verify(Request $request)
    {
        $key = 'qr_verify:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response()->json(['error' => '試行回数の上限を超えました'], 429);
        }
        RateLimiter::hit($key, 60);

        $request->validate(['qr_token' => 'required|string']);

        $user = User::where('qr_token', $request->qr_token)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return response()->json(['error' => '無効なQRコードです'], 404);
        }

        $today = Carbon::today();
        $rule = $this->workRuleService->resolve($user->id);

        $activeAttendance = $user->attendances()
            ->whereDate('clock_in', $today)
            ->whereNull('clock_out')
            ->latest()
            ->first();

        if ($activeAttendance) {
            // 退勤: アクティブな休憩を自動終了
            $activeBreak = $activeAttendance->breakRecords->whereNull('break_end')->first();
            if ($activeBreak) {
                $activeBreak->update(['break_end' => Carbon::now()]);
            }

            $activeAttendance->update([
                'clock_out' => Carbon::now(),
            ]);

            return response()->json([
                'action' => 'clock_out',
                'user_name' => $user->name,
                'time' => now()->format('H:i'),
                'message' => $user->name . 'さん、お疲れさまでした！',
            ]);
        }

        // 出勤: 複数打刻が許可されていない場合のチェック
        if (! $rule['allow_multiple_clock_ins']) {
            $todayRecord = $user->attendances()
                ->whereDate('clock_in', $today)
                ->exists();
            if ($todayRecord) {
                return response()->json(['error' => '本日は既に打刻済みです'], 400);
            }
        }

        $sessionNumber = $user->attendances()
            ->whereDate('clock_in', $today)
            ->max('session_number') ?? 0;

        Attendance::create([
            'user_id' => $user->id,
            'clock_in' => Carbon::now(),
            'session_number' => $sessionNumber + 1,
        ]);

        return response()->json([
            'action' => 'clock_in',
            'user_name' => $user->name,
            'time' => now()->format('H:i'),
            'message' => $user->name . 'さん、おはようございます！',
        ]);
    }
}
