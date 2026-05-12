<?php 
include 'check_login.php';
include 'db.php'; 
include 'navbar.php';
date_default_timezone_set('Asia/Bangkok');

$user_id   = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_name FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$user_name = $user['user_name'] ?? 'Unknown';

// ดึงเจ้าหน้าที่ที่ on duty อยู่ (ยกเว้นตัวเอง)
$stmt = $conn->query("SELECT DISTINCT user_id, user_name FROM duty_logs WHERE status = 1");
$on_duty_officers = $stmt->fetchAll();

// preset items แยกตาม zone
$items_general = [
    // ===== ของกลาง =====
    ['name'=>'Shadow Token (เหรียญแดง)', 'price'=>0, 'jail'=>10,],
    ['name'=>'เงินแดง', 'price'=>0, 'jail'=>10,],
    ['name'=>'ปูน', 'price'=>5000, 'jail'=>5],
    ['name'=>'สายไฟ', 'price'=>5000, 'jail'=>5],
    ['name'=>'Cap A B', 'price'=>2000, 'jail'=>10],
    ['name'=>'Cap C', 'price'=>5000, 'jail'=>5],
    ['name'=>'ยา B3', 'price'=>5000, 'jail'=>10],
    ['name'=>'ยา B4', 'price'=>8000, 'jail'=>10],
    ['name'=>'ยา B5', 'price'=>10000, 'jail'=>10],
    ['name'=>'ถุง Methamphetamine', 'price'=>5000, 'jail'=>1],
    ['name'=>'ถุง Cocaine', 'price'=>5000, 'jail'=>1],
    ['name'=>'ถุง Ketamine', 'price'=>5000, 'jail'=>1],
    ['name'=>'ทำลายหลักฐาน / ทิ้งของกลาง', 'price'=>30000, 'jail'=>15],

    // ===== หลบหนี =====
    ['name'=>'หลบหนีการจับกุม', 'price'=>10000, 'jail'=>10],
    ['name'=>'หลบหนีนอกเมือง', 'price'=>20000, 'jail'=>10],
    ['name'=>'หลบหนีหลังการจับกุม', 'price'=>30000, 'jail'=>30],
    ['name'=>'หลบหนีลงน้ำ / ขึ้นเขา', 'price'=>0, 'jail'=>10, 'x2'=>true],
    ['name'=>'สมรู้ร่วมคิด', 'price'=>0, 'jail'=>0],
    ['name'=>'พื้นที่สุ่มเสี่ยง', 'price'=>5000, 'jail'=>5],

    // ===== ทำร้ายร่างกาย =====
    ['name'=>'ทะเลาะวิวาท / สร้างความวุ่นวาย', 'price'=>50000, 'jail'=>40],
    ['name'=>'ทำร้ายร่างกายจนบาดเจ็บ', 'price'=>100000, 'jail'=>50],
    ['name'=>'ทำร้ายร่างกายจนสลบ', 'price'=>300000, 'jail'=>60],

    // ===== กฎหมายหน่วยงาน =====
    ['name'=>'ก่อกวน', 'price'=>5000, 'jail'=>15],
    ['name'=>'แต่งกายเลียนแบบเจ้าหน้าที่', 'price'=>50000, 'jail'=>60],
    ['name'=>'แจ้งความเท็จ / ให้การเท็จ', 'price'=>5000, 'jail'=>0],
    ['name'=>'ไม่ให้ความร่วมมือกับเจ้าหน้าที่', 'price'=>10000, 'jail'=>10],
    ['name'=>'ทำลายทรัพย์สินหน่วยงาน', 'price'=>50000, 'jail'=>30],
    ['name'=>'บุกรุกสถานที่ราชการ', 'price'=>50000, 'jail'=>15],
    ['name'=>'หมิ่นประมาทเจ้าหน้าที่', 'price'=>100000, 'jail'=>30],
    ['name'=>'อันเจล', 'price'=>0, 'jail'=>0],
];

$items_cayo = [
    ['name'=>'ทอง (Cayo)',      'price'=>50000, 'jail'=>30],
    ['name'=>'ภาพวาด (Cayo)',   'price'=>80000, 'jail'=>30],
    ['name'=>'เพชร (Cayo)',     'price'=>100000,'jail'=>30],
    ['name'=>'ยาเสพติด (Cayo)','price'=>40000, 'jail'=>20],
    ['name'=>'โบราณ (Cayo)',    'price'=>60000, 'jail'=>25],
];

// ดึงเคสล่าสุด 20 รายการ
$case_stmt = $conn->prepare("SELECT *, CONVERT_TZ(created_at, '+00:00', '+07:00') as created_bkk FROM cases WHERE officer_id = ? ORDER BY id DESC LIMIT 20");
$case_stmt->execute([$user_id]);
$my_cases = $case_stmt->fetchAll();

