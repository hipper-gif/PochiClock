<?php
/**
 * 職種グループ別WorkRuleセットアップスクリプト
 *
 * CLAUDE.mdの部門別ルール + まいどCSVの指定休憩分布に基づき、
 * JobGroup と WorkRule を作成する。
 *
 * Usage: php scripts/setup_work_rules.php [--dry-run]
 * 前提: SSHトンネル接続済み
 */

$dryRun = in_array('--dry-run', $argv);

// .env
$envFile = __DIR__ . '/../.env';
$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'], $env['DB_DATABASE']);
$pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo $dryRun ? "[DRY RUN]\n" : "[LIVE]\n";

// Get tenant_id from first user (may be NULL if multi-tenant not yet active)
$tenantId = $pdo->query("SELECT tenant_id FROM users LIMIT 1")->fetchColumn() ?: null;
echo "tenant_id: " . ($tenantId ?? 'NULL (シングルテナントモード)') . "\n\n";

// Department map
$depts = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$deptMap = [];
foreach ($depts as $d) $deptMap[$d['name']] = $d['id'];

/**
 * 職種グループ定義 (CLAUDE.md + まいどデータ分析に基づく)
 *
 * break_tiers: 段階的休憩ルール
 *   - thresholdHours: この時間以上勤務した場合に適用
 *   - breakMinutes: 適用する休憩時間（分）
 */
$jobGroupDefs = [
    '配食-調理' => [
        'description' => '配食部門 調理スタッフ（10名）',
        'departments' => ['調理'],
        'rule' => [
            'work_start_time' => '08:00',
            'work_end_time' => '14:00',
            'default_break_minutes' => 15,
            'break_tiers' => json_encode([
                ['thresholdHours' => 0, 'breakMinutes' => 15],
                ['thresholdHours' => 8, 'breakMinutes' => 75],
            ]),
            'allow_multiple_clock_ins' => false,
            'rounding_unit' => 1,
            'clock_in_rounding' => 'none',
            'clock_out_rounding' => 'none',
            'early_clock_in_cutoff' => '08:00',  // 8:00以前は8:00に丸め
            'early_clock_in_cutoff_pm' => null,
        ],
    ],
    '配食-配達' => [
        'description' => '配食部門 配達スタッフ（17名）2回出勤パターン',
        'departments' => ['配達'],
        'rule' => [
            'work_start_time' => '09:30',
            'work_end_time' => '18:00',
            'default_break_minutes' => 0,
            'break_tiers' => json_encode([
                ['thresholdHours' => 0, 'breakMinutes' => 0],
                ['thresholdHours' => 6, 'breakMinutes' => 60],
            ]),
            'allow_multiple_clock_ins' => true,   // 午前便+午後便
            'rounding_unit' => 1,
            'clock_in_rounding' => 'none',
            'clock_out_rounding' => 'none',
            'early_clock_in_cutoff' => '09:30',   // 午前便9:30以前カット
            'early_clock_in_cutoff_pm' => '14:00', // 午後便14:00以前カット
        ],
    ],
    '訪問介護' => [
        'description' => '訪問介護 常勤スタッフ（4名）直行直帰あり',
        'departments' => ['訪問介護'],
        'rule' => [
            'work_start_time' => '08:30',
            'work_end_time' => '17:30',
            'default_break_minutes' => 60,
            'break_tiers' => json_encode([
                ['thresholdHours' => 0, 'breakMinutes' => 0],
                ['thresholdHours' => 6, 'breakMinutes' => 60],
            ]),
            'allow_multiple_clock_ins' => false,
            'rounding_unit' => 1,
            'clock_in_rounding' => 'none',
            'clock_out_rounding' => 'none',
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ],
    ],
    '居宅介護' => [
        'description' => 'ケアプランセンター（5名）固定8:30-17:30',
        'departments' => ['ケアプランセンター', '介護タクシー'],
        'rule' => [
            'work_start_time' => '08:30',
            'work_end_time' => '17:30',
            'default_break_minutes' => 60,
            'break_tiers' => json_encode([
                ['thresholdHours' => 0, 'breakMinutes' => 0],
                ['thresholdHours' => 6, 'breakMinutes' => 60],
            ]),
            'allow_multiple_clock_ins' => false,
            'rounding_unit' => 1,
            'clock_in_rounding' => 'none',
            'clock_out_rounding' => 'none',
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ],
    ],
    '美容' => [
        'description' => 'Can I Dressy 寝屋川・守口（10名）シフト制',
        'departments' => ['美容'],
        'rule' => [
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'default_break_minutes' => 60,
            'break_tiers' => null,
            'allow_multiple_clock_ins' => false,
            'rounding_unit' => 1,
            'clock_in_rounding' => 'none',
            'clock_out_rounding' => 'none',
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ],
    ],
    '本社' => [
        'description' => 'Smiley本社（4名）固定9:00-18:00',
        'departments' => ['Smiley'],
        'rule' => [
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'default_break_minutes' => 60,
            'break_tiers' => json_encode([
                ['thresholdHours' => 0, 'breakMinutes' => 30],
                ['thresholdHours' => 6, 'breakMinutes' => 60],
            ]),
            'allow_multiple_clock_ins' => false,
            'rounding_unit' => 1,
            'clock_in_rounding' => 'none',
            'clock_out_rounding' => 'none',
            'early_clock_in_cutoff' => null,
            'early_clock_in_cutoff_pm' => null,
        ],
    ],
];

