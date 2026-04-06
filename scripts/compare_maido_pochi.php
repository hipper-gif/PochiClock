<?php
/**
 * まいどシステム vs PochiClock 勤怠データ突き合わせスクリプト
 *
 * Usage: php scripts/compare_maido_pochi.php <year> <month>
 * Example: php scripts/compare_maido_pochi.php 2026 3
 *
 * 前提: SSHトンネル (ssh -L 3307:127.0.0.1:3306 xserver-smartclock) が接続済み
 */

// ── 引数チェック ──────────────────────────────
if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/compare_maido_pochi.php <year> <month>\n");
    fwrite(STDERR, "Example: php scripts/compare_maido_pochi.php 2026 3\n");
    exit(1);
}

$year  = (int)$argv[1];
$month = (int)$argv[2];
if ($year < 2020 || $year > 2099 || $month < 1 || $month > 12) {
    fwrite(STDERR, "Error: 年月が不正です (year=$year, month=$month)\n");
    exit(1);
}

$monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);
$periodStart = "{$year}-{$monthPadded}-01";
$periodEnd   = date('Y-m-t', strtotime($periodStart)); // 月末日

echo "=== まいど vs PochiClock 突き合わせ ===\n";
echo "対象: {$year}年{$month}月 ({$periodStart} 〜 {$periodEnd})\n\n";

// ── .env 読み込み ──────────────────────────────
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    fwrite(STDERR, "Error: .env ファイルが見つかりません: {$envPath}\n");
    exit(1);
}

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (strpos($line, '=') === false) continue;
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value);
}

$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '3307';
$dbName = $env['DB_DATABASE'] ?? 'twinklemark_pochiclock';
$dbUser = $env['DB_USERNAME'] ?? '';
$dbPass = $env['DB_PASSWORD'] ?? '';

// ── まいどCSV読み込み ──────────────────────────
$csvPath = __DIR__ . "/../storage/maido_{$year}_{$monthPadded}.csv";
if (!file_exists($csvPath)) {
    fwrite(STDERR, "Error: まいどCSVが見つかりません: {$csvPath}\n");
    fwrite(STDERR, "期待するファイル名: maido_{$year}_{$monthPadded}.csv\n");
    exit(1);
}

echo "まいどCSV: {$csvPath}\n";

$maidoRecords = [];
$handle = fopen($csvPath, 'r');
$header = fgetcsv($handle); // skip header

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 11) continue;

    $rec = [
        'maido_id'   => $row[0],
        'name'       => $row[1],
        'date'       => $row[2],
        'clock_in'   => $row[4],
        'clock_out'  => $row[5],
        'subtotal_h' => (float)$row[6],
        'total_h'    => (float)$row[7],
        'break_h'    => (float)$row[8],
        'sched_break_h' => (float)$row[9],
        'working_h'  => (float)$row[10],
    ];
    $maidoRecords[] = $rec;
}
fclose($handle);

echo "まいどレコード数: " . count($maidoRecords) . "\n";

// ── DB接続 ─────────────────────────────────────
$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    echo "DB接続OK ({$dbHost}:{$dbPort}/{$dbName})\n";
} catch (PDOException $e) {
    fwrite(STDERR, "\nError: DB接続に失敗しました。\n");
    fwrite(STDERR, "DSN: {$dsn}\n");
    fwrite(STDERR, "SSHトンネルは起動していますか？ (ssh -L 3307:127.0.0.1:3306 xserver-smartclock)\n");
    fwrite(STDERR, "PDO Error: " . $e->getMessage() . "\n");
    exit(1);
}

// ── PochiClockデータ取得 ─────────────────────────
// attendances + break_records + users
$sql = "
    SELECT
        a.id AS attendance_id,
        a.user_id,
        a.session_number,
        a.clock_in,
        a.clock_out,
        u.name AS user_name,
        u.employee_number
    FROM attendances a
    JOIN users u ON u.id = a.user_id
    WHERE a.clock_in BETWEEN :start AND :end
    ORDER BY u.name, a.clock_in
";

// DB stores UTC; convert JST range to UTC
$utcStart = (new DateTime($periodStart . ' 00:00:00', new DateTimeZone('Asia/Tokyo')))->setTimezone(new DateTimeZone('UTC'));
$utcEnd   = (new DateTime($periodEnd . ' 23:59:59', new DateTimeZone('Asia/Tokyo')))->setTimezone(new DateTimeZone('UTC'));

