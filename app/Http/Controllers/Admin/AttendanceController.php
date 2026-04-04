<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\BreakRecord;
use App\Models\Department;
use App\Models\User;
use App\Services\TimeService;
use App\Services\WorkRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function __construct(
        private TimeService $timeService,
        private WorkRuleService $workRuleService,
    ) {}

    public function index(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);
        $departmentId = $request->input('department_id');
        $currentUser = $request->user();

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        // Manager can only view their own department
        if ($currentUser->role === Role::MANAGER) {
            $departmentId = $currentUser->department_id;
        }

        $query = Attendance::with([
                'user.department',
                'breakRecords',
                'auditLogs' => fn ($q) => $q->where('action', 'updated')->with('user')->latest(),
            ])
            ->withCount(['auditLogs as modification_count' => fn ($q) => $q->where('action', 'updated')])
            ->whereBetween('clock_in', [$startOfMonth, $endOfMonth])
            ->orderBy('clock_in');

        if ($departmentId) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId));
        }

        $attendances = $query->get()->groupBy('user_id');

        $users = User::with('department')
            ->whereIn('id', $attendances->keys())
            ->orderBy('name')
            ->get();

        // Managers only see their own department in the filter
        if ($currentUser->role === Role::MANAGER) {
            $departments = Department::where('id', $currentUser->department_id)->get();
        } else {
            $departments = Department::orderBy('name')->get();
        }

        return view('admin.attendance.index', compact(
            'attendances', 'users', 'departments', 'year', 'month', 'departmentId'
        ));
    }

    public function update(Request $request, Attendance $attendance)
    {
        $request->validate([
            'clock_in' => 'required|date',
            'clock_out' => 'nullable|date|after:clock_in',
            'session_number' => 'nullable|integer|min:1|max:10',
            'note' => 'nullable|string|max:500',
            'reason' => 'nullable|string|max:500',
        ]);

        $data = [
            'clock_in' => $request->clock_in,
            'clock_out' => $request->clock_out ?: null,
            'note' => $request->note,
        ];

        if ($request->filled('session_number')) {
            $data['session_number'] = $request->session_number;
        }

        DB::transaction(function () use ($attendance, $data, $request) {
            $attendance->update($data);

            // Set reason on the audit log
            if ($request->filled('reason')) {
                $attendance->auditLogs()->latest()->first()?->update(['reason' => $request->reason]);
            }
        });

        return back()->with('success', '打刻を修正しました');
    }

    public function export(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);
        $departmentId = $request->input('department_id');
        $format = $request->input('format', 'standard');
        $currentUser = $request->user();

        // Manager can only export their own department
        if ($currentUser->role === Role::MANAGER) {
            $departmentId = $currentUser->department_id;
        }

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth()->endOfDay();

        $query = Attendance::with(['user.department', 'breakRecords'])
            ->whereBetween('clock_in', [$startOfMonth, $endOfMonth])
            ->orderBy('clock_in');

        if ($departmentId) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId));
        }

        $attendances = $query->get();

        if ($format === 'tkc') {
            $filename = "tkc_{$year}_{$month}.csv";
        } else {
            $filename = "attendance_{$year}_{$month}.csv";
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        if ($format === 'tkc') {
            $callback = function () use ($attendances) {
                $file = fopen('php://output', 'w');
                fwrite($file, "\xEF\xBB\xBF");
                fputcsv($file, [
                    '社員番号',
                    '氏名',
                    '所属',
                    '年月日',
                    '出勤時刻',
                    '退勤時刻',
                    '休憩時間',
                    '所定内労働時間',
                    '時間外労働（法定内）',
                    '時間外労働（法定外）',
                    '深夜労働',
                    '有給取得日数',
                    '備考',
                ]);

                foreach ($attendances as $att) {
                    $rule = $this->workRuleService->resolve($att->user_id);
                    $rounding = [
                        'rounding_unit' => $rule['rounding_unit'],
                        'clock_in_rounding' => $rule['clock_in_rounding'],
                        'clock_out_rounding' => $rule['clock_out_rounding'],
                    ];

                    $workMin = $this->timeService->calculateWorkingMinutesWithRounding(
                        $att->clock_in, $att->clock_out, $att->breakRecords, $rounding
                    );
                    $breakMin = $this->timeService->calculateBreakMinutes($att->breakRecords);

                    // Standard day minutes from work rule (work_start to work_end minus default break)
                    [$startH, $startM] = explode(':', $rule['work_start_time']);
                    [$endH, $endM] = explode(':', $rule['work_end_time']);
                    $standardDayMinutes = ((int)$endH * 60 + (int)$endM) - ((int)$startH * 60 + (int)$startM) - $rule['default_break_minutes'];
                    $standardDayMinutes = max(0, $standardDayMinutes);

                    $normalMin = $workMin !== null ? min($workMin, $standardDayMinutes) : 0;
                    $overtimeMin = $workMin !== null ? max(0, $workMin - $standardDayMinutes) : 0;

                    // Effective clock_in after rounding
                    $roundedTimes = $this->timeService->getRoundedTimes($att->clock_in, $att->clock_out, $rounding);
                    $effectiveClockIn = $roundedTimes['rounded_clock_in'];

                    fputcsv($file, [
                        $att->user->employee_number,
                        $att->user->name,
                        $att->user->department?->name ?? '',
                        $att->clock_in->format('Y/m/d'),
                        $effectiveClockIn->format('H:i'),
                        $att->clock_out?->format('H:i') ?? '',
                        $breakMin,
                        $normalMin,
                        0, // 時間外労働（法定内）: not implemented yet
                        $overtimeMin, // 時間外労働（法定外）
                        0, // 深夜労働: not implemented yet
                        0, // 有給取得日数: not implemented yet
                        $att->note ?? '',
                    ]);
                }
                fclose($file);
            };
        } else {
            $callback = function () use ($attendances) {
                $file = fopen('php://output', 'w');
                // BOM for UTF-8
                fwrite($file, "\xEF\xBB\xBF");
                fputcsv($file, ['社員番号', '名前', '部署', '日付', '回', '出勤', '退勤', '休憩(分)', '実働(分)', '備考']);

                foreach ($attendances as $att) {
                    $rule = $this->workRuleService->resolve($att->user_id);
                    $rounding = [
                        'rounding_unit' => $rule['rounding_unit'],
                        'clock_in_rounding' => $rule['clock_in_rounding'],
                        'clock_out_rounding' => $rule['clock_out_rounding'],
                    ];
                    $breakMin = $this->timeService->calculateBreakMinutes($att->breakRecords);
                    $workMin = $this->timeService->calculateWorkingMinutesWithCutoff(
                        $att->clock_in, $att->clock_out, $att->breakRecords, $rounding, $rule
                    );

                    fputcsv($file, [
                        $att->user->employee_number,
                        $att->user->name,
                        $att->user->department?->name ?? '',
                        $att->clock_in->format('Y-m-d'),
                        $att->session_number,
                        $att->clock_in->format('H:i'),
                        $att->clock_out?->format('H:i') ?? '',
                        $breakMin,
                        $workMin ?? '',
                        $att->note ?? '',
                    ]);
                }
                fclose($file);
            };
        }

        return response()->stream($callback, 200, $headers);
    }

    public function addBreak(Request $request, Attendance $attendance)
    {
        $request->validate([
            'break_start' => 'required|date',
            'break_end' => 'nullable|date|after:break_start',
        ]);

        BreakRecord::create([
            'attendance_id' => $attendance->id,
            'break_start' => $request->break_start,
            'break_end' => $request->break_end ?: null,
        ]);

        return back()->with('success', '休憩を追加しました');
    }

    public function updateBreak(Request $request, BreakRecord $breakRecord)
    {
        $request->validate([
            'break_start' => 'required|date',
            'break_end' => 'nullable|date|after:break_start',
        ]);

        $breakRecord->update([
            'break_start' => $request->break_start,
            'break_end' => $request->break_end ?: null,
        ]);

        return back()->with('success', '休憩を更新しました');
    }

    public function deleteBreak(BreakRecord $breakRecord)
    {
        $breakRecord->delete();
        return back()->with('success', '休憩を削除しました');
    }
}
