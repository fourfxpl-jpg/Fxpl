<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();
include 'db.php';

$client_id     = '1490948398889828362';
$client_secret = 'D_KEkaGklLFjQRWN4DwLzKP9FKpJzHXl';
$redirect_uri  = 'https://c80a966e-c31b-4f1f-979c-caba6d4b8184-00-1ei7y7yia7xmz.pike.replit.dev/login.php';
$guild_id      = '1462409196602396830';

function apiRequest($url, $post = null, $headers = [], $attempt = 0) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $headers[] = 'Accept: application/json';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 429 && $attempt < 3) {
        sleep(5);
        return apiRequest($url, $post, $headers, $attempt + 1);
    }

    return json_decode($response);
}

if (!isset($_GET['code'])) {
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
    <title>POLICE ALL STAR PD — AUTHENTICATION</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            max-width: 480px;
            width: 100%;
            padding: 64px 48px;
            text-align: center;
            background: linear-gradient(160deg, var(--surface), oklch(from var(--primary) 0.1 0.05 250));
            border: 1px solid var(--border-mid);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: 'AUTHENTICATION REQUIRED';
            position: absolute;
            top: 20px; left: 0; right: 0;
            font-family: 'Rajdhani';
            font-size: 10px;
            letter-spacing: 4px;
            color: var(--primary);
            opacity: 0.5;
        }
        h1 {
            font-size: 2.8rem;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--text), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .subtitle {
            color: var(--text-muted);
            font-family: 'Rajdhani';
            font-weight: 700;
            letter-spacing: 2px;
            margin-bottom: 48px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-card card">
        <h1>POLICE ALL STAR</h1>
        <div class="subtitle">BTPD DISPATCH SYSTEM V2.0</div>
        
        <a href="<?= htmlspecialchars($auth_url) ?>" class="btn btn-primary" style="width:100%; font-size:16px; padding:18px; background:#5865F2;">
            <i class="fab fa-discord" style="margin-right:12px;"></i> ACCESS WITH DISCORD
        </a>
        
        <div style="margin-top:32px; font-family:'Rajdhani'; font-size:11px; color:var(--text-muted); letter-spacing:1px;">
            SECURE ACCESS PORTAL — AUTHORIZED PERSONNEL ONLY
        </div>
    </div>
</body>
</html>
<?php exit(); } 

// ==================== Verify State ====================
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? null)) {
    die("❌ Invalid state");
}

$code = filter_var($_GET['code'] ?? '', FILTER_SANITIZE_STRING);
$token_data = apiRequest('https://discord.com/api/oauth2/token', [
    "client_id"     => $client_id,
    "client_secret" => $client_secret,
    "grant_type"    => "authorization_code",
    "code"          => $code,
    "redirect_uri"  => $redirect_uri
]);

if (!isset($token_data->access_token)) die("❌ Token failed");

$access_token = $token_data->access_token;
$user = apiRequest('https://discord.com/api/users/@me', null, ['Authorization: Bearer ' . $access_token]);
$guild_member = @apiRequest("https://discord.com/api/users/@me/guilds/$guild_id/member", null, ['Authorization: Bearer ' . $access_token]);

$current_name = $guild_member->nick ?? $user->global_name ?? $user->username ?? 'Unknown';
$current_avatar = "https://cdn.discordapp.com/embed/avatars/0.png";
if (!empty($user->avatar)) {
    $ext = (strpos($user->avatar, 'a_') === 0) ? 'gif' : 'png';
    $current_avatar = "https://cdn.discordapp.com/avatars/{$user->id}/{$user->avatar}.{$ext}";
}

$stmt = $conn->prepare("INSERT INTO users (user_id, user_name, avatar) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_name = VALUES(user_name), avatar = VALUES(avatar)");
$stmt->execute([$user->id, $current_name, $current_avatar]);

$_SESSION['user_id']   = $user->id;
$_SESSION['user_name'] = $current_name;
$_SESSION['avatar']    = $current_avatar;

header('Location: index.php');
exit();
?>
