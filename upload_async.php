<?php
// idcard/upload_async.php
require_once 'connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ป้องกันคนนอกยิง API
if (!isset($_SESSION['user_id']) && !isset($_SESSION['public_access'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); 
    exit;
}

// เตรียมโฟลเดอร์รับไฟล์
$upload_dir = __DIR__ . '/../../secure_uploads/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
$upload_dir = realpath($upload_dir) . '/';

$b64_data = $_POST['file_b64'] ?? '';
$filename = $_POST['file_name'] ?? '';
// 🟢 ป้องกันการยิงไฟล์ขนาดใหญ่เกิน 8MB (8 * 1024 * 1024 bytes) เข้ามาทำให้เว็บล่ม (DoS Protection)
if (strlen($b64_data) > 8388608) {
    echo json_encode(['status' => 'error', 'message' => 'Payload too large']);
    exit;
}

if (!empty($b64_data) && !empty($filename)) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (in_array($ext, $allowed)) {
        $parts = explode(",", $b64_data);
        if (count($parts) === 2) {
            $decoded = base64_decode($parts[1]);
            // ตั้งชื่อไฟล์ชั่วคราว
            $tmp_name = "TEMP_" . uniqid() . "_" . time() . "." . $ext;
            $target = $upload_dir . $tmp_name;
            
            // นำไฟล์ไปวางในโฟลเดอร์ secure_uploads
            if (file_put_contents($target, $decoded)) {
                echo json_encode(['status' => 'success', 'tmp_path' => $target]);
                exit;
            }
        }
    }
}
echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
?>
