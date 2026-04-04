<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JobGroup;
use Illuminate\Http\Request;

class JobGroupController extends Controller
{
    public function index()
    {
        $jobGroups = JobGroup::withCount(['departments', 'users'])
            ->with('departments')
            ->orderBy('name')
            ->get();

        $departments = Department::orderBy('name')->get();

        return view('admin.job-groups.index', compact('jobGroups', 'departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:1|max:100|unique:job_groups,name',
            'description' => 'nullable|string|max:255',
        ]);

        JobGroup::create([
            'name' => trim($request->name),
            'description' => $request->description ? trim($request->description) : null,
        ]);

        return back()->with('success', '職種グループを作成しました');
    }

    public function update(Request $request, JobGroup $jobGroup)
    {
        $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:100', 'unique:job_groups,name,' . $jobGroup->id],
            'description' => 'nullable|string|max:255',
        ]);

        $jobGroup->update([
            'name' => trim($request->name),
            'description' => $request->description ? trim($request->description) : null,
        ]);

        return back()->with('success', '職種グループを更新しました');
    }

    public function destroy(JobGroup $jobGroup)
    {
        if ($jobGroup->workRule) {
            return back()->with('error', '勤務ルールが設定されている職種グループは削除できません。先にルールを削除してください。');
        }

        if ($jobGroup->users()->exists() || $jobGroup->departments()->exists()) {
            return back()->with('error', '所属ユーザーまたは関連部署がある職種グループは削除できません');
        }

        $jobGroup->delete();
        return back()->with('success', '職種グループを削除しました');
    }
}
