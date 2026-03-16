<?php
// idcard/save_request.php
require_once 'connect.php';
require_once 'helpers.php'; // 🟢 เพิ่มบรรทัดนี้เข้ามา
require_once 'notifications.php'; // 🔔 สำหรับแจ้งเตือน Discord/Line/Telegram

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Error: Invalid CSRF Token");
}

// 2. รับค่าพื้นฐาน
// 🟢 แก้ไขปัญหา Incorrect integer value (กรองข้อมูล user_id ให้เป็นตัวเลขเท่านั้น)
$raw_user_id = $_SESSION['user_id'] ?? NULL;
$user_id = is_numeric($raw_user_id) ? (int)$raw_user_id : NULL;
$audit_user_id = $user_id ?? 0; // สำหรับตาราง log ที่บังคับไม่ให้เป็น NULL
$written_at = trim($_POST['written_at']);
$rank_id = !empty($_POST['rank_id']) && is_numeric($_POST['rank_id']) ? (int)$_POST['rank_id'] : null;
$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);
$full_name = $first_name . ' ' . $last_name;
$birth_date = $_POST['birth_date'];

// คำนวณอายุฝั่ง Server
$age = $_POST['age'] ?? 0;
if (empty($age) || !is_numeric($age)) {
    $age = !empty($birth_date) ? calculateAge($birth_date) : 0;
}

$nationality = trim($_POST['nationality']);
$blood_type = $_POST['blood_type'];
// 🛡️ ป้องกัน IDOR: ดึงเลข ปชช. จาก Session เท่านั้น ห้ามเชื่อ $_POST!
$raw_id = $_SESSION['id_card_public'] ?? ($_SESSION['id_card'] ?? '');
$id_card_number = preg_replace('/[^0-9]/', '', $raw_id);
if (empty($id_card_number)) {
    die("Error: ไม่พบสิทธิ์ในการทำรายการ (Invalid Session)");
}
$phone = trim($_POST['phone']);

// จัดการ JSON ที่อยู่
$address_data = [
    'house_no' => $_POST['addr_house_no'],
    'moo' => $_POST['addr_moo'],
    'road' => $_POST['addr_road'],
    'tambon' => $_POST['addr_tambon'],
    'amphoe' => $_POST['addr_amphoe'],
    'province' => $_POST['addr_province'],
    'zipcode' => $_POST['addr_zipcode']
];
$address_json = json_encode($address_data, JSON_UNESCAPED_UNICODE);

$contact_type = $_POST['contact_address_type'];
$contact_detail = ($contact_type == 'OTHER') ? trim($_POST['contact_address_detail']) : 'SAME';
$officer_type = $_POST['officer_type'];
$position = trim($_POST['position']);
$org_id = $_POST['org_id'];

// 🟢 รับประเภทบัตรโดยตรงจากฟอร์ม (ยกเลิกการ Map)
$card_type_id = $_POST['card_type_id'] ?? 1;

$reason = $_POST['request_reason'];
$reason_detail = $_POST['request_reason_detail'] ?? '';
$reason_other = $_POST['request_reason_other'] ?? '';
$old_card_num = $_POST['old_card_number'] ?? '';

// 🟢 Backend Validation for Request Reason and Detail
if ($reason === 'NEW') {
    $valid_new_details = ['EXPIRED', 'LOST'];
    if (!in_array($reason_detail, $valid_new_details)) {
        die("Error: กรุณาเลือกเหตุผลขอมีบัตรใหม่ ('บัตรหมดอายุ' หรือ 'บัตรหาย/ถูกทำลาย') อย่างน้อย 1 ตัวเลือก");
    }
}
elseif ($reason === 'CHANGE') {
    $valid_change_details = ['CHANGE_POS', 'CHANGE_NAME', 'CHANGE_SURNAME', 'CHANGE_BOTH', 'DAMAGED', 'RETIRED', 'OTHER'];
    if (!in_array($reason_detail, $valid_change_details)) {
        die("Error: กรุณาเลือกเหตุผลขอเปลี่ยนบัตร อย่างน้อย 1 ตัวเลือก");
    }
    if ($reason_detail === 'OTHER' && empty(trim($reason_other))) {
        die("Error: กรุณาระบุเหตุผลอื่นๆ สำหรับขอเปลี่ยนบัตร");
    }
}

// 1. ระบุตำแหน่งถอยหลัง 2 ก้าว (โดยยังไม่ใช้ realpath)
$upload_dir = __DIR__ . '/../../secure_uploads/';

// 2. สร้างโฟลเดอร์หากยังไม่มีอยู่จริง (เปิดสิทธิ์ 0755 ให้ระบบเขียนไฟล์ได้ชัวร์ๆ)
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 3. เมื่อมั่นใจว่ามีโฟลเดอร์แล้ว ค่อยจัดฟอร์แมต Path ให้สมบูรณ์
$upload_dir = realpath($upload_dir) . '/';