// เคสทั้งหมดสำหรับ table ล่าสุด
$all_stmt = $conn->query("SELECT *, CONVERT_TZ(created_at, '+00:00', '+07:00') as created_bkk FROM cases ORDER BY id DESC LIMIT 30");
$all_cases = $all_stmt->fetchAll();

// ดึงเวลา on duty ปัจจุบัน
$duty_stmt = $conn->prepare("SELECT CONVERT_TZ(start_time, '+00:00', '+07:00') as start_time FROM duty_logs WHERE user_id = ? AND status = 1 LIMIT 1");
$duty_stmt->execute([$user_id]);
$duty_row = $duty_stmt->fetch();
$duty_elapsed_ms = 0;
if ($duty_row) {
    $elapsed_stmt = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) as elapsed");
    $elapsed_stmt->execute([$duty_row['start_time']]);
    $elapsed_sec = $elapsed_stmt->fetch()['elapsed'] ?? 0;
    $duty_elapsed_ms = max(0, (int)$elapsed_sec * 1000);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Police All Star PD - บันทึกเคส</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== CASES PAGE STYLES ===== */
        .cases-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 20px;
            align-items: start;
        }
        .cases-main { display: flex; flex-direction: column; gap: 16px; }

        /* Header bar */
        .cases-header {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .cases-header h2 {
            margin: 0;
            font-size: 1.2rem;
            display: flex; align-items: center; gap: 10px;
        }
        .duty-timer-small {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            font-variant-numeric: tabular-nums;
        }
        .duty-start-label {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Zone tabs */
        .zone-tabs {
            display: flex;
            gap: 8px;
        }
        .zone-tab {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            transition: 0.2s;
        }
        .zone-tab.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        /* Search box */
        .search-box {
            position: relative;
        }
        .search-box input {
            width: 100%;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 16px 12px 42px;
            color: var(--text);
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }
        .search-box input:focus { border-color: var(--primary); }
        .search-box i {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .search-time-hint {
            font-size: 12px;
            color: var(--primary);
            margin-top: 6px;
        }

        /* Items grid */
        .items-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 460px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .items-grid::-webkit-scrollbar { width: 4px; }
        .items-grid::-webkit-scrollbar-track { background: transparent; }
        .items-grid::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

        .item-row {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: 0.15s;
        }
        .item-row:hover { border-color: var(--primary); background: rgba(0,102,255,0.06); }
        .item-row.selected { border-color: var(--primary); background: rgba(0,102,255,0.12); }
        .item-row-info b { font-size: 14px; display: block; line-height: 1.3; }
        .item-row-info small { font-size: 12px; color: var(--text-muted); }
        .item-row-actions { display: flex; align-items: center; gap: 8px; }
        .qty-btn {
            width: 28px; height: 28px;
            background: rgba(0,102,255,0.15);
            border: 1px solid var(--primary);
            border-radius: 6px;
            color: var(--primary);
            font-size: 14px; font-weight: bold;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: 0.15s;
        }
        .qty-btn:hover { background: var(--primary); color: #fff; }
        .qty-display {
            min-width: 28px;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
        }
        .fav-btn {
            width: 28px; height: 28px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-muted);
            font-size: 13px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: 0.15s;
        }
        .fav-btn.active { color: #f59e0b; border-color: #f59e0b; background: rgba(245,158,11,0.1); }

        /* Filter chips */
        .filter-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .filter-chip {
            padding: 5px 12px;
            background: rgba(0,102,255,0.08);
            border: 1px solid var(--primary);
            border-radius: 20px;
            font-size: 12px;
            color: var(--primary);
            cursor: pointer;
            transition: 0.15s;
        }
        .filter-chip:hover, .filter-chip.active-chip {
            background: var(--primary);
            color: #fff;
        }

        /* ===== RIGHT PANEL (sidebar) ===== */
        .cases-sidebar {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            position: sticky;
            top: 90px;
        }
        .sidebar-section label {
            font-size: 12px;
            color: var(--primary);
            font-weight: 700;
            display: block;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sidebar-input {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--text);
            font-size: 14px;
            outline: none;
            transition: 0.2s;
            box-sizing: border-box;
        }
        .sidebar-input:focus { border-color: var(--primary); }

        /* Case type buttons */
        .case-type-btns { display: flex; gap: 8px; }
        .case-type-btn {
            flex: 1;
            padding: 9px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .case-type-btn.active-black { background: #1a1a2e; border-color: #333; color: #fff; }
        .case-type-btn.active-other { background: rgba(239,68,68,0.15); border-color: var(--danger); color: var(--danger); }

        /* Summary box */
        .summary-box {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 5px;
            align-items: center;
        }
        .summary-row:last-child { margin-bottom: 0; }
        .summary-row .val { font-weight: 700; color: var(--primary); font-size: 15px; }
        .summary-row .val.red { color: var(--danger); }
        .summary-editable {
            width: 110px;
            text-align: right;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-weight: 700;
            font-size: 15px;
            padding: 3px 8px;
            outline: none;
            transition: 0.2s;
        }
        .summary-editable:focus { border-color: var(--primary); background: rgba(0,102,255,0.08); }

        /* Discord report preview */
        .discord-preview {
            background: #36393f;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 12px;
            line-height: 1.6;
            color: #dcddde;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-word;
            min-height: 80px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .copy-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Officers on duty */
        .officer-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-height: 150px;
            overflow-y: auto;
        }
        .officer-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 8px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: 0.15s;
        }
        .officer-item.selected { border-color: var(--success); background: rgba(16,185,129,0.08); }
        .officer-item input[type=checkbox] { accent-color: var(--success); }
        .officer-item label { cursor: pointer; font-size: 13px; }

        /* Submit btn */
        .btn-submit-case {
            width: 100%;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit-case:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-submit-case:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* History table bottom */
        .section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px;
        }
        .badge-case-black {
            display: inline-block; padding: 2px 10px; border-radius: 20px;
            background: rgba(0,0,0,0.4); border: 1px solid #555; font-size: 11px; color: #aaa;
        }
        .badge-case-other {
            display: inline-block; padding: 2px 10px; border-radius: 20px;
            background: rgba(239,68,68,0.1); border: 1px solid var(--danger); font-size: 11px; color: var(--danger);
        }
        .badge-sent {
            display: inline-block; padding: 2px 8px; border-radius: 20px;
            background: rgba(16,185,129,0.1); border: 1px solid var(--success); font-size: 11px; color: var(--success);
        }

        /* Toast */
        #toast {
            position: fixed; bottom: 30px; right: 30px;
            background: var(--success);
            color: #fff; padding: 12px 24px;
            border-radius: 10px; font-weight: 600;
            opacity: 0; transition: 0.3s;
            z-index: 9999;
        }
        #toast.show { opacity: 1; }
        #toast.error { background: var(--danger); }

        /* ===== QTY MODAL ===== */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: #1e2235;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px 40px;
            min-width: 280px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .modal-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 24px; color: var(--text); }
        .modal-counter {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 28px;
        }
        .modal-counter-val {
            font-size: 2rem;
            font-weight: 800;
            min-width: 60px;
            color: var(--text);
        }
        .modal-btn-pm {
            width: 48px; height: 48px;
            border-radius: 10px;
            border: none;
            font-size: 1.5rem; font-weight: 700;
            cursor: pointer;
            transition: 0.15s;
        }
        .modal-btn-pm.minus { background: var(--danger); color: #fff; }
        .modal-btn-pm.plus  { background: var(--success); color: #fff; }
        .modal-btn-pm:hover { opacity: 0.85; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .modal-confirm { background: var(--primary); color: #fff; border: none; border-radius: 8px; padding: 10px 28px; font-weight: 700; cursor: pointer; font-size: 15px; }
        .modal-cancel  { background: transparent; color: var(--text-muted); border: 1px solid var(--border); border-radius: 8px; padding: 10px 28px; font-weight: 600; cursor: pointer; font-size: 15px; }

        /* ===== IMAGE UPLOAD AREA ===== */
        .image-upload-area {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 14px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
            position: relative;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 6px;
            color: var(--text-muted);
            font-size: 13px;
        }
        .image-upload-area:hover, .image-upload-area.dragover {
            border-color: var(--primary);
            background: rgba(0,102,255,0.05);
        }
        .image-upload-area input[type=file] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .img-preview-grid {
            display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px;
        }
        .img-preview-item {
            position: relative;
            width: 60px; height: 60px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .img-preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .img-preview-item .remove-img {
            position: absolute; top: 2px; right: 2px;
            background: var(--danger); color: #fff;
            border: none; border-radius: 50%;
            width: 16px; height: 16px;
            font-size: 10px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            line-height: 1;
        }

        @media(max-width: 900px) {
            .cases-layout { grid-template-columns: 1fr; }
            .cases-sidebar { position: static; }
        }

        /* ซ่อน spinner ของ input number */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button,
        .summary-editable::-webkit-inner-spin-button,
        .summary-editable::-webkit-outer-spin-button,
        .modal-counter-val::-webkit-inner-spin-button,
        .modal-counter-val::-webkit-outer-spin-button {
            -webkit-appearance: none !important;
            appearance: none !important;
            margin: 0 !important;
            display: none !important;
        }
        input[type=number],
        .summary-editable,
        .modal-counter-val {
            -moz-appearance: textfield !important;
            appearance: textfield !important;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="cases-layout">

        <!-- ===== LEFT PANEL ===== -->
        <div class="cases-main">

            <!-- Header -->
            <div class="cases-header">
                <h2><i class="fas fa-calculator"></i> เครื่องคิดเลขคดี & บันทึกรายงาน</h2>
                <div style="text-align:right;">
                    <div class="duty-timer-small" id="duty-timer">00:00:00</div>
                    <div class="duty-start-label">
                        <?php if($duty_row): ?>
                            เริ่มเข้าเวรตั้งแต่ <?= date('H:i', strtotime($duty_row['start_time'])) ?> น.
                        <?php else: ?>
                            <span style="color:var(--danger);">● ยังไม่ได้เข้าเวร</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Zone Tabs -->
            <div class="zone-tabs">
                <button class="zone-tab active" onclick="switchZone('general', this)">📋 คดีดำ-คดีเเดง</button>
                <!-- <button class="zone-tab" onclick="switchZone('cayo', this)">🏝️ คดีเเดง Cayo</button> -->
            </div>

            <!-- Search -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="item-search" placeholder="ค้นหาชื่อ หรือ พิมตรงของกฏหมาย..." oninput="filterItems()">
            </div>
            <div class="search-time-hint" id="search-time-hint" style="display:none;"></div>

            <!-- Filter Chips -->
            <div class="filter-chips" id="filter-chips-general">
                <span class="filter-chip active-chip" onclick="filterChip(this,'')">ทั้งหมด</span>
                <span class="filter-chip" onclick="filterChip(this,'สายไฟ')">สายไฟ</span>
                <span class="filter-chip" onclick="filterChip(this,'เงิน')">เงินแดง</span>
                <span class="filter-chip" onclick="filterChip(this,'ปูน')">ปูน</span>
                <span class="filter-chip" onclick="filterChip(this,'พื้นที่')">พื้นที่สุ่มเสี่ยง</span>
                <span class="filter-chip" onclick="filterChip(this,'อันเจล')">อันเจล</span>
            </div>

            <!-- Items List -->
            <div class="items-grid" id="items-grid"></div>

            <!-- History Table -->
            <div class="table-container" style="margin-top:10px;">
                <div class="section-title"><i class="fas fa-history"></i> ประวัติเคสล่าสุด (30 รายการ)</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>เลขเคส</th>
                            <th>วัน/เวลา</th>
                            <th>ผู้ต้องหา</th>
                            <th>รายละเอียด</th>
                            <th>จำคุก</th>
                            <th>ค่าปรับ</th>
                            <th>Discord</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_cases as $c): 
                            $items_arr = json_decode($c['items'], true) ?? [];
                            $items_str = implode(', ', array_map(fn($i)=>$i['name'].'x'.$i['qty'], $items_arr));
                        ?>
                        <tr>
                            <td style="font-family:monospace;color:var(--primary);"><?= htmlspecialchars($c['case_number']) ?></td>
                            <td style="font-size:12px;"><?= date('d/m H:i', strtotime($c['created_at'])) ?></td>
                            <td><b><?= htmlspecialchars($c['suspect_name']) ?></b></td>
                            <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($items_str ?: '-') ?></td>
                            <td><?= $c['jail_minutes'] ?> น.</td>
                            <td><?= number_format($c['fine_amount']) ?> ฿</td>
                            <td>
                                <?php if($c['discord_sent']): ?>
                                    <span class="badge-sent">✓ ส่งแล้ว</span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:11px;">รอส่ง</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($all_cases)): ?>
                            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px;">ยังไม่มีประวัติเคส</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== RIGHT PANEL (Sidebar) ===== -->
        <div class="cases-sidebar">

            <!-- Suspect -->
            <div class="sidebar-section">
                <label>★ ชื่อผู้ต้องหา</label>
                <input type="text" class="sidebar-input" id="suspect-name" placeholder="ชื่อ-นามสกุล">
            </div>

            <!-- Case Type -->
            <div class="sidebar-section">
                <label>★ ประเภทคดี</label>
                <div class="case-type-btns">
                    <button class="case-type-btn active-black" onclick="setCaseType('เคสดำ', this)" id="btn-type-black">⚫ เคสดำ</button>
                    <button class="case-type-btn" onclick="setCaseType('เคสแดง', this)" id="btn-type-other">🔴 เคสเเดง
                        
                    </button>
                </div>
            </div>



            <!-- Summary -->
            <div class="sidebar-section">
                <label>💰 สรุปโทษ</label>
                <div class="summary-box">
                    <div class="summary-row">
                        <span>ค่าปรับที่กำหนด:</span>
                        <div style="display:flex;align-items:center;gap:4px;">
                            <input type="number" id="sum-fine-input" min="0" step="100" value="0"
                                class="summary-editable" style="color:var(--primary);"
                                oninput="onManualEdit()">
                            <span style="color:var(--primary);font-weight:700;font-size:15px;">฿</span>
                        </div>
                    </div>
                    <div class="summary-row" style="margin-top:6px;">
                        <span>เวลาจำคุกที่กำหนด:</span>
                        <div style="display:flex;align-items:center;gap:4px;">
                            <input type="number" id="sum-jail-input" min="0" step="1" value="0"
                                class="summary-editable" style="color:var(--danger);width:70px;"
                                oninput="onManualEdit()">
                            <span style="color:var(--danger);font-weight:700;font-size:15px;">นาที</span>
                        </div>
                    </div>
                    <div style="text-align:right;margin-top:6px;">
                        <a href="#" id="reset-link" style="display:none;font-size:11px;color:var(--text-muted);" onclick="resetSummary();return false;">↺ รีเซ็ตตามที่เลือก</a>
                    </div>
                </div>
            </div>

            <!-- Discord Preview -->
            <div class="sidebar-section">
                <label>📋 รายงาน Discord (Copy)</label>
                <div class="discord-preview" id="discord-preview">กรอกข้อมูลแล้วจะแสดงตัวอย่างที่นี่...</div>
                <div class="copy-hint">คลิก Ctrl+V แบบรูปแบบปักษิณา (อย่างน้อย 1 รูป)</div>
            </div>

            <!-- Officers On Duty -->
<div class="sidebar-section">
    <label>👮 เจ้าหน้าที่ร่วม (ออนไลน์ <?= count($on_duty_officers) ?> คน)</label>
    
    <div class="search-box" style="margin-bottom: 10px;">
        <input type="text" id="officer-search" class="sidebar-input" placeholder="🔍 ค้นหาชื่อเจ้าหน้าที่..." oninput="filterOfficers()">
    </div>

    <div class="officer-list" id="officer-list">
        <?php foreach($on_duty_officers as $o): 
            if($o['user_id'] == $user_id) continue; ?>
            <div class="officer-item" onclick="toggleOfficer(this)" 
                 data-userid="<?= htmlspecialchars($o['user_id']) ?>"
                 data-username="<?= htmlspecialchars($o['user_name']) ?>"
                 data-discordid="<?= htmlspecialchars($o['user_id']) ?>">
                <input type="checkbox" readonly>
                <label><?= htmlspecialchars($o['user_name']) ?></label>
            </div>
        <?php endforeach; ?>
    </div>
</div>

            <!-- Image Upload -->
            <div class="sidebar-section">
                <label>📷 แนบรูปภาพ (Ctrl+V หรือเลือกไฟล์)</label>
                <div class="image-upload-area" id="img-upload-area">
                    <input type="file" id="img-file-input" accept="image/*" multiple onchange="handleFileSelect(this.files)">
                    <i class="fas fa-image" style="font-size:20px;"></i>
                    <span>วางรูป (Ctrl+V) หรือคลิกเลือกไฟล์</span>
                </div>
                <div class="img-preview-grid" id="img-preview-grid"></div>
            </div>

            <!-- Submit Button -->
            <button class="btn-submit-case" id="btn-submit" onclick="submitCase()">
                <i class="fab fa-discord"></i> บันทึกและส่ง Discord
            </button>
            <div style="text-align:center;">
                <a href="#" style="font-size:12px;color:var(--text-muted);" onclick="clearAll()">ล้างข้อมูลทั้งหมด</a>
            </div>
        </div>
    </div>
</div>

<!-- QTY MODAL -->
<div class="modal-overlay" id="qty-modal">
    <div class="modal-box">
        <div class="modal-title" id="modal-item-name">ระบุจำนวน</div>
        <div class="modal-counter">
            <button class="modal-btn-pm minus" onclick="modalChange(-1)">−</button>
            <input type="number" id="modal-val" class="modal-counter-val" 
       style="background: #161925; border: 1px solid var(--border); border-radius: 8px; color: white; width: 100px; text-align: center; outline: none; font-size: 2rem; font-weight: 800;" 
       min="1">
            <button class="modal-btn-pm plus"  onclick="modalChange(+1)">+</button>
        </div>
        <div class="modal-actions">
            <button class="modal-confirm" onclick="modalConfirm()">ยืนยัน</button>
            <button class="modal-cancel"  onclick="modalClose()">ยกเลิก</button>
        </div>
    </div>
</div>

<div id="toast"></div>

<script>
// ===== DATA =====
const ITEMS = {
    general: <?= json_encode($items_general, JSON_UNESCAPED_UNICODE) ?>,
    cayo:    <?= json_encode($items_cayo,    JSON_UNESCAPED_UNICODE) ?>
};

let currentZone   = 'general';
let selectedItems = {}; // key = item name, val = qty
let caseType      = 'เคสดำ';
let chipFilter    = '';
let favs          = JSON.parse(localStorage.getItem('smpd_favs') || '[]');

// ===== ZONE SWITCH =====
function switchZone(zone, el) {
    currentZone = zone;
    document.querySelectorAll('.zone-tab').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('filter-chips-cayo')?.style && 
        (document.getElementById('filter-chips-cayo').style.display = zone==='cayo'?'flex':'none');
    chipFilter = '';
    document.querySelectorAll('.filter-chip').forEach(c=>c.classList.remove('active-chip'));
    document.querySelectorAll('.filter-chip')[0]?.classList.add('active-chip');
    renderItems();
}

// ===== RENDER ITEMS =====
function renderItems() {
    const search = document.getElementById('item-search').value.toLowerCase();
    let items = ITEMS[currentZone].filter(it => {
        const nameMatch = it.name.toLowerCase().includes(search);
        const chipMatch = chipFilter==='' || it.name.toLowerCase().includes(chipFilter.toLowerCase());
        return nameMatch && chipMatch;
    });

    // sort: favourites first
    items.sort((a,b) => {
        const af = favs.includes(a.name) ? -1 : 1;
        const bf = favs.includes(b.name) ? -1 : 1;
        return af - bf;
    });

    const grid = document.getElementById('items-grid');
    grid.innerHTML = items.map(it => {
        const qty = selectedItems[it.name] || 0;
        const isFav = favs.includes(it.name);
        const displayPrice = it.x2 ? it.price * 2 : it.price;
        const jailLabel    = it.fixedJail ? `จำคุก ${it.fixedJail} น. (คงที่)` : `จำคุก ${it.jail} น.`;
        const x2Badge      = it.x2 ? ' <span style="color:#f59e0b;font-weight:700;">x2</span>' : '';
        return `
        <div class="item-row ${qty>0?'selected':''}" id="item-${hashName(it.name)}">
            <div class="item-row-info" style="cursor:pointer;" onclick="openModal('${escStr(it.name)}')">
                <b>${it.name}${x2Badge}</b>
                <small>💰 ${displayPrice.toLocaleString()} ฿ &nbsp;|&nbsp; ⏱ ${jailLabel}</small>
            </div>
            <div class="item-row-actions">
                <button class="fav-btn ${isFav?'active':''}" onclick="toggleFav('${escStr(it.name)}', this)" title="Favourite">★</button>
                <button class="qty-btn" onclick="changeQty('${escStr(it.name)}', -1)">−</button>
                <span class="qty-display" style="cursor:pointer;" onclick="openModal('${escStr(it.name)}')">${qty}</span>
                <button class="qty-btn" onclick="openModal('${escStr(it.name)}')">+</button>
            </div>
        </div>`;
    }).join('');
}

function hashName(n) { return n.replace(/[^a-zA-Z0-9ก-ฮ]/g,'_'); }
function escStr(s) { return s.replace(/'/g,"\\'"); }

function changeQty(name, delta) {
    const cur = selectedItems[name] || 0;
    const nv = Math.max(0, cur + delta);
    if(nv === 0) delete selectedItems[name]; else selectedItems[name] = nv;
    updateSummary();
    renderItems();
}

function toggleFav(name, btn) {
    if(favs.includes(name)) { favs = favs.filter(f=>f!==name); btn.classList.remove('active'); }
    else { favs.push(name); btn.classList.add('active'); }
    localStorage.setItem('smpd_favs', JSON.stringify(favs));
    renderItems();
}

function filterItems() { chipFilter=''; renderItems(); }

function filterChip(el, val) {
    document.querySelectorAll('.filter-chip').forEach(c=>c.classList.remove('active-chip'));
    el.classList.add('active-chip');
    chipFilter = val;
    renderItems();
}

// ===== CASE TYPE =====
function setCaseType(type, el) {
    caseType = type;
    document.getElementById('btn-type-black').classList.remove('active-black');
    document.getElementById('btn-type-other').classList.remove('active-other');
    if(type==='เคสดำ') el.classList.add('active-black');
    else el.classList.add('active-other');
    updateSummary();
}

// ===== TOGGLE OFFICER =====
function toggleOfficer(el) {
    el.classList.toggle('selected');
    const cb = el.querySelector('input[type=checkbox]');
    cb.checked = el.classList.contains('selected');
    updateSummary();
}

// ===== UPDATE SUMMARY & DISCORD PREVIEW =====
let manualOverride = false;

function onManualEdit() {
    manualOverride = true;
    document.getElementById('reset-link').style.display = 'inline';
    buildDiscordPreview([]);
}

function resetSummary() {
    manualOverride = false;
    document.getElementById('reset-link').style.display = 'none';
    updateSummary();
}

function updateSummary() {
    const allItems = [...ITEMS.general, ...ITEMS.cayo];
    let totalFine = 0, totalJail = 0;
    let itemLines = [];

    for(const [name, qty] of Object.entries(selectedItems)) {
        const meta = allItems.find(i=>i.name===name);
        if(!meta) continue;
        const unitPrice    = meta.x2 ? meta.price * 2 : meta.price;
        const subtotalFine = unitPrice * qty;
        const subtotalJail = meta.fixedJail ? meta.fixedJail : meta.jail * qty;
        totalFine += subtotalFine;
        totalJail += subtotalJail;
        const priceLabel   = meta.x2 ? `${unitPrice.toLocaleString()} ฿ x${qty} (x2)` : `${subtotalFine.toLocaleString()} ฿`;
        itemLines.push(`• ${name} x${qty} (${priceLabel} | ${subtotalJail} น.)`);
    }

    // อัปเดต input เฉพาะถ้าไม่ได้แก้เอง
    if (!manualOverride) {
        document.getElementById('sum-fine-input').value = totalFine;
        document.getElementById('sum-jail-input').value = totalJail;
    }

    buildDiscordPreview(itemLines);
}

function buildDiscordPreview(itemLines) {
    const suspectName = document.getElementById('suspect-name').value || '(ยังไม่ระบุ)';
    const selectedOff = [...document.querySelectorAll('.officer-item.selected')];
    const offLines    = selectedOff.map(o => `<@${o.dataset.discordid}>`).join(', ');

    let msg = `🏛️ **รายงานสถานะคดี | ${caseType}**\n`;
    if(itemLines.length) {
        msg += itemLines.join('\n') + '\n';
    }

    document.getElementById('discord-preview').textContent = msg;
}

// ===== SUBMIT =====
async function submitCase() {
    const suspectName = document.getElementById('suspect-name').value.trim();
    if(!suspectName) { showToast('กรุณาระบุชื่อผู้ต้องหา', true); return; }
    if(Object.keys(selectedItems).length === 0) { showToast('กรุณาเลือกข้อหาอย่างน้อย 1 รายการ', true); return; }

    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

    const allItems = [...ITEMS.general, ...ITEMS.cayo];
    let totalFine = 0, totalJail = 0;
    const itemsArr = [];
    for(const [name, qty] of Object.entries(selectedItems)) {
        const meta = allItems.find(i=>i.name===name);
        if(!meta) continue;
        const unitPrice = meta.x2 ? meta.price * 2 : meta.price;
        const jailTotal = meta.fixedJail ? meta.fixedJail : meta.jail * qty;
        totalFine += unitPrice * qty;
        totalJail += jailTotal;
        itemsArr.push({name, qty, price: unitPrice, jail: meta.fixedJail ? meta.fixedJail : meta.jail});
    }

    const selectedOff = [...document.querySelectorAll('.officer-item.selected')];
    const assistingArr = selectedOff.map(o => ({user_id: o.dataset.userid, user_name: o.dataset.username}));

    const fd = new FormData();
    fd.append('suspect_name',    suspectName);
    fd.append('case_type',       caseType);
    fd.append('location',        '');
    fd.append('jail_minutes',    parseInt(document.getElementById('sum-jail-input').value) || totalJail);
    fd.append('fine_amount',     parseInt(document.getElementById('sum-fine-input').value) || totalFine);
    fd.append('items_json',      JSON.stringify(itemsArr));
    fd.append('assisting_json',  JSON.stringify(assistingArr));
    fd.append('discord_message', document.getElementById('discord-preview').textContent);
    // แนบรูปภาพ
    uploadedImages.forEach((img, i) => {
        fd.append('case_images[]', img.file, img.file.name || `image_${i}.png`);
    });

    try {
        const r = await fetch('action_case.php', {method:'POST', body: fd});
        const d = await r.json();
        if(d.success) {
            showToast('✅ บันทึกและส่ง Discord เรียบร้อย!');
            setTimeout(()=>location.reload(), 1500);
        } else {
            showToast(d.message || 'เกิดข้อผิดพลาด', true);
            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-discord"></i> บันทึกและส่ง Discord';
        }
    } catch(e) {
        showToast('ไม่สามารถเชื่อมต่อได้', true);
        btn.disabled = false;
        btn.innerHTML = '<i class="fab fa-discord"></i> บันทึกและส่ง Discord';
    }
}

function clearAll() {
    selectedItems = {};
    manualOverride = false;
    document.getElementById('reset-link').style.display = 'none';
    document.getElementById('suspect-name').value = '';

    document.querySelectorAll('.officer-item').forEach(o=>{
        o.classList.remove('selected');
        o.querySelector('input').checked = false;
    });
    updateSummary();
    renderItems();
}

function showToast(msg, isError=false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show' + (isError?' error':'');
    setTimeout(()=>t.className='', 2500);
}

// ===== QTY MODAL =====
let modalItemName = '';
let modalQty = 1;

function filterOfficers() {
    const search = document.getElementById('officer-search').value.toLowerCase().trim();
    const officers = document.querySelectorAll('.officer-item');
    
    officers.forEach(item => {
        const name = item.dataset.username.toLowerCase();
        
        // ถ้าช่องค้นหาว่าง ให้ซ่อนทั้งหมด
        // ถ้าไม่ว่าง ให้แสดงเฉพาะชื่อที่ตรงกับที่พิมพ์
        if (search !== '' && name.includes(search)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function openModal(name) {
    modalItemName = name;
    modalQty = selectedItems[name] || 1;
    document.getElementById('modal-item-name').textContent = 'ระบุจำนวน: ' + name;
    document.getElementById('modal-val').textContent = modalQty;
    document.getElementById('qty-modal').classList.add('show');
}

// ฟังก์ชันสำหรับปุ่ม + และ -
function modalChange(delta) {
    // ดึงค่าปัจจุบันจากช่อง input (ถ้าว่างให้เป็น 0)
    let input = document.getElementById('modal-val');
    let currentVal = parseInt(input.value) || 0;
    
    // คำนวณค่าใหม่ โดยไม่ให้ต่ำกว่า 1
    input.value = Math.max(1, currentVal + delta);
}

// ฟังก์ชันยืนยันค่า
function modalConfirm() {
    // อ่านค่าสุดท้ายจากช่อง input ที่เราพิมพ์หรือกดบวกลบไว้
    let inputVal = parseInt(document.getElementById('modal-val').value);
    modalQty = (isNaN(inputVal) || inputVal < 1) ? 1 : inputVal;
    
    selectedItems[modalItemName] = modalQty;
    modalClose();
    updateSummary();
    renderItems();
}

function modalClose() {
    document.getElementById('qty-modal').classList.remove('show');
}

document.getElementById('qty-modal').addEventListener('click', function(e) {
    if(e.target === this) modalClose();
});

// ===== IMAGE PASTE & UPLOAD =====
let uploadedImages = []; // array of {file, dataUrl}

function handleFileSelect(files) {
    [...files].forEach(f => addImage(f));
}

function addImage(file) {
    if(!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        uploadedImages.push({file, dataUrl: e.target.result});
        renderImagePreviews();
    };
    reader.readAsDataURL(file);
}

function removeImage(idx) {
    uploadedImages.splice(idx, 1);
    renderImagePreviews();
}

function renderImagePreviews() {
    const grid = document.getElementById('img-preview-grid');
    grid.innerHTML = uploadedImages.map((img, i) => `
        <div class="img-preview-item">
            <img src="${img.dataUrl}" alt="รูป ${i+1}">
            <button class="remove-img" onclick="removeImage(${i})">×</button>
        </div>
    `).join('');
}

// Ctrl+V paste image
document.addEventListener('paste', function(e) {
    const items = e.clipboardData?.items;
    if(!items) return;
    for(const item of items) {
        if(item.type.startsWith('image/')) {
            const file = item.getAsFile();
            if(file) addImage(file);
        }
    }
});

// Drag-over styling for upload area
const uploadArea = document.getElementById('img-upload-area');
uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop', e => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    handleFileSelect(e.dataTransfer.files);
});

// ===== DUTY TIMER =====
// elapsed ที่คำนวณจาก MySQL (ไม่มีปัญหา timezone)
const dutyElapsedAtLoad = <?= $duty_elapsed_ms ?>;
const dutyLoadedAt = Date.now();
function updateDutyTimer() {
    if(!dutyElapsedAtLoad && dutyElapsedAtLoad !== 0) return;
    if(<?= $duty_row ? 'true' : 'false' ?> === false) return;
    const totalMs = dutyElapsedAtLoad + (Date.now() - dutyLoadedAt);
    const h = Math.floor(totalMs/3600000);
    const m = Math.floor((totalMs%3600000)/60000);
    const s = Math.floor((totalMs%60000)/1000);
    document.getElementById('duty-timer').textContent =
        h.toString().padStart(2,'0')+':'+m.toString().padStart(2,'0')+':'+s.toString().padStart(2,'0');
}
setInterval(updateDutyTimer, 1000);
updateDutyTimer();

// auto update preview on input
document.getElementById('suspect-name').addEventListener('input', updateSummary);


// Init
renderItems();
updateSummary();
</script>
    <footer style="text-align:center; padding:28px 0 20px; color:#4a5568; font-size:12px; letter-spacing:0.5px; font-family:'Noto Sans Thai',sans-serif;">
    © Police All Star. by Four Fxpl .achikp_43035
</footer>
</body>
</html>
