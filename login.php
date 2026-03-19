<?php
// idcard/login.php
// บังคับความปลอดภัยของ Session Cookie สำหรับ Cross-Subdomain
session_set_cookie_params([
    'lifetime' => 86400, // 24 ชั่วโมง
    'path' => '/idcard/', // จำกัด path สำหรับ idcard subdirectory
    'domain' => '.pathumthani.police.go.th', // ให้ทำงานข้าม subdomain
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax' // ให้ทำงานข้าม site
]);

require_once 'env_loader.php'; // Load environment variables

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ไม่ต้อง session_start() ซ้ำ เพราะ config/connect เปิดให้แล้ว

// 1. เช็คก่อนว่า Login อยู่แล้วหรือเปล่า? (ถ้าเป็น Admin อยู่แล้ว ให้ไปหน้า Dashboard เลย)
if (isset($_SESSION['user_id']) && !empty($_SESSION['role']) && $_SESSION['role'] !== 'User') {
    header("Location: admin_dashboard.php");
    exit();
}
// 2. เตรียม Link สำหรับส่งไป Console
if (empty($_SESSION['oauth_state'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16)); // 🟢 สร้างรหัสลับกันโดนหลอกล็อกอิน
}

// 🟢 2.5 สร้าง PKCE Code Verifier & Challenge
if (empty($_SESSION['code_verifier'])) {
    $_SESSION['code_verifier'] = bin2hex(random_bytes(32)); 
}
// S256 Challenge
$code_challenge = rtrim(strtr(base64_encode(hash('sha256', $_SESSION['code_verifier'], true)), '+/', '-_'), '=');

// 3. เตรียม Link สำหรับส่งไป Console
$params = [
    'action' => 'oauth_authorize',
    'client_id' => CLIENT_ID,
    'redirect_uri' => REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'profile email department', // 🟢 อัปเดต Scope ใหม่
    'state' => $_SESSION['oauth_state'],
    'code_challenge' => $code_challenge,
    'code_challenge_method' => 'S256'
];
$login_url = CONSOLE_API_URL . '?' . http_build_query($params);

// 4. สั่ง Redirect ด้วย PHP Header (วิธีหลัก)
if (!headers_sent()) {
    header("Location: " . $login_url);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>กำลังเข้าสู่ระบบ...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
    <style> body { font-family: 'Sarabun', sans-serif; } </style>
</head>
<body class="bg-gray-100 h-screen flex flex-col items-center justify-center">
    
    <div class="bg-white p-8 rounded-lg shadow-lg text-center max-w-sm w-full">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-900 mx-auto mb-4"></div>
        
        <h2 class="text-xl font-bold text-gray-800 mb-2">กำลังเชื่อมต่อระบบกลาง...</h2>
        <p class="text-gray-500 text-sm mb-6">กรุณารอสักครู่ ระบบกำลังนำท่านไปยัง Police Cloud Console</p>
        
        <a href="<?= $login_url ?>" class="block w-full bg-blue-900 text-white py-2 rounded hover:bg-blue-800 transition">
            คลิกที่นี่หากหน้านิ่งค้าง
        </a>
    </div>

    <script>
        setTimeout(function() {
            window.location.href = "<?= $login_url ?>";
        }, 100); // หน่วงเวลาเสี้ยววินาทีเพื่อให้ User รู้ว่ามีการโหลด
    </script>
</body>
</html>
