<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();
include 'db.php';

$client_id     = getenv('DISCORD_CLIENT_ID') ?: '1490948398889828362';
$client_secret = getenv('DISCORD_CLIENT_SECRET') ?: 'D_KEkaGklLFjQRWN4DwLzKP9FKpJzHXl';
$redirect_uri  = getenv('DISCORD_REDIRECT_URI') ?: 'https://fxpl-q71i.onrender.com/login.php';
$guild_id      = getenv('DISCORD_GUILD_ID') ?: '1462409196602396830';

function apiRequest($url, $post = null, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // ลดจาก 30 เป็น 15
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);

    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $headers[] = 'Accept: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // ✅ หากเป็น 429 ให้ retry โดยไม่ sleep (ให้ user refresh เอง)
    if ($httpCode === 429) {
        // ลองได้สูงสุด 1 ครั้ง แล้ว redirect ให้ retry
        if (!isset($_SESSION['retry_count'])) {
            $_SESSION['retry_count'] = 0;
        }
        
        if ($_SESSION['retry_count'] < 1) {
            $_SESSION['retry_count']++;
            sleep(3); // wait 3 วิ
            return apiRequest($url, $post, $headers);
        }
        
        die("❌ Discord rate limited - Refresh ไปลองใหม่ (เครื่องขาดแบนน์ชั่วคราว)");
    }

    if ($httpCode >= 400 || $error) {
        error_log("API Error - HTTP $httpCode: " . $error);
        
        if ($httpCode === 401 || $httpCode === 403) {
            die("❌ Unauthorized - ตรวจสอบ Client ID/Secret");
        }
        
        if ($httpCode >= 500) {
            die("❌ Discord Server Error - ลองใหม่ในอีกสั��ครู่");
        }
        
        die("❌ Error: $httpCode - ลองใหม่");
    }

    $data = json_decode($response);
    return $data;
}

// ==================== หน้า Login ====================
if (!isset($_GET['code'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    $_SESSION['retry_count'] = 0; // reset retry count
    
    $auth_url = "https://discord.com/api/oauth2/authorize?" . http_build_query([
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => 'identify guilds.members.read',
        'state'         => $_SESSION['oauth_state']
    ]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Police All Star - Login</title>
    <style>
        body { 
            font-family: 'Kanit', sans-serif; 
            background: linear-gradient(135deg, #070b1a 0%, #1a1f3a 100%);
            color: white; 
            text-align: center; 
            padding-top: 120px;
            margin: 0;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 40px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        h1 { 
            margin: 0 0 10px 0;
            font-size: 2.5rem;
            text-shadow: 0 0 20px rgba(88, 101, 242, 0.5);
        }
        p { 
            margin: 0 0 40px 0;
            color: #aaa;
            font-size: 1.1rem;
        }
        .btn { 
            background: linear-gradient(135deg, #5865F2 0%, #4752c4 100%);
            color: white; 
            padding: 18px 40px; 
            border-radius: 12px; 
            text-decoration: none; 
            font-size: 1.2rem; 
            display: inline-flex; 
            align-items: center; 
            gap: 12px; 
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            box-shadow: 0 8px 25px rgba(88, 101, 242, 0.3);
        }
        .btn:hover { 
            background: linear-gradient(135deg, #4752c4 0%, #364099 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(88, 101, 242, 0.5);
        }
        .btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⭐ Police All Star PD</h1>
        <p>Y Police All Star Y</p>
        <a href="<?= htmlspecialchars($auth_url) ?>" class="btn">🔵 เข้าสู่ระบบด้วย Discord</a>
    </div>
</body>
</html>
<?php exit(); } 

// ==================== Verify State ====================
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? null)) {
    die("❌ Invalid state - CSRF detected");
}

// ==================== Input Validation ====================
$code = filter_var($_GET['code'] ?? '', FILTER_SANITIZE_STRING);
if (!$code) {
    die("❌ No authorization code");
}

// ==================== Token Exchange ====================
$token_data = apiRequest('https://discord.com/api/oauth2/token', [
    "client_id"     => $client_id,
    "client_secret" => $client_secret,
    "grant_type"    => "authorization_code",
    "code"          => $code,
    "redirect_uri"  => $redirect_uri
]);

if (!isset($token_data->access_token)) {
    die("❌ Failed to get access token");
}

$access_token = $token_data->access_token;

// ดึงข้อมูลผู้ใช้
$user = apiRequest('https://discord.com/api/users/@me', null, ['Authorization: Bearer ' . $access_token]);

if (!isset($user->id)) {
    die("❌ Failed to get user info");
}

// ดึง guild member info (optional - ถ้า fail ก็ดำเนินการต่อ)
$guild_member = @apiRequest("https://discord.com/api/users/@me/guilds/$guild_id/member", null, ['Authorization: Bearer ' . $access_token]);

// จัดการชื่อและรูป
$current_name = $guild_member->nick ?? $user->global_name ?? $user->username ?? 'Unknown';
$current_name = filter_var($current_name, FILTER_SANITIZE_STRING);

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
$_SESSION['user_id']   = $user->id;
$_SESSION['user_name'] = $current_name;
$_SESSION['avatar']    = $current_avatar;

header('Location: index.php');
exit();
?>
