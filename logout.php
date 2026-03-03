<?php
// idcard/logout.php
session_start();

// 1. ล้างข้อมูล Session ทั้งหมด
$_SESSION = [];

// 2. ทำลาย Session ทิ้ง
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 3. ดีดกลับไปที่หน้าหลัก// ดีดกลับไปหน้าหลักของ Portal
header("Location: https://portal.pathumthani.police.go.th/");
exit();
?>
