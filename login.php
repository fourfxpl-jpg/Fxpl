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
    $maxRetries = 5;
    $retryDelay = 2; // เริ่มต้น 2 วินาที
    
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

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
        $retryAfter = 0;

        if (preg_match('/x-ratelimit-remaining:\s*(\d+)/i', $headerText, $m)) {
            $rateLimitRemaining = (int)$m[1];
        }
        if (preg_match('/x-ratelimit-reset:\s*(\d+\.?\d*)/i', $headerText, $m)) {
            $rateLimitReset = (int)$m[1];
        }
        if (preg_match('/retry-after:\s*(\d+\.?\d*)/i', $headerText, $m)) {
            $retryAfter = (int)ceil($m[1]);
        }

        // ✅ Handle 429 (Rate Limit)
        if ($httpCode === 429) {
            $waitTime = max($retryAfter, 5); // อย่างน้อย 5 วินาที
            
            if ($attempt < $maxRetries - 1) {
                error_log("Rate limited on attempt " . ($attempt + 1) . ". Waiting $waitTime seconds...");
                sleep($waitTime);
                $retryDelay = $waitTime;
                continue;
            } else {
                die("❌ Discord API Rate Limited หลังจากลองหลายครั้ง - กรุณาลองใหม่ในอีก 5 นาที");
            }
        }

        // ✅ Handle Connection Timeout และ Temp Errors
        if ($httpCode >= 500 || $error) {
            if ($attempt < $maxRetries - 1) {
                error_log("Server error $httpCode or connection error. Retrying in " . $retryDelay . " seconds...");
                sleep($retryDelay);
                $retryDelay *= 2; // Exponential backoff
                continue;
            }
        }

        // ✅ หากใกล้จะหมด rate limit - sleep
        if ($rateLimitRemaining <= 1 && $rateLimitReset > 0) {
            $sleepTime = max(1, $rateLimitReset - time() + 1);
            if ($sleepTime > 0 && $sleepTime <= 10) {
                error_log("Rate limit nearly exhausted. Sleeping $sleepTime seconds...");
                sleep($sleepTime);
                continue;
            }
        }

        // ✅ Check for errors
        if ($httpCode >= 400) {
            // อย่าแสดงข้อมูล API response ให้ผู้ใช้เห็น
            error_log("Discord API Error - HTTP $httpCode: " . substr($body, 0, 200));
            
            if ($httpCode === 401 || $httpCode === 403) {
                die("❌ Unauthorized - กรุณาลงชื่อเข้าสู่ระบบใหม่");
            }
            
            if ($attempt < $maxRetries - 1 && $httpCode >= 500) {
                sleep($retryDelay);
                $retryDelay *= 2;
                continue;
            }
            
            die("❌ Discord API Error ($httpCode) - กรุณาลองใหม่");
        }

        if ($error) {
            error_log("CURL Error: $error");
            if ($attempt < $maxRetries - 1) {
                sleep($retryDelay);
                $retryDelay *= 2;
                continue;
            }
            die("❌ Connection error - กรุณาลองใหม่");
        }

        $data = json_decode($body);
        return $data;
    }

    die("❌ API request failed after $maxRetries attempts");
}

// ==================== หน้า Login ====================
if (!isset($_GET['code'])) {
    if (!isset($_SESSION['oauth_state'])) {
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    }

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
            background:#070b1a; 
            color:white; 
            text-align:center; 
            padding-top:120px; 
        }
        .btn { 
            background:#5865F2; 
            color:white; 
            padding:16px 36px; 
            border-radius:12px; 
            text-decoration:none; 
            font-size:1.3rem; 
            display:inline-flex; 
            align-items:center; 
            gap:12px; 
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover { 
            background:#4752c4; 
            transform:scale(1.05); 
        }
        .loading {
            display: none;
            color: #888;
            margin-top: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <h1>Police All Star PD</h1>
    <p>Y Police All Star Y</p>
    <a href="<?= htmlspecialchars($auth_url) ?>" class="btn" onclick="showLoading()">🔵 เข้าสู่ระบบด้วย Discord</a>
    <div class="loading" id="loading">กำลังเชื่อมต่อ Discord...</div>
    <script>
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }
    </script>
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

if (!isset($user->id)) {
    die("❌ ไม่สามารถดึงข้อมูลผู้ใช้จาก Discord ได้");
}

// ดึง guild member info
$guild_member = apiRequest("https://discord.com/api/users/@me/guilds/$guild_id/member", null, ['Authorization: Bearer ' . $access_token]);

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

// Session management
$_SESSION['user_id']   = $user->id;
$_SESSION['user_name'] = $current_name;
$_SESSION['avatar']    = $current_avatar;

header('Location: index.php');
exit();
?>
