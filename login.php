<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db.php';

$client_id     = '1490948398889828362';
$client_secret = 'D_KEkaGklLFjQRWN4DwLzKP9FKpJzHXl'; 
$redirect_uri  = 'https://fxpl-q71i.onrender.com/login.php';
$guild_id      = '1462409196602396830'; 

function apiRequest($url, $post = null, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    
    $headers[] = 'Accept: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        error_log("Discord API Error: HTTP $httpCode - $response");
    }

    return json_decode($response);
}

// 1. ยังไม่มี Code → แสดงปุ่ม Login
if (!isset($_GET['code'])) {
    $auth_url = "https://discord.com/api/oauth2/authorize?" . http_build_query([
        "client_id"     => $client_id,
        "redirect_uri"  => $redirect_uri,
        "response_type" => "code",
        "scope"         => "identify guilds.members.read"
    ]);
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
            .login-box { max-width:420px; padding:50px 40px; border-radius:20px; background:#161925; text-align:center; border:1px solid rgba(255,255,255,0.1); }
            h1 { color:#0066FF; margin-bottom:8px; }
            p { color:#a1a1a1; margin-bottom:35px; }
            .btn-discord { background:#5865F2; text-decoration:none; display:inline-flex; align-items:center; gap:12px; padding:14px 32px; border-radius:12px; color:white; font-weight:bold; font-size:1.1rem; transition:0.3s; }
            .btn-discord:hover { background:#4752c4; transform: scale(1.05); }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>Police All Star PD</h1>
            <p>Y Police All Star Y</p>
            <a href="<?= $auth_url ?>" class="btn-discord">
                <i class="fab fa-discord" style="font-size:1.8rem;"></i> 
                เข้าสู่ระบบด้วย Discord
            </a>
        </div>
    </body>
    </html>
<?php
    exit();
}

// ==================== แลกเปลี่ยน Code เป็น Token ====================
$token_data = apiRequest('https://discord.com/api/oauth2/token', [
    "client_id"     => $client_id,
    "client_secret" => $client_secret,
    "grant_type"    => "authorization_code",
    "code"          => $_GET['code'],
    "redirect_uri"  => $redirect_uri
]);

if (!isset($token_data->access_token)) {
    die("❌ ไม่สามารถรับ Access Token ได้<br><pre>" . htmlspecialchars(print_r($token_data, true)) . "</pre>");
}

$access_token = $token_data->access_token;

// ==================== ดึงข้อมูลผู้ใช้ ====================
$user = apiRequest('https://discord.com/api/users/@me', null, [
    'Authorization: Bearer ' . $access_token
]);

$guild_member = apiRequest("https://discord.com/api/users/@me/guilds/$guild_id/member", null, [
    'Authorization: Bearer ' . $access_token
]);

if (!isset($user->id)) {
    die("❌ ไม่สามารถดึงข้อมูล Discord ได้");
}

// ==================== จัดการชื่อและรูป ====================
$current_name = $guild_member->nick ?? $user->global_name ?? $user->username ?? 'Unknown';

$current_avatar = "https://cdn.discordapp.com/embed/avatars/0.png";
if (!empty($user->avatar)) {
    $ext = (strpos($user->avatar, 'a_') === 0) ? 'gif' : 'png';
    $current_avatar = "https://cdn.discordapp.com/avatars/{$user->id}/{$user->avatar}.{$ext}";
}

// ==================== บันทึกข้อมูล ====================
$stmt = $conn->prepare("INSERT INTO users (user_id, user_name, avatar) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                        user_name = VALUES(user_name), 
                        avatar = VALUES(avatar)");
$stmt->execute([$user->id, $current_name, $current_avatar]);

// ==================== Session ====================
$_SESSION['user_id']      = $user->id;
$_SESSION['user_name']    = $current_name;
$_SESSION['avatar']       = $current_avatar;
$_SESSION['access_token'] = $access_token;

// ==================== Redirect ====================
header('Location: index.php');
exit();
?>
