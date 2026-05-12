<?php
include 'check_login.php';
include 'db.php';

$current_page = basename($_SERVER['PHP_SELF']);

$user_name = 'Guest';

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT user_name FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user && !empty($user['user_name'])) {
        $user_name = $user['user_name'];
    }
}
?>
<nav class="navbar">
    <div class="nav-left">
        <div class="user-info" style="display:flex;align-items:center;gap:10px;">
            <?php if(isset($_SESSION['avatar'])): ?>
                <img src="<?= $_SESSION['avatar'] ?>" style="width:35px;height:35px;border-radius:50%;border:1px solid var(--primary);">
            <?php endif; ?>
            <div>
                <b><?= htmlspecialchars($user_name) ?></b>
                <span style="font-size:11px;color:var(--primary);">ID : <?= $_SESSION['user_id'] ?? '0' ?></span>
            </div>
        </div>
    </div>

    <div class="nav-center">
        <a href="index.php" class="<?= ($current_page=='index.php')?'active':'' ?>">หน้าหลัก</a>
        <a href="rules.php" class="<?= ($current_page=='rules.php')?'active':'' ?>">กฎระเบียบ</a>
        <a href="duty.php" class="<?= ($current_page=='duty.php')?'active':'' ?>">เข้าเวร</a>
        <a href="cases.php" class="<?= ($current_page=='cases.php')?'active':'' ?>">ลงเคส</a>
        <a href="stats.php" class="<?= ($current_page=='stats.php')?'active':'' ?>">สถิติ</a>
    </div>

    <div class="nav-right">
        <button class="theme-toggle">🌙</button>
        <button onclick="showLogoutModal()" class="logout-btn-nav">ออกจากระบบ</button>
    </div>
</nav>

<!-- Logout Modal -->
<div id="logoutModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#0d1529; border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:45px 40px; text-align:center; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.5);">
        <div style="width:72px; height:72px; border:3px solid #0066FF; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:2rem; color:#0066FF; font-weight:bold;">!</div>
        <h2 style="color:white; margin:0 0 10px; font-size:1.4rem; font-family:'Noto Sans Thai',sans-serif;">ยืนยันการออกจากระบบ?</h2>
        <p style="color:#6b7fa3; margin:0 0 30px; font-size:0.95rem; font-family:'Noto Sans Thai',sans-serif;">คุณต้องการเลิกเวรและออกจากระบบใช่หรือไม่?</p>
        <div style="display:flex; gap:12px; justify-content:center;">
            <button onclick="hideLogoutModal()" style="padding:12px 32px; border-radius:10px; border:1px solid rgba(255,255,255,0.15); background:rgba(255,255,255,0.05); color:white; font-size:0.95rem; cursor:pointer; font-family:'Noto Sans Thai',sans-serif; transition:0.2s;">ยกเลิก</button>
            <a href="logout.php" style="padding:12px 32px; border-radius:10px; border:none; background:#ef4444; color:white; font-size:0.95rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; font-family:'Noto Sans Thai',sans-serif; font-weight:600; box-shadow:0 4px 15px rgba(239,68,68,0.35); transition:0.2s;">ใช่, ออกจากระบบ</a>
        </div>
    </div>
</div>

<script>
function showLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.style.display = 'flex';
}
function hideLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.style.display = 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('logoutModal').addEventListener('click', function(e) {
        if (e.target === this) hideLogoutModal();
    });
});
</script>
