<?php
// idcard/index.php
require_once 'connect.php';

// ล้าง Session เก่าทิ้ง กรณีคนอื่นใช้งานค้างไว้
if (isset($_GET['clear'])) {
    session_unset();
    session_destroy();
}

// --- ดึงค่าประกาศหน้าแรก ---
$ann_stmt = $conn->query("SELECT setting_key, setting_value FROM idcard_settings WHERE setting_key LIKE 'announcement_%'");
$ann_data = [];
while ($row = $ann_stmt->fetch(PDO::FETCH_ASSOC)) {
    $ann_data[$row['setting_key']] = $row['setting_value'];
}

$ann_enabled = $ann_data['announcement_enabled'] ?? 'off';
$ann_title = $ann_data['announcement_title'] ?? 'ประกาศปัญหาการเชื่อมโยงระบบ';
$ann_msg = $ann_data['announcement_message'] ?? 'ขณะนี้ประสบปัญหาการเชื่อมโยงระบบ cURL';
$ann_sub = $ann_data['announcement_sub_message'] ?? 'อาจทำให้บางช่วงเวลาไม่สามารถใช้งานได้ชั่วคราว';
$ann_box_title = $ann_data['announcement_box_title'] ?? 'เจ้าหน้าที่กำลังเร่งดำเนินการแก้ไข';
$ann_box_text = $ann_data['announcement_box_text'] ?? 'ขออภัยในความไม่สะดวก';
$ann_type = $ann_data['announcement_type'] ?? 'warning';
$ann_icon = $ann_data['announcement_icon'] ?? 'fas fa-bullhorn';
$ann_fsize = $ann_data['announcement_font_size'] ?? 'text-base';

// กำหนดสีตามประเภทที่เลือก
$themes = [
    'info' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-400', 'text' => 'text-blue-900', 'btn' => 'bg-blue-500 hover:bg-blue-600', 'icon_bg' => 'bg-blue-100', 'icon_text' => 'text-blue-500', 'sub' => 'text-blue-700', 'box' => 'bg-blue-100'],
    'warning' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-400', 'text' => 'text-amber-900', 'btn' => 'bg-amber-500 hover:bg-amber-600', 'icon_bg' => 'bg-amber-100', 'icon_text' => 'text-amber-500', 'sub' => 'text-amber-700', 'box' => 'bg-amber-100'],
    'danger' => ['bg' => 'bg-red-50', 'border' => 'border-red-400', 'text' => 'text-red-900', 'btn' => 'bg-red-500 hover:bg-red-600', 'icon_bg' => 'bg-red-100', 'icon_text' => 'text-red-500', 'sub' => 'text-red-700', 'box' => 'bg-red-100'],
    'success' => ['bg' => 'bg-green-50', 'border' => 'border-green-400', 'text' => 'text-green-900', 'btn' => 'bg-green-500 hover:bg-green-600', 'icon_bg' => 'bg-green-100', 'icon_text' => 'text-green-500', 'sub' => 'text-green-700', 'box' => 'bg-green-100']
];
$theme = $themes[$ann_type] ?? $themes['warning'];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ระบบบัตรข้าราชการตำรวจ - ภ.จว.ปทุมธานี</title>
    <link rel="icon" type="image/png" href="https://portal.pathumthani.police.go.th/assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        .hover-card:hover {
            transform: translateY(-5px);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex flex-col">

    <?php include 'public_navbar.php'; ?>

    <!-- Modal ประกาศหน้าแรก -->
    <?php if ($ann_enabled === 'on'): ?>
    <div id="announcementModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 px-4">
        <div
            class="<?= $theme['bg']?> border-2 <?= $theme['border']?> rounded-xl shadow-2xl w-full max-w-md p-6 relative animate-[fadeIn_0.3s_ease-out]">
            <button onclick="closeAnnouncement()"
                class="absolute top-4 right-4 <?= $theme['text']?> opacity-60 hover:opacity-100 transition">
                <i class="fas fa-times text-xl"></i>
            </button>

            <div class="text-center">
                <div
                    class="w-16 h-16 <?= $theme['icon_bg']?> rounded-full flex items-center justify-center mx-auto mb-4 border <?= $theme['border']?> border-opacity-30">
                    <i class="<?= htmlspecialchars($ann_icon)?> <?= $theme['icon_text']?> text-3xl"></i>
                </div>

                <h3 class="text-xl font-bold <?= $theme['text']?> mb-3">
                    <?= htmlspecialchars($ann_title)?>
                </h3>

                <p class="<?= $theme['text']?> <?= htmlspecialchars($ann_fsize)?> font-medium mb-2">
                    <strong>
                        <?= nl2br(htmlspecialchars($ann_msg))?>
                    </strong>
                </p>

                <?php if ($ann_sub): ?>
                <p class="<?= $theme['sub']?> text-sm mb-4">
                    <?= htmlspecialchars($ann_sub)?>
                </p>
                <?php
    endif; ?>

                <?php if ($ann_box_title || $ann_box_text): ?>
                <div class="<?= $theme['box']?> rounded-lg p-3 mb-4">
                    <p class="<?= $theme['sub']?> text-sm font-bold">
                        <i class="fas fa-info-circle mr-1"></i>
                        <?= htmlspecialchars($ann_box_title)?>
                    </p>
                    <p class="<?= $theme['sub']?> text-sm mt-1">
                        <?= htmlspecialchars($ann_box_text)?>
                    </p>
                </div>
                <?php
    endif; ?>

                <button onclick="closeAnnouncement()"
                    class="<?= $theme['btn']?> text-white font-bold py-2 px-6 rounded-lg transition shadow-md">
                    <i class="fas fa-check mr-2"></i>เข้าใจแล้ว
                </button>
            </div>
        </div>
    </div>
    <?php
