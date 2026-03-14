<?php
// idcard/process_public_check.php
require_once 'connect.php';
require_once 'env_loader.php'; // Load environment variables
if (session_status() === PHP_SESSION_NONE)
    session_start();

// 🛡️ Rate Limiting: ป้องกัน Brute Force — อนุญาตสูงสุด 10 ครั้ง ภายใน 5 นาที
$now = time();
$window = 300; // 5 นาที
$max_attempts = 10;

if (!isset($_SESSION['check_attempts'])) {
    $_SESSION['check_attempts'] = ['count' => 0, 'start' => $now];
}

// ถ้าช่วงเวลาหมดแล้ว ให้รีเซ็ต
if ($now - $_SESSION['check_attempts']['start'] > $window) {
    $_SESSION['check_attempts'] = ['count' => 0, 'start' => $now];
}

if ($_SESSION['check_attempts']['count'] >= $max_attempts) {
    $wait = $window - ($now - $_SESSION['check_attempts']['start']);
    header('Content-Type: application/json');
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'type' => 'rate_limit',
        'message' => "คุณทำรายการบ่อยเกินไป กรุณารอ " . ceil($wait / 60) . " นาทีแล้วลองใหม่อีกครั้ง"
    ]);
    exit;
}
$_SESSION['check_attempts']['count']++;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_card = trim($_POST['id_card_number']);
    $phone = trim($_POST['phone_number']); // รับเบอร์โทร
    $action = $_POST['action_type']; // 'REQUEST' หรือ 'TRACK'

    // 1. โค้ดตรวจสอบ Cloudflare Turnstile ด้วย cURL
    $turnstile_secret = TURNSTILE_SECRET; // Load from environment variables
    $turnstile_response = $_POST['cf-turnstile-response'] ?? '';

    // ใช้ cURL ยิงข้อมูลไปถาม Cloudflare
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://challenges.cloudflare.com/turnstile/v0/siteverify");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $turnstile_secret,
        'response' => $turnstile_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $response_data = json_decode($response);

    if (!$response_data || !$response_data->success) {
        $msg = 'ยืนยันตัวตนไม่สำเร็จ (คุณอาจเป็นบอท หรือ Token หมดอายุ)';
        if ($response_data && isset($response_data->{ 'error-codes'})) {
            $msg .= ' | Error: ' . implode(', ', $response_data->{ 'error-codes'});
        }
        elseif (!$response_data) {
            $msg .= ' | cURL Error: ' . $curl_error;
        }
        echo json_encode(['status' => 'error', 'type' => 'turnstile', 'message' => $msg]);
        exit;
    }
    // 🟢 สิ้นสุดโค้ดตรวจสอบ Turnstile

    // 2. ตรวจสอบความถูกต้องของเลขบัตร
    if (strlen($id_card) !== 13 || !is_numeric($id_card)) {
        echo json_encode(['status' => 'error', 'type' => 'invalid_id', 'message' => 'เลขประจำตัวประชาชนไม่ถูกต้อง']);
        exit;
    }

    // 3. ค้นหาคำขอล่าสุดของคนนี้ (ไม่นับสถานะยกเลิก)
    $stmt = $conn->prepare("SELECT * FROM idcard_requests WHERE id_card_number = ? AND status != 'CANCELLED' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$id_card]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    // 🛑 4. กฎการตรวจสอบความปลอดภัย (Privacy Check)
    if ($req) {
        // ถ้ามีประวัติในระบบ -> เบอร์โทรต้องตรงกับฐานข้อมูลเป๊ะๆ
        if ($req['phone'] !== $phone) {
            echo json_encode(['status' => 'error', 'type' => 'phone_mismatch', 'message' => 'เบอร์โทรศัพท์ไม่ตรงกับข้อมูลในระบบ']);
            exit;
        }
    }
    else {
        // ถ้าไม่มีประวัติในระบบ แต่กด "ติดตามสถานะ"
        if ($action === 'TRACK') {
            echo json_encode(['status' => 'error', 'type' => 'not_found', 'message' => 'ยังไม่มีประวัติการยื่นคำขอของเลขบัตรประชาชนนี้']);
            exit;
        }
    }

    // --- ผ่านการตรวจสอบ อนุญาตให้ไปต่อได้ ---
    $_SESSION['public_access'] = true;
    $_SESSION['id_card_public'] = $id_card;
    $_SESSION['phone_public'] = $phone; // 🆕 เก็บเบอร์ไว้เอาไป Pre-fill ให้

    $req_state = 'new';
    $reject_reason = '';

    if ($req) {
        $_SESSION['form_prefill'] = $req;

        if (in_array($req['status'], ['PENDING_CHECK', 'PENDING_APPROVAL', 'SENT_TO_PRINT'])) {
            $_SESSION['edit_request_id'] = $req['id'];
            $req_state = 'pending';
        }
        elseif ($req['status'] === 'REJECTED') {
            $_SESSION['edit_request_id'] = $req['id'];
            $req_state = 'rejected';
            $reject_reason = $req['reject_reason'] ?? '';
        }
        else {
            // READY_PICKUP, COMPLETED ถือว่าเป็นบัตรที่เสร็จแล้ว สามารถขึ้นใหม่ได้ (กรณีสูญหาย)
            unset($_SESSION['edit_request_id']);
            $req_state = 'completed';
        }
    }
    else {
        unset($_SESSION['form_prefill']);
        unset($_SESSION['edit_request_id']);
    }

    if ($action === 'TRACK') {
        echo json_encode(['status' => 'success', 'action' => 'redirect', 'url' => 'track_status.php']);
    }
    else {
        if ($req_state === 'new') {
            echo json_encode(['status' => 'success', 'action' => 'redirect', 'url' => 'request.php']);
        }
        else {
            // แจ้งสถานะปัจจุบันให้ frontend ตัดสินใจ
            echo json_encode([
                'status' => 'success',
                'action' => 'prompt',
                'req_state' => $req_state, // pending, rejected, completed
                'db_status' => $req['status'], // PENDING_CHECK, etc.
                'reject_reason' => $reject_reason
            ]);
        }
    }
    exit;
}