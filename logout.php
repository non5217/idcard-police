<?php
// =============================================================
// idcard/logout.php
// =============================================================
// ลบ Session ของ idcard เอง แล้วส่งไปออกจากระบบกลางของ Portal (SSO Logout)
// =============================================================

// 0. ตั้งค่า Session Cookie ให้ตรงกับตอน Login
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/idcard/',
    'domain' => '.pathumthani.police.go.th',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ล้าง Session ของ idcard เอง
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 2. ส่ง User ไปที่ SSO Logout กลางของ Portal
//    Portal จะล้าง Session ของตัวเองให้ทั้งหมด แล้วเด้งกลับมาที่ idcard/login.php
$sso_logout_url = 'https://portal.pathumthani.police.go.th/portal/sso_logout.php?redirect='
    . urlencode('https://portal.pathumthani.police.go.th/idcard/login.php');

header("Location: " . $sso_logout_url);
exit();
?>