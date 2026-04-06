<?php

namespace Tests\Feature\Controllers;

use App\Enums\WorkRuleScope;
use App\Models\Attendance;
use App\Models\BreakRecord;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
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

        // SYSTEM level work rule
        WorkRule::factory()->create(['tenant_id' => $tenantId]);
    }

    public function test_認証済みユーザーが出勤できる(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 0, 0));

        $response = $this->actingAs($this->user)
            ->post(route('attendance.clockIn'));

        $response->assertRedirect();
        $response->assertSessionHas('success', '出勤しました');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'session_number' => 1,
        ]);

        Carbon::setTestNow();
    }

    public function test_既に出勤中ならエラー(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 0, 0));

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::now(),
            'clock_out' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('attendance.clockIn'));

        $response->assertRedirect();
        $response->assertSessionHas('error', '既に出勤中です');

        Carbon::setTestNow();
    }

    public function test_複数打刻不許可で2回目はエラー(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 0, 0));

        // Default rule: allow_multiple_clock_ins = false
        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::now()->subHours(2),
            'clock_out' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('attendance.clockIn'));

        $response->assertRedirect();
        $response->assertSessionHas('error', '本日は既に打刻済みです');

        Carbon::setTestNow();
    }

    public function test_複数打刻許可でセッション番号が自動インクリメント(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 14, 0, 0));

        // Override to allow multiple clock-ins
        WorkRule::where('scope', WorkRuleScope::SYSTEM)->update([
            'allow_multiple_clock_ins' => true,
        ]);

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => Carbon::today()->setTime(12, 0),
            'session_number' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('attendance.clockIn'));

        $response->assertRedirect();
        $response->assertSessionHas('success', '出勤しました');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'session_number' => 2,
        ]);

        Carbon::setTestNow();
    }

    public function test_GPS座標が記録される(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 0, 0));

        $response = $this->actingAs($this->user)
            ->post(route('attendance.clockIn'), [
                'latitude' => 34.75,
                'longitude' => 135.60,
            ]);

        $response->assertRedirect();

        $attendance = Attendance::where('user_id', $this->user->id)->first();
        $this->assertEquals(34.75, $attendance->clock_in_lat);
        $this->assertEquals(135.60, $attendance->clock_in_lng);

        Carbon::setTestNow();
    }

    public function test_出勤中ユーザーが退勤できる(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 18, 0, 0));

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('attendance.clockOut'));

        $response->assertRedirect();
        $response->assertSessionHas('success', '退勤しました');

        $attendance->refresh();
        $this->assertNotNull($attendance->clock_out);

        Carbon::setTestNow();
    }

    public function test_出勤記録なしで退勤するとエラー(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 18, 0, 0));

        $response = $this->actingAs($this->user)
            ->post(route('attendance.clockOut'));

        $response->assertRedirect();
        $response->assertSessionHas('error', '出勤記録がありません');

        Carbon::setTestNow();
    }

    public function test_休憩中に退勤すると休憩も自動終了(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 18, 0, 0));

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => null,
        ]);

        $breakRecord = BreakRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'attendance_id' => $attendance->id,
            'break_start' => Carbon::today()->setTime(12, 0),
            'break_end' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('attendance.clockOut'));

        $response->assertRedirect();
        $response->assertSessionHas('success', '退勤しました');

        $breakRecord->refresh();
        $this->assertNotNull($breakRecord->break_end);

        $attendance->refresh();
        $this->assertNotNull($attendance->clock_out);

        Carbon::setTestNow();
    }
}
