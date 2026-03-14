<?php
// idcard/logout.php
require_once 'env_loader.php'; // โหลด Session + CONSOLE_API_URL

// ============================================================
// 1. แจ้ง Portal ให้ Logout (Revoke Server-side Session ด้วย)
//    ส่ง Cookie PORTALSESSID ไปด้วยเพื่อให้ Portal รู้ว่า Session ไหน
// ============================================================
if (isset($_COOKIE['PORTALSESSID'])) {
    $ch = curl_init(CONSOLE_API_URL . '?action=logout');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // รอแค่ 5 วินาที ไม่งั้นค้าง
    // ส่ง Cookie PORTALSESSID ไปด้วย เพื่อให้ Portal รู้ vs Session ไหน
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: PORTALSESSID=' . $_COOKIE['PORTALSESSID']
    ]);
    // ส่ง CSRF Token ไปด้วย (ถ้ามีเก็บไว้ใน Session)
    $csrfToken = $_SESSION['portal_csrf_token'] ?? '';
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['csrf_token' => $csrfToken]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cookie: PORTALSESSID=' . $_COOKIE['PORTALSESSID']
    ]);
    curl_exec($ch);
    curl_close($ch);

    // ลบ Cookie Portal ออกจาก Browser ด้วย
    setcookie('PORTALSESSID', '', time() - 3600, '/');
}

// ============================================================
// 2. ล้าง Session ของ idcard เอง
// ============================================================
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// ============================================================
// 3. ดีดกลับไปหน้าหลักของ Portal
// ============================================================
header("Location: https://portal.pathumthani.police.go.th/");
exit();
?>