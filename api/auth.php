<?php
// idcard/api/auth.php
require_once '../connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://portal.pathumthani.police.go.th');
header('Access-Control-Allow-Credentials: true');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if logged in via SSO sessions
    if (isset($_SESSION['user_id']) && !empty($_SESSION['role'])) {
        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['full_name'] ?? 'Admin',
                'role' => $_SESSION['role']
            ]
        ]);
    }
    else {
        echo json_encode([
            'success' => true,
            'logged_in' => false
        ]);
    }
}
else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>