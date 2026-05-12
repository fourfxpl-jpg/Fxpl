<?php
ob_start(); // ✅ จุดที่ 1
include 'check_login.php';
include 'db.php';
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json; charset=utf-8');

$user_id      = $_SESSION['user_id'];
$guild_id     = '1462409196602396830';
$access_token = $_SESSION['access_token'] ?? null;

/* ================== FUNCTIONS ================== */
function apiRequest($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}

function getDiscordNickname($access_token, $guild_id) {
    if (!$access_token) return null;

    $guild = apiRequest("https://discord.com/api/users/@me/guilds/$guild_id/member", [
        'Authorization: Bearer ' . $access_token
    ]);

    return $guild->nick ?? null;
}

function jsonErr($msg) {
    ob_clean(); // ✅ จุดที่ 2
    echo json_encode(['success'=>false,'message'=>$msg]);
    exit;
}

/* ================== CHECK METHOD ================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonErr('Method not allowed');
}

/* ================== INPUT ================== */
$suspect_name   = trim($_POST['suspect_name'] ?? '');
$case_type      = trim($_POST['case_type'] ?? 'เคสดำ');
$location       = trim($_POST['location'] ?? '');
$jail_minutes   = intval($_POST['jail_minutes'] ?? 0);
$fine_amount    = intval($_POST['fine_amount'] ?? 0);
$items_json     = $_POST['items_json'] ?? '[]';
$assisting_json = $_POST['assisting_json'] ?? '[]';

/* ================== FIX สำคัญ ================== */
// 🔒 กันค่ามั่ว
$allowed_types = ['เคสดำ','เคสแดง'];
if (!in_array($case_type, $allowed_types)) {
    $case_type = 'เคสดำ';
}
// กันยาวเกิน DB
$case_type = mb_substr($case_type, 0, 20);

if (!$suspect_name) jsonErr('กรุณาระบุชื่อผู้ต้องหา');

/* ================== JSON ================== */
$items_arr     = json_decode($items_json, true);
$assisting_arr = json_decode($assisting_json, true);

if (!is_array($items_arr)) jsonErr('items json พัง');
if (!is_array($assisting_arr)) jsonErr('assisting json พัง');
if (empty($items_arr)) jsonErr('ต้องมีข้อหาอย่างน้อย 1');

/* ================== USER ================== */
$user_name = getDiscordNickname($access_token, $guild_id) ?? 'Unknown';

/* ================== WEBHOOK ================== */
$WEBHOOK_BLACK = 'https://discordapp.com/api/webhooks/1491754152856916039/I86xaT0eFFgOultIefxcLM6cPUgxg9yRPvHXdmAeEVwyO-vschixA6TYrutlDhR1DFzL';
$WEBHOOK_RED   = 'https://discordapp.com/api/webhooks/1491765665420541953/X3ZxwF3ct19KTW4_CZLxLEAGnrBE2akUjpETEWbsuGcbYIB1qv2k6Z4mIhshBnCfJLVT';

$DISCORD_WEBHOOK = ($case_type === 'เคสดำ') 
    ? $WEBHOOK_BLACK 
    : $WEBHOOK_RED;

/* ================== CASE NUMBER ================== */
$today = date('Ymd');
$stmt  = $conn->query("SELECT COUNT(*) FROM cases WHERE DATE(created_at)=CURDATE()");
$seq   = $stmt->fetchColumn() + 1;
$case_number = 'SMPD-' . $today . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

/* ================== ITEMS ================== */
$items_lines = [];
foreach ($items_arr as $item) {
    $sub_fine = ($item['price'] ?? 0) * ($item['qty'] ?? 1);
    $sub_jail = ($item['jail'] ?? 0) * ($item['qty'] ?? 1);

    $items_lines[] = "• {$item['name']} x{$item['qty']} (" .
        number_format($sub_fine) . "฿ | {$sub_jail}น.)";
}

$items_text = implode("\n", $items_lines);

