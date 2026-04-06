<?php

namespace Tests\Feature\Services;

use App\Models\Attendance;
use App\Models\BreakRecord;
use App\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeServiceTest extends TestCase
{
    use RefreshDatabase;

    private TimeService $service;
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TimeService();

        $tenant = Tenant::create(['name' => 'Test Company', 'slug' => 'test', 'is_active' => true]);
        $this->tenantId = $tenant->id;
        app()->instance('current_tenant_id', $this->tenantId);

        Carbon::setTestNow(Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ==============================
    // 丸め計算（roundTime）
    // ==============================

    public function test_floor_15分_9時07分は9時00分になる(): void
    {
        $time = Carbon::create(2026, 4, 6, 9, 7, 0, 'Asia/Tokyo');
        $result = $this->service->roundTime($time, 15, 'floor');
        $this->assertEquals('09:00', $result->format('H:i'));
    }

    public function test_ceil_15分_9時07分は9時15分になる(): void
    {
        $time = Carbon::create(2026, 4, 6, 9, 7, 0, 'Asia/Tokyo');
        $result = $this->service->roundTime($time, 15, 'ceil');
        $this->assertEquals('09:15', $result->format('H:i'));
    }

    public function test_none_時刻変更なし(): void
    {
        $time = Carbon::create(2026, 4, 6, 9, 7, 0, 'Asia/Tokyo');
        $result = $this->service->roundTime($time, 15, 'none');
        $this->assertEquals('09:07', $result->format('H:i'));
    }

    public function test_floor_30分_17時44分は17時30分になる(): void
    {
        $time = Carbon::create(2026, 4, 6, 17, 44, 0, 'Asia/Tokyo');
        $result = $this->service->roundTime($time, 30, 'floor');
        $this->assertEquals('17:30', $result->format('H:i'));
    }

    public function test_ceil_30分_17時44分は18時00分になる(): void
    {
        $time = Carbon::create(2026, 4, 6, 17, 44, 0, 'Asia/Tokyo');
        $result = $this->service->roundTime($time, 30, 'ceil');
        $this->assertEquals('18:00', $result->format('H:i'));
    }

    // ==============================
    // 休憩時間計算（calculateBreakMinutes）
    // ==============================

    public function test_60分の休憩1回は60分(): void
    {
        $breaks = collect([
            (object) [
                'break_start' => Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo'),
                'break_end' => Carbon::create(2026, 4, 6, 13, 0, 0, 'Asia/Tokyo'),
            ],
        ]);
        $this->assertEquals(60, $this->service->calculateBreakMinutes($breaks));
    }

    public function test_30分と15分の2回は45分(): void
    {
        $breaks = collect([
            (object) [
                'break_start' => Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo'),
                'break_end' => Carbon::create(2026, 4, 6, 12, 30, 0, 'Asia/Tokyo'),
            ],
            (object) [
                'break_start' => Carbon::create(2026, 4, 6, 15, 0, 0, 'Asia/Tokyo'),
                'break_end' => Carbon::create(2026, 4, 6, 15, 15, 0, 'Asia/Tokyo'),
            ],
        ]);
        $this->assertEquals(45, $this->service->calculateBreakMinutes($breaks));
    }

    public function test_休憩なしは0分(): void
    {
        $this->assertEquals(0, $this->service->calculateBreakMinutes(collect()));
    }

    public function test_休憩中のbreak_endがnullはカウントしない(): void
    {
        $breaks = collect([
            (object) [
                'break_start' => Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo'),
                'break_end' => null,
            ],
        ]);
        $this->assertEquals(0, $this->service->calculateBreakMinutes($breaks));
    }

    // ==============================
    // 実働時間計算（calculateWorkingMinutes）
    // ==============================

    public function test_9時から18時_休憩60分は480分(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 18, 0, 0, 'Asia/Tokyo');
        $breaks = collect([
            (object) [
                'break_start' => Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo'),
                'break_end' => Carbon::create(2026, 4, 6, 13, 0, 0, 'Asia/Tokyo'),
            ],
        ]);
        $this->assertEquals(480, $this->service->calculateWorkingMinutes($clockIn, $clockOut, $breaks));
    }

    public function test_clock_outがnullならnull(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo');
        $this->assertNull($this->service->calculateWorkingMinutes($clockIn, null, collect()));
    }

    public function test_9時から12時_休憩0分は180分(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo');
        $this->assertEquals(180, $this->service->calculateWorkingMinutes($clockIn, $clockOut, collect()));
    }

    // ==============================
    // 丸め付き実働時間（calculateWorkingMinutesWithRounding）
    // ==============================

    public function test_8時53分から17時07分_ceil_floor_15分は420分(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 8, 53, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 17, 7, 0, 'Asia/Tokyo');
        $breaks = collect([
            (object) [
                'break_start' => Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo'),
                'break_end' => Carbon::create(2026, 4, 6, 13, 0, 0, 'Asia/Tokyo'),
            ],
        ]);
        $rounding = [
            'rounding_unit' => 15,
            'clock_in_rounding' => 'ceil',
            'clock_out_rounding' => 'floor',
        ];
        // ceil 8:53 → 9:00, floor 17:07 → 17:00 = 480 - 60 = 420
        $this->assertEquals(420, $this->service->calculateWorkingMinutesWithRounding($clockIn, $clockOut, $breaks, $rounding));
    }

    // ==============================
    // 早出カットオフ（applyEarlyCutoff）
    // ==============================

    public function test_7時45分打刻_カットオフ8時_8時に補正(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 7, 45, 0, 'Asia/Tokyo');
        $result = $this->service->applyEarlyCutoff($clockIn, '08:00');
        $this->assertEquals('08:00', $result->format('H:i'));
    }

    public function test_8時15分打刻_カットオフ8時_変更なし(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 8, 15, 0, 'Asia/Tokyo');
        $result = $this->service->applyEarlyCutoff($clockIn, '08:00');
        $this->assertEquals('08:15', $result->format('H:i'));
    }

    public function test_セッション2_cutoff_pm_14時_13時45分は14時に補正(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 13, 45, 0, 'Asia/Tokyo');
        $result = $this->service->applyEarlyCutoff($clockIn, '08:00', '14:00', 2);
        $this->assertEquals('14:00', $result->format('H:i'));
    }

    public function test_cutoffがnullなら変更なし(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 7, 45, 0, 'Asia/Tokyo');
        $result = $this->service->applyEarlyCutoff($clockIn, null);
        $this->assertEquals('07:45', $result->format('H:i'));
    }

    // ==============================
    // カットオフ＋丸め統合（getRoundedTimesWithCutoff）
    // ==============================

    public function test_7時45分打刻_カットオフ8時_ceil15分_8時00分でcutoff_applied_true(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 7, 45, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 17, 0, 0, 'Asia/Tokyo');
        $rounding = ['rounding_unit' => 15, 'clock_in_rounding' => 'ceil', 'clock_out_rounding' => 'floor'];
        $rule = ['early_clock_in_cutoff' => '08:00', 'early_clock_in_cutoff_pm' => null];

        $result = $this->service->getRoundedTimesWithCutoff($clockIn, $clockOut, $rounding, $rule);

        // cutoff 8:00 applied, then ceil 15min → 8:00 (already on 15min boundary)
        $this->assertEquals('08:00', $result['rounded_clock_in']->format('H:i'));
        $this->assertTrue($result['cutoff_applied']);
    }

    public function test_9時07分打刻_カットオフ8時_ceil15分_9時15分でcutoff_applied_false(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 7, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 17, 0, 0, 'Asia/Tokyo');
        $rounding = ['rounding_unit' => 15, 'clock_in_rounding' => 'ceil', 'clock_out_rounding' => 'floor'];
        $rule = ['early_clock_in_cutoff' => '08:00', 'early_clock_in_cutoff_pm' => null];

        $result = $this->service->getRoundedTimesWithCutoff($clockIn, $clockOut, $rounding, $rule);

        $this->assertEquals('09:15', $result['rounded_clock_in']->format('H:i'));
        $this->assertFalse($result['cutoff_applied']);
    }

    // ==============================
    // 日合計（calculateDailyWorkingMinutes）
    // ==============================

    public function test_午前午後セッション合計420分(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId, 'department_id' => $dept->id]);

        $am = Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'session_number' => 1,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo'),
        ]);

        $pm = Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'session_number' => 2,
            'clock_in' => Carbon::create(2026, 4, 6, 14, 0, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2026, 4, 6, 18, 0, 0, 'Asia/Tokyo'),
        ]);

        // Load breakRecords relation (empty)
        $attendances = collect([$am->load('breakRecords'), $pm->load('breakRecords')]);
        $rounding = ['rounding_unit' => 1, 'clock_in_rounding' => 'none', 'clock_out_rounding' => 'none'];

        // 180 + 240 = 420
        $this->assertEquals(420, $this->service->calculateDailyWorkingMinutes($attendances, $rounding));
    }

    public function test_セッション1件だけの場合はその分のみ(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId, 'department_id' => $dept->id]);

        $att = Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'session_number' => 1,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2026, 4, 6, 18, 0, 0, 'Asia/Tokyo'),
        ]);

        $attendances = collect([$att->load('breakRecords')]);
        $rounding = ['rounding_unit' => 1, 'clock_in_rounding' => 'none', 'clock_out_rounding' => 'none'];

        $this->assertEquals(540, $this->service->calculateDailyWorkingMinutes($attendances, $rounding));
    }

    // ==============================
    // 残業計算（calculateOvertimeMinutes）
    // ==============================

    public function test_9時から18時_所定8h_残業0(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 18, 0, 0, 'Asia/Tokyo');
        $breaks = collect([
            (object) [
                'break_start' => Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo'),
                'break_end' => Carbon::create(2026, 4, 6, 13, 0, 0, 'Asia/Tokyo'),
            ],
        ]);
        $rounding = ['rounding_unit' => 1, 'clock_in_rounding' => 'none', 'clock_out_rounding' => 'none'];
        $rule = [
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'default_break_minutes' => 60,
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ];

        $this->assertEquals(0, $this->service->calculateOvertimeMinutes($clockIn, $clockOut, $breaks, $rounding, $rule));
    }

    public function test_9時から20時_所定8h_残業120分(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 20, 0, 0, 'Asia/Tokyo');
        $breaks = collect([
            (object) [
                'break_start' => Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo'),
                'break_end' => Carbon::create(2026, 4, 6, 13, 0, 0, 'Asia/Tokyo'),
            ],
        ]);
        $rounding = ['rounding_unit' => 1, 'clock_in_rounding' => 'none', 'clock_out_rounding' => 'none'];
        $rule = [
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'default_break_minutes' => 60,
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ];

        $this->assertEquals(120, $this->service->calculateOvertimeMinutes($clockIn, $clockOut, $breaks, $rounding, $rule));
    }

    public function test_clock_outがnullなら残業0(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo');
        $rounding = ['rounding_unit' => 1, 'clock_in_rounding' => 'none', 'clock_out_rounding' => 'none'];
        $rule = [
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'default_break_minutes' => 60,
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ];

        $this->assertEquals(0, $this->service->calculateOvertimeMinutes($clockIn, null, collect(), $rounding, $rule));
    }

    // ==============================
    // アラート検出（detectAttendanceAlerts）
    // ==============================

    public function test_9時10分打刻_所定9時_遅刻10分(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 10, 0, 'Asia/Tokyo');
        $rule = [
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ];

        $alerts = $this->service->detectAttendanceAlerts($clockIn, null, $rule);
        $late = collect($alerts)->firstWhere('type', 'late');
        $this->assertNotNull($late);
        $this->assertEquals(10, $late['minutes']);
    }

    public function test_17時50分退勤_所定18時_早退10分(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 17, 50, 0, 'Asia/Tokyo');
        $rule = [
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ];

        $alerts = $this->service->detectAttendanceAlerts($clockIn, $clockOut, $rule);
        $earlyLeave = collect($alerts)->firstWhere('type', 'early_leave');
        $this->assertNotNull($earlyLeave);
        $this->assertEquals(10, $earlyLeave['minutes']);
    }

    public function test_18時30分退勤_所定18時_残業30分(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 18, 30, 0, 'Asia/Tokyo');
        $rule = [
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ];

        $alerts = $this->service->detectAttendanceAlerts($clockIn, $clockOut, $rule);
        $overtime = collect($alerts)->firstWhere('type', 'overtime');
        $this->assertNotNull($overtime);
        $this->assertEquals(30, $overtime['minutes']);
    }

    public function test_正常時間はアラートなし(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 9, 0, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 18, 0, 0, 'Asia/Tokyo');
        $rule = [
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ];

        $alerts = $this->service->detectAttendanceAlerts($clockIn, $clockOut, $rule);
        $this->assertEmpty($alerts);
    }

    // ==============================
    // 配食部門 - 調理シナリオ
    // ==============================

    public function test_調理シナリオ_7時45分打刻_カットオフ8時_12時退勤_実働4時間(): void
    {
        $clockIn = Carbon::create(2026, 4, 6, 7, 45, 0, 'Asia/Tokyo');
        $clockOut = Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo');
        $rounding = ['rounding_unit' => 1, 'clock_in_rounding' => 'none', 'clock_out_rounding' => 'none'];
        $rule = [
            'early_clock_in_cutoff' => '08:00',
            'early_clock_in_cutoff_pm' => null,
        ];

        $minutes = $this->service->calculateWorkingMinutesWithCutoff($clockIn, $clockOut, collect(), $rounding, $rule);
        // 8:00 - 12:00 = 240分 = 4h
        $this->assertEquals(240, $minutes);
    }

    // ==============================
    // 配食部門 - 配達シナリオ（2回出勤）
    // ==============================

    public function test_配達シナリオ_2回出勤パターン_日合計6時間30分(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenantId]);
        $user = User::factory()->create(['tenant_id' => $this->tenantId, 'department_id' => $dept->id]);

        $rule = [
            'early_clock_in_cutoff' => '09:30',
            'early_clock_in_cutoff_pm' => '14:00',
        ];
        $rounding = ['rounding_unit' => 1, 'clock_in_rounding' => 'none', 'clock_out_rounding' => 'none'];

        // 午前便: 9:15打刻 → cutoff 9:30 → 12:00退勤 = 150分 = 2.5h
        $am = Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'session_number' => 1,
            'clock_in' => Carbon::create(2026, 4, 6, 9, 15, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2026, 4, 6, 12, 0, 0, 'Asia/Tokyo'),
        ]);

        // 午後便: 13:50打刻 → cutoff 14:00 → 18:00退勤 = 240分 = 4h
        $pm = Attendance::factory()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $user->id,
            'session_number' => 2,
            'clock_in' => Carbon::create(2026, 4, 6, 13, 50, 0, 'Asia/Tokyo'),
            'clock_out' => Carbon::create(2026, 4, 6, 18, 0, 0, 'Asia/Tokyo'),
        ]);

        $attendances = collect([$am->load('breakRecords'), $pm->load('breakRecords')]);

        // 150 + 240 = 390分 = 6.5h
        $total = $this->service->calculateDailyWorkingMinutes($attendances, $rounding, $rule);
        $this->assertEquals(390, $total);
    }
}
