<?php
// connect.php
require_once 'config.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    // ตั้งค่า Error Mode เป็น Exception เพื่อให้รู้ทันทีเมื่อ Query พลาด
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // ป้องกันการจำลอง Prepare Statement (Security Best Practice)
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
}
catch (PDOException $e) {
    // ห้ามแสดง Error จริงให้ User เห็น (Log ลงไฟล์แทน)
    error_log("Connection Error: " . $e->getMessage());
    die("ระบบฐานข้อมูลขัดข้อง กรุณาติดต่อผู้ดูแลระบบ");
}
// 🟢 1. ฟังก์ชันดึง IP ที่แท้จริง (ทะลุ Proxy / WAN Server)
function getRealIP()
{
    $ip = 'UNKNOWN';
    // เช็คจาก Header ที่ Proxy/Load Balancer มักจะส่งมาให้
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // บางครั้งมาเป็น Array ของ IP (เช่น IP ลูกค้า, IP Proxy) ให้เอาตัวแรก
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ipList[0]);
    }
    else {
        // ถ้าไม่มี Proxy คั่น ก็ใช้ IP ตรงๆ
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
    return $ip;
}

// 🟢 2. ฟังก์ชันบันทึก Log การทำงาน
function saveLog($conn, $action_type, $action_detail, $target_id = null, $old_data = null, $new_data = null)
{
    $user_type = 'GUEST';
    $user_identifier = 'UNKNOWN';

    // เช็คว่าใครเป็นคนทำรายการจาก Session
    if (isset($_SESSION['user_id'])) {
        $user_type = 'ADMIN';
        $user_identifier = $_SESSION['user_id']; // ไอดีของแอดมิน
    }
    elseif (isset($_SESSION['id_card_public'])) {
        $user_type = 'PUBLIC';
        $user_identifier = $_SESSION['id_card_public']; // เลขบัตรประชาชน
    }

    $ip_address = getRealIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    // แปลงข้อมูล Array ให้เป็น JSON (ถ้ามีข้อมูลส่งมา)
    $old_json = is_array($old_data) ? json_encode($old_data, JSON_UNESCAPED_UNICODE) : $old_data;
    $new_json = is_array($new_data) ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : $new_data;

    try {
        $stmt = $conn->prepare("INSERT INTO idcard_logs 
            (user_type, user_identifier, action_type, target_id, action_detail, old_data, new_data, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_type, $user_identifier, $action_type, $target_id, $action_detail,
            $old_json, $new_json, $ip_address, $user_agent
        ]);
    }
    catch (PDOException $e) {
        // เงียบไว้ ไม่ให้ระบบหลักพังถ้า Log บันทึกไม่สำเร็จ
        error_log("Log Insert Error: " . $e->getMessage());
    }
}
?>