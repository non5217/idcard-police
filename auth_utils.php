<?php
// idcard/auth_utils.php
require_once __DIR__ . '/env_loader.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ฟังก์ชันตรวจสอบและ Refresh Token อัตโนมัติหากใกล้หมดอายุ
 * @return bool สำเร็จหรือไม่
 */
function refreshTokenIfNeeded() {
    // 1. ถ้าไม่มี Session หรือไม่มี Refresh Token แสดงว่าไม่ได้ล็อกอินแบบ OAuth 2.0 สมบูรณ์
    if (empty($_SESSION['access_token']) || empty($_SESSION['refresh_token'])) {
        return false;
    }

    // 2. เช็คเวลาหมดอายุ (เผื่อเวลาไว้ 5 นาที เพื่อไม่ให้ Token ขาดตอน)
    $expires_at = $_SESSION['access_token_expires_at'] ?? 0;
    if (time() < ($expires_at - 300)) {
        // Token ยังไม่หมดอายุ ใช้งานต่อได้เลย
        return true; 
    }

    // 3. Token หมดอายุแล้ว ให้ทำการ Refresh
    $token_url = CONSOLE_API_URL . '?action=oauth_token';
    $params = [
        'grant_type' => 'refresh_token',
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'refresh_token' => $_SESSION['refresh_token']
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        // Refresh ล้มเหลว (เช่น User กด Revoke จาก Portal ไปแล้ว)
        // บังคับ Logout ทันทีเพื่อความปลอดภัย
        session_destroy();
        return false;
    }

    $token_data = json_decode($response, true);
    
    // 4. บันทึก Token ใหม่ลง Session
    if (isset($token_data['access_token'])) {
        $_SESSION['access_token'] = $token_data['access_token'];
        $_SESSION['access_token_expires_at'] = time() + ($token_data['expires_in'] ?? 3600);
        
        // บาง IdP อาจจะส่ง Refresh Token แก๊งใหม่มาให้ด้วย (Rotate)
        if (!empty($token_data['refresh_token'])) {
            $_SESSION['refresh_token'] = $token_data['refresh_token'];
        }
        return true;
    }

    // ถ้าไม่มี access_token ใน response ถือว่าล้มเหลว
    session_destroy();
    return false;
}
?>
