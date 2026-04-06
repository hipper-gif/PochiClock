<?php

namespace Tests\Feature\Controllers\Admin;

use App\Enums\Role;
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
    private User $admin;
    private User $employee;

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
        $this->employee = User::factory()->create([
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

    // --- 勤怠一覧 ---

    public function test_管理者が勤怠一覧にアクセスできる(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.attendance.index'));

        $response->assertOk();
    }

    public function test_一般ユーザーが勤怠一覧にアクセスすると403(): void
    {
        $response = $this->actingAs($this->employee)
            ->get(route('admin.attendance.index'));

        $response->assertStatus(403);
    }

    // --- 勤怠修正 ---

    public function test_clock_in_clock_outを変更できる(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 20, 0, 0));

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->employee->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => Carbon::today()->setTime(18, 0),
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.attendance.update', $attendance), [
                'clock_in' => Carbon::today()->setTime(8, 30)->toDateTimeString(),
                'clock_out' => Carbon::today()->setTime(17, 30)->toDateTimeString(),
                'reason' => '打刻修正',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $attendance->refresh();
        $this->assertEquals('08:30', $attendance->clock_in->format('H:i'));
        $this->assertEquals('17:30', $attendance->clock_out->format('H:i'));
    }

    public function test_reasonが監査ログに記録される(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 20, 0, 0));

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->employee->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => Carbon::today()->setTime(18, 0),
        ]);

        // Clear any audit logs from creation
        $attendance->auditLogs()->delete();

        $this->actingAs($this->admin)
            ->put(route('admin.attendance.update', $attendance), [
                'clock_in' => Carbon::today()->setTime(8, 50)->toDateTimeString(),
                'clock_out' => Carbon::today()->setTime(18, 0)->toDateTimeString(),
                'reason' => '打刻ミス修正',
            ]);

        // Audit log should have the reason
        $auditLog = $attendance->auditLogs()->where('action', 'updated')->first();
        $this->assertNotNull($auditLog, 'Updated audit log should exist');
        $this->assertEquals('打刻ミス修正', $auditLog->reason);
    }

    public function test_clock_outがclock_inより前ならバリデーションエラー(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 20, 0, 0));

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->employee->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => Carbon::today()->setTime(18, 0),
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.attendance.update', $attendance), [
                'clock_in' => Carbon::today()->setTime(18, 0)->toDateTimeString(),
                'clock_out' => Carbon::today()->setTime(9, 0)->toDateTimeString(),
            ]);

        $response->assertSessionHasErrors('clock_out');
    }

    // --- 休憩管理 ---

    public function test_休憩追加でBreakRecord作成(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 20, 0, 0));

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->employee->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => Carbon::today()->setTime(18, 0),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.attendance.addBreak', $attendance), [
                'break_start' => Carbon::today()->setTime(12, 0)->toDateTimeString(),
                'break_end' => Carbon::today()->setTime(13, 0)->toDateTimeString(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('break_records', [
            'attendance_id' => $attendance->id,
        ]);
    }

    public function test_休憩更新で値が変わる(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 20, 0, 0));

        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->employee->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => Carbon::today()->setTime(18, 0),
        ]);

        $breakRecord = BreakRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'attendance_id' => $attendance->id,
            'break_start' => Carbon::today()->setTime(12, 0),
            'break_end' => Carbon::today()->setTime(13, 0),
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.attendance.updateBreak', $breakRecord), [
                'break_start' => Carbon::today()->setTime(12, 30)->toDateTimeString(),
                'break_end' => Carbon::today()->setTime(13, 30)->toDateTimeString(),
            ]);

        $response->assertRedirect();

        $breakRecord->refresh();
        $this->assertEquals('12:30', $breakRecord->break_start->format('H:i'));
        $this->assertEquals('13:30', $breakRecord->break_end->format('H:i'));
    }

    public function test_休憩削除で削除される(): void
    {
        $attendance = Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->employee->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => Carbon::today()->setTime(18, 0),
        ]);

        $breakRecord = BreakRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'attendance_id' => $attendance->id,
            'break_start' => Carbon::today()->setTime(12, 0),
            'break_end' => Carbon::today()->setTime(13, 0),
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.attendance.deleteBreak', $breakRecord));

        $response->assertRedirect();
        $this->assertDatabaseMissing('break_records', ['id' => $breakRecord->id]);
    }

    // --- CSV出力 ---

    public function test_標準形式CSVのヘッダーとデータ行(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 20, 0, 0));

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->employee->id,
            'clock_in' => Carbon::create(2026, 4, 1, 9, 0),
            'clock_out' => Carbon::create(2026, 4, 1, 18, 0),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.attendance.export', [
                'year' => 2026,
                'month' => 4,
                'format' => 'standard',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        // Remove BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = array_filter(explode("\n", trim($content)));
        $this->assertGreaterThanOrEqual(2, count($lines));

        // Check header
        $this->assertStringContainsString('社員番号', $lines[0]);
        $this->assertStringContainsString('名前', $lines[0]);
        $this->assertStringContainsString('部署', $lines[0]);
        $this->assertStringContainsString('実働(分)', $lines[0]);
    }

    public function test_TKC形式CSVのヘッダー(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 20, 0, 0));

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->employee->id,
            'clock_in' => Carbon::create(2026, 4, 1, 9, 0),
            'clock_out' => Carbon::create(2026, 4, 1, 18, 0),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.attendance.export', [
                'year' => 2026,
                'month' => 4,
                'format' => 'tkc',
            ]));

        $response->assertOk();

        $content = $response->streamedContent();
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = array_filter(explode("\n", trim($content)));

        $this->assertStringContainsString('社員番号', $lines[0]);
        $this->assertStringContainsString('所定内労働時間', $lines[0]);
        $this->assertStringContainsString('時間外労働', $lines[0]);
    }
}
