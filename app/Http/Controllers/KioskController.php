<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\User;
use App\Services\WorkRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class KioskController extends Controller
{
    public function __construct(private WorkRuleService $workRuleService) {}

    public function index()
    {
        $departments = Department::orderBy('name')->get();
        return view('kiosk.index', compact('departments'));
    }

    public function manifest(Department $department)
    {
        $manifest = [
            'name' => config('app.name') . ' - ' . $department->name,
            'short_name' => $department->name,
            'description' => $department->name . ' 勤怠打刻キオスク',
            'start_url' => route('kiosk.department', $department),
            'scope' => url('/kiosk/'),
            'display' => 'standalone',
            'background_color' => '#f0f9ff',
            'theme_color' => '#38bdf8',
            'orientation' => 'portrait',
            'icons' => [
                [
                    'src' => asset('icons/icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('icons/icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('icons/icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => asset('icons/icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }

    public function department(Department $department)
    {
        $this->setTenantFromDepartment($department);
        return view('kiosk.department', compact('department'));
    }

    public function lookup(Request $request, Department $department)
    {
        $this->setTenantFromDepartment($department);

        $key = 'kiosk_pin:' . $request->ip() . ':' . $department->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => '試行回数が上限を超えました。しばらく待ってからお試しください',
            ], 429);
        }

        $request->validate([
            'kiosk_code' => 'required|digits:4',
        ]);

        $user = User::where('kiosk_code', $request->kiosk_code)
            ->where('department_id', $department->id)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            RateLimiter::hit($key, 300);
            return response()->json(['success' => false, 'message' => '該当するユーザーが見つかりません']);
        }

        RateLimiter::clear($key);

        $today = Carbon::today();
        $rule = $this->workRuleService->resolve($user->id);

        $todayAttendances = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $today)
            ->with('breakRecords')
            ->orderBy('clock_in', 'desc')
            ->get();

        $attendance = $todayAttendances->first();
        $totalSessions = $todayAttendances->count();
        $activeSession = $todayAttendances->whereNull('clock_out')->first();

        $status = 'not_started';
        if ($activeSession) {
            $activeBreak = $activeSession->breakRecords->whereNull('break_end')->first();
            $status = $activeBreak ? 'on_break' : 'clocked_in';
        } elseif ($attendance) {
            $status = 'clocked_out';
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_number' => $user->employee_number,
            ],
            'status' => $status,
            'session' => [
                'current' => $activeSession ? $activeSession->session_number : null,
                'total' => $totalSessions,
                'allow_multiple' => (bool) $rule['allow_multiple_clock_ins'],
            ],
        ]);
    }

    public function clockIn(Request $request, Department $department)
    {
        $this->setTenantFromDepartment($department);
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::where('id', $request->user_id)
            ->where('department_id', $department->id)
            ->where('is_active', true)
            ->firstOrFail();

        $today = Carbon::today();
        $rule = $this->workRuleService->resolve($user->id);

        $existing = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $today)
            ->whereNull('clock_out')
            ->exists();

        if ($existing) {
            return response()->json(['success' => false, 'message' => '既に出勤中です']);
        }

        if (!$rule['allow_multiple_clock_ins']) {
            $todayRecord = Attendance::where('user_id', $user->id)
                ->whereDate('clock_in', $today)
                ->exists();
            if ($todayRecord) {
                return response()->json(['success' => false, 'message' => '本日は既に打刻済みです']);
            }
        }

        $sessionNumber = 1;
        if ($rule['allow_multiple_clock_ins']) {
            $maxSession = Attendance::where('user_id', $user->id)
                ->whereDate('clock_in', $today)
                ->max('session_number');
            $sessionNumber = ($maxSession ?? 0) + 1;
        }

        Attendance::create([
            'user_id' => $user->id,
            'session_number' => $sessionNumber,
            'clock_in' => Carbon::now(),
            'clock_in_lat' => $request->input('latitude'),
            'clock_in_lng' => $request->input('longitude'),
        ]);

        return response()->json(['success' => true, 'message' => '出勤しました']);
    }

    public function clockOut(Request $request, Department $department)
    {
        $this->setTenantFromDepartment($department);
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::where('id', $request->user_id)
            ->where('department_id', $department->id)
            ->where('is_active', true)
            ->firstOrFail();

        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $today)
            ->whereNull('clock_out')
            ->with('breakRecords')
            ->orderBy('clock_in', 'desc')
            ->first();

        if (!$attendance) {
            return response()->json(['success' => false, 'message' => '出勤記録がありません']);
        }

        $activeBreak = $attendance->breakRecords->whereNull('break_end')->first();
        if ($activeBreak) {
            $activeBreak->update(['break_end' => Carbon::now()]);
        }

        $attendance->update([
            'clock_out' => Carbon::now(),
            'clock_out_lat' => $request->input('latitude'),
            'clock_out_lng' => $request->input('longitude'),
        ]);

        return response()->json(['success' => true, 'message' => '退勤しました']);
    }

    private function setTenantFromDepartment(Department $department): void
    {
        if ($department->tenant_id) {
            app()->instance('current_tenant_id', $department->tenant_id);
        }
    }
}
