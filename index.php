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
    <title>POLICE ALL STAR PD — DISPATCH</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">

    <!-- WELCOME & STATUS -->
    <div class="card" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:40px; padding:32px; background:linear-gradient(135deg, var(--surface), oklch(from var(--primary) 0.15 0.05 250));">
        <div>
            <h2 class="heading-tech" style="font-size:2rem; margin-bottom:4px;">OFFICER TERMINAL</h2>
            <p style="color:var(--text-muted); font-weight:600;">ACTIVE SESSION: <?= date('Y-m-d H:i') ?></p>
        </div>
        <div style="text-align:right;">
            <?php if($on_duty): ?>
                <div class="status-badge" style="background:var(--success); color:#fff; padding:10px 24px; border-radius:12px; font-weight:800; display:inline-flex; align-items:center; gap:10px;">
                    <span class="pulse-dot" style="width:10px; height:10px; background:#fff; border-radius:50%; display:inline-block;"></span>
                    ON-DUTY
                </div>
            <?php else: ?>
                <div class="status-badge" style="background:var(--danger); color:#fff; padding:10px 24px; border-radius:12px; font-weight:800; display:inline-flex; align-items:center; gap:10px;">
                    OFF-DUTY
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOP PANELS -->
    <div class="top-panels">
        <div class="card">
            <h3 class="heading-tech" style="font-size:14px; margin-bottom:20px; color:var(--primary);">📢 SYSTEM ANNOUNCEMENTS</h3>
            <div style="background:var(--primary-dim); border:1px solid var(--border-mid); padding:16px; border-radius:12px; margin-bottom:20px;">
                <p style="font-size:13px; font-weight:700; color:var(--primary);">SYSTEM: BTPD DISPATCH V2.0</p>
                <p style="font-size:12px; color:var(--text-muted); margin-top:4px;">Enhanced UI/UX protocol engaged. All units report status.</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <a href="https://discord.gg/mskEYgPXYW" target="_blank" class="btn btn-primary" style="background:#5865F2;">
                    <i class="fab fa-discord"></i> DISCORD COMMS
                </a>
                <a href="rules.php" class="btn btn-primary">
                    <i class="fas fa-book"></i> PROTOCOLS
                </a>
                <a href="stats.php" class="btn btn-accent">
                    <i class="fas fa-chart-line"></i> PERFORMANCE ANALYTICS
                </a>
            </div>
        </div>

        <div class="card">
            <h3 class="heading-tech" style="font-size:14px; margin-bottom:20px; color:var(--primary);">⚡ RAPID DEPLOYMENT</h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <a href="duty.php" class="shortcut-item">
                    <i class="fas fa-clock"></i> ON-DUTY
                </a>
                <a href="cases.php" class="shortcut-item">
                    <i class="fas fa-shield-alt"></i> INCIDENT
                </a>
                <a href="stats.php" class="shortcut-item">
                    <i class="fas fa-database"></i> RECORDS
                </a>
                <a href="rules.php" class="shortcut-item">
                    <i class="fas fa-info-circle"></i> CODES
                </a>
            </div>
        </div>
    </div>

    <!-- HALL OF FAME -->
    <div class="hof-section">
        <div class="hof-label">ELITE UNITS — TOP 5 MONTHLY</div>
        <div class="hof-cards">
        <?php
        $display_order = [4,2,0,1,3];
        $rank_labels   = [5,3,1,2,4];
        foreach($display_order as $di => $idx):
            if(!isset($hof[$idx])) continue;
            $p    = $hof[$idx];
            $rank = $rank_labels[$di];
            $h    = floor($p['duty_sec']/3600);
            $cls  = $rank === 1 ? 'rank-1' : 'rank-n';
        ?>
        <div class="hof-card <?= $cls ?>">
            <div style="position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:<?= $rank==1?'var(--accent)':'var(--surface-2)' ?>; color:#fff; padding:4px 12px; border-radius:20px; font-size:10px; font-weight:800; font-family:'Rajdhani';">RANK <?= $rank ?></div>
            <?php if(!empty($p['avatar'])): ?>
                <img src="<?= htmlspecialchars($p['avatar']) ?>" class="hof-avatar" alt="">
            <?php else: ?>
                <div class="hof-avatar" style="background:var(--surface-2); display:flex; align-items:center; justify-content:center; font-size:2rem;">👮</div>
            <?php endif; ?>
            <div class="heading-tech" style="font-size:14px; color:var(--text); margin-bottom:16px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($p['name']) ?></div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <div style="display:flex; justify-content:space-between; font-size:11px;">
                    <span style="color:var(--text-muted);">DUTY TIME</span>
                    <span style="color:var(--primary); font-weight:800;"><?= $h ?>H</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:11px;">
                    <span style="color:var(--text-muted);">INCIDENTS</span>
                    <span style="color:var(--accent); font-weight:800;"><?= $p['case_count'] ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- LEADERBOARDS -->
    <div class="top-panels" style="margin-top:40px;">

        <div class="card">
            <h3 class="heading-tech" style="font-size:14px; margin-bottom:24px; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-trophy" style="color:var(--accent);"></i> INCIDENT MASTERY (WEEKLY)
            </h3>
            <?php if(empty($top_cases)): ?>
                <div style="text-align:center; color:var(--text-muted); padding:20px;">NO DATA LOGGED</div>
            <?php else: foreach($top_cases as $i => $row):
                $r = $i+1;
            ?>
            <div style="display:flex; align-items:center; gap:16px; padding:12px 0; border-bottom:1px solid var(--border);">
                <div style="width:28px; height:28px; border-radius:8px; background:<?= $r<=3?'var(--accent)':'var(--surface-2)' ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-family:'Rajdhani'; font-weight:800;"><?= $r ?></div>
                <div style="flex:1; font-weight:600; font-size:13px;"><?= htmlspecialchars($row['name']) ?></div>
                <div style="background:var(--primary-dim); color:var(--primary); padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800;"><?= $row['case_count'] ?> CASES</div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card">
            <h3 class="heading-tech" style="font-size:14px; margin-bottom:24px; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-hourglass-half" style="color:var(--primary);"></i> DUTY ENDURANCE (WEEKLY)
            </h3>
            <?php if(empty($top_duty)): ?>
                <div style="text-align:center; color:var(--text-muted); padding:20px;">NO DATA LOGGED</div>
            <?php else: foreach($top_duty as $i => $row):
                $r = $i+1;
                $h = floor($row['total_sec']/3600);
            ?>
            <div style="display:flex; align-items:center; gap:16px; padding:12px 0; border-bottom:1px solid var(--border);">
                <div style="width:28px; height:28px; border-radius:8px; background:<?= $r<=3?'var(--primary)':'var(--surface-2)' ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-family:'Rajdhani'; font-weight:800;"><?= $r ?></div>
                <div style="flex:1; font-weight:600; font-size:13px;"><?= htmlspecialchars($row['name']) ?></div>
                <div style="background:var(--accent-dim); color:var(--accent); padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800;"><?= $h ?>H TOTAL</div>
            </div>
            <?php endforeach; endif; ?>
        </div>

    </div>

</div>

<footer style="text-align:center; padding:48px 0; color:var(--text-muted); font-size:11px; letter-spacing:2px; font-family:'Rajdhani',sans-serif; text-transform:uppercase;">
    © POLICE ALL STAR PD — DISPATCH SYSTEM 2.0
</footer>

</body>
</html>
