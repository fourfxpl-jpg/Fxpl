<?php 
include 'check_login.php'; 
include 'db.php'; 
date_default_timezone_set('Asia/Bangkok');

$user_id = $_SESSION['user_id'];

// ===== เวลางานรวมทั้งหมด =====
$stmt = $conn->prepare("SELECT SUM(duration) as total FROM duty_logs WHERE user_id = ? AND status = 0");
$stmt->execute([$user_id]);
$total_sec = $stmt->fetch()['total'] ?? 0;
$h_all = floor($total_sec / 3600);
$m_all = floor(($total_sec % 3600) / 60);

// ===== เวลางานสัปดาห์นี้ (Mon-Sun) =====
$stmt = $conn->prepare("
    SELECT SUM(duration) as weekly FROM duty_logs 
    WHERE user_id = ? AND status = 0 
    AND start_time >= DATE(DATE_SUB(NOW(), INTERVAL WEEKDAY(NOW()) DAY))
");
$stmt->execute([$user_id]);
$week_sec = $stmt->fetch()['weekly'] ?? 0;
$h_week = floor($week_sec / 3600);
$m_week = floor(($week_sec % 3600) / 60);

// ===== เวลางานวันนี้ =====
$stmt = $conn->prepare("
    SELECT SUM(duration) as daily FROM duty_logs 
    WHERE user_id = ? AND status = 0 
    AND DATE(start_time) = CURDATE()
");
$stmt->execute([$user_id]);
$day_sec = $stmt->fetch()['daily'] ?? 0;
$h_day = floor($day_sec / 3600);
$m_day = floor(($day_sec % 3600) / 60);

// ===== ถ้ากำลัง On Duty อยู่ ให้รวมเวลาปัจจุบันด้วย =====
$stmt = $conn->prepare("SELECT CONVERT_TZ(start_time, '+00:00', '+07:00') as start_time FROM duty_logs WHERE user_id = ? AND status = 1 LIMIT 1");
$stmt->execute([$user_id]);
$active = $stmt->fetch();
$active_elapsed_ms = 0;
if ($active) {
    $elapsed_stmt = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) as elapsed");
    $elapsed_stmt->execute([$active['start_time']]);
    $elapsed_sec = $elapsed_stmt->fetch()['elapsed'] ?? 0;
    $active_elapsed_ms = max(0, (int)$elapsed_sec * 1000);
}

// ===== เคสสัปดาห์นี้ =====
$stmt = $conn->prepare("
    SELECT COUNT(*) as cnt FROM cases 
    WHERE officer_id = ? 
    AND created_at >= DATE(DATE_SUB(NOW(), INTERVAL WEEKDAY(NOW()) DAY))
");
$stmt->execute([$user_id]);
$cases_week = $stmt->fetch()['cnt'] ?? 0;

// ===== เคสรวมทั้งหมด =====
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM cases WHERE officer_id = ?");
$stmt->execute([$user_id]);
$cases_all = $stmt->fetch()['cnt'] ?? 0;

// ===== เคสวันนี้ =====
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM cases WHERE officer_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$user_id]);
$cases_today = $stmt->fetch()['cnt'] ?? 0;

