<?php
// idcard/admin_auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth_utils.php';

// 1. เช็คว่า Login ยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 🟢 1.2 เช็คสถานะ Single Sign-Out (ถ้าไม่มี Cookie ของ Portal แปลว่าโดนล็อกเอาท์จากหน้าหลักไปแล้ว)
if (empty($_COOKIE['PORTALSESSID'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// 🟢 1.5 เช็คและต่ออายุ Token หากจำเป็น (Refresh Token)
// หากล้มเหลว (เช่นกด Revoke มาจาก Portal) ให้ออกไปหน้า Login
if (!refreshTokenIfNeeded()) {
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