endif; ?>

    <script>
        function closeAnnouncement() {
            document.getElementById('announcementModal').style.display = 'none';
        }
    </script>

    <main class="flex-grow flex flex-col items-center justify-center p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl w-full">

            <div onclick="openPdpaModal()"
                class="bg-white rounded-xl shadow-lg p-8 text-center cursor-pointer transition duration-300 hover-card border-t-4 border-blue-500">
                <div
                    class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6 text-blue-600">
                    <i class="fas fa-id-card text-4xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">ขอมีบัตร/เปลี่ยนบัตร</h2>
                <p class="text-gray-500">สำหรับข้าราชการตำรวจที่ต้องการ<br>ทำบัตรใหม่ หรือเปลี่ยนบัตรเดิม</p>
                <button class="mt-6 bg-blue-600 text-white px-6 py-2 rounded-full font-bold hover:bg-blue-700 w-full">
                    คลิกเพื่อดำเนินการ
                </button>
            </div>

            <div onclick="openModal('TRACK')"
                class="bg-white rounded-xl shadow-lg p-8 text-center cursor-pointer transition duration-300 hover-card border-t-4 border-yellow-500">
                <div
                    class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6 text-yellow-600">
                    <i class="fas fa-search text-4xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">ติดตามสถานะ</h2>
                <p class="text-gray-500">ตรวจสอบความคืบหน้าของคำขอ<br>หรือสถานะการจัดพิมพ์บัตร</p>
                <button
                    class="mt-6 bg-yellow-500 text-white px-6 py-2 rounded-full font-bold hover:bg-yellow-600 w-full">
                    ตรวจสอบสถานะ
                </button>
            </div>

            <a href="login.php"
                class="bg-white rounded-xl shadow-lg p-8 text-center cursor-pointer transition duration-300 hover-card border-t-4 border-red-500 block">
                <div
                    class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6 text-red-600">
                    <i class="fa-solid fa-user-lock text-4xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Admin</h2>
                <p class="text-gray-500">เข้าสู่ระบบสำหรับเจ้าหน้าที่ผู้ออกบัตร<br>และอนุมัติการออกบัตร</p>
                <span class="mt-6 bg-red-600 text-white px-6 py-2 rounded-full font-bold hover:bg-red-700 w-full block">
                    เข้าสู่ระบบ SSO
                </span>
            </a>

        </div>
        <div class="max-w-6xl w-full mt-10 bg-white rounded-xl shadow-lg p-6 md:p-8 border-t-4 border-indigo-500">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center"><i
                    class="fas fa-file-alt text-indigo-500 mr-2"></i> เอกสารที่ต้องเตรียมก่อนยื่นคำขอ</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-blue-50 rounded-lg p-6 border border-blue-100 shadow-sm">
                    <h3 class="text-lg font-bold text-blue-800 mb-4 border-b border-blue-200 pb-2">
                        <i class="fas fa-id-badge mr-2"></i> กรณีทำบัตรใหม่ (ครั้งแรก)
                    </h3>
                    <ul class="space-y-3 text-gray-700 font-semibold">
                        <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                            <span>1. สำเนาคำสั่งแต่งตั้ง</span>
                        </li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                            <span>2. สำเนาทะเบียนบ้าน</span>
                        </li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                            <span>3. หนังสือยืนยันกรุ๊ปเลือดที่ออกโดยสถานพยาบาล <br>
                                <span class="text-sm text-gray-500 font-normal">(กรณีมีบัตรที่ออกโดยหน่วยงานของรัฐอื่นๆ
                                    ที่ระบุกรุ๊ปเลือดไม่ต้องตรวจ)</span>
                            </span>
                        </li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                            <span>4. รูปถ่าย</span>
                        </li>
                    </ul>
                </div>

                <div class="bg-yellow-50 rounded-lg p-6 border border-yellow-100 shadow-sm">
                    <h3 class="text-lg font-bold text-yellow-800 mb-4 border-b border-yellow-200 pb-2">
                        <i class="fas fa-sync-alt mr-2"></i> กรณีเปลี่ยนบัตร / บัตรหาย / บัตรหมดอายุ
                    </h3>
                    <ul class="space-y-3 text-gray-700 font-semibold">
                        <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                            <span>1. สำเนาคำสั่งแต่งตั้ง <span
                                    class="text-sm text-gray-500 font-normal">(กรณีเลื่อนยศ)</span></span>
                        </li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                            <span>2. สำเนาทะเบียนบ้าน</span>
                        </li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                            <span>3. รูปถ่าย</span>
                        </li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                            <span>4. บัตรเก่า</span>
                        </li>
                        <li class="flex items-start"><i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                            <span>5. ใบแจ้งความหาย <span
                                    class="text-sm text-gray-500 font-normal">(กรณีเอกสารหาย)</span></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-gray-400 py-4 text-center text-sm">
        &copy;
        <?= date('Y')?> Police Cloud Service - Pathum Thani Provincial Police | พัฒนาระบบโดย ส.ต.ท.รัฐภูมิ |
        ติดต่อสอบถาม 02 581 7673 | ratthaphum.kh@police.go.th
    </footer>

    <div id="pdpaModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 px-4">
        <div
            class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 md:p-8 relative max-h-[95vh] flex flex-col animate-[fadeIn_0.3s_ease-out]">
            <h3 class="text-xl md:text-2xl font-bold text-blue-900 mb-4 text-center border-b border-gray-200 pb-4">
                <i class="fas fa-shield-alt text-blue-600 mr-2"></i>
                การแจ้งวัตถุประสงค์และรายละเอียดการประมวลผลข้อมูลส่วนบุคคล<br><span
                    class="text-lg text-gray-500">(Privacy Notice)</span>
            </h3>

            <div class="overflow-y-auto custom-scrollbar text-[15px] text-gray-700 space-y-4 mb-6 pr-4 flex-1">
                <p><strong>ตำรวจภูธรจังหวัดปทุมธานี (ภ.จว.ปทุมธานี)</strong> ในฐานะผู้ควบคุมข้อมูลส่วนบุคคล
                    ขอแจ้งรายละเอียดการประมวลผลข้อมูลส่วนบุคคลตามมาตรา ๒๓ แห่งพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ.
                    ๒๕๖๒
                    เพื่อให้การดำเนินการออกบัตรประจำตัวข้าราชการตำรวจและเจ้าหน้าที่ของรัฐผ่านระบบอิเล็กทรอนิกส์เป็นไปอย่างโปร่งใสและชอบด้วยกฎหมาย
                    ดังนี้:</p>

                <ul class="list-disc list-inside space-y-3 ml-2">
                    <li><strong class="text-gray-900">วัตถุประสงค์ในการประมวลผลข้อมูล:</strong> เพื่อใช้ในการยืนยันตัวตน
                        ตรวจสอบคุณสมบัติ จัดทำฐานข้อมูลทะเบียนประวัติ
                        และดำเนินการออกบัตรประจำตัวเจ้าหน้าที่ของรัฐตามระเบียบสำนักงานตำรวจแห่งชาติ</li>
                    <li><strong class="text-gray-900">ฐานทางกฎหมายในการประมวลผล:</strong> ภ.จว.ปทุมธานี
                        ดำเนินการภายใต้ฐานภารกิจของรัฐ (Public Task) และฐานการปฏิบัติตามกฎหมาย (Legal Obligation)
                        เพื่อให้การออกบัตรประจำตัวข้าราชการตำรวจเป็นไปตามมาตรา ๖๓ แห่งพระราชบัญญัติตำรวจแห่งชาติ พ.ศ.
                        ๒๕๖๕ และระเบียบ ตร. ลักษณะที่ ๒๔ บัตร พ.ศ. ๒๕๖๖</li>
                    <li><strong class="text-gray-900">ความจำเป็นในการให้ข้อมูล:</strong>
                        ข้อมูลที่จัดเก็บเป็นความจำเป็นตามข้อกฎหมายและระเบียบ ตร. หากท่านไม่ให้ข้อมูลที่ครบถ้วน
                        หน่วยงานจะไม่สามารถดำเนินการพิจารณาออกบัตรประจำตัวให้แก่ท่านได้</li>
                    <li><strong class="text-gray-900">ประเภทข้อมูลส่วนบุคคลที่จัดเก็บ:</strong>
                        <ul class="list-[circle] list-inside ml-8 mt-2 space-y-1 text-gray-700">
                            <li><strong class="text-blue-800">ข้อมูลทั่วไป:</strong> ชื่อ-นามสกุล, เลขประจำตัวประชาชน,
                                ยศ, ตำแหน่ง, ที่อยู่ปัจจุบัน และที่อยู่ตามทะเบียนบ้าน</li>
                            <li><strong class="text-red-700">ข้อมูลส่วนบุคคลที่มีความอ่อนไหว (Sensitive Data):</strong>
                                หมู่โลหิต และรูปถ่ายใบหน้า (ข้อมูลชีวภาพ)
                                ซึ่งหน่วยงานจะขอความยินยอมโดยชัดแจ้งจากท่านก่อนการประมวลผล</li>
                        </ul>
                    </li>
                    <li><strong class="text-gray-900">ระยะเวลาการเก็บรักษาข้อมูล:</strong>
                        หน่วยงานจะเก็บรักษาข้อมูลของท่านไว้ตลอดระยะเวลาที่ท่านดำรงตำแหน่งหรือสังกัดหน่วยงาน
                        และจะจัดเก็บต่อเนื่องเป็นเวลา ๕ ปี นับแต่วันที่บัตรหมดอายุตามระเบียบ ตร.
                        เพื่อใช้เป็นหลักฐานทะเบียนประวัติก่อนดำเนินการทำลายตามมาตรฐานที่กำหนด</li>
                    <li><strong class="text-gray-900">การรักษาความมั่นคงปลอดภัย:</strong>
                        มีการใช้มาตรการบริหารจัดการสิทธิเข้าถึงข้อมูล (Access Control)
                        และการเข้ารหัสข้อมูลในระบบฐานข้อมูลตามมาตรฐานความมั่นคงปลอดภัยไซเบอร์ของ บก.สสท.
                        และเกณฑ์มาตรฐานศูนย์ราชการสะดวก (GECC)</li>
                    <li><strong class="text-gray-900">สิทธิของเจ้าของข้อมูล:</strong> ท่านมีสิทธิในการเข้าถึง แก้ไข ลบ
                        หรือคัดค้านการประมวลผล รวมถึงสิทธิในการถอนความยินยอมได้ภายใต้เงื่อนไขที่กฎหมายกำหนด</li>
                    <li><strong class="text-gray-900">ช่องทางการติดต่อ:</strong> ฝ่ายอำนวยการ ๑ ภ.จว.ปทุมธานี โทรศัพท์
                        ๐๒ ๕๘๑ ๗๖๗๓ ต่อ ๓ หรือผ่านทางผู้ควบคุมข้อมูลส่วนบุคคล (DPO) ของสำนักงานตำรวจแห่งชาติ</li>
                </ul>

                <div
                    class="mt-6 mb-2 text-center bg-gray-50 py-3 rounded-lg border border-gray-300 shadow-sm hover:bg-gray-100 transition">
                    <a href="privacy_policy.php" target="_blank"
                        class="text-blue-700 hover:text-blue-900 font-bold text-[16px] flex items-center justify-center gap-2">
                        <i class="fas fa-external-link-alt"></i> คลิกอ่านนโยบายคุ้มครองข้อมูลส่วนบุคคล (Privacy Policy)
                        ฉบับเต็ม
                    </a>
                </div>

                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 mt-4 shadow-inner">
                    <p class="font-bold text-blue-900 mb-2 text-lg"><i
                            class="fas fa-check-square text-blue-600 mr-2"></i>การให้ความยินยอม</p>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" id="pdpaCheckbox"
                            class="mt-1 w-6 h-6 text-blue-600 rounded cursor-pointer" onchange="togglePdpaButton()">
                        <span
                            class="font-bold text-gray-800 leading-relaxed text-[15px]">ข้าพเจ้าได้อ่านและเข้าใจรายละเอียดการคุ้มครองข้อมูลส่วนบุคคลข้างต้นแล้ว
                            และขอให้ความยินยอมแก่ ภ.จว.ปทุมธานี ในการเก็บรวบรวมและใช้ข้อมูลส่วนบุคคลของข้าพเจ้า
                            รวมถึงข้อมูลหมู่โลหิตและรูปถ่าย
                            เพื่อวัตถุประสงค์ในการจัดทำบัตรประจำตัวเจ้าหน้าที่ของรัฐตามระเบียบที่เกี่ยวข้อง</span>
                    </label>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <button onclick="declinePdpa()"
                    class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3.5 rounded-lg transition shadow-sm">
                    <i class="fas fa-times mr-1"></i> ไม่ยินยอม (กลับหน้าหลัก)
                </button>
                <button id="btnAcceptPdpa" onclick="acceptPdpa()" disabled
                    class="w-full bg-blue-300 cursor-not-allowed text-white font-bold py-3.5 rounded-lg transition shadow-md">
                    <i class="fas fa-check mr-1"></i> ยอมรับและดำเนินการต่อ
                </button>
            </div>
        </div>
    </div>

    <div id="idCardModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 relative animate-[fadeIn_0.3s_ease-out]">
            <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>

            <h3 class="text-xl font-bold text-gray-800 mb-4 text-center" id="modalTitle">กรอกเลขบัตรประชาชน</h3>

            <form id="requestForm" onsubmit="submitForm(event)">
                <input type="hidden" name="action_type" id="actionType">

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">เลขประจำตัวประชาชน (13 หลัก)</label>
                    <input type="text" name="id_card_number" id="id_card_input" required maxlength="13"
                        inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        placeholder="เช่น 1100700xxxxxx"
                        class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 text-center text-lg tracking-widest">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">เบอร์โทรศัพท์สำหรับลงทะเบียน (10
                        หลัก)</label>
                    <input type="text" name="phone_number" id="phone_input" required maxlength="10" inputmode="numeric"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="เช่น 0812345678"
                        class="w-full px-4 py-3 rounded border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 text-center text-lg tracking-widest">
                </div>
                <div class="flex justify-center mt-4">
                    <div id="turnstile-container"></div>
                </div>
                <div class="grid grid-cols-2 gap-4 pt-2">
                    <button type="submit" id="submitBtn"
                        class="w-full bg-blue-900 text-white font-bold py-3 rounded hover:bg-blue-800 transition col-span-2">
                        ตกลง / ยืนยัน
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPdpaModal() {
            document.getElementById('pdpaModal').classList.remove('hidden');
            document.getElementById('pdpaCheckbox').checked = false;
            togglePdpaButton();
        }

        function closePdpaModal() {
            document.getElementById('pdpaModal').classList.add('hidden');
        }

        function togglePdpaButton() {
            const btn = document.getElementById('btnAcceptPdpa');
            if (document.getElementById('pdpaCheckbox').checked) {
                btn.disabled = false;
                btn.classList.remove('bg-blue-300', 'cursor-not-allowed');
                btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            } else {
                btn.disabled = true;
                btn.classList.add('bg-blue-300', 'cursor-not-allowed');
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            }
        }

        function acceptPdpa() {
            closePdpaModal();
            openModal('REQUEST'); // ไปหน้ากรอกเลข ปชช ต่อ
        }

        function declinePdpa() {
            window.location.href = 'https://portal.pathumthani.police.go.th';
        }

        var turnstileWidgetId = null;

        function openModal(type) {
            const modal = document.getElementById('idCardModal');
            const title = document.getElementById('modalTitle');
            const input = document.getElementById('actionType');

            modal.classList.remove('hidden');
            input.value = type;

            if (type === 'REQUEST') {
                title.innerText = 'ระบุเลขบัตรฯ เพื่อเริ่มทำบัตร';
                title.className = 'text-xl font-bold text-blue-800 mb-4 text-center';
            } else {
                title.innerText = 'ระบุเลขบัตรฯ เพื่อติดตามสถานะ';
                title.className = 'text-xl font-bold text-yellow-700 mb-4 text-center';
            }

            // Render Turnstile widget แบบ explicit ตอนเปิด modal
            if (typeof turnstile !== 'undefined') {
                // ลบ widget เก่าก่อน (ถ้ามี)
                if (turnstileWidgetId !== null) {
                    turnstile.remove(turnstileWidgetId);
                }
                turnstileWidgetId = turnstile.render('#turnstile-container', {
                    sitekey: '0x4AAAAAACpD9GYXR9vpVNCE',
                    theme: 'light'
                });
            }
        }

        function closeModal() {
            document.getElementById('idCardModal').classList.add('hidden');
            // ลบ Turnstile widget ตอนปิด modal
            if (typeof turnstile !== 'undefined' && turnstileWidgetId !== null) {
                turnstile.remove(turnstileWidgetId);
                turnstileWidgetId = null;
            }
        }

        window.onclick = function (event) {
            const idModal = document.getElementById('idCardModal');
            if (event.target == idModal) {
                closeModal();
            }
        }

        function checkThaiID(id) {
            if (id.length != 13) return false;
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                sum += parseFloat(id.charAt(i)) * (13 - i);
            }
            let check = (11 - sum % 11) % 10;
            if (check == parseFloat(id.charAt(12))) {
                return true;
            }
            return false;
        }

        function validateForm() {
            const idInput = document.getElementById('id_card_input').value;

            if (!checkThaiID(idInput)) {
                Swal.fire({
                    title: 'เลขบัตรประชาชนไม่ถูกต้อง',
                    text: 'กรุณาตรวจสอบเลข 13 หลักอีกครั้ง (Check Digit ไม่ผ่าน)',
                    icon: 'error',
                    confirmButtonText: 'แก้ไข',
                    confirmButtonColor: '#d33'
                });
                return false;
            }

            var turnstileResponse = document.querySelector('input[name="cf-turnstile-response"]');
            if (!turnstileResponse || turnstileResponse.value.length === 0) {
                Swal.fire('แจ้งเตือน', 'กรุณารอให้ระบบยืนยันตัวตน (Turnstile) เสร็จสิ้นก่อนกดยืนยัน', 'warning');
                return false;
            }

            return true;
        }

        function getStatusLabel(status) {
            const labels = {
                'PENDING_CHECK': 'รอตรวจสอบ',
                'PENDING_APPROVAL': 'รออนุมัติ',
                'SENT_TO_PRINT': 'รอจัดพิมพ์',
                'READY_PICKUP': 'พิมพ์เสร็จ/รอรับบัตร',
                'COMPLETED': 'รับบัตรแล้ว/จบงาน',
                'REJECTED': 'ปฏิเสธคำขอ/แก้ไขข้อมูล'
            };
            return labels[status] || 'ไม่ทราบสถานะ';
        }

        async function submitForm(e) {
            e.preventDefault();
            if (!validateForm()) return;

            const form = document.getElementById('requestForm');
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerText;

            submitBtn.disabled = true;
            submitBtn.innerText = 'กำลังตรวจสอบ...';

            try {
                const formData = new FormData(form);
                const response = await fetch('process_public_check.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });

                const data = await response.json();

                if (data.status === 'error') {
                    if (data.type === 'phone_mismatch') {
                        Swal.fire({
                            icon: 'error',
                            title: 'ข้อมูลไม่ตรงกัน',
                            html: data.message + '<br><span class="text-xs text-red-500">(เพื่อความเป็นส่วนตัว ต้องระบุเบอร์ให้ตรงกับที่เคยลงทะเบียนไว้)</span>'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: data.message
                        });
                    }
                    turnstile.reset();
                } else if (data.status === 'success') {
                    if (data.action === 'redirect') {
                        window.location.href = data.url;
                    } else if (data.action === 'prompt') {
                        if (data.req_state === 'pending') {
                            Swal.fire({
                                icon: 'info',
                                title: 'พบคำขอในระบบ',
                                html: `สถานะปัจจุบัน: <b>${getStatusLabel(data.db_status)}</b><br><br>คุณมีคำขอที่กำลังดำเนินการอยู่ ต้องการแก้ไขข้อมูลหรือไม่?`,
                                showCancelButton: true,
                                confirmButtonText: 'ใช่, ต้องการแก้ไข',
                                cancelButtonText: 'ไม่, กลับหน้าหลัก',
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'request.php';
                                }
                            });
                        } else if (data.req_state === 'rejected') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'คำขอถูกปฏิเสธ',
                                html: `เหตุผลที่ถูกปฏิเสธ:<br><b class="text-red-500">${data.reject_reason || 'ไม่ระบุเหตุผล'}</b><br><br>ต้องการแก้ไขข้อมูลและส่งใหม่หรือไม่?`,
                                showCancelButton: true,
                                confirmButtonText: 'ใช่, ต้องการแก้ไข',
                                cancelButtonText: 'ไม่, กลับหน้าหลัก',
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'request.php';
                                }
                            });
                        } else if (data.req_state === 'completed') {
                            Swal.fire({
                                icon: 'success',
                                title: 'บัตรเสร็จเรียบร้อยแล้ว',
                                html: `สถานะปัจจุบัน: <b>${getStatusLabel(data.db_status)}</b><br><br>คุณต้องการขอออกบัตรใหม่ (กรณีชำรุด/สูญหาย) ใช่หรือไม่?`,
                                showCancelButton: true,
                                confirmButtonText: 'ใช่, ขอทำบัตรใหม่',
                                cancelButtonText: 'ไม่, กลับหน้าหลัก',
                                confirmButtonColor: '#28a745',
                                cancelButtonColor: '#d33'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'request.php';
                                }
                            });
                        }
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                turnstile.reset();
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
            }
        }

        window.onload = function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('err') === 'phone_mismatch') {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อมูลไม่ตรงกัน',
                    html: 'เบอร์โทรศัพท์ไม่ตรงกับข้อมูลในระบบ<br><span class="text-xs text-red-500">(เพื่อความเป็นส่วนตัว ต้องระบุเบอร์ให้ตรงกับที่เคยลงทะเบียนไว้)</span>'
                });
                window.history.replaceState({}, document.title, window.location.pathname);
            } else if (urlParams.get('err') === 'not_found') {
                Swal.fire('ไม่พบข้อมูล', 'ยังไม่มีประวัติการยื่นคำขอของเลขบัตรประชาชนนี้', 'info');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        };
    </script>
</body>

</html>