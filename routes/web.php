<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BreakController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ルートリダイレクト
Route::get('/', function () {
    return auth()->check() ? redirect('/dashboard') : redirect('/login');
});

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

    // プロフィール
    Route::get('/dashboard/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/dashboard/profile', [ProfileController::class, 'updateName'])->name('profile.updateName');
    Route::put('/dashboard/profile/password', [ProfileController::class, 'changePassword'])->name('profile.changePassword');

    // 管理者
    Route::middleware('admin')->prefix('admin')->group(function () {
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

        // 部署管理
        Route::get('/departments', [Admin\DepartmentController::class, 'index'])->name('admin.departments.index');
        Route::post('/departments', [Admin\DepartmentController::class, 'store'])->name('admin.departments.store');
        Route::put('/departments/{department}', [Admin\DepartmentController::class, 'update'])->name('admin.departments.update');
        Route::delete('/departments/{department}', [Admin\DepartmentController::class, 'destroy'])->name('admin.departments.destroy');

        // 勤怠管理
        Route::get('/attendance', [Admin\AttendanceController::class, 'index'])->name('admin.attendance.index');
        Route::put('/attendance/{attendance}', [Admin\AttendanceController::class, 'update'])->name('admin.attendance.update');
        Route::get('/attendance/export', [Admin\AttendanceController::class, 'export'])->name('admin.attendance.export');
        Route::post('/attendance/{attendance}/breaks', [Admin\AttendanceController::class, 'addBreak'])->name('admin.attendance.addBreak');
        Route::put('/breaks/{breakRecord}', [Admin\AttendanceController::class, 'updateBreak'])->name('admin.attendance.updateBreak');
        Route::delete('/breaks/{breakRecord}', [Admin\AttendanceController::class, 'deleteBreak'])->name('admin.attendance.deleteBreak');

        // 勤務ルール設定
        Route::get('/settings', [Admin\WorkRuleController::class, 'index'])->name('admin.settings.index');
        Route::post('/settings/system', [Admin\WorkRuleController::class, 'upsertSystem'])->name('admin.settings.upsertSystem');
        Route::post('/settings/department', [Admin\WorkRuleController::class, 'upsertDepartment'])->name('admin.settings.upsertDepartment');
        Route::post('/settings/user', [Admin\WorkRuleController::class, 'upsertUser'])->name('admin.settings.upsertUser');
        Route::delete('/settings/{rule}', [Admin\WorkRuleController::class, 'destroy'])->name('admin.settings.destroy');

        // アラート
        Route::get('/alerts', [Admin\AlertController::class, 'index'])->name('admin.alerts.index');
    });
});

// キオスク（認証不要）
Route::prefix('kiosk')->group(function () {
    Route::get('/', [KioskController::class, 'index'])->name('kiosk.index');
    Route::get('/{department}', [KioskController::class, 'department'])->name('kiosk.department');
    Route::post('/{department}/lookup', [KioskController::class, 'lookup'])->name('kiosk.lookup');
    Route::post('/{department}/clock-in', [KioskController::class, 'clockIn'])->name('kiosk.clockIn');
    Route::post('/{department}/clock-out', [KioskController::class, 'clockOut'])->name('kiosk.clockOut');
});
