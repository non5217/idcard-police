<?php
// idcard/admin_settings.php
require_once 'connect.php';
require_once 'admin_auth.php'; // 🔒 ล็อกสิทธิ์เฉพาะแอดมิน

// 🟢 บันทึก Log เมื่อมีคนเปิดเข้ามาดูหน้าตั้งค่า
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    saveLog($conn, 'VIEW_SETTINGS', 'เปิดดูหน้าตั้งค่าระบบ');
}

// 🟢 ฟังก์ชันแปลงภาพเป็น Base64 ฝังลงหน้าเว็บโดยตรง
function getBase64Img($path)
{
    if (empty($path) || !file_exists($path))
        return '';
    $data = @file_get_contents($path);
    if (!$data)
        return '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $swal_back = function ($icon, $title, $text) {
        return "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><body style='background:#f3f4f6;'><script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: '$icon', title: '$title', text: '$text', confirmButtonColor: '#3085d6' }).then(() => { window.history.back(); }); });</script></body>";
    };
    $swal_reload = function ($icon, $title, $text) {
        return "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><body style='background:#f3f4f6;'><script>document.addEventListener('DOMContentLoaded', function () { Swal.fire({ icon: '$icon', title: '$title', text: '$text', confirmButtonColor: '#3085d6' }).then(() => { window.location.href = 'admin_settings.php'; }); });</script></body>";
    };

    // 🟢 ตรวจสอบ CSRF Token ก่อนยอมให้ตั้งค่าระบบ
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die($swal_back('error', 'Security Error', '⛔ ตรวจพบความพยายามเจาะระบบ (CSRF)'));
    }

    $action = $_POST['action'] ?? '';

    // --- 🟢 ระบบตั้งค่าเลขรันนิ่งล่าสุด ---
    if ($action === 'set_last_sequence') {
        $year = (int)$_POST['card_year'];
        $seq = (int)$_POST['last_sequence'];

        $stmt_check = $conn->prepare("SELECT MAX(card_sequence) FROM idcard_requests WHERE card_year = ?");
        $stmt_check->execute([$year]);
        $current_max = (int)$stmt_check->fetchColumn();

        if ($seq <= $current_max) {
            die($swal_back('error', 'ผิดพลาด', "❌ ตั้งค่าไม่สำเร็จ! เลขที่ตั้ง ($seq) ต้องมากกว่าเลขสูงสุดปัจจุบันในระบบ ($current_max)"));
        }
        else {
            $ct_id = $conn->query("SELECT id FROM idcard_card_types LIMIT 1")->fetchColumn() ?: 1;
            $rk_id = $conn->query("SELECT id FROM idcard_ranks LIMIT 1")->fetchColumn() ?: 1;
            $og_id = $conn->query("SELECT id FROM idcard_organizations LIMIT 1")->fetchColumn() ?: 1;

            $dummy_no = sprintf("0016.5 - %d - %04d", $year, $seq);

            $sql = "INSERT INTO idcard_requests 
                    (user_id, card_type_id, rank_id, full_name, birth_date, age, blood_type, 
                    id_card_number, phone, address_json, contact_address_type, contact_address_json,
                    officer_type, position, org_id, request_reason, documents_json, status, reject_reason,
                    card_sequence, card_year, generated_card_no, created_at, photo_path, signature_file) 
                    VALUES (0, ?, ?, '*** ตั้งค่าเลขเริ่มต้นระบบ ***', '1970-01-01', 0, 'O', 
                    '0000000000000', '-', '{}', 'SAME', '', 'POLICE', '-', ?, 'OTHER', '[]', 'REJECTED', 'SYSTEM_SEED', 
                    ?, ?, ?, NOW(), '', '')";
            $conn->prepare($sql)->execute([$ct_id, $rk_id, $og_id, $seq, $year, $dummy_no]);

            // บันทึก Log การตั้งค่าเลขรันนิ่ง
            saveLog($conn, 'SETTING_SEQUENCE', "ตั้งค่าเลขลำดับเริ่มต้น ปี $year เป็น $seq", null, ['old_max' => $current_max], ['new_sequence' => $seq, 'year' => $year]);

            die($swal_reload('success', 'สำเร็จ', "✅ ตั้งค่าเลขล่าสุดสำเร็จ! (เลขต่อไปที่จะถูกรันคือ " . ($seq + 1) . ")"));
        }
    }

    // --- 🟢 1. จัดการคนเซ็น ---
    elseif ($action === 'add_signer') {
        $type = $_POST['signer_type'];
        $rank = $_POST['rank_name'] ?? NULL;
        $name = trim($_POST['full_name']);
        $pos = $_POST['position'] ?? NULL;

        $raw_dir = __DIR__ . "/../../secure_uploads/setting/";
        if (!file_exists($raw_dir))
            mkdir($raw_dir, 0755, true);
        $upload_dir = realpath($raw_dir) . "/";

        if (!empty($_FILES['signature_file']['name'])) {
            $ext = strtolower(pathinfo($_FILES['signature_file']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['png', 'jpg', 'jpeg'];
            if (!in_array($ext, $allowed_exts)) {
                die($swal_back('error', 'ข้อผิดพลาด', "⛔ Error: ไม่อนุญาตให้อัปโหลดไฟล์สกุล .$ext"));
            }

            $target = $upload_dir . "sig_" . strtolower($type) . "_" . time() . "_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES['signature_file']['tmp_name'], $target)) {
                $check = $conn->prepare("SELECT count(*) FROM idcard_signers WHERE signer_type = ?");
                $check->execute([$type]);
                $is_active = ($check->fetchColumn() == 0) ? 1 : 0;

                $conn->prepare("INSERT INTO idcard_signers (signer_type, rank_name, full_name, position, signature_path, is_active) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$type, $rank, $name, $pos, $target, $is_active]);

                // บันทึก Log เพิ่มลายเซ็น
                saveLog($conn, 'SETTING_SIGNER', "เพิ่มผู้ออก/ตรวจบัตรใหม่: $name ($type)", null, null, ['name' => $name, 'rank' => $rank, 'position' => $pos]);
                $msg = "เพิ่มข้อมูลลายเซ็นสำเร็จ";
            }
        }
    }
    elseif ($action === 'set_active_signer') {
        $id = $_POST['signer_id'];
        $type = $_POST['signer_type'];

        $conn->prepare("UPDATE idcard_signers SET is_active = 0 WHERE signer_type = ?")->execute([$type]);
        $conn->prepare("UPDATE idcard_signers SET is_active = 1 WHERE id = ?")->execute([$id]);

        // บันทึก Log เลือกผู้ลงนาม
        saveLog($conn, 'SETTING_SIGNER', "ตั้งค่าให้ ID: $id เป็นผู้ลงนาม/ตรวจบัตรหลัก ($type)", $id);
        $msg = "เปลี่ยนผู้ลงนามปัจจุบันเรียบร้อยแล้ว";
    }
    elseif ($action === 'delete_signer') {
        $id = $_POST['signer_id'];
        try {
            $stmt = $conn->prepare("SELECT full_name, signature_path FROM idcard_signers WHERE id = ?");
            $stmt->execute([$id]);
            $signer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($signer['signature_path'] && file_exists($signer['signature_path']))
                unlink($signer['signature_path']);
            $conn->prepare("DELETE FROM idcard_signers WHERE id = ?")->execute([$id]);

            // บันทึก Log ลบลายเซ็น
            saveLog($conn, 'SETTING_SIGNER', "ลบลายเซ็นของ: " . ($signer['full_name'] ?? 'ไม่ทราบชื่อ') . " (ID: $id)", $id);
            $msg = "ลบข้อมูลสำเร็จ";
        }
        catch (PDOException $e) {
            $msg = "❌ ไม่สามารถลบได้ เนื่องจากถูกใช้งานในประวัติการทำบัตรแล้ว";
        }
    }

    // --- 🟢 2. จัดการหน่วยงาน ---
    elseif ($action === 'add_org') {
        $org_name = trim($_POST['org_name']);
        if ($org_name) {
            $conn->prepare("INSERT INTO idcard_organizations (org_name, is_active) VALUES (?, 1)")->execute([$org_name]);
            saveLog($conn, 'SETTING_ORG', "เพิ่มหน่วยงานใหม่: $org_name", null, null, ['org_name' => $org_name]);
            $msg = "เพิ่มหน่วยงานสำเร็จ";
        }
    }
    elseif ($action === 'edit_org') {
        $id = $_POST['org_id'];
        $org_name = trim($_POST['org_name']);

        $stmt_old = $conn->prepare("SELECT org_name FROM idcard_organizations WHERE id = ?");
        $stmt_old->execute([$id]);
        $old_org = $stmt_old->fetchColumn();
        $conn->prepare("UPDATE idcard_organizations SET org_name = ? WHERE id = ?")->execute([$org_name, $id]);
        saveLog($conn, 'SETTING_ORG', "แก้ไขชื่อหน่วยงาน (ID: $id)", $id, ['org_name' => $old_org], ['org_name' => $org_name]);
        $msg = "แก้ไขหน่วยงานสำเร็จ";
    }
    elseif ($action === 'delete_org') {
        $id = $_POST['org_id'];
        try {
            $stmt_old = $conn->prepare("SELECT org_name FROM idcard_organizations WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_org = $stmt_old->fetchColumn();
            $conn->prepare("DELETE FROM idcard_organizations WHERE id = ?")->execute([$id]);
            saveLog($conn, 'SETTING_ORG', "ลบหน่วยงาน: $old_org (ID: $id)", $id, ['org_name' => $old_org], null);
            $msg = "ลบหน่วยงานสำเร็จ";
        }
        catch (PDOException $e) {
            $msg = "❌ ไม่สามารถลบได้ เนื่องจากมีประวัติคำขอที่ใช้หน่วยงานนี้อยู่";
        }
    }

    // --- 🟢 3. จัดการตำแหน่ง ---
    elseif ($action === 'add_position') {
        $pos = trim($_POST['position_name']);
        $org_id = !empty($_POST['default_org_id']) ? $_POST['default_org_id'] : NULL;
        if ($pos) {
            $conn->prepare("INSERT IGNORE INTO idcard_positions (position_name, default_org_id) VALUES (?, ?)")->execute([$pos, $org_id]);
            saveLog($conn, 'SETTING_POS', "เพิ่มตำแหน่ง: $pos", null, null, ['position_name' => $pos, 'default_org_id' => $org_id]);
            $msg = "เพิ่มตำแหน่งเข้าในตัวช่วยกรอกสำเร็จ";
        }
    }
    elseif ($action === 'edit_position') {
        $id = $_POST['pos_id'];
        $pos = trim($_POST['position_name']);
        $org_id = !empty($_POST['default_org_id']) ? $_POST['default_org_id'] : NULL;

        // ดึงค่าเก่ามาเก็บ Log
        $stmt = $conn->prepare("SELECT position_name, default_org_id FROM idcard_positions WHERE id = ?");
        $stmt->execute([$id]);
        $old_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // อัปเดตทั้งชื่อตำแหน่งและสังกัด
        $conn->prepare("UPDATE idcard_positions SET position_name = ?, default_org_id = ? WHERE id = ?")->execute([$pos, $org_id, $id]);

        saveLog($conn, 'SETTING_POS', "แก้ไขข้อมูลตำแหน่ง (ID: $id)", $id, $old_data, ['position_name' => $pos, 'default_org_id' => $org_id]);
        $msg = "แก้ไขตำแหน่งและผูกสังกัดสำเร็จ";
    }
    elseif ($action === 'delete_position') {
        $id = $_POST['pos_id'];
        $stmt_old = $conn->prepare("SELECT position_name FROM idcard_positions WHERE id = ?");
        $stmt_old->execute([$id]);
        $old_pos = $stmt_old->fetchColumn();
        $conn->prepare("DELETE FROM idcard_positions WHERE id = ?")->execute([$id]);
        saveLog($conn, 'SETTING_POS', "ลบตำแหน่ง: $old_pos (ID: $id)", $id, ['position_name' => $old_pos], null);
        $msg = "ลบตำแหน่งออกจากตัวเลือกสำเร็จ";
    }
    elseif ($action === 'export_positions_csv') {
        // ดึงข้อมูลตำแหน่งพร้อมชื่อหน่วยงาน
        $positions = $conn->query("
            SELECT p.position_name, o.org_name 
            FROM idcard_positions p 
            LEFT JOIN idcard_organizations o ON p.default_org_id = o.id 
            ORDER BY p.position_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Header สำหรับดาวน์โหลดไฟล์ CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="positions_export_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        // เพิ่ม BOM เพื่อให้ Excel อ่านภาษาไทยได้ถูกต้อง
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // เขียน Header ของ CSV
        fputcsv($output, ['ชื่อตำแหน่ง', 'ผูกสังกัด']);

        foreach ($positions as $p) {
            fputcsv($output, [$p['position_name'], $p['org_name']]);
        }

        fclose($output);
        saveLog($conn, 'SETTING_POS', "ส่งออกไฟล์ตำแหน่ง (CSV)", null, null, null);
        exit();
    }
    elseif ($action === 'import_positions_csv') {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                die($swal_back('error', 'ข้อผิดพลาด', "⛔ Error: กรุณาอัปโหลดไฟล์นามสกุล .csv เท่านั้น"));
            }

            $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
            if ($handle !== FALSE) {
                // ข้าม BOM ถ้ามี และอ่าน Header ทิ้ง
                $line = fgets($handle);
                // ตรวจสอบ BOM (EF BB BF)
                if (strncmp($line, "\xEF\xBB\xBF", 3) === 0) {
                    $line = substr($line, 3);
                }
                // ถ้าไม่ใช่ Header ก็เริ่มอ่านข้อมูล (ส่วนใหญ่จะเป็นระบุ header มา)
                // ตรวจสอบง่ายๆ ว่าเป็นคอลัมน์ "ชื่อตำแหน่ง" หรือไม่ ถ้าใช่ให้ข้ามไปบรรทัดถัดไป
                // ถ้าไม่มั่นใจ ให้ใช้ fgetcsv ตั้งแต่เริ่ม

                rewind($handle);
                // ข้าม BOM ใน stream จริง
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                $header = fgetcsv($handle, 1000, ","); // ข้าม Header

                // ดึงรายชื่อหน่วยงานทั้งหมดเพื่อตรวจสอบตอน Import
                $orgs_rows = $conn->query("SELECT id, org_name FROM idcard_organizations")->fetchAll(PDO::FETCH_ASSOC);
                $orgs_map = [];
                foreach ($orgs_rows as $or) {
                    $orgs_map[$or['org_name']] = $or['id'];
                }

                $importedCount = 0;
                $skippedCount = 0;

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $pos_name = trim($data[0] ?? '');
                    $org_name = trim($data[1] ?? '');

                    if (empty($pos_name))
                        continue;

                    $org_id = null;
                    if (!empty($org_name)) {
                        // ค้นหา ID จากชื่อ
                        if (isset($orgs_map[$org_name])) {
                            $org_id = $orgs_map[$org_name];
                        }
                        else {
                            // ถ้าไม่เจอชื่อหน่วยงานก็อาจจะไม่ผูก หรือจะสร้างใหม่ (ในกรณีนี้ให้ไม่ผูกตาม Requirement ไม่ได้บอกให้สร้างใหม่)
                            $org_id = null;
                        }
                    }

                    // Check if exist
                    $check = $conn->prepare("SELECT COUNT(*) FROM idcard_positions WHERE position_name = ?");
                    $check->execute([$pos_name]);
                    if ($check->fetchColumn() == 0) {
                        $stmt = $conn->prepare("INSERT INTO idcard_positions (position_name, default_org_id) VALUES (?, ?)");
                        $stmt->execute([$pos_name, $org_id]);
                        $importedCount++;
                    }
                    else {
                        // อาจจะมีการปรับ Default ORG ID ถ้ามีอยู่แล้วหรือไม่
                        // ตาม Requirement "หากตำแหน่งซ้ำกับที่มีอยู่ให้ถือว่าเป็นตำแหน่งเดียวกัน" แปลว่าข้ามไป หรือแค่อัปเดต
                        $skippedCount++;
                    }
                }
                fclose($handle);
                saveLog($conn, 'SETTING_POS', "นำเข้าตำแหน่งจากไฟล์ CSV (สำเร็จ: $importedCount, ข้าม/ซ้ำ: $skippedCount)", null, null, null);
                $msg = "นำเข้าข้อมูลสำเร็จ $importedCount รายการ (ข้ามข้อมูลซ้ำ $skippedCount รายการ)";
            }
            else {
                $msg = "❌ ไม่สามารถอ่านไฟล์ CSV ได้";
            }
        }
    }

    // --- 🟢 4. จัดการการแจ้งเตือน (Notifications) ---
    elseif ($action === 'update_notifications') {
        // 👮 เฉพาะ Super_Admin เท่านั้นที่บันทึกได้
        if ($_SESSION['role'] !== 'Super_Admin') {
            die("⛔ Access Denied: คุณไม่มีสิทธิ์แก้ไขการตั้งค่าส่วนนี้");
        }

        $discord = trim($_POST['discord_webhook_url']);
        $line_token = trim($_POST['line_channel_access_token']);
        $line_user = trim($_POST['line_user_id']);
        $tg_bot = trim($_POST['telegram_bot_token']);
        $tg_chat = trim($_POST['telegram_chat_id']);

        $settings = [
            'discord_webhook_url' => $discord,
            'line_channel_access_token' => $line_token,
            'line_user_id' => $line_user,
            'telegram_bot_token' => $tg_bot,
            'telegram_chat_id' => $tg_chat
        ];

        foreach ($settings as $key => $val) {
            $stmt = $conn->prepare("UPDATE idcard_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$val, $key]);
        }

        saveLog($conn, 'SETTING_NOTIFICATIONS', 'อัปเดตการตั้งค่าการแจ้งเตือน (Discord/Line/Telegram)');
        $msg = "อัปเดตการตั้งค่าการแจ้งเตือนสำเร็จ";
    }


    if (isset($msg)) {
        $icon = (strpos($msg, '❌') !== false || strpos($msg, 'Error') !== false) ? 'error' : 'success';
        $title = $icon === 'error' ? 'เกิดข้อผิดพลาด' : 'สำเร็จ';
        die($swal_reload($icon, $title, $msg));
    }
}

// --- ดึงข้อมูลมาแสดงผล ---
$issuers = $conn->query("SELECT * FROM idcard_signers WHERE signer_type = 'ISSUER' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$inspectors = $conn->query("SELECT * FROM idcard_signers WHERE signer_type = 'INSPECTOR' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$orgs = $conn->query("SELECT * FROM idcard_organizations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$positions_list = $conn->query("SELECT p.*, o.org_name FROM idcard_positions p LEFT JOIN idcard_organizations o ON p.default_org_id = o.id ORDER BY p.position_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ดึงค่าตั้งค่าการแจ้งเตือน
$nt_settings = $conn->query("SELECT setting_key, setting_value FROM idcard_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$discord_webhook = $nt_settings['discord_webhook_url'] ?? '';
$line_channel_token = $nt_settings['line_channel_access_token'] ?? '';
$line_user_id = $nt_settings['line_user_id'] ?? '';
$line_msg_count = (int)($nt_settings['line_msg_count'] ?? 0);
$tg_bot_token = $nt_settings['telegram_bot_token'] ?? '';
$tg_chat_id = $nt_settings['telegram_chat_id'] ?? '';

// ==========================================
// 🟢 ดึงข้อมูลเลขรันนิ่งปัจจุบันมาโชว์แอดมิน
// ==========================================
$current_y_th = (int)(date('Y') + 543);
$stmt_max = $conn->prepare("SELECT MAX(card_sequence) FROM idcard_requests WHERE card_year = ?");
$stmt_max->execute([$current_y_th]);
$current_max_seq = (int)$stmt_max->fetchColumn();
// ==========================================
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ตั้งค่าระบบ - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-100 pb-10">

    <?php include 'admin_navbar.php'; ?>

    <div class="container mx-auto px-4 max-w-6xl mt-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6"><i class="fas fa-cog"></i> จัดการตั้งค่าระบบ</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div class="bg-white p-6 rounded-lg shadow-md border-t-4 border-blue-600">
                <h2 class="text-xl font-bold mb-4 text-blue-800"><i class="fas fa-user-tie"></i> 1. ทำเนียบผู้ออกบัตร
                    (ผู้บังคับบัญชา)</h2>

                <form action="" method="POST" enctype="multipart/form-data"
                    class="flex flex-col gap-2 mb-6 bg-blue-50 p-4 rounded border">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                    <input type="hidden" name="action" value="add_signer">
                    <input type="hidden" name="signer_type" value="ISSUER">
                    <div class="flex gap-2">
                        <div class="w-1/3"><label class="text-sm font-bold">ยศ</label><input type="text"
                                name="rank_name" class="w-full border p-2 rounded" required></div>
                        <div class="flex-1"><label class="text-sm font-bold">ชื่อ-สกุล</label><input type="text"
                                name="full_name" class="w-full border p-2 rounded" required></div>
                    </div>
                    <div><label class="text-sm font-bold">ตำแหน่ง</label><input type="text" name="position"
                            class="w-full border p-2 rounded" required></div>
                    <div><label class="text-sm font-bold">ไฟล์ลายเซ็น (PNG พื้นใส)</label><input type="file"
                            name="signature_file" accept="image/*" class="w-full border p-1 rounded bg-white" required>
                    </div>
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-bold mt-2">+
                        เพิ่มผู้ออกบัตรใหม่</button>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2 border">สถานะ</th>
                                <th class="p-2 border">ชื่อและตำแหน่ง</th>
                                <th class="p-2 border">ลายเซ็น</th>
                                <th class="p-2 border">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($issuers as $i): ?>
                            <tr>
                                <td class="p-2 border text-center">
                                    <?php if ($i['is_active']): ?>
                                    <span
                                        class="bg-green-500 text-white px-2 py-1 rounded text-xs font-bold">ใช้งานอยู่</span>
                                    <?php
    else: ?>
                                    <form action="" method="POST"><input type="hidden" name="csrf_token"
                                            value="<?= $_SESSION['csrf_token']?>"><input type="hidden" name="action"
                                            value="set_active_signer"><input type="hidden" name="signer_type"
                                            value="ISSUER"><input type="hidden" name="signer_id"
                                            value="<?= $i['id']?>"><button type="submit"
                                            class="bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded text-xs text-gray-700 font-bold">เลือกใช้</button>
                                    </form>
                                    <?php
    endif; ?>
                                </td>
                                <td class="p-2 border leading-tight">
                                    <strong>
                                        <?= $i['rank_name'] . ' ' . $i['full_name']?>
                                    </strong><br>
                                    <span class="text-xs text-gray-500">
                                        <?= $i['position']?>
                                    </span>
                                </td>
                                <td class="p-2 border"><img src="<?= getBase64Img($i['signature_path'])?>"
                                        class="h-8 max-w-[100px] object-contain"></td>
                                <td class="p-2 border text-center">
                                    <form action="" method="POST"
                                        onsubmit="event.preventDefault(); swalConfirm(event, 'ยืนยันการลบลายเซ็นนี้? (ถ้าถูกใช้ไปแล้วระบบจะปฏิเสธการลบ)');">
                                        <input type="hidden" name="csrf_token"
                                            value="<?= $_SESSION['csrf_token']?>"><input type="hidden" name="action"
                                            value="delete_signer"><input type="hidden" name="signer_id"
                                            value="<?= $i['id']?>"><button type="submit"
                                            class="text-red-500 hover:text-red-700"><i
                                                class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md border-t-4 border-yellow-500">
                <h2 class="text-xl font-bold mb-4 text-yellow-700"><i class="fas fa-search"></i> 2. ผู้ตรวจบัตร
                    (เฉพาะลายเซ็น)</h2>

                <form action="" method="POST" enctype="multipart/form-data"
                    class="flex flex-col gap-2 mb-6 bg-yellow-50 p-4 rounded border">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                    <input type="hidden" name="action" value="add_signer">
                    <input type="hidden" name="signer_type" value="INSPECTOR">
                    <div><label class="text-sm font-bold">ชื่ออ้างอิง (เช่น ชุดที่ 1, นาย เอ)</label><input type="text"
                            name="full_name" class="w-full border p-2 rounded" required></div>
                    <div><label class="text-sm font-bold">ไฟล์ลายเซ็น (PNG พื้นใส)</label><input type="file"
                            name="signature_file" accept="image/*" class="w-full border p-1 rounded bg-white" required>
                    </div>
                    <button type="submit"
                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded font-bold mt-2">+
                        เพิ่มผู้ตรวจบัตรใหม่</button>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2 border">สถานะ</th>
                                <th class="p-2 border">ชื่ออ้างอิง</th>
                                <th class="p-2 border">ลายเซ็น</th>
                                <th class="p-2 border">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inspectors as $i): ?>
                            <tr>
                                <td class="p-2 border text-center">
                                    <?php if ($i['is_active']): ?><span
                                        class="bg-green-500 text-white px-2 py-1 rounded text-xs font-bold">ใช้งานอยู่</span>
                                    <?php
    else: ?>
                                    <form action="" method="POST"><input type="hidden" name="csrf_token"
                                            value="<?= $_SESSION['csrf_token']?>"><input type="hidden" name="action"
                                            value="set_active_signer"><input type="hidden" name="signer_type"
                                            value="INSPECTOR"><input type="hidden" name="signer_id"
                                            value="<?= $i['id']?>"><button type="submit"
                                            class="bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded text-xs font-bold text-gray-700">เลือกใช้</button>
                                    </form>
                                    <?php
    endif; ?>
                                </td>
                                <td class="p-2 border">
                                    <?= $i['full_name']?>
                                </td>
                                <td class="p-2 border"><img src="<?= getBase64Img($i['signature_path'])?>"
                                        class="h-8 max-w-[100px] object-contain"></td>
                                <td class="p-2 border text-center">
                                    <form action="" method="POST"
                                        onsubmit="event.preventDefault(); swalConfirm(event, 'ยืนยันการลบ?');"><input
                                            type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>"><input
                                            type="hidden" name="action" value="delete_signer"><input type="hidden"
                                            name="signer_id" value="<?= $i['id']?>"><button type="submit"
                                            class="text-red-500 hover:text-red-700"><i
                                                class="fas fa-trash"></i></button></form>
                                </td>
                            </tr>
                            <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md border-t-4 border-green-600">
                <h2 class="text-xl font-bold mb-4 text-green-800"><i class="fas fa-sitemap"></i> 3. จัดการหน่วยในสังกัด
                </h2>

                <form action="" method="POST" class="flex gap-2 mb-6">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                    <input type="hidden" name="action" value="add_org">
                    <input type="text" name="org_name" placeholder="ระบุชื่อหน่วยงานใหม่..."
                        class="flex-1 border p-2 rounded" required>
                    <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-bold">+ เพิ่ม</button>
                </form>

                <div class="overflow-y-auto max-h-96 border rounded">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th class="p-2 border-b">ID</th>
                                <th class="p-2 border-b w-full">ชื่อหน่วยงาน</th>
                                <th class="p-2 border-b text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orgs as $o): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-2">
                                    <?= $o['id']?>
                                </td>
                                <td class="p-2 font-semibold text-gray-700">
                                    <?= htmlspecialchars($o['org_name'])?>
                                </td>
                                <td class="p-2 text-center flex justify-center gap-1">
                                    <button type="button"
                                        onclick="editOrg(<?= $o['id']?>, '<?= htmlspecialchars(addslashes($o['org_name']))?>')"
                                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs">แก้ไข</button>
                                    <form action="" method="POST"
                                        onsubmit="event.preventDefault(); swalConfirm(event, 'คำเตือน: หากลบถาวร ข้อมูลบัตรเก่าที่ผูกกับหน่วยงานนี้อาจสูญหาย ยืนยันการลบ?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                                        <input type="hidden" name="action" value="delete_org"><input type="hidden"
                                            name="org_id" value="<?= $o['id']?>">
                                        <button type="submit"
                                            class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs"
                                            title="ลบถาวร"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md border-t-4 border-purple-600">
                <h2 class="text-xl font-bold mb-4 text-purple-800"><i class="fas fa-list-ul"></i> 4. ตัวช่วยกรอกตำแหน่ง
                </h2>
                <p class="text-sm text-gray-500 mb-4">ข้อมูลส่วนนี้จะไปโผล่เป็นตัวเลือกให้หน้าผู้ใช้งาน และหน้า Admin
                </p>

                <form action="" method="POST" class="flex gap-2 mb-3">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                    <input type="hidden" name="action" value="add_position">
                    <input type="text" name="position_name" placeholder="เพิ่มตำแหน่งใหม่..."
                        class="flex-1 border p-2 rounded" required>
                    <select name="default_org_id" class="border p-2 rounded w-1/3">
                        <option value="">ไม่ผูกสังกัด</option>
                        <?php foreach ($orgs as $o): ?>
                        <option value="<?= $o['id']?>">
                            <?= htmlspecialchars($o['org_name'])?>
                        </option>
                        <?php
endforeach; ?>
                    </select>
                    <button type="submit"
                        class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded font-bold">+
                        เพิ่ม</button>
                </form>

                <!-- ปุ่ม Export / Import CSV -->
                <div class="flex items-center gap-2 mb-4 p-2 bg-gray-50 border rounded">
                    <span class="text-xs text-gray-500 font-semibold shrink-0">CSV:</span>

                    <!-- Export -->
                    <form action="" method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                        <input type="hidden" name="action" value="export_positions_csv">
                        <button type="submit"
                            class="flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-xs font-bold">
                            <i class="fas fa-file-export"></i> Export CSV
                        </button>
                    </form>

                    <!-- Import -->
                    <form action="" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                        <input type="hidden" name="action" value="import_positions_csv">
                        <input type="file" name="csv_file" accept=".csv"
                            class="text-xs border rounded px-1 py-1 bg-white" required>
                        <button type="submit"
                            class="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded text-xs font-bold">
                            <i class="fas fa-file-import"></i> Upload CSV
                        </button>
                    </form>
                </div>

                <div class="overflow-y-auto max-h-96 border rounded">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th class="p-2 border-b w-full">ชื่อตำแหน่ง</th>
                                <th class="p-2 border-b text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($positions_list as $p): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-2 font-semibold text-gray-700">
                                    <?= htmlspecialchars($p['position_name'])?>
                                    <?php if (!empty($p['org_name'])): ?>
                                    <span
                                        class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded ml-2 border border-blue-200">
                                        <i class="fas fa-link"></i>
                                        <?= htmlspecialchars($p['org_name'])?>
                                    </span>
                                    <?php
    endif; ?>
                                </td>
                                <td class="p-2 text-center flex justify-center gap-1">
                                    <button type="button"
                                        onclick="editPosition(<?= $p['id']?>, '<?= htmlspecialchars(addslashes($p['position_name']))?>', '<?= $p['default_org_id'] ?? ''?>')"
                                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs">แก้ไข</button>
                                    <form action="" method="POST"
                                        onsubmit="event.preventDefault(); swalConfirm(event, 'ยืนยันการลบตำแหน่งนี้ออกจากระบบตัวเลือก?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                                        <input type="hidden" name="action" value="delete_position"><input type="hidden"
                                            name="pos_id" value="<?= $p['id']?>">
                                        <button type="submit"
                                            class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs"><i
                                                class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6 mb-6 border-t-4 border-yellow-500">
                <h2 class="text-xl font-bold mb-4 text-gray-800"><i
                        class="fas fa-sort-numeric-up-alt text-yellow-600"></i> ตั้งค่าเลขลำดับบัตรล่าสุด (Running
                    Number)</h2>
                <p class="text-sm text-gray-600 mb-4">
                    ใช้สำหรับกรณีที่มีการออกบัตรจากระบบอื่นมาก่อนหน้านี้ และต้องการให้ระบบรันเลขต่อจากเดิม
                    <br><i>ตัวอย่าง: หากปีนี้ออกบัตรในกระดาษไปแล้ว 100 ใบ และต้องการให้ระบบนี้เริ่มออกบัตรใบที่ 101
                        ให้กรอก <b>100</b></i>
                </p>

                <div class="bg-yellow-50 p-4 rounded border border-yellow-200 mb-4 flex items-center gap-3">
                    <i class="fas fa-info-circle text-yellow-600 text-2xl"></i>
                    <div>
                        <p class="text-sm font-bold text-yellow-800">ข้อมูลปัจจุบัน (ปี พ.ศ.
                            <?= $current_y_th?>)
                        </p>
                        <p class="text-sm text-yellow-700">ลำดับเลขบัตรสูงสุดที่ออกไปแล้วในระบบ คือ: <span
                                class="font-bold text-lg">
                                <?= $current_max_seq?>
                            </span></p>
                    </div>
                </div>

                <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                    <input type="hidden" name="action" value="set_last_sequence">

                    <div>
                        <label class="block text-sm font-bold mb-1">ปี พ.ศ. (ปีที่ต้องการตั้งค่า)</label>
                        <input type="number" name="card_year" value="<?= $current_y_th?>"
                            class="w-full border p-2 rounded bg-white focus:ring-2 focus:ring-yellow-400 outline-none"
                            required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-1">เลขลำดับล่าสุด (ที่ออกไปแล้ว)</label>
                        <input type="number" name="last_sequence" min="<?= $current_max_seq + 1?>"
                            placeholder="ต้องมากกว่า <?= $current_max_seq?>"
                            class="w-full border p-2 rounded bg-white focus:ring-2 focus:ring-yellow-400 outline-none"
                            required>
                    </div>
                    <div>
                        <button type="submit"
                            class="w-full bg-yellow-600 text-white font-bold py-2 px-4 rounded shadow hover:bg-yellow-700 transition"
                            onclick="event.preventDefault(); swalConfirm(event, 'ยืนยันการตั้งค่าเลขลำดับล่าสุดหรือไม่? (ระบบจะสร้างข้อมูลจำลองเพื่อเป็นฐานให้เลขถัดไป)', false);">
                            <i class="fas fa-save"></i> บันทึกตั้งค่าเลข
                        </button>
                    </div>
                </form>
            </div>

            <!-- 🟢 5. ตั้งค่าการแจ้งเตือน (เฉพาะ Super_Admin) -->
            <?php if ($_SESSION['role'] === 'Super_Admin'): ?>
            <div class="bg-white p-6 rounded-lg shadow-md border-t-4 border-red-500 md:col-span-2">
                <h2 class="text-xl font-bold mb-4 text-red-800"><i class="fas fa-bell"></i> 5. ตั้งค่าการแจ้งเตือน
                    (Discord / LINE / Telegram)</h2>
                <p class="text-sm text-gray-500 mb-6">ระบบจะส่งข้อมูลแจ้งเตือนทันทีเมื่อมีผู้ยื่นคำขอใหม่เข้ามาในระบบ
                    (ส่วนนี้เห็นเฉพาะ Super Admin)</p>

                <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                    <input type="hidden" name="action" value="update_notifications">

                    <!-- Discord -->
                    <div class="space-y-3 bg-indigo-50 p-4 rounded-lg border border-indigo-100">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fab fa-discord text-2xl text-indigo-600"></i>
                            <h3 class="font-bold text-gray-800">Discord Notification</h3>
                        </div>
                        <div class="relative">
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Webhook URL</label>
                            <input type="password" id="discord_webhook" name="discord_webhook_url"
                                value="<?= htmlspecialchars($discord_webhook)?>"
                                placeholder="https://discord.com/api/webhooks/..."
                                class="w-full border p-2 pr-10 rounded bg-white text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                            <button type="button" onclick="toggleVisibility('discord_webhook')"
                                class="absolute right-3 top-8 text-gray-400 hover:text-indigo-600">
                                <i class="fas fa-eye" id="eye-discord_webhook"></i>
                            </button>
                            <p class="text-[10px] text-gray-500 mt-1 italic">นำ Webhook URL จาก Channel Settings >
                                Integrations มาใส่</p>
                        </div>
                    </div>

                    <!-- LINE Messaging API -->
                    <div class="space-y-3 bg-green-50 p-4 rounded-lg border border-green-100">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <i class="fab fa-line text-2xl text-green-600"></i>
                                <h3 class="font-bold text-gray-800">LINE Messaging API</h3>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-bold text-gray-500 block uppercase">โควต้าเดือนนี้</span>
                                <span
                                    class="<?= $line_msg_count >= 300 ? 'text-red-600' : 'text-green-700'?> font-bold text-sm">
                                    <?= $line_msg_count?> / 300
                                </span>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="relative">
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Channel Access
                                    Token</label>
                                <input type="password" id="line_token" name="line_channel_access_token"
                                    value="<?= htmlspecialchars($line_channel_token)?>"
                                    placeholder="ระบุ Long-lived Access Token..."
                                    class="w-full border p-2 pr-10 rounded bg-white text-sm focus:ring-2 focus:ring-green-400 outline-none">
                                <button type="button" onclick="toggleVisibility('line_token')"
                                    class="absolute right-3 top-8 text-gray-400 hover:text-green-600">
                                    <i class="fas fa-eye" id="eye-line_token"></i>
                                </button>
                            </div>
                            <div class="relative">
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">User / Group
                                    ID</label>
                                <input type="password" id="line_user" name="line_user_id"
                                    value="<?= htmlspecialchars($line_user_id)?>" placeholder="e.g. U123456789abcdef..."
                                    class="w-full border p-2 pr-10 rounded bg-white text-sm focus:ring-2 focus:ring-green-400 outline-none">
                                <button type="button" onclick="toggleVisibility('line_user')"
                                    class="absolute right-3 top-8 text-gray-400 hover:text-green-600">
                                    <i class="fas fa-eye" id="eye-line_user"></i>
                                </button>
                            </div>
                        </div>
                        <p class="text-[10px] text-gray-500 mt-1 italic">ตั้งค่าได้ที่ <a
                                href="https://developers.line.biz/" target="_blank"
                                class="text-green-600 underline">LINE Developers Console</a></p>
                    </div>

                    <!-- Telegram -->
                    <div class="space-y-3 bg-blue-50 p-4 rounded-lg border border-blue-100 md:col-span-2">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fab fa-telegram text-2xl text-blue-500"></i>
                            <h3 class="font-bold text-gray-800">Telegram Bot Notification</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="relative">
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Bot Token</label>
                                <input type="password" id="tg_token" name="telegram_bot_token"
                                    value="<?= htmlspecialchars($tg_bot_token)?>" placeholder="e.g. 123456:ABC-DEF..."
                                    class="w-full border p-2 pr-10 rounded bg-white text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                                <button type="button" onclick="toggleVisibility('tg_token')"
                                    class="absolute right-3 top-8 text-gray-400 hover:text-blue-600">
                                    <i class="fas fa-eye" id="eye-tg_token"></i>
                                </button>
                            </div>
                            <div class="relative">
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Chat ID</label>
                                <input type="password" id="tg_chat" name="telegram_chat_id"
                                    value="<?= htmlspecialchars($tg_chat_id)?>" placeholder="e.g. -10012345678"
                                    class="w-full border p-2 pr-10 rounded bg-white text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                                <button type="button" onclick="toggleVisibility('tg_chat')"
                                    class="absolute right-3 top-8 text-gray-400 hover:text-blue-600">
                                    <i class="fas fa-eye" id="eye-tg_chat"></i>
                                </button>
                            </div>
                        </div>
                        <p class="text-[10px] text-gray-500 mt-1 italic">สร้าง Bot จาก @BotFather และหา Chat ID ได้จาก
                            @userinfobot หรือ Bot API</p>
                    </div>

                    <div class="md:col-span-2 flex justify-center mt-4">
                        <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white px-10 py-3 rounded-xl font-bold shadow-lg transition transform hover:scale-105">
                            <i class="fas fa-save mr-2"></i> บันทึกการตั้งค่าการแจ้งเตือนทั้งหมด
                        </button>
                    </div>
                </form>
            </div>
            <?php
endif; ?>

        </div>
    </div>

    <!-- Script สำหรับการ Toggle Visibility -->
    <script>
        function toggleVisibility(inputId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById('eye-' + inputId);
            if (input.type === 'password') {
                input.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }
    </script>

    <div id="editPosModal"
        class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 backdrop-blur-sm">
        <div class="bg-white p-6 rounded-xl w-96 shadow-2xl transform transition-transform scale-100">
            <h3 class="text-xl font-bold mb-4 text-purple-800"><i class="fas fa-edit"></i> แก้ไขข้อมูลตำแหน่ง</h3>

            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                <input type="hidden" name="action" value="edit_position">
                <input type="hidden" name="pos_id" id="modal_pos_id">

                <div class="mb-4">
                    <label class="block text-sm font-bold mb-1 text-gray-700">ชื่อตำแหน่ง</label>
                    <input type="text" name="position_name" id="modal_pos_name"
                        class="w-full border p-2.5 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none" required>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-bold mb-1 text-gray-700">ผูกสังกัด (Auto-fill)</label>
                    <select name="default_org_id" id="modal_org_id"
                        class="w-full border p-2.5 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none bg-gray-50">
                        <option value="">-- ไม่ผูกสังกัด (เว้นว่าง) --</option>
                        <?php foreach ($orgs as $o): ?>
                        <option value="<?= $o['id']?>">
                            <?= $o['org_name']?>
                        </option>
                        <?php
endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('editPosModal').classList.add('hidden')"
                        class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg font-bold text-gray-700 transition">ยกเลิก</button>
                    <button type="submit"
                        class="bg-purple-600 hover:bg-purple-700 text-white px-5 py-2 rounded-lg font-bold shadow-md transition">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ดึงค่า CSRF Token จากฟอร์มอื่นในหน้าเว็บมาเตรียมไว้
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;

        // JS สำหรับหน้าต่างแก้ไขหน่วยงาน
        function editOrg(id, currentName) {
            let newName = prompt("แก้ไขชื่อหน่วยงาน:", currentName);
            if (newName !== null && newName.trim() !== "") {
                let form = document.createElement('form'); form.method = 'POST'; form.action = '';

                let csrfInput = document.createElement('input'); csrfInput.type = 'hidden'; csrfInput.name = 'csrf_token'; csrfInput.value = csrfToken;
                let act = document.createElement('input'); act.type = 'hidden'; act.name = 'action'; act.value = 'edit_org';
                let idInput = document.createElement('input'); idInput.type = 'hidden'; idInput.name = 'org_id'; idInput.value = id;
                let nameInput = document.createElement('input'); nameInput.type = 'hidden'; nameInput.name = 'org_name'; nameInput.value = newName.trim();

                form.append(csrfInput, act, idInput, nameInput); document.body.appendChild(form); form.submit();
            }
        }

        // JS สำหรับหน้าต่างแก้ไขตำแหน่งและสังกัด (Pop-up)
        function editPosition(id, currentName, currentOrgId) {
            document.getElementById('modal_pos_id').value = id;
            document.getElementById('modal_pos_name').value = currentName;

            // ตั้งค่า Select ให้ตรงกับสังกัดเดิม (ถ้าไม่มีให้เป็นค่าว่าง)
            document.getElementById('modal_org_id').value = currentOrgId ? currentOrgId : '';

            // โชว์หน้าต่าง Modal
            document.getElementById('editPosModal').classList.remove('hidden');
        }

        // Global function for SweetAlert Confirm
        function swalConfirm(event, text, isDanger = true) {
            event.preventDefault(); // Stop standard form submission
            const form = event.target.closest('form') || event.target;

            Swal.fire({
                title: 'ยืนยันการทำรายการ?',
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: isDanger ? '#d33' : '#3085d6',
                cancelButtonColor: isDanger ? '#3085d6' : '#d33',
                confirmButtonText: isDanger ? '🗑️ ยืนยันลบ' : '✅ ยืนยัน',
                cancelButtonText: '❌ ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    </script>
</body>

</html>