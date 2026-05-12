<?php
include 'check_login.php';
include 'db.php';
include 'navbar.php';

date_default_timezone_set('Asia/Bangkok');

$user_id = $_SESSION['user_id'];

// สถานะ On Duty ปัจจุบัน
$stmt = $conn->prepare("SELECT * FROM duty_logs WHERE user_id = ? AND status = 1 LIMIT 1");
$stmt->execute([$user_id]);
$on_duty = $stmt->fetch();

// ============================================================
// HALL OF FAME — Top 5 รายเดือน
// ============================================================
$hof = $conn->query("
    SELECT
        u.user_id,
        u.user_name AS name,
        u.avatar,
        COALESCE(d.duty_sec, 0) AS duty_sec,
        COALESCE(c.case_count, 0) AS case_count
    FROM users u
    LEFT JOIN (
        SELECT user_id, SUM(duration) AS duty_sec
        FROM duty_logs
        WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY user_id
    ) d ON u.user_id = d.user_id
    LEFT JOIN (
        SELECT officer_id, COUNT(*) AS case_count
        FROM cases
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY officer_id
    ) c ON u.user_id = c.officer_id
    ORDER BY (COALESCE(d.duty_sec,0) + COALESCE(c.case_count,0)*600) DESC
    LIMIT 5
")->fetchAll();

// ============================================================
// TOP 10 — เคสรายสัปดาห์
// ============================================================
$top_cases = $conn->query("
    SELECT officer_name AS name, COUNT(*) AS case_count
    FROM cases
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY officer_id, officer_name
    ORDER BY case_count DESC
    LIMIT 10
")->fetchAll();

// ============================================================
// TOP 10 — ชั่วโมงเวรรายสัปดาห์
// ============================================================
$top_duty = $conn->query("
    SELECT u.user_name AS name, SUM(d.duration) AS total_sec
    FROM duty_logs d
    JOIN users u ON u.user_id = d.user_id
    WHERE d.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY d.user_id
    ORDER BY total_sec DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Police All Star</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ── TOP PANELS ── */
        .top-panels {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 36px;
        }
        @media(max-width:768px){ .top-panels{ grid-template-columns:1fr; } }

        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 22px 24px;
        }
        .panel-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .announce-version {
            background: rgba(16,185,129,0.08);
            border: 1px solid rgba(16,185,129,0.25);
            border-radius: 8px;
            padding: 7px 14px;
            font-size: 12px;
            color: #10b981;
            margin-bottom: 12px;
        }
        .announce-btn {
            display: block;
            width: 100%;
            padding: 10px 16px;
            border-radius: 10px;
            border: none;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Noto Sans Thai', sans-serif;
            cursor: pointer;
            margin-bottom: 8px;
            text-align: center;
            text-decoration: none;
            transition: opacity 0.2s, transform 0.15s;
        }
        .announce-btn:last-child { margin-bottom: 0; }
        .announce-btn:hover { opacity: 0.88; transform: translateY(-1px); }
        .btn-discord { background: #5865f2; color: #fff; }
        .btn-orange  { background: linear-gradient(135deg,#f89b29,#e07b0a); color: #fff; }
        .btn-orange2 { background: linear-gradient(135deg,#fb923c,#ea6b00); color: #fff; }

        .shortcut-list { display: flex; flex-direction: column; gap: 8px; }
        .shortcut-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            color: var(--text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }
        .shortcut-item:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-dim);
        }
        .shortcut-item i { width: 18px; text-align:center; color: var(--primary); }

        /* ── HALL OF FAME ── */
        .hof-section { text-align: center; margin-bottom: 36px; }
        .hof-label {
            font-size: 11px;
            letter-spacing: 4px;
            color: var(--primary);
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 2px;
        }
        .hof-label::before { content:''; flex:1; max-width:80px; height:1px; background:linear-gradient(90deg,transparent,var(--primary)); }
        .hof-label::after  { content:''; flex:1; max-width:80px; height:1px; background:linear-gradient(90deg,var(--primary),transparent); }
        .hof-sub { font-size:11px; color:var(--text-muted); letter-spacing:2px; margin-bottom:24px; }

        .hof-cards {
            display: flex;
            gap: 14px;
            justify-content: center;
            align-items: flex-end;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        .hof-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 22px 16px 18px;
            text-align: center;
            min-width: 138px;
            max-width: 155px;
            flex-shrink: 0;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .hof-card:hover { transform: translateY(-5px); box-shadow: 0 12px 36px rgba(0,0,0,0.4); }
        .hof-card.rank-1 {
            min-width: 160px; max-width: 178px;
            background: linear-gradient(160deg,#111e38,#0d1529);
            border-color: var(--primary);
            box-shadow: 0 0 28px rgba(248,155,41,0.15);
        }
        .hof-rank-badge {
            position: absolute;
            top: -10px; left: 50%;
            transform: translateX(-50%);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 10px; font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 1px;
            white-space: nowrap;
        }
        .hof-card.rank-1 .hof-rank-badge { background: var(--primary); color: #fff; border-color: var(--primary); }
        .hof-card.rank-2 .hof-rank-badge { color: #c0c0c0; border-color: #c0c0c0; }
        .hof-card.rank-3 .hof-rank-badge { color: #cd7f32; border-color: #cd7f32; }
        .hof-avatar {
            width: 70px; height: 70px; border-radius: 50%;
            border: 3px solid var(--border-mid);
            object-fit: cover; display: block;
            margin: 10px auto 10px;
        }
        .hof-card.rank-1 .hof-avatar { width: 80px; height: 80px; border-color: var(--primary); }
        .hof-avatar-placeholder {
            width: 70px; height: 70px; border-radius: 50%;
            background: #1a2a4a; border: 3px solid var(--border-mid);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.7rem; margin: 10px auto 10px;
        }
        .hof-card.rank-1 .hof-avatar-placeholder { width: 80px; height: 80px; }
        .hof-name {
            font-size: 13px; font-weight: 700; color: var(--text);
            margin-bottom: 10px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .hof-stats { display: flex; flex-direction: column; gap: 5px; }
        .hof-stat-row { display: flex; justify-content: space-between; align-items: center; font-size: 11px; }
        .hof-stat-label { color: var(--text-muted); }
        .hof-stat-val {
            background: rgba(248,155,41,0.12);
            border: 1px solid rgba(248,155,41,0.25);
            color: var(--primary);
            border-radius: 20px; padding: 2px 8px;
            font-weight: 700; font-size: 11px;
        }

        /* ── LEADERBOARDS ── */
        .leaderboards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }
        @media(max-width:768px){ .leaderboards{ grid-template-columns:1fr; } }
        .lb-panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px 22px;
        }
        .lb-title {
            font-size: 13px; font-weight: 700; color: var(--text);
            display: flex; align-items: center; gap: 8px;
            margin: 0 0 16px;
        }
        .lb-row {
            display: flex; align-items: center;
            gap: 12px; padding: 9px 0;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .lb-row:last-child { border-bottom: none; }
        .lb-rank {
            width: 28px; height: 28px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 800; flex-shrink: 0;
            font-family: 'Rajdhani', sans-serif;
        }
        .lb-rank.r1 { background: rgba(248,155,41,0.9); color: #fff; }
        .lb-rank.r2 { background: rgba(192,192,192,0.12); color: #c0c0c0; border: 1px solid rgba(192,192,192,0.25); }
        .lb-rank.r3 { background: rgba(205,127,50,0.12); color: #cd7f32; border: 1px solid rgba(205,127,50,0.25); }
        .lb-rank.rn { background: rgba(255,255,255,0.04); color: var(--text-muted); border: 1px solid var(--border); }
        .lb-name {
            flex: 1; font-size: 13px; font-weight: 600; color: var(--text);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .lb-val {
            font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
            white-space: nowrap; flex-shrink: 0;
        }
        .lb-val-case {
            background: rgba(96,165,250,0.1);
            border: 1px solid rgba(96,165,250,0.25);
            color: var(--blue);
        }
        .lb-val-duty {
            background: rgba(248,155,41,0.1);
            border: 1px solid rgba(248,155,41,0.25);
            color: var(--primary);
        }
        .empty-lb { color: var(--text-muted); font-size: 13px; text-align: center; padding: 20px 0; }
    </style>
</head>
<body>
<div class="container">

    <!-- TOP PANELS -->
    <div class="top-panels">
        <div class="panel">
            <div class="panel-title">📢 ประกาศสำคัญ</div>
            <div class="announce-version">System: BTPD System</div>
            <a href="https://discord.gg/mskEYgPXYW" target="_blank" class="announce-btn btn-discord">
                <i class="fab fa-discord"></i> Discord Police All Star
            </a>
            <a href="rules.php" class="announce-btn btn-orange">
                <i class="fas fa-book"></i> คู่มือและกฎระเบียบ
            </a>
            <a href="stats.php" class="announce-btn btn-orange2">
                <i class="fas fa-graduation-cap"></i> ดูสถิติการทำงาน
            </a>
        </div>

        <div class="panel">
            <div class="panel-title">⚡ ทางลัดปฏิบัติงาน</div>
            <div class="shortcut-list">
                <a href="duty.php" class="shortcut-item">
                    <i class="fas fa-clock"></i> ลงชื่อเข้าหน่วยงาน
                </a>
                <a href="cases.php" class="shortcut-item">
                    <i class="fas fa-folder-open"></i> บันทึกเคส
                </a>
                <a href="stats.php" class="shortcut-item">
                    <i class="fas fa-chart-bar"></i> ตรวจสอบสถิติการทำงาน
                </a>
                <a href="rules.php" class="shortcut-item">
                    <i class="fas fa-book-open"></i> ศึกษากฎหมายและข้อบังคับ
                </a>
            </div>
        </div>
    </div>

    <!-- HALL OF FAME -->
    <div class="hof-section">
        <div class="hof-label">Y &nbsp; All POLICE &nbsp; Y</div>
        <div class="hof-sub"> Top Cases </div>

        <div class="hof-cards">
        <?php
        $display_order = [4,2,0,1,3];
        $rank_labels   = [5,3,1,2,4];
        foreach($display_order as $di => $idx):
            if(!isset($hof[$idx])) continue;
            $p    = $hof[$idx];
            $rank = $rank_labels[$di];
            $h    = floor($p['duty_sec']/3600);
            $m    = floor(($p['duty_sec']%3600)/60);
            $cls  = $rank === 1 ? 'rank-1' : ($rank <= 3 ? 'rank-'.$rank : 'rank-n');
        ?>
        <div class="hof-card <?= $cls ?>">
            <div class="hof-rank-badge">RANK <?= $rank ?></div>
            <?php if(!empty($p['avatar'])): ?>
                <img src="<?= htmlspecialchars($p['avatar']) ?>" class="hof-avatar" alt="">
            <?php else: ?>
                <div class="hof-avatar-placeholder">👮</div>
            <?php endif; ?>
            <div class="hof-name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="hof-stats">
                <div class="hof-stat-row">
                    <span class="hof-stat-label">ชั่วโมงเวร</span>
                    <span class="hof-stat-val"><?= $h ?> ชม.</span>
                </div>
                <div class="hof-stat-row">
                    <span class="hof-stat-label">เคสปิด</span>
                    <span class="hof-stat-val"><?= $p['case_count'] ?> เคส</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- LEADERBOARDS -->
    <div class="leaderboards">

        <div class="lb-panel">
            <div class="lb-title">🏆 10 อันดับการไล่เคสรายสัปดาห์</div>
            <?php if(empty($top_cases)): ?>
                <div class="empty-lb">ยังไม่มีข้อมูล</div>
            <?php else: foreach($top_cases as $i => $row):
                $r = $i+1; $cls = $r===1?'r1':($r===2?'r2':($r===3?'r3':'rn'));
            ?>
            <div class="lb-row">
                <div class="lb-rank <?= $cls ?>"><?= $r ?></div>
                <div class="lb-name"><?= htmlspecialchars($row['name']) ?></div>
                <div class="lb-val lb-val-case"><?= $row['case_count'] ?> เคส</div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="lb-panel">
            <div class="lb-title">⏱️ 10 อันดับชั่วโมงเวรรายสัปดาห์</div>
            <?php if(empty($top_duty)): ?>
                <div class="empty-lb">ยังไม่มีข้อมูล</div>
            <?php else: foreach($top_duty as $i => $row):
                $r = $i+1; $cls = $r===1?'r1':($r===2?'r2':($r===3?'r3':'rn'));
                $h = floor($row['total_sec']/3600);
                $m = floor(($row['total_sec']%3600)/60);
            ?>
            <div class="lb-row">
                <div class="lb-rank <?= $cls ?>"><?= $r ?></div>
                <div class="lb-name"><?= htmlspecialchars($row['name']) ?></div>
                <div class="lb-val lb-val-duty"><?= $h ?> ชม. <?= $m ?> น.</div>
            </div>
            <?php endforeach; endif; ?>
        </div>

    </div>

</div>

<footer style="text-align:center; padding:28px 0 20px; color:#4a5568; font-size:12px; letter-spacing:0.5px; font-family:'Noto Sans Thai',sans-serif;">
    © Police All Star. by Four Fxpl .achikp_43035
</footer>

</body>
</html>
