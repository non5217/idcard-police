<?php
// idcard/admin_edit.php
require_once 'connect.php';
require_once 'admin_auth.php'; // 🔒 เฉพาะ Admin
// 🟢 เพิ่มฟังก์ชันดึงไฟล์มาฝังหน้าเว็บ (แปลงเป็น Base64) ซ่อน URL จริง
function getBase64ImgDocs($file_path)
{
    if (empty($file_path))
        return '';
    $real_path = file_exists($file_path) ? realpath($file_path) : realpath(__DIR__ . '/' . $file_path);
    if (!$real_path || !file_exists($real_path))
        return '';
    $data = @file_get_contents($real_path);
    if (!$data)
        return '';
    $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
    $mime = ($ext === 'pdf') ? 'application/pdf' : (($ext === 'png') ? 'image/png' : 'image/jpeg');
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}
if (!isset($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM idcard_requests WHERE id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$req)
    die("ไม่พบข้อมูล");

// 🟢 แทรกโค้ดเก็บ Log การเปิดดูข้อมูล
saveLog($conn, 'VIEW_REQUEST_DETAIL', "เปิดดูข้อมูลคำขอของ " . $req['full_name'] . " (ID: $id)", $id);

// 🟢 ดึงประวัติการทำบัตรเก่าของเลข ปชช. นี้ (ไม่รวมรายการปัจจุบัน)
$stmt_history = $conn->prepare("SELECT id, created_at, request_reason, request_reason_detail, status FROM idcard_requests WHERE id_card_number = ? AND id != ? AND status != 'CANCELLED' ORDER BY created_at DESC");
$stmt_history->execute([$req['id_card_number'], $id]);
$history_records = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

// 🟢 ดึงบันทึกข้อความจากเจ้าหน้าที่ (ผูกกับเลขบัตรประชาชน)
$stmt_notes = $conn->prepare("SELECT * FROM idcard_admin_notes WHERE id_card_number = ? ORDER BY created_at DESC");
$stmt_notes->execute([$req['id_card_number']]);
$staff_notes = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);

$reason_map = [
    'NEW' => 'ขอมีบัตรครั้งแรก',
    'RENEW' => 'บัตรเดิมหมดอายุ',
    'CHANGE' => 'เลื่อนยศ/เปลี่ยนตำแหน่ง/ย้ายสังกัด',
    'LOST' => 'บัตรหาย หรือ ชำรุด',
    'OTHER' => 'อื่นๆ'
];
$status_map = [
    'PENDING_CHECK' => 'รอตรวจสอบ', 'PENDING_APPROVAL' => 'รออนุมัติ',
    'SENT_TO_PRINT' => 'รอพิมพ์บัตร', 'READY_PICKUP' => 'พิมพ์บัตรแล้ว / รอรับ',
    'COMPLETED' => 'รับบัตรแล้ว (จบงาน)', 'REJECTED' => 'ปฏิเสธคำขอ'
];

// 🟢 Dynamic Workflow Button Logic
$dynamic_btn = null;
if ($req['status'] === 'PENDING_CHECK') {
    $dynamic_btn = [
        'label' => 'เปลี่ยนเป็นสถานะ "รออนุมัติ"',
        'target_status' => 'PENDING_APPROVAL',
        'color' => 'bg-indigo-600 hover:bg-indigo-700',
        'icon' => 'fa-check-circle'
    ];
} elseif ($req['status'] === 'PENDING_APPROVAL') {
    $dynamic_btn = [
        'label' => 'เปลี่ยนเป็นสถานะ "รอพิมพ์บัตร"',
        'target_status' => 'SENT_TO_PRINT',
        'color' => 'bg-blue-600 hover:bg-blue-700',
        'icon' => 'fa-print'
    ];
} elseif ($req['status'] === 'SENT_TO_PRINT') {
    $dynamic_btn = [
        'label' => 'เปลี่ยนเป็นสถานะ "พิมพ์บัตรแล้ว / รอรับ"',
        'target_status' => 'READY_PICKUP',
        'color' => 'bg-teal-600 hover:bg-teal-700',
        'icon' => 'fa-flag-checkered'
    ];
}

// แปลง JSON ที่อยู่
$addr = json_decode($req['address_json'], true) ?? [];

