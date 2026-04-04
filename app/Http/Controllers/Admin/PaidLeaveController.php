<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\PaidLeave;
use App\Models\PaidLeaveBalance;
use App\Models\User;
use App\Services\PaidLeaveService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaidLeaveController extends Controller
{
    public function __construct(
        private PaidLeaveService $paidLeaveService,
    ) {}

    /**
     * 有給管理一覧
     */
    public function index(Request $request)
    {
        $departmentId = $request->input('department_id');

        $usersQuery = User::with('department')->active()->orderBy('name');
        if ($departmentId) {
            $usersQuery->where('department_id', $departmentId);
        }
        $users = $usersQuery->get();

        // 各ユーザーの残高情報を集計
        $balanceSummary = [];
        foreach ($users as $user) {
            $balances = PaidLeaveBalance::where('user_id', $user->id)->active()->get();
            $totalGranted = $balances->sum('granted_days');
            $totalUsed = $balances->sum('used_days');
            $balanceSummary[$user->id] = [
                'granted' => (float) $totalGranted,
                'used' => (float) $totalUsed,
                'remaining' => (float) $totalGranted - (float) $totalUsed,
            ];
        }

        // 申請一覧（pending優先、新しい順）
        $applications = PaidLeave::with(['user', 'approver'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'rejected' THEN 3 END")
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $departments = Department::orderBy('name')->get();

        return view('admin.paid-leaves.index', compact(
            'users',
            'balanceSummary',
            'applications',
            'departments',
            'departmentId',
        ));
    }

    /**
     * 有給申請
     */
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'leave_date' => 'required|date',
            'leave_type' => 'required|in:full,half_am,half_pm',
            'reason' => 'nullable|string|max:255',
        ]);

        // 残日数チェック
        $user = User::findOrFail($validated['user_id']);
        $consumeDays = $validated['leave_type'] === 'full' ? 1.0 : 0.5;
        $remaining = $this->paidLeaveService->getRemainingDays($user);

        if ($remaining < $consumeDays) {
            return back()->with('error', '有給残日数が不足しています（残: ' . $remaining . '日）');
        }

        // 同日の申請重複チェック
        $exists = PaidLeave::where('user_id', $validated['user_id'])
            ->where('leave_date', $validated['leave_date'])
            ->where('status', '!=', 'rejected')
            ->exists();

        if ($exists) {
            return back()->with('error', 'この日付には既に有給申請があります');
        }

        PaidLeave::create($validated);

        return back()->with('success', '有給申請を登録しました');
    }

    /**
     * 承認
     */
    public function approve(PaidLeave $paidLeave)
    {
        if ($paidLeave->status !== 'pending') {
            return back()->with('error', 'この申請は既に処理済みです');
        }

        // 残日数チェック
        $user = $paidLeave->user;
        $consumeDays = $paidLeave->consume_days;
        $remaining = $this->paidLeaveService->getRemainingDays($user);

        if ($remaining < $consumeDays) {
            return back()->with('error', '有給残日数が不足しています（残: ' . $remaining . '日）');
        }

        // 残高消費とステータス更新をトランザクションで実行
        DB::transaction(function () use ($user, $consumeDays, $paidLeave) {
            $this->paidLeaveService->useDays($user, $consumeDays);

            $paidLeave->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        return back()->with('success', $user->name . 'さんの有給申請を承認しました');
    }

    /**
     * 却下
     */
    public function reject(PaidLeave $paidLeave)
    {
        if ($paidLeave->status !== 'pending') {
            return back()->with('error', 'この申請は既に処理済みです');
        }

        DB::transaction(function () use ($paidLeave) {
            $paidLeave->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        return back()->with('success', $paidLeave->user->name . 'さんの有給申請を却下しました');
    }

    /**
     * 手動付与（admin only）
     */
    public function grant(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'granted_days' => 'required|numeric|min:0.5|max:40',
            'grant_date' => 'required|date',
        ]);

        $grantDate = Carbon::parse($validated['grant_date']);

        PaidLeaveBalance::create([
            'user_id' => $validated['user_id'],
            'grant_date' => $grantDate,
            'expiry_date' => $grantDate->copy()->addYears(2),
            'granted_days' => $validated['granted_days'],
            'used_days' => 0,
            'grant_reason' => '手動付与',
        ]);

        $user = User::findOrFail($validated['user_id']);

        return back()->with('success', $user->name . 'さんに' . $validated['granted_days'] . '日付与しました');
    }

    /**
     * 一括自動付与（admin only）
     */
    public function autoGrant(Request $request)
    {
        $grantDate = Carbon::today();
        $users = User::active()->whereNotNull('hire_date')->get();
        $granted = 0;

        foreach ($users as $user) {
            $days = $this->paidLeaveService->calculateGrantDays($user, $grantDate);
            if ($days <= 0) {
                continue;
            }

            // 同じ付与日で既に付与済みかチェック
            $alreadyGranted = PaidLeaveBalance::where('user_id', $user->id)
                ->where('grant_date', $grantDate->toDateString())
                ->where('grant_reason', '法定付与')
                ->exists();

            if ($alreadyGranted) {
                continue;
            }

            PaidLeaveBalance::create([
                'user_id' => $user->id,
                'grant_date' => $grantDate,
                'expiry_date' => $grantDate->copy()->addYears(2),
                'granted_days' => $days,
                'used_days' => 0,
                'grant_reason' => '法定付与',
            ]);

            $granted++;
        }

        return back()->with('success', $granted . '名に有給を自動付与しました');
    }
}
