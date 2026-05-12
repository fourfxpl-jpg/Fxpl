<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db.php';

// --- ข้อมูลจาก Discord Developer Portal ---
$client_id     = '1490948398889828362';
$client_secret = 'D_KEkaGklLFjQRWN4DwLzKP9FKpJzHXl'; 
$redirect_uri  = 'https://fxpl-q71i.onrender.com/login.php';
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

// --- จุดแก้บัคหลัก: ตรวจสอบว่าได้ Token จริงหรือไม่ก่อนไปต่อ ---
if(!isset($token_data->access_token)) {
    die("Error: " . ($token_data->error_description ?? "Could not get access token"));
}

$access_token = $token_data->access_token;

// 3. ดึงข้อมูลจาก Discord API
// 3.1 ดึงข้อมูล Profile พื้นฐาน
$user = apiRequest('https://discord.com/api/users/@me', false, ['Authorization: Bearer ' . $access_token]);

// 3.2 ดึงข้อมูลสมาชิกจากเซิร์ฟเวอร์เฉพาะ
$guild_member = apiRequest("https://discord.com/api/users/@me/guilds/$guild_id/member", false, ['Authorization: Bearer ' . $access_token]);

// --- ตรวจสอบว่าดึงข้อมูล User สำเร็จไหม ---
if (!isset($user->id)) {
    die("❌ ไม่สามารถดึงข้อมูล User ได้");
}

// --- จัดการชื่อที่จะใช้แสดงผล ---
if (isset($guild_member->nick) && !empty($guild_member->nick)) {
    $current_name = $guild_member->nick;
} elseif (isset($user->global_name)) {
    $current_name = $user->global_name;
} else {
    $current_name = $user->username;
}

// --- จัดการรูปโปรไฟล์ ---
$current_avatar = "https://cdn.discordapp.com/embed/avatars/0.png"; 
if (isset($user->avatar)) {
    $ext = (strpos($user->avatar, 'a_') === 0) ? 'gif' : 'png';
    $current_avatar = "https://cdn.discordapp.com/avatars/{$user->id}/{$user->avatar}.{$ext}";
}

// 4. บันทึกหรืออัปเดตข้อมูลลง Database
$stmt = $conn->prepare("INSERT INTO users (user_id, user_name, avatar) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                        user_name = VALUES(user_name), 
                        avatar = VALUES(avatar)");
$stmt->execute([$user->id, $current_name, $current_avatar]);

// 5. บันทึกข้อมูลลง Session
$_SESSION['user_id']      = $user->id;
$_SESSION['user_name']    = $current_name;
$_SESSION['avatar']       = $current_avatar;
$_SESSION['access_token'] = $access_token;

// 6. ส่งไปหน้า Dashboard
header('Location: index.php');
exit();
?>
