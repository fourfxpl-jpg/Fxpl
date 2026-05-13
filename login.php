<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();
include 'db.php';

$client_id     = getenv('DISCORD_CLIENT_ID') ?: '1490948398889828362';
$client_secret = getenv('DISCORD_CLIENT_SECRET') ?: 'D_KEkaGklLFjQRWN4DwLzKP9FKpJzHXl';
$redirect_uri  = getenv('DISCORD_REDIRECT_URI') ?: 'https://c80a966e-c31b-4f1f-979c-caba6d4b8184-00-1ei7y7yia7xmz.pike.replit.dev/login.php';
$guild_id      = getenv('DISCORD_GUILD_ID') ?: '1462409196602396830';

// ✅ ใช้ proxy เพื่อหลีก rate limit
$proxy_list = [
    'http://proxy.example.com:8080', // ใส่ proxy ของคุณ (ถ้ามี)
];

function apiRequest($url, $post = null, $headers = [], $attempt = 0) {
    global $proxy_list;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);
    
    // ✅ ใช้ User-Agent เพื่อหลีก block
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

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

    // ✅ Handle 429 - แทนจะ die ให้ retry ด้วยการ wait นานขึ้น
    if ($httpCode === 429) {
        if ($attempt < 3) {
            $waitTime = (2 ** $attempt) * 5; // 5s, 10s, 20s
            error_log("Rate limit hit. Attempt " . ($attempt + 1) . " - waiting $waitTime seconds");
            sleep($waitTime);
            return apiRequest($url, $post, $headers, $attempt + 1);
        }
        // ถ้า retry 3 ครั้งแล้วยัง 429 - ให้ redirect กลับหน้า login
        $_SESSION['rate_limited'] = time();
        header('Location: login.php');
        exit();
    }

    if ($httpCode >= 400 || $error) {
        error_log("API Error - HTTP $httpCode: " . $error);
        
        if ($httpCode === 401 || $httpCode === 403) {
            die("❌ Unauthorized - ตรวจสอบ Discord Credentials");
        }
        
        if ($httpCode >= 500) {
            // Server error - retry หลังจาก wait
            if ($attempt < 2) {
                sleep(5);
                return apiRequest($url, $post, $headers, $attempt + 1);
            }
            die("❌ Discord Server Error - ลองใหม่ในอีกสักครู่");
        }
        
        die("❌ Error: HTTP $httpCode");
    }

    $data = json_decode($response);
    return $data;
}

// ==================== หน้า Login ====================
if (!isset($_GET['code'])) {
    // ✅ แสดง message ถ้า rate limited เมื่อเร็ว ๆ นี้
    $rate_limited_msg = '';
    if (isset($_SESSION['rate_limited'])) {
        $time_since = time() - $_SESSION['rate_limited'];
        if ($time_since < 120) { // ถ้าน้อยกว่า 2 นาที
            $rate_limited_msg = '<p style="color: #ff6b6b; margin: 20px 0;">⏳ Discord ยังอยู่ในสถานะจำกัด - รอสักครู่...</p>';
        } else {
            unset($_SESSION['rate_limited']);
        }
    }
    
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Kanit', sans-serif; 
            background: linear-gradient(135deg, #070b1a 0%, #1a1f3a 100%);
            color: white; 
            text-align: center; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 450px;
            padding: 50px 40px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(88, 101, 242, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        h1 { 
            margin: 0 0 10px 0;
            font-size: 2.8rem;
            text-shadow: 0 0 20px rgba(88, 101, 242, 0.6);
            background: linear-gradient(135deg, #5865F2, #7289DA);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle { 
            margin: 0 0 35px 0;
            color: #aaa;
            font-size: 1rem;
        }
        
        .rate-limit-msg {
            color: #ff6b6b;
            margin: 20px 0;
            font-size: 0.95rem;
            padding: 12px;
            background: rgba(255, 107, 107, 0.1);
            border-radius: 8px;
            border-left: 3px solid #ff6b6b;
        }
        
        .btn { 
            background: linear-gradient(135deg, #5865F2 0%, #4752c4 100%);
            color: white; 
            padding: 16px 36px; 
            border-radius: 12px; 
            text-decoration: none; 
            font-size: 1.1rem; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            gap: 12px; 
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            box-shadow: 0 8px 25px rgba(88, 101, 242, 0.3);
            font-weight: 600;
            width: 100%;
        }
        
        .btn:hover:not(:disabled) { 
            background: linear-gradient(135deg, #4752c4 0%, #364099 100%);
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(88, 101, 242, 0.5);
        }
        
        .btn:active:not(:disabled) {
            transform: translateY(-1px);
        }
        
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .loading {
            display: none;
            margin-top: 20px;
            color: #5865F2;
            font-size: 0.9rem;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(88, 101, 242, 0.3);
            border-radius: 50%;
            border-top-color: #5865F2;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>⭐ Police All Star PD</h1>
        <p class="subtitle">Y Police All Star Y</p>
        
        <?php if ($rate_limited_msg): ?>
            <div class="rate-limit-msg">
                ⏳ Discord ยังอยู่ในสถานะจำกัด - รอสักครู่แล้วลองใหม่
            </div>
        <?php endif; ?>
        
        <a href="<?= htmlspecialchars($auth_url) ?>" class="btn" onclick="startLogin(event)">
            <span id="btn-text">🔵 เข้าสู่ระบบด้วย Discord</span>
        </a>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            กำลังเชื่อมต่อ...
        </div>
    </div>
    
    <script>
        function startLogin(e) {
            const btn = e.target.closest('.btn');
            const loadingEl = document.getElementById('loading');
            
            btn.style.display = 'none';
            loadingEl.style.display = 'block';
            
            setTimeout(() => {
                window.location.href = btn.href;
            }, 300);
        }
    </script>
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

// ดึง guild member info (optional)
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
