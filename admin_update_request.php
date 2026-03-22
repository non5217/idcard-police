<?php
// idcard/admin_update_request.php
require_once 'connect.php';
require_once 'admin_auth.php'; // 🔒 เฉพาะ Admin

// 🟢 1. ตรวจสอบ CSRF Token (ป้องกัน Admin โดนหลอกให้กดลิงก์มาแก้ข้อมูล)
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("⛔ Security Error: CSRF Token ไม่ถูกต้อง หรือตรวจสอบไม่ผ่าน");
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    die("Invalid Request");

$id = $_POST['id'];

// 🟢 2. ดึงข้อมูลเก่าทั้งหมดมาเก็บไว้ทำ Log ก่อนถูกเขียนทับ
$stmt_old = $conn->prepare("SELECT * FROM idcard_requests WHERE id = ?");
$stmt_old->execute([$id]);
$old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

// 3. รับค่าพื้นฐาน
$card_type_id = $_POST['card_type_id'];

$rank_id = $_POST['rank_id'];
$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);
$full_name = $first_name . ' ' . $last_name;
$birth_date = trim($_POST['birth_date'] ?? '');

$age = $_POST['age'];

// Server-Side Validation: ตรวจสอบวันเกิด 
if (empty($birth_date) || $birth_date === '0000-00-00') {
    die("<script>alert('⛔ Error: ข้อมูลวันเกิดไม่ถูกต้อง หรือกรอกไม่ครบถ้วน\\nกรุณากรอกในรูปแบบ วว/ดด/ปปปป (เช่น 05/03/2530)'); window.history.back();</script>");
}

$phone = trim($_POST['phone']);
$position = trim($_POST['position']);
$org_id = $_POST['org_id'];
$status = $_POST['status'];
$reject_reason = $_POST['reject_reason'];

// 4. จัดการที่อยู่ JSON
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

// ดึงค่าวันที่ (ถ้ากรอกมา)
$issue_date = !empty($_POST['issue_date']) ? $_POST['issue_date'] : NULL;
$expire_date = !empty($_POST['expire_date']) ? $_POST['expire_date'] : NULL;

// 5. เตรียมคำสั่ง Update พื้นฐาน
$update_fields = [
    "card_type_id = ?", "rank_id = ?", "full_name = ?", "birth_date = ?", "age = ?", "phone = ?",
    "blood_type = ?", "position = ?", "org_id = ?", "address_json = ?", "status = ?", "reject_reason = ?",
    "issue_date = ?", "expire_date = ?"
];
$update_params = [
    $card_type_id, $rank_id, $full_name, $birth_date, $age, $phone,
    $_POST['blood_type'] ?? NULL, $position, $org_id, $address_json, $status, $reject_reason,
    $issue_date, $expire_date
];

// 🛑 6. โลจิกออกเลขบัตรใหม่แบบถาวร
$eligible_statuses = ['SENT_TO_PRINT', 'READY_PICKUP', 'COMPLETED'];

// ถ้าสถานะถึงจุดที่ต้องออกบัตร
if (in_array($status, $eligible_statuses)) {
    require_once 'helpers.php'; // เรียกใช้ฟังก์ชันคำนวณวัน

    // 6.1 ถ้ายังไม่มีเลขรันนิ่ง (เช็คจากข้อมูลเก่า) ให้ออกเลข
    if (empty($old_data['card_sequence'])) {
        $current_year_th = (int)(date('Y') + 543);
        $stmt_max = $conn->prepare("SELECT MAX(card_sequence) FROM idcard_requests WHERE card_year = ?");
        $stmt_max->execute([$current_year_th]);
        $max_seq = $stmt_max->fetchColumn();

        $new_sequence = $max_seq ? $max_seq + 1 : 1;
        $generated_card_no = sprintf("0016.5 - %d - %04d", $current_year_th, $new_sequence); // 🟢 ปรับเป็น 4 หลักให้ตรงกับระบบ

        $update_fields[] = "card_sequence = ?";
        $update_params[] = $new_sequence;
        $update_fields[] = "card_year = ?";
        $update_params[] = $current_year_th;
        $update_fields[] = "generated_card_no = ?";
        $update_params[] = $generated_card_no;
    }

    // 6.2 ถ้าปล่อยวันออกบัตร/วันหมดอายุว่างไว้ ให้คำนวณอัตโนมัติ
    if (empty($issue_date)) {
        $issue_date = date('Y-m-d');
        $update_fields[] = "issue_date = ?";
        $update_params[] = $issue_date;
    }
    if (empty($expire_date)) {
        $is_pension = false;
        $stmt_ctype = $conn->prepare("SELECT type_name FROM idcard_card_types WHERE id = ?");
        $stmt_ctype->execute([$card_type_id]);
        if (strpos($stmt_ctype->fetchColumn(), 'บำนาญ') !== false) {
            $is_pension = true;
        }
        $expire_date = calculateCardExpiry($birth_date, $issue_date, $is_pension);
        $update_fields[] = "expire_date = ?";
        $update_params[] = $expire_date;
    }
}

// 7. จัดการไฟล์รูป/ลายเซ็น (ถ้ามีการอัปโหลดใหม่จาก Admin ให้เก็บเข้า secure_uploads)
$upload_dir = __DIR__ . '/../../secure_uploads/';
if (!file_exists($upload_dir))
    mkdir($upload_dir, 0755, true);
$upload_dir = realpath($upload_dir) . '/';

