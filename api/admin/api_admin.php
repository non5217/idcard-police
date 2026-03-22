<?php
// idcard/api/admin/api_admin.php
require_once '../../connect.php';

// เปิด Session หากยังไม่ได้เปิด
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// 1. Security Check
if (!isset($_SESSION['user_id']) || empty($_SESSION['role']) || $_SESSION['role'] === 'User') {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit();
}

// 1.5 🟢 ป้องกัน Cross-Site Request Forgery (CSRF) ตรวจสอบว่าเรียกมาจากเว็บเราเท่านั้น
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (empty($referer) || parse_url($referer, PHP_URL_HOST) !== $host) {
    echo json_encode(['success' => false, 'message' => 'CSRF Token Mismatch / Invalid Referer']);
    exit();
}

// 2. รับค่า JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'update_status') {
    $id = $input['id'];
    $status = $input['status'];
    $reason = ($status === 'REJECTED') ? $input['reason'] : NULL;

    try {
        // 🟢 1. ดึงข้อมูลเก่ามาเก็บไว้ก่อน (เพื่อทำ Log และเช็คการออกเลขบัตร)
        $stmt_old = $conn->prepare("SELECT status, full_name, birth_date, card_sequence, card_type_id FROM idcard_requests WHERE id = ?");
        $stmt_old->execute([$id]);
        $old_req = $stmt_old->fetch(PDO::FETCH_ASSOC);

        $old_status = $old_req['status'] ?? 'UNKNOWN';
        $req_name = $old_req['full_name'] ?? 'ไม่ทราบชื่อ';

        // 🟢 2. อัปเดตสถานะและเหตุผลใหม่
        $stmt = $conn->prepare("UPDATE idcard_requests SET status = ?, reject_reason = ? WHERE id = ?");
        $stmt->execute([$status, $reason, $id]);

        // 🟢 3. โลจิกออกเลขบัตร, วันออกบัตร และวันหมดอายุ (ยุบรวมเหลือชุดเดียว)
        $eligible_statuses = ['SENT_TO_PRINT', 'READY_PICKUP', 'COMPLETED'];

        // ถ้าสถานะอยู่ในกลุ่มที่ต้องพิมพ์บัตร และยังไม่เคยออกเลขบัตรมาก่อน
        if (in_array($status, $eligible_statuses) && empty($old_req['card_sequence'])) {
            require_once '../../helpers.php'; // สำหรับฟังก์ชัน calculateCardExpiry

            $current_year_th = (int)(date('Y') + 543);

            // หาค่ารันนิ่งล่าสุดของปีปัจจุบัน
            $stmt_max = $conn->prepare("SELECT MAX(card_sequence) FROM idcard_requests WHERE card_year = ?");
            $stmt_max->execute([$current_year_th]);
            $max_seq = $stmt_max->fetchColumn();

            $new_sequence = $max_seq ? $max_seq + 1 : 1;
            // กำหนดรูปแบบเลขบัตร: 0016.5 - ปี - ลำดับ 4 หลัก
            $generated_card_no = sprintf("0016.5 - %d - %04d", $current_year_th, $new_sequence);

            $is_pension = false;
            if (!empty($old_req['card_type_id'])) {
                $stmt_ctype = $conn->prepare("SELECT type_name FROM idcard_card_types WHERE id = ?");
                $stmt_ctype->execute([$old_req['card_type_id']]);
                if (strpos($stmt_ctype->fetchColumn(), 'บำนาญ') !== false) {
                    $is_pension = true;
                }
            }

            // คำนวณวันออกบัตรและวันหมดอายุ
            $issue_date = date('Y-m-d');
            $expire_date = calculateCardExpiry($old_req['birth_date'], $issue_date, $is_pension);

            // บันทึกเลขบัตรและวันที่ลงฐานข้อมูล
            $stmt_update_no = $conn->prepare("UPDATE idcard_requests SET card_sequence = ?, card_year = ?, generated_card_no = ?, issue_date = ?, expire_date = ? WHERE id = ?");
            $stmt_update_no->execute([$new_sequence, $current_year_th, $generated_card_no, $issue_date, $expire_date, $id]);
        }

        // 🟢 4. บันทึก Log การทำงานด้วยระบบใหม่
        $action_detail = "เปลี่ยนสถานะคำขอของ {$req_name} (ID: {$id}) เป็น {$status}";
        if ($status === 'REJECTED') {
            $action_detail .= " (เหตุผล: {$reason})";
        }

        // เรียกใช้ saveLog() เพื่อเก็บค่าสถานะเก่า และสถานะใหม่ เป็น JSON
        saveLog($conn, 'STATUS_CHANGE', $action_detail, $id,
        ['status' => $old_status],
        ['status' => $status, 'reject_reason' => $reason]
        );

        // 🟢 6. ส่งการแจ้งเตือนทาง LINE (ถ้ามีการผูกไว้)
        $stmt_line = $conn->prepare("SELECT line_user_id FROM idcard_requests WHERE id = ?");
        $stmt_line->execute([$id]);
        $line_user_id = $stmt_line->fetchColumn();

        if ($line_user_id) {
            $status_labels = [
                'PENDING_CHECK' => 'รอตรวจสอบ',
                'PENDING_APPROVAL' => 'รออนุมัติ',
                'SENT_TO_PRINT' => 'รอพิมพ์บัตร',
                'READY_PICKUP' => 'บัตรเสร็จแล้ว (กรุณามารับบัตร)',
                'COMPLETED' => 'รับบัตรเรียบร้อยแล้ว',
                'REJECTED' => 'คำขอถูกปฏิเสธ'
            ];
            $current_label = $status_labels[$status] ?? $status;
            
            $msg = "📢 แจ้งเตือนสถานะการทำบัตร\n"
                 . "คุณ {$req_name}\n"
                 . "สถานะใหม่: {$current_label}";
            
            if ($status === 'REJECTED' && $reason) {
                $msg .= "\nเหตุผล: {$reason}";
            }
            
            sendLineMessage($line_user_id, $msg);
        }

        echo json_encode(['success' => true]);
        exit();

    }
    catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
        exit();
    }
}

// 🟢 5. เพิ่มจำนวนครั้งที่พิมพ์บัตร
if ($action === 'increment_print_count') {
    $id = $input['id'];

    try {
        // อัปเดตเลขจำนวนครั้งที่พิมพ์ (+1)
        $stmt = $conn->prepare("UPDATE idcard_requests SET print_count = print_count + 1 WHERE id = ?");
        $stmt->execute([$id]);

        // ดึงข้อมูลมาทำ Log
        $stmt_info = $conn->prepare("SELECT full_name, print_count FROM idcard_requests WHERE id = ?");
        $stmt_info->execute([$id]);
        $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        saveLog($conn, 'PRINT_CARD', "พิมพ์บัตรของ {$info['full_name']} (ครั้งที่ {$info['print_count']})", $id);

        echo json_encode(['success' => true, 'print_count' => $info['print_count']]);
        exit();
    }
    catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
        exit();
    }
}

// กรณีไม่มี Action ที่ส่งมาตรงเงื่อนไข
echo json_encode(['success' => false, 'message' => 'Invalid Action']);
exit();
?>
