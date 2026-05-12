<?php 
include 'check_login.php';
include 'db.php'; 
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Police All Star PD - กฎระเบียบ</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .rules-wrapper { max-width: 960px; margin: 0 auto; }

        .page-title {
            text-align: center; margin-bottom: 10px;
            font-size: 2rem; font-weight: 800;
            color: var(--primary); letter-spacing: 2px; text-transform: uppercase;
        }
        .page-sub { text-align: center; color: var(--text-muted); margin-bottom: 24px; font-size: 0.95rem; }

        /* Search */
        .search-box {
            display: flex; align-items: center; gap: 10px;
            background: var(--card); border: 1px solid var(--border);
            border-radius: 12px; padding: 10px 18px; margin-bottom: 16px;
        }
        .search-box i { color: var(--text-muted); }
        .search-box input {
            flex: 1; background: transparent; border: none;
            outline: none; color: var(--text); font-size: 0.95rem;
        }
        .search-box input::placeholder { color: var(--text-muted); }

        /* Tab Nav */
        .tab-nav {
            display: flex; flex-wrap: wrap; gap: 8px;
            background: var(--card); border: 1px solid var(--border);
            border-radius: 14px; padding: 10px 14px; margin-bottom: 28px;
        }
        .tab-btn {
            display: flex; align-items: center; gap: 7px;
            padding: 8px 16px; border-radius: 10px;
            border: 1px solid transparent; background: transparent;
            color: var(--text-muted); font-size: 0.88rem; font-weight: 600;
            cursor: pointer; transition: all 0.18s ease; white-space: nowrap;
            font-family: inherit;
        }
        .tab-btn:hover { background: rgba(255,255,255,0.06); color: var(--text); }
        .tab-btn i { font-size: 0.82rem; }

        .tab-btn.active-all      { background: var(--primary); color: #000; border-color: var(--primary); }
        .tab-btn.active-laws     { background: rgba(59,130,246,0.2);  color: #60a5fa; border-color: rgba(59,130,246,0.45); }
        .tab-btn.active-discipline { background: rgba(251,191,36,0.2); color: #fbbf24; border-color: rgba(251,191,36,0.45); }
        .tab-btn.active-blacklist { background: rgba(239,68,68,0.2);  color: #f87171; border-color: rgba(239,68,68,0.45); }
        .tab-btn.active-rebel    { background: rgba(236,72,153,0.2);  color: #f472b6; border-color: rgba(236,72,153,0.45); }
        .tab-btn.active-weapon   { background: rgba(168,85,247,0.2);  color: #c084fc; border-color: rgba(168,85,247,0.45); }

        /* Page sections (top-level tabs) */
        .page-section { display: none; }
        .page-section.active { display: block; }

        /* Category card */
        .cat-section {
            margin-bottom: 28px; border-radius: 15px; overflow: hidden;
            border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .cat-header {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 20px; font-size: 1.05rem; font-weight: 700; letter-spacing: 0.5px;
        }
        .hdr-blue   { background: linear-gradient(90deg, rgba(59,130,246,0.25), rgba(59,130,246,0.05)); color: #60a5fa; border-bottom: 1px solid rgba(59,130,246,0.3); }
        .hdr-yellow { background: linear-gradient(90deg, rgba(251,191,36,0.25), rgba(251,191,36,0.05)); color: #fbbf24; border-bottom: 1px solid rgba(251,191,36,0.3); }
        .hdr-red    { background: linear-gradient(90deg, rgba(239,68,68,0.25), rgba(239,68,68,0.05)); color: #f87171; border-bottom: 1px solid rgba(239,68,68,0.3); }
        .hdr-green  { background: linear-gradient(90deg, rgba(34,197,94,0.25), rgba(34,197,94,0.05)); color: #4ade80; border-bottom: 1px solid rgba(34,197,94,0.3); }
        .hdr-purple { background: linear-gradient(90deg, rgba(168,85,247,0.25), rgba(168,85,247,0.05)); color: #c084fc; border-bottom: 1px solid rgba(168,85,247,0.3); }
        .hdr-pink   { background: linear-gradient(90deg, rgba(236,72,153,0.25), rgba(236,72,153,0.05)); color: #f472b6; border-bottom: 1px solid rgba(236,72,153,0.3); }

        /* Tables */
        .law-table { width: 100%; border-collapse: collapse; background: var(--card); }
        .law-table thead tr { background: rgba(255,255,255,0.04); border-bottom: 1px solid var(--border); }
        .law-table th {
            padding: 10px 16px; text-align: left; font-size: 0.78rem;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted);
        }
        .law-table td {
            padding: 12px 16px; font-size: 0.9rem; color: var(--text);
            vertical-align: top; border-bottom: 1px solid var(--border); line-height: 1.5;
        }
        .law-table tbody tr:last-child td { border-bottom: none; }
        .law-table tbody tr:hover { background: rgba(255,255,255,0.03); }
        .col-no   { width: 40px; color: var(--text-muted); font-size: 0.8rem; }
        .col-fine { white-space: nowrap; font-weight: 700; color: #f87171; }
        .col-time { white-space: nowrap; font-weight: 700; color: #60a5fa; }
        .row-note {
            font-size: 0.8rem; color: var(--text-muted);
            margin-top: 4px; padding-left: 6px; border-left: 2px solid var(--border);
        }

        /* List card (for non-table sections) */
        .list-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 15px; overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 28px;
        }
        .list-item {
            display: flex; gap: 14px; padding: 14px 20px;
            border-bottom: 1px solid var(--border); align-items: flex-start;
        }
        .list-item:last-child { border-bottom: none; }
        .list-item:hover { background: rgba(255,255,255,0.03); }
        .list-num {
            min-width: 28px; height: 28px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.78rem; font-weight: 700; margin-top: 1px; flex-shrink: 0;
        }
        .num-yellow { background: rgba(251,191,36,0.18); color: #fbbf24; }
        .num-red    { background: rgba(239,68,68,0.18);  color: #f87171; }
        .num-pink   { background: rgba(236,72,153,0.18); color: #f472b6; }
        .num-purple { background: rgba(168,85,247,0.18); color: #c084fc; }
        .list-text { font-size: 0.92rem; line-height: 1.65; color: var(--text); }
        .fine-badge {
            display: inline-block; margin-top: 6px;
            background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.35);
            color: #f87171; border-radius: 8px; padding: 3px 10px;
            font-size: 0.8rem; font-weight: 700;
        }
        .note-badge {
            display: inline-block; margin-top: 6px;
            background: rgba(251,191,36,0.12); border: 1px solid rgba(251,191,36,0.3);
            color: #fbbf24; border-radius: 8px; padding: 3px 10px;
            font-size: 0.8rem;
        }

        .no-result { text-align: center; padding: 40px; color: var(--text-muted); font-size: 0.95rem; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
    <div class="rules-wrapper">

        <h1 class="page-title">&#128221; กฎระเบียบ SummerCity</h1>
        <p class="page-sub">คดี · วินัย · Blacklist · พื้นที่พิเศษ · การใช้อาวุธ</p>

        <div class="search-box" id="searchBox" style="display:none;">
            <i class="fas fa-search"></i>
            <input type="text" id="lawSearch" placeholder="ค้นหา..." oninput="filterLaws()">
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button class="tab-btn active-all" data-tab="all" onclick="switchTab('all',this)">
                <i class="fas fa-list"></i> ทั้งหมด
            </button>
            <button class="tab-btn" data-tab="laws" onclick="switchTab('laws',this)">
                <i class="fas fa-gavel"></i> คดีทั่วไป
            </button>
            <button class="tab-btn" data-tab="discipline" onclick="switchTab('discipline',this)">
                <i class="fas fa-shield-alt"></i> โทษวินัยตำรวจ
            </button>
            <button class="tab-btn" data-tab="blacklist" onclick="switchTab('blacklist',this)">
                <i class="fas fa-ban"></i> Blacklist
            </button>
            <button class="tab-btn" data-tab="rebel" onclick="switchTab('rebel',this)">
                <i class="fas fa-map-marker-alt"></i> พื้นที่เรเบลแดง
            </button>
            <button class="tab-btn" data-tab="weapon" onclick="switchTab('weapon',this)">
                <i class="fas fa-crosshairs"></i> การใช้อาวุธ
            </button>
        </div>

        <!-- ===== SECTION: คดีทั่วไป ===== -->
        <div class="page-section active" data-section="laws">

            <?php
            $lawCategories = [
                'คดีทั่วไป' => [
                    'hdr'  => 'hdr-blue',
                    'icon' => 'fas fa-file-alt',
                    'key'  => 'general',
                    'rows' => [
                        [1,  'Cement',                                    '3,000',          '10 นาที',            'Cement Pack คิดเป็นจำนวน Cement 100 ชิ้น'],
                        [2,  'เงินแดง',                                    'จำนวนเงินแดง x 2',          '10 นาที',            '*จำคุก 10 นาที (ค่าปรับขั้นต่ำ 1000 cash )'],
                        [3,  'Capsule ทุกชนิด',                                    '3,000',          '10 นาที',            ''],
                        [4,  'พื้นที่สุ่มเสี่ยง',                                    '3,000',          '10 นาที',            'บริเวณพื้นที่ที่มีสิ่งผิดกฎหมาย'],
                        [5,  'กระทำความผิดซึ่งหน้า',                                '5,000',          '10 นาที',            'พูดถึงสิ่งผิดกฎหมายต่อหน้าเจ้าหน้าที่ ก็ถือเป็นกระทำความผิดซึ่งหน้า'],
                        [6, 'ก่อกวนผู้อื่น',                                        '5,000',          '15 นาที',            ''],
                        [7,  'พูดจาดูหมิ่นประชาชนคนอื่น',                           '30,000',         '30 นาที',            ''],
                        [8, 'อนาจาร',                                              '10,000',         '30 นาที',            ''],
                        [9, 'แจ้งความเท็จ / ใส่ร้ายคนอื่น',                        '10,000',         '60 นาที',            ''],
                        [10, 'ไม่ให้ความร่วมมือกับเจ้าหน้าที่',                     '100,000',        '60 นาที',            ''],
                        [11, 'ทิ้ง/ทำลายหลักฐาน / ส่งของผิดกฎหมายให้คนอื่น',       '10,000',         '30 นาที',            ''],
                        [12, 'ทำลายทรัพย์สินประชาชน',                               '5,000',          '30 นาที',            'ค่าเสียหาย 2,500 x จำนวนผู้กระทำ'],
                        [13, 'ขโมยรถประชาชน',                                       '10,000',         '30 นาที',            'ผู้เสียหายสามารถเรียกค่าเสียหายได้ 5,000 IC'],
                        [14, 'หลบหนี',                                               '2,000',          '10 นาที',            'สามารถใช้ Pump Shotgun ในการจับกุมได้'],
                        [15, 'หลบหนีหลังการจับกุม',                                  'x2',             'x2',                 'สามารถใช้ Pump Shotgun ในการจับกุมได้'],
                        [16, 'หลบหนีขึ้นภูเขา',                                      'x3',             '30 นาที',            'ตำรวจสามารถบั้มรถได้ทันที'],
                        [17, 'หลบหนีลงน้ำ',                                         'x3',             '30 นาที',            'ตำรวจสามารถจับตายได้ / ใช้เมนูอุ้มในน้ำได้ / ไม่สามารถใส่กุญแจมือในน้ำ'],
                        [18, 'งานดำนอกเมือง / หลบหนีออกนอกเมือง',                   'x3',             '30 นาที',            'ตำรวจสามารถบั้มรถได้ทันที'],
                        [19, 'แหกคุก',                                               '10,000',         'เวลาเดิม + 60 นาที', ''],
                    ],
                ],
                'อาวุธ' => [
                    'hdr'  => 'hdr-red',
                    'icon' => 'fas fa-crosshairs',
                    'key'  => 'weapon_law',
                    'rows' => [
                        [20, 'ถืออาวุธในสถานที่ราชการ (อาวุธทุกชนิด)',              '20,000',         '120 นาที',           'เตือนก่อน 1 ครั้ง หากยังไม่ก่อเหตุ · กรณีทำร้ายร่างกายปรับได้ทันที'],
                        [21, 'ถืออาวุธในพื้นที่สาธารณะ (อาวุธทุกชนิด)',             '10,000',         '60 นาที',            'เตือนก่อน 1 ครั้ง หากยังไม่ก่อเหตุ · กรณีทำร้ายร่างกายปรับได้ทันที'],
                    ],
                ],
                'ก่อกวนเจ้าหน้าที่, โจน' => [
                    'hdr'  => 'hdr-purple',
                    'icon' => 'fas fa-user-secret',
                    'key'  => 'social',
                    'rows' => [
                        [22, 'ใส่หน้ากาก / ปิดบังใบหน้า (ยกเว้นผ้าปิดปาก)',        '10,000',         '60 นาที',            'เตือนก่อน 1 ครั้ง · งานดำโดนทันทีโดยไม่มีการเตือน'],
                        [23, 'ก่อกวนเจ้าหน้าที่หน่วยงาน',                           '20,000',         '60 นาที',            ''],
                        [24, 'แอบอ้างเป็นหน่วยงาน',                                '50,000',         '60 นาที',            'ทั้งคำพูดและการแต่งกายที่เกี่ยวข้อง · ชำระบิลหน้างาน'],
                        [25, 'บุกรุกสถานที่ราชการ',                                 '10,000',         '30 นาที',            'สถานีตำรวจ / สภา / โรงพยาบาล / คุก'],
                        [26, 'ขับรถโดยประมาท',                                     '10,000',         '10 นาที',            'ขับรถหลบหนีเข้าพื้นที่แลนด์หรือที่มีคนอยู่มาก'],
                        [27, 'หมายจับ',                                             '10,000',         '60 นาที',            'ติดคดี 24 ชม. · ต้องรายงานตัวทุก 1 ชม. ทางข้อความตำรวจ'],
                        [28, 'กดวิทยุสื่อสารขณะถูกใส่กุญแจมือ',                     'x5',             '-',                  'พิจารณาโทษใบตามกฎประเทศ'],
                        [29, 'ช่วยเหลือผู้กระทำผิดระหว่างจับกุม',                   '10,000',         '30 นาที',            'ยิงยางหรือบั้มรถได้เลย / ยิงบนรถได้ทันที'],
                        [30, 'ประกันเวลา',                                          '200 ต่อ 1 นาที', 'ลดได้ถึง 1 นาที',   'เฉพาะเคสงานดำทั่วไปเท่านั้น'],
                    ],
                ],
            ];

            foreach ($lawCategories as $catName => $cat):
            ?>
            <div class="cat-section law-block" data-key="<?= $cat['key'] ?>">
                <div class="cat-header <?= $cat['hdr'] ?>">
                    <i class="<?= $cat['icon'] ?>"></i>
                    <?= htmlspecialchars($catName) ?>
                    <span style="margin-left:auto;font-size:0.8rem;font-weight:400;opacity:0.75;"><?= count($cat['rows']) ?> คดี</span>
                </div>
                <table class="law-table">
                    <thead><tr>
                        <th style="width:40px">#</th>
                        <th>รายการ</th>
                        <th style="width:130px">ค่าปรับ ($)</th>
                        <th style="width:140px">จำคุก</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($cat['rows'] as $row): ?>
                        <tr class="law-row" data-name="<?= htmlspecialchars(mb_strtolower($row[1])) ?>">
                            <td class="col-no"><?= $row[0] ?></td>
                            <td>
                                <?= htmlspecialchars($row[1]) ?>
                                <?php if (!empty($row[4])): ?>
                                <div class="row-note"><?= htmlspecialchars($row[4]) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="col-fine"><?= htmlspecialchars($row[2]) ?></td>
                            <td class="col-time"><?= htmlspecialchars($row[3]) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ===== SECTION: โทษวินัยตำรวจ ===== -->
        <div class="page-section" data-section="discipline">
            <?php
            $discipline = [
                [1,  'ตำรวจต้องอยู่ครบ 1 เดือน นับวันก็ต่อเมื่อเข้าเวร 3 ชม. ขึ้นไป หากไม่ครบจะไม่นับวัน และหากอยู่ไม่ถึงจะถูกรีตัว', ''],
                [2,  'หากตำรวจทำผิดวินัย 3 ครั้ง ปลดทันที หรือ ผบ.ตร. สามารถปลดได้โดยไม่ต้องครบ 3 ครั้ง ขึ้นอยู่กับดุลพินิจ', ''],
                [3,  'หากออกจากตำรวจ ต้องติดคูลดาวน์ 15 วัน ถึงจะสามารถกลับมาสอบหน่วยงานตำรวจใหม่ได้', ''],
                [4,  'ห้ามช่วยเหลือแก๊ง/ประชาชน หรือกระทำการเอื้อประโยชน์ให้ผู้ต้องหา หากพบเจอ เป็นตำรวจดำทันที', 'ขึ้นอยู่กับดุลพินิจของ ผ.บ. และ นายก'],
                [5,  'ห้ามออกเวรไปทำงานดำ เว้นแต่ลาพักร้อน หากไปทำสตอรี่โจรและอุ้มฆ่า โดนจับได้ เป็นตำรวจดำทันที', 'ขึ้นอยู่กับดุลพินิจของ ผ.บ. และ นายก'],
                [6,  'ตำรวจมีปากเสียงกับประชาชนหากเราเริ่มก่อน (ต้องมีหลักฐานจากฝั่งประชาชน หรือสายตาตำรวจท่านอื่น)', '500,000 · ผิดวินัย 1 ครั้ง'],
                [7,  'ใช้เมนูค้นตัว หรือ ตรวจบัตร โดยไม่ได้ขออนุญาต (ยกเว้นผู้ต้องสงสัย/ผู้ต้องหา สามารถตรวจได้ทันที)', '500,000'],
                [8,  'ขณะอยู่ในหน้าที่ มีการใช้อาวุธตีกัน หรือยิงเทเซอร์ใส่กัน ในกรณีมีผู้อื่นนอกจากตำรวจอยู่', '300,000'],
                [9,  'ด่า / ตะคอกเสียง / หยาบคาย กันในวอหน่วยงาน', '500,000 · ผิดวินัย 1 ครั้ง'],
                [10, 'ยืนเหม่อโดยไม่มีการแจ้ง หรือเกิน 10 นาที เรียกไม่ตอบ', '500,000'],
                [11, 'ยืนเหม่อจนสลบ', '1,000,000 · ยึด Police Coin 50%'],
                [12, 'เข้าเวรเล่นกิจกรรมต่างๆ เช่น การเล่น Airdrop ประชาชน', '1,000,000 · ผิดวินัย 1 ครั้ง'],
                [13, 'พบเจอตำรวจที่เข้าข่ายตำรวจดำ จะมีการพิจารณาโทษวินัยจาก ผบ. และโทษใบแดงจากนายก', ''],
                [14, 'ออกตำรวจแล้วไม่คืนของกลับเข้ากรม เช่น Taser, Pump Shotgun, Nightstick, รถหน่วยงาน', 'ใบเหลือง'],
                [15, 'ขัดคำสั่งผู้บังคับบัญชา', '500,000 · ผิดวินัย 1 ครั้ง'],
                [16, 'ไม่ลงชื่อเข้า-ออกเวร', '50,000/วัน (ยกเว้นวันที่ลาเวร)'],
                [17, 'ห้ามตำรวจอุ้มกันส่ง รพ.', '2,000,000'],
                [18, 'จอดรถขวางหน้า สน. / ขับรถขึ้น สน.', 'จอดขวาง: 100,000 · ขับขึ้น: 200,000'],
                [19, 'ไม่เข้าวอ หลังจากประกาศเตือน 3 ครั้ง', '100,000'],
                [20, 'ไม่ใช้รหัสวอในการสื่อสาร', '50,000'],
                [21, 'ไม่เก็บรถก่อนออกเวร', '100,000'],
                [22, 'ตำรวจทำหน้าที่บกพร่อง', '50,000 – 500,000 · ผิดวินัย 1 ครั้ง'],
                [23, 'ใช้ของหน่วยงานขณะออกเวร', '500,000'],
                [24, 'ผบ.ตร. สามารถลดย่อนได้ตามดุลยพินิจ', ''],
                [25, 'ห้ามใช้เจลลอยโดยไม่ได้รับอนุญาต', '500,000'],
            ];
            ?>
            <div class="list-card">
                <div class="cat-header hdr-yellow">
                    <i class="fas fa-shield-alt"></i> โทษวินัยตำรวจ
                    <span style="margin-left:auto;font-size:0.8rem;font-weight:400;opacity:0.75;"><?= count($discipline) ?> ข้อ</span>
                </div>
                <?php foreach ($discipline as $item): ?>
                <div class="list-item disc-row" data-name="<?= htmlspecialchars(mb_strtolower($item[1])) ?>">
                    <div class="list-num num-yellow"><?= $item[0] ?></div>
                    <div class="list-text">
                        <?= htmlspecialchars($item[1]) ?>
                        <?php if (!empty($item[2])): ?>
                        <br><span class="fine-badge"><i class="fas fa-exclamation-circle" style="font-size:0.75rem;margin-right:4px;"></i><?= htmlspecialchars($item[2]) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== SECTION: Blacklist ===== -->
        <div class="page-section" data-section="blacklist">
            <?php
            $blacklist = [
                [1, 'ทำร้ายร่างกายเจ้าหน้าที่ (พื้นที่ สน. เท่านั้น)', 'ปชช. 200,000 / แก๊ง 500,000', 'หากยังมีการทำผิดซ้ำในเรื่องเดิม จะทำการคูณค่า Blacklist ตามลำดับ สูงสุด x5'],
                [2, 'เข้ามาก่อกวน/ขัดขวางการทำงาน/ก่อความไม่สงบในพื้นที่ สน.', 'ปชช. 200,000 / แก๊ง 500,000', ''],
                [3, 'อุ้มฆ่าหน่วยงาน หรือเคลื่อนย้ายศพหน่วยงาน', 'ปชช. และ แก๊ง 500,000', 'ระหว่าง Blacklist จนกว่าจะชำระค่าปลด จะไม่ได้รับความยุติธรรมและความคุ้มครองทางกฎหมายทุกกรณี'],
            ];
            ?>
            <div class="list-card">
                <div class="cat-header hdr-red">
                    <i class="fas fa-ban"></i> Blacklist
                    <span style="margin-left:auto;font-size:0.8rem;font-weight:400;opacity:0.75;"><?= count($blacklist) ?> ข้อ</span>
                </div>
                <?php foreach ($blacklist as $item): ?>
                <div class="list-item">
                    <div class="list-num num-red"><?= $item[0] ?></div>
                    <div class="list-text">
                        <?= htmlspecialchars($item[1]) ?>
                        <br><span class="fine-badge"><i class="fas fa-coins" style="font-size:0.75rem;margin-right:4px;"></i>ค่าปลด: <?= htmlspecialchars($item[2]) ?></span>
                        <?php if (!empty($item[3])): ?>
                        <br><span class="note-badge"><?= htmlspecialchars($item[3]) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== SECTION: พื้นที่เรเบลแดง ===== -->
        <div class="page-section" data-section="rebel">
            <?php
            $rebel = [
                [1, 'เจ้าหน้าที่สามารถบั้มแล้วยิงจากบนรถได้ทุกกรณี เนื่องจากเป็นพื้นที่ไร้กฎหมาย'],
                [2, 'การก่อคดีนอกพื้นที่แล้วหลบหนีเข้าเรเบล จะนับโทษตามกฎหมายปกติ'],
                [3, 'หากมีการทำผิดในเรเบลแดง เจ้าหน้าที่ไม่สามารถจับกุมได้'],
                [4, 'กรณีห่อในพื้นที่เรเบลแดงแล้วลากออกมาจากวงเรเบล จะถูกดำเนินคดีตามปกติ แม้จะลากกลับเข้าไปในพื้นที่ไร้กฎหมาย'],
                [5, 'เจ้าหน้าที่ไม่สามารถทำการล็อคมือผู้ต้องหาได้ทุกกรณี'],
                [6, 'การนำตัวผู้ต้องหาออกไปดำเนินคดี เจ้าหน้าที่ต้องทำให้ผู้ต้องหาสลบเท่านั้น (เฉพาะต้นเคสที่ลากเข้าเรเบล)'],
                [7, 'หากมีการไฟต์กับเจ้าหน้าที่ในเขตพื้นที่เรเบล ระหว่างไฟต์สามารถเคลื่อนย้ายศพหรือกักศพเจ้าหน้าที่ได้ แต่หากไฟต์จบแล้วและทำการขอศพต้องคืนศพเจ้าหน้าที่ทุกกรณี'],
                [8, 'กรณีที่มีการไฟท์แล้วหน่วยงานในพื้นที่เรเบลสลบหมด แล้วมีการประกาศขอคืนศพหน่วยงาน ต้องคืนทันที ห้ามอุ้มไว้หรือเคลื่อนย้ายศพ'],
            ];
            ?>
            <div class="list-card">
                <div class="cat-header hdr-pink">
                    <i class="fas fa-map-marker-alt"></i> กฎพื้นที่เรเบลแดง
                    <span style="margin-left:auto;font-size:0.8rem;font-weight:400;opacity:0.75;"><?= count($rebel) ?> ข้อ</span>
                </div>
                <?php foreach ($rebel as $item): ?>
                <div class="list-item">
                    <div class="list-num num-pink"><?= $item[0] ?></div>
                    <div class="list-text"><?= htmlspecialchars($item[1]) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== SECTION: การใช้อาวุธ ===== -->
        <div class="page-section" data-section="weapon">
            <?php
            $weapons = [
                [
                    'name' => 'Taser',
                    'hdr'  => 'hdr-green',
                    'icon' => 'fas fa-bolt',
                    'items' => ['สามารถใช้ได้ทุกเคส ทุกกรณี เช่น ยิงยาง / ยิงตัวผู้ต้องหาเพื่อทำการจับกุม'],
                ],
                [
                    'name' => 'Pump Shotgun',
                    'hdr'  => 'hdr-yellow',
                    'icon' => 'fas fa-crosshairs',
                    'items' => [
                        'ผู้ต้องหาใส่หน้ากาก / ปิดบังใบหน้า',
                        'ผู้ต้องหาหลบหนีหลังการจับกุม',
                        'ผู้ต้องหาติด Blacklist',
                        'ผู้ต้องหาติดหมายจับ',
                        'ยิงผู้ต้องหาให้ตกรถ กรณีที่รถติดไม่สามารถไปต่อได้แต่ไม่ยอมลงรถ (ภายใน 20 วินาที)',
                        'คดีในหมวดทำร้ายร่างกาย / ทรัพย์สิน / ฆาตกรรม',
                        'ป้องกันตัวเอง',
                        'สามารถใช้ในพื้นที่ราชการได้ทุกกรณี',
                        'ขโมยรถ',
                        'คดีแดง',
                    ],
                ],
                [
                    'name' => 'Sniper Rifle (ยิงล้มของตำรวจ)',
                    'hdr'  => 'hdr-red',
                    'icon' => 'fas fa-dot-circle',
                    'items' => [
                        'สามารถใช้ได้ในเคสลาก / เคลื่อนย้ายศพทุกรูปแบบ',
                        'สามารถยิงบนพื้นหรือบนฮอ / สามารถยิงตกรถได้ทันทีโดยไม่ต้องรอล้อแตก 4 ล้อ',
                    ],
                ],
                [
                    'name' => 'อาวุธหน่วยงานทั่วไป (Taser, Pump Shotgun, Nightstick)',
                    'hdr'  => 'hdr-purple',
                    'icon' => 'fas fa-shield-alt',
                    'items' => [
                        'สามารถใช้ในการป้องกันตัวหรือสู้กับผู้ต้องหา',
                        'ห้ามใช้อาวุธหน่วยงานนอกเวลาเข้าเวรแม้จะเป็นการป้องกันตัว',
                    ],
                ],
                [
                    'name' => 'ปืน Revolver (ปชช.)',
                    'hdr'  => 'hdr-blue',
                    'icon' => 'fas fa-circle',
                    'items' => [
                        'อนุญาตให้หน่วยงานตำรวจที่มีสามารถใช้ได้ทุกเคส',
                    ],
                ],
            ];
            ?>
            <?php foreach ($weapons as $w): ?>
            <div class="list-card">
                <div class="cat-header <?= $w['hdr'] ?>">
                    <i class="<?= $w['icon'] ?>"></i> <?= htmlspecialchars($w['name']) ?>
                </div>
                <?php foreach ($w['items'] as $idx => $item): ?>
                <div class="list-item">
                    <div class="list-num num-purple"><?= $idx + 1 ?></div>
                    <div class="list-text"><?= htmlspecialchars($item) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <p class="no-result" id="noResult" style="display:none;">ไม่พบรายการที่ตรงกับการค้นหา</p>

    </div>
</div>

<script>
const activeClasses = {
    all:        'active-all',
    laws:       'active-laws',
    discipline: 'active-discipline',
    blacklist:  'active-blacklist',
    rebel:      'active-rebel',
    weapon:     'active-weapon',
};

// Sections that support search
const searchableSections = ['laws', 'discipline'];

function switchTab(key, btn) {
    // Update buttons
    document.querySelectorAll('.tab-btn').forEach(b => b.className = 'tab-btn');
    btn.classList.add(activeClasses[key] || 'active-all');

    // Show/hide search bar
    const searchBox = document.getElementById('searchBox');
    searchBox.style.display = (key === 'all' || searchableSections.includes(key)) ? 'flex' : 'none';
    document.getElementById('lawSearch').value = '';
    document.getElementById('noResult').style.display = 'none';

    if (key === 'all') {
        document.querySelectorAll('.page-section').forEach(s => s.classList.add('active'));
    } else {
        document.querySelectorAll('.page-section').forEach(s => {
            s.classList.toggle('active', s.dataset.section === key);
        });
    }

    // Reset all rows visibility
    document.querySelectorAll('.law-row, .disc-row').forEach(r => r.style.display = '');
    document.querySelectorAll('.cat-section.law-block, .list-card').forEach(s => s.style.display = '');
}

function filterLaws() {
    const q = document.getElementById('lawSearch').value.toLowerCase().trim();
    let anyVisible = false;

    // Filter law rows
    document.querySelectorAll('.cat-section.law-block').forEach(block => {
        const rows = block.querySelectorAll('.law-row');
        let blockVisible = false;
        rows.forEach(row => {
            const match = !q || row.dataset.name.includes(q);
            row.style.display = match ? '' : 'none';
            if (match) blockVisible = true;
        });
        block.style.display = blockVisible ? '' : 'none';
        if (blockVisible) anyVisible = true;
    });

    // Filter discipline rows
    document.querySelectorAll('.disc-row').forEach(row => {
        const match = !q || row.dataset.name.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) anyVisible = true;
    });

    document.getElementById('noResult').style.display = (!q || anyVisible) ? 'none' : 'block';
}

// Init: show all on load
document.querySelectorAll('.page-section').forEach(s => s.classList.add('active'));
</script>

</body>
</html>
