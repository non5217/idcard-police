<?php
// idcard/api/save_mobile_sig.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

$sig_id = $_POST['sig_id'] ?? '';
$sig_data = $_POST['sig_data'] ?? '';

if (empty($sig_id) || empty($sig_data)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing signature ID or data']);
    exit();
}

// Validate sig_id (prevent directory traversal)
if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $sig_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature ID format']);
    exit();
}

$temp_dir = __DIR__ . '/../temp_signatures/';
if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0777, true);
}

$filename = $temp_dir . 'sig_' . $sig_id . '.txt';
if (file_put_contents($filename, $sig_data)) {
    echo json_encode(['status' => 'success', 'message' => 'Signature saved successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save signature file']);
}
?>
