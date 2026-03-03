<?php
// idcard/api/save_request.php
require_once '../connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://portal.pathumthani.police.go.th');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // รับ JSON Payload
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception("Invalid JSON Payload");
    }

    $written_at = $data['written_at'] ?? 'ภ.จว.ปทุมธานี';
    $rank_id = $data['rank_id'] ?? 1;
    $first_name = $data['first_name'] ?? '';
    $last_name = $data['last_name'] ?? '';
    $full_name = trim($first_name . ' ' . $last_name);

    // Default values for missing data in the mocked frontend
    $birth_date = '1990-01-01';
    $age = 30;
    $nationality = 'ไทย';
    $blood_type = 'O';
    // Use a test ID card if session isn't set
    $id_card_number = $_SESSION['id_card_public'] ?? '0000000000000';
    $phone = '0800000000';
    $address_json = '{}';
    $officer_type = 'POLICE';
    $position = 'เจ้าหน้าที่';
    $org_id = 1;
    $card_type_id = 1;
    $reason = 'NEW';

    $upload_dir = realpath(__DIR__ . '/../../secure_uploads/') . '/';
    if (!file_exists($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $photo_path = '';
    if (!empty($data['face_image'])) {
        $img_data = explode(",", $data['face_image']);
        if (count($img_data) === 2) {
            $decoded = base64_decode($img_data[1]);
            $photo_name = "PHOTO_" . uniqid() . ".jpg";
            $photo_path = $upload_dir . $photo_name;
            file_put_contents($photo_path, $decoded);
        }
    }

    $sig_path = '';
    if (!empty($data['signature_image'])) {
        $img_data = explode(",", $data['signature_image']);
        if (count($img_data) === 2) {
            $decoded = base64_decode($img_data[1]);
            $sig_name = "SIG_" . uniqid() . ".png";
            $sig_path = $upload_dir . $sig_name;
            file_put_contents($sig_path, $decoded);
        }
    }

    // Insert into DB
    $sql = "INSERT INTO idcard_requests 
        (card_type_id, written_at, rank_id, full_name, birth_date, age, nationality, blood_type, 
        id_card_number, phone, address_json, officer_type, position, org_id, request_reason, 
        photo_path, signature_file, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING_CHECK', NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $card_type_id,
        $written_at,
        $rank_id,
        $full_name,
        $birth_date,
        $age,
        $nationality,
        $blood_type,
        $id_card_number,
        $phone,
        $address_json,
        $officer_type,
        $position,
        $org_id,
        $reason,
        $photo_path,
        $sig_path
    ]);

    echo json_encode(['success' => true, 'message' => 'บันทึกคำขอข้อมูลเรียบร้อยแล้ว']);

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>