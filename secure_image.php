<?php
// idcard/secure_image.php
require_once 'connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบสิทธิ์: ถ้าไม่ได้ล็อกอิน ห้ามดูเด็ดขาด!
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("⛔ Access Denied: คุณไม่มีสิทธิ์เข้าถึงไฟล์นี้");
}

// 2. รับที่อยู่ไฟล์ที่ต้องการดู
$file_path = $_GET['f'] ?? '';
if (empty($file_path)) {
    http_response_code(404);
    die("File not specified");
}

// 3. ป้องกันการเจาะระบบแบบ Directory Traversal (ป้องกันการแอบดูไฟล์ระบบเช่น ../../config.php)
// ชี้ไปที่โฟลเดอร์เป้าหมาย
$real_base_dir = realpath(__DIR__ . '/../../secure_uploads');

// 🟢 รองรับกรณีที่ path ในฐานข้อมูลถูกบันทึกเป็นที่อยู่แบบเต็มไปแล้ว
if (file_exists($file_path)) {
    $real_request_file = realpath($file_path);
} else {
    $real_request_file = realpath(__DIR__ . '/' . $file_path);
}

if ($real_request_file === false || strpos($real_request_file, $real_base_dir) !== 0) {
    http_response_code(403);
    die("⛔ Access Denied: ไม่อนุญาตให้เข้าถึงเส้นทางนี้");
}

// 4. ส่งไฟล์ภาพออกไปให้เบราว์เซอร์
if (file_exists($real_request_file)) {
    // บอกเบราว์เซอร์และ Google ว่า "ห้ามจำ" และ "ห้าม Index" ภาพนี้เด็ดขาด
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow'); // กัน Google 100%
    
    // ตั้งค่า Content-Type ให้ตรงกับนามสกุลไฟล์
    $mime_type = mime_content_type($real_request_file);
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($real_request_file));
    
    // อ่านและแสดงผลไฟล์
    readfile($real_request_file);
    exit;
} else {
    http_response_code(404);
    die("File not found");
}
