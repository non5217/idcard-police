<?php
// api/admin/action_cancel_request.php
require_once '../../connect.php';
require_once '../../admin_auth.php'; // Ensure admin access

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$request_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($request_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request ID.']);
    exit();
}

try {
    // Confirm the request exists and isn't already cancelled
    $stmt = $conn->prepare("SELECT id, id_card_number, full_name, status, line_user_id FROM idcard_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit();
    }

    if ($req['status'] === 'CANCELLED') {
        echo json_encode(['success' => false, 'message' => 'Request is already cancelled.']);
        exit();
    }

    // 🟢 Fix ENUM Issue on the fly: Convert 'status' column to VARCHAR to support 'CANCELLED'
    $conn->exec("ALTER TABLE idcard_requests MODIFY status VARCHAR(50) NOT NULL DEFAULT 'PENDING_CHECK'");

    // Update status to CANCELLED
    $updateStmt = $conn->prepare("UPDATE idcard_requests SET status = 'CANCELLED' WHERE id = ?");
    $updateStmt->execute([$request_id]);

    // Log the cancellation attempt
    saveLog($conn, 'CANCEL_REQUEST', "ยกเลิกคำขอ ID: $request_id (เลขบัตร: " . $req['id_card_number'] . ")", $request_id);

    // 🟢 แจ้งเตือนทาง LINE (ถ้ามีการผูกไว้)
    if (!empty($req['line_user_id'])) {
        $msg = "📢 แจ้งเตือนสถานะการทำบัตร\n"
             . "คุณ {$req['full_name']}\n"
             . "ขณะนี้: คำขอของคุณถูกยกเลิกแล้ว";
        sendLineMessage($req['line_user_id'], $msg);
    }

    echo json_encode(['success' => true, 'message' => 'Request successfully cancelled.']);
}
catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>