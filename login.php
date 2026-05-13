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
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $headers[] = 'Accept: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    $data = json_decode($response);

    // Debug สำหรับ Error
    if ($httpCode >= 400 || $error || !$data) {
        echo "<h2>❌ Discord API Error</h2>";
        echo "<strong>HTTP Code:</strong> $httpCode<br>";
        if ($error) echo "<strong>CURL Error:</strong> " . htmlspecialchars($error) . "<br>";
        echo "<strong>Response:</strong><pre>" . htmlspecialchars($response) . "</pre>";
        exit();
    }

    return $data;
}

// ==================== หน้า Login ====================
if (!isset($_GET['code'])) {
    $auth_url = "https://discord.com/api/oauth2/authorize?" . http_build_query([
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => 'identify guilds.members.read'
    ]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Police All Star - Login</title>
    <style>
        body { font-family: 'Kanit', sans-serif; background:#070b1a; color:white; text-align:center; padding-top:120px; }
        .btn { background:#5865F2; color:white; padding:16px 36px; border-radius:12px; text-decoration:none; font-size:1.3rem; display:inline-flex; align-items:center; gap:12px; }
        .btn:hover { background:#4752c4; transform:scale(1.05); }
    </style>
</head>
<body>
    <h1>Police All Star PD</h1>
    <p>Y Police All Star Y</p>
    <a href="<?= $auth_url ?>" class="btn">🔵 เข้าสู่ระบบด้วย Discord</a>
</body>
</html>
<?php exit(); } 

// ==================== Callback ====================
$token_data = apiRequest('https://discord.com/api/oauth2/token', [
    "client_id"     => $client_id,
    "client_secret" => $client_secret,
    "grant_type"    => "authorization_code",
    "code"          => $_GET['code'],
    "redirect_uri"  => $redirect_uri
]);

$access_token = $token_data->access_token;

// ดึงข้อมูลผู้ใช้
$user = apiRequest('https://discord.com/api/users/@me', null, ['Authorization: Bearer ' . $access_token]);
$guild_member = apiRequest("https://discord.com/api/users/@me/guilds/$guild_id/member", null, ['Authorization: Bearer ' . $access_token]);

if (!isset($user->id)) {
    die("❌ ไม่สามารถดึงข้อมูลผู้ใช้จาก Discord ได้");
}

// จัดการชื่อและรูป
$current_name = $guild_member->nick ?? $user->global_name ?? $user->username ?? 'Unknown';
$current_avatar = "https://cdn.discordapp.com/embed/avatars/0.png";
if (!empty($user->avatar)) {
    $ext = (strpos($user->avatar, 'a_') === 0) ? 'gif' : 'png';
    $current_avatar = "https://cdn.discordapp.com/avatars/{$user->id}/{$user->avatar}.{$ext}";
}

// บันทึกข้อมูล
$stmt = $conn->prepare("INSERT INTO users (user_id, user_name, avatar) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                        user_name = VALUES(user_name), 
                        avatar = VALUES(avatar)");
$stmt->execute([$user->id, $current_name, $current_avatar]);

// Session
$_SESSION['user_id']      = $user->id;
$_SESSION['user_name']    = $current_name;
$_SESSION['avatar']       = $current_avatar;
$_SESSION['access_token'] = $access_token;

header('Location: index.php');
exit();
?>
