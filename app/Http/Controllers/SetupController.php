<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\WorkRuleScope;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SetupController extends Controller
{
    public function index()
    {
        // Only accessible when no setup has been done
        if (User::count() > 0) {
            return redirect()->route('login');
        }
        return view('setup.index');
    }

    public function store(Request $request)
    {
        if (User::count() > 0) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'company_name'          => 'required|string|max:255',
            'departments'           => 'required|array|min:1',
            'departments.*.name'    => 'required|string|max:255',
            'work_start_time'       => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'work_end_time'         => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'default_break_minutes' => 'required|integer|min:0|max:480',
            'admin_name'            => 'required|string|max:255',
            'admin_employee_number' => 'required|string|max:255',
            'admin_email'           => 'required|email|max:255',
            'admin_password'        => 'required|string|min:8|confirmed',
        ]);

        DB::transaction(function () use ($validated) {
            // Re-check inside transaction to prevent race condition
            if (DB::table('users')->lockForUpdate()->count() > 0) {
                return;
            }

            // 1. Create departments
            $firstDeptId = null;
            foreach ($validated['departments'] as $deptData) {
                $deptName = trim($deptData['name']);
                if ($deptName === '') {
                    continue;
                }
                $dept = Department::create(['name' => $deptName]);
                if ($firstDeptId === null) {
                    $firstDeptId = $dept->id;
                }
            }

            // 2. Create system work rule
            WorkRule::create([
                'scope'                  => WorkRuleScope::SYSTEM,
                'work_start_time'        => $validated['work_start_time'],
                'work_end_time'          => $validated['work_end_time'],
                'default_break_minutes'  => (int) $validated['default_break_minutes'],
                'rounding_unit'          => 1,
                'clock_in_rounding'      => 'none',
                'clock_out_rounding'     => 'none',
            ]);

            // 3. Create admin user
            $admin = User::create([
                'name'            => $validated['admin_name'],
                'employee_number' => $validated['admin_employee_number'],
                'email'           => $validated['admin_email'],
                'password'        => $validated['admin_password'],
                'role'            => Role::ADMIN,
                'is_active'       => true,
                'department_id'   => $firstDeptId,
            ]);

            Auth::login($admin);
        });

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'PochiClockのセットアップが完了しました！');
    }
}
