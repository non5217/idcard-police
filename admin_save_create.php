<?php
// idcard/admin_save_create.php
require_once 'connect.php';
require_once 'admin_auth.php'; // 🔒 เฉพาะ Admin (ไฟล์นี้จะทำการเปิด Session ให้)
require_once 'helpers.php'; // สำหรับดึงฟังก์ชันคำนวณต่างๆ
require_once 'notifications.php'; // 🔔 สำหรับแจ้งเตือน Discord/Line/Telegram

// 🟢 1. ตรวจสอบ CSRF Token (ป้องกัน Admin โดนหลอกให้รันสคริปต์)
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("⛔ Security Error: CSRF Token ไม่ถูกต้อง หรือตรวจสอบไม่ผ่าน");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    die("Invalid Request");

$admin_id = $_SESSION['user_id'];
// 1. รับค่า
$status = $_POST['status'];
$rank_id = $_POST['rank_id'];
$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);
$full_name = $first_name . ' ' . $last_name;
$birth_date = $_POST['birth_date'];
$id_card_number = trim($_POST['id_card_number']);
$blood_type = $_POST['blood_type'];
$phone = trim($_POST['phone']);
$position = trim($_POST['position']);
$org_id = $_POST['org_id'];
$card_type_id = $_POST['card_type_id'];

$officer_type = $_POST['officer_type'];

// 🟢 2. Server-Side Validation: ตรวจสอบเลขบัตร ปชช. ว่าเป็นตัวเลข 13 หลักจริงๆ
if (!preg_match('/^[0-9]{13}$/', $id_card_number)) {
    die("⛔ Error: เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลักเท่านั้น");
}
// 🟢 3. Server-Side Validation: ตรวจสอบวันเกิด (ป้องกัน Database Error 1292)
if (empty($birth_date) || $birth_date === '0000-00-00') {
    die("<script>alert('⛔ Error: ข้อมูลวันเกิดไม่ถูกต้อง หรือกรอกไม่ครบถ้วน\\nกรุณากรอกในรูปแบบ วว/ดด/ปปปป (เช่น 05/03/2530)'); window.history.back();</script>");
}
// คำนวณอายุตั้งต้นโดยเรียกใช้ฟังก์ชันกลาง
$age = !empty($birth_date) ? calculateAge($birth_date) : 0;

// วันออกบัตรและหมดอายุ
$is_pension = false;
$stmt_ctype = $conn->prepare("SELECT type_name FROM idcard_card_types WHERE id = ?");
$stmt_ctype->execute([$card_type_id]);
if (strpos($stmt_ctype->fetchColumn(), 'บำนาญ') !== false) {
    $is_pension = true;
}