// ฟังก์ชันสร้างชื่อไฟล์
function generateCustomName($id_card, $name, $seq_num, $ext)
{
    $y_th = (date('Y') + 543) % 100;
    $safe_name = preg_replace('/[^A-Za-z0-9ก-๙]/u', '_', $name);
    $date_prefix = $y_th . date('md');
    $seq_str = sprintf('%03d', $seq_num);
    return $date_prefix . "_" . $id_card . "_" . $seq_str . "_" . $name . "." . $ext;
}

// 🚀 ฟังก์ชันจัดการไฟล์ที่อัปโหลดมาล่วงหน้า (Async Upload - Secure Version)
function moveAsyncUpload($input_name, &$doc_array, &$seq, $id_card, $fname, $dir)
{
    // 🛡️ ป้องกัน Path Traversal: สกัดเอาแค่ "ชื่อไฟล์" ไม่เอาโฟลเดอร์ที่แฮกเกอร์พิมพ์มา
    $tmp_filename = basename($_POST[$input_name . '_uploaded_path'] ?? '');

    // เช็คว่ามีชื่อไฟล์ และต้องขึ้นต้นด้วย TEMP_ เท่านั้น
    if (!empty($tmp_filename) && strpos($tmp_filename, 'TEMP_') === 0) {
        $tmp_path = $dir . $tmp_filename;

        if (file_exists($tmp_path)) {
            $ext = strtolower(pathinfo($tmp_path, PATHINFO_EXTENSION));
            $new_name = generateCustomName($id_card, $fname, $seq, $ext);
            $final_path = $dir . $new_name;

            if (rename($tmp_path, $final_path)) {
                $doc_array[] = $final_path;
                $seq++;
            }
        }
    }
}

// 🛡️ ดึง ID ที่ได้รับสิทธิ์ให้แก้ไขจาก Session
$edit_id = $_SESSION['edit_request_id'] ?? '';
$old_req = null;

if (!empty($edit_id)) {
    $stmt = $conn->prepare("SELECT photo_path, signature_file, documents_json FROM idcard_requests WHERE id = ? AND id_card_number = ?");
    $stmt->execute([$edit_id, $id_card_number]);
    $old_req = $stmt->fetch();
}

// 3.1 รูปถ่ายหน้าตรง
$photo_path = $old_req['photo_path'] ?? '';
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
elseif (empty($edit_id)) {
    die("Error: ไม่พบข้อมูลรูปถ่าย");
}

// 3.2 เอกสารแนบ
$seq_counter = 2;
$new_documents_arr = [];

// 🟢 เรียกใช้ฟังก์ชันดึงไฟล์ Async ที่อัปไว้มารวมกัน (ดึงให้ครบทุกช่องทางการอัปโหลด)
moveAsyncUpload('doc_idcard_house', $new_documents_arr, $seq_counter, $id_card_number, $first_name, $upload_dir);
moveAsyncUpload('doc_position', $new_documents_arr, $seq_counter, $id_card_number, $first_name, $upload_dir);
moveAsyncUpload('doc_rank', $new_documents_arr, $seq_counter, $id_card_number, $first_name, $upload_dir);
moveAsyncUpload('doc_old_card', $new_documents_arr, $seq_counter, $id_card_number, $first_name, $upload_dir);
moveAsyncUpload('doc_blood', $new_documents_arr, $seq_counter, $id_card_number, $first_name, $upload_dir);
moveAsyncUpload('doc_lost', $new_documents_arr, $seq_counter, $id_card_number, $first_name, $upload_dir);
moveAsyncUpload('doc_name_change', $new_documents_arr, $seq_counter, $id_card_number, $first_name, $upload_dir);
moveAsyncUpload('doc_surname_change', $new_documents_arr, $seq_counter, $id_card_number, $first_name, $upload_dir);
moveAsyncUpload('doc_retired', $new_documents_arr, $seq_counter, $id_card_number, $first_name, $upload_dir);

// 🟢 โหมดแก้ไข: เอาไฟล์เก่าที่เคยอัปโหลดไว้มารวมกับไฟล์ใหม่ ไม่เขียนทับ
$old_documents_arr = [];
if (!empty($edit_id) && !empty($old_req['documents_json'])) {
    $old_documents_arr = json_decode($old_req['documents_json'], true) ?? [];
}

$final_documents_arr = array_merge($old_documents_arr, $new_documents_arr);

$documents_json = json_encode($final_documents_arr, JSON_UNESCAPED_UNICODE);

// 3.3 ลายเซ็น (รองรับทั้งวาดและอัปโหลด)
$sig_path = $old_req['signature_file'] ?? '';
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

// --- ดึง ID ของผู้ออกบัตร และ ผู้ตรวจบัตร ปัจจุบัน เพื่อฝังลงในคำขอ ---
$issuer_id = null;
$inspector_id = null;
$stmt_issuer = $conn->query("SELECT id FROM idcard_signers WHERE signer_type = 'ISSUER' AND is_active = 1 LIMIT 1");
if ($row = $stmt_issuer->fetch())
    $issuer_id = $row['id'];

$stmt_inspector = $conn->query("SELECT id FROM idcard_signers WHERE signer_type = 'INSPECTOR' AND is_active = 1 LIMIT 1");
if ($row = $stmt_inspector->fetch())
    $inspector_id = $row['id'];

