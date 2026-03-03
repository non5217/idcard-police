<?php
// idcard/callback.php
require_once 'connect.php'; // ❌ ไม่ต้อง session_start()

// 1. ตรวจสอบว่ามี Code ส่งมาไหม
if (!isset($_GET['code'])) {
    die("Error: ไม่พบ Authorization Code");
}

// 🟢 1.5 ตรวจสอบ State เพื่อป้องกัน Login CSRF Attack
//if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
//    die("⛔ Security Error: ตรวจพบการโจมตีแบบ CSRF (รหัส State ไม่ตรงกัน)");
//}
//unset($_SESSION['oauth_state']); // ใช้เสร็จแล้วลบทิ้งทันที
$code = $_GET['code'];

// 2. เตรียมแลก Token
$token_url = CONSOLE_API_URL . '?action=oauth_token';
$params = [
    'grant_type' => 'authorization_code',
    'client_id' => CLIENT_ID,
    'client_secret' => CLIENT_SECRET,
    'redirect_uri' => REDIRECT_URI,
    'code' => $code
];

// --- เริ่มยิง cURL ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// ⚠️ เพิ่ม 2 บรรทัดนี้เพื่อแก้ปัญหา SSL (เฉพาะกิจ)
#curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
#curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
// ⚠️ เพิ่ม 2 บรรทัดนี้เพื่อแก้ปัญหา SSL (เฉพาะกิจ)
$response = curl_exec($ch);

// ⚠️ เช็ค Error ของ cURL โดยตรง
if ($response === false) {
    die("cURL Connect Error: " . curl_error($ch));
}
curl_close($ch);
// -------------------

$token_data = json_decode($response, true);

// เช็คว่าได้ Token จริงไหม
if (!isset($token_data['access_token'])) {
    // ปริ้น response ออกมาดูเลยว่า Server ตอบอะไรมา (อาจจะเป็น HTML Error)
    echo "<h3>Server Response:</h3>";
    var_dump($response);
    die("<br><hr>Error Obtaining Token: " . ($token_data['error_description'] ?? 'Unknown Error'));
}

// 3. เอา Token ไปดึงข้อมูล User
$access_token = $token_data['access_token'];
$user_info_url = CONSOLE_API_URL . '?action=oauth_userinfo';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $user_info_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $access_token]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ⚠️ ปิด SSL ตรงนี้ด้วย
//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);     // ⚠️ ปิด SSL ตรงนี้ด้วย
$user_json = curl_exec($ch);
curl_close($ch);

$user_data = json_decode($user_json, true);

if (!$user_data || !isset($user_data['id'])) {
    die("Error Fetching User Profile: " . $user_json);
}

// 4. สร้าง Session และตรวจสอบ Role
session_regenerate_id(true); // 🟢 เพิ่มบรรทัดนี้! ล้างรหัสคุกกี้เก่าทิ้ง สร้างคุกกี้ใหม่ทันทีหลังล็อกอิน ป้องกันการสวมรอย 100%

$_SESSION['user_id'] = $user_data['id'];
$_SESSION['fullname'] = ($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? '');
$_SESSION['id_card'] = $user_data['id_card'] ?? ''; 
$_SESSION['access_token'] = $access_token;

// ดึง Role จากตารางในฐานข้อมูล (ถ้ามี)
$stmt = $conn->prepare("SELECT role FROM idcard_staff_roles WHERE console_user_id = ?");
$stmt->execute([$user_data['id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

// ถ้าไม่มีในตาราง staff ให้เป็น User ทั่วไป
$_SESSION['role'] = $staff ? $staff['role'] : 'User';

// 5. บันทึก Log การเข้าใช้งาน
$ip = $_SERVER['REMOTE_ADDR'];
$conn->prepare("INSERT INTO idcard_audit_logs (user_id, action, details, ip_address) VALUES (?, 'LOGIN', 'เข้าสู่ระบบสำเร็จ', ?)")
     ->execute([$user_data['id'], $ip]);

// 6. ส่งไปหน้า Dashboard หรือ Admin ตาม Role
if ($_SESSION['role'] === 'User') {
    header("Location: index.php");
} else {
    header("Location: admin_dashboard.php");
}
exit();
?>
