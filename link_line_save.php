<?php
// idcard/link_line_save.php
require_once 'connect.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$userId = $_POST['userId'] ?? '';
$req_id = $_POST['req_id'] ?? '';
$id_card = $_SESSION['id_card_public'] ?? '';

if (!$userId || !$id_card) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

try {
    // ผูก userId กับคำขอชิ้ันนี้ (หรือทุกชิ้นของบัตรใบนี้)
    if ($req_id) {
        $stmt = $conn->prepare("UPDATE idcard_requests SET line_user_id = ? WHERE id = ? AND id_card_number = ?");
        $stmt->execute([$userId, $req_id, $id_card]);
    } else {
        $stmt = $conn->prepare("UPDATE idcard_requests SET line_user_id = ? WHERE id_card_number = ?");
        $stmt->execute([$userId, $id_card]);
    }

    saveLog($conn, 'LINK_LINE', "ผูกบัญชี LINE กับคำขอ " . ($req_id ? "ID:$req_id" : "All"), $req_id);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
