<?php
// idcard/admin_auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. เช็คว่า Login ยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. เช็คว่าเป็น Staff ไหม (Role ต้องไม่ว่างเปล่า และไม่ใช่ User ธรรมดา)
if (empty($_SESSION['role']) || $_SESSION['role'] === 'User') {
    http_response_code(403);
    die("⛔ Access Denied: คุณไม่มีสิทธิ์เข้าถึงส่วนนี้");
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>