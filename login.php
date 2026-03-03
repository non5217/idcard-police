<?php
// idcard/login.php
require_once 'config.php';
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
// 2. เตรียม Link สำหรับส่งไป Console
$params = [
    'action' => 'oauth_authorize',
    'client_id' => CLIENT_ID,
    'redirect_uri' => REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'basic_info',
    //'state' => $_SESSION['oauth_state'] // 🟢 แนบรหัสลับไปด้วย
];
$login_url = CONSOLE_API_URL . '?' . http_build_query($params);

// 3. สั่ง Redirect ด้วย PHP Header (วิธีหลัก)
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
