<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('users')->orderBy('name')->get();
        return view('admin.departments.index', compact('departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:1|max:100|unique:departments,name',
        ]);

        Department::create(['name' => trim($request->name)]);

        return back()->with('success', '部署を作成しました');
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:100', 'unique:departments,name,' . $department->id],
            'job_group_id' => 'nullable|exists:job_groups,id',
        ]);

        $department->update([
            'name' => trim($request->name),
            'job_group_id' => $request->job_group_id ?: null,
        ]);

        return back()->with('success', '部署を更新しました');
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return back()->with('success', '部署を削除しました');
    }
}
