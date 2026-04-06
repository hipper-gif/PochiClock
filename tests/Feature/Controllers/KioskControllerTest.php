<?php

namespace Tests\Feature\Controllers;

use App\Enums\WorkRuleScope;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class KioskControllerTest extends TestCase
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
        $this->user = User::factory()->withPin()->create([
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

    public function test_正しいPINでユーザー情報とステータスを返す(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 0, 0));
        RateLimiter::clear('kiosk_pin:127.0.0.1:' . $this->dept->id);

        $response = $this->postJson(
            route('kiosk.lookup', $this->dept),
            ['kiosk_code' => $this->user->kiosk_code]
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ],
                'status' => 'not_started',
            ]);
    }

    public function test_不正なPINでエラー(): void
    {
        RateLimiter::clear('kiosk_pin:127.0.0.1:' . $this->dept->id);

        $response = $this->postJson(
            route('kiosk.lookup', $this->dept),
            ['kiosk_code' => '0000']
        );

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'message' => '該当するユーザーが見つかりません',
            ]);
    }

    public function test_非アクティブユーザーでエラー(): void
    {
        RateLimiter::clear('kiosk_pin:127.0.0.1:' . $this->dept->id);

        $inactiveUser = User::factory()->withPin()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->dept->id,
        ]);

        $response = $this->postJson(
            route('kiosk.lookup', $this->dept),
            ['kiosk_code' => $inactiveUser->kiosk_code]
        );

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'message' => '該当するユーザーが見つかりません',
            ]);
    }

    public function test_キオスク出勤でAttendanceレコード作成(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 0, 0));

        $response = $this->postJson(
            route('kiosk.clockIn', $this->dept),
            ['user_id' => $this->user->id]
        );

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => '出勤しました']);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'session_number' => 1,
        ]);
    }

    public function test_複数セッション対応で2回目の出勤はsession_number2(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 14, 0, 0));

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

        $response = $this->postJson(
            route('kiosk.clockIn', $this->dept),
            ['user_id' => $this->user->id]
        );

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'session_number' => 2,
        ]);
    }

    public function test_キオスク退勤で成功(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 18, 0, 0));

        Attendance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::today()->setTime(9, 0),
            'clock_out' => null,
        ]);

        $response = $this->postJson(
            route('kiosk.clockOut', $this->dept),
            ['user_id' => $this->user->id]
        );

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => '退勤しました']);

        $attendance = Attendance::where('user_id', $this->user->id)->first();
        $this->assertNotNull($attendance->clock_out);
    }
}
