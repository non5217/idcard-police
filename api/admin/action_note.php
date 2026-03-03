<?php
// api/admin/action_note.php
require_once '../../connect.php';
require_once '../../admin_auth.php'; // Ensure admin access

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$id_card_number = isset($_POST['id_card_number']) ? trim($_POST['id_card_number']) : '';
$note_text = isset($_POST['note_text']) ? trim($_POST['note_text']) : '';
$admin_name = $_SESSION['fullname'] ?? 'Unknown Admin';

if (empty($id_card_number) || empty($note_text)) {
    echo json_encode(['success' => false, 'message' => 'Missing ID Card Number or Note Text.']);
    exit();
}

try {
    // 🟢 Fix Charset Issue on the fly since CLI cannot alter the table
    $conn->exec("ALTER TABLE idcard_admin_notes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $stmt = $conn->prepare("INSERT INTO idcard_admin_notes (id_card_number, admin_name, note_text) VALUES (?, ?, ?)");
    $stmt->execute([$id_card_number, $admin_name, $note_text]);

    // Fetch back the created note for UI
    $note_id = $conn->lastInsertId();
    $fetchStmt = $conn->prepare("SELECT * FROM idcard_admin_notes WHERE id = ?");
    $fetchStmt->execute([$note_id]);
    $note = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'note' => $note]);
}
catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>