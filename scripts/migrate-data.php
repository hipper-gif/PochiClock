<?php

require __DIR__ . '/../vendor/autoload.php';

$src = new PDO('mysql:host=127.0.0.1;port=3307;dbname=twinklemark_smartclock', 'twinklemark_app', 'twinkle2525');
$dst = new PDO('mysql:host=127.0.0.1;port=3307;dbname=twinklemark_pochiclock', 'twinklemark_app', 'twinkle2525');
$src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// シード済みのデータを削除
$dst->exec('SET FOREIGN_KEY_CHECKS = 0');
$dst->exec('DELETE FROM break_records');
$dst->exec('DELETE FROM attendances');
$dst->exec('DELETE FROM work_rules');
$dst->exec('DELETE FROM users');
$dst->exec('DELETE FROM departments');
$dst->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "=== Migrating Departments ===" . PHP_EOL;
$rows = $src->query('SELECT id, name, createdAt, updatedAt FROM Department')->fetchAll(PDO::FETCH_ASSOC);
$stmt = $dst->prepare('INSERT INTO departments (id, name, created_at, updated_at) VALUES (?, ?, ?, ?)');
foreach ($rows as $r) {
    $stmt->execute([$r['id'], $r['name'], $r['createdAt'], $r['updatedAt']]);
}
echo count($rows) . " departments migrated" . PHP_EOL;

echo "=== Migrating Users ===" . PHP_EOL;
$rows = $src->query('SELECT id, employeeNumber, name, email, password, kioskCode, role, isActive, departmentId, createdAt, updatedAt FROM User')->fetchAll(PDO::FETCH_ASSOC);
$stmt = $dst->prepare('INSERT INTO users (id, employee_number, name, email, password, kiosk_code, role, is_active, department_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($rows as $r) {
    $stmt->execute([
        $r['id'],
        $r['employeeNumber'],
        $r['name'],
        $r['email'],
        $r['password'],
        $r['kioskCode'],
        $r['role'],
        $r['isActive'],
        $r['departmentId'],
        $r['createdAt'],
        $r['updatedAt'],
    ]);
}
echo count($rows) . " users migrated" . PHP_EOL;

echo "=== Migrating Attendances ===" . PHP_EOL;
$rows = $src->query('SELECT id, userId, clockIn, clockOut, note, clockInLat, clockInLng, clockOutLat, clockOutLng, createdAt, updatedAt FROM Attendance')->fetchAll(PDO::FETCH_ASSOC);
$stmt = $dst->prepare('INSERT INTO attendances (id, user_id, clock_in, clock_out, note, clock_in_lat, clock_in_lng, clock_out_lat, clock_out_lng, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$count = 0;
foreach ($rows as $r) {
    $stmt->execute([
        $r['id'],
        $r['userId'],
        $r['clockIn'],
        $r['clockOut'],
        $r['note'],
        $r['clockInLat'],
        $r['clockInLng'],
        $r['clockOutLat'],
        $r['clockOutLng'],
        $r['createdAt'],
        $r['updatedAt'],
    ]);
    $count++;
    if ($count % 1000 === 0) echo "  {$count}..." . PHP_EOL;
}
echo $count . " attendances migrated" . PHP_EOL;

echo "=== Migrating Breaks ===" . PHP_EOL;
$rows = $src->query('SELECT id, attendanceId, breakStart, breakEnd, latitude, longitude, endLatitude, endLongitude, createdAt, updatedAt FROM `Break`')->fetchAll(PDO::FETCH_ASSOC);
$stmt = $dst->prepare('INSERT INTO break_records (id, attendance_id, break_start, break_end, latitude, longitude, end_latitude, end_longitude, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($rows as $r) {
    $stmt->execute([
        $r['id'],
        $r['attendanceId'],
        $r['breakStart'],
        $r['breakEnd'],
        $r['latitude'],
        $r['longitude'],
        $r['endLatitude'],
        $r['endLongitude'],
        $r['createdAt'],
        $r['updatedAt'],
    ]);
}
echo count($rows) . " breaks migrated" . PHP_EOL;

echo "=== Migrating WorkRules ===" . PHP_EOL;
$rows = $src->query('SELECT id, scope, departmentId, userId, workStartTime, workEndTime, defaultBreakMinutes, breakTiers, allowMultipleClockIns, roundingUnit, clockInRounding, clockOutRounding, createdAt, updatedAt FROM WorkRule')->fetchAll(PDO::FETCH_ASSOC);
$stmt = $dst->prepare('INSERT INTO work_rules (id, scope, department_id, user_id, work_start_time, work_end_time, default_break_minutes, break_tiers, allow_multiple_clock_ins, rounding_unit, clock_in_rounding, clock_out_rounding, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($rows as $r) {
    $stmt->execute([
        $r['id'],
        $r['scope'],
        $r['departmentId'],
        $r['userId'],
        $r['workStartTime'],
        $r['workEndTime'],
        $r['defaultBreakMinutes'],
        $r['breakTiers'],
        $r['allowMultipleClockIns'],
        $r['roundingUnit'],
        $r['clockInRounding'],
        $r['clockOutRounding'],
        $r['createdAt'],
        $r['updatedAt'],
    ]);
}
echo count($rows) . " work rules migrated" . PHP_EOL;

echo PHP_EOL . "=== Migration complete ===" . PHP_EOL;
