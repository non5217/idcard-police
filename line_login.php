<?php
// idcard/line_login.php
require_once 'connect.php';
require_once 'env_loader.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure user is in a public track_status session
if (!isset($_SESSION['id_card_public'])) {
    header("Location: index.php");
    exit();
}

$channel_id = $_ENV['LINE_CHANNEL_ID'] ?? '';
$callback_url = $_ENV['LINE_LOGIN_CALLBACK'] ?? '';

if (empty($channel_id) || empty($callback_url)) {
    die("LINE Login is not configured. Please contact administrator.");
}

// Create state for security
$state = bin2hex(random_bytes(16));
$_SESSION['line_login_state'] = $state;

$url = "https://access.line.me/oauth2/v2.1/authorize?" . http_build_query([
    'response_type' => 'code',
    'client_id' => $channel_id,
    'redirect_uri' => $callback_url,
    'state' => $state,
    'scope' => 'profile openid',
    'nonce' => bin2hex(random_bytes(16))
]);

header("Location: " . $url);
exit();