// Master Data
$all_ranks = $conn->query("SELECT * FROM idcard_ranks")->fetchAll(PDO::FETCH_ASSOC);
$ranks_by_id = array_column($all_ranks, null, 'id');
$rank_sort_order = [1, 2, 3, 15, 4, 16, 5, 17, 6, 18, 7, 19, 8, 20, 9, 21, 10, 22, 11, 23, 12, 24, 13, 25, 14, 26];
$orgs = $conn->query("SELECT * FROM idcard_organizations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$pos_dict = $conn->query("SELECT position_name FROM idcard_positions ORDER BY position_name ASC")->fetchAll(PDO::FETCH_COLUMN);
$card_types = $conn->query("SELECT * FROM idcard_card_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

function val($key, $data)
{
    return htmlspecialchars($data[$key] ?? '');
}

// เตรียมข้อมูลวันที่
$issue_th = '';
if (!empty($req['issue_date'])) {
    $d = new DateTime($req['issue_date']);
    $issue_th = $d->format('d/m/') . ($d->format('Y') + 543);
}
$expire_th = '';
$is_lifetime = false;
if (!empty($req['expire_date'])) {
    if ($req['expire_date'] === '9999-12-31') {
        $is_lifetime = true;
    }
    else {
        $d = new DateTime($req['expire_date']);
        $expire_th = $d->format('d/m/') . ($d->format('Y') + 543);
    }
}
$dob_th = '';
if (!empty($req['birth_date'])) {
    $dob_obj = new DateTime($req['birth_date']);
    $dob_th = $dob_obj->format('d/m/') . ($dob_obj->format('Y') + 543);
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูล - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        .custom-checkered-bg {
            background-image: linear-gradient(45deg, #ccc 25%, transparent 25%), linear-gradient(-45deg, #ccc 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #ccc 75%), linear-gradient(-45deg, transparent 75%, #ccc 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }
    </style>
</head>

<body class="bg-gray-100 p-6">
    <?php include 'admin_navbar.php'; ?>
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-xl overflow-hidden">
        <div class="bg-yellow-500 p-4 flex justify-between items-center text-white">
            <h1 class="text-xl font-bold">✏️ แก้ไขข้อมูลคำขอ (ID:
                <?= $req['id']?>)
            </h1>
            <a href="admin_dashboard.php"
                class="bg-white text-yellow-600 px-4 py-1 rounded hover:bg-gray-100 font-bold">Cancel / กลับ</a>
        </div>

        <?php if (!empty($history_records) && count($history_records) > 0): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mx-6 mt-6 mb-2 rounded-r shadow-md">
            <div class="flex items-center mb-2 text-yellow-800">
                <i class="fas fa-exclamation-triangle text-2xl mr-3 animate-pulse"></i>
                <h3 class="font-bold text-lg">เฝ้าระวัง: บุคคลนี้เคยยื่นคำขอทำบัตรมาแล้ว!</h3>
            </div>
            <div class="ml-9 text-sm text-gray-700 bg-white p-3 rounded border border-yellow-200">
                <p class="font-bold mb-2 text-yellow-700">มีคำขอเดิมจำนวน
                    <?= count($history_records)?> รายการ:
                </p>
                <div class="space-y-3">
                    <?php foreach ($history_records as $hist):
        $hist_year = $hist['request_year'] ?? (date('Y', strtotime($hist['created_at'])) + 543);
        $hist_id_formatted = sprintf('%04d/%s', $hist['id'], $hist_year);
        $thai_date = date('d/m/Y', strtotime($hist['created_at']));
        $status_str = $status_map[$hist['status']] ?? $hist['status'];
?>
                    <div
                        class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-gray-100 last:border-0 last:pb-0">
                        <div class="text-sm">
                            <span class="font-bold text-gray-800">มีคำขอเดิมเลขที่ <span class="text-blue-600">
                                    <?= $hist_id_formatted?>
                                </span></span>
                            <span class="mx-1 text-gray-400">|</span>
                            <span>วันที่ยื่น <span class="font-semibold text-gray-700">
                                    <?= $thai_date?>
                                </span></span>
                            <span class="mx-1 text-gray-400">|</span>
                            <span>สถานะเป็น <span class="font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                    <?= $status_str?>
                                </span></span>
                        </div>

                        <div>
                            <a href="admin_edit.php?id=<?= $hist['id']?>" target="_blank"
                                class="inline-flex items-center gap-1.5 bg-gray-800 hover:bg-gray-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition whitespace-nowrap"><i
                                    class="fas fa-external-link-alt"></i> เรียกดูข้อมูลเดิม
                            </a>
                        </div>
                    </div>
                    <?php
    endforeach; ?>
                </div>
            </div>
        </div>
        <?php
endif; ?>

        <form action="admin_update_request.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
            <input type="hidden" name="id" value="<?= $req['id']?>">

            <div class="bg-blue-50 p-4 rounded border border-blue-200">
                <h3 class="font-bold text-blue-800 mb-2">⚙️ จัดการสถานะ</h3>

                <?php if (!empty($req['generated_card_no'])): ?>
                <div
                    class="mb-4 bg-green-100 border border-green-300 text-green-800 p-3 rounded flex items-center gap-2">
                    <i class="fas fa-check-circle text-xl"></i>
                    <div>
                        <span class="block text-xs font-bold uppercase tracking-wider">เลขทะเบียนบัตรที่ออกแล้ว</span>
                        <span class="text-xl font-bold">
                            <?= $req['generated_card_no']?>
                        </span>
                    </div>
                </div>
                <?php
else: ?>
                <p class="text-xs text-gray-500 mb-4 bg-white p-2 rounded border inline-block">
                    <i class="fas fa-info-circle text-blue-500"></i> เลขทะเบียนบัตรจะถูกสร้างอัตโนมัติเมื่อสถานะเป็น
                    "รอพิมพ์บัตร"
                </p>
                <?php
endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold">สถานะปัจจุบัน</label>
                        <select name="status" id="status" class="w-full border p-2 rounded bg-white">
                            <?php
$statuses = [
    'PENDING_CHECK' => 'รอตรวจสอบ',
    'PENDING_APPROVAL' => 'รออนุมัติ',
    'SENT_TO_PRINT' => 'รอพิมพ์บัตร',
    'READY_PICKUP' => 'พิมพ์บัตรแล้ว / รอรับ',
    'COMPLETED' => 'รับบัตรแล้ว (จบงาน)',
    'REJECTED' => 'ปฏิเสธคำขอ',
    'CANCELLED' => 'ยกเลิก/ซ่อนคำขอ (Soft Delete)'
];
foreach ($statuses as $key => $label):
?>
                            <option value="<?= $key?>" <?= $req['status'] == $key ? 'selected' : ''?>>
                                <?= $label?>
                            </option>
                            <?php
endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold">หมายเหตุ / เหตุผลที่ปฏิเสธ</label>
                        <input type="text" name="reject_reason" value="<?= val('reject_reason', $req)?>"
                            class="w-full border p-2 rounded bg-white">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-bold">ยศ</label>
                    <select name="rank_id" class="w-full border p-2 rounded bg-white">
                        <?php foreach ($rank_sort_order as $rid):
    if (isset($ranks_by_id[$rid])):
        $r = $ranks_by_id[$rid]; ?>
                        <option value="<?= $r['id']?>" <?= $req['rank_id'] == $r['id'] ? 'selected' : ''?>>
                            <?= $r['rank_name']?>
                        </option>
                        <?php
    endif;
endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold">ชื่อ (แยกคำนำหน้าออก)</label>
                    <?php
$parts = explode(' ', $req['full_name']);
$fname = htmlspecialchars($parts[0] ?? '', ENT_QUOTES, 'UTF-8');
$lname = htmlspecialchars($parts[1] ?? '', ENT_QUOTES, 'UTF-8');
?>
                    <input type="text" name="first_name" value="<?= $fname?>" class="w-full border p-2 rounded bg-white"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-bold">นามสกุล</label>
                    <input type="text" name="last_name" value="<?= $lname?>" class="w-full border p-2 rounded bg-white"
                        required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-bold">วันเกิด (พ.ศ.)</label>
                    <input type="text" id="edit_birth_date_th" value="<?= $dob_th?>"
                        class="w-full border p-2 rounded bg-white text-center tracking-widest" placeholder="วว/ดด/ปปปป">
                    <input type="hidden" name="birth_date" id="edit_birth_date_db" value="<?= $req['birth_date']?>">
                </div>
                <div>
                    <label class="block text-sm font-bold">อายุ</label>
                    <input type="number" name="age"
                        value="<?= htmlspecialchars($req['age'] ?? '', ENT_QUOTES, 'UTF-8')?>"
                        class="w-full border p-2 rounded bg-white">
                </div>
                <div>
                    <label class="block text-sm font-bold">เลขบัตร ปชช.</label>
                    <input type="text" name="id_card_number" value="<?= $req['id_card_number']?>"
                        class="w-full border p-2 rounded bg-gray-100" readonly>
                </div>
                <div>
                    <label class="block text-sm font-bold">โทรศัพท์</label>
                    <input type="text" name="phone"
                        value="<?= htmlspecialchars($req['phone'] ?? '', ENT_QUOTES, 'UTF-8')?>"
                        class="w-full border p-2 rounded bg-white">
                </div>
                <div>
                    <label class="block text-sm font-bold">หมู่โลหิต</label>
                    <select name="blood_type" class="w-full border p-2 rounded bg-white">
                        <option value="">-</option>
                        <?php foreach (['O', 'A', 'B', 'AB'] as $b): ?>
                        <option value="<?= $b?>" <?=($req['blood_type'] ?? '') == $b ? 'selected' : ''?>>
                            <?= $b?>
                        </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="border p-4 rounded bg-gray-50">
                <h3 class="font-bold text-gray-700 mb-2">ที่อยู่ตามทะเบียนบ้าน</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                    <input type="text" name="addr_house_no"
                        value="<?= htmlspecialchars($addr['house_no'] ?? '', ENT_QUOTES, 'UTF-8')?>"
                        placeholder="บ้านเลขที่" class="border p-2 rounded bg-white">
                    <input type="text" name="addr_moo"
                        value="<?= htmlspecialchars($addr['moo'] ?? '', ENT_QUOTES, 'UTF-8')?>" placeholder="หมู่"
                        class="border p-2 rounded bg-white">
                    <input type="text" name="addr_road"
                        value="<?= htmlspecialchars($addr['road'] ?? '', ENT_QUOTES, 'UTF-8')?>" placeholder="ถนน"
                        class="border p-2 rounded col-span-2 bg-white">
                    <input type="text" name="addr_tambon"
                        value="<?= htmlspecialchars($addr['tambon'] ?? '', ENT_QUOTES, 'UTF-8')?>" placeholder="ตำบล"
                        class="border p-2 rounded bg-white">
                    <input type="text" name="addr_amphoe"
                        value="<?= htmlspecialchars($addr['amphoe'] ?? '', ENT_QUOTES, 'UTF-8')?>" placeholder="อำเภอ"
                        class="border p-2 rounded bg-white">
                    <input type="text" name="addr_province"
                        value="<?= htmlspecialchars($addr['province'] ?? '', ENT_QUOTES, 'UTF-8')?>"
                        placeholder="จังหวัด" class="border p-2 rounded bg-white">
                    <input type="text" name="addr_zipcode"
                        value="<?= htmlspecialchars($addr['zipcode'] ?? '', ENT_QUOTES, 'UTF-8')?>"
                        placeholder="รหัส ปณ." class="border p-2 rounded bg-white">
                </div>
            </div>

            <div class="border p-4 rounded bg-emerald-50 border-emerald-200">
                <h3 class="font-bold text-emerald-800 mb-2"><i class="fas fa-question-circle"></i> เหตุผลในการขอมีบัตร
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold">กรณีขอมีบัตร</label>
                        <div class="w-full border p-2 rounded bg-white text-gray-800">
                            <?= $reason_map[$req['request_reason']] ?? htmlspecialchars($req['request_reason'], ENT_QUOTES, 'UTF-8')?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold">รายละเอียดเพิ่มเติม (ถ้ามี)</label>
                        <div
                            class="w-full border p-2 rounded bg-gray-100 text-gray-700 h-[42px] overflow-hidden whitespace-nowrap overflow-ellipsis">
                            <?= htmlspecialchars($req['request_reason_detail'] ?? '-', ENT_QUOTES, 'UTF-8')?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold">ประเภทบัตร</label>
                    <select name="card_type_id" class="w-full border p-2 rounded bg-white" required>
                        <?php foreach ($card_types as $ct): ?>
                        <option value="<?= $ct['id']?>" <?= $req['card_type_id'] == $ct['id'] ? 'selected' : ''?>>
                            <?= $ct['type_name']?>
                        </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold">ประเภท จนท.</label>
                    <select name="officer_type" class="w-full border p-2 rounded bg-white" required>
                        <option value="POLICE" <?= $req['officer_type'] == 'POLICE' ? 'selected' : ''?>>ข้าราชการตำรวจ
                        </option>
                        <option value="PERMANENT_EMP" <?= $req['officer_type'] == 'PERMANENT_EMP' ? 'selected' : ''?>
                            >ลูกจ้างประจำ</option>
                        <option value="GOV_EMP" <?= $req['officer_type'] == 'GOV_EMP' ? 'selected' : ''?>>พนักงานราชการ
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold">ตำแหน่ง</label>
                    <input type="text" name="position"
                        value="<?= htmlspecialchars($req['position'] ?? '', ENT_QUOTES, 'UTF-8')?>"
                        class="w-full border p-2 rounded bg-white" list="pos_list" autocomplete="off">
                    <datalist id="pos_list">
                        <?php foreach ($pos_dict as $p): ?>
                        <option value="<?= htmlspecialchars($p)?>">
                            <?php
endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-bold">สังกัด</label>
                    <select name="org_id" class="w-full border p-2 rounded bg-white">
                        <?php foreach ($orgs as $o): ?>
                        <option value="<?= $o['id']?>" <?= $req['org_id'] == $o['id'] ? 'selected' : ''?>>
                            <?= $o['org_name']?>
                        </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="bg-white p-5 rounded-lg border border-gray-300 shadow-sm mt-4">
                <h3 class="font-bold text-gray-800 mb-4 border-b border-gray-200 pb-2">
                    <i class="fas fa-magic text-yellow-600"></i> ปรับแต่งรูปถ่ายและลายเซ็น
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">

                    <div class="bg-gray-50 p-4 rounded border border-gray-200">
                        <label class="block text-sm font-bold mb-2 text-gray-800">รูปถ่ายหน้าตรง</label>
                        <div class="flex items-start gap-4 mb-3">
                            <div
                                class="w-24 h-32 bg-gray-200 border border-gray-300 flex items-center justify-center overflow-hidden rounded shadow-inner flex-shrink-0 relative">
                                <img id="photo_preview"
                                    src="<?= $req['photo_path'] ? 'secure_image.php?f=' . urlencode($req['photo_path']) : ''?>"
                                    class="w-full h-full object-cover <?= $req['photo_path'] ? '' : 'hidden'?>">
                                <span id="photo_placeholder"
                                    class="text-xs text-gray-500 <?= $req['photo_path'] ? 'hidden' : ''?>">No
                                    Photo</span>
                            </div>
                            <div class="flex-1 space-y-2">
                                <?php if ($req['photo_path']): ?>
                                <button type="button"
                                    onclick="editExistingPhoto('secure_image.php?f=<?= urlencode($req['photo_path'])?>')"
                                    class="bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded text-xs font-bold hover:bg-indigo-200 transition shadow-sm w-full text-left"><i
                                        class="fas fa-crop-alt"></i> Crop รูปเดิมในระบบ</button>
                                <?php
endif; ?>

                                <label
                                    class="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded text-xs font-bold hover:bg-gray-50 transition shadow-sm w-full block text-left cursor-pointer">
                                    <i class="fas fa-upload"></i> อัปโหลดรูปใหม่ (และ Crop)
                                    <input type="file" id="new_photo_input" name="new_photo" accept="image/*"
                                        class="hidden" onchange="handleNewPhoto(this)">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded border border-gray-200">
                        <label class="block text-sm font-bold mb-2 text-gray-800">ลายมือชื่อผู้ยื่นคำขอ</label>
                        <div class="flex items-center gap-3 mb-3">
                            <div
                                class="w-40 h-12 bg-white border border-dashed border-gray-400 flex items-center justify-center rounded p-1 shadow-inner relative">
                                <img id="sig_preview"
                                    src="<?= $req['signature_file'] ? 'secure_image.php?f=' . urlencode($req['signature_file']) : ''?>"
                                    class="h-full object-contain <?= $req['signature_file'] ? '' : 'hidden'?>">
                                <span id="sig_placeholder"
                                    class="text-xs text-gray-400 <?= $req['signature_file'] ? 'hidden' : ''?>">ไม่มีลายเซ็น</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <?php if ($req['signature_file']): ?>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button"
                                    onclick="editExistingSignatureCrop('secure_image.php?f=<?= urlencode($req['signature_file'])?>')"
                                    class="bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded text-xs font-bold hover:bg-indigo-200 transition shadow-sm block w-full text-left"><i
                                        class="fas fa-crop-alt"></i> Crop ลายเซ็นเดิม</button>
                                <button type="button"
                                    onclick="editExistingSignature('secure_image.php?f=<?= urlencode($req['signature_file'])?>')"
                                    class="bg-purple-100 text-purple-700 px-3 py-1.5 rounded text-xs font-bold hover:bg-purple-200 transition shadow-sm block w-full text-left"><i
                                        class="fas fa-eraser"></i> ลบพื้นหลังเดิม</button>
                            </div>
                            <?php
endif; ?>

                            <label
                                class="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded text-xs font-bold hover:bg-gray-50 transition shadow-sm w-full block text-left cursor-pointer">
                                <i class="fas fa-upload"></i> อัปโหลดลายเซ็นใหม่ (และตัดพื้นหลัง)
                                <input type="file" id="new_sig_input" name="new_signature" accept="image/*"
                                    class="hidden" onchange="handleNewSignature(this)">
                            </label>
                        </div>
                    </div>
                </div>

                <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
                <script>
                    // กำหนด Worker ให้ PDF.js
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16min.js';
                </script>

                <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
                <script>
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16min.js';
                </script>

                <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
                <script>
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16min.js';
                </script>

                <div class="bg-gray-50 p-4 rounded border border-gray-200">
                    <label class="block text-sm font-bold mb-3 text-gray-800">
                        <i class="fas fa-paperclip text-blue-600"></i> เอกสารหลักฐานแนบเพิ่มเติม
                    </label>
                    <?php
$docs = json_decode($req['documents_json'], true);
if (!empty($docs) && is_array($docs) && count($docs) > 0):
?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($docs as $index => $doc_path):
        $doc_ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
        $base64_data = getBase64ImgDocs($doc_path);
        if (!$base64_data)
            continue;
        $icon = ($doc_ext == 'pdf') ? 'fa-file-pdf text-red-500' : 'fa-file-image text-green-500';
?>
                        <div
                            class="border rounded-lg overflow-hidden bg-white shadow-sm flex flex-col h-[600px] relative">
                            <div
                                class="bg-gray-200 px-3 py-2 text-sm font-bold text-gray-700 flex justify-between items-center z-10">
                                <span><i class="fas <?= $icon?>"></i> ไฟล์เอกสารที่
                                    <?= $index + 1?>
                                </span>
                                <span class="text-xs bg-gray-300 px-2 py-0.5 rounded uppercase">
                                    <?= $doc_ext?>
                                </span>
                            </div>

                            <div class="flex-1 bg-gray-600 flex justify-center overflow-auto relative p-4">
                                <?php if ($doc_ext === 'pdf'): ?>
                                <div class="pdf-canvas-container w-full" data-b64="<?= $base64_data?>"
                                    style="display: flex; flex-direction: column; gap: 10px; align-items: center;">
                                    <div class="text-white text-sm loading-text"><i class="fas fa-spinner fa-spin"></i>
                                        กำลังเรนเดอร์ PDF...</div>
                                </div>
                                <?php
        else: ?>
                                <img src="<?= $base64_data?>" class="max-w-full h-full object-contain doc-image">
                                <button type="button" onclick="openImageFullscreen(this)"
                                    class="absolute top-2 right-2 bg-white/90 text-blue-800 border border-blue-200 px-3 py-1.5 rounded shadow-lg hover:bg-white transition z-50 font-bold text-xs flex items-center gap-1">
                                    <i class="fas fa-expand"></i> เปิดดูเต็มจอ
                                </button>
                                <?php
        endif; ?>
                            </div>
                        </div>
                        <?php
    endforeach; ?>
                    </div>

                    <script>
                        // 🟢 ฟังก์ชันใหม่: เปิดรูปภาพเต็มจอ (แปลง Base64 -> Blob URL ก่อนเปิดเพื่อหลบระบบความปลอดภัยบราวเซอร์)
                        function openImageFullscreen(btn) {
                            const img = btn.parentElement.querySelector('.doc-image');
                            if (!img) return;
                            const b64Data = img.src;

                            const arr = b64Data.split(',');
                            const mime = arr[0].match(/:(.*?);/)[1];
                            const bstr = atob(arr[1]);
                            let n = bstr.length;
                            const u8arr = new Uint8Array(n);
                            while (n--) { u8arr[n] = bstr.charCodeAt(n); }

                            const blob = new Blob([u8arr], { type: mime });
                            const blobUrl = URL.createObjectURL(blob);
                            window.open(blobUrl, '_blank'); // จะเปิดเป็น blob:https://... ทำให้ไม่โดนบล็อก
                        }

                        document.addEventListener("DOMContentLoaded", function () {
                            document.querySelectorAll('.pdf-canvas-container').forEach(container => {
                                const b64Data = container.getAttribute('data-b64');
                                if (b64Data) {
                                    const arr = b64Data.split(',');
                                    const mime = arr[0].match(/:(.*?);/)[1];
                                    const base64String = arr[1];
                                    const binaryString = window.atob(base64String);
                                    const len = binaryString.length;
                                    const bytes = new Uint8Array(len);
                                    for (let i = 0; i < len; i++) {
                                        bytes[i] = binaryString.charCodeAt(i);
                                    }

                                    // สร้าง Blob URL สำหรับปุ่ม "เปิดดูเต็มจอ" ของ PDF
                                    const blob = new Blob([bytes], { type: mime });
                                    const blobUrl = URL.createObjectURL(blob);

                                    // สร้างปุ่มแปะไว้มุมขวาบน
                                    const btn = document.createElement('a');
                                    btn.href = blobUrl;
                                    btn.target = '_blank';
                                    btn.className = 'absolute top-2 right-2 bg-white/90 text-blue-800 border border-blue-200 px-3 py-1.5 rounded shadow-lg hover:bg-white transition z-50 font-bold text-xs flex items-center gap-1';
                                    btn.innerHTML = '<i class="fas fa-expand"></i> เปิดดูเต็มจอ';
                                    container.parentElement.appendChild(btn);

                                    // ลบข้อความ "กำลังโหลด"
                                    container.innerHTML = '';

                                    // สั่งให้ PDF.js อ่านข้อมูลมาวาด
                                    pdfjsLib.getDocument({ data: bytes }).promise.then(function (pdf) {
                                        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                                            pdf.getPage(pageNum).then(function (page) {
                                                const scale = 1.5;
                                                const viewport = page.getViewport({ scale: scale });

                                                const canvas = document.createElement('canvas');
                                                canvas.className = 'shadow-lg bg-white mb-4 max-w-full h-auto';
                                                const context = canvas.getContext('2d');
                                                canvas.height = viewport.height;
                                                canvas.width = viewport.width;

                                                container.appendChild(canvas);

                                                const renderContext = {
                                                    canvasContext: context,
                                                    viewport: viewport
                                                };
                                                page.render(renderContext);
                                            });
                                        }
                                    }).catch(function (error) {
                                        container.innerHTML = '<div class="text-red-400 font-bold">ไม่สามารถอ่านไฟล์ PDF ได้</div>';
                                        console.error('Error rendering PDF:', error);
                                    });

                                    // ลบข้อมูล Base64 ออกจาก HTML
                                    container.removeAttribute('data-b64');
                                }
                            });
    });
                    </script>

                    <?php
else: ?>
                    <div
                        class="bg-white border border-dashed border-gray-300 p-3 rounded text-center text-sm text-gray-500">
                        ไม่มีเอกสารแนบเพิ่มเติมในคำขอนี้
                    </div>
                    <?php
endif; ?>
                </div>
            </div>

            <div class="bg-indigo-50 p-5 rounded-lg border border-indigo-200 shadow-sm mt-6 mb-6">
                <h3 class="font-bold text-indigo-800 mb-4 border-b border-indigo-200 pb-2">
                    <i class="fas fa-calendar-alt"></i> วันที่ออกบัตร และ วันหมดอายุ
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-700">วันออกบัตร (พ.ศ.)</label>
                        <input type="text" id="issue_date_th" value="<?= $issue_th?>"
                            class="w-full border border-gray-300 p-2.5 rounded bg-white text-center font-bold tracking-widest text-lg focus:ring-2 focus:ring-indigo-400 outline-none"
                            placeholder="วว/ดด/ปปปป">
                        <input type="hidden" name="issue_date" id="issue_date_db"
                            value="<?= $req['issue_date'] ?? ''?>">
                        <p class="text-[11px] text-gray-500 mt-1">ปล่อยว่างไว้ ระบบจะตั้งเป็น "วันนี้"
                            อัตโนมัติเมื่อกดอนุมัติ</p>
                    </div>
                    <div>
                        <label class="flex justify-between items-end mb-2">
                            <span class="block text-sm font-bold text-gray-700">วันหมดอายุบัตร (พ.ศ.)</span>
                            <label
                                class="inline-flex items-center text-xs text-blue-700 font-bold cursor-pointer bg-blue-100 px-2 py-1 rounded shadow-sm hover:bg-blue-200 transition">
                                <input type="checkbox" id="is_lifetime" class="mr-1.5 w-3.5 h-3.5" <?= $is_lifetime
    ? 'checked' : ''?>> บัตรตลอดชีพ
                            </label>
                        </label>
                        <input type="text" id="expire_date_th" value="<?= $expire_th?>"
                            class="w-full border border-gray-300 p-2.5 rounded text-center font-bold tracking-widest text-lg focus:ring-2 focus:ring-indigo-400 outline-none <?= $is_lifetime ? 'bg-gray-200 cursor-not-allowed text-gray-400' : 'bg-white'?>"
                            placeholder="วว/ดด/ปปปป" <?= $is_lifetime ? 'readonly' : ''?>>
                        <input type="hidden" name="expire_date" id="expire_date_db"
                            value="<?= $req['expire_date'] ?? ''?>">
                        <p class="text-[11px] text-gray-500 mt-1">ปล่อยว่างไว้ ระบบจะคำนวณวันเกษียณ/หมดอายุ ให้อัตโนมัติ
                        </p>
                    </div>
                </div>
            </div>

            <input type="hidden" name="save_action" id="save_action" value="save">

            <div class="mt-8 flex justify-center gap-4">
                <a href="admin_dashboard.php"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-xl font-bold text-lg shadow-lg transition"><i
                        class="fas fa-arrow-left"></i> ยกเลิก / กลับ</a>

                <button type="submit" onclick="document.getElementById('save_action').value='save';"
                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl font-bold text-lg shadow-lg transition transform hover:scale-105">
                    <i class="fas fa-save"></i> บันทึกอย่างเดียว
                </button>

                <button type="submit"
                    onclick="document.getElementById('save_action').value='save_and_print'; document.getElementById('status').value='SENT_TO_PRINT';"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-bold text-lg shadow-lg transition transform hover:scale-105">
                    <i class="fas fa-print"></i> บันทึกและรอพิมพ์บัตร
                </button>

                <?php if ($dynamic_btn): ?>
                <button type="submit"
                    onclick="document.getElementById('save_action').value='save'; document.getElementById('status').value='<?= $dynamic_btn['target_status']?>';"
                    class="<?= $dynamic_btn['color']?> text-white px-6 py-3 rounded-xl font-bold text-lg shadow-lg transition transform hover:scale-105">
                    <i class="fas <?= $dynamic_btn['icon']?>"></i>
                    <?= $dynamic_btn['label']?>
                </button>
                <?php
endif; ?>
            </div>

        </form>

        <!-- 🟢 ส่วนระบบบันทึกงานของเจ้าหน้าที่ (Staff Notes) -->
        <div class="p-6 bg-gray-50 border-t border-gray-200 mt-4 rounded-b-lg">
            <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-comment-dots text-blue-600 text-xl"></i>
                บันทึกข้อความจากเจ้าหน้าที่ (Staff Notes)
                <span
                    class="bg-blue-100 text-blue-800 text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider font-semibold">บันทึกติดตัวบุคคล</span>
            </h3>

            <div class="bg-white p-4 rounded-xl border border-gray-300 shadow-sm mb-6 relative">
                <textarea id="staff_note_input" rows="3"
                    class="w-full border-none focus:ring-0 p-2 text-gray-700 bg-transparent resize-none outline-none"
                    placeholder="พิมพ์ข้อความบันทึกการทำงาน หรือ หมายเหตุเพิ่มเติมเกี่ยวกับบุคคลนี้..."></textarea>
                <div class="border-t border-gray-100 mt-2 pt-3 flex justify-between items-center px-2">
                    <span class="text-xs text-gray-400"><i class="fas fa-info-circle"></i>
                        ข้อความนี้จะแสดงทุกครั้งที่ค้นหาเลขบัตร ปชช. นี้</span>
                    <button type="button"
                        onclick="saveStaffNote('<?= htmlspecialchars($req['id_card_number'], ENT_QUOTES, 'UTF-8')?>')"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1.5 px-4 rounded-lg shadow-sm transition text-sm flex items-center gap-2">
                        <i class="fas fa-paper-plane"></i> บันทึกข้อความ
                    </button>
                </div>
            </div>

            <div class="relative pl-4 space-y-4 before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-gray-300 before:to-transparent"
                id="notes_container">
                <?php if (!empty($staff_notes)): ?>
                <?php foreach ($staff_notes as $note): ?>
                <div class="relative flex items-start gap-4 md:justify-center">
                    <!-- Timeline Dot -->
                    <div
                        class="absolute left-0 md:left-1/2 w-4 h-4 rounded-full bg-blue-500 border-2 border-white shadow -ml-[7px] md:-ml-2 mt-1.5 z-10">
                    </div>

                    <!-- Note Content -->
                    <div
                        class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 w-full md:w-[45%] md:ml-auto <?=($note === $staff_notes[0]) ? 'md:mr-auto md:ml-0' : ''?> text-sm relative">
                        <!-- Small Arrow -->
                        <div
                            class="absolute top-2 w-3 h-3 bg-white border-t border-l border-gray-200 transform -rotate-45 -left-1.5 md:hidden">
                        </div>

                        <div class="flex justify-between items-start mb-2">
                            <span class="font-bold text-indigo-700 flex items-center gap-1.5">
                                <i class="fas fa-user-tie text-[10px]"></i>
                                <?= htmlspecialchars($note['admin_name'])?>
                            </span>
                            <span class="text-xs text-gray-400 font-semibold bg-gray-100 px-2 py-0.5 rounded">
                                <?= date('d/m/Y H:i', strtotime($note['created_at']))?>
                            </span>
                        </div>
                        <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">
                            <?= htmlspecialchars($note['note_text'])?>
                        </p>
                    </div>
                </div>
                <?php
    endforeach; ?>
                <?php
else: ?>
                <div id="no_notes_msg" class="text-center text-gray-400 text-sm py-4 w-full">
                    ยังไม่มีบันทึกข้อความสำหรับบุคคลนี้</div>
                <?php
endif; ?>
            </div>
        </div>
    </div>

    <div id="photoModal"
        class="fixed inset-0 bg-black bg-opacity-80 hidden z-[100] flex flex-col items-center justify-center p-4">
        <div class="bg-white w-full max-w-lg rounded-xl overflow-hidden shadow-2xl">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-lg"><i class="fas fa-crop-alt text-blue-600"></i> ตัดรูปถ่ายหน้าตรง (อัตราส่วน
                    3:4)</h3>
                <button type="button" onclick="closePhotoModal()" class="text-gray-500 hover:text-red-500"><i
                        class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-4 bg-gray-900 flex justify-center items-center h-[60vh]">
                <img id="cropper_image" class="max-w-full max-h-full">
            </div>
            <div class="p-4 bg-gray-50 border-t flex justify-end gap-2">
                <button type="button" onclick="closePhotoModal()"
                    class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded font-bold shadow-sm transition">ยกเลิก</button>
                <button type="button" onclick="confirmCrop()"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold shadow transition"><i
                        class="fas fa-check"></i> ยืนยันการตัดรูป</button>
            </div>
        </div>
    </div>

    <div id="sigModal"
        class="fixed inset-0 bg-black bg-opacity-80 hidden z-[100] flex flex-col items-center justify-center p-4">
        <div class="bg-white w-full max-w-2xl rounded-xl overflow-hidden shadow-2xl">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-lg"><i class="fas fa-eraser text-purple-600"></i>
                    ปรับแต่ง/ตัดครอป/ลบพื้นหลังลายเซ็น</h3>
                <button type="button" onclick="closeSigModal()" class="text-gray-500 hover:text-red-500"><i
                        class="fas fa-times text-xl"></i></button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 bg-gray-900 h-[50vh]">
                <div class="relative border-r border-gray-700 overflow-hidden flex items-center justify-center">
                    <img id="sig_cropper_image" class="max-w-full max-h-full">
                    <div class="absolute top-2 left-2 bg-black/50 text-white text-[10px] px-2 py-1 rounded">1. ครอป &
                        ซูม (15:8)</div>
                </div>
                <div
                    class="relative bg-white flex flex-col items-center justify-center p-4 custom-checkered-bg overflow-hidden">
                    <canvas id="sig_canvas" class="max-w-full max-h-full shadow-lg border border-gray-300"></canvas>
                    <div class="absolute top-2 left-2 bg-black/50 text-white text-[10px] px-2 py-1 rounded">2. ผลลัพธ์
                        (ขาว-ดำ)</div>
                </div>
            </div>
            <div class="p-6 bg-gray-50 border-t">
                <div class="mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-bold text-gray-700">ปรับระดับความสว่างที่จะถูกลบ (Threshold):
                            <span id="threshold_val" class="text-blue-600">180</span></label>
                        <span class="text-[10px] text-gray-400 font-bold"><i class="fas fa-info-circle"></i>
                            ใช้ลูกกลิ้งเมาส์เพื่อซูมเข้า/ออก นอกกรอบได้</span>
                    </div>
                    <input type="range" id="threshold_slider" min="0" max="255" value="180"
                        class="w-full cursor-pointer h-2 bg-gray-200 rounded-lg appearance-none accent-purple-600 shadow-inner"
                        oninput="renderSignature()">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeSigModal()"
                        class="px-5 py-2.5 bg-gray-300 hover:bg-gray-400 rounded-lg font-bold shadow-sm transition">ยกเลิก</button>
                    <button type="button" onclick="confirmSignature()"
                        class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-bold shadow-lg transition transform hover:scale-105"><i
                            class="fas fa-check"></i> ยืนยันการปรับแต่ง</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ==========================================
        // 🟢 ระบบจัดการรูปถ่าย (Cropper) และลายเซ็น (Threshold)
        // ==========================================
        function setFileToInput(inputId, blob, filename) {
            const dt = new DataTransfer();
            dt.items.add(new File([blob], filename, { type: blob.type }));
            document.getElementById(inputId).files = dt.files;
        }

        // --- ระบบตัดรูป (Cropper) ---
        let cropper = null;
        let currentCropTarget = 'photo'; // 'photo' หรือ 'signature'

        async function loadCropperImage(source, target = 'photo') {
            currentCropTarget = target;
            const modalTitle = document.querySelector('#photoModal h3');
            const aspectRatio = (target === 'photo') ? 3 / 4 : NaN;

            if (target === 'photo') {
                modalTitle.innerHTML = '<i class="fas fa-crop-alt text-blue-600"></i> ตัดรูปถ่ายหน้าตรง (อัตราส่วน 3:4)';
            } else {
                modalTitle.innerHTML = '<i class="fas fa-crop-alt text-purple-600"></i> ตัดครอปรูปลายเซ็น';
            }

            try {
                let objUrl;
                if (source instanceof File || source instanceof Blob) {
                    objUrl = URL.createObjectURL(source);
                } else {
                    // โหลดเป็น Blob ป้องกันติด CORS ตอนวาดลง Canvas (สำหรับรูปจาก Server)
                    const res = await fetch(source);
                    const blob = await res.blob();
                    objUrl = URL.createObjectURL(blob);
                }

                const img = document.getElementById('cropper_image');
                img.onload = () => {
                    document.getElementById('photoModal').classList.remove('hidden');
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(img, {
                        aspectRatio: aspectRatio,
                        viewMode: 1,
                        autoCropArea: 1,
                    });
                };
                img.src = objUrl;
            } catch (error) {
                console.error('Error loading image cross-origin:', error);
                alert('ไม่สามารถโหลดรูปภาพได้');
            }
        }

        function editExistingPhoto(url) { loadCropperImage(url, 'photo'); }

        function handleNewPhoto(input) {
            if (input.files && input.files[0]) {
                loadCropperImage(input.files[0], 'photo');
                input.value = ''; // เคลียร์เพื่อให้เลือกไฟล์เดิมซ้ำได้ใหม่
            }
        }

        function closePhotoModal() {
            document.getElementById('photoModal').classList.add('hidden');
            if (cropper) { cropper.destroy(); cropper = null; }
        }

        function confirmCrop() {
            if (!cropper) return;

            const options = (currentCropTarget === 'photo')
                ? { width: 600, height: 800 }
                : { maxWidth: 1200, maxHeight: 400 };

            cropper.getCroppedCanvas(options).toBlob((blob) => {
                const url = URL.createObjectURL(blob);

                if (currentCropTarget === 'photo') {
                    document.getElementById('photo_preview').src = url;
                    document.getElementById('photo_preview').classList.remove('hidden');
                    document.getElementById('photo_placeholder').classList.add('hidden');
                    setFileToInput('new_photo_input', blob, 'cropped_photo.jpg');
                } else {
                    document.getElementById('sig_preview').src = url;
                    document.getElementById('sig_preview').classList.remove('hidden');
                    document.getElementById('sig_placeholder').classList.add('hidden');
                    setFileToInput('new_sig_input', blob, 'cropped_signature.png');
                }

                closePhotoModal();
            }, (currentCropTarget === 'photo' ? 'image/jpeg' : 'image/png'), 0.9);
        }

        // --- ระบบปรับลายเซ็น (Threshold) ---
        let sigCropper = null;
        let sigImageObj = new Image();
        async function loadSignatureImage(source) {
            try {
                let objUrl;
                if (source instanceof File || source instanceof Blob) {
                    objUrl = URL.createObjectURL(source);
                } else {
                    const res = await fetch(source);
                    const blob = await res.blob();
                    objUrl = URL.createObjectURL(blob);
                }

                const img = document.getElementById('sig_cropper_image');
                img.onload = () => {
                    document.getElementById('sigModal').classList.remove('hidden');
                    document.getElementById('threshold_slider').value = 180;

                    if (sigCropper) sigCropper.destroy();
                    sigCropper = new Cropper(img, {
                        aspectRatio: 15 / 8, // อัตราส่วนตามบัตรมาตรฐาน
                        viewMode: 0,        // สำคัญ: 0 จะซูมออกนอกขอบรูปเดิมได้ (Adding padding/margins)
                        autoCropArea: 0.9,
                        background: false,
                        ready() { renderSignature(); },
                        crop() { renderSignature(); }
                    });
                };
                img.src = objUrl;
            } catch (error) {
                console.error('Error loading signature image:', error);
                alert('ไม่สามารถโหลดรูปลายเซ็นได้');
            }
        }

        function editExistingSignature(url) { loadSignatureImage(url); }

        function handleNewSignature(input) {
            if (input.files && input.files[0]) {
                loadSignatureImage(input.files[0]);
                input.value = '';
            }
        }

        function closeSigModal() {
            document.getElementById('sigModal').classList.add('hidden');
            if (sigCropper) { sigCropper.destroy(); sigCropper = null; }
        }

        function renderSignature() {
            if (!sigCropper) return;

            // ดึง Canvas จาก Cropper ตามขอบที่เราเลือก (รวมพื้นที่สีดำที่เกิดจากการซูมออกด้วย)
            const croppedCanvas = sigCropper.getCroppedCanvas({
                width: 750,
                height: 400,
                fillColor: '#fff', // เติมสีขาวพื้นหลังสำรองไว้ให้ Threshold ลบออก
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            const canvas = document.getElementById('sig_canvas');
            const ctx = canvas.getContext('2d');
            const threshold = parseInt(document.getElementById('threshold_slider').value);
            document.getElementById('threshold_val').innerText = threshold;

            canvas.width = croppedCanvas.width;
            canvas.height = croppedCanvas.height;
            ctx.drawImage(croppedCanvas, 0, 0);

            const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imgData.data;

            for (let i = 0; i < data.length; i += 4) {
                // ถ้ารูปเดิมโปร่งใสอยู่แล้ว หรือสีขาวมากๆ ให้โปร่งใส
                const r = data[i], g = data[i + 1], b = data[i + 2];
                const luma = (r * 0.299) + (g * 0.587) + (b * 0.114);

                if (data[i + 3] < 50 || luma > threshold) {
                    data[i + 3] = 0;
                } else {
                    // ทำให้เส้นดำ
                    data[i] = 0; data[i + 1] = 0; data[i + 2] = 0; data[i + 3] = 255;
                }
            }
            ctx.putImageData(imgData, 0, 0);
        }

        function editExistingSignatureCrop(url) {
            // เปิด sigModal พร้อมกับโหลดออกจาก server
            loadSignatureImage(url);
        }

        function confirmSignature() {
            if (!sigCropper) return;
            renderSignature(); // อัปเดต canvas ล่าสุดก่อน

            const canvas = document.getElementById('sig_canvas');
            canvas.toBlob((blob) => {
                const url = URL.createObjectURL(blob);
                document.getElementById('sig_preview').src = url;
                document.getElementById('sig_preview').classList.remove('hidden');
                document.getElementById('sig_placeholder').classList.add('hidden');
                setFileToInput('new_sig_input', blob, 'processed_signature.png');
                closeSigModal();
            }, 'image/png');
        }

        // ==========================================

        // ==========================================
        $(document).ready(function () {
            $('#issue_date_th, #expire_date_th, #edit_birth_date_th').inputmask({
                mask: "99/99/9999",
                placeholder: "dd/mm/yyyy",
                clearIncomplete: false
            });

            function convertToDBDate(thDateStr) {
                if (thDateStr && thDateStr.length === 10) {
                    let parts = thDateStr.split('/');
                    let d = parseInt(parts[0]);
                    let m = parseInt(parts[1]);
                    let yTH = parseInt(parts[2]);
                    if (d > 0 && d <= 31 && m > 0 && m <= 12 && yTH > 2400) {
                        return (yTH - 543) + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                    }
                }
                return '';
            }

            $('#edit_birth_date_th').on('change blur keyup', function () {
                $('#edit_birth_date_db').val(convertToDBDate($(this).val()));
                let newDbDate = $('#edit_birth_date_db').val();
                if (newDbDate) {
                    let age = new Date().getFullYear() - new Date(newDbDate).getFullYear();
                    let mDiff = new Date().getMonth() - new Date(newDbDate).getMonth();
                    if (mDiff < 0 || (mDiff === 0 && new Date().getDate() < new Date(newDbDate).getDate())) {
                        age--;
                    }
                    $('input[name="age"]').val(age);
                }
            });

            $('#issue_date_th').on('change blur keyup', function () {
                $('#issue_date_db').val(convertToDBDate($(this).val()));
            });
            $('#expire_date_th').on('change blur keyup', function () {
                if (!$('#is_lifetime').is(':checked'))
                    $('#expire_date_db').val(convertToDBDate($(this).val()));
            });

            $('#is_lifetime').change(function () {
                if ($(this).is(':checked')) {
                    $('#expire_date_th').val('ตล--------ดชีพ').prop('readonly', true).addClass('bg-gray-200 cursor-not-allowed text-gray-400');
                    $('#expire_date_db').val('9999-12-31');
                } else {
                    $('#expire_date_th').prop('readonly', false).removeClass('bg-gray-200 cursor-not-allowed text-gray-400').val('');
                    $('#expire_date_db').val('');
                }
            });
        });

        // ==========================================
        // 🟢 ระบบบันทึกงานเจ้าหน้าที่ (AJAX)
        // ==========================================
        function saveStaffNote(idCardNumber) {
            const noteInput = document.getElementById('staff_note_input');
            const noteText = noteInput.value.trim();

            if (!noteText) {
                Swal.fire('แจ้งเตือน', 'กรุณาพิมพ์ข้อความบันทึกก่อนกดปุ่ม', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('id_card_number', idCardNumber);
            formData.append('note_text', noteText);

            Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch('api/admin/action_note.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then(res => res.json()).then(data => {
                if (data.status === 'success') {
                    // Append newly created note UI
                    const container = document.getElementById('notes_container');
                    const noMsg = document.getElementById('no_notes_msg');
                    if (noMsg) noMsg.remove();
                    // Format date
                    const dateObj = new Date(data.note.created_at);
                    const formattedDate = dateObj.toLocaleDateString('en-GB') + ' ' + dateObj.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

                    const newNoteHTML = `
                        <div class="relative flex items-start gap-4 md:justify-center animate-[fadeIn_0.5s_ease-out]">
                            <div class="absolute left-0 md:left-1/2 w-4 h-4 rounded-full bg-green-500 border-2 border-white shadow -ml-[7px] md:-ml-2 mt-1.5 z-10"></div>
                            <div class="bg-green-50 p-4 rounded-xl shadow-sm border border-green-200 w-full md:w-[45%] md:mr-auto md:ml-0 text-sm relative">
                                <div class="absolute top-2 w-3 h-3 bg-green-50 border-t border-l border-green-200 transform -rotate-45 -left-1.5 md:hidden"></div>
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-bold text-green-700 flex items-center gap-1.5">
                                        <i class="fas fa-user-tie text-[10px]"></i> ${data.note.admin_name}
                                    </span>
                                    <span class="text-xs text-green-600 font-semibold bg-green-100 px-2 py-0.5 rounded">
                                        ${formattedDate}
                                    </span>
                                </div>
                                <p class="text-gray-700 leading-relaxed whitespace-pre-wrap">${data.note.note_text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")}</p>
                            </div>
                        </div>
                    `;
                    container.insertAdjacentHTML('afterbegin', newNoteHTML);
                    noteInput.value = '';
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'บันทึกข้อความสำเร็จ',
                        showConfirmButton: false,
                        timer: 3000
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', data.message || 'ไม่สามารถบันทึกข้อความได้', 'error');
                }
            }).catch(err => {
                console.error(err);
                Swal.fire('เกิดข้อผิดพลาด', 'ปัญหาการเชื่อมต่อเซิร์ฟเวอร์', 'e')       });
        }
    </script>

</body>