$issue_date = !empty($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');
$expire_date = !empty($_POST['expire_date']) ? $_POST['expire_date'] : calculateCardExpiry($birth_date, $issue_date, $is_pension);

// ข้อมูลจำลองอื่นๆ ให้ครบตาม Table โครงสร้างเดิม
$address_json = "{}";
$contact_type = "SAME";
$contact_json = "";
$reason = "NEW";
$documents_json = "[]";

// 🟢 เพิ่มตัวแปรเหล่านี้จำลองไว้ตรงนี้เลยครับ
$written_at = 'ภ.จว.ปทุมธานี';
$nationality = 'ไทย';
$reason_detail = '';
$reason_other = '';
$old_card_num = '';

// 1. ระบุตำแหน่งถอยหลัง 2 ก้าว (โดยยังไม่ใช้ realpath)
$upload_dir = __DIR__ . '/../../secure_uploads/';

// 2. สร้างโฟลเดอร์หากยังไม่มีอยู่จริง (เปิดสิทธิ์ 0777 ให้ระบบเขียนไฟล์ได้ชัวร์ๆ)
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 3. เมื่อมั่นใจว่ามีโฟลเดอร์แล้ว ค่อยจัดฟอร์แมต Path ให้สมบูรณ์
$upload_dir = realpath($upload_dir) . '/';

function generateCustomName($id_card, $name, $seq_num, $ext)
{
    return ((date('Y') + 543) % 100) . date('md') . "_" . $id_card . "_" . sprintf('%03d', $seq_num) . "_" . $name . "." . $ext;
}

$photo_path = null;
if (!empty($_POST['cropped_photo_data'])) {
    $data_uri = $_POST['cropped_photo_data'];
    $parts = explode(",", $data_uri);
    if (count($parts) === 2) {
        $decoded_image = base64_decode($parts[1]);
        $photo_name = generateCustomName($id_card_number, $first_name, 1, 'jpg');
        $photo_path = $upload_dir . $photo_name;
        file_put_contents($photo_path, $decoded_image);
    }
}

$sig_path = null;
if (!empty($_POST['signature_data'])) {
    $data_uri = $_POST['signature_data'];
    $parts = explode(",", $data_uri);
    if (count($parts) === 2) {
        $decoded_image = base64_decode($parts[1]);
        $sig_name = "SIG_" . uniqid() . ".png";
        $sig_path = $upload_dir . $sig_name;
        file_put_contents($sig_path, $decoded_image);
    }
}

// ดึงคนเซ็นบัตรปัจจุบัน
$issuer_id = null;
$inspector_id = null;
if ($row = $conn->query("SELECT id FROM idcard_signers WHERE signer_type = 'ISSUER' AND is_active = 1 LIMIT 1")->fetch())
    $issuer_id = $row['id'];
if ($row = $conn->query("SELECT id FROM idcard_signers WHERE signer_type = 'INSPECTOR' AND is_active = 1 LIMIT 1")->fetch())
    $inspector_id = $row['id'];

// 3. 🛑 โลจิกออกเลขบัตรใหม่แบบถาวร (ถ้าระบุสถานะที่ต้องออกบัตร)
$new_sequence = null;
$current_year_th = null;
$generated_card_no = null;

if (in_array($status, ['SENT_TO_PRINT', 'READY_PICKUP', 'COMPLETED'])) {
    $current_year_th = (int)(date('Y') + 543);
    $stmt_max = $conn->prepare("SELECT MAX(card_sequence) FROM idcard_requests WHERE card_year = ?");
    $stmt_max->execute([$current_year_th]);
    $max_seq = $stmt_max->fetchColumn();
    $new_sequence = $max_seq ? $max_seq + 1 : 1;
    $generated_card_no = sprintf("0016.5 - %d - %04d", $current_year_th, $new_sequence);
}
if (!preg_match('/^[0-9]{13}$/', $id_card_number)) {
    die("⛔ Error: เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลักเท่านั้น");
}
// 4. บันทึกลง Database (INSERT)
try {
    $current_year_th = $current_year_th ?? (int)(date('Y') + 543);
    $sql = "INSERT INTO idcard_requests 
    (user_id, card_type_id, written_at, rank_id, full_name, birth_date, age, nationality, blood_type, 
    id_card_number, phone, address_json, contact_address_type, contact_address_json,
    officer_type, position, org_id, request_reason, request_reason_detail, request_reason_other, old_card_number, documents_json, 
    photo_path, signature_file, status, created_at, issuer_id, inspector_id,
    card_sequence, card_year, generated_card_no, issue_date, expire_date, request_year)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        0, $card_type_id, $written_at, $rank_id, $full_name, $birth_date, $age, $nationality, $blood_type,
        $id_card_number, $phone, $address_json, $contact_type, $contact_json,
        $officer_type, $position, $org_id, $reason, $reason_detail, $reason_other, $old_card_num, $documents_json,
        $photo_path, $sig_path, $status, $issuer_id, $inspector_id,
        $new_sequence, $current_year_th, $generated_card_no, $issue_date, $expire_date, $current_year_th
    ]);

    $new_id = $conn->lastInsertId();

    if (!empty($position)) {
        $conn->prepare("INSERT IGNORE INTO idcard_positions (position_name) VALUES (?)")->execute([$position]);
    }

    $conn->prepare("INSERT INTO idcard_audit_logs (user_id, action, details, ip_address) VALUES (?, 'ADMIN_CREATE', ?, ?)")
        ->execute([$admin_id, "สร้างข้อมูลใหม่โดย Admin ID: $new_id ($full_name)", $_SERVER['REMOTE_ADDR']]);

    // 🔔 ส่งแจ้งเตือน
    sendIDCardNotification($conn, $new_id);

    echo "<script>alert('สร้างข้อมูลสำเร็จ! (เลขที่บัตร: " . ($generated_card_no ?? 'ยังไม่ออกเลข') . ")'); window.location.href = 'admin_dashboard.php';</script>";

}
catch (PDOException $e) {
    die("Error Saving Record: " . $e->getMessage());
}
?>