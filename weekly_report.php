<?php
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

$WEBHOOK_URL = 'https://discordapp.com/api/webhooks/1491751563784486944/JCBLl_tWQ0on3iKyAdBFAiZ0HOHl15-oco-IWEDdaBMxYdv7Oy9m1aDbjkUc_AlnOXdM';

// ดึงข้อมูลย้อนหลัง 7 วัน
$start = date('Y-m-d H:i:s', strtotime('-7 days'));
$end   = date('Y-m-d H:i:s');

// นับเคสหลัก
$stmt = $conn->prepare("
    SELECT officer_name, COUNT(*) as total 
    FROM cases 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY officer_id, officer_name
    ORDER BY total DESC
");
$stmt->execute([$start, $end]);
$main_cases = $stmt->fetchAll();

// นับเคสช่วย
$stmt2 = $conn->prepare("
    SELECT assisting_officers 
    FROM cases 
    WHERE created_at BETWEEN ? AND ?
    AND assisting_officers != '[]'
    AND assisting_officers IS NOT NULL
");
$stmt2->execute([$start, $end]);
$assist_rows = $stmt2->fetchAll();

$assist_count = [];
foreach ($assist_rows as $row) {
    $assistants = json_decode($row['assisting_officers'], true);
    if (is_array($assistants)) {
        foreach ($assistants as $a) {
            $name = $a['user_name'] ?? 'Unknown';
            $assist_count[$name] = ($assist_count[$name] ?? 0) + 1;
        }
    }
}

// รวมทั้งหมด
$all_officers = [];
foreach ($main_cases as $row) {
    $all_officers[$row['officer_name']] = ($all_officers[$row['officer_name']] ?? 0) + $row['total'];
}
foreach ($assist_count as $name => $count) {
    $all_officers[$name] = ($all_officers[$name] ?? 0) + $count;
}

arsort($all_officers);
$grand_total = array_sum($all_officers);

$lines = '';
$rank = 1;
foreach ($all_officers as $name => $total) {
    $medal = match($rank) {
        1 => '🥇', 2 => '🥈', 3 => '🥉',
        default => "#{$rank}"
    };
    $main   = 0;
    foreach ($main_cases as $row) {
        if ($row['officer_name'] === $name) { $main = $row['total']; break; }
    }
    $assist = $assist_count[$name] ?? 0;
    $lines .= "{$medal} **{$name}** — {$total} เคส (บันทึกเอง {$main} | ช่วย {$assist})\n";
    $rank++;
}

if (empty($all_officers)) $lines = "ไม่มีเคสในสัปดาห์นี้";

$week_start = date('d/m/Y', strtotime('-7 days'));
$week_end   = date('d/m/Y');

$embed = [
    'username' => 'SMPD System',
    'embeds'   => [[
        'title'       => '📊 รายงานสรุปเคสประจำสัปดาห์',
        'description' => "**ช่วงเวลา:** {$week_start} — {$week_end}\n\n{$lines}\n━━━━━━━━━━━━━━━\n**รวมทั้งสัปดาห์: {$grand_total} เคส**",
        'color'       => 0xf89b29,
        'footer'      => ['text' => 'SMPD Weekly Report • ' . date('d/m/Y H:i')],
        'timestamp'   => date('c'),
    ]]
];

$ch = curl_init($WEBHOOK_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($embed, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Sent! HTTP: $code | Response: $res";
?>
