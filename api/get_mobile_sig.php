<?php
// idcard/api/get_mobile_sig.php
header('Content-Type: application/json');

$sig_id = $_GET['sig_id'] ?? '';

if (empty($sig_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing signature ID']);
    exit();
}

// Validate sig_id
if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $sig_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature ID format']);
    exit();
}

$temp_dir = __DIR__ . '/../temp_signatures/';
$filename = $temp_dir . 'sig_' . $sig_id . '.txt';

if (file_exists($filename)) {
    $data = file_get_contents($filename);
    // Delete file after reading if it's older than 5 minutes (clean up)
    // Or just let it be and use a cron later if needed. 
    // For simplicity, let's just return it. 
    // The client will stop polling once they get data.
    unlink($filename); // Consume it
    
    echo json_encode(['status' => 'success', 'sig_data' => $data]);
} else {
    echo json_encode(['status' => 'pending', 'message' => 'Waiting for signature...']);
}
?>
