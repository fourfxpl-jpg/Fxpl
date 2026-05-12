<?php
include 'check_login.php'; 
include 'db.php'; 
date_default_timezone_set('Asia/Bangkok');

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_name FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_row = $stmt->fetch();
$user_name = $user_row['user_name'] ?? ($_SESSION['user_name'] ?? 'Unknown');

// ✅ ฟังก์ชันแปลง client timestamp (ms) → datetime ตาม timezone ไทย
function msToThaiDatetime($ms) {
    $dt = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
    $dt->setTimestamp(intval($ms / 1000));
    return $dt->format('Y-m-d H:i:s');
}

function nowThaiDatetime() {
    $dt = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
    return $dt->format('Y-m-d H:i:s');
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $client_time_ms = isset($_POST['client_time']) && is_numeric($_POST['client_time']) 
                      ? intval($_POST['client_time']) 
                      : null;

    if ($action == 'start') {
        $check = $conn->prepare("SELECT id FROM duty_logs WHERE user_id = ? AND status = 1");
        $check->execute([$user_id]);
        
        if (!$check->fetch()) {
            // ✅ ใช้เวลาจาก client เป็นหลัก fallback เป็น server
            $start_time = $client_time_ms 
                          ? msToThaiDatetime($client_time_ms) 
                          : nowThaiDatetime();
            $client_timestamp = $client_time_ms;

            $stmt = $conn->prepare("INSERT INTO duty_logs (user_id, user_name, start_time, client_timestamp, status) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$user_id, $user_name, $start_time, $client_timestamp]);
        }
    } 
    elseif ($action == 'stop') {
        $stmt = $conn->prepare("SELECT id, start_time, client_timestamp FROM duty_logs WHERE user_id = ? AND status = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $log = $stmt->fetch();

        if ($log) {
            // ✅ ใช้เวลาจาก client เป็นหลัก fallback เป็น server
            $end_time = $client_time_ms 
                        ? msToThaiDatetime($client_time_ms) 
                        : nowThaiDatetime();

            // ✅ คำนวณ duration จาก ms ถ้ามีทั้งคู่ ไม่งั้น fallback datetime
            if ($log['client_timestamp'] && $client_time_ms) {
                $duration = intval(($client_time_ms - $log['client_timestamp']) / 1000);
            } else {
                $duration = strtotime($end_time) - strtotime($log['start_time']);
            }

            if ($duration < 0) $duration = 0;

            $stmt = $conn->prepare("UPDATE duty_logs SET end_time = ?, duration = ?, status = 0 WHERE id = ?");
            $stmt->execute([$end_time, $duration, $log['id']]);
        }
    }
}

header("Location: duty.php");
exit();
?>