// 4. บันทึกลง Database
try {
    if (!empty($edit_id)) {
        // 🟢 โหมด UPDATE (แก้ไขข้อมูลที่ส่งมาแล้วเพราะตีกลับ) เพิ่ม card_type_id
        $sql = "UPDATE idcard_requests SET 
            card_type_id=?, rank_id=?, full_name=?, birth_date=?, age=?, nationality=?, blood_type=?, phone=?, 
            address_json=?, contact_address_type=?, contact_address_json=?, officer_type=?, position=?, org_id=?, 
            request_reason=?, request_reason_detail=?, request_reason_other=?, old_card_number=?, 
            photo_path=?, documents_json=?, signature_file=?, status='PENDING_CHECK',
            issuer_id=?, inspector_id=? 
            WHERE id=? AND id_card_number=?";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $card_type_id, $rank_id, $full_name, $birth_date, $age, $nationality, $blood_type, $phone,
            $address_json, $contact_type, $contact_detail, $officer_type, $position, $org_id,
            $reason, $reason_detail, $reason_other, $old_card_num,
            $photo_path, $documents_json, $sig_path,
            $issuer_id, $inspector_id,
            $edit_id, $id_card_number
        ]);

        $conn->prepare("INSERT INTO idcard_audit_logs (user_id, action, details, ip_address) VALUES (?, 'EDIT_REQUEST', ?, ?)")
            ->execute([$audit_user_id, "แก้ไขคำขอ: $full_name", $_SERVER['REMOTE_ADDR']]);

        // 🔔 ส่งแจ้งเตือน (กรณีแก้ไข)
        sendIDCardNotification($conn, $edit_id);

    }
    else {
        // 🔵 โหมด INSERT (สร้างคำขอใหม่เอี่ยม) เพิ่ม card_type_id และ request_year
        $current_year_th = (int)(date('Y') + 543);
        $sql = "INSERT INTO idcard_requests 
        (user_id, card_type_id, written_at, rank_id, full_name, birth_date, age, nationality, blood_type, 
        id_card_number, phone, address_json, contact_address_type, contact_address_json,
        officer_type, position, org_id, request_reason, request_reason_detail, request_reason_other,
        old_card_number, photo_path, documents_json, signature_file, status, created_at, issuer_id, inspector_id, request_year)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING_CHECK', NOW(), ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $user_id, $card_type_id, $written_at, $rank_id, $full_name, $birth_date, $age, $nationality, $blood_type,
            $id_card_number, $phone, $address_json, $contact_type, $contact_detail,
            $officer_type, $position, $org_id, $reason, $reason_detail, $reason_other,
            $old_card_num, $photo_path, $documents_json, $sig_path,
            $issuer_id, $inspector_id, $current_year_th
        ]);

                // 🔔 ส่งแจ้งเตือน (กรณียื่นใหม่)
        $new_id = $conn->lastInsertId();
        
        $conn->prepare("INSERT INTO idcard_audit_logs (user_id, action, details, ip_address) VALUES (?, 'SUBMIT_REQUEST', ?, ?)")
            ->execute([$audit_user_id, "ยื่นคำขอใหม่: $full_name", $_SERVER['REMOTE_ADDR']]);


        sendIDCardNotification($conn, $new_id);
    }

    // ระบบเรียนรู้ชื่อตำแหน่งอัตโนมัติ (Auto-learn)
    if (!empty($position)) {
        $conn->prepare("INSERT IGNORE INTO idcard_positions (position_name) VALUES (?)")->execute([$position]);
    }

    // สร้าง Token สำหรับแชร์ลิงก์ดูสถานะ (อายุ 30 วัน)
    $payload = base64_encode(json_encode([
        'id_card' => $id_card_number,
        'exp' => time() + (86400 * 30)
    ]));
    $signature = hash_hmac('sha256', $payload, TOKEN_SECRET);
    $tracking_token = $payload . '.' . $signature;

    // ล้างค่า Session คืนสู่ปกติ (หลังจากสร้าง Token เสร็จแล้ว)
    if (isset($_SESSION['public_access'])) {
        unset($_SESSION['public_access']);
        unset($_SESSION['id_card_public']);
        unset($_SESSION['form_prefill']);
        unset($_SESSION['edit_request_id']);
        unset($_SESSION['phone_public']);
    }

    // แสดงหน้าจอสำเร็จ และ Redirect ไปหน้า Track Status พร้อม Token
    echo "<!DOCTYPE html>
    <html lang='th'>
    <head>
        <meta charset='UTF-8'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <link href='https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap' rel='stylesheet'>
        <style> body { font-family: 'Sarabun', sans-serif; background: #f3f4f6; } </style>
    </head>
    <body>
        <script>
            Swal.fire({
                title: 'บันทึกคำขอสำเร็จ!',
                text: 'ระบบกำลังนำท่านไปยังหน้าติดตามสถานะ...',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'track_status.php?token={$tracking_token}';
            });
        </script>
    </body>
    </html>";

}
catch (PDOException $e) {
    die("<h3>เกิดข้อผิดพลาดในระบบฐานข้อมูล:</h3>" . $e->getMessage());
}
?>