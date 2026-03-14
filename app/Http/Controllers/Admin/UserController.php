<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('department')->orderBy('created_at');

        if ($request->filled('dept')) {
            $query->where('department_id', $request->dept);
        }
        if ($request->input('inactive') !== '1') {
            $query->where('is_active', true);
        }

        $users = $query->get();
        $departments = Department::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'departments'));
    }

    public function create()
    {
        $departments = Department::orderBy('name')->get();
        return view('admin.users.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_number' => 'required|string|unique:users,employee_number',
            'name' => 'required|string|min:1|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::enum(Role::class)],
            'department_id' => 'nullable|exists:departments,id',
            'kiosk_code' => 'nullable|digits:4|unique:users,kiosk_code',
        ]);

        User::create($request->only([
            'employee_number', 'name', 'email', 'password',
            'role', 'department_id', 'kiosk_code',
        ]));

        return redirect()->route('admin.users.index')->with('success', 'ユーザーを作成しました');
    }

    public function edit(User $user)
    {
        $departments = Department::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'departments'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|min:1|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($request->only(['name', 'email']));

        return redirect()->route('admin.users.index')->with('success', 'ユーザー情報を更新しました');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', '自分自身は削除できません');
        }

        $user->delete();
        return back()->with('success', 'ユーザーを削除しました');
    }

    public function updateRole(Request $request, User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', '自分自身のロールは変更できません');
        }

        $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $user->update(['role' => $request->role]);

        return back()->with('success', 'ロールを変更しました');
    }

    public function toggleStatus(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', '自分自身のステータスは変更できません');
        }

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', 'ステータスを変更しました');
    }

    public function assignDepartment(Request $request, User $user)
    {
        $request->validate([
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $user->update(['department_id' => $request->department_id ?: null]);

        return back()->with('success', '部署を変更しました');
    }
}
