<?php

namespace Tests\Feature\Scenarios;

use App\Enums\WorkRuleScope;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\JobGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class KioskPwaScenarioTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $tenantId;
    private Department $dept;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Smiley', 'slug' => 'smiley', 'is_active' => true]);
        $this->tenantId = $this->tenant->id;
        app()->instance('current_tenant_id', $this->tenantId);
        app()->instance('audit_enabled', true);

        $jobGroup = JobGroup::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => '訪問介護',
        ]);
        $this->dept = Department::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => '訪問介護',
            'job_group_id' => $jobGroup->id,
        ]);
        $this->user = User::factory()->withPin()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $this->dept->id,
            'job_group_id' => $jobGroup->id,
        ]);

        WorkRule::factory()->create([
            'tenant_id' => $this->tenantId,
            'scope' => WorkRuleScope::JOB_GROUP,
            'job_group_id' => $jobGroup->id,
            'work_start_time' => '08:30',
            'work_end_time' => '17:30',
            'default_break_minutes' => 60,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // --------------------------------------------------
    // PWA Manifest
    // --------------------------------------------------

    public function test_部署別manifestが正しいJSON形式で返る(): void
    {
        $response = $this->get(route('kiosk.manifest', $this->dept));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json');

        $json = $response->json();
        $this->assertStringContainsString('訪問介護', $json['name']);
        $this->assertEquals('訪問介護', $json['short_name']);
        $this->assertEquals('standalone', $json['display']);
        $this->assertEquals('portrait', $json['orientation']);
        $this->assertStringContainsString('/kiosk/', $json['start_url']);
        $this->assertStringContainsString('/kiosk', $json['scope']);
        $this->assertCount(3, $json['icons']);
    }

    public function test_異なる部署で別のmanifestが返る(): void
    {
        $dept2 = Department::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => '配達',
        ]);

        $res1 = $this->get(route('kiosk.manifest', $this->dept));
        $res2 = $this->get(route('kiosk.manifest', $dept2));

        $this->assertEquals('訪問介護', $res1->json('short_name'));
        $this->assertEquals('配達', $res2->json('short_name'));
        $this->assertNotEquals($res1->json('start_url'), $res2->json('start_url'));
    }

    // --------------------------------------------------
    // スマホPWA GPS打刻シナリオ（直行直帰の訪問介護スタッフ）
    // --------------------------------------------------

    public function test_スマホPWAでGPS付き出退勤の一連フロー(): void
    {
        // 訪問先に直行 → スマホPWAで出勤打刻（GPS付き）
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 8, 30, 0));
        RateLimiter::clear('kiosk_pin:127.0.0.1:' . $this->dept->id);

        // Step 1: PIN lookup
        $this->postJson(route('kiosk.lookup', $this->dept), [
            'kiosk_code' => $this->user->kiosk_code,
        ])->assertOk()->assertJson([
            'success' => true,
            'status' => 'not_started',
        ]);

        // Step 2: 出勤（GPS座標付き）
        $this->postJson(route('kiosk.clockIn', $this->dept), [
            'user_id' => $this->user->id,
            'latitude' => 34.7352,
            'longitude' => 135.5847,
        ])->assertOk()->assertJson(['success' => true]);

        $attendance = Attendance::where('user_id', $this->user->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals(34.7352, $attendance->clock_in_lat);
        $this->assertEquals(135.5847, $attendance->clock_in_lng);

        // Step 3: PIN lookup → 出勤中を確認
        $this->postJson(route('kiosk.lookup', $this->dept), [
            'kiosk_code' => $this->user->kiosk_code,
        ])->assertOk()->assertJson([
            'success' => true,
            'status' => 'clocked_in',
        ]);

        // Step 4: 退勤（別の場所からGPS付き）
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 17, 30, 0));

        $this->postJson(route('kiosk.clockOut', $this->dept), [
            'user_id' => $this->user->id,
            'latitude' => 34.7500,
            'longitude' => 135.5900,
        ])->assertOk()->assertJson(['success' => true]);

        $attendance->refresh();
        $this->assertNotNull($attendance->clock_out);
        $this->assertEquals(34.7500, $attendance->clock_out_lat);
        $this->assertEquals(135.5900, $attendance->clock_out_lng);

        // 出勤・退勤で座標が異なる（直行直帰の証跡）
        $this->assertNotEquals($attendance->clock_in_lat, $attendance->clock_out_lat);
    }

    public function test_GPS取得失敗でもnullで打刻できる(): void
    {
        // PCキオスクなどGPS非対応端末のケース
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 0, 0));

        // 出勤（GPS情報なし）
        $this->postJson(route('kiosk.clockIn', $this->dept), [
            'user_id' => $this->user->id,
        ])->assertOk()->assertJson(['success' => true]);

        $attendance = Attendance::where('user_id', $this->user->id)->first();
        $this->assertNull($attendance->clock_in_lat);
        $this->assertNull($attendance->clock_in_lng);

        // 退勤（GPS情報なし）
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 17, 0, 0));

        $this->postJson(route('kiosk.clockOut', $this->dept), [
            'user_id' => $this->user->id,
        ])->assertOk()->assertJson(['success' => true]);

        $attendance->refresh();
        $this->assertNull($attendance->clock_out_lat);
        $this->assertNull($attendance->clock_out_lng);
    }

    // --------------------------------------------------
    // キオスク画面のPWAメタタグ表示
    // --------------------------------------------------

    public function test_キオスク部署画面にmanifestリンクが含まれる(): void
    {
        $response = $this->get(route('kiosk.department', $this->dept));

        $response->assertOk();
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('manifest.json', false);
        $response->assertSee('apple-mobile-web-app-capable', false);
        $response->assertSee('serviceWorker', false);
    }

    public function test_キオスクindex画面はmanifestリンクなし(): void
    {
        $response = $this->get(route('kiosk.index'));

        $response->assertOk();
        // index画面は $department がないので manifest link は出ない
        $response->assertDontSee('rel="manifest"', false);
        // ただしService WorkerとPWAメタタグは含まれる
        $response->assertSee('apple-mobile-web-app-capable', false);
    }

    // --------------------------------------------------
    // 配達部門: 2回出勤 + GPS（複合シナリオ）
    // --------------------------------------------------

    public function test_配達部門の2回出勤にGPS座標が各セッションに記録される(): void
    {
        $jobGroup = JobGroup::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => '配食-配達',
        ]);
        $dept = Department::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => '配達',
            'job_group_id' => $jobGroup->id,
        ]);
        $user = User::factory()->withPin()->create([
            'tenant_id' => $this->tenantId,
            'department_id' => $dept->id,
            'job_group_id' => $jobGroup->id,
        ]);

        WorkRule::factory()->create([
            'tenant_id' => $this->tenantId,
            'scope' => WorkRuleScope::JOB_GROUP,
            'job_group_id' => $jobGroup->id,
            'allow_multiple_clock_ins' => true,
            'work_start_time' => '09:30',
            'work_end_time' => '18:00',
            'default_break_minutes' => 0,
        ]);

        // --- 午前便: 事務所で出勤 ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 9, 15, 0));

        $this->postJson(route('kiosk.clockIn', $dept), [
            'user_id' => $user->id,
            'latitude' => 34.7352,
            'longitude' => 135.5847,
        ])->assertJson(['success' => true]);

        // --- 午前便: 退勤 ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 12, 0, 0));

        $this->postJson(route('kiosk.clockOut', $dept), [
            'user_id' => $user->id,
            'latitude' => 34.7352,
            'longitude' => 135.5847,
        ])->assertJson(['success' => true]);

        // --- 午後便: 出勤 ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 14, 0, 0));

        $this->postJson(route('kiosk.clockIn', $dept), [
            'user_id' => $user->id,
            'latitude' => 34.7400,
            'longitude' => 135.5900,
        ])->assertJson(['success' => true]);

        // --- 午後便: 退勤 ---
        Carbon::setTestNow(Carbon::create(2026, 4, 6, 18, 0, 0));

        $this->postJson(route('kiosk.clockOut', $dept), [
            'user_id' => $user->id,
            'latitude' => 34.7400,
            'longitude' => 135.5900,
        ])->assertJson(['success' => true]);

        // 検証: 2セッションそれぞれにGPS座標が記録されている
        $sessions = Attendance::where('user_id', $user->id)
            ->orderBy('session_number')
            ->get();

        $this->assertCount(2, $sessions);

        // 午前便
        $this->assertEquals(1, $sessions[0]->session_number);
        $this->assertEquals(34.7352, $sessions[0]->clock_in_lat);
        $this->assertNotNull($sessions[0]->clock_out);
        $this->assertNotNull($sessions[0]->clock_out_lat);

        // 午後便
        $this->assertEquals(2, $sessions[1]->session_number);
        $this->assertEquals(34.7400, $sessions[1]->clock_in_lat);
        $this->assertNotNull($sessions[1]->clock_out);
        $this->assertNotNull($sessions[1]->clock_out_lat);
    }
}
