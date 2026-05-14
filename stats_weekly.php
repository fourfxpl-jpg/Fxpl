<?php
include 'check_login.php';
include 'db.php';
include 'navbar.php';

date_default_timezone_set('Asia/Bangkok');

// --- DATA FETCHING (Adapted from weekly_report.php) ---
$start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
$end_date   = date('Y-m-d H:i:s');

// 1. UNIT TOTALS
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM cases WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$total_cases = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT SUM(duration) as total_sec FROM duty_logs WHERE start_time BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$total_duty_sec = $stmt->fetch()['total_sec'] ?? 0;
$total_duty_h = floor($total_duty_sec / 3600);

// 2. OFFICER PERFORMANCE
$stmt = $conn->prepare("
    SELECT officer_id, officer_name, COUNT(*) as total 
    FROM cases 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY officer_id, officer_name
    ORDER BY total DESC
");
$stmt->execute([$start_date, $end_date]);
$main_cases_rows = $stmt->fetchAll();

$stmt2 = $conn->prepare("
    SELECT assisting_officers 
    FROM cases 
    WHERE created_at BETWEEN ? AND ?
    AND assisting_officers != '[]'
    AND assisting_officers IS NOT NULL
");
$stmt2->execute([$start_date, $end_date]);
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

$officer_stats = [];
foreach ($main_cases_rows as $row) {
    $name = $row['officer_name'];
    $officer_stats[$name] = [
        'id' => $row['officer_id'],
        'main' => $row['total'],
        'assist' => 0,
        'total' => $row['total']
    ];
}
foreach ($assist_count as $name => $count) {
    if (!isset($officer_stats[$name])) {
        $officer_stats[$name] = ['id' => 0, 'main' => 0, 'assist' => $count, 'total' => $count];
    } else {
        $officer_stats[$name]['assist'] = $count;
        $officer_stats[$name]['total'] += $count;
    }
}
uasort($officer_stats, fn($a, $b) => $b['total'] <=> $a['total']);

// 3. INCIDENT DISTRIBUTION (Mock/Basic for now based on what we have)
$incident_types = $conn->prepare("
    SELECT category, COUNT(*) as count 
    FROM cases 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY category
    ORDER BY count DESC
");
$incident_types->execute([$start_date, $end_date]);
$distribution = $incident_types->fetchAll();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>WEEKLY ANALYTICS — OFFICER TERMINAL</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Feature-specific overrides for "Committed" strategy */
        :root {
            --chart-fill: var(--primary-dim);
            --chart-stroke: var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .metric-card {
            background: linear-gradient(135deg, var(--surface), oklch(from var(--primary) 0.15 0.05 250 / 0.2));
            border: 1px solid var(--border-mid);
            padding: 24px;
            border-radius: var(--radius);
            position: relative;
            overflow: hidden;
        }

        .metric-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, var(--primary-dim) 0%, transparent 70%);
            opacity: 0.1;
            pointer-events: none;
        }

        .metric-value {
            font-family: 'Rajdhani', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            margin: 8px 0;
        }

        .metric-label {
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .leaderboard-row {
            background: var(--surface);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }

        .leaderboard-row:hover {
            transform: scale(1.01);
            box-shadow: var(--shadow-md);
            background: var(--surface-2);
        }

        .leaderboard-cell {
            padding: 16px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .leaderboard-cell:first-child {
            border-left: 1px solid var(--border);
            border-radius: 12px 0 0 12px;
            width: 60px;
            text-align: center;
        }

        .leaderboard-cell:last-child {
            border-right: 1px solid var(--border);
            border-radius: 0 12px 12px 0;
            text-align: right;
        }

        .rank-medal {
            font-size: 1.2rem;
            font-weight: 800;
        }

        .rank-1 { color: #ffd700; text-shadow: 0 0 10px rgba(255,215,0,0.3); }
        .rank-2 { color: #c0c0c0; }
        .rank-3 { color: #cd7f32; }

        .progress-bar-bg {
            background: var(--base);
            height: 6px;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
        }

        .progress-bar-fill {
            background: var(--primary);
            height: 100%;
            box-shadow: 0 0 10px var(--primary-dim);
        }

        .split-view {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 32px;
        }

        @media (max-width: 1024px) {
            .split-view { grid-template-columns: 1fr; }
        }

        .category-tag {
            background: var(--surface-2);
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            border: 1px solid var(--border);
        }
    </style>
</head>
<body>
<div class="container">
    <header style="margin-bottom: 40px;">
        <h1 class="heading-tech" style="font-size: 2.5rem;">WEEKLY PERFORMANCE ANALYTICS</h1>
        <p style="color:var(--text-muted); font-weight:600;">REPORTING PERIOD: <?= date('d M', strtotime($start_date)) ?> — <?= date('d M Y') ?></p>
    </header>

    <!-- UNIT TOTALS -->
    <div class="stats-grid">
        <div class="metric-card">
            <span class="metric-label">Total Incidents</span>
            <div class="metric-value"><?= number_format($total_cases) ?></div>
            <div style="font-size: 11px; color: var(--success); font-weight: 700;">
                <i class="fas fa-arrow-up"></i> SYSTEM ACTIVE
            </div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Unit Duty Hours</span>
            <div class="metric-value"><?= number_format($total_duty_h) ?>H</div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">
                ACROSS ALL ACTIVE PERSONNEL
            </div>
        </div>
        <div class="metric-card">
            <span class="metric-label">Personnel Impact</span>
            <div class="metric-value"><?= count($officer_stats) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">
                OFFICERS LOGGED ACTIVITY
            </div>
        </div>
    </div>

    <div class="split-view">
        <!-- LEADERBOARD -->
        <div class="card" style="padding: 0; background: transparent; border: none; box-shadow: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h2 class="heading-tech">OFFICER LEADERBOARD</h2>
                <span class="category-tag">TOP PERFORMERS</span>
            </div>
            <table class="leaderboard-table">
                <tbody>
                    <?php 
                    $rank = 1;
                    $max_total = !empty($officer_stats) ? reset($officer_stats)['total'] : 1;
                    foreach ($officer_stats as $name => $data): 
                        $pct = ($data['total'] / $max_total) * 100;
                    ?>
                        <tr class="leaderboard-row">
                            <td class="leaderboard-cell">
                                <span class="rank-medal rank-<?= $rank ?>">
                                    <?= ($rank <= 3) ? ($rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉')) : "#$rank" ?>
                                </span>
                            </td>
                            <td class="leaderboard-cell">
                                <div style="font-weight: 800; font-size: 1.1rem;"><?= htmlspecialchars($name) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Officer ID: <?= $data['id'] ?></div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: <?= $pct ?>%;"></div>
                                </div>
                            </td>
                            <td class="leaderboard-cell" style="width: 150px;">
                                <div style="font-family: 'Rajdhani', sans-serif; font-weight: 800; font-size: 1.5rem; color: var(--primary);">
                                    <?= $data['total'] ?>
                                </div>
                                <div style="font-size: 10px; color: var(--text-muted); font-weight: 700;">
                                    <?= $data['main'] ?> MAIN | <?= $data['assist'] ?> ASSIST
                                </div>
                            </td>
                        </tr>
                    <?php 
                        $rank++;
                        if ($rank > 10) break;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>

        <!-- DISTRIBUTION -->
        <div>
            <div class="card" style="margin-bottom: 24px;">
                <h3 class="heading-tech" style="margin-bottom: 20px;">INCIDENT DISTRIBUTION</h3>
                <?php foreach ($distribution as $item): 
                    $dist_pct = ($total_cases > 0) ? ($item['count'] / $total_cases) * 100 : 0;
                ?>
                    <div style="margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; margin-bottom: 4px;">
                            <span><?= strtoupper(htmlspecialchars($item['category'])) ?></span>
                            <span style="color: var(--primary);"><?= $item['count'] ?></span>
                        </div>
                        <div class="progress-bar-bg" style="height: 4px;">
                            <div class="progress-bar-fill" style="width: <?= $dist_pct ?>%; opacity: 0.7;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($distribution)): ?>
                    <p style="color:var(--text-muted); font-style: italic;">No incidents logged this period.</p>
                <?php endif; ?>
            </div>

            <div class="card" style="background: var(--primary-dim); border-color: var(--primary);">
                <h3 class="heading-tech" style="color: var(--primary); margin-bottom: 12px;">TACTICAL SUMMARY</h3>
                <p style="font-size: 13px; line-height: 1.5;">
                    Unit efficiency is currently at <span style="color: var(--primary); font-weight: 800;">STABLE</span> levels. 
                    Top contributor <span style="font-weight: 800;"><?= !empty($officer_stats) ? array_key_first($officer_stats) : 'N/A' ?></span> 
                    has secured the primary rank for this cycle.
                </p>
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--primary-dim);">
                    <button class="btn" style="width: 100%;" onclick="window.print()">GENERATE HARDCOPY</button>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
