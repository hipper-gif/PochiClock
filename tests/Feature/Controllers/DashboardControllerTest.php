<?php

namespace Tests\Feature\Controllers;

use App\Models\Attendance;
use App\Models\BreakRecord;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Department $dept;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Co', 'slug' => 'test-co', 'is_active' => true]);
        $tenantId = $this->tenant->id;
        app()->instance('current_tenant_id', $tenantId);
        app()->instance('audit_enabled', true);

        $this->dept = Department::factory()->create(['tenant_id' => $tenantId]);
        $this->user = User::factory()->create([
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

    public function test_未出勤ならstatus_not_started(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 8, 0, 0));

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('status', 'not_started');
    }

    public function test_出勤中ならstatus_clocked_in(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 10, 0, 0));

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('status', 'clocked_in');
    }

    public function test_休憩中ならstatus_on_break(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 12, 30, 0));

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => null,
        ]);

        BreakRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'attendance_id' => $attendance->id,
            'break_start' => Carbon::today()->setTime(12, 0),
            'break_end' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('status', 'on_break');
    }

    public function test_退勤済みならstatus_clocked_out(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 19, 0, 0));

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => Carbon::today()->setTime(18, 0),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('status', 'clocked_out');
    }

    public function test_履歴表示で月別データが返る(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 19, 0, 0));

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::create(2026, 4, 1, 9, 0),
            'clock_out' => Carbon::create(2026, 4, 1, 18, 0),
        ]);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::create(2026, 4, 2, 9, 0),
            'clock_out' => Carbon::create(2026, 4, 2, 18, 0),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.history', ['year' => 2026, 'month' => 4]));

        $response->assertOk();
        $response->assertViewHas('records');
        $response->assertViewHas('totalWorkDays', 2);
    }

    public function test_複数セッションの日もグループ化される(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 19, 0, 0));

        // Session 1 (AM)
        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'session_number' => 1,
            'clock_in' => Carbon::create(2026, 4, 3, 9, 0),
            'clock_out' => Carbon::create(2026, 4, 3, 12, 0),
        ]);

        // Session 2 (PM)
        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'session_number' => 2,
            'clock_in' => Carbon::create(2026, 4, 3, 14, 0),
            'clock_out' => Carbon::create(2026, 4, 3, 18, 0),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.history', ['year' => 2026, 'month' => 4]));

        $response->assertOk();
        $response->assertViewHas('groupedRecords');

        $grouped = $response->viewData('groupedRecords');
        // Both sessions are on the same date, so grouped under one key
        $this->assertCount(1, $grouped);
        $this->assertCount(2, $grouped->first());
    }
}
