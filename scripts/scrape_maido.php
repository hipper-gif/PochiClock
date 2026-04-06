<?php
/**
 * まいどシステム スクレイパー
 * 月間の勤怠データ（出退勤時刻・実働時間）をCSVに出力
 */

$year = $argv[1] ?? '2026';
$month = $argv[2] ?? '3';
$shopId = '3661';  // Smiley
$monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);

$cookieFile = tempnam(sys_get_temp_dir(), 'maido_');

function fetch(string $url, string $cookieFile, ?array $postData = null): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $body = curl_exec($ch);
    curl_close($ch);
    return $body;
}

// Step 1: Login
echo "Logging in...\n";
fetch('https://maido-system.jp/users/login', $cookieFile);
$body = fetch('https://maido-system.jp/users/login', $cookieFile, [
    '_method' => 'POST',
    'data[User][shop_id]' => '3660',
    'data[User][login_id]' => 'nikoniko173',
    'data[User][password]' => '2525173',
]);
if (strpos($body, 'ログアウト') === false) {
    die("Login failed!\n");
}
echo "Login OK.\n";

// Step 2: Get staff list from monthly overview
echo "Fetching staff list...\n";
// POST search to set session, then GET the result page
fetch('https://maido-system.jp/timecards/history', $cookieFile, [
    'data[Timecard][shop_id]' => $shopId,
    'data[Req][from_y]' => $year,
    'data[Req][from_m]' => $month,
]);
// Need separate GET because POST response doesn't include the grid
$body = fetch("https://maido-system.jp/timecards/history/{$shopId}/?from_y={$year}&from_m={$month}&from_d=1", $cookieFile);
if (strpos($body, 'goTimecardView') === false) {
    // Retry: sometimes needs the POST result directly
    $body = fetch('https://maido-system.jp/timecards/history', $cookieFile, [
        'data[Timecard][shop_id]' => $shopId,
        'data[Req][from_y]' => $year,
        'data[Req][from_m]' => $month,
    ]);
}

// Extract staff IDs and names using timecard_error_{id} pattern + nearby link text
preg_match_all('/timecard_error_(\d+)[^>]*>.*?<\/span>\s*(.*?)\s*<\/a>/is', $body, $staffRows, PREG_SET_ORDER);
$staffList = [];
foreach ($staffRows as $s) {
    $staffList[$s[1]] = trim(strip_tags($s[2]));
}
echo "Found " . count($staffList) . " staff.\n";

// Step 3: Fetch each staff's timecard view
$outputFile = __DIR__ . "/../storage/maido_{$year}_{$monthPadded}.csv";
$fp = fopen($outputFile, 'w');
fwrite($fp, "\xEF\xBB\xBF");
fputcsv($fp, ['maido_id', 'name', 'date', 'day_of_week', 'clock_in', 'clock_out', 'subtotal_h', 'total_h', 'break_h', 'scheduled_break_h', 'working_h']);

$total = count($staffList);
$i = 0;
foreach ($staffList as $staffId => $name) {
    $i++;
    echo sprintf("[%d/%d] %s...\n", $i, $total, $name);

    $tcBody = fetch("https://maido-system.jp/timecards/view/{$staffId}/", $cookieFile, [
        'from_y' => $year,
        'from_m' => $monthPadded,
    ]);

    // Check for session expiry
    if (strpos($tcBody, 'login') !== false && strpos($tcBody, 'タイムカード') === false) {
        echo "  Session expired, re-logging in...\n";
        fetch('https://maido-system.jp/users/login', $cookieFile);
        fetch('https://maido-system.jp/users/login', $cookieFile, [
            '_method' => 'POST',
            'data[User][shop_id]' => '3660',
            'data[User][login_id]' => 'nikoniko173',
            'data[User][password]' => '2525173',
        ]);
        $tcBody = fetch("https://maido-system.jp/timecards/view/{$staffId}/", $cookieFile, [
            'from_y' => $year,
            'from_m' => $monthPadded,
        ]);
    }

    // Find the main table (>10 rows = day rows)
    preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $tcBody, $tables);
    $foundData = false;
    foreach ($tables[0] as $ti => $table) {
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $table, $rows);
        if (count($rows[1]) < 10) continue;

        foreach ($rows[1] as $row) {
            preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $row, $cells);
            $vals = array_map(fn($c) => trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($c)))), $cells[1]);

            if (empty($vals) || count($vals) < 10) continue;

            // Parse date: "3/2 (月)" format
            if (!preg_match('/(\d+)\/(\d+).*?\((.)\)/u', $vals[0] ?? '', $dateMatch)) {
                continue;
            }
            $foundData = true;

            $day = (int)$dateMatch[2];
            $dow = $dateMatch[3];
            $date = sprintf('%s-%s-%02d', $year, $monthPadded, $day);

            $clockIn = trim($vals[1] ?? '');
            $clockOut = trim($vals[2] ?? '');

            // Skip days with no data
            if (empty($clockIn) && empty($clockOut)) continue;

            // vals: 0=date, 1=clock_in, 2=clock_out, 3=time1, 4=time2, 5=time3, 6=subtotal, 7=total, 8=break, 9=scheduled_break, 10=working
            fputcsv($fp, [
                $staffId,
                $name,
                $date,
                $dow,
                $clockIn,
                $clockOut,
                str_replace([' h', ' '], '', $vals[6] ?? ''),  // subtotal
                str_replace([' h', ' '], '', $vals[7] ?? ''),  // total
                str_replace([' h', ' '], '', $vals[8] ?? ''),  // break
                str_replace([' h', ' '], '', $vals[9] ?? ''),  // scheduled break
                str_replace([' h', ' '], '', $vals[10] ?? ''), // working
            ]);
        }
        break; // only need the first matching table
    }

    usleep(150000); // 150ms delay
}

fclose($fp);
echo "\nDone! Output: {$outputFile}\n";
echo "Records written for {$year}/{$monthPadded}\n";
unlink($cookieFile);