$stmt = $pdo->prepare($sql);
$stmt->execute(['start' => $utcStart->format('Y-m-d H:i:s'), 'end' => $utcEnd->format('Y-m-d H:i:s')]);
$pochiRows = $stmt->fetchAll();

echo "PochiClockレコード数: " . count($pochiRows) . "\n";

// 休憩データを一括取得
$breakSql = "
    SELECT
        br.attendance_id,
        br.break_start,
        br.break_end
    FROM break_records br
    JOIN attendances a ON a.id = br.attendance_id
    WHERE a.clock_in BETWEEN :start AND :end
    ORDER BY br.break_start
";
$bstmt = $pdo->prepare($breakSql);
$bstmt->execute(['start' => $utcStart->format('Y-m-d H:i:s'), 'end' => $utcEnd->format('Y-m-d H:i:s')]);
$breakRows = $bstmt->fetchAll();

// attendance_id => [break_records]
$breaksByAttendance = [];
foreach ($breakRows as $br) {
    $breaksByAttendance[$br['attendance_id']][] = $br;
}

// ── WorkRule取得（ユーザー別ルール解決）──
// 優先順: USER > JOB_GROUP (user.job_group_id or dept.job_group_id) > SYSTEM > DEFAULT
$workRulesRaw = $pdo->query("SELECT * FROM work_rules ORDER BY scope")->fetchAll();
$userRows = $pdo->query("SELECT u.id, u.name, u.job_group_id, u.department_id, d.job_group_id as dept_job_group_id
    FROM users u LEFT JOIN departments d ON d.id = u.department_id")->fetchAll();

$systemRule = null;
$jobGroupRules = [];
$userRules = [];
foreach ($workRulesRaw as $wr) {
    if ($wr['scope'] === 'SYSTEM') $systemRule = $wr;
    elseif ($wr['scope'] === 'JOB_GROUP' && $wr['job_group_id']) $jobGroupRules[$wr['job_group_id']] = $wr;
    elseif ($wr['scope'] === 'USER' && $wr['user_id']) $userRules[$wr['user_id']] = $wr;
}

$defaultRule = [
    'default_break_minutes' => 60,
    'break_tiers' => null,
    'early_clock_in_cutoff' => null,
    'early_clock_in_cutoff_pm' => null,
];

function resolveWorkRule(string $userId, array $userRows, array $userRules, array $jobGroupRules, ?array $systemRule, array $defaultRule): array
{
    if (isset($userRules[$userId])) return $userRules[$userId];

    $user = null;
    foreach ($userRows as $u) {
        if ($u['id'] === $userId) { $user = $u; break; }
    }
    if ($user) {
        $jgId = $user['job_group_id'] ?: $user['dept_job_group_id'];
        if ($jgId && isset($jobGroupRules[$jgId])) return $jobGroupRules[$jgId];
    }

    return $systemRule ?? $defaultRule;
}

/**
 * Effective break: mimic TimeService::calculateEffectiveBreakMinutes()
 */
function calculateEffectiveBreak(float $actualBreakMin, int $grossWorkingMin, array $rule): int
{
    if ($actualBreakMin > 0) return (int) $actualBreakMin;

    $tiers = $rule['break_tiers'] ?? null;
    if (is_string($tiers)) $tiers = json_decode($tiers, true);

    if (!empty($tiers) && $grossWorkingMin > 0) {
        usort($tiers, fn($a, $b) => ($a['thresholdHours'] ?? 0) <=> ($b['thresholdHours'] ?? 0));
        $breakMin = 0;
        $grossH = $grossWorkingMin / 60;
        foreach ($tiers as $t) {
            if ($grossH >= ($t['thresholdHours'] ?? 0)) {
                $breakMin = (int) ($t['breakMinutes'] ?? 0);
            }
        }
        return $breakMin;
    }

    if ($grossWorkingMin > 0) {
        return (int) ($rule['default_break_minutes'] ?? 0);
    }

    return 0;
}

echo "WorkRule: SYSTEM + " . count($jobGroupRules) . " JOB_GROUP + " . count($userRules) . " USER ルール\n";

// ── PochiClockデータを name+date でインデックス ──
// 名前の正規化関数: 全角/半角スペースを全て除去
function normalizeName(string $name): string
{
    $name = mb_convert_kana($name, 's'); // 全角スペース→半角
    return preg_replace('/\s+/', '', $name);
}

$pochiByNameDate = [];
$utcTz = new DateTimeZone('UTC');
$jstTz = new DateTimeZone('Asia/Tokyo');

foreach ($pochiRows as $row) {
    $normName = normalizeName($row['user_name']);

    // DB is stored in UTC, convert to JST for comparison with まいど
    $clockIn  = new DateTime($row['clock_in'], $utcTz);
    $clockIn->setTimezone($jstTz);
    $clockOut = $row['clock_out'] ? new DateTime($row['clock_out'], $utcTz) : null;
    if ($clockOut) $clockOut->setTimezone($jstTz);

    $date = $clockIn->format('Y-m-d'); // JST date
    $key = $normName . '|' . $date;

    // 休憩時間を計算（BreakRecord実績）
    $actualBreakMinutes = 0;
    if (isset($breaksByAttendance[$row['attendance_id']])) {
        foreach ($breaksByAttendance[$row['attendance_id']] as $br) {
            if ($br['break_start'] && $br['break_end']) {
                $bs = new DateTime($br['break_start'], $utcTz);
                $be = new DateTime($br['break_end'], $utcTz);
                $actualBreakMinutes += ($be->getTimestamp() - $bs->getTimestamp()) / 60;
            }
        }
    }

    // WorkRuleベースの実効休憩（BreakRecordなし→ルールから自動計算）
    $rule = resolveWorkRule($row['user_id'], $userRows, $userRules, $jobGroupRules, $systemRule, $defaultRule);
    $grossMinutes = $clockOut ? ($clockOut->getTimestamp() - $clockIn->getTimestamp()) / 60 : 0;
    $breakMinutes = calculateEffectiveBreak($actualBreakMinutes, (int)$grossMinutes, $rule);

    // 実働（分）= 出勤〜退勤 - 実効休憩
    $workingMinutes = null;
    if ($clockOut) {
        $workingMinutes = max(0, $grossMinutes - $breakMinutes);
    }

    $entry = [
        'user_name'       => $row['user_name'],
        'date'            => $date,
        'clock_in'        => $clockIn->format('H:i'),
        'clock_out'       => $clockOut ? $clockOut->format('H:i') : null,
        'break_minutes'   => $breakMinutes,
        'working_minutes' => $workingMinutes,
        'session_number'  => $row['session_number'] ?? 1,
    ];

    // 同一日に複数セッションがある場合（配達の2回出勤等）は配列に追加
    if (!isset($pochiByNameDate[$key])) {
        $pochiByNameDate[$key] = [];
    }
    $pochiByNameDate[$key][] = $entry;
}

// ── まいどデータもname+dateでインデックス ────────
$maidoByNameDate = [];
foreach ($maidoRecords as $rec) {
    $normName = normalizeName($rec['name']);
    $key = $normName . '|' . $rec['date'];
    if (!isset($maidoByNameDate[$key])) {
        $maidoByNameDate[$key] = [];
    }
    $maidoByNameDate[$key][] = $rec;
}

// ── 名前マッチング確認 ─────────────────────────
$maidoNames = [];
foreach ($maidoRecords as $rec) {
    $maidoNames[normalizeName($rec['name'])] = $rec['name'];
}
$pochiNames = [];
foreach ($pochiRows as $row) {
    $pochiNames[normalizeName($row['user_name'])] = $row['user_name'];
}

$unmatchedMaido = array_diff_key($maidoNames, $pochiNames);
$unmatchedPochi = array_diff_key($pochiNames, $maidoNames);

if ($unmatchedMaido || $unmatchedPochi) {
    echo "\n[名前マッチング警告]\n";
    if ($unmatchedMaido) {
        echo "  まいどにいるがPochiにいない:\n";
        foreach ($unmatchedMaido as $norm => $orig) {
            echo "    - {$orig} (正規化: {$norm})\n";
        }
    }
    if ($unmatchedPochi) {
        echo "  Pochiにいるがまいどにいない:\n";
        foreach ($unmatchedPochi as $norm => $orig) {
            echo "    - {$orig} (正規化: {$norm})\n";
        }
    }
}

// ── 突き合わせ ─────────────────────────────────
$results    = [];
$matchCount = 0;
$mismatchCount = 0;
$missingInPochi = 0; // まいどにあるがPochiにない
$extraInPochi   = 0; // Pochiにあるがまいどにない

// まいど側を基準にループ
$processedPochiKeys = [];

foreach ($maidoByNameDate as $key => $maidoEntries) {
    $processedPochiKeys[$key] = true;

    if (!isset($pochiByNameDate[$key])) {
        // Pochiに存在しない
        foreach ($maidoEntries as $m) {
            $missingInPochi++;
            $results[] = [
                'name'            => $m['name'],
                'date'            => $m['date'],
                'status'          => 'MISSING_IN_POCHI',
                'maido_in'        => $m['clock_in'],
                'maido_out'       => $m['clock_out'],
                'maido_working_h' => $m['working_h'],
                'pochi_in'        => '',
                'pochi_out'       => '',
                'pochi_working_h' => '',
                'diff_in'         => '',
                'diff_out'        => '',
                'diff_working'    => '',
            ];
        }
        continue;
    }

    $pochiEntries = $pochiByNameDate[$key];

    // セッション数が同じ場合は1対1で比較、異なる場合はまいどの合計とPochiの合計を比較
    // まいどは通常1日1レコード（合計値）、Pochiは複数セッションの場合あり

    foreach ($maidoEntries as $mi => $m) {
        // まいどの1レコードに対応するPochiデータを探す
        // 配達のように1日2回出勤の場合、まいどは合算している可能性がある
        // → まいどのレコードが1つでPochiが複数の場合は合算比較

        if (count($maidoEntries) === 1 && count($pochiEntries) > 1) {
            // まいど1レコード vs Pochi複数セッション → 合算して比較
            $pochiFirstIn  = null;
            $pochiLastOut  = null;
            $pochiTotalWorkMin = 0;
            $pochiTotalBreakMin = 0;

            foreach ($pochiEntries as $p) {
                if ($pochiFirstIn === null || $p['clock_in'] < $pochiFirstIn) {
                    $pochiFirstIn = $p['clock_in'];
                }
                if ($p['clock_out'] !== null && ($pochiLastOut === null || $p['clock_out'] > $pochiLastOut)) {
                    $pochiLastOut = $p['clock_out'];
                }
                if ($p['working_minutes'] !== null) {
                    $pochiTotalWorkMin += $p['working_minutes'];
                }
                $pochiTotalBreakMin += $p['break_minutes'];
            }

            $maidoWorkMin  = $m['working_h'] * 60;
            $pochiWorkHour = round($pochiTotalWorkMin / 60, 2);
            $diffWork      = round($m['working_h'] - $pochiWorkHour, 2);

            $diffIn  = compareTimes($m['clock_in'], $pochiFirstIn);
            $diffOut = compareTimes($m['clock_out'], $pochiLastOut);

            $isMismatch = (abs($diffWork) > 0.01) || ($diffIn !== '0') || ($diffOut !== '0');

            if ($isMismatch) {
                $mismatchCount++;
                $status = 'MISMATCH';
            } else {
                $matchCount++;
                $status = 'OK';
            }

            $results[] = [
                'name'            => $m['name'],
                'date'            => $m['date'],
                'status'          => $status,
                'maido_in'        => $m['clock_in'],
                'maido_out'       => $m['clock_out'],
                'maido_working_h' => $m['working_h'],
                'pochi_in'        => $pochiFirstIn ?? '',
                'pochi_out'       => $pochiLastOut ?? '',
                'pochi_working_h' => $pochiWorkHour,
                'diff_in'         => $diffIn,
                'diff_out'        => $diffOut,
                'diff_working'    => $diffWork,
                'note'            => "Pochi {$pochiEntries[0]['session_number']}セッション合算",
            ];
        } else {
            // 1対1で比較
            $p = $pochiEntries[$mi] ?? null;
            if ($p === null) {
                $missingInPochi++;
                $results[] = [
                    'name'            => $m['name'],
                    'date'            => $m['date'],
                    'status'          => 'MISSING_IN_POCHI',
                    'maido_in'        => $m['clock_in'],
                    'maido_out'       => $m['clock_out'],
                    'maido_working_h' => $m['working_h'],
                    'pochi_in'        => '',
                    'pochi_out'       => '',
                    'pochi_working_h' => '',
                    'diff_in'         => '',
                    'diff_out'        => '',
                    'diff_working'    => '',
                ];
                continue;
            }

            $maidoWorkMin  = $m['working_h'] * 60;
            $pochiWorkHour = $p['working_minutes'] !== null ? round($p['working_minutes'] / 60, 2) : null;
            $diffWork      = $pochiWorkHour !== null ? round($m['working_h'] - $pochiWorkHour, 2) : null;

            $diffIn  = compareTimes($m['clock_in'], $p['clock_in']);
            $diffOut = compareTimes($m['clock_out'], $p['clock_out']);

            $isMismatch = ($diffWork !== null && abs($diffWork) > 0.01)
                       || ($diffIn !== '0')
                       || ($diffOut !== '0');

            if ($isMismatch) {
                $mismatchCount++;
                $status = 'MISMATCH';
            } else {
                $matchCount++;
                $status = 'OK';
            }

            $results[] = [
                'name'            => $m['name'],
                'date'            => $m['date'],
                'status'          => $status,
                'maido_in'        => $m['clock_in'],
                'maido_out'       => $m['clock_out'],
                'maido_working_h' => $m['working_h'],
                'pochi_in'        => $p['clock_in'] ?? '',
                'pochi_out'       => $p['clock_out'] ?? '',
                'pochi_working_h' => $pochiWorkHour ?? '',
                'diff_in'         => $diffIn,
                'diff_out'        => $diffOut,
                'diff_working'    => $diffWork ?? '',
            ];
        }
    }
}

// Pochiにあるがまいどにないもの
foreach ($pochiByNameDate as $key => $pochiEntries) {
    if (isset($processedPochiKeys[$key])) continue;

    foreach ($pochiEntries as $p) {
        $extraInPochi++;
        $results[] = [
            'name'            => $p['user_name'],
            'date'            => $p['date'],
            'status'          => 'EXTRA_IN_POCHI',
            'maido_in'        => '',
            'maido_out'       => '',
            'maido_working_h' => '',
            'pochi_in'        => $p['clock_in'],
            'pochi_out'       => $p['clock_out'] ?? '',
            'pochi_working_h' => $p['working_minutes'] !== null ? round($p['working_minutes'] / 60, 2) : '',
            'diff_in'         => '',
            'diff_out'        => '',
            'diff_working'    => '',
        ];
    }
}

// ── コンソール出力 ──────────────────────────────
echo "\n=== 突き合わせ結果 ===\n";
echo "対象: {$year}年{$month}月\n";
echo "まいどレコード数: " . count($maidoRecords) . "\n";
echo "PochiClockレコード数: " . count($pochiRows) . "\n";
echo "マッチ済み（一致）: {$matchCount}\n";
echo "不一致: {$mismatchCount}\n";
echo "欠落（まいどにあるがPochiにない）: {$missingInPochi}\n";
echo "余剰（Pochiにあるがまいどにない）: {$extraInPochi}\n";

// 不一致一覧
$mismatches = array_filter($results, fn($r) => $r['status'] === 'MISMATCH');
if ($mismatches) {
    echo "\n[不一致一覧]\n";
    foreach ($mismatches as $r) {
        $line = sprintf(
            "%s | %s | 出勤: まいど %s vs Pochi %s",
            $r['name'], $r['date'], $r['maido_in'], $r['pochi_in']
        );
        if ($r['diff_in'] !== '0') {
            $line .= " (差:{$r['diff_in']}分)";
        }
        $line .= sprintf(
            " | 退勤: まいど %s vs Pochi %s",
            $r['maido_out'], $r['pochi_out']
        );
        if ($r['diff_out'] !== '0') {
            $line .= " (差:{$r['diff_out']}分)";
        }
        $line .= sprintf(
            " | 実働: まいど %sh vs Pochi %sh (差: %sh)",
            $r['maido_working_h'], $r['pochi_working_h'], $r['diff_working']
        );
        $line .= " <- MISMATCH";
        if (!empty($r['note'])) {
            $line .= " [{$r['note']}]";
        }
        echo $line . "\n";
    }
}

// 欠落一覧
$missing = array_filter($results, fn($r) => $r['status'] === 'MISSING_IN_POCHI');
if ($missing) {
    echo "\n[欠落一覧（まいどにあるがPochiにない）]\n";
    foreach ($missing as $r) {
        echo sprintf(
            "%s | %s | まいど: %s〜%s (%sh)\n",
            $r['name'], $r['date'], $r['maido_in'], $r['maido_out'], $r['maido_working_h']
        );
    }
}

// 余剰一覧
$extra = array_filter($results, fn($r) => $r['status'] === 'EXTRA_IN_POCHI');
if ($extra) {
    echo "\n[余剰一覧（Pochiにあるがまいどにない）]\n";
    foreach ($extra as $r) {
        echo sprintf(
            "%s | %s | Pochi: %s〜%s (%sh)\n",
            $r['name'], $r['date'], $r['pochi_in'], $r['pochi_out'], $r['pochi_working_h']
        );
    }
}

// ── CSV出力 ─────────────────────────────────────
$outputCsvPath = __DIR__ . "/../storage/compare_{$year}_{$monthPadded}.csv";
$fp = fopen($outputCsvPath, 'w');

// BOM for Excel
fwrite($fp, "\xEF\xBB\xBF");

fputcsv($fp, [
    'name', 'date', 'status',
    'maido_clock_in', 'maido_clock_out', 'maido_working_h',
    'pochi_clock_in', 'pochi_clock_out', 'pochi_working_h',
    'diff_clock_in_min', 'diff_clock_out_min', 'diff_working_h',
    'note',
]);

foreach ($results as $r) {
    fputcsv($fp, [
        $r['name'],
        $r['date'],
        $r['status'],
        $r['maido_in'],
        $r['maido_out'],
        $r['maido_working_h'],
        $r['pochi_in'],
        $r['pochi_out'],
        $r['pochi_working_h'],
        $r['diff_in'],
        $r['diff_out'],
        $r['diff_working'],
        $r['note'] ?? '',
    ]);
}

fclose($fp);
echo "\nCSV出力: {$outputCsvPath}\n";

// ── サマリー（人別） ────────────────────────────
echo "\n[人別サマリー]\n";
$byPerson = [];
foreach ($results as $r) {
    $name = $r['name'];
    if (!isset($byPerson[$name])) {
        $byPerson[$name] = ['ok' => 0, 'mismatch' => 0, 'missing' => 0, 'extra' => 0];
    }
    match ($r['status']) {
        'OK'               => $byPerson[$name]['ok']++,
        'MISMATCH'         => $byPerson[$name]['mismatch']++,
        'MISSING_IN_POCHI' => $byPerson[$name]['missing']++,
        'EXTRA_IN_POCHI'   => $byPerson[$name]['extra']++,
    };
}

ksort($byPerson);
echo str_pad('名前', 20) . str_pad('OK', 6) . str_pad('不一致', 8) . str_pad('欠落', 6) . str_pad('余剰', 6) . "\n";
echo str_repeat('-', 46) . "\n";
foreach ($byPerson as $name => $counts) {
    $nameDisplay = mbStrPad($name, 16);
    echo "{$nameDisplay}" . str_pad($counts['ok'], 6) . str_pad($counts['mismatch'], 8) . str_pad($counts['missing'], 6) . str_pad($counts['extra'], 6) . "\n";
}

echo "\n完了。\n";

// ── ヘルパー関数 ────────────────────────────────

/**
 * HH:MM 同士を比較し、差（分）を返す。一致なら "0"
 */
function compareTimes(?string $a, ?string $b): string
{
    if ($a === null || $b === null || $a === '' || $b === '') return '?';
    // HH:MM に正規化
    $aParts = explode(':', $a);
    $bParts = explode(':', $b);
    if (count($aParts) < 2 || count($bParts) < 2) return '?';

    $aMin = (int)$aParts[0] * 60 + (int)$aParts[1];
    $bMin = (int)$bParts[0] * 60 + (int)$bParts[1];
    $diff = $aMin - $bMin;

    return (string)$diff;
}

/**
 * mbStrPad: マルチバイト対応str_pad
 */
function mbStrPad(string $str, int $padLength, string $padString = ' ', int $padType = STR_PAD_RIGHT): string
{
    $strLen = mb_strwidth($str);
    if ($strLen >= $padLength) return $str;
    $diff = $padLength - $strLen;
    return match ($padType) {
        STR_PAD_RIGHT => $str . str_repeat($padString, $diff),
        STR_PAD_LEFT  => str_repeat($padString, $diff) . $str,
        STR_PAD_BOTH  => str_repeat($padString, (int)floor($diff / 2)) . $str . str_repeat($padString, (int)ceil($diff / 2)),
    };
}
