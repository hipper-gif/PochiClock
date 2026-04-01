<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BreakController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\QrScannerController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// ルートリダイレクト
Route::get('/', function () {
    if (\App\Models\User::count() === 0) {
        return redirect('/setup');
    }
    return auth()->check() ? redirect('/dashboard') : redirect('/login');
});

// 初期セットアップ（ユーザーが0人のときのみ有効）
Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

// 認証
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// 認証必須
Route::middleware('auth')->group(function () {
    // ダッシュボード
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/history', [DashboardController::class, 'history'])->name('dashboard.history');

    // 打刻
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clockIn');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clockOut');
    Route::post('/attendance/break-start', [BreakController::class, 'start'])->name('attendance.breakStart');
    Route::post('/attendance/break-end', [BreakController::class, 'end'])->name('attendance.breakEnd');

    // QRコード表示（スマホ用）
    Route::get('/qr', [QrController::class, 'show'])->name('qr.show');
    Route::post('/qr/regenerate', [QrController::class, 'regenerate'])->name('qr.regenerate');

    // プロフィール
    Route::get('/dashboard/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/dashboard/profile', [ProfileController::class, 'updateName'])->name('profile.updateName');
    Route::put('/dashboard/profile/password', [ProfileController::class, 'changePassword'])->name('profile.changePassword');

    // 管理者（admin専用）
    Route::middleware('role:ADMIN')->prefix('admin')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.users.index'));

        // ユーザー管理
        Route::get('/users', [Admin\UserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create', [Admin\UserController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [Admin\UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::put('/users/{user}/role', [Admin\UserController::class, 'updateRole'])->name('admin.users.updateRole');
        Route::put('/users/{user}/status', [Admin\UserController::class, 'toggleStatus'])->name('admin.users.toggleStatus');
        Route::put('/users/{user}/department', [Admin\UserController::class, 'assignDepartment'])->name('admin.users.assignDepartment');
        Route::post('/users/{user}/reset-pin', [Admin\UserController::class, 'resetPin'])->name('admin.users.resetPin');
        Route::delete('/users/{user}/pin', [Admin\UserController::class, 'clearPin'])->name('admin.users.clearPin');
        Route::post('/users/bulk-generate-pins', [Admin\UserController::class, 'bulkGeneratePins'])->name('admin.users.bulkGeneratePins');
        Route::post('/users/{user}/reset-qr', [Admin\UserController::class, 'resetQrToken'])->name('admin.users.resetQrToken');
        Route::delete('/users/{user}/qr', [Admin\UserController::class, 'clearQrToken'])->name('admin.users.clearQrToken');

        // 職種グループ管理
        Route::get('/job-groups', [Admin\JobGroupController::class, 'index'])->name('admin.job-groups.index');
        Route::post('/job-groups', [Admin\JobGroupController::class, 'store'])->name('admin.job-groups.store');
        Route::put('/job-groups/{jobGroup}', [Admin\JobGroupController::class, 'update'])->name('admin.job-groups.update');
        Route::delete('/job-groups/{jobGroup}', [Admin\JobGroupController::class, 'destroy'])->name('admin.job-groups.destroy');

        // 部署管理
        Route::get('/departments', [Admin\DepartmentController::class, 'index'])->name('admin.departments.index');
        Route::post('/departments', [Admin\DepartmentController::class, 'store'])->name('admin.departments.store');
        Route::put('/departments/{department}', [Admin\DepartmentController::class, 'update'])->name('admin.departments.update');
        Route::delete('/departments/{department}', [Admin\DepartmentController::class, 'destroy'])->name('admin.departments.destroy');

        // 勤務ルール設定
        Route::get('/settings', [Admin\WorkRuleController::class, 'index'])->name('admin.settings.index');
        Route::post('/settings/system', [Admin\WorkRuleController::class, 'upsertSystem'])->name('admin.settings.upsertSystem');
        Route::post('/settings/job-group', [Admin\WorkRuleController::class, 'upsertJobGroup'])->name('admin.settings.upsertJobGroup');
        Route::post('/settings/user', [Admin\WorkRuleController::class, 'upsertUser'])->name('admin.settings.upsertUser');
        Route::delete('/settings/{rule}', [Admin\WorkRuleController::class, 'destroy'])->name('admin.settings.destroy');

        // 監査ログ
        Route::get('/audit-logs', [Admin\AuditLogController::class, 'index'])->name('admin.audit-logs.index');

        // 有給管理（admin only）
        Route::post('/paid-leaves/grant', [Admin\PaidLeaveController::class, 'grant'])->name('admin.paid-leaves.grant');
        Route::post('/paid-leaves/auto-grant', [Admin\PaidLeaveController::class, 'autoGrant'])->name('admin.paid-leaves.autoGrant');
    });

    // 勤怠管理（admin + manager）
    Route::middleware(['role:ADMIN,MANAGER', 'department.access'])->prefix('admin')->group(function () {
        Route::get('/attendance', [Admin\AttendanceController::class, 'index'])->name('admin.attendance.index');
        Route::put('/attendance/{attendance}', [Admin\AttendanceController::class, 'update'])->name('admin.attendance.update');
        Route::get('/attendance/export', [Admin\AttendanceController::class, 'export'])->name('admin.attendance.export');
        Route::post('/attendance/{attendance}/breaks', [Admin\AttendanceController::class, 'addBreak'])->name('admin.attendance.addBreak');
        Route::put('/breaks/{breakRecord}', [Admin\AttendanceController::class, 'updateBreak'])->name('admin.attendance.updateBreak');
        Route::delete('/breaks/{breakRecord}', [Admin\AttendanceController::class, 'deleteBreak'])->name('admin.attendance.deleteBreak');
    });

    // 管理者・マネージャー共通
    Route::middleware(['role:ADMIN,MANAGER', 'department.access'])->prefix('admin')->group(function () {
        Route::get('/month-summary', [Admin\MonthSummaryController::class, 'index'])->name('admin.month-summary.index');
        Route::get('/alerts', [Admin\AlertController::class, 'index'])->name('admin.alerts.index');
        Route::get('/overtime', [Admin\OvertimeController::class, 'index'])->name('admin.overtime.index');
        Route::get('/comp-leaves', [Admin\CompLeaveController::class, 'index'])->name('admin.comp-leaves.index');
        Route::post('/comp-leaves', [Admin\CompLeaveController::class, 'store'])->name('admin.comp-leaves.store');
        Route::delete('/comp-leaves/{compLeave}', [Admin\CompLeaveController::class, 'destroy'])->name('admin.comp-leaves.destroy');
        Route::get('/paid-leaves', [Admin\PaidLeaveController::class, 'index'])->name('admin.paid-leaves.index');
        Route::post('/paid-leaves/apply', [Admin\PaidLeaveController::class, 'apply'])->name('admin.paid-leaves.apply');
        Route::put('/paid-leaves/{paidLeave}/approve', [Admin\PaidLeaveController::class, 'approve'])->name('admin.paid-leaves.approve');
        Route::put('/paid-leaves/{paidLeave}/reject', [Admin\PaidLeaveController::class, 'reject'])->name('admin.paid-leaves.reject');
    });
});

// キオスク（認証不要）
Route::prefix('kiosk')->group(function () {
    Route::get('/', [KioskController::class, 'index'])->name('kiosk.index');
    Route::get('/{department}/qr', [QrScannerController::class, 'index'])->name('kiosk.qr');
    Route::post('/qr-verify', [QrScannerController::class, 'verify'])->name('kiosk.qrVerify');
    Route::get('/{department}', [KioskController::class, 'department'])->name('kiosk.department');
    Route::post('/{department}/lookup', [KioskController::class, 'lookup'])->name('kiosk.lookup');
    Route::post('/{department}/clock-in', [KioskController::class, 'clockIn'])->name('kiosk.clockIn');
    Route::post('/{department}/clock-out', [KioskController::class, 'clockOut'])->name('kiosk.clockOut');
});
