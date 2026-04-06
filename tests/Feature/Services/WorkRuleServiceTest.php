<?php

namespace Tests\Feature\Services;

use App\Enums\WorkRuleScope;
use App\Models\Department;
use App\Models\JobGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkRule;
use App\Services\WorkRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkRuleService $service;
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WorkRuleService();

        $tenant = Tenant::create(['name' => 'Test Company', 'slug' => 'test', 'is_active' => true]);
        $this->tenantId = $tenant->id;
        app()->instance('current_tenant_id', $this->tenantId);
    }

    // ==============================
    // 3階層解決
    // ==============================

    public function test_USERルールがあればそれを返す(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId]);
        $jobGroup = JobGroup::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
            'job_group_id' => $jobGroup->id,
        ]);

        // SYSTEM rule
        WorkRule::factory()->create(['tenant_id' => $this->tenantId, 'scope' => WorkRuleScope::SYSTEM, 'work_start_time' => '09:00']);

        // JOB_GROUP rule
        WorkRule::factory()->forJobGroup($jobGroup)->create(['tenant_id' => $this->tenantId, 'work_start_time' => '08:30']);

        // USER rule
        WorkRule::factory()->forUser($user)->create(['tenant_id' => $this->tenantId, 'work_start_time' => '07:00']);

        $result = $this->service->resolve($user->id);

        $this->assertEquals('USER', $result['source']);
        $this->assertEquals('07:00', $result['work_start_time']);
    }

    public function test_USERルールなしでJOB_GROUPルールがあればそれを返す(): void
    {
        $jobGroup = JobGroup::factory()->create(['tenant_id' => $this->tenantId]);
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId, 'job_group_id' => $jobGroup->id]);
        $user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
            'job_group_id' => $jobGroup->id,
        ]);

        // SYSTEM rule
        WorkRule::factory()->create(['tenant_id' => $this->tenantId, 'scope' => WorkRuleScope::SYSTEM, 'work_start_time' => '09:00']);

        // JOB_GROUP rule
        WorkRule::factory()->forJobGroup($jobGroup)->create(['tenant_id' => $this->tenantId, 'work_start_time' => '08:30']);

        $result = $this->service->resolve($user->id);

        $this->assertEquals('JOB_GROUP', $result['source']);
        $this->assertEquals('08:30', $result['work_start_time']);
    }

    public function test_USER_JOB_GROUPなしでSYSTEMルールを返す(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
        ]);

        WorkRule::factory()->create(['tenant_id' => $this->tenantId, 'scope' => WorkRuleScope::SYSTEM, 'work_start_time' => '09:00']);

        $result = $this->service->resolve($user->id);

        $this->assertEquals('SYSTEM', $result['source']);
        $this->assertEquals('09:00', $result['work_start_time']);
    }

    public function test_何もなければDEFAULTルールを返す(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
        ]);

        $result = $this->service->resolve($user->id);

        $this->assertEquals('DEFAULT', $result['source']);
        $this->assertEquals('09:00', $result['work_start_time']);
        $this->assertEquals('18:00', $result['work_end_time']);
    }

    // ==============================
    // resolvedJobGroup経由の解決
    // ==============================

    public function test_ユーザーに直接job_group_idがある場合そのJobGroupのルール(): void
    {
        $jobGroupDirect = JobGroup::factory()->create(['tenant_id' => $this->tenantId, 'name' => '直接設定']);
        $jobGroupDept = JobGroup::factory()->create(['tenant_id' => $this->tenantId, 'name' => '部署設定']);
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId, 'job_group_id' => $jobGroupDept->id]);
        $user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
            'job_group_id' => $jobGroupDirect->id,
        ]);

        WorkRule::factory()->forJobGroup($jobGroupDirect)->create(['tenant_id' => $this->tenantId, 'work_start_time' => '07:00']);
        WorkRule::factory()->forJobGroup($jobGroupDept)->create(['tenant_id' => $this->tenantId, 'work_start_time' => '08:30']);

        $result = $this->service->resolve($user->id);

        $this->assertEquals('JOB_GROUP', $result['source']);
        $this->assertEquals('07:00', $result['work_start_time']);
    }

    public function test_ユーザーにjob_group_idなしで部署のJobGroupルールを使う(): void
    {
        $jobGroup = JobGroup::factory()->create(['tenant_id' => $this->tenantId]);
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId, 'job_group_id' => $jobGroup->id]);
        $user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
            'job_group_id' => null,
        ]);

        WorkRule::factory()->forJobGroup($jobGroup)->create(['tenant_id' => $this->tenantId, 'work_start_time' => '08:30']);

        $result = $this->service->resolve($user->id);

        $this->assertEquals('JOB_GROUP', $result['source']);
        $this->assertEquals('08:30', $result['work_start_time']);
    }

    // ==============================
    // ルール内容の正確性
    // ==============================

    public function test_早出カットオフが正しく返される(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
        ]);

        WorkRule::factory()->create([
            'tenant_id' => $this->tenantId,
            'scope' => WorkRuleScope::SYSTEM,
            'early_clock_in_cutoff' => '08:00',
            'early_clock_in_cutoff_pm' => '14:00',
        ]);

        $result = $this->service->resolve($user->id);

        $this->assertEquals('08:00', $result['early_clock_in_cutoff']);
        $this->assertEquals('14:00', $result['early_clock_in_cutoff_pm']);
    }

    public function test_allow_multiple_clock_insが正しく返される(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
        ]);

        WorkRule::factory()->withMultipleClockIns()->create([
            'tenant_id' => $this->tenantId,
            'scope' => WorkRuleScope::SYSTEM,
        ]);

        $result = $this->service->resolve($user->id);

        $this->assertTrue($result['allow_multiple_clock_ins']);
    }

    public function test_sourceフィールドが正しい(): void
    {
        $jobGroup = JobGroup::factory()->create(['tenant_id' => $this->tenantId]);
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId, 'job_group_id' => $jobGroup->id]);

        $user1 = User::factory()->create(['tenant_id' => $this->tenantId, 'department_id' => $dept->id, 'job_group_id' => $jobGroup->id]);
        $user2 = User::factory()->create(['tenant_id' => $this->tenantId, 'department_id' => $dept->id, 'job_group_id' => $jobGroup->id]);
        $deptNoJobGroup = Department::factory()->create(['tenant_id' => $this->tenantId, 'job_group_id' => null]);
        $user3 = User::factory()->create(['tenant_id' => $this->tenantId, 'department_id' => $deptNoJobGroup->id]);

        WorkRule::factory()->forUser($user1)->create(['tenant_id' => $this->tenantId]);
        WorkRule::factory()->forJobGroup($jobGroup)->create(['tenant_id' => $this->tenantId]);
        WorkRule::factory()->create(['tenant_id' => $this->tenantId, 'scope' => WorkRuleScope::SYSTEM]);

        // Need fresh service instances due to cache
        $service1 = new WorkRuleService();
        $this->assertEquals('USER', $service1->resolve($user1->id)['source']);

        $service2 = new WorkRuleService();
        $this->assertEquals('JOB_GROUP', $service2->resolve($user2->id)['source']);

        $service3 = new WorkRuleService();
        $this->assertEquals('SYSTEM', $service3->resolve($user3->id)['source']);
    }
}
