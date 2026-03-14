<?php
// idcard/admin_create.php
require_once 'connect.php';
require_once 'admin_auth.php'; // 🔒 เฉพาะ Admin

// Master Data
$all_ranks = $conn->query("SELECT * FROM idcard_ranks")->fetchAll(PDO::FETCH_ASSOC);
$ranks_by_id = array_column($all_ranks, null, 'id');
$rank_sort_order = [1, 2, 3, 15, 4, 16, 5, 17, 6, 18, 7, 19, 8, 20, 9, 21, 10, 22, 11, 23, 12, 24, 13, 25, 14, 26];
$orgs = $conn->query("SELECT * FROM idcard_organizations ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// 🟢 ดึงข้อมูลตำแหน่งพร้อมสังกัดที่ผูกไว้ส่งให้ Javascript
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

// ค่าเริ่มต้นวันที่ออกบัตรเป็นวันนี้
$issue_th = date('d/m/') . (date('Y') + 543);
$issue_db = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>สร้างคำขอใหม่ - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        .checkerboard-bg {
            background-image:
                linear-gradient(45deg, #e5e7eb 25%, transparent 25%),
                linear-gradient(-45deg, #e5e7eb 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #e5e7eb 75%),
                linear-gradient(-45deg, transparent 75%, #e5e7eb 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }

        /* 🟢 2. ปรับแต่ง Awesomplete ให้สวยเข้ากับ Tailwind และกดง่ายบนมือถือ */
        .awesomplete {
            width: 100%;
        }

        .awesomplete>ul {
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            margin-top: 4px;
            max-height: 250px;
            overflow-y: auto;
        }

        .awesomplete>ul>li {
            padding: 12px 14px;
            font-family: 'Sarabun', sans-serif;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            font-size: 16px;
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

<body class="bg-gray-100 p-6">
    <?php include 'admin_navbar.php'; ?>
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-xl overflow-hidden">
        <div class="bg-green-600 p-4 flex justify-between items-center text-white">
            <h1 class="text-xl font-bold"><i class="fas fa-plus-circle"></i> สร้างข้อมูลทำบัตรใหม่ (โดยเจ้าหน้าที่)</h1>
            <a href="admin_dashboard.php"
                class="bg-white text-green-700 px-4 py-1 rounded hover:bg-gray-100 font-bold">Cancel / กลับ</a>
        </div>

        <form action="admin_save_create" method="POST" enctype="multipart/form-data" class="p-6 space-y-6"
            id="createForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']?>">
            <div class="bg-blue-50 p-4 rounded border border-blue-200">
                <h3 class="font-bold text-blue-800 mb-2">⚙️ ตั้งค่าสถานะเริ่มต้น</h3>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-sm font-bold">สถานะ</label>
                        <select name="status" class="w-full border p-2 rounded bg-white">
                            <option value="PENDING_CHECK" selected>รอตรวจสอบ (ยังไม่ออกเลข)</option>
                            <option value="SENT_TO_PRINT">ส่งพิมพ์บัตร (ออกเลขทันที)</option>
                            <option value="COMPLETED">รับบัตรแล้ว (จบงาน) - ใช้กรณีคีย์ประวัติย้อนหลัง</option>
                        </select>
                        <p class="text-xs text-blue-600 mt-1">* หากเลือก "รอพิมพ์บัตร" หรือ "รับบัตรแล้ว"
                            ระบบจะรันเลขทะเบียนให้อัตโนมัติเมื่อกดบันทึก</p>
                    </div>
                </div>
            </div>

            <h3 class="font-bold border-b pb-2 text-gray-700">ข้อมูลส่วนตัว</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-blue-900"><i class="fas fa-id-card mr-1"></i> เลขบัตร
                        ปชช.</label>
                    <div class="flex gap-2">
                        <input type="text" name="id_card_number" id="id_card_input" maxlength="13" inputmode="numeric"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            class="w-full border-2 border-blue-100 p-2 rounded-lg bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-bold text-lg"
                            placeholder="กรอกเลขบัตรประชาชน 13 หลัก" required>
                        <button type="button" id="fetch_cor_btn" onclick="fetchCorOfficerData()"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold whitespace-nowrap flex items-center gap-2 transition-all">
                            <i class="fas fa-link"></i> เชื่อมฐานข้อมูลกำลังพล
                        </button>
                    </div>
                    <p id="id_card_error" class="text-xs text-red-500 mt-1 hidden font-bold">❌ เลขบัตรประชาชนไม่ถูกต้อง
                    </p>
                    <p id="cor_fetch_status" class="text-xs mt-1 hidden"></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-bold">ยศ</label>
                    <input type="text" name="rank_name_input" id="rank_input" class="w-full border p-2 rounded bg-white" placeholder="- พิมพ์เพื่อค้นหายศ -" autocomplete="off" required>
                    <input type="hidden" name="rank_id" id="rank_id_hidden">
                    <datalist id="rank_list">
                        <?php foreach ($rank_sort_order as $rid):
                            if (isset($ranks_by_id[$rid])):
                                $r = $ranks_by_id[$rid]; ?>
                                <option data-id="<?= $r['id'] ?>" value="<?= htmlspecialchars($r['rank_name']) ?>">
                                    <?= htmlspecialchars($r['rank_name']) ?>
                                </option>
                            <?php
                            endif;
                        endforeach; ?>
                    </datalist>
                </div>
                <div><label class="block text-sm font-bold">ชื่อ (ไม่มีคำนำหน้า)</label><input type="text"
                        name="first_name" class="w-full border p-2 rounded bg-white" required></div>
                <div><label class="block text-sm font-bold">นามสกุล</label><input type="text" name="last_name"
                        class="w-full border p-2 rounded bg-white" required></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-bold">วันเกิด (พ.ศ.)</label>
                    <input type="text" id="create_birth_date_th"
                        class="w-full border p-2 rounded bg-white text-center tracking-widest" placeholder="วว/ดด/ปปปป"
                        required>
                    <input type="hidden" name="birth_date" id="create_birth_date_db">
                </div>
                <div><label class="block text-sm font-bold">หมู่โลหิต <span class="text-red-500">*</span></label>
                    <select name="blood_type" class="w-full border p-2 rounded bg-white" required>
                        <option value="">- เลือกหมู่โลหิต -</option>
                        <option value="O">O</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="AB">AB</option>
                    </select>
                </div>
                <div class="md:col-span-2"><label class="block text-sm font-bold">โทรศัพท์</label><input type="text"
                        name="phone" class="w-full border p-2 rounded bg-white"></div>
            </div>

            <h3 class="font-bold border-b pb-2 text-gray-700 mt-6">ข้อมูลการทำงาน</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-bold">ประเภทบัตร</label>
                    <select name="card_type_id" class="w-full border p-2 rounded bg-white" required>
                        <option value="">- เลือกประเภทบัตร -</option>
                        <?php foreach ($card_types as $ct): ?>
                        <option value="<?= $ct['id']?>">
                            <?= $ct['type_name']?>
                        </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold">ประเภท จนท.</label>
                    <select name="officer_type" class="w-full border p-2 rounded bg-white" required>
                        <option value="POLICE">ข้าราชการตำรวจ</option>
                        <option value="PERMANENT_EMP">ลูกจ้างประจำ</option>
                        <option value="GOV_EMP">พนักงานราชการ</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold">ตำแหน่ง</label>
                    <input type="text" name="position" id="position_input" class="w-full border p-2 rounded bg-white"
                        placeholder="- พิมพ์เพื่อค้นหา หรือพิมพ์เพิ่มเอง -" autocomplete="off" required>
                    <datalist id="pos_list">
                        <?php foreach ($pos_dict as $p): ?>
                        <option>
                            <?= htmlspecialchars($p)?>
                        </option>
                        <?php
endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-bold">สังกัด</label>
                    <select name="org_id" class="w-full border p-2 rounded bg-white" required>
                        <option value="">- เลือกหน่วยงาน -</option>
                        <?php foreach ($orgs as $o): ?>
                        <option value="<?= $o['id']?>">
                            <?= $o['org_name']?>
                        </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
            </div>

            <h3 class="font-bold border-b pb-2 text-gray-700 mt-6">ไฟล์รูปถ่าย และ ลายเซ็น</h3>

            <div class="border p-6 rounded bg-gray-50 mb-6">
                <label class="block font-bold text-lg mb-2">1. รูปถ่ายหน้าตรง <span
                        class="text-red-500">*</span></label>
                <input type="file" id="upload_image" accept="image/*"
                    class="w-full text-sm mb-4 border p-2 rounded bg-white">

                <div id="cropper_wrapper" class="hidden mb-4">
                    <div class="w-full max-w-md bg-black p-2 rounded mx-auto">
                        <img id="image_to_crop" style="max-width: 100%; display: block;">
                    </div>
                    <div class="flex justify-center gap-2 mt-2">
                        <button type="button" id="rotate_left" class="bg-gray-600 text-white px-3 py-1 rounded">↺
                            หมุนซ้าย</button>
                        <button type="button" id="rotate_right" class="bg-gray-600 text-white px-3 py-1 rounded">↻
                            หมุนขวา</button>
                        <button type="button" id="confirm_crop"
                            class="bg-green-600 text-white px-4 py-1 rounded font-bold">✅ ยืนยันรูปนี้</button>
                    </div>
                </div>

                <div id="cropped_result" class="hidden text-center">
                    <p class="text-green-600 font-bold mb-2">ใช้รูปนี้ในการทำบัตร:</p>
                    <img id="final_image_preview" class="h-48 mx-auto rounded border-2 border-green-500 shadow-md">
                    <button type="button" onclick="resetCrop()"
                        class="mt-2 text-red-500 text-sm underline">เลือกรูปใหม่</button>
                </div>
                <input type="hidden" name="cropped_photo_data" id="cropped_photo_data">
            </div>

            <div class="border p-6 rounded bg-gray-50 mb-6">
                <label class="block font-bold text-lg mb-2">2. ไฟล์ลายเซ็น (อัปโหลดรูปภาพ) <span
                        class="text-red-500">*</span></label>
                <input type="file" id="sig_upload_input" accept="image/png, image/jpeg, image/jpg"
                    class="w-full text-sm mb-4 border p-2 rounded bg-white shadow-sm">

                <div id="sig_cropper_wrapper" class="hidden mb-4">
                    <div class="w-full max-w-lg bg-gray-200 p-2 rounded mx-auto overflow-hidden">
                        <img id="sig_image_to_crop" style="max-width: 100%; display: block;">
                    </div>
                    <p class="text-center text-xs text-gray-500 mt-2">💡 สามารถเลื่อน/ขยาย/หดขอบเขตได้ตามต้องการ
                        (อัตราส่วน 2:1)</p>
                    <div class="flex justify-center gap-2 mt-2">
                        <button type="button" id="sig_rotate_left"
                            class="bg-gray-600 text-white px-3 py-1 rounded text-sm"><i class="fas fa-undo"></i>
                            หมุนซ้าย</button>
                        <button type="button" id="sig_rotate_right"
                            class="bg-gray-600 text-white px-3 py-1 rounded text-sm"><i class="fas fa-redo"></i>
                            หมุนขวา</button>
                        <button type="button" id="sig_confirm_crop"
                            class="bg-blue-600 text-white px-4 py-1 rounded font-bold text-sm"><i
                                class="fas fa-crop"></i> ยืนยันการตัดขอบ</button>
                    </div>
                </div>

                <div id="sig_edit_controls"
                    class="hidden w-full max-w-md bg-blue-50 p-4 rounded-lg border border-blue-200 mb-4 shadow-sm">
                    <p class="text-sm font-bold text-blue-800 mb-3"><i class="fas fa-sliders-h"></i> ปรับแต่งลายเซ็น
                        (เพื่อให้พื้นหลังโปร่งใส)</p>
                    <div class="mb-4">
                        <label class="flex justify-between text-xs font-semibold text-gray-700 mb-1">
                            <span>ตัดพื้นหลัง (Threshold)</span><span id="thresh_val"
                                class="text-blue-600 font-bold">200</span>
                        </label>
                        <input type="range" id="sig_threshold" min="0" max="255" value="200"
                            class="w-full h-2 bg-gray-300 rounded-lg appearance-none cursor-pointer">
                    </div>
                    <div>
                        <label class="flex justify-between text-xs font-semibold text-gray-700 mb-1">
                            <span>เพิ่มความเข้มลายเส้น</span><span id="dark_val"
                                class="text-blue-600 font-bold">0%</span>
                        </label>
                        <input type="range" id="sig_darkness" min="0" max="100" value="0"
                            class="w-full h-2 bg-gray-300 rounded-lg appearance-none cursor-pointer">
                    </div>
                </div>

                <div id="sig_canvas_container"
                    class="hidden border-2 border-dashed border-gray-400 rounded-lg overflow-hidden relative shadow-inner checkerboard-bg"
                    style="width: 400px; height: 200px;">
                    <canvas id="sig_upload_canvas" width="400" height="200"
                        style="position: relative; z-index: 1;"></canvas>
                </div>
                <p id="sig_helper_text" class="hidden text-xs text-gray-500 mt-2">ภาพตัวอย่างลายเซ็น
                    (ลายตารางหมากรุกคือส่วนที่โปร่งใส)</p>

                <input type="hidden" name="signature_data" id="signature_data">
            </div>

            <div class="bg-indigo-50 p-4 rounded border border-indigo-200 mt-6 mb-4">
                <h3 class="font-bold text-indigo-800 mb-3"><i class="fas fa-magic"></i> วันที่ออกบัตร และ วันหมดอายุ
                    (คำนวณอัตโนมัติ)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold mb-1 text-gray-700">วันออกบัตร (พ.ศ.)</label>
                        <input type="text" id="issue_date_th" value="<?= $issue_th?>"
                            class="w-full border p-2 rounded bg-white text-center font-bold tracking-widest text-lg"
                            placeholder="วว/ดด/ปปปป">
                        <input type="hidden" name="issue_date" id="issue_date_db" value="<?= $issue_db?>">
                    </div>
                    <div>
                        <label class="flex justify-between items-end mb-1">
                            <span class="block text-sm font-bold text-gray-700">วันหมดอายุบัตร (พ.ศ.)</span>
                            <label
                                class="inline-flex items-center text-xs text-blue-700 font-bold cursor-pointer bg-blue-100 px-2 py-1 rounded">
                                <input type="checkbox" id="is_lifetime" class="mr-1 w-4 h-4"> บัตรตลอดชีพ
                            </label>
                        </label>
                        <input type="text" id="expire_date_th"
                            class="w-full border p-2 rounded text-center font-bold tracking-widest text-lg bg-green-50 text-green-700"
                            placeholder="คำนวณอัตโนมัติเมื่อใส่วันเกิด...">
                        <input type="hidden" name="expire_date" id="expire_date_db">
                    </div>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded shadow-lg text-lg">
                💾 บันทึกและสร้างข้อมูล
            </button>

        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>

    <script>
        // =========================================================
        // 🟢 ระบบ Auto-fill ตำแหน่ง และ ยศ (ด้วย Awesomplete)
        // =========================================================
        $(document).ready(function () {
            const posInput = document.getElementById('position_input');
            const orgSelect = document.querySelector('select[name="org_id"]');
            const posAutoMap = <?= $pos_auto_map_json?>;

            // --- Awesomplete สำหรับตำแหน่ง ---
            const posAwesomplete = new Awesomplete(posInput, {
                list: "#pos_list",
                minChars: 0,
                maxItems: 15,
                autoFirst: true
            });

            posInput.addEventListener("click", function () {
                if (posAwesomplete.ul.childNodes.length === 0) {
                    posAwesomplete.minChars = 0;
                    posAwesomplete.evaluate();
                } else if (posAwesomplete.ul.hasAttribute('hidden')) {
                    posAwesomplete.open();
                } else {
                    posAwesomplete.close();
                }
            });

            window.triggerAutoFill = function(val) {
                if (val && posAutoMap[val]) {
                    orgSelect.value = posAutoMap[val];
                }
            };

            posInput.addEventListener('input', function () {
                window.triggerAutoFill(this.value.trim());
            });

            posInput.addEventListener('awesomplete-selectcomplete', function (e) {
                window.triggerAutoFill(e.text.value);
            });

            // --- Awesomplete สำหรับยศ ---
            const rankInput = document.getElementById('rank_input');
            const rankIdHidden = document.getElementById('rank_id_hidden');
            const rankListOptions = Array.from(document.querySelectorAll('#rank_list option')).map(opt => ({
                label: opt.value,
                value: opt.getAttribute('data-id')
            }));

            const rankAwesomplete = new Awesomplete(rankInput, {
                list: "#rank_list",
                minChars: 0,
                maxItems: 20,
                autoFirst: true
            });

            rankInput.addEventListener("click", function () {
                if (rankAwesomplete.ul.childNodes.length === 0) {
                    rankAwesomplete.minChars = 0;
                    rankAwesomplete.evaluate();
                } else if (rankAwesomplete.ul.hasAttribute('hidden')) {
                    rankAwesomplete.open();
                } else {
                    rankAwesomplete.close();
                }
            });

            // ฟังก์ชันค้นหา ID ของยศจากชื่อยศ
            window.rankListOptions = rankListOptions;
            window.syncRankId = function(name) {
                const match = window.rankListOptions.find(r => r.label === name.trim());
                if (match) {
                    rankIdHidden.value = match.value;
                } else {
                    rankIdHidden.value = ''; // หรือจะกำหนดเป็น 0/null สำหรับยศใหม่
                }
            };

            rankInput.addEventListener('input', function () {
                syncRankId(this.value);
            });

            rankInput.addEventListener('awesomplete-selectcomplete', function (e) {
                rankIdHidden.value = e.text.value; // Awesomplete-selectcomplete ส่งค่า value มาให้ (คือ rank_id)
                rankInput.value = e.text.label; // แล้วแก้หน้าจอให้เป็น label
            });

            // ================= Date Logic =================
            $('#create_birth_date_th, #issue_date_th, #expire_date_th').inputmask({ mask: "99/99/9999", placeholder: "dd/mm/yyyy", clearIncomplete: false });

            function convertToDBDate(thDateStr) {
                if (thDateStr && thDateStr.length === 10) {
                    let parts = thDateStr.split('/');
                    let d = parseInt(parts[0]); let m = parseInt(parts[1]); let yTH = parseInt(parts[2]);
                    if (d > 0 && d <= 31 && m > 0 && m <= 12 && yTH > 2400) {
                        return (yTH - 543) + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                    }
                } return '';
            }

            window.autoCalcExpiry = function() {
                if ($('#is_lifetime').is(':checked')) return;

                let dobStr = $('#create_birth_date_db').val();
                let issueStr = $('#issue_date_db').val();
                if (!dobStr || !issueStr) return;

                let dob = new Date(dobStr);
                let issue = new Date(issueStr);

                let ageAtIssue = issue.getFullYear() - dob.getFullYear();
                if (issue.getMonth() < dob.getMonth() || (issue.getMonth() === dob.getMonth() && issue.getDate() < dob.getDate())) {
                    ageAtIssue--;
                }

                if (ageAtIssue >= 64) {
                    $('#is_lifetime').prop('checked', true).trigger('change');
                    return;
                }

                let retireYear = dob.getFullYear() + 60;
                if (dob.getMonth() > 9 || (dob.getMonth() === 9 && dob.getDate() >= 2)) {
                    retireYear++;
                }
                let retireDate = new Date(retireYear, 8, 30);

                let normalExpire = new Date(issue);
                normalExpire.setFullYear(normalExpire.getFullYear() + 6);
                normalExpire.setDate(normalExpire.getDate() - 1);

                let finalExpire = normalExpire;
                if (issue <= retireDate && normalExpire > retireDate) {
                    finalExpire = retireDate;
                }

                let exY = finalExpire.getFullYear();
                let exM = String(finalExpire.getMonth() + 1).padStart(2, '0');
                let exD = String(finalExpire.getDate()).padStart(2, '0');

                $('#expire_date_db').val(`${exY}-${exM}-${exD}`);
                $('#expire_date_th').val(`${exD}/${exM}/${exY + 543}`).addClass('border-green-400 border-2');
                setTimeout(() => $('#expire_date_th').removeClass('border-green-400 border-2'), 1000);
            };

            $('#create_birth_date_th').on('change blur keyup', function () {
                $('#create_birth_date_db').val(convertToDBDate($(this).val()));
                setTimeout(window.autoCalcExpiry, 100);
            });
            $('#issue_date_th').on('change blur keyup', function () {
                $('#issue_date_db').val(convertToDBDate($(this).val()));
                setTimeout(window.autoCalcExpiry, 100);
            });
            $('#expire_date_th').on('change blur keyup', function () {
                if (!$('#is_lifetime').is(':checked')) $('#expire_date_db').val(convertToDBDate($(this).val()));
            });
            $('#is_lifetime').change(function () {
                if ($(this).is(':checked')) {
                    $('#expire_date_th').val('ตลอดชีพ').prop('readonly', true).addClass('bg-gray-200 text-gray-400');
                    $('#expire_date_db').val('9999-12-31');
                } else {
                    $('#expire_date_th').val('').prop('readonly', false).removeClass('bg-gray-200 text-gray-400');
                    window.autoCalcExpiry();
                }
            });
        });

        // ================= Cropper Logic =================
        let cropper;
        const imageInput = document.getElementById('upload_image');
        const imageToCrop = document.getElementById('image_to_crop');
        const cropperWrapper = document.getElementById('cropper_wrapper');
        const croppedResult = document.getElementById('cropped_result');
        const finalImagePreview = document.getElementById('final_image_preview');
        const croppedPhotoData = document.getElementById('cropped_photo_data');

        imageInput.addEventListener('change', function (e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    imageToCrop.src = event.target.result;
                    cropperWrapper.classList.remove('hidden');
                    croppedResult.classList.add('hidden');
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(imageToCrop, { aspectRatio: 3 / 4, viewMode: 1, autoCropArea: 1 });
                };
                reader.readAsDataURL(files[0]);
            }
        });

        document.getElementById('rotate_left').addEventListener('click', () => cropper.rotate(-90));
        document.getElementById('rotate_right').addEventListener('click', () => cropper.rotate(90));

        document.getElementById('confirm_crop').addEventListener('click', () => {
            if (cropper) {
                const canvas = cropper.getCroppedCanvas({ width: 600, height: 800 });
                const base64Image = canvas.toDataURL('image/jpeg', 0.9);
                finalImagePreview.src = base64Image;
                croppedPhotoData.value = base64Image;
                cropperWrapper.classList.add('hidden');
                croppedResult.classList.remove('hidden');
            }
        });

        function resetCrop() {
            imageInput.value = '';
            croppedResult.classList.add('hidden');
            croppedPhotoData.value = '';
        }

        // ================= Signature Processing Logic =================
        let originalSigImg = new Image();
        let originalSigData = null;
        let sigCropper = null;
        const sigImageToCrop = document.getElementById('sig_image_to_crop');
        const sigCropperWrapper = document.getElementById('sig_cropper_wrapper');

        document.getElementById('sig_upload_input').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) {
                originalSigData = null;
                document.getElementById('sig_edit_controls').classList.add('hidden');
                document.getElementById('sig_canvas_container').classList.add('hidden');
                document.getElementById('sig_helper_text').classList.add('hidden');
                sigCropperWrapper.classList.add('hidden');
                const ctx = document.getElementById('sig_upload_canvas').getContext('2d');
                ctx.clearRect(0, 0, 400, 200);
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                sigImageToCrop.src = event.target.result;
                sigCropperWrapper.classList.remove('hidden');

                document.getElementById('sig_edit_controls').classList.add('hidden');
                document.getElementById('sig_canvas_container').classList.add('hidden');
                document.getElementById('sig_helper_text').classList.add('hidden');

                if (sigCropper) sigCropper.destroy();
                sigCropper = new Cropper(sigImageToCrop, {
                    aspectRatio: 2 / 1,
                    viewMode: 0,
                    dragMode: 'move',
                    autoCropArea: 1,
                    background: false
                });
            };
            reader.readAsDataURL(file);
        });

        document.getElementById('sig_rotate_left').addEventListener('click', () => sigCropper.rotate(-90));
        document.getElementById('sig_rotate_right').addEventListener('click', () => sigCropper.rotate(90));

        document.getElementById('sig_confirm_crop').addEventListener('click', () => {
            if (sigCropper) {
                const canvas = sigCropper.getCroppedCanvas({
                    width: 400,
                    height: 200,
                    fillColor: '#fff'
                });
                const base64Image = canvas.toDataURL('image/jpeg', 1.0);

                originalSigImg.onload = function () {
                    const tempCanvas = document.createElement('canvas');
                    tempCanvas.width = 400;
                    tempCanvas.height = 200;
                    const tempCtx = tempCanvas.getContext('2d');

                    tempCtx.fillStyle = 'white';
                    tempCtx.fillRect(0, 0, 400, 200);
                    tempCtx.drawImage(originalSigImg, 0, 0, 400, 200);

                    originalSigData = tempCtx.getImageData(0, 0, 400, 200);

                    sigCropperWrapper.classList.add('hidden');
                    document.getElementById('sig_edit_controls').classList.remove('hidden');
                    document.getElementById('sig_canvas_container').classList.remove('hidden');
                    document.getElementById('sig_helper_text').classList.remove('hidden');
                    processSignature();
                };
                originalSigImg.src = base64Image;
            }
        });

        document.getElementById('sig_threshold').addEventListener('input', processSignature);
        document.getElementById('sig_darkness').addEventListener('input', processSignature);

        function processSignature() {
            if (!originalSigData) return;

            const threshold = parseInt(document.getElementById('sig_threshold').value);
            const contrast = parseInt(document.getElementById('sig_darkness').value);

            document.getElementById('thresh_val').innerText = threshold;
            document.getElementById('dark_val').innerText = contrast + '%';

            const canvas = document.getElementById('sig_upload_canvas');
            const ctx = canvas.getContext('2d');

            const imgData = new ImageData(new Uint8ClampedArray(originalSigData.data), originalSigData.width, originalSigData.height);
            const data = imgData.data;

            for (let i = 0; i < data.length; i += 4) {
                const r = data[i]; const g = data[i + 1]; const b = data[i + 2];
                const luma = (r * 0.299) + (g * 0.587) + (b * 0.114);

                if (luma > threshold) {
                    data[i + 3] = 0;
                } else {
                    const factor = 1 - (contrast / 100);
                    data[i] = r * factor; data[i + 1] = g * factor; data[i + 2] = b * factor;
                    let alpha = 255; let diff = threshold - luma;
                    if (diff < 20) { alpha = Math.floor((diff / 20) * 255); }
                    data[i + 3] = alpha;
                }
            }
            ctx.putImageData(imgData, 0, 0);
        }

        /// ================= Form Submission Logic =================
        function checkThaiID(id) {
            if (id.length !== 13) return false;
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                sum += parseInt(id.charAt(i)) * (13 - i);
            }
            let check = (11 - (sum % 11)) % 10;
            return check === parseInt(id.charAt(12));
        }

        // ================= Fetch COR Officer Data =================
        function fetchCorOfficerData() {
            const idCardInput = document.getElementById('id_card_input');
            const idCard = idCardInput.value.trim();
            const statusEl = document.getElementById('cor_fetch_status');
            const btn = document.getElementById('fetch_cor_btn');

            // Validate ID card
            if (!idCard || idCard.length !== 13) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณากรอกเลขบัตรประชาชน',
                    text: 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก',
                    confirmButtonText: 'ตกลง'
                });
                idCardInput.focus();
                return;
            }

            if (!checkThaiID(idCard)) {
                Swal.fire({
                    icon: 'error',
                    title: 'เลขบัตรประชาชนไม่ถูกต้อง',
                    text: 'เลขบัตรประชาชนไม่ถูกต้องตามหลักเกณฑ์',
                    confirmButtonText: 'ตกลง'
                });
                return;
            }

            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังค้นหา...';
            statusEl.classList.remove('hidden');
            statusEl.className = 'text-xs mt-1 text-blue-600';
            statusEl.textContent = '🔍 กำลังค้นหาข้อมูลจากฐานข้อมูลกำลังพล...';

            // Fetch data from API
            fetch(`api_fetch_cor_officer.php?id_card=${idCard}`)
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-link"></i> เชื่อมฐานข้อมูลกำลังพล';

                    if (data.status === 'success' && data.data) {
                        const officer = data.data;

                        // Fill in the form fields
                        if (officer.rank_name) {
                            document.getElementById('rank_input').value = officer.rank_name;
                            if (officer.rank_id) {
                                document.getElementById('rank_id_hidden').value = officer.rank_id;
                            } else {
                                // ถ้าไม่มี id ลองหาแมทช์จากตารางที่มีอยู่
                                if (typeof syncRankId === 'function') syncRankId(officer.rank_name);
                            }
                        }

                        if (officer.first_name) {
                            document.querySelector('input[name="first_name"]').value = officer.first_name;
                        }

                        if (officer.last_name) {
                            document.querySelector('input[name="last_name"]').value = officer.last_name;
                        }

                        if (officer.birth_date_th) {
                            document.getElementById('create_birth_date_th').value = officer.birth_date_th;
                            document.getElementById('create_birth_date_db').value = officer.birth_date;
                            // Trigger auto-calculation of expiry date
                            setTimeout(() => window.autoCalcExpiry(), 100);
                        }

                        if (officer.blood_type) {
                            document.querySelector('select[name="blood_type"]').value = officer.blood_type;
                        }

                        if (officer.phone) {
                            document.querySelector('input[name="phone"]').value = officer.phone;
                        }

                        if (officer.officer_type) {
                            document.querySelector('select[name="officer_type"]').value = officer.officer_type;
                        }

                        if (officer.position_name) {
                            document.getElementById('position_input').value = officer.position_name;
                            // Trigger auto-fill for org if position has mapping
                            if (typeof window.triggerAutoFill === 'function') {
                                window.triggerAutoFill(officer.position_name);
                            }
                        }

                        if (officer.org_id) {
                            document.querySelector('select[name="org_id"]').value = officer.org_id;
                        }

                        // Show success message
                        statusEl.className = 'text-xs mt-1 text-green-600 font-bold';
                        statusEl.textContent = `✅ ดึงข้อมูลสำเร็จ: ${officer.rank_name || ''} ${officer.first_name} ${officer.last_name}`;

                        // Auto-hide status after 5 seconds
                        setTimeout(() => {
                            statusEl.classList.add('hidden');
                        }, 5000);

                    } else {
                        statusEl.className = 'text-xs mt-1 text-red-500';
                        statusEl.textContent = `❌ ${data.message || 'ไม่พบข้อมูลกำลังพลในระบบ'}`;
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-link"></i> เชื่อมฐานข้อมูลกำลังพล';
                    statusEl.className = 'text-xs mt-1 text-red-500';
                    statusEl.textContent = '❌ เกิดข้อผิดพลาดในการเชื่อมต่อกับระบบ';
                    console.error('Fetch error:', error);
                });
        }

        document.getElementById('id_card_input').addEventListener('blur', function () {
            if (this.value.length > 0 && !checkThaiID(this.value)) {
                document.getElementById('id_card_error').classList.remove('hidden');
                this.classList.add('border-red-500', 'bg-red-50');
            } else {
                document.getElementById('id_card_error').classList.add('hidden');
                this.classList.remove('border-red-500', 'bg-red-50');
            }
        });

        document.getElementById('createForm').addEventListener('submit', function (e) {
            let idCardVal = document.getElementById('id_card_input').value;
            if (!checkThaiID(idCardVal)) {
                e.preventDefault();
                Swal.fire('ข้อมูลไม่ถูกต้อง!', 'เลขประจำตัวประชาชน 13 หลัก ไม่ถูกต้องตามหลักเกณฑ์', 'error');
                document.getElementById('id_card_input').focus();
                return;
            }

            if (!croppedPhotoData.value) {
                e.preventDefault(); Swal.fire('ข้อมูลไม่ครบ!', 'กรุณาอัปโหลดและยืนยันรูปถ่ายหน้าตรง', 'warning'); return;
            }

            if (!originalSigData) {
                e.preventDefault(); Swal.fire('ข้อมูลไม่ครบ!', 'กรุณาอัปโหลดไฟล์ลายเซ็น', 'warning'); return;
            }

            const uploadCanvas = document.getElementById('sig_upload_canvas');
            document.getElementById('signature_data').value = uploadCanvas.toDataURL('image/png');
        });
    </script>
</body>

</html>