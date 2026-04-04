<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ShiftAssignment;
use App\Models\ShiftTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    /**
     * シフトカレンダー表示
     */
    public function index(Request $request)
    {
        $year = (int) $request->input('year', Carbon::now()->year);
        $month = (int) $request->input('month', Carbon::now()->month);
        $departmentId = $request->input('department_id');

        $startOfMonth = Carbon::create($year, $month, 1);
        $daysInMonth = $startOfMonth->daysInMonth;

        $departments = Department::orderBy('name')->get();
        $templates = ShiftTemplate::orderBy('name')->get();

        $usersQuery = User::active()->orderBy('name');
        if ($departmentId) {
            $usersQuery->where('department_id', $departmentId);
        }
        $users = $usersQuery->get();

        // 月内の全シフト割当を取得
        $assignments = ShiftAssignment::with('shiftTemplate')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get()
            ->groupBy('user_id');

        // 日付ごとの曜日情報
        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d);
            $days[] = [
                'day' => $d,
                'dow' => $date->dayOfWeek, // 0=Sun, 6=Sat
                'date' => $date->format('Y-m-d'),
            ];
        }

        return view('admin.shifts.index', compact(
            'year', 'month', 'daysInMonth', 'departments', 'departmentId',
            'templates', 'users', 'assignments', 'days'
        ));
    }

    /**
     * テンプレート管理画面
     */
    public function templates(Request $request)
    {
        $templates = ShiftTemplate::orderBy('name')->get();

        return view('admin.shifts.templates', compact('templates'));
    }

    /**
     * テンプレート作成
     */
    public function storeTemplate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'required|string|max:7',
            'start_time' => 'required|string|size:5',
            'end_time' => 'required|string|size:5',
            'break_minutes' => 'required|integer|min:0',
        ]);

        ShiftTemplate::create($validated);

        return redirect()->route('admin.shifts.templates')
            ->with('success', 'シフトテンプレートを作成しました');
    }

    /**
     * テンプレート更新
     */
    public function updateTemplate(Request $request, ShiftTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'required|string|max:7',
            'start_time' => 'required|string|size:5',
            'end_time' => 'required|string|size:5',
            'break_minutes' => 'required|integer|min:0',
        ]);

        $template->update($validated);

        return redirect()->route('admin.shifts.templates')
            ->with('success', 'シフトテンプレートを更新しました');
    }

    /**
     * テンプレート削除
     */
    public function destroyTemplate(ShiftTemplate $template)
    {
        $template->delete();

        return redirect()->route('admin.shifts.templates')
            ->with('success', 'シフトテンプレートを削除しました');
    }

    /**
     * シフト割当（単一）
     */
    public function assign(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'shift_template_id' => 'required|uuid|exists:shift_templates,id',
            'date' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        ShiftAssignment::updateOrCreate(
            ['user_id' => $validated['user_id'], 'date' => $validated['date']],
            [
                'shift_template_id' => $validated['shift_template_id'],
                'note' => $validated['note'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'シフトを割り当てました');
    }

    /**
     * シフト一括割当
     */
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'assignments' => 'required|array|min:1',
            'assignments.*.user_id' => 'required|uuid|exists:users,id',
            'assignments.*.shift_template_id' => 'required|uuid|exists:shift_templates,id',
            'assignments.*.date' => 'required|date',
            'assignments.*.note' => 'nullable|string|max:255',
        ]);

        $count = DB::transaction(function () use ($validated) {
            $count = 0;
            foreach ($validated['assignments'] as $assignment) {
                ShiftAssignment::updateOrCreate(
                    ['user_id' => $assignment['user_id'], 'date' => $assignment['date']],
                    [
                        'shift_template_id' => $assignment['shift_template_id'],
                        'note' => $assignment['note'] ?? null,
                    ]
                );
                $count++;
            }
            return $count;
        });

        return redirect()->back()->with('success', "{$count}件のシフトを割り当てました");
    }

    /**
     * シフト割当削除
     */
    public function removeAssignment(ShiftAssignment $assignment)
    {
        $assignment->delete();

        return redirect()->back()->with('success', 'シフト割当を解除しました');
    }
}
