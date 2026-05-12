<?php 
include 'check_login.php';
include 'db.php'; 
include 'navbar.php'; 

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM duty_logs WHERE user_id = ? AND status = 1 ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$current_duty = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Police All Star PD - Duty</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="duty-container">
        <div class="duty-status">
            <span class="dot <?= $current_duty ? 'online' : '' ?>"></span>
            <?= $current_duty ? "ON DUTY" : "OFF DUTY" ?>
        </div>
        <div class="timer-display" id="timer">00:00:00</div>

        <form id="duty-form" action="action_duty.php" method="POST">
            <input type="hidden" name="client_time" id="client_time">
            <?php if(!$current_duty): ?>
                <button type="button" onclick="submitDuty('start')" class="btn-duty-start">เริ่มเข้าเวรใหม่</button>
            <?php else: ?>
                <button type="button" onclick="submitDuty('stop')" class="btn-duty-stop" style="background:#ef4444;">จบการปฏิบัติหน้าที่</button>
            <?php endif; ?>
            <input type="hidden" name="action" id="duty-action">
        </form>
    </div>

    <div class="table-container" style="margin-top:20px;">
        <h3 style="color:white; margin-bottom:10px;">เจ้าหน้าที่กำลังปฏิบัติงาน</h3>
        <table class="data-table">
            <thead>
                <tr><th>ชื่อ</th><th>สถานะ</th><th>เริ่มเมื่อ</th></tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->query("SELECT * FROM duty_logs WHERE status = 1");
                $active = $stmt->fetchAll();
                foreach($active as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                        <td style="color:#10b981;">ปฏิบัติงาน</td>
                        <?php
                            // ส่ง client_timestamp (ms) มาให้ JS แสดงแทน
                            // ถ้ายังไม่มีคอลัมน์ client_timestamp ให้ fallback เป็น start_time
                            $cts = $row['client_timestamp'] ?? null;
                        ?>
                        <td class="duty-start-time"
                            data-cts="<?= $cts ? intval($cts) : '' ?>"
                            data-fallback="<?= htmlspecialchars($row['start_time']) ?>">
                            --:-- น.
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // ============================================================
    // TIMER — ใช้ client_timestamp ที่บันทึกตอนกดปุ่มเริ่มเวร
    // ถ้ายังไม่มีคอลัมน์ใหม่ให้ fallback เป็นวิธีเดิม
    // ============================================================
    <?php if($current_duty): ?>
    const startTimestamp = <?= 
        isset($current_duty['client_timestamp']) && $current_duty['client_timestamp']
            ? intval($current_duty['client_timestamp'])           // ms จาก client โดยตรง
            : strtotime($current_duty['start_time']) * 1000       // fallback
    ?>;
    <?php else: ?>
    const startTimestamp = 0;
    <?php endif; ?>

    function updateTimer() {
        if (!startTimestamp) return;
        const diff = Date.now() - startTimestamp;
        if (diff < 0) return;
        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        document.getElementById('timer').innerText =
            h.toString().padStart(2,'0') + ':' +
            m.toString().padStart(2,'0') + ':' +
            s.toString().padStart(2,'0');
    }
    setInterval(updateTimer, 1000);
    updateTimer();

    // ============================================================
    // ตาราง "เริ่มเมื่อ" — แปลงเป็นเวลาของ browser คนดู
    // ============================================================
    document.querySelectorAll('.duty-start-time').forEach(function(td) {
        const cts = td.getAttribute('data-cts');
        let d;

        if (cts) {
            // ✅ มี client_timestamp → ใช้เลย (ms)
            d = new Date(parseInt(cts));
        } else {
            // fallback: parse start_time string จาก DB
            const raw = td.getAttribute('data-fallback'); // "2025-01-01 12:09:00"
            d = new Date(raw.replace(' ', 'T'));           // ISO format ให้ JS อ่านได้ทุก browser
        }

        if (!isNaN(d.getTime())) {
            const h = d.getHours().toString().padStart(2, '0');
            const m = d.getMinutes().toString().padStart(2, '0');
            td.textContent = h + ':' + m + ' น.';
        }
    });

    function submitDuty(action) {
        // ✅ ส่งเวลา ms จากคอม user ตอนกดปุ่มเลย
        document.getElementById('client_time').value = Date.now(); // ms (ไม่หาร 1000)
        document.getElementById('duty-action').value = action;
        document.getElementById('duty-form').submit();
    }
</script>
    <footer style="text-align:center; padding:28px 0 20px; color:#4a5568; font-size:12px; letter-spacing:0.5px; font-family:'Noto Sans Thai',sans-serif;">
    © Police All Star. by Four Fxpl .achikp_43035
</footer>
</body>
</html>
