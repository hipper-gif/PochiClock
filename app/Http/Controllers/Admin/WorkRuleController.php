<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkRuleScope;
use App\Http\Controllers\Controller;
use App\Models\JobGroup;
use App\Models\User;
use App\Models\WorkRule;
use App\Services\WorkRuleService;
use Illuminate\Http\Request;

class WorkRuleController extends Controller
{
    public function __construct(private WorkRuleService $workRuleService) {}

    public function index()
    {
        $systemRule = $this->workRuleService->getSystemRule();
        $jobGroupRules = $this->workRuleService->getJobGroupRules();
        $userRules = $this->workRuleService->getUserRules();
        $jobGroups = JobGroup::orderBy('name')->get();
        $users = User::orderBy('name')->get();

        // 全ユーザーの適用ルール一覧
        $allUsersRules = User::with(['department', 'jobGroup'])->orderBy('name')->get()->map(function ($user) {
            return [
                'user' => $user,
                'rule' => $this->workRuleService->resolve($user->id),
            ];
        });

        return view('admin.settings.index', compact(
            'systemRule', 'jobGroupRules', 'userRules',
            'jobGroups', 'users', 'allUsersRules'
        ));
    }

    public function upsertSystem(Request $request)
    {
        $data = $this->validateRuleData($request);

        WorkRule::updateOrCreate(
            ['scope' => WorkRuleScope::SYSTEM],
            $data
        );

        return back()->with('success', 'システムルールを保存しました');
    }

    public function upsertJobGroup(Request $request)
    {
        $request->validate(['job_group_id' => 'required|exists:job_groups,id']);
        $data = $this->validateRuleData($request);
        $data['job_group_id'] = $request->job_group_id;

        WorkRule::updateOrCreate(
            ['scope' => WorkRuleScope::JOB_GROUP, 'job_group_id' => $request->job_group_id],
            $data
        );

        return back()->with('success', '職種グループルールを保存しました');
    }

    public function upsertUser(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $data = $this->validateRuleData($request);
        $data['user_id'] = $request->user_id;

        WorkRule::updateOrCreate(
            ['scope' => WorkRuleScope::USER, 'user_id' => $request->user_id],
            $data
        );

        return back()->with('success', '個人ルールを保存しました');
    }

    public function destroy(WorkRule $rule)
    {
        if ($rule->scope === WorkRuleScope::SYSTEM) {
            return back()->with('error', 'システムルールは削除できません');
        }

        $rule->delete();
        return back()->with('success', 'ルールを削除しました');
    }

    private function validateRuleData(Request $request): array
    {
        $validated = $request->validate([
            'work_start_time' => 'required|string|size:5',
            'work_end_time' => 'required|string|size:5',
            'default_break_minutes' => 'required|integer|min:0',
            'rounding_unit' => 'required|in:1,5,10,15,30,60',
            'clock_in_rounding' => 'required|in:none,ceil,floor',
            'clock_out_rounding' => 'required|in:none,ceil,floor',
            'allow_multiple_clock_ins' => 'sometimes|boolean',
            'break_tiers' => 'nullable|json',
            'early_clock_in_cutoff' => 'nullable|string|size:5|regex:/^\d{2}:\d{2}$/',
            'early_clock_in_cutoff_pm' => 'nullable|string|size:5|regex:/^\d{2}:\d{2}$/',
        ]);

        $validated['allow_multiple_clock_ins'] = $request->boolean('allow_multiple_clock_ins');
        $validated['break_tiers'] = $request->break_tiers ? json_decode($request->break_tiers, true) : null;
        $validated['early_clock_in_cutoff'] = $validated['early_clock_in_cutoff'] ?: null;
        $validated['early_clock_in_cutoff_pm'] = $validated['early_clock_in_cutoff_pm'] ?: null;
        $validated['scope'] = $validated['scope'] ?? null;

        return collect($validated)->except(['scope'])->toArray();
    }
}
