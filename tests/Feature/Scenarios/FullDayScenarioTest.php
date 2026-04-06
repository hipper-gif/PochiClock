<?php

namespace Tests\Feature\Scenarios;

use App\Enums\WorkRuleScope;
use App\Models\Attendance;
use App\Models\BreakRecord;
use App\Models\Department;
use App\Models\JobGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class FullDayScenarioTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Smiley', 'slug' => 'smiley', 'is_active' => true]);
        $this->tenantId = $this->tenant->id;
        app()->instance('current_tenant_id', $this->tenantId);
        app()->instance('audit_enabled', true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_配達部門の1日フロー(): void
    {
        // --- Setup ---
        $jobGroup = JobGroup::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => '配食-配達',
        ]);
        $dept = Department::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => '配達',
            'job_group_id' => $jobGroup->id,
        ]);
        $admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
        ]);
        $user = User::factory()->withPin()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
            'job_group_id' => $jobGroup->id,
        ]);

        // Work rule: multiple clock-ins, early cutoff at 9:30 (AM) / 14:00 (PM)
        WorkRule::factory()->create([
            'tenant_id' => $this->tenantId,
            'scope' => WorkRuleScope::JOB_GROUP,
            'job_group_id' => $jobGroup->id,
            'allow_multiple_clock_ins' => true,
            'early_clock_in_cutoff' => '09:30',
            'early_clock_in_cutoff_pm' => '14:00',
            'work_start_time' => '09:30',
            'work_end_time' => '18:00',
            'default_break_minutes' => 0,
        ]);

        // --- Step 2: Kiosk PIN lookup ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 15, 0));
        RateLimiter::clear('kiosk_pin:127.0.0.1:' . $dept->id);

        $lookupResponse = $this->postJson(
            route('kiosk.lookup', $dept),
            ['kiosk_code' => $user->kiosk_code]
        );
        $lookupResponse->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'not_started',
                'session' => ['allow_multiple' => true],
            ]);

        // --- Step 3: AM clock-in at 9:15 (before cutoff 9:30) ---
        $this->postJson(
            route('kiosk.clockIn', $dept),
            ['user_id' => $user->id]
        )->assertJson(['success' => true]);

        $amAttendance = Attendance::where('user_id', $user->id)->first();
        $this->assertEquals(1, $amAttendance->session_number);

        // --- Step 4: AM clock-out at 12:00 ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 12, 0, 0));

        $this->postJson(
            route('kiosk.clockOut', $dept),
            ['user_id' => $user->id]
        )->assertJson(['success' => true]);

        $amAttendance->refresh();
        $this->assertNotNull($amAttendance->clock_out);

        // --- Step 5: PM clock-in at 13:50 (before PM cutoff 14:00) ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 13, 50, 0));

        $this->postJson(
            route('kiosk.clockIn', $dept),
            ['user_id' => $user->id]
        )->assertJson(['success' => true]);

        $pmAttendance = Attendance::where('user_id', $user->id)
            ->where('session_number', 2)
            ->first();
        $this->assertNotNull($pmAttendance);
        $this->assertEquals(2, $pmAttendance->session_number);

        // --- Step 6: PM clock-out at 18:00 ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 18, 0, 0));

        $this->postJson(
            route('kiosk.clockOut', $dept),
            ['user_id' => $user->id]
        )->assertJson(['success' => true]);

        $pmAttendance->refresh();
        $this->assertNotNull($pmAttendance->clock_out);

        // --- Step 7: Dashboard daily total check ---
        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('status', 'clocked_out');
        $response->assertViewHas('todayAttendances');
        $todayAttendances = $response->viewData('todayAttendances');
        $this->assertCount(2, $todayAttendances);

        // --- Step 8: Admin attendance list ---
        $adminResponse = $this->actingAs($admin)
            ->get(route('admin.attendance.index', [
                'year' => 2026,
                'month' => 4,
            ]));
        $adminResponse->assertOk();

        // --- Step 9: CSV export with cutoffs ---
        $csvResponse = $this->actingAs($admin)
            ->get(route('admin.attendance.export', [
                'year' => 2026,
                'month' => 4,
                'format' => 'standard',
            ]));
        $csvResponse->assertOk();

        $content = $csvResponse->streamedContent();
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = array_filter(explode("\n", trim($content)));

        // Header + 2 data rows (AM + PM)
        $this->assertCount(3, $lines);

        // AM session: clock_in was 9:15, cutoff is 9:30 -> effective clock_in should be 09:30
        $amLine = $lines[1];
        $this->assertStringContainsString('09:30', $amLine);

        // PM session: clock_in was 13:50, cutoff_pm is 14:00 -> effective clock_in should be 14:00
        $pmLine = $lines[2];
        $this->assertStringContainsString('14:00', $pmLine);
    }

    public function test_美容部門の1日フロー(): void
    {
        // --- Setup ---
        $jobGroup = JobGroup::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => '美容',
        ]);
        $dept = Department::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Can I Dressy 寝屋川',
            'job_group_id' => $jobGroup->id,
        ]);
        $admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
            'job_group_id' => $jobGroup->id,
        ]);

        // Work rule: 9:00 - 18:00
        WorkRule::factory()->create([
            'tenant_id' => $this->tenantId,
            'scope' => WorkRuleScope::JOB_GROUP,
            'job_group_id' => $jobGroup->id,
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'default_break_minutes' => 60,
        ]);

        // --- Step 1: Clock in ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 0, 0));

        $this->actingAs($user)
            ->post(route('attendance.clockIn'))
            ->assertSessionHas('success', '出勤しました');

        $attendance = Attendance::where('user_id', $user->id)->first();
        $this->assertNotNull($attendance);

        // --- Step 2: Break start ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 12, 0, 0));

        $this->actingAs($user)
            ->post(route('attendance.breakStart'))
            ->assertRedirect();

        $break = BreakRecord::where('attendance_id', $attendance->id)->first();
        $this->assertNotNull($break);
        $this->assertNull($break->break_end);

        // --- Step 3: Break end ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 13, 0, 0));

        $this->actingAs($user)
            ->post(route('attendance.breakEnd'))
            ->assertRedirect();

        $break->refresh();
        $this->assertNotNull($break->break_end);

        // --- Step 4: Clock out at 20:00 (shift overtime) ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 20, 0, 0));

        $this->actingAs($user)
            ->post(route('attendance.clockOut'))
            ->assertSessionHas('success', '退勤しました');

        $attendance->refresh();
        $this->assertEquals('20:00', $attendance->clock_out->format('H:i'));

        // --- Step 5: Alert screen shows shift overtime ---
        $alertResponse = $this->actingAs($admin)
            ->get(route('admin.alerts.index', ['date' => '2026-04-06']));

        $alertResponse->assertOk();
        $alertResponse->assertViewHas('shiftOvertime');

        $shiftOvertime = $alertResponse->viewData('shiftOvertime');
        // 20:00 - 18:00 = 120 min overtime (>= 15 threshold)
        $this->assertGreaterThanOrEqual(1, $shiftOvertime->count());

        $overtimeEntry = $shiftOvertime->first();
        $this->assertEquals($attendance->id, $overtimeEntry['attendance']->id);
        $this->assertGreaterThanOrEqual(120, $overtimeEntry['overtime_minutes']);
    }
}
