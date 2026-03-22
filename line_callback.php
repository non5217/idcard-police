<?php
// idcard/line_callback.php
require_once 'connect.php';
require_once 'env_loader.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$saved_state = $_SESSION['line_login_state'] ?? '';

if (!$code || $state !== $saved_state) {
    die("Invalid request or state mismatch.");
}

$channel_id = $_ENV['LINE_CHANNEL_ID'] ?? '';
$channel_secret = $_ENV['LINE_CHANNEL_SECRET'] ?? '';
$callback_url = $_ENV['LINE_LOGIN_CALLBACK'] ?? '';

// 1. Exchange code for access token
$ch = curl_init("https://api.line.me/oauth2/v2.1/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $callback_url,
    'client_id' => $channel_id,
    'client_secret' => $channel_secret
]));

$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);

if (!isset($data['access_token'])) {
    die("Failed to get access token: " . ($data['error_description'] ?? 'Unknown error'));
}

$access_token = $data['access_token'];

// 2. Get User Profile (to get userId)
$ch = curl_init("https://api.line.me/v2/profile");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token
]);

$profile_response = curl_exec($ch);
curl_close($ch);
$profile = json_decode($profile_response, true);

if (!isset($profile['userId'])) {
    die("Failed to get LINE profile.");
}

$line_user_id = $profile['userId'];
$id_card_number = $_SESSION['id_card_public'] ?? '';

if (!$id_card_number) {
    die("Session expired. Please start over.");
}

// 3. Save to database
try {
    $stmt = $conn->prepare("INSERT INTO idcard_line_subscriptions (id_card_number, line_user_id, is_active) 
                            VALUES (?, ?, 1) 
                            ON DUPLICATE KEY UPDATE is_active = 1, line_user_id = ?");
    $stmt->execute([$id_card_number, $line_user_id, $line_user_id]);

    $_SESSION['line_link_success'] = true;
    header("Location: track_status.php");
    exit();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
