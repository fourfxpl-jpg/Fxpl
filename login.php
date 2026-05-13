<?php
ini_set('display_errors', 0); // ปิดในโปรดักชั่น
error_reporting(E_ALL);
session_start();
include 'db.php';

// ใช้ environment variables แทน hardcoded values
// หรือถ้ายังไม่ใช้ .env ให้ใส่ค่าในที่นี้ (แล้วนำไปเก็บใน .env ทีหลัง)
$client_id     = getenv('DISCORD_CLIENT_ID') ?: '1490948398889828362';
$client_secret = getenv('DISCORD_CLIENT_SECRET') ?: 'D_KEkaGklLFjQRWN4DwLzKP9FKpJzHXl';
$redirect_uri  = getenv('DISCORD_REDIRECT_URI') ?: 'https://fxpl-q71i.onrender.com/login.php';
$guild_id      = getenv('DISCORD_GUILD_ID') ?: '1462409196602396830';

function apiRequest($url, $post = null, $headers = []) {
    $maxRetries = 3;
    $retryDelay = 1; // seconds
    
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // ✅ เปิด SSL verification
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true); // ✅ รับ headers

        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        $headers[] = 'Accept: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // ✅ ตรวจสอบ Rate Limit Headers
        $rateLimitRemaining = 0;
        $rateLimitReset = 0;
        if (preg_match('/x-ratelimit-remaining: (\d+)/i', $headerText, $m)) {
            $rateLimitRemaining = (int)$m[1];
        }
        if (preg_match('/x-ratelimit-reset: (\d+)/i', $headerText, $m)) {
            $rateLimitReset = (int)$m[1];
        }

        // ✅ Handle 429 (Rate Limit)
        if ($httpCode === 429) {
            if ($attempt < $maxRetries - 1) {
                sleep($retryDelay);
                $retryDelay *= 2; // Exponential backoff
                continue;
            }
            die("❌ Discord API Rate Limited - กรุณาลองใหม่ในอีกสักครู่ (Error 429)");
        }

        // ✅ ถ้า requests เกือบหมด - sleep ไปเรื่อยๆ
        if ($rateLimitRemaining === 0 && $rateLimitReset > 0) {
            $sleepTime = max(0, $rateLimitReset - time() + 1);
            if ($sleepTime > 0 && $attempt < $maxRetries - 1) {
                sleep(min($sleepTime, 5)); // สูงสุด 5 วินาที
                continue;
            }
        }

        // ✅ Handle 5xx errors (Server error - retry)
        if ($httpCode >= 500 && $attempt < $maxRetries - 1) {
            sleep($retryDelay);
            $retryDelay *= 2;
            continue;
        }

        // Other errors
        if ($httpCode >= 400 || $error) {
            echo "<h2>❌ Discord API Error</h2>";
            echo "<strong>HTTP Code:</strong> $httpCode<br>";
            if ($error) echo "<strong>CURL Error:</strong> " . htmlspecialchars($error) . "<br>";
            echo "<strong>Response:</strong><pre>" . htmlspecialchars($body) . "</pre>";
            exit();
        }

        $data = json_decode($body);
        return $data;
    }

    die("❌ API request failed after $maxRetries attempts");
}

// ==================== หน้า Login ====================
if (!isset($_GET['code'])) {
    // ✅ CSRF Protection: สร้าง state parameter
    if (!isset($_SESSION['oauth_state'])) {
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    }

    $auth_url = "https://discord.com/api/oauth2/authorize?" . http_build_query([
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'response_type' => 'code',
        'scope'         => 'identify guilds.members.read',
        'state'         => $_SESSION['oauth_state'] // ✅ เพิ่ม state
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
    <a href="<?= htmlspecialchars($auth_url) ?>" class="btn">🔵 เข้าสู่ระบบด้วย Discord</a>
</body>
</html>
<?php exit(); } 

// ==================== Verify State (CSRF Protection) ====================
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? null)) {
    die("❌ Invalid state parameter - CSRF attack detected");
}

// ==================== Input Validation ====================
$code = filter_var($_GET['code'] ?? '', FILTER_SANITIZE_STRING);
if (!$code || strlen($code) < 10) {
    die("❌ Invalid authorization code");
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
    die("❌ ไม่สามารถรับ access token จาก Discord ได้");
}

$access_token = $token_data->access_token;

// ดึงข้อมูลผู้ใช้
$user = apiRequest('https://discord.com/api/users/@me', null, ['Authorization: Bearer ' . $access_token]);
$guild_member = apiRequest("https://discord.com/api/users/@me/guilds/$guild_id/member", null, ['Authorization: Bearer ' . $access_token]);

if (!isset($user->id)) {
    die("❌ ไม่สามารถดึงข้อมูลผู้ใช้จาก Discord ได้");
}

// จัดการชื่อและรูป
$current_name = $guild_member->nick ?? $user->global_name ?? $user->username ?? 'Unknown';
$current_name = filter_var($current_name, FILTER_SANITIZE_STRING); // ✅ Sanitize

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

// ✅ Session management - ไม่เก็บ access token ใน $_SESSION
$_SESSION['user_id']   = $user->id;
$_SESSION['user_name'] = $current_name;
$_SESSION['avatar']    = $current_avatar;
// ไม่เก็บ access token ใน session (security risk)
// ถ้าต้องใช้ให้เก็บใน database หรือ server-side storage

header('Location: index.php');
exit();
?>
