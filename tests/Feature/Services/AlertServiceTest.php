<?php

namespace Tests\Feature\Services;

use App\Enums\WorkRuleScope;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\ShiftAssignment;
use App\Models\ShiftTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkRule;
use App\Services\AlertService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private AlertService $service;
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Test Company', 'slug' => 'test', 'is_active' => true]);
        $this->tenantId = $tenant->id;
        app()->instance('current_tenant_id', $this->tenantId);

        $this->service = app(AlertService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createUserWithDept(array $userOverrides = []): User
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId]);
        return User::factory()->create(array_merge([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
        ], $userOverrides));
    }

    // ==============================
    // 退勤未打刻（getMissingClockOuts）
    // ==============================

    public function test_clock_outがnullのレコードが検出される(): void
    {
        $user = $this->createUserWithDept();
        Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'),
            'clock_out' => null,
        ]);

        $result = $this->service->getMissingClockOuts('2026-04-06');
        $this->assertCount(1, $result);
        $this->assertEquals($user->id, $result->first()->user_id);
    }

    public function test_clock_outがあるレコードは検出されない(): void
    {
        $user = $this->createUserWithDept();
        Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2026, 4, 6, 18, 0, 0, 'Asia/Tokyo'),
        ]);

        $result = $this->service->getMissingClockOuts('2026-04-06');
        $this->assertCount(0, $result);
    }

    public function test_指定日付のみ検出(): void
    {
        $user = $this->createUserWithDept();
        // 4/5のレコード（clock_out null）
        Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'clock_in' => Carbon::create(2026, 4, 5, 9, 0, 0, 'Asia/Tokyo'),
            'clock_out' => null,
        ]);

        // 4/6を検索 → ヒットしない
        $result = $this->service->getMissingClockOuts('2026-04-06');
        $this->assertCount(0, $result);

        // 4/5を検索 → ヒットする
        $result = $this->service->getMissingClockOuts('2026-04-05');
        $this->assertCount(1, $result);
    }

    // ==============================
    // 出勤未打刻（getMissingClockIns）
    // ==============================

    public function test_平日に打刻なしのアクティブユーザーが検出される(): void
    {
        // 2026-04-06 is Monday (weekday)
        Carbon::setTestNow(Carbon::create(2026, 4, 7, 9, 0, 0, 'Asia/Tokyo'));
        $user = $this->createUserWithDept();

        $result = $this->service->getMissingClockIns('2026-04-06');
        $this->assertTrue($result->contains('id', $user->id));
    }

    public function test_平日に打刻ありのユーザーは検出されない(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 7, 9, 0, 0, 'Asia/Tokyo'));
        $user = $this->createUserWithDept();
        Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'),
        ]);

        $result = $this->service->getMissingClockIns('2026-04-06');
        $this->assertFalse($result->contains('id', $user->id));
    }

    public function test_非アクティブユーザーは検出されない(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 7, 9, 0, 0, 'Asia/Tokyo'));
        $user = $this->createUserWithDept(['is_active' => false]);

        $result = $this->service->getMissingClockIns('2026-04-06');
        $this->assertFalse($result->contains('id', $user->id));
    }

    public function test_シフト割当がある日に打刻なしで検出される(): void
    {
        // 2026-04-05 is Sunday
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'));
        $user = $this->createUserWithDept();

        $shiftTemplate = ShiftTemplate::factory()->create(['tenant_id' => $this->tenantId]);
        ShiftAssignment::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'shift_template_id' => $shiftTemplate->id,
            'date' => Carbon::create(2026, 4, 5),
        ]);

        $result = $this->service->getMissingClockIns('2026-04-05');
        $this->assertTrue($result->contains('id', $user->id));
    }

    public function test_週末でシフトなしは検出されない(): void
    {
        // 2026-04-05 is Sunday
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'));
        $user = $this->createUserWithDept();

        $result = $this->service->getMissingClockIns('2026-04-05');
        $this->assertFalse($result->contains('id', $user->id));
    }

    // ==============================
    // シフト超過（getShiftOvertime）
    // ==============================

    public function test_所定終業30分超過で検出される(): void
    {
        $user = $this->createUserWithDept();

        // SYSTEM rule: 9:00-18:00
        WorkRule::factory()->create([
            'tenant_id' => $this->tenantId,
            'scope' => WorkRuleScope::SYSTEM,
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
        ]);

        Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2026, 4, 6, 18, 30, 0, 'Asia/Tokyo'),
        ]);

        $result = $this->service->getShiftOvertime('2026-04-06');
        $this->assertCount(1, $result);
        $this->assertEquals(30, $result->first()['overtime_minutes']);
    }

    public function test_所定時間内退勤は検出されない(): void
    {
        $user = $this->createUserWithDept();

        WorkRule::factory()->create([
            'tenant_id' => $this->tenantId,
            'scope' => WorkRuleScope::SYSTEM,
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
        ]);

        Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2026, 4, 6, 18, 0, 0, 'Asia/Tokyo'),
        ]);

        $result = $this->service->getShiftOvertime('2026-04-06');
        $this->assertCount(0, $result);
    }

    public function test_15分未満の超過は検出されない(): void
    {
        $user = $this->createUserWithDept();

        WorkRule::factory()->create([
            'tenant_id' => $this->tenantId,
            'scope' => WorkRuleScope::SYSTEM,
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
        ]);

        Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2026, 4, 6, 18, 10, 0, 'Asia/Tokyo'),
        ]);

        $result = $this->service->getShiftOvertime('2026-04-06');
        $this->assertCount(0, $result);
    }

    // ==============================
    // アラートカウント（getAlertCounts）
    // ==============================

    public function test_各種アラートの件数が正しい(): void
    {
        // Set "today" to 2026-04-07 (Tuesday) so "yesterday" is 2026-04-06 (Monday)
        Carbon::setTestNow(Carbon::create(2026, 4, 7, 9, 0, 0, 'Asia/Tokyo'));

        WorkRule::factory()->create([
            'tenant_id' => $this->tenantId,
            'scope' => WorkRuleScope::SYSTEM,
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
        ]);

        $user1 = $this->createUserWithDept();
        $user2 = $this->createUserWithDept();
        $user3 = $this->createUserWithDept();

        // user1: missing clock_out (yesterday)
        Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user1->id,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'),
            'clock_out' => null,
        ]);

        // user2: shift overtime (yesterday) - 18:30退勤
        Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user2->id,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2026, 4, 6, 18, 30, 0, 'Asia/Tokyo'),
        ]);

        // user3: no attendance yesterday (missing clock-in on weekday)

        $counts = $this->service->getAlertCounts();

        // missing_clock_ins: user3 has no attendance for 2026-04-06 (Monday)
        // user1 and user2 have attendance, so they are not missing
        $this->assertEquals(1, $counts['missing_clock_ins']);

        // missing_clock_outs: user1 has null clock_out within past 3 days
        $this->assertEquals(1, $counts['missing_clock_outs']);

        // shift_overtime: user2 has 30min overtime
        $this->assertEquals(1, $counts['shift_overtime']);
    }
}