// UUID generator
function uuid(): string {
    return sprintf('%s%s-%s-%s-%s-%s%s%s',
        bin2hex(random_bytes(4)), '', bin2hex(random_bytes(2)),
        dechex(0x4000 | random_int(0, 0x0fff)),
        dechex(0x8000 | random_int(0, 0x3fff)),
        bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)));
}

$pdo->beginTransaction();
try {
    foreach ($jobGroupDefs as $name => $def) {
        echo "--- {$name} ---\n";

        // 1. Create JobGroup
        $jgId = uuid();
        $jgSql = "INSERT INTO job_groups (id, tenant_id, name, description, created_at, updated_at)
                   VALUES (:id, :tid, :name, :desc, NOW(), NOW())
                   ON DUPLICATE KEY UPDATE description = VALUES(description)";

        // Check if already exists
        $existing = $pdo->prepare("SELECT id FROM job_groups WHERE tenant_id = ? AND name = ?");
        $existing->execute([$tenantId, $name]);
        $existingRow = $existing->fetch();

        if ($existingRow) {
            $jgId = $existingRow['id'];
            echo "  JobGroup: 既存 ({$jgId})\n";
        } else {
            if (!$dryRun) {
                $stmt = $pdo->prepare($jgSql);
                $stmt->execute(['id' => $jgId, 'tid' => $tenantId, 'name' => $name, 'desc' => $def['description']]);
            }
            echo "  JobGroup: 作成 ({$jgId})\n";
        }

        // 2. Link departments to JobGroup
        foreach ($def['departments'] as $deptName) {
            if (!isset($deptMap[$deptName])) {
                echo "  [WARN] 部署 '{$deptName}' が見つかりません\n";
                continue;
            }
            if (!$dryRun) {
                $pdo->prepare("UPDATE departments SET job_group_id = ? WHERE id = ?")->execute([$jgId, $deptMap[$deptName]]);
            }
            echo "  部署リンク: {$deptName}\n";
        }

        // 3. Create WorkRule for JobGroup
        $rule = $def['rule'];
        $existingRule = $pdo->prepare("SELECT id FROM work_rules WHERE scope = 'JOB_GROUP' AND job_group_id = ?");
        $existingRule->execute([$jgId]);

        if ($existingRule->fetch()) {
            if (!$dryRun) {
                $upd = $pdo->prepare("UPDATE work_rules SET
                    work_start_time = ?, work_end_time = ?, default_break_minutes = ?,
                    break_tiers = ?, allow_multiple_clock_ins = ?, rounding_unit = ?,
                    clock_in_rounding = ?, clock_out_rounding = ?,
                    early_clock_in_cutoff = ?, early_clock_in_cutoff_pm = ?,
                    updated_at = NOW()
                    WHERE scope = 'JOB_GROUP' AND job_group_id = ?");
                $upd->execute([
                    $rule['work_start_time'], $rule['work_end_time'], $rule['default_break_minutes'],
                    $rule['break_tiers'], $rule['allow_multiple_clock_ins'] ? 1 : 0, $rule['rounding_unit'],
                    $rule['clock_in_rounding'], $rule['clock_out_rounding'],
                    $rule['early_clock_in_cutoff'], $rule['early_clock_in_cutoff_pm'],
                    $jgId,
                ]);
            }
            echo "  WorkRule: 更新\n";
        } else {
            $wrId = uuid();
            if (!$dryRun) {
                $ins = $pdo->prepare("INSERT INTO work_rules
                    (id, tenant_id, scope, job_group_id, work_start_time, work_end_time,
                     default_break_minutes, break_tiers, allow_multiple_clock_ins, rounding_unit,
                     clock_in_rounding, clock_out_rounding, early_clock_in_cutoff, early_clock_in_cutoff_pm,
                     created_at, updated_at)
                    VALUES (?, ?, 'JOB_GROUP', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $ins->execute([
                    $wrId, $tenantId, $jgId,
                    $rule['work_start_time'], $rule['work_end_time'], $rule['default_break_minutes'],
                    $rule['break_tiers'], $rule['allow_multiple_clock_ins'] ? 1 : 0, $rule['rounding_unit'],
                    $rule['clock_in_rounding'], $rule['clock_out_rounding'],
                    $rule['early_clock_in_cutoff'], $rule['early_clock_in_cutoff_pm'],
                ]);
            }
            echo "  WorkRule: 作成\n";
        }

        echo "\n";
    }

    // 4. Update SYSTEM rule
    echo "--- SYSTEMルール更新 ---\n";
    if (!$dryRun) {
        $pdo->prepare("UPDATE work_rules SET
            default_break_minutes = 60,
            break_tiers = ?,
            updated_at = NOW()
            WHERE scope = 'SYSTEM'")->execute([
            json_encode([
                ['thresholdHours' => 0, 'breakMinutes' => 0],
                ['thresholdHours' => 6, 'breakMinutes' => 45],
                ['thresholdHours' => 8, 'breakMinutes' => 60],
            ]),
        ]);
    }
    echo "  SYSTEM: break_tiers 更新（労基法準拠: 6h=45min, 8h=60min）\n";

    if ($dryRun) {
        $pdo->rollBack();
        echo "\n[DRY RUN] ロールバックしました。--dry-run を外して実行してください\n";
    } else {
        $pdo->commit();
        echo "\n[完了] 全てコミットしました\n";
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

// Summary
echo "\n=== 設定サマリ ===\n";
$rules = $pdo->query("
    SELECT wr.scope, jg.name as jg_name, wr.work_start_time, wr.work_end_time,
           wr.default_break_minutes, wr.break_tiers, wr.allow_multiple_clock_ins,
           wr.early_clock_in_cutoff, wr.early_clock_in_cutoff_pm
    FROM work_rules wr
    LEFT JOIN job_groups jg ON jg.id = wr.job_group_id
    ORDER BY wr.scope, jg.name
")->fetchAll();

foreach ($rules as $r) {
    $label = $r['scope'] === 'SYSTEM' ? 'SYSTEM' : $r['jg_name'];
    echo sprintf(
        "%s: %s-%s break=%dmin multi=%s cutoff=%s/%s tiers=%s\n",
        str_pad($label, 12),
        $r['work_start_time'], $r['work_end_time'],
        $r['default_break_minutes'],
        $r['allow_multiple_clock_ins'] ? 'Y' : 'N',
        $r['early_clock_in_cutoff'] ?? '-',
        $r['early_clock_in_cutoff_pm'] ?? '-',
        $r['break_tiers'] ? 'Y' : 'N'
    );
}
