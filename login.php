<?php
session_start(); // ← เพิ่มบรรทัดนี้
include 'db.php';
// --- ข้อมูลจาก Discord Developer Portal ---
// 1. ตรวจสอบว่า Client ID ตรงกับในหน้าเว็บ Discord หรือไม่
$client_id = '1490948398889828362';

// 2. แนะนำให้กด "Reset Secret" ใน Discord Portal แล้วเอารหัสใหม่มาใส่ตรงนี้
$client_secret = 'h1CQbm4H6tTP_8QC4vpZtSXQQTVIM4P-'; 

// 3. แก้บรรทัดนี้ให้ตรงกับ Domain ที่คุณใช้อยู่ (fxpl-production)
$redirect_uri = 'https://fxpl-q71i.onrender.com/login.php';

$guild_id      = '1462409196602396830'; 
// ฟังก์ชันสำหรับส่งข้อมูลไปหา Discord API
function apiRequest($url, $post=false, $headers=array()) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    if($post) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    $headers[] = 'Accept: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    return json_decode($response);
}

// 1. ตรวจสอบว่ามีการส่ง Code กลับมาหรือไม่
if(!isset($_GET['code'])) {
    // กำหนด Scope: identify (ดูโปรไฟล์) และ guilds.members.read (ดูชื่อเล่นในเซิร์ฟเวอร์)
    $auth_url = "https://discord.com/api/oauth2/authorize?client_id=".$client_id."&redirect_uri=".urlencode($redirect_uri)."&response_type=code&scope=identify+guilds.members.read";
?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <title>Police All Star - Login</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body { display:flex; align-items:center; justify-content:center; height:100vh; background:#070b1a; font-family: 'Kanit', sans-serif; margin:0; }
            .login-box { max-width:400px; padding:40px; border-radius:20px; background:#161925; text-align:center; border:1px solid rgba(255,255,255,0.1); }
            h1 { color:#0066FF; margin-bottom:10px; }
            p { color:#a1a1a1; margin-bottom:30px; }
            .btn-discord { background:#5865F2; text-decoration:none; display:inline-flex; align-items:center; gap:10px; padding:12px 24px; border-radius:10px; color:white; font-weight:bold; transition: 0.3s; }
            .btn-discord:hover { background:#4752c4; transform: scale(1.05); }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>Police All Star PD</h1>
            <p>Y Police All Star Y</p>
            <a href="<?= $auth_url ?>" class="btn-discord">
                <i class="fab fa-discord" style="font-size:1.5rem;"></i> เข้าสู่ระบบด้วย Discord
            </a>
        </div>
    </body>
    </html>
<?php
    exit();
}

// 2. แลกเปลี่ยน Code เป็น Access Token
$token = apiRequest('https://discord.com/api/oauth2/token', [
    "client_id" => $client_id,
    "client_secret" => $client_secret,
    "grant_type" => "authorization_code",
    "code" => $_GET['code'],
    "redirect_uri" => $redirect_uri
]);

if(isset($token->error)) {
    die("Error: " . $token->error_description);
}

// 3. ดึงข้อมูลจาก Discord API
// 3.1 ดึงข้อมูล Profile พื้นฐาน
$user = apiRequest('https://discord.com/api/users/@me', false, ['Authorization: Bearer ' . $token->access_token]);

// 3.2 ดึงข้อมูลสมาชิกจากเซิร์ฟเวอร์เฉพาะ
$guild_member = apiRequest("https://discord.com/api/users/@me/guilds/$guild_id/member", false, ['Authorization: Bearer ' . $token->access_token]);

// --- จัดการชื่อที่จะใช้แสดงผล ---
// ใช้ชื่อในเซิร์ฟเท่านั้น
if (isset($guild_member->nick) && !empty($guild_member->nick)) {
    $current_name = $guild_member->nick;
} else {
    die("❌ กรุณาตั้งชื่อใน Discord Server ก่อนเข้าใช้งาน");
}
// --- จัดการรูปโปรไฟล์ (รองรับ GIF) ---
$current_avatar = "https://cdn.discordapp.com/embed/avatars/0.png"; // รูปพื้นฐาน
if (isset($user->id) && isset($user->avatar)) {
    $ext = (strpos($user->avatar, 'a_') === 0) ? 'gif' : 'png';
    $current_avatar = "https://cdn.discordapp.com/avatars/{$user->id}/{$user->avatar}.{$ext}";
}

// 4. บันทึกหรืออัปเดตข้อมูลลง Database ทันที
$stmt = $conn->prepare("INSERT INTO users (user_id, user_name, avatar) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                        user_name = VALUES(user_name), 
                        avatar = VALUES(avatar)");
$stmt->execute([$user->id, $current_name, $current_avatar]);

// 5. บันทึกข้อมูลลง Session เพื่อใช้ในหน้าอื่นๆ
$_SESSION['user_id']   = $user->id;
$_SESSION['user_name'] = $current_name;
$_SESSION['avatar']    = $current_avatar;
$_SESSION['access_token'] = $token->access_token;

// 6. ส่งไปหน้า Dashboard หลัก
header('Location: index.php');
exit();
?>