// กันยาวเกิน Discord
if (mb_strlen($items_text) > 1000) {
    $items_text = mb_substr($items_text, 0, 1000) . "...";
}

/* ================== OFFICERS ================== */
$officer_mention = "<@{$user_id}> (ผู้บันทึก)";
foreach ($assisting_arr as $off) {
    if (!empty($off['user_id'])) {
        $officer_mention .= "\n<@{$off['user_id']}>";
    }
}

/* ================== EMBED ================== */
$embed = [
    'title'  => ($case_type === 'เคสดำ' ? '⚫' : '🔴') . " รายงานสถานะคดี | {$case_type}",
    'color'  => ($case_type === 'เคสดำ') ? 0x1a1a2e : 0xef4444,
    'fields' => [
        ['name'=>'👤 ผู้ต้องหา','value'=>$suspect_name,'inline'=>false],
        ['name'=>'⏳ จำคุก','value'=>"$jail_minutes นาที",'inline'=>true],
        ['name'=>'💸 ค่าปรับ','value'=>number_format($fine_amount).' บาท','inline'=>true],
    ],
    'footer' => ['text'=>"SMPD • {$user_name} • ".date('d/m/Y H:i')],
    'timestamp'=>date('c')
];

if ($location) {
    $embed['fields'][] = ['name'=>'📍 สถานที่','value'=>$location];
}
if ($items_text) {
    $embed['fields'][] = ['name'=>'⚖️ ข้อหา','value'=>$items_text];
}

$embed['fields'][] = ['name'=>'👮 เจ้าหน้าที่','value'=>$officer_mention];
$embed['fields'][] = ['name'=>'🔢 เลขเคส','value'=>"`$case_number`"];

/* ================== IMAGE ================== */
$uploaded_files = $_FILES['case_images'] ?? null;
$has_images = $uploaded_files && !empty($uploaded_files['tmp_name'][0]) && $uploaded_files['error'][0] === UPLOAD_ERR_OK;

if ($has_images) {
    $ext = pathinfo($uploaded_files['name'][0], PATHINFO_EXTENSION) ?: 'png';
    $embed['image'] = ['url'=>"attachment://case_image_0.$ext"];
}

/* ================== DISCORD ================== */
$discord_sent = 0;

if ($DISCORD_WEBHOOK) {
    $payload = [
        'username'=>'SMPD System',
        'embeds'=>[$embed]
    ];

    $ch = curl_init($DISCORD_WEBHOOK);

    if ($has_images) {
        $form = ['payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE)];

        foreach ($uploaded_files['tmp_name'] as $i => $tmp) {
            if (!is_uploaded_file($tmp)) continue;
            if ($uploaded_files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext  = pathinfo($uploaded_files['name'][$i], PATHINFO_EXTENSION) ?: 'png';
            $mime = mime_content_type($tmp) ?: 'image/png';
            $form["files[$i]"] = new CURLFile($tmp, $mime, "case_image_$i.$ext");
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http >= 200 && $http < 300) {
        $discord_sent = 1;
    } else {
        // log error
        file_put_contents('discord_error.log',
            date('Y-m-d H:i:s') . " | HTTP:$http | ERROR:$error | RESP:$response\n",
            FILE_APPEND
        );
    }
}

/* ================== DB ================== */
try {
    $stmt = $conn->prepare("
        INSERT INTO cases 
        (case_number, officer_id, officer_name, suspect_name, case_type, location,
         jail_minutes, fine_amount, items, items_text, assisting_officers, discord_sent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $case_number, $user_id, $user_name, $suspect_name,
        $case_type, $location, $jail_minutes, $fine_amount,
        $items_json, $items_text, $assisting_json, $discord_sent
    ]);

    ob_clean(); // ✅ จุดที่ 3
    echo json_encode([
        'success'=>true,
        'case_number'=>$case_number,
        'discord_sent'=>$discord_sent
    ]);

} catch (PDOException $e) {
    jsonErr('DB Error: '.$e->getMessage());
}
