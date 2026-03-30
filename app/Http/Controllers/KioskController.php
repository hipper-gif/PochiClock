<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\User;
use App\Services\WorkRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KioskController extends Controller
{
    public function __construct(private WorkRuleService $workRuleService) {}

    public function index()
    {
        $departments = Department::orderBy('name')->get();
        return view('kiosk.index', compact('departments'));
    }

    public function department(Department $department)
    {
        // Kiosk routes are unauthenticated; set tenant context from the department
        $this->setTenantFromDepartment($department);

        return view('kiosk.department', compact('department'));
    }

    public function lookup(Request $request, Department $department)
    {
        $this->setTenantFromDepartment($department);
        $request->validate([
            'kiosk_code' => 'required|digits:4',
        ]);

        $user = User::where('kiosk_code', $request->kiosk_code)
            ->where('department_id', $department->id)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => '該当するユーザーが見つかりません']);
        }

        $today = Carbon::today();
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in', $today)
            ->with('breakRecords')
            ->orderBy('clock_in', 'desc')
            ->first();

        $status = 'not_started';
        if ($attendance) {
            if (!$attendance->clock_out) {
                $activeBreak = $attendance->breakRecords->whereNull('break_end')->first();
                $status = $activeBreak ? 'on_break' : 'clocked_in';
            } else {
                $status = 'clocked_out';
            }
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_number' => $user->employee_number,
            ],
            'status' => $status,
        ]);
    }

    public function clockIn(Request $request, Department $department)
    {
        $this->setTenantFromDepartment($department);
        $request->validate(['user_id' => 'required|exists:users,id']);

        $user = User::findOrFail($request->user_id);
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

        Attendance::create([
            'user_id' => $user->id,
            'clock_in' => Carbon::now(),
        ]);

        return response()->json(['success' => true, 'message' => '出勤しました']);
    }

    public function clockOut(Request $request, Department $department)
    {
        $this->setTenantFromDepartment($department);
        $request->validate(['user_id' => 'required|exists:users,id']);

        $user = User::findOrFail($request->user_id);
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

        // アクティブな休憩を自動終了
        $activeBreak = $attendance->breakRecords->whereNull('break_end')->first();
        if ($activeBreak) {
            $activeBreak->update(['break_end' => Carbon::now()]);
        }

        $attendance->update(['clock_out' => Carbon::now()]);

        return response()->json(['success' => true, 'message' => '退勤しました']);
    }

    /**
     * Set the tenant context from a department (for unauthenticated kiosk routes).
     */
    private function setTenantFromDepartment(Department $department): void
    {
        if ($department->tenant_id) {
            app()->instance('current_tenant_id', $department->tenant_id);
        }
    }
}
