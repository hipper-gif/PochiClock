<?php

namespace Tests\Feature\Controllers\Admin;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\ShiftAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Department $dept;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'is_active' => true]);
        $tenantId = $this->tenant->id;
        app()->instance('current_tenant_id', $tenantId);
        app()->instance('audit_enabled', true);

        $this->dept = Department::factory()->create(['tenant_id' => $tenantId]);
        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $tenantId,
            'department_id' => $this->dept->id,
        ]);

        WorkRule::factory()->create(['tenant_id' => $tenantId]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_管理者がアラート画面にアクセスできる(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.alerts.index'));

        $response->assertOk();
    }

    public function test_出勤未打刻が表示される(): void
    {
        // A weekday with no attendance records for a user
        $targetDate = Carbon::create(2026, 4, 3); // Friday
        Carbon::setTestNow(Carbon::create(2026, 4, 4, 10, 0, 0));

        $employee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->dept->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.alerts.index', ['date' => $targetDate->toDateString()]));

        $response->assertOk();
        $response->assertViewHas('missingClockIns');

        $missingClockIns = $response->viewData('missingClockIns');
        $missingIds = $missingClockIns->pluck('id')->toArray();
        $this->assertContains($employee->id, $missingIds);
    }

    public function test_退勤未打刻が表示される(): void
    {
        $targetDate = Carbon::create(2026, 4, 3);
        Carbon::setTestNow(Carbon::create(2026, 4, 4, 10, 0, 0));

        $employee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->dept->id,
        ]);

        // Clocked in but no clock out
        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $employee->id,
            'clock_in' => $targetDate->copy()->setTime(9, 0),
            'clock_out' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.alerts.index', ['date' => $targetDate->toDateString()]));

        $response->assertOk();
        $response->assertViewHas('missingClockOuts');

        $missingClockOuts = $response->viewData('missingClockOuts');
        $this->assertTrue($missingClockOuts->contains('user_id', $employee->id));
    }

    public function test_シフト超過が表示される(): void
    {
        $targetDate = Carbon::create(2026, 4, 3);
        Carbon::setTestNow(Carbon::create(2026, 4, 4, 10, 0, 0));

        $employee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->dept->id,
        ]);

        // Clocked out well past 18:00 (work_end_time) -> 20:00 = 120min overtime
        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $employee->id,
            'clock_in' => $targetDate->copy()->setTime(9, 0),
            'clock_out' => $targetDate->copy()->setTime(20, 0),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.alerts.index', ['date' => $targetDate->toDateString()]));

        $response->assertOk();
        $response->assertViewHas('shiftOvertime');

        $shiftOvertime = $response->viewData('shiftOvertime');
        $this->assertGreaterThanOrEqual(1, $shiftOvertime->count());
    }

    public function test_日付パラメータでフィルターできる(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.alerts.index', ['date' => '2026-04-01']));

        $response->assertOk();
        $response->assertViewHas('date', '2026-04-01');
    }
}