// ===== ประวัติเวรรายวัน 14 วันย้อนหลัง =====
$stmt = $conn->prepare("
    SELECT DATE(start_time) as duty_date,
           SUM(duration) as day_total,
           COUNT(*) as sessions
    FROM duty_logs 
    WHERE user_id = ? AND status = 0 AND start_time >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(start_time)
    ORDER BY duty_date DESC
");
$stmt->execute([$user_id]);
$daily_logs = $stmt->fetchAll();

// ===== ประวัติเคสล่าสุด 20 รายการ =====
$stmt = $conn->prepare("SELECT * FROM cases WHERE officer_id = ? ORDER BY id DESC LIMIT 20");
$stmt->execute([$user_id]);
$my_cases = $stmt->fetchAll();

// ===== Chart data - 14 วัน =====
$chart_labels = [];
$chart_hours  = [];
$chart_cases  = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $label = date('d/m', strtotime($d));
    $chart_labels[] = $label;
    // หาเวลาของวัน
    $found = array_filter($daily_logs, fn($r) => $r['duty_date'] === $d);
    $found = array_values($found);
    $hrs = $found ? round($found[0]['day_total'] / 3600, 1) : 0;
    $chart_hours[] = $hrs;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Police All Star PD - สถิติ</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Noto+Sans+Thai:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== STATS PAGE ===== */
        .stats-hero {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .hero-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px 20px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .hero-card:hover { transform: translateY(-3px); }
        .hero-card::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.04;
            background: var(--accent-color, var(--primary));
        }
        .hero-card .card-icon {
            font-size: 1.6rem;
            margin-bottom: 8px;
            display: block;
        }
        .hero-card .card-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
        }
        .hero-card .card-value {
            font-size: 1.9rem;
            font-weight: 800;
            color: var(--accent-color, var(--primary));
            font-family: 'Rajdhani', sans-serif;
            line-height: 1.1;
        }
        .hero-card .card-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 3px;
        }
        .hero-card .card-bar {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--accent-color, var(--primary));
            opacity: 0.5;
        }

        /* Live duty timer */
        .live-duty-banner {
            background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(16,185,129,0.05));
            border: 1px solid rgba(16,185,129,0.3);
            border-radius: 14px;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }
        .live-dot {
            width: 10px; height: 10px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: pulse-green 1.5s infinite;
        }
        @keyframes pulse-green {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.6); }
            50% { box-shadow: 0 0 0 6px rgba(16,185,129,0); }
        }
        .live-label { font-size: 14px; color: #10b981; font-weight: 600; }
        .live-timer {
            font-size: 2.2rem;
            font-weight: 800;
            color: #10b981;
            font-family: 'Rajdhani', monospace;
            letter-spacing: 2px;
        }

        /* Charts & tables section */
        .stats-grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        @media (max-width: 900px) {
            .stats-grid-2col { grid-template-columns: 1fr; }
        }

        .stat-section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px;
        }
        .stat-section-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stat-section-title span { color: var(--primary); }

        /* Bar chart */
        .bar-chart {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            height: 120px;
        }
        .bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            height: 100%;
            justify-content: flex-end;
        }
        .bar {
            width: 100%;
            background: linear-gradient(180deg, var(--primary), rgba(0,102,255,0.4));
            border-radius: 4px 4px 0 0;
            min-height: 2px;
            transition: height 0.5s ease;
            cursor: default;
            position: relative;
        }
        .bar:hover::after {
            content: attr(data-val) 'h';
            position: absolute;
            top: -22px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: #fff;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
        }
        .bar-label {
            font-size: 9px;
            color: var(--text-muted);
            text-align: center;
            white-space: nowrap;
        }

        /* Daily history table */
        .duty-history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .duty-history-table th {
            text-align: left;
            font-size: 11px;
            color: var(--text-muted);
            padding: 8px 10px;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .duty-history-table td {
            padding: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
            font-size: 13px;
        }
        .duty-history-table tr:last-child td { border-bottom: none; }
        .duty-history-table tr:hover td { background: rgba(255,255,255,0.02); }

        .time-pill {
            background: rgba(0,102,255,0.1);
            border: 1px solid rgba(0,102,255,0.25);
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            font-family: 'Rajdhani', sans-serif;
        }

        /* Cases table */
        .cases-full-table {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px;
        }
        .badge-type-black {
            background: rgba(0,0,0,0.5);
            border: 1px solid #444;
            color: #aaa;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .badge-type-red {
            background: rgba(239,68,68,0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .badge-discord-sent {
            background: rgba(16,185,129,0.1);
            border: 1px solid #10b981;
            color: #10b981;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .empty-row {
            text-align: center;
            padding: 40px !important;
            color: var(--text-muted);
            font-size: 14px;
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
        }
        .page-header-sub {
            color: var(--text-muted);
            font-size: 13px;
            margin-top: 2px;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>📊 สถิติการปฏิบัติหน้าที่</h1>
            <div class="page-header-sub">ข้อมูล ณ <?= date('d/m/Y H:i') ?> น.</div>
        </div>
    </div>

    <!-- Live Duty Banner (แสดงเฉพาะตอน On Duty) -->
    <?php if($active): ?>
    <div class="live-duty-banner">
        <div>
            <span class="live-dot"></span>
            <span class="live-label">กำลังปฏิบัติหน้าที่อยู่</span>
            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">เริ่มเวร <?= date('H:i', strtotime($active['start_time'])) ?> น.</div>
        </div>
        <div class="live-timer" id="live-timer">00:00:00</div>
    </div>
    <?php endif; ?>

    <!-- Hero Stats Cards -->
    <div class="stats-hero">
        <!-- วันนี้ -->
        <div class="hero-card" style="--accent-color:#0066FF;">
            <span class="card-icon">☀️</span>
            <div class="card-label">เวลางานวันนี้</div>
            <div class="card-value" id="today-display">
                <?= $h_day ?><small style="font-size:1rem;">ชม.</small><?= $m_day ?><small style="font-size:1rem;">น.</small>
            </div>
            <div class="card-sub"><?= date('l, d M Y') ?></div>
            <div class="card-bar"></div>
        </div>

        <!-- สัปดาห์นี้ -->
        <div class="hero-card" style="--accent-color:#0066FF;">
            <span class="card-icon">📅</span>
            <div class="card-label">เวลางานสัปดาห์นี้</div>
            <div class="card-value">
                <?= $h_week ?><small style="font-size:1rem;">ชม.</small><?= $m_week ?><small style="font-size:1rem;">น.</small>
            </div>
            <div class="card-sub">จันทร์ - <?= date('l') ?></div>
            <div class="card-bar"></div>
        </div>

        <!-- รวมทั้งหมด -->
        <div class="hero-card" style="--accent-color:#a78bfa;">
            <span class="card-icon">🏆</span>
            <div class="card-label">เวลางานรวมทั้งหมด</div>
            <div class="card-value">
                <?= $h_all ?><small style="font-size:1rem;">ชม.</small><?= $m_all ?><small style="font-size:1rem;">น.</small>
            </div>
            <div class="card-sub">ตลอดช่วงเวลาที่ใช้งาน</div>
            <div class="card-bar"></div>
        </div>

        <!-- เคสวันนี้ -->
        <div class="hero-card" style="--accent-color:#0066FF;">
            <span class="card-icon">📁</span>
            <div class="card-label">เคสวันนี้</div>
            <div class="card-value"><?= $cases_today ?><small style="font-size:1rem;"> เคส</small></div>
            <div class="card-sub">สัปดาห์นี้ <?= $cases_week ?> | รวม <?= $cases_all ?></div>
            <div class="card-bar"></div>
        </div>
    </div>

    <!-- 2 คอลัมน์: กราฟ + ประวัติเวรรายวัน -->
    <div class="stats-grid-2col">

        <!-- Bar Chart เวลาย้อนหลัง 14 วัน -->
        <div class="stat-section">
            <div class="stat-section-title">
                <i class="fas fa-chart-bar"></i>
                <span>ชั่วโมงปฏิบัติหน้าที่</span> — 14 วันย้อนหลัง
            </div>
            <?php
            $chartData = json_encode($chart_hours);
            $chartLabels = json_encode($chart_labels);
            $maxHrs = max(array_merge($chart_hours, [1]));
            ?>
            <div class="bar-chart" id="bar-chart">
                <?php foreach ($chart_hours as $i => $hrs): 
                    $pct = $maxHrs > 0 ? ($hrs / $maxHrs) * 100 : 0;
                ?>
                <div class="bar-wrap">
                    <div class="bar" style="height:<?= max($pct, $hrs>0?3:0) ?>%" data-val="<?= $hrs ?>"></div>
                    <div class="bar-label"><?= $chart_labels[$i] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ประวัติเวรรายวัน -->
        <div class="stat-section">
            <div class="stat-section-title">
                <i class="fas fa-calendar-check"></i>
                <span>ประวัติเวร</span> — 14 วันล่าสุด
            </div>
            <?php if(empty($daily_logs)): ?>
                <div style="text-align:center;color:var(--text-muted);padding:30px;font-size:13px;">ยังไม่มีประวัติการเข้าเวร</div>
            <?php else: ?>
            <table class="duty-history-table">
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th>เวลารวม</th>
                        <th>เซสชัน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($daily_logs as $row):
                        $sec = $row['day_total'];
                        $dh = floor($sec / 3600);
                        $dm = floor(($sec % 3600) / 60);
                        $dateLabel = date('D d/m/Y', strtotime($row['duty_date']));
                    ?>
                    <tr>
                        <td style="color:var(--text);"><?= $dateLabel ?></td>
                        <td><span class="time-pill"><?= $dh ?>ชม. <?= $dm ?>น.</span></td>
                        <td style="color:var(--text-muted);"><?= $row['sessions'] ?> ครั้ง</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ประวัติเคสของฉัน -->
    <div class="cases-full-table">
        <div class="stat-section-title" style="margin-bottom:16px;">
            <i class="fas fa-folder-open"></i>
            <span>ประวัติเคสของฉัน</span> — ล่าสุด 20 รายการ
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>เลขเคส</th>
                    <th>วัน/เวลา</th>
                    <th>ผู้ต้องหา</th>
                    <th>ประเภท</th>
                    <th>รายละเอียด</th>
                    <th>จำคุก</th>
                    <th>ค่าปรับ</th>
                    <th>Discord</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($my_cases)): ?>
                    <tr><td colspan="8" class="empty-row">📂 ยังไม่มีประวัติเคส</td></tr>
                <?php else: ?>
                <?php foreach($my_cases as $c):
                    $items_arr = json_decode($c['items'], true) ?? [];
                    $items_str = implode(', ', array_map(fn($i) => $i['name'].'x'.$i['qty'], $items_arr));
                ?>
                <tr>
                    <td style="font-family:'Rajdhani',monospace;color:var(--primary);font-size:13px;"><?= htmlspecialchars($c['case_number']) ?></td>
                    <td style="font-size:12px;white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
                    <td><b><?= htmlspecialchars($c['suspect_name']) ?></b></td>
                    <td>
                        <?php if($c['case_type'] === 'เคสดำ'): ?>
                            <span class="badge-type-black">⚫ เคสดำ</span>
                        <?php else: ?>
                            <span class="badge-type-red">🔴 เคสเเดง</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-muted);">
                        <?= htmlspecialchars($items_str ?: '-') ?>
                    </td>
                    <td style="color:var(--danger);font-weight:600;"><?= $c['jail_minutes'] ?> น.</td>
                    <td style="color:var(--primary);font-weight:600;"><?= number_format($c['fine_amount']) ?> ฿</td>
                    <td>
                        <?php if($c['discord_sent']): ?>
                            <span class="badge-discord-sent">✓ ส่งแล้ว</span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:11px;">รอส่ง</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
// ===== Live Duty Timer =====
const activeElapsedAtLoad = <?= $active_elapsed_ms ?>;
const activeLoadedAt = Date.now();
const isOnDuty = <?= $active ? 'true' : 'false' ?>;
function updateLiveTimer() {
    if (!isOnDuty) return;
    const totalMs = activeElapsedAtLoad + (Date.now() - activeLoadedAt);
    const h = Math.floor(totalMs / 3600000);
    const m = Math.floor((totalMs % 3600000) / 60000);
    const s = Math.floor((totalMs % 60000) / 1000);
    const el = document.getElementById('live-timer');
    if (el) el.textContent = 
        h.toString().padStart(2,'0') + ':' +
        m.toString().padStart(2,'0') + ':' +
        s.toString().padStart(2,'0');
}
setInterval(updateLiveTimer, 1000);
updateLiveTimer();
</script>
                <footer style="text-align:center; padding:28px 0 20px; color:#4a5568; font-size:12px; letter-spacing:0.5px; font-family:'Noto Sans Thai',sans-serif;">
    © Police All Star. by Four Fxpl .achikp_43035
</footer>
</body>
</html>
