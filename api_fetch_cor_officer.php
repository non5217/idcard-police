<?php
/**
 * =========================================================================
 * API: ดึงข้อมูลกำลังพลจาก COR โดยใช้เลขบัตรประชาชน
 * DESCRIPTION: ค้นหาข้อมูลจาก 1cor_officers และส่งกลับในรูปแบบ JSON
 * สำหรับใช้ในหน้า idcard/admin_create.php
 * =========================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/connect.php';

// รับเลขบัตรประชาชนจาก query parameter
$id_card_number = $_GET['id_card'] ?? '';

// ตรวจสอบความถูกต้องของเลขบัตรประชาชน
if (empty($id_card_number) || !preg_match('/^[0-9]{13}$/', $id_card_number)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // ค้นหาข้อมูลจากตาราง 1cor_officers
    $sql = "SELECT 
        o.id_card_number,
        o.rank_id,
        o.rank_name_text,
        r.rank_name,
        o.first_name,
        o.last_name,
        o.birth_date,
        o.blood_type,
        o.phone,
        o.officer_type,
        o.position_name,
        o.org_id,
        o.org_name
    FROM 1cor_officers o
    LEFT JOIN idcard_ranks r ON o.rank_id = r.id
    WHERE o.id_card_number = :id_card
    LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_card' => $id_card_number]);
    $officer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($officer) {
        $display_rank = $officer['rank_name'] ?: $officer['rank_name_text'];
        // แปลงวันเกิดจาก Y-m-d เป็น วว/ดด/ปปปป (พ.ศ.)
        $birth_date_th = '';
        if (!empty($officer['birth_date']) && $officer['birth_date'] !== '0000-00-00') {
            $birth = new DateTime($officer['birth_date']);
            $birth_date_th = $birth->format('d/m/') . ($birth->format('Y') + 543);
        }

        // แปลง officer_type ให้ตรงกับค่าใน idcard
        $officer_type_map = [
            'POLICE' => 'POLICE',
            'PERMANENT_EMP' => 'PERMANENT_EMP',
            'GOV_EMP' => 'GOV_EMP',
            'ข้าราชการตำรวจ' => 'POLICE',
            'ลูกจ้างประจำ' => 'PERMANENT_EMP',
            'พนักงานราชการ' => 'GOV_EMP'
        ];
        $mapped_officer_type = $officer_type_map[$officer['officer_type']] ?? 'POLICE';

        echo json_encode([
            "status" => "success",
            "data" => [
                "id_card_number" => $officer['id_card_number'],
                "rank_id" => $officer['rank_id'],
                "rank_name" => $display_rank,
                "rank_name_text" => $officer['rank_name_text'],
                "first_name" => $officer['first_name'],
                "last_name" => $officer['last_name'],
                "birth_date" => $officer['birth_date'],
                "birth_date_th" => $birth_date_th,
                "blood_type" => $officer['blood_type'],
                "phone" => $officer['phone'],
                "officer_type" => $mapped_officer_type,
                "position_name" => $officer['position_name'],
                "org_id" => $officer['org_id'],
                "org_name" => $officer['org_name']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "ไม่พบข้อมูลกำลังพลในระบบ COR ที่มีเลขบัตรประชาชนนี้"
        ], JSON_UNESCAPED_UNICODE);
    }
}
catch (PDOException $e) {
    error_log("COR Lookup Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "เกิดข้อผิดพลาดในการค้นหาข้อมูล"
    ], JSON_UNESCAPED_UNICODE);
}
?>
