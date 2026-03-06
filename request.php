<?php
// idcard/request.php
require_once 'connect.php';

// --- 🟢 1. ฟังก์ชันแปลงภาพเป็น Base64 (แก้ปัญหารูปไม่แสดง/ติดสิทธิ์) ---
function getBase64Img($file_path)
{
    if (empty($file_path))
        return '';
    if (file_exists($file_path)) {
        $real_path = realpath($file_path);
    }
    else {
        $real_path = realpath(__DIR__ . '/' . $file_path);
    }
    if (!$real_path || !file_exists($real_path))
        return '';
    $data = @file_get_contents($real_path);
    if (!$data)
        return '';
    $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

// --- 🟢 2. โค้ดเช็คขีดจำกัดไฟล์อัปโหลดจาก Server ---
function getBytesFromIni($val)
{
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $val = (int)$val;
    switch ($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}
$upload_max = getBytesFromIni(ini_get('upload_max_filesize'));
$post_max = getBytesFromIni(ini_get('post_max_size'));
$max_bytes = min($upload_max, $post_max);
$max_mb = max(1, floor($max_bytes / (1024 * 1024)));

// เช็ค Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_edit = isset($_SESSION['edit_request_id']) && !empty($_SESSION['edit_request_id']);

if (!isset($_SESSION['user_id']) && !isset($_SESSION['public_access'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $log_msg = $is_edit ? "เปิดหน้าฟอร์มเพื่อแก้ไขข้อมูลคำขอ (ID: {$_SESSION['edit_request_id']})" : "เปิดหน้าแบบฟอร์มเพื่อกรอกคำขอทำบัตรใหม่";
    $target_id = $is_edit ? $_SESSION['edit_request_id'] : null;
    saveLog($conn, 'VIEW_REQUEST_FORM', $log_msg, $target_id);
}

// 🟢 ดึงข้อมูลไฟล์แนบเดิมในระบบ
$pre = $_SESSION['form_prefill'] ?? [];
$id_card_val = $_SESSION['id_card_public'] ?? ($_SESSION['id_card'] ?? '');
$phone_val = $_SESSION['phone_public'] ?? '';

// 🟢 แปลงรูปถ่ายและลายเซ็นเก่าเป็น Base64 เพื่อให้หน้าเว็บดึงไปโชว์ได้เลย
$photo_path = $pre['photo_path'] ?? '';
$signature_file = $pre['signature_file'] ?? '';
$photo_b64 = getBase64Img($photo_path);
$sig_b64 = getBase64Img($signature_file);

$docs_json_str = $pre['documents_json'] ?? '[]';
$docs_arr = json_decode($docs_json_str, true) ?? [];

function val($key, $default = '')
{
    global $pre;
    return isset($pre[$key]) && $pre[$key] !== '' ? htmlspecialchars($pre[$key]) : $default;
}

// แกะชื่อ-สกุลออกจาก full_name
$fname = '';
$lname = '';
if (!empty($pre['full_name'])) {
    $parts = explode(' ', $pre['full_name']);
    $fname = htmlspecialchars($parts[0] ?? '');
    $lname = htmlspecialchars($parts[1] ?? '');
}

// ถอดรหัสที่อยู่
$addr = [];
if (!empty($pre['address_json'])) {
    $addr = json_decode($pre['address_json'], true) ?? [];
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Master Data
$all_ranks = $conn->query("SELECT * FROM idcard_ranks")->fetchAll(PDO::FETCH_ASSOC);
$ranks_by_id = array_column($all_ranks, null, 'id');
$rank_sort_order = [1, 2, 3, 15, 4, 16, 5, 17, 6, 18, 7, 19, 8, 20, 9, 21, 10, 22, 11, 23, 12, 24, 13, 25, 14, 26];
$orgs = $conn->query("SELECT * FROM idcard_organizations WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$pos_dict_raw = $conn->query("SELECT position_name, default_org_id FROM idcard_positions ORDER BY position_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$pos_auto_map = [];
$pos_dict = [];
foreach ($pos_dict_raw as $p) {
    $pos_dict[] = $p['position_name'];
    if (!empty($p['default_org_id'])) {
        $pos_auto_map[$p['position_name']] = $p['default_org_id'];
    }
}
$pos_auto_map_json = json_encode($pos_auto_map);

$card_types = $conn->query("SELECT * FROM idcard_card_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$current_date_th = date('d') . ' / ' . date('m') . ' / ' . (date('Y') + 543);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ยื่นคำขอมีบัตร - Police ID Card</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>
    <script type="text/javascript"
        src="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dependencies/JQL.min.js"></script>
    <script type="text/javascript"
        src="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dependencies/typeahead.bundle.js"></script>
    <link rel="stylesheet"
        href="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dist/jquery.Thailand.min.css">
    <script type="text/javascript"
        src="https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/dist/jquery.Thailand.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        .input-field {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            outline: none;
            transition: border-color 0.15s;
            font-size: 16px;
        }

        .input-field:focus {
            border-color: #2563eb;
            ring: 2px;
            ring-color: #bfdbfe;
        }

        .label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.25rem;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: bold;
            color: #1e3a8a;
            border-left: 4px solid #1e3a8a;
            padding-left: 0.5rem;
            margin-bottom: 1rem;
        }

        .checkerboard-bg {
            background-image: linear-gradient(45deg, #e5e7eb 25%, transparent 25%), linear-gradient(-45deg, #e5e7eb 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #e5e7eb 75%), linear-gradient(-45deg, transparent 75%, #e5e7eb 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }

        .awesomplete {
            width: 100%;
        }

        .awesomplete>ul {
            border-radius: 0.375rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            margin-top: 4px;
            max-height: 250px;
            overflow-y: auto;
        }

        .awesomplete>ul>li {
            padding: 10px 12px;
            font-family: 'Sarabun', sans-serif;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            font-size: 15px;
        }

        .awesomplete>ul>li:hover,
        .awesomplete>ul>li[aria-selected="true"] {
            background-color: #eff6ff;
            color: #1e40af;
            font-weight: bold;
        }

        .awesomplete mark {
            background-color: transparent;
            color: #d97706;
            font-weight: bold;
        }
    </style>
</head>

<body class="bg-gray-50 pb-20">

    <?php include 'public_navbar.php'; ?>

    <div class="container mx-auto mt-6 p-4 max-w-5xl">
        <div class="bg-white rounded-lg shadow-xl overflow-hidden">
            <div class="bg-blue-900 text-white p-5 flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold">📝
                        <?= $is_edit ? 'แก้ไขข้อมูลคำขอมีบัตร (รอตรวจสอบ)' : 'คำขอมีบัตรประจำตัวเจ้าหน้าที่ของรัฐ'?>
                    </h2>
                    <p class="text-sm text-blue-200">
                        <?= $is_edit ? 'แก้ไขข้อมูลที่ผิดพลาดได้เลย (ไม่ต้องแนบไฟล์ใหม่หากเคยอัปโหลดไว้แล้ว)' : 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง'?>
                    </p>
                </div>
            </div>

            <form action="save_request" method="POST" enctype="multipart/form-data"
                class="p-4 md:p-8 space-y-6 md:space-y-8 pb-32 md:pb-8" id="requestForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
                <input type="hidden" name="edit_request_id" value="<?= $is_edit ? $_SESSION['edit_request_id'] : ''?>">

                <div class="bg-gray-50 p-4 rounded border">
                    <h3 class="font-bold text-lg text-gray-700 border-b pb-2 mb-4">1. ข้อมูลทั่วไป</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label">เขียนที่ <span class="text-red-500">*</span></label>
                            <input type="text" name="written_at" value="<?= val('written_at', 'ภ.จว.ปทุมธานี')?>"
                                required class="input-field">
                        </div>
                        <div>
                            <label class="label">วันที่</label>
                            <input type="text" value="<?= $current_date_th?>" readonly class="input-field bg-gray-100">
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="section-title">2. ประวัติส่วนตัว</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
                        <div>
                            <label class="label">ยศ <span class="text-red-500">*</span></label>
                            <select name="rank_id" required class="input-field">
                                <option value="">-- เลือกยศ --</option>
                                <?php foreach ($rank_sort_order as $rid):
    if (isset($ranks_by_id[$rid])):
        $r = $ranks_by_id[$rid]; ?>
                                <option value="<?= $r['id']?>" <?=val('rank_id')==$r['id'] ? 'selected' : '' ?>>
                                    <?= $r['rank_name']?>
                                </option>
                                <?php
    endif;
endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="label">ชื่อ <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" required value="<?= $fname?>" class="input-field">
                        </div>
                        <div>
                            <label class="label">นามสกุล <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" required value="<?= $lname?>" class="input-field">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-4">
                        <div>
                            <label class="label">วัน/เดือน/ปี เกิด (พ.ศ.) <span class="text-red-500">*</span></label>
                            <input type="text" id="birth_date_th" name="birth_date_th_display" placeholder="วว/ดด/ปปปป"
                                required class="input-field text-center bg-white" autocomplete="off"
                                inputmode="numeric">
                            <input type="hidden" name="birth_date" id="birth_date_db" value="<?= val('birth_date')?>">
                            <p class="text-xs text-blue-600 mt-1">เช่น 05/03/2530</p>
                        </div>
                        <div>
                            <label class="label">อายุ (ปี) <span class="text-red-500">*</span></label>
                            <input type="number" name="age" id="age" value="<?= val('age')?>" required readonly
                                class="input-field bg-gray-100 text-center font-bold text-blue-800">
                        </div>
                        <div>
                            <label class="label">สัญชาติ <span class="text-red-500">*</span></label>
                            <input type="text" name="nationality" value="<?= val('nationality', 'ไทย')?>" required
                                class="input-field">
                        </div>
                        <div>
                            <label class="label">หมู่โลหิต <span class="text-red-500">*</span></label>
                            <select name="blood_type" required class="input-field">
                                <option value="">-- เลือก --</option>
                                <?php foreach (['O', 'A', 'B', 'AB'] as $b): ?>
                                <option value="<?= $b?>" <?=val('blood_type')==$b ? 'selected' : '' ?>>
                                    <?= $b?>
                                </option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label">เลขประจำตัวประชาชน (13 หลัก) <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="id_card_number" maxlength="13" value="<?= $id_card_val?>" required
                                readonly class="input-field bg-gray-100">
                        </div>
                        <div>
                            <label class="label">โทรศัพท์มือถือ <span class="text-red-500">*</span></label>
                            <input type="text" name="phone" value="<?= val('phone', $phone_val)?>" required
                                class="input-field">
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 p-4 rounded border border-blue-100">
                    <h3 class="section-title">3. ที่อยู่ตามทะเบียนบ้าน</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div>
                            <label class="label">บ้านเลขที่ <span class="text-red-500">*</span></label>
                            <input type="text" name="addr_house_no"
                                value="<?= htmlspecialchars($addr['house_no'] ?? '')?>" required class="input-field">
                        </div>
                        <div>
                            <label class="label">หมู่ที่</label>
                            <input type="text" name="addr_moo" value="<?= htmlspecialchars($addr['moo'] ?? '')?>"
                                class="input-field">
                        </div>
                        <div class="md:col-span-2">
                            <label class="label">ถนน</label>
                            <input type="text" name="addr_road" value="<?= htmlspecialchars($addr['road'] ?? '')?>"
                                class="input-field">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4" id="address-container">
                        <div>
                            <label class="label">รหัสไปรษณีย์ <span class="text-red-500">*</span></label>
                            <input type="text" name="addr_zipcode" id="addr_zipcode"
                                value="<?= htmlspecialchars($addr['zipcode'] ?? '')?>" required class="input-field"
                                placeholder="กรอกรหัส...">
                        </div>
                        <div>
                            <label class="label">ตำบล/แขวง <span class="text-red-500">*</span></label>
                            <input type="text" name="addr_tambon" id="addr_tambon"
                                value="<?= htmlspecialchars($addr['tambon'] ?? '')?>" required class="input-field">
                        </div>
                        <div>
                            <label class="label">อำเภอ/เขต <span class="text-red-500">*</span></label>
                            <input type="text" name="addr_amphoe" id="addr_amphoe"
                                value="<?= htmlspecialchars($addr['amphoe'] ?? '')?>" required class="input-field">
                        </div>
                        <div>
                            <label class="label">จังหวัด <span class="text-red-500">*</span></label>
                            <input type="text" name="addr_province" id="addr_province"
                                value="<?= htmlspecialchars($addr['province'] ?? '')?>" required class="input-field">
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="section-title">4. ที่อยู่ที่สามารถติดต่อได้</h3>
                    <div class="mb-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="contact_address_type" value="SAME"
                                <?=val('contact_address_type', 'SAME' )=='SAME' ? 'checked' : '' ?>
                            onchange="toggleContactAddress()" class="form-radio text-blue-600">
                            <span class="ml-2">เหมือนที่อยู่ตามทะเบียนบ้าน</span>
                        </label>
                        <label class="inline-flex items-center ml-6">
                            <input type="radio" name="contact_address_type" value="OTHER"
                                <?=val('contact_address_type')=='OTHER' ? 'checked' : '' ?>
                            onchange="toggleContactAddress()" class="form-radio text-blue-600">
                            <span class="ml-2">ที่อยู่อื่น</span>
                        </label>
                    </div>
                    <div id="contact_addr_box" class="hidden bg-gray-50 p-4 rounded border">
                        <label class="label">ระบุที่อยู่ติดต่อ <span class="text-red-500">*</span></label>
                        <textarea name="contact_address_detail" id="contact_address_detail"
                            class="input-field h-24"><?= val('contact_address_type') == 'OTHER' ? val('contact_address_json') : ''?></textarea>
                    </div>
                </div>

                <div>
                    <h3 class="section-title">5. ข้อมูลการทำงาน</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                        <div>
                            <label class="label">ประเภทบัตร <span class="text-red-500">*</span></label>
                            <select name="card_type_id" required class="input-field">
                                <option value="">-- เลือกประเภทบัตร --</option>
                                <?php foreach ($card_types as $ct): ?>
                                <option value="<?= $ct['id']?>" <?=val('card_type_id')==$ct['id'] ? 'selected' : '' ?>>
                                    <?= $ct['type_name']?>
                                </option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="label">ประเภทเจ้าหน้าที่ <span class="text-red-500">*</span></label>
                            <select name="officer_type" required class="input-field">
                                <option value="POLICE" <?=val('officer_type')=='POLICE' ? 'selected' : '' ?>
                                    >ข้าราชการตำรวจ</option>
                                <option value="PERMANENT_EMP" <?=val('officer_type')=='PERMANENT_EMP' ? 'selected' : ''
                                    ?>>ลูกจ้างประจำ</option>
                                <option value="GOV_EMP" <?=val('officer_type')=='GOV_EMP' ? 'selected' : '' ?>
                                    >พนักงานราชการ</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label">ตำแหน่งปัจจุบัน <span class="text-red-500">*</span></label>
                            <input type="text" name="position" id="position_input" value="<?= val('position')?>"
                                required class="input-field" autocomplete="off"
                                placeholder="- พิมพ์ค้นหา หรือพิมพ์เพิ่มเอง -">
                            <datalist id="pos_list">
                                <?php foreach ($pos_dict as $p): ?>
                                <option>
                                    <?= htmlspecialchars($p)?>
                                </option>
                                <?php
endforeach; ?>
                            </datalist>
                            <div class="text-xs text-gray-500 mt-2 bg-gray-50 p-2 rounded border border-gray-200">
                                <div class="font-bold text-gray-700 mb-1">💡 คำแนะนำการเลือกตำแหน่ง:</div>
                                <ul class="list-disc pl-4 space-y-0.5">
                                    <li>พิมพ์ชื่อ <strong class="text-blue-600">สภ.</strong>
                                        จากนั้นเลือกตำแหน่งจากรายการ</li>
                                    <li>หาก <strong class="text-red-600">"ไม่พบ" ตำแหน่ง:</strong>
                                        ให้กรอกข้อมูลด้วยตนเอง</li>
                                    <li><strong class="text-indigo-600">กองร้อย คฝ.:</strong> ให้พิมพ์
                                        กองร้อยควบคุมฝูงชน</li>
                                </ul>
                            </div>
                        </div>

                        <div>
                            <label class="label">สังกัด <span class="text-red-500">*</span></label>
                            <select name="org_id" class="input-field">
                                <option value="">-- เลือกสังกัด --</option>
                                <?php foreach ($orgs as $o): ?>
                                <option value="<?= $o['id']?>" <?=val('org_id')==$o['id'] ? 'selected' : '' ?>>
                                    <?= $o['org_name']?>
                                </option>
                                <?php
endforeach; ?>
                            </select>
                            <div class="text-xs text-gray-500 mt-2 bg-gray-50 p-2 rounded border border-gray-200">
                                <div class="font-bold text-gray-700 mb-1">💡 คำแนะนำการเลือกสังกัด:</div>
                                <ul class="list-disc pl-4 space-y-0.5">
                                    <li>ระดับ <strong class="text-blue-600">สภ.:</strong> ให้เลือก จว.ปทุมธานี</li>
                                    <li>ระดับ <strong class="text-indigo-600">กก.สส.:</strong> ให้เลือก ภ.จว.ปทุมธานี
                                    </li>
                                    <li><strong class="text-purple-600">กองร้อย คฝ.:</strong> ให้เลือก
                                        กก.สส.ภ.จว.ปทุมธานี</li>
                                    <li><strong class="text-orange-600">ฝอ., รอง ผบก.:</strong> ให้ปล่อยเป็น <strong
                                            class="text-red-600">ค่าว่าง</strong></li>
                                </ul>
                                <div class="mt-1 text-green-600 font-semibold italic">
                                    สังกัดโดยปกติจะถูกเลือกให้อัตโนมัติ ไม่ต้องแก้</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-yellow-50 p-4 rounded border border-yellow-200">
                    <h3 class="section-title">6. เหตุผลของการขอบัตร <span class="text-red-500">*</span></h3>

                    <div class="mb-2">
                        <label class="inline-flex items-center font-bold">
                            <input type="radio" name="request_reason" value="FIRST" <?=val('request_reason')=='FIRST'
                                ? 'checked' : '' ?> onclick="toggleReason(); updateRequiredDocs();" required
                            class="form-radio h-5 w-5 text-blue-600">
                            <span class="ml-2">1. ขอมีบัตรครั้งแรก (บรรจุครั้งแรก)</span>
                        </label>
                    </div>

                    <div class="mb-2">
                        <label class="inline-flex items-center font-bold">
                            <input type="radio" name="request_reason" value="NEW" <?=val('request_reason')=='NEW'
                                ? 'checked' : '' ?> onclick="toggleReason(); updateRequiredDocs();" required
                            class="form-radio h-5 w-5 text-blue-600">
                            <span class="ml-2">2. ขอมีบัตรใหม่ เนื่องจาก</span>
                        </label>
                        <div class="ml-8 mt-1 space-y-2 hidden" id="reason_new_options">
                            <label class="inline-flex items-center"><input type="radio" name="request_reason_detail"
                                    value="EXPIRED" <?=val('request_reason_detail')=='EXPIRED' ? 'checked' : '' ?>
                                onclick="checkCardWarning(this); updateRequiredDocs();" class="form-radio"><span
                                    class="ml-2">บัตรหมดอายุ</span></label>
                            <label class="inline-flex items-center ml-4"><input type="radio"
                                    name="request_reason_detail" value="LOST" <?=val('request_reason_detail')=='LOST'
                                    ? 'checked' : '' ?> onclick="updateRequiredDocs();" class="form-radio"><span
                                    class="ml-2">บัตรหาย/ถูกทำลาย</span></label>
                            <div class="mt-2"><label class="text-xs text-gray-600 block mb-1">ระบุเลขทะเบียนบัตรเดิม
                                    (ถ้ามี)</label><input type="text" name="old_card_number"
                                    value="<?= val('old_card_number')?>" placeholder="เช่น 0016.5-xx-xxxx"
                                    class="input-field w-2/3"></div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="inline-flex items-center font-bold">
                            <input type="radio" name="request_reason" value="CHANGE" <?=val('request_reason')=='CHANGE'
                                ? 'checked' : '' ?> onclick="toggleReason(); updateRequiredDocs();" required
                            class="form-radio h-5 w-5 text-blue-600">
                            <span class="ml-2">3. ขอเปลี่ยนบัตร เนื่องจาก</span>
                        </label>
                        <div class="ml-8 mt-1 grid grid-cols-1 md:grid-cols-2 gap-2 hidden" id="reason_change_options">
                            <label class="inline-flex items-center"><input type="radio" name="request_reason_detail"
                                    value="CHANGE_POS" <?=val('request_reason_detail')=='CHANGE_POS' ? 'checked' : '' ?>
                                onclick="updateRequiredDocs();" class="form-radio"><span
                                    class="ml-2">เลื่อนยศ/ตำแหน่ง</span></label>
                            <label class="inline-flex items-center"><input type="radio" name="request_reason_detail"
                                    value="CHANGE_NAME" <?=val('request_reason_detail')=='CHANGE_NAME' ? 'checked' : ''
                                    ?> onclick="updateRequiredDocs();" class="form-radio"><span
                                    class="ml-2">เปลี่ยนชื่อตัว</span></label>
                            <label class="inline-flex items-center"><input type="radio" name="request_reason_detail"
                                    value="CHANGE_SURNAME" <?=val('request_reason_detail')=='CHANGE_SURNAME' ? 'checked'
                                    : '' ?> onclick="updateRequiredDocs();" class="form-radio"><span
                                    class="ml-2">เปลี่ยนชื่อสกุล</span></label>
                            <label class="inline-flex items-center"><input type="radio" name="request_reason_detail"
                                    value="CHANGE_BOTH" <?=val('request_reason_detail')=='CHANGE_BOTH' ? 'checked' : ''
                                    ?> onclick="updateRequiredDocs();" class="form-radio"><span
                                    class="ml-2">เปลี่ยนชื่อและสกุล</span></label>
                            <label class="inline-flex items-center"><input type="radio" name="request_reason_detail"
                                    value="DAMAGED" <?=val('request_reason_detail')=='DAMAGED' ? 'checked' : '' ?>
                                onclick="checkCardWarning(this); updateRequiredDocs();" class="form-radio"><span
                                    class="ml-2">ชำรุด</span></label>
                            <label class="inline-flex items-center"><input type="radio" name="request_reason_detail"
                                    value="RETIRED" <?=val('request_reason_detail')=='RETIRED' ? 'checked' : '' ?>
                                onclick="updateRequiredDocs();" class="form-radio"><span
                                    class="ml-2">เกษียณ</span></label>
                            <div class="col-span-2 flex items-center">
                                <label class="inline-flex items-center"><input type="radio" name="request_reason_detail"
                                        value="OTHER" <?=val('request_reason_detail')=='OTHER' ? 'checked' : '' ?>
                                    onclick="updateRequiredDocs();" class="form-radio"><span
                                        class="ml-2 whitespace-nowrap mr-2">อื่นๆ</span></label>
                                <input type="text" name="request_reason_other" value="<?= val('request_reason_other')?>"
                                    placeholder="ระบุเหตุผล..." class="input-field py-1">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <h3 class="section-title">7. หลักฐานประกอบ</h3>

                    <div class="border p-6 rounded bg-gray-100 mb-6">
                        <label class="block font-bold text-lg mb-2">1. รูปถ่ายหน้าตรง (พื้นหลังสีขาว หรือ น้ำเงิน)
                            <?php if ($is_edit): ?>
                            <span class="text-sm text-blue-600 ml-2">📌 ระบบดึงรูปเดิมมาให้แล้ว
                                สามารถนำมาตัดแต่งใหม่ได้</span>
                            <?php
else: ?>
                            <span class="text-red-500">* บังคับ</span>
                            <?php
endif; ?>
                        </label>

                        <div class="flex items-start gap-4 mb-3">
                            <div
                                class="w-24 h-32 bg-gray-200 border border-gray-300 flex items-center justify-center overflow-hidden rounded shadow-inner flex-shrink-0 relative">
                                <img id="photo_preview" src="<?= $photo_b64?>"
                                    class="w-full h-full object-cover <?= $photo_b64 ? '' : 'hidden'?>">
                                <span id="photo_placeholder"
                                    class="text-xs text-gray-500 <?= $photo_b64 ? 'hidden' : ''?>">No Photo</span>
                            </div>
                            <div class="flex-1 space-y-2">
                                <?php if ($photo_b64): ?>
                                <button type="button" onclick="editExistingPhoto()"
                                    class="bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded text-xs font-bold hover:bg-indigo-200 transition shadow-sm w-full md:w-auto text-left"><i
                                        class="fas fa-crop-alt"></i> ครอปรูปเดิมในระบบใหม่</button>
                                <?php
endif; ?>

                                <label
                                    class="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded text-xs font-bold hover:bg-gray-50 transition shadow-sm w-full md:w-auto block md:inline-block text-left cursor-pointer mt-2 md:mt-0 md:ml-2">
                                    <i class="fas fa-upload"></i> อัปโหลดรูปใหม่ (และครอปภาพ)
                                    <input type="file" id="upload_image" accept="image/*" class="hidden"
                                        onchange="handleNewPhoto(this)">
                                </label>
                            </div>
                        </div>
                        <input type="hidden" name="cropped_photo_data" id="cropped_photo_data">
                    </div>

                    <div class="border p-6 rounded bg-blue-50 border-blue-200 mb-6 shadow-sm">
                        <label class="block font-bold text-lg mb-2 text-blue-900">2. แบบรับรอง ทร.12/2 หรือ
                            สำเนาทะเบียนบ้าน หรือ บัตรประชาชน (PDF/รูปภาพ)</label>
                        <?php if ($is_edit): ?>
                        <div
                            class="mb-4 p-3 bg-green-50 border border-green-300 rounded text-sm text-green-800 font-bold flex items-center gap-2">
                            <i class="fas fa-lock text-xl"></i> 🔒 มีไฟล์สำเนาในระบบแล้ว (เพื่อความปลอดภัย
                            จึงซ่อนไฟล์เดิมไว้ หากต้องการเปลี่ยนให้เลือกอัปโหลดใหม่)
                        </div>
                        <?php
else: ?>
                        <span class="text-red-500 text-sm mb-2 block">* บังคับ</span>
                        <?php
endif; ?>
                        <input type="file" name="doc_idcard_house" id="doc_idcard_house" accept=".pdf,.jpg,.png,.jpeg"
                            class="w-full text-sm bg-white p-2 border rounded">
                    </div>

                    <div class="border p-6 rounded bg-gray-50 mb-6" id="dynamic_docs_section">
                        <label class="block font-bold text-lg mb-2">3. เอกสารเพิ่มเติม
                            (ระบบกำหนดให้อัตโนมัติจากเหตุผลข้อ 6)</label>

                        <?php if ($is_edit && count($docs_arr) > 0): ?>
                        <div
                            class="mb-4 p-3 bg-green-50 border border-green-300 rounded text-sm text-green-800 font-bold flex items-center gap-2">
                            <i class="fas fa-lock text-xl"></i> 🔒 มีเอกสารแนบเพิ่มเติมในระบบจำนวน
                            <?= count($docs_arr)?> ไฟล์แล้ว (เพื่อความปลอดภัย จึงซ่อนไฟล์เดิมไว้
                            หากต้องการเปลี่ยนให้เลือกอัปโหลดใหม่)
                        </div>
                        <?php
endif; ?>

                        <div class="space-y-4" id="docs_container">
                            <div id="box_doc_position"
                                class="hidden flex flex-col gap-2 p-4 bg-white border border-blue-200 rounded shadow-sm">
                                <label class="font-semibold text-blue-900">📌 แนบคำสั่งตำแหน่งปัจจุบัน <span
                                        class="text-red-500">*</span></label>
                                <input type="file" name="doc_position" id="doc_position" accept=".pdf,.jpg,.png"
                                    class="w-full text-sm">
                            </div>

                            <div id="box_doc_old_card"
                                class="hidden flex flex-col gap-2 p-4 bg-white border border-blue-200 rounded shadow-sm">
                                <label class="font-semibold text-blue-900">📌 สำเนาบัตรข้าราชการเดิม <span
                                        class="text-red-500">*</span></label>
                                <input type="file" name="doc_old_card" id="doc_old_card" accept=".pdf,.jpg,.png"
                                    class="w-full text-sm">
                            </div>

                            <div id="box_doc_blood"
                                class="hidden flex flex-col gap-2 p-4 bg-white border border-blue-200 rounded shadow-sm">
                                <label class="font-semibold text-blue-900">📌 แนบใบรับรองผลการตรวจหมู่โลหิต<span
                                        class="text-red-500">*</span></label>
                                <input type="file" name="doc_blood" id="doc_blood" accept=".pdf,.jpg,.png"
                                    class="w-full text-sm">
                            </div>

                            <div id="box_doc_lost"
                                class="hidden flex flex-col gap-2 p-4 bg-white border border-blue-200 rounded shadow-sm">
                                <label class="font-semibold text-blue-900">📌 บันทึกแจ้งความ (ใบแจ้งความบัตรหาย) <span
                                        class="text-red-500">*</span></label>
                                <input type="file" name="doc_lost" id="doc_lost" accept=".pdf,.jpg,.png"
                                    class="w-full text-sm">
                            </div>

                            <div id="box_doc_name_change"
                                class="hidden flex flex-col gap-2 p-4 bg-white border border-blue-200 rounded shadow-sm">
                                <label class="font-semibold text-blue-900">📌 ใบเปลี่ยนชื่อตัว <span
                                        class="text-red-500">*</span></label>
                                <input type="file" name="doc_name_change" id="doc_name_change" accept=".pdf,.jpg,.png"
                                    class="w-full text-sm">
                            </div>

                            <div id="box_doc_surname_change"
                                class="hidden flex flex-col gap-2 p-4 bg-white border border-blue-200 rounded shadow-sm">
                                <label class="font-semibold text-blue-900">📌 ใบเปลี่ยนชื่อสกุล <span
                                        class="text-red-500">*</span></label>
                                <input type="file" name="doc_surname_change" id="doc_surname_change"
                                    accept=".pdf,.jpg,.png" class="w-full text-sm">
                            </div>

                            <div id="box_doc_retired"
                                class="hidden flex flex-col gap-2 p-4 bg-white border border-blue-200 rounded shadow-sm">
                                <label class="font-semibold text-blue-900">📌 แนบคำสั่งเกษียณอายุราชการ <span
                                        class="text-red-500">*</span></label>
                                <input type="file" name="doc_retired" id="doc_retired" accept=".pdf,.jpg,.png"
                                    class="w-full text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <h3 class="section-title">8. ลายมือชื่อผู้ยื่นคำขอ
                        <?php if ($is_edit && $sig_b64): ?>
                        <span class="text-sm text-blue-600 ml-2">📌 ระบบดึงลายเซ็นเดิมมาให้แล้ว</span>
                        <?php
else: ?>
                        <span class="text-red-500">*</span>
                        <?php
endif; ?>
                    </h3>

                    <div class="flex justify-center gap-6 mb-4">
                        <label
                            class="inline-flex items-center cursor-pointer bg-white px-4 py-2 rounded-lg border shadow-sm hover:bg-gray-50 transition">
                            <input type="radio" name="sig_method" value="draw" onchange="toggleSigMethod()"
                                class="form-radio text-blue-600 w-5 h-5">
                            <span class="ml-2 font-bold text-gray-700"><i class="fas fa-pen-nib text-blue-600"></i>
                                เซ็นชื่อสดหน้าจอ</span>
                        </label>
                        <label
                            class="inline-flex items-center cursor-pointer bg-white px-4 py-2 rounded-lg border shadow-sm hover:bg-gray-50 transition">
                            <input type="radio" name="sig_method" value="upload" checked onchange="toggleSigMethod()"
                                class="form-radio text-blue-600 w-5 h-5">
                            <span class="ml-2 font-bold text-gray-700"><i class="fas fa-file-image text-green-600"></i>
                                อัปโหลด / ใช้ลายเซ็นเดิม</span>
                        </label>
                    </div>

                    <div id="sig_panel_draw" class="hidden flex flex-col items-center">
                        <div class="border-2 border-dashed border-gray-400 rounded-lg bg-white shadow-inner">
                            <canvas id="signature-pad" width="400" height="200" class="cursor-crosshair"></canvas>
                        </div>
                        <div class="mt-2 text-sm text-red-600 cursor-pointer underline hover:text-red-800"
                            id="clear-sig">ล้างลายเซ็น</div>
                    </div>

                    <div id="sig_panel_upload" class="flex flex-col items-center w-full">
                        <div class="bg-gray-50 p-4 rounded border border-gray-200 w-full max-w-md mb-4">
                            <div class="flex items-center justify-center gap-3 mb-3">
                                <div
                                    class="w-40 h-16 bg-white border border-dashed border-gray-400 flex items-center justify-center rounded p-1 shadow-inner relative checkerboard-bg">
                                    <img id="sig_preview" src="<?= $sig_b64?>"
                                        class="h-full object-contain <?= $sig_b64 ? '' : 'hidden'?>">
                                    <span id="sig_placeholder"
                                        class="text-xs text-gray-400 <?= $sig_b64 ? 'hidden' : ''?>">ไม่มีลายเซ็น</span>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <?php if ($sig_b64): ?>
                                <button type="button" onclick="editExistingSignature()"
                                    class="bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded text-xs font-bold hover:bg-indigo-200 transition shadow-sm block w-full text-center"><i
                                        class="fas fa-eraser"></i> ลบพื้นหลังลายเซ็นเดิมให้โปร่งใส</button>
                                <?php
endif; ?>

                                <label
                                    class="bg-white border border-gray-300 text-gray-700 px-3 py-1.5 rounded text-xs font-bold hover:bg-gray-50 transition shadow-sm w-full block text-center cursor-pointer">
                                    <i class="fas fa-upload"></i> อัปโหลดลายเซ็นใหม่
                                    <input type="file" id="new_sig_input" accept="image/*" class="hidden"
                                        onchange="handleNewSignature(this)">
                                </label>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="signature_data" id="signature_data">
                </div>

                <div
                    class="fixed bottom-0 left-0 w-full bg-white p-4 border-t border-gray-200 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.1)] z-40 md:static md:bg-transparent md:border-none md:shadow-none md:p-0 md:pt-6 md:z-auto">
                    <button type="submit"
                        class="w-full bg-blue-900 hover:bg-blue-800 text-white font-bold py-4 px-6 rounded-xl shadow-lg text-xl transition transform active:scale-95 md:hover:scale-[1.01]">
                        🚀 ยืนยันและส่งคำขอ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="photoModal"
        class="fixed inset-0 bg-black bg-opacity-80 hidden z-[100] flex flex-col items-center justify-center p-4">
        <div class="bg-white w-full max-w-lg rounded-xl overflow-hidden shadow-2xl">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-lg"><i class="fas fa-crop-alt text-blue-600"></i> ตัดรูปถ่ายหน้าตรง (สัดส่วน
                    25:30)</h3>
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
        <div class="bg-white w-full max-w-lg rounded-xl overflow-hidden shadow-2xl">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-lg"><i class="fas fa-eraser text-purple-600"></i> ปรับแต่งลบพื้นหลังลายเซ็น
                </h3>
                <button type="button" onclick="closeSigModal()" class="text-gray-500 hover:text-red-500"><i
                        class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6 bg-gray-100 flex flex-col items-center justify-center">
                <p class="text-xs text-gray-500 mb-2 text-center">เลื่อนแถบเพื่อลบพื้นหลังที่ติดมาให้กลายเป็นโปร่งใส</p>
                <div
                    class="w-full h-32 bg-white border-2 border-dashed border-gray-400 flex items-center justify-center relative shadow-inner overflow-hidden mb-4 checkerboard-bg">
                    <canvas id="sig_canvas" class="max-w-full max-h-full"></canvas>
                </div>
                <div class="w-full mb-3">
                    <label class="flex justify-between text-sm font-bold text-gray-700 mb-2"><span>1. ลบพื้นหลัง
                            (Threshold):</span><span id="threshold_val" class="text-blue-600">180</span></label>
                    <input type="range" id="threshold_slider" min="0" max="255" value="180"
                        class="w-full cursor-pointer" oninput="renderSignature()">
                </div>
                <div class="w-full">
                    <label class="flex justify-between text-sm font-bold text-gray-700 mb-2"><span>2.
                            เพิ่มความเข้มลายเส้น:</span><span id="darkness_val" class="text-blue-600">0%</span></label>
                    <input type="range" id="darkness_slider" min="0" max="100" value="0" class="w-full cursor-pointer"
                        oninput="renderSignature()">
                </div>
            </div>
            <div class="p-4 bg-gray-50 border-t flex justify-end gap-2">
                <button type="button" onclick="closeSigModal()"
                    class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded font-bold shadow-sm transition">ยกเลิก</button>
                <button type="button" onclick="confirmSignature()"
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded font-bold shadow transition"><i
                        class="fas fa-check"></i> ยืนยันการปรับแต่ง</button>
            </div>
        </div>
    </div>

    <script>
        const maxUploadBytes = <?= $max_bytes ?>;
        const maxUploadMB = <?= $max_mb ?>;

        // --- Address & Date ---
        $(document).ready(function () {
            $.Thailand({
                database: 'https://earthchie.github.io/jquery.Thailand.js/jquery.Thailand.js/database/db.json',
                $district: $('#addr_tambon'),
                $amphoe: $('#addr_amphoe'),
                $province: $('#addr_province'),
                $zipcode: $('#addr_zipcode')
            });

            $('#birth_date_th').inputmask({ mask: "99/99/9999", placeholder: "dd/mm/yyyy", clearIncomplete: false });

            $('#birth_date_th').on('change blur keyup', function () {
                let val = $(this).val();
                if (val && val.length === 10) {
                    let parts = val.split('/');
                    let d = parseInt(parts[0]); let m = parseInt(parts[1]); let yTH = parseInt(parts[2]);
                    if (d > 0 && d <= 31 && m > 0 && m <= 12 && yTH > 2400) {
                        let yEN = yTH - 543;
                        let dateEN = yEN + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                        $('#birth_date_db').val(dateEN);

                        let age = new Date().getFullYear() - new Date(dateEN).getFullYear();
                        let today = new Date(); let birthDate = new Date(dateEN);
                        let mDiff = today.getMonth() - birthDate.getMonth();
                        if (mDiff < 0 || (mDiff === 0 && today.getDate() < birthDate.getDate())) { age--; }
                        $('#age').val(age);
                    }
                }
            });

            let preDate = $('#birth_date_db').val();
            if (preDate && preDate !== '0000-00-00') {
                let d = new Date(preDate);
                let day = String(d.getDate()).padStart(2, '0');
                let month = String(d.getMonth() + 1).padStart(2, '0');
                let yearTH = d.getFullYear() + 543;
                $('#birth_date_th').val(day + '/' + month + '/' + yearTH);
            }

            toggleContactAddress();
            if (document.querySelector('input[name="request_reason"]:checked')) toggleReason();
            updateRequiredDocs();
        });

        // --- Awesomplete ---
        const posInput = document.getElementById('position_input');
        const orgSelect = document.querySelector('select[name="org_id"]');
        const posAutoMap = <?= $pos_auto_map_json ?>;

        if (posInput && orgSelect) {
            const awesomplete = new Awesomplete(posInput, { list: "#pos_list", minChars: 0, maxItems: 15, autoFirst: true });
            posInput.addEventListener("click", function () {
                if (awesomplete.ul.childNodes.length === 0) { awesomplete.minChars = 0; awesomplete.evaluate(); }
                else if (awesomplete.ul.hasAttribute('hidden')) awesomplete.open();
                else awesomplete.close();
            });
            function triggerAutoFill(val) { if (val && posAutoMap[val]) orgSelect.value = posAutoMap[val]; }
            posInput.addEventListener('input', function () { triggerAutoFill(this.value.trim()); });
            posInput.addEventListener('awesomplete-selectcomplete', function (e) { triggerAutoFill(e.text.value); });
        }

        // --- 🟢 Cropper (รูปถ่าย) โหลดจาก src ตรงๆ ---
        let cropper = null;
        async function loadCropperImage(url) {
            const img = document.getElementById('cropper_image');
            img.src = url; // ดึง Base64 จาก src มาใส่ตรงๆ
            document.getElementById('photoModal').classList.remove('hidden');

            if (cropper) cropper.destroy();
            img.onload = () => { cropper = new Cropper(img, { aspectRatio: 25 / 30, viewMode: 1, autoCropArea: 1 }); };
        }

        function editExistingPhoto() { loadCropperImage(document.getElementById('photo_preview').src); }

        function handleNewPhoto(input) {
            if (input.files && input.files[0]) {
                const objUrl = URL.createObjectURL(input.files[0]);
                loadCropperImage(objUrl);
            }
            input.value = '';
        }
        function closePhotoModal() { document.getElementById('photoModal').classList.add('hidden'); if (cropper) { cropper.destroy(); cropper = null; } }

        function confirmCrop() {
            if (!cropper) return;
            const canvas = cropper.getCroppedCanvas({ width: 500, height: 600, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' });
            const base64Image = canvas.toDataURL('image/jpeg', 0.9);

            document.getElementById('photo_preview').src = base64Image;
            document.getElementById('photo_preview').classList.remove('hidden');
            document.getElementById('photo_placeholder').classList.add('hidden');

            document.getElementById('cropped_photo_data').value = base64Image;
            closePhotoModal();
        }

        // --- ลายเซ็น (Threshold) ---
        var sigCanvasDraw = document.getElementById('signature-pad');
        var signaturePad = new SignaturePad(sigCanvasDraw);
        document.getElementById('clear-sig').addEventListener('click', () => signaturePad.clear());

        function toggleSigMethod() {
            const method = document.querySelector('input[name="sig_method"]:checked').value;
            if (method === 'draw') {
                document.getElementById('sig_panel_draw').classList.remove('hidden');
                document.getElementById('sig_panel_upload').classList.add('hidden');
            } else {
                document.getElementById('sig_panel_draw').classList.add('hidden');
                document.getElementById('sig_panel_upload').classList.remove('hidden');
            }
        }

        let sigImageObj = new Image();
        let sigCropper = null;
        async function loadSignatureImage(url) {
            sigImageObj.onload = () => {
                document.getElementById('sigModal').classList.remove('hidden');
                document.getElementById('threshold_slider').value = 180;
                document.getElementById('darkness_slider').value = 0;
                renderSignature();
            };
            sigImageObj.src = url; // โหลดภาพจาก Base64/ObjectUrl
        }

        function editExistingSignature() { loadSignatureImage(document.getElementById('sig_preview').src); }

        function handleNewSignature(input) {
            if (input.files && input.files[0]) {
                const objUrl = URL.createObjectURL(input.files[0]);
                loadSignatureImage(objUrl);
            }
            input.value = '';
        }
        function closeSigModal() { document.getElementById('sigModal').classList.add('hidden'); }

        function renderSignature() {
            const canvas = document.getElementById('sig_canvas');
            const ctx = canvas.getContext('2d');
            const threshold = parseInt(document.getElementById('threshold_slider').value);
            const contrast = parseInt(document.getElementById('darkness_slider').value);

            document.getElementById('threshold_val').innerText = threshold;
            document.getElementById('darkness_val').innerText = contrast + '%';

            canvas.width = sigImageObj.width;
            canvas.height = sigImageObj.height;
            ctx.drawImage(sigImageObj, 0, 0);

            const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imgData.data;

            for (let i = 0; i < data.length; i += 4) {
                // 🟢 เพิ่มโค้ด 4 บรรทัดนี้: ถ้ารูปเดิมโปร่งใสอยู่แล้ว ให้ข้ามพิกเซลนี้ไปเลย
                if (data[i + 3] < 50) {
                    data[i + 3] = 0;
                    continue;
                }

                const r = data[i], g = data[i + 1], b = data[i + 2];
                const luma = (r * 0.299) + (g * 0.587) + (b * 0.114);

                if (luma > threshold) {
                    data[i + 3] = 0;
                } else {
                    const factor = 1 - (contrast / 100);
                    data[i] = r * factor; data[i + 1] = g * factor; data[i + 2] = b * factor;
                    let alpha = 255; let diff = threshold - luma;
                    if (diff < 20) alpha = Math.floor((diff / 20) * 255);
                    data[i + 3] = alpha;
                }
            }
            ctx.putImageData(imgData, 0, 0);
        }

        function confirmSignature() {
            const canvas = document.getElementById('sig_canvas');
            const base64Image = canvas.toDataURL('image/png');

            document.getElementById('sig_preview').src = base64Image;
            document.getElementById('sig_preview').classList.remove('hidden');
            document.getElementById('sig_placeholder').classList.add('hidden');

            document.getElementById('signature_data').value = base64Image;
            closeSigModal();
        }

        // --- Docs Toggle & Async Upload ---
        function toggleContactAddress() {
            var box = document.getElementById('contact_addr_box');
            if (document.querySelector('input[name="contact_address_type"]:checked')?.value === 'OTHER') box.classList.remove('hidden'); else box.classList.add('hidden');
        }

        function toggleReason() {
            var reason = document.querySelector('input[name="request_reason"]:checked')?.value;
            document.getElementById('reason_new_options').classList.add('hidden');
            document.getElementById('reason_change_options').classList.add('hidden');
            if (reason === 'NEW') document.getElementById('reason_new_options').classList.remove('hidden');
            else if (reason === 'CHANGE') document.getElementById('reason_change_options').classList.remove('hidden');
        }

        function updateRequiredDocs() {
            const allBoxes = ['box_doc_position', 'box_doc_old_card', 'box_doc_blood', 'box_doc_lost', 'box_doc_name_change', 'box_doc_surname_change', 'box_doc_retired'];
            allBoxes.forEach(id => { let el = document.getElementById(id); if (el) { el.classList.add('hidden'); el.classList.remove('required-doc'); } });

            const reason = document.querySelector('input[name="request_reason"]:checked')?.value;
            const detail = document.querySelector('input[name="request_reason_detail"]:checked')?.value;
            let required = [];

            if (reason === 'FIRST') { required = ['box_doc_position', 'box_doc_blood']; }
            else if (reason === 'NEW') {
                if (detail === 'EXPIRED') required = ['box_doc_position', 'box_doc_old_card'];
                if (detail === 'LOST') required = ['box_doc_position', 'box_doc_lost', 'box_doc_blood'];
            } else if (reason === 'CHANGE') {
                if (detail === 'CHANGE_POS') required = ['box_doc_position', 'box_doc_old_card'];
                if (detail === 'CHANGE_NAME') required = ['box_doc_position', 'box_doc_old_card', 'box_doc_name_change'];
                if (detail === 'CHANGE_SURNAME') required = ['box_doc_position', 'box_doc_old_card', 'box_doc_surname_change'];
                if (detail === 'CHANGE_BOTH') required = ['box_doc_position', 'box_doc_old_card', 'box_doc_name_change', 'box_doc_surname_change'];
                if (detail === 'DAMAGED') required = ['box_doc_position', 'box_doc_old_card'];
                if (detail === 'RETIRED') required = ['box_doc_retired', 'box_doc_old_card'];
                if (detail === 'OTHER') required = ['box_doc_position', 'box_doc_old_card'];
            }

            required.forEach(id => { let el = document.getElementById(id); if (el) { el.classList.remove('hidden'); el.classList.add('required-doc'); } });
        }

        function checkCardWarning(el) { if (el.checked) Swal.fire({ title: 'ข้อควรระวัง', text: 'กรุณานำบัตรเดิมที่หมดอายุหรือชำรุด มาส่งคืนที่ ภ.จว.ปทุมธานี ด้วยครับ', icon: 'info', confirmButtonText: 'รับทราบ' }); }

        const maxBypassMB = 5.5;
        const maxBypassBytes = maxBypassMB * 1024 * 1024;
        let uploadsInProgress = 0;

        const docInputs = document.querySelectorAll('input[type="file"][name^="doc_"]');
        docInputs.forEach(input => {
            const originalName = input.name;
            input.removeAttribute('name');

            const hiddenPath = document.createElement('input');
            hiddenPath.type = 'hidden';
            hiddenPath.name = originalName + '_uploaded_path';
            hiddenPath.id = originalName + '_uploaded_path';
            input.parentNode.insertBefore(hiddenPath, input.nextSibling);

            const statusText = document.createElement('p');
            statusText.className = 'text-sm mt-2 upload-status';
            input.parentNode.insertBefore(statusText, hiddenPath);

            input.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) { hiddenPath.value = ''; statusText.innerHTML = ''; return; }
                if (file.size > maxBypassBytes) { Swal.fire('ไฟล์ใหญ่เกินไป!', `กรุณาย่อขนาดให้ไม่เกิน ${maxBypassMB}MB`, 'error'); this.value = ''; return; }

                input.disabled = true; uploadsInProgress++;
                statusText.innerHTML = '<span class="text-blue-600 font-bold"><i class="fas fa-spinner fa-spin"></i> ⏳ กำลังอัปโหลด...</span>';

                const reader = new FileReader();
                reader.onload = function (evt) {
                    const formData = new FormData();
                    formData.append('file_b64', evt.target.result); formData.append('file_name', file.name);
                    fetch('upload_async.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            input.disabled = false; uploadsInProgress--;
                            if (data.status === 'success') {
                                hiddenPath.value = data.tmp_path;
                                statusText.innerHTML = '<span class="text-green-600 font-bold"><i class="fas fa-check-circle"></i> ✅ อัปโหลดสำเร็จ!</span>';
                            } else {
                                statusText.innerHTML = '<span class="text-red-600 font-bold"><i class="fas fa-times-circle"></i> ❌ อัปโหลดล้มเหลว</span>'; input.value = '';
                            }
                        }).catch(err => {
                            input.disabled = false; uploadsInProgress--;
                            statusText.innerHTML = '<span class="text-red-600 font-bold">❌ เกิดข้อผิดพลาด/ไฟล์ใหญ่เกินไป</span>'; input.value = '';
                        });
                };
                reader.readAsDataURL(file);
            });
        });

        // --- 🟢 Submit Validation ---
        document.getElementById('requestForm').addEventListener('submit', function (e) {
            if (uploadsInProgress > 0) { e.preventDefault(); Swal.fire('รอสักครู่', 'มีไฟล์กำลังอัปโหลดอยู่ กรุณารอให้อัปโหลดเสร็จทุกไฟล์ก่อนกดยืนยันครับ', 'warning'); return; }

            let isEditMode = <?= $is_edit ? 'true' : 'false' ?>;
            let sigMethod = document.querySelector('input[name="sig_method"]:checked').value;
            let croppedPhoto = document.getElementById('cropped_photo_data').value;
            let processedSig = document.getElementById('signature_data').value;
            let photoPathDB = "<?= $photo_path?>";
            let sigPathDB = "<?= $signature_file?>";

            // ข้ามการตรวจสอบถ้ารูปเดิมมีอยู่แล้ว และอยู่ในโหมดแก้ไข
            if (!isEditMode && !croppedPhoto && !photoPathDB) { e.preventDefault(); Swal.fire('ลืมรูปถ่าย!', 'กรุณาอัปโหลดรูปถ่ายหน้าตรง', 'warning'); return; }

            // ข้ามตรวจสอบเอกสารเดิมถ้าอยู่ในโหมดแก้ไข
            const docIdHouseHidden = document.getElementById('doc_idcard_house_uploaded_path');
            if (!isEditMode && (!docIdHouseHidden || docIdHouseHidden.value === '')) {
                e.preventDefault(); Swal.fire('ข้อมูลไม่ครบ!', 'กรุณาแนบแบบรับรอง ทร.12/2 หรือ บัตรประชาชน (ในหัวข้อที่ 7.2)', 'warning'); return;
            }

            let missingDocs = false; let missingDocNames = [];
            document.querySelectorAll('.required-doc').forEach(box => {
                const hiddenInput = box.querySelector('input[type="hidden"][id$="_uploaded_path"]');
                if (!isEditMode && (!hiddenInput || hiddenInput.value === '')) {
                    missingDocs = true; missingDocNames.push(box.querySelector('label').innerText.replace('* บังคับ', '').replace('📌', '').replace('*', '').trim());
                }
            });

            if (missingDocs) {
                e.preventDefault();
                Swal.fire({ title: 'แนบเอกสารไม่ครบ!', html: '<div class="text-left text-sm">กรุณาอัปโหลดไฟล์ตามเงื่อนไขดังนี้:<br><br><span class="text-red-500 font-bold">- ' + missingDocNames.join('<br>- ') + '</span></div>', icon: 'warning' });
                return;
            }

            // 🟢 Add Request Reason Detail Validation
            let reason = document.querySelector('input[name="request_reason"]:checked')?.value;
            let detail = document.querySelector('input[name="request_reason_detail"]:checked')?.value;

            if (reason === 'NEW') {
                if (!detail || (detail !== 'EXPIRED' && detail !== 'LOST')) {
                    e.preventDefault();
                    Swal.fire('ข้อมูลไม่ครบ!', 'กรุณาเลือกเหตุผล "ขอมีบัตรใหม่ เนื่องจาก" อย่างน้อย 1 ตัวเลือก (บัตรหมดอายุ หรือ บัตรหาย/ถูกทำลาย)', 'warning');
                    return;
                }
            } else if (reason === 'CHANGE') {
                const validChangeDetails = ['CHANGE_POS', 'CHANGE_NAME', 'CHANGE_SURNAME', 'CHANGE_BOTH', 'DAMAGED', 'RETIRED', 'OTHER'];
                if (!detail || !validChangeDetails.includes(detail)) {
                    e.preventDefault();
                    Swal.fire('ข้อมูลไม่ครบ!', 'กรุณาเลือกเหตุผล "ขอเปลี่ยนบัตร เนื่องจาก" อย่างน้อย 1 ตัวเลือก', 'warning');
                    return;
                }

                // ถ้าระบุว่าอื่นๆ ต้องกรอกรายละเอียด
                if (detail === 'OTHER' && !document.querySelector('input[name="request_reason_other"]').value.trim()) {
                    e.preventDefault();
                    Swal.fire('ข้อมูลไม่ครบ!', 'กรุณาระบุเหตุผล "อื่นๆ"', 'warning');
                    return;
                }
            }

            // ข้ามตรวจสอบลายเซ็นถ้าของเดิมมีอยู่แล้ว และอยู่ในโหมดแก้ไข
            if (sigMethod === 'draw' && !signaturePad.isEmpty()) {
                document.getElementById('signature_data').value = signaturePad.toDataURL();
            } else if (sigMethod === 'draw' && signaturePad.isEmpty() && !isEditMode && !sigPathDB) {
                e.preventDefault(); Swal.fire('ลืมเซ็นชื่อ!', 'กรุณาลงลายมือชื่อสด หรือสลับโหมดเป็นอัปโหลดรูปภาพ', 'warning'); return;
            } else if (sigMethod === 'upload' && !processedSig && !isEditMode && !sigPathDB) {
                e.preventDefault(); Swal.fire('ลืมลายเซ็น!', 'กรุณาอัปโหลดไฟล์รูปลายเซ็น', 'warning'); return;
            }

            if (document.querySelector('input[name="contact_address_type"]:checked').value === 'OTHER') {
                if (!document.getElementById('contact_address_detail').value.trim()) { e.preventDefault(); Swal.fire('ข้อมูลไม่ครบ', 'กรุณาระบุที่อยู่ติดต่อ', 'warning'); }
            }
        });
    </script>
</body>

</html>