$raw_id = $_POST['id_card_number'] ?? '';
$id_card_number = !empty($raw_id) ? preg_replace('/[^0-9]/', '', $raw_id) : 'adminedit';
function generateCustomName($id_card, $name, $seq_num, $ext)
{
    $safe_name = preg_replace('/[^A-Za-z0-9ก-๙]/u', '_', $name);
    $y_th = (date('Y') + 543) % 100;
    return $y_th . date('md') . "_" . $id_card . "_" . sprintf('%03d', $seq_num) . "_" . $name . "." . $ext;
}

// 🛡️ กำหนดสกุลไฟล์ที่อนุญาต
$allowed_exts = ['png', 'jpg', 'jpeg'];

if (!empty($_FILES['new_photo']['name'])) {
    $ext = strtolower(pathinfo($_FILES['new_photo']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts)) {
        die("<script>alert('⛔ Error: รูปถ่ายต้องเป็นไฟล์ภาพ (JPG, PNG) เท่านั้น'); window.history.back();</script>");
    }

    $photo_name = generateCustomName($id_card_number, $first_name, 1, $ext);
    $target = $upload_dir . $photo_name;
    if (move_uploaded_file($_FILES['new_photo']['tmp_name'], $target)) {
        $update_fields[] = "photo_path = ?";
        $update_params[] = $target;
    }
}

if (!empty($_FILES['new_signature']['name'])) {
    $ext = strtolower(pathinfo($_FILES['new_signature']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts)) {
        die("<script>alert('⛔ Error: ลายเซ็นต้องเป็นไฟล์ภาพ (JPG, PNG) เท่านั้น'); window.history.back();</script>");
    }

    $sig_name = "SIG_" . uniqid() . "." . $ext;
    $target = $upload_dir . $sig_name;
    if (move_uploaded_file($_FILES['new_signature']['tmp_name'], $target)) {
        $update_fields[] = "signature_file = ?";
        $update_params[] = $target;
    }
}

// 8. Update Database
$sql = "UPDATE idcard_requests SET " . implode(", ", $update_fields) . " WHERE id = ?";
$update_params[] = $id;


try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($update_params);

    // จำตำแหน่งเข้าดิกชันนารี (Auto-learn)
    if (!empty($position)) {
        $conn->prepare("INSERT IGNORE INTO idcard_positions (position_name) VALUES (?)")->execute([$position]);
    }

    // 🟢 9. ดึงข้อมูลใหม่หลังจากอัปเดตเสร็จแล้ว (เพื่อเอาไปทำเปรียบเทียบใน Log)
    $stmt_new = $conn->prepare("SELECT * FROM idcard_requests WHERE id = ?");
    $stmt_new->execute([$id]);
    $new_data = $stmt_new->fetch(PDO::FETCH_ASSOC);
    // 🟢 10. บันทึก Log ด้วยระบบใหม่
    saveLog($conn, 'ADMIN_EDIT', "แอดมินแก้ไขข้อมูลและอัปเดตสถานะคำขอของ $full_name (ID: $id)", $id, $old_data, $new_data);

    // 🟢 11. ส่งการแจ้งเตือนทาง LINE (ถ้ามีการเปลี่ยนสถานะ และมีการเชื่อมต่อไว้)
    if ($status !== $old_data['status']) {
        require_once 'line_notify_helper.php';
        $status_map = [
            'PENDING_CHECK' => 'รอการตรวจสอบข้อมูล',
            'PENDING_APPROVAL' => 'รอผู้กำกับอนุมัติ',
            'REJECTED' => 'คำขอถูกปฏิเสธ (กรุณาตรวจสอบเหตุผล)',
            'SENT_TO_PRINT' => 'ส่งเรื่องพิมพ์บัตรแล้ว',
            'READY_PICKUP' => 'พิมพ์บัตรเสร็จแล้ว (พร้อมรับบัตร)',
            'COMPLETED' => 'รับบัตรเรียบร้อยแล้ว'
        ];
        $label = $status_map[$status] ?? $status;
        $id_card_raw = $old_data['id_card_number']; // ใช้จากข้อมูลเดิมที่ดึงมา
        $message = "🔔 แจ้งเตือนสถานะบัตรประชาชนตำรวจ\n\nสถานะล่าสุดของคุณ: $label\n\nตรวจสอบรายละเอียดเพิ่มเติมได้ที่:\nhttps://portal.pathumthani.police.go.th/idcard/track_status.php";
        
        sendLineNotification($id_card_raw, $message);
    }

    // 🟢 12. ตรวจสอบ Action ว่าให้ไปหน้าไหนต่อ (Work-flow)
    $save_action = $_POST['save_action'] ?? 'save';
    $user_role = $_SESSION['role'] ?? 'Staff';

    if ($save_action === 'save_and_print') {
        // 👮 เฉพาะ Super_Admin และ Admin เท่านั้นที่เด้งไปหน้าพิมพ์
        if (in_array($user_role, ['Super_Admin', 'Admin'])) {
            echo "<script>
                window.location.href = 'admin_print_card.php?id=$id';
            </script>";
        }
        else {
            // สิทธิ์อื่น (Staff) บันทึกสำเร็จแล้วให้เด้งกลับ Dashboard
            echo "<script>
                alert('บันทึกข้อมูลและส่งเรื่องพิมพ์เรียบร้อยแล้ว');
                window.location.href = 'admin_dashboard.php';
            </script>";
        }
    }
    else {
        // ถ้าบันทึกอย่างเดียว ให้กลับไปรีเฟรชหน้า Dashboard
        echo "<script>
            alert('บันทึกข้อมูลเรียบร้อยแล้ว');
            window.location.href = 'admin_dashboard.php';
        </script>";
    }

}
catch (PDOException $e) {
    die("Error Updating Record: " . $e->getMessage());
}
?>