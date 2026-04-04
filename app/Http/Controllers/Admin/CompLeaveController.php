<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CompLeave;
use App\Models\Department;
use App\Models\User;
use App\Services\TimeService;
use App\Services\WorkRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CompLeaveController extends Controller
{
    public function __construct(
        private TimeService $timeService,
        private WorkRuleService $workRuleService,
    ) {}

    public function index(Request $request)
    {
        $year         = (int) $request->input('year', now()->year);
        $departmentId = $request->input('department_id');

        $departments = Department::orderBy('name')->get();
        $usersQuery  = User::active()->with('department');
        if ($departmentId) {
            $usersQuery->where('department_id', $departmentId);
        }
        $users = $usersQuery->orderBy('name')->get();

        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();

        $yearlyAtts = Attendance::whereBetween('clock_in', [$yearStart, $yearEnd])
            ->whereIn('user_id', $users->pluck('id'))
            ->with('breakRecords')
            ->get()
            ->groupBy('user_id');

        $compLeaves = CompLeave::whereBetween('leave_date', [$yearStart, $yearEnd])
            ->whereIn('user_id', $users->pluck('id'))
            ->with('approver')
            ->get()
            ->groupBy('user_id');

        $data = [];
        foreach ($users as $user) {
            $rule = $this->workRuleService->resolve($user->id);
            $rounding = [
                'rounding_unit'      => $rule['rounding_unit'],
                'clock_in_rounding'  => $rule['clock_in_rounding'],
                'clock_out_rounding' => $rule['clock_out_rounding'],
            ];

            $userAtts    = $yearlyAtts->get($user->id, collect());
            $otMinutes   = $this->timeService->calculateTotalOvertimeMinutes($userAtts, $rounding, $rule);
            $otHours     = $otMinutes / 60;

            $userLeaves  = $compLeaves->get($user->id, collect());
            $usedHours   = (float) $userLeaves->sum('hours');
            $remainHours = max(0, $otHours - $usedHours);

            $data[] = [
                'user'           => $user,
                'overtime_hours' => round($otHours, 1),
                'used_hours'     => $usedHours,
                'remain_hours'   => round($remainHours, 1),
                'leaves'         => $userLeaves->sortBy('leave_date'),
            ];
        }

        return view('admin.comp-leaves.index', compact(
            'data', 'departments', 'departmentId', 'year', 'users'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'leave_date' => 'required|date',
            'hours'      => 'required|numeric|min:0.5|max:24',
            'note'       => 'nullable|string|max:255',
        ]);

        CompLeave::create([
            ...$validated,
            'approved_by' => auth()->id(),
        ]);

        return back()->with('success', '振替を登録しました');
    }

    public function destroy(CompLeave $compLeave)
    {
        $compLeave->delete();
        return back()->with('success', '振替を削除しました');
    }
}
