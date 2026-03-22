<?php
// idcard/admin_print_card.php
require_once 'connect.php';
require_once 'helpers.php';
require_once 'admin_auth.php';
// 👮 จำกัดสิทธิ์เฉพาะ Admin และ Super_Admin ที่พิมพ์บัตรได้
if (!in_array($_SESSION['role'], ['Super_Admin', 'Admin'])) {
    die("⛔ Access Denied: คุณไม่มีสิทธิ์เข้าถึงส่วนการพิมพ์บัตร");
}

// 🟢 ฟังก์ชันแปลงภาพเป็น Base64 (ต้องอยู่ตรงนี้ ห้ามลบ เพื่อให้ระบบทะลุ Firewall)
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

// =========================================================
// 🟢 API สำหรับรับ-ส่งข้อมูล Pre-set ลงฐานข้อมูล (AJAX)
// =========================================================
$conn->exec("CREATE TABLE IF NOT EXISTS `idcard_print_presets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `preset_name` varchar(100) NOT NULL,
  `config_data` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($referer) || parse_url($referer, PHP_URL_HOST) !== $host) {
        echo json_encode(['status' => 'error', 'message' => 'CSRF Token Mismatch / Invalid Referer']);
        exit();
    }

    $action = $_POST['ajax_action'];

    try {
        if ($action === 'load_presets') {
            $stmt = $conn->query("SELECT id, preset_name FROM idcard_print_presets ORDER BY id ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        elseif ($action === 'get_preset') {
            $stmt = $conn->prepare("SELECT config_data FROM idcard_print_presets WHERE id = ?");
            $stmt->execute([$_POST['preset_id']]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchColumn()]);
        }
        elseif ($action === 'save_new_preset') {
            $stmt = $conn->prepare("INSERT INTO idcard_print_presets (preset_name, config_data) VALUES (?, ?)");
            $stmt->execute([$_POST['preset_name'], $_POST['config_data']]);
            echo json_encode(['status' => 'success', 'id' => $conn->lastInsertId()]);
        }
        elseif ($action === 'update_preset') {
            $stmt = $conn->prepare("UPDATE idcard_print_presets SET config_data = ? WHERE id = ?");
            $stmt->execute([$_POST['config_data'], $_POST['preset_id']]);
            echo json_encode(['status' => 'success']);
        }
        elseif ($action === 'delete_preset') {
            $stmt = $conn->prepare("DELETE FROM idcard_print_presets WHERE id = ?");
            $stmt->execute([$_POST['preset_id']]);
            echo json_encode(['status' => 'success']);
        }
    }
    catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
// =========================================================

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Error: Invalid request ID");
}

$sql = "SELECT r.*, k.rank_name, o.org_name, t.type_name,
               iss.rank_name as issuer_rank, iss.full_name as issuer_name, iss.position as issuer_pos, iss.signature_path as issuer_sig,
               ins.signature_path as inspector_sig
        FROM idcard_requests r
        LEFT JOIN idcard_ranks k ON r.rank_id = k.id
        LEFT JOIN idcard_organizations o ON r.org_id = o.id
        LEFT JOIN idcard_card_types t ON r.card_type_id = t.id
        LEFT JOIN idcard_signers iss ON r.issuer_id = iss.id
        LEFT JOIN idcard_signers ins ON r.inspector_id = ins.id
        WHERE r.id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req)
    die("ไม่พบข้อมูล");

if (!empty($req['card_year']) && !empty($req['card_sequence'])) {
    $year_2d = substr($req['card_year'], -2);
    $card_num_str = sprintf("0016.5 - %s - %04d", $year_2d, $req['card_sequence']);
}
else {
    $card_num_str = !empty($req['generated_card_no']) ? $req['generated_card_no'] : "ยังไม่ออกเลข";
    if (preg_match('/- (\d{4}) -/', $card_num_str, $matches)) {
        $year_2d = substr($matches[1], -2);
        $card_num_str = str_replace("- {$matches[1]} -", "- $year_2d -", $card_num_str);
    }
}
$card_serial_thai = toThaiNum($card_num_str);

function formatThaiDateShort($dateString)
{
    if (!$dateString || $dateString == '0000-00-00')
        return "-";
    if ($dateString == '9999-12-31')
        return "ตลอดชีพ";
    $timestamp = strtotime($dateString);
    $d = date('j', $timestamp);
    $m = date('n', $timestamp);
    $y = date('Y', $timestamp) + 543;
    $monthsShort = [1 => "ม.ค.", 2 => "ก.พ.", 3 => "มี.ค.", 4 => "เม.ย.", 5 => "พ.ค.", 6 => "มิ.ย.", 7 => "ก.ค.", 8 => "ส.ค.", 9 => "ก.ย.", 10 => "ต.ค.", 11 => "พ.ย.", 12 => "ธ.ค."];
    return toThaiNum($d . ' ' . $monthsShort[$m] . ' ' . $y);
}

$issue_date_thai = formatThaiDateShort(!empty($req['issue_date']) ? $req['issue_date'] : date('Y-m-d'));
$expiry_date_thai = formatThaiDateShort(!empty($req['expire_date']) ? $req['expire_date'] : '9999-12-31');

$blood_map = ['O' => 'โอ', 'A' => 'เอ', 'B' => 'บี', 'AB' => 'เอบี'];
$blood_th = isset($blood_map[$req['blood_type']]) ? $blood_map[$req['blood_type']] : $req['blood_type'];

$id_formatted = $req['id_card_number'];
if (strlen($id_formatted) == 13) {
    $id_formatted = substr($id_formatted, 0, 1) . ' - ' . substr($id_formatted, 1, 4) . ' - ' . substr($id_formatted, 5, 5) . ' - ' . substr($id_formatted, 10, 2) . ' - ' . substr($id_formatted, 12, 1);
}
$id_card_thai = toThaiNum($id_formatted);
$full_name_rank = $req['rank_name'] . $req['full_name'];

// 💡 ระบบอัจฉริยะ: ถ้าข้อมูลคนเซ็นเก่าหายไป ให้ดึงคนที่ Active อยู่ปัจจุบันมาแทนอัตโนมัติ (ทั้งรูปลายเซ็น ยศ และตำแหน่ง)
$issuer_sig_path = $req['issuer_sig'];
if (empty($req['issuer_pos']) || empty($issuer_sig_path) || !file_exists($issuer_sig_path)) {
    $stmt_is = $conn->query("SELECT * FROM idcard_signers WHERE signer_type = 'ISSUER' AND is_active = 1 LIMIT 1");
    $active_issuer = $stmt_is ? $stmt_is->fetch(PDO::FETCH_ASSOC) : null;
    if ($active_issuer) {
        $issuer_sig_path = $active_issuer['signature_path'];
        $req['issuer_rank'] = $active_issuer['rank_name'];
        $req['issuer_pos'] = $active_issuer['position'];
    }
}

$inspector_sig_path = $req['inspector_sig'];
if (empty($inspector_sig_path) || !file_exists($inspector_sig_path)) {
    $stmt_in = $conn->query("SELECT signature_path FROM idcard_signers WHERE signer_type = 'INSPECTOR' AND is_active = 1 LIMIT 1");
    $inspector_sig_path = $stmt_in ? $stmt_in->fetchColumn() : '';
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Print Zebra -
        <?= htmlspecialchars($req['full_name'])?>
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --base-font-size: 10pt;
            --gov-font-size: 10.5pt;
        }

        @page {
            size: 85.6mm 53.98mm;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Sarabun', sans-serif;
            background-color: #e5e7eb;
            overflow-x: hidden;
        }

        .card-box {
            position: relative;
            width: 85.6mm;
            height: 53.98mm;
            background-color: #fff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            box-sizing: border-box;
            color: #000;
            transform-origin: top center;
            transition: transform 0.2s ease-out;
        }

        .el {
            position: absolute;
            font-size: var(--base-font-size);
            font-weight: 600;
            white-space: nowrap;
            line-height: 1;
        }

        #el_gov {
            font-size: var(--gov-font-size);
        }

        @media print {
            .no-print {
                display: none !important;
            }

            html,
            body {
                background-color: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 85.6mm !important;
                height: 53.98mm !important;
                overflow: hidden !important;
            }

            .card-box {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                transform: scale(1) !important;
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
            }

            #preview-wrapper {
                display: block !important;
                position: static !important;
                width: 85.6mm !important;
                height: 53.98mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
            }
        }

        .set-row {
            display: grid;
            grid-template-columns: 2.5fr 1fr 1fr 1fr 1fr 1fr;
            gap: 4px;
            margin-bottom: 5px;
            align-items: center;
        }

        .set-row input {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 2px 4px;
            font-size: 11px;
            width: 100%;
            text-align: center;
        }

        .set-row input:disabled {
            background-color: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }

        .set-head {
            font-size: 11px;
            font-weight: bold;
            color: #555;
            text-align: center;
        }
    </style>
</head>

<body class="flex flex-col h-screen overflow-y-auto">

    <div id="settingsSidebar"
        class="fixed top-0 left-0 h-screen w-[620px] bg-white shadow-[5px_0_20px_rgba(0,0,0,0.2)] z-50 transform -translate-x-full transition-transform duration-300 no-print flex">

        <div class="flex-1 h-full overflow-y-auto p-6 bg-white relative">
            <div class="flex justify-between items-center mb-4 border-b pb-4">
                <h2 class="text-xl font-bold text-blue-800"><i class="fas fa-sliders-h"></i> จัดการพิกัด Layout</h2>
                <div class="flex gap-2">
                    <button id="lockBtn" onclick="toggleLock()"
                        class="px-3 py-1 rounded text-sm font-bold shadow transition"></button>
                    <button onclick="resetDefaults()"
                        class="bg-gray-400 hover:bg-gray-500 text-white px-3 py-1 rounded text-sm font-bold shadow"
                        title="โหลดพิกัดเริ่มต้นใหม่"><i class="fas fa-undo"></i> รีเซ็ต</button>
                </div>
            </div>

            <div class="mb-4 bg-purple-50 p-3 rounded border border-purple-200">
                <label class="font-bold text-xs text-purple-900 mb-1 block"><i class="fas fa-save"></i> ฐานข้อมูล
                    Pre-set (เลือกเพื่อโหลดค่า):</label>
                <div class="flex gap-2 items-center">
                    <select id="preset_select" class="border p-1 rounded w-full text-sm font-bold bg-white"
                        onchange="loadPresetFromDB()">
                        <option value="default">-- ค่าเริ่มต้น (Default Config) --</option>
                    </select>
                    <button onclick="updateCurrentPreset()"
                        class="bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold hover:bg-blue-700 whitespace-nowrap"><i
                            class="fas fa-save"></i> ทับ</button>
                    <button onclick="saveAsNewPreset()"
                        class="bg-green-600 text-white px-2 py-1 rounded text-xs font-bold hover:bg-green-700 whitespace-nowrap"><i
                            class="fas fa-plus"></i> ใหม่</button>
                    <button onclick="deleteCurrentPreset()"
                        class="bg-red-500 text-white px-2 py-1 rounded text-xs font-bold hover:bg-red-600 whitespace-nowrap"><i
                            class="fas fa-trash"></i></button>
                </div>
            </div>

            <div class="mb-4 bg-blue-50 p-3 rounded border border-blue-200 flex justify-between items-center gap-2">
                <div class="w-1/2">
                    <label class="font-bold text-xs text-blue-900 block mb-1">ฟอนต์เนื้อหา (Base):</label>
                    <div class="flex items-center gap-2">
                        <input type="number" id="inp_baseFontSize" step="0.5"
                            class="border p-1 w-full text-center rounded font-bold" oninput="updateBaseSettings()">
                        <span class="text-sm font-bold text-gray-600">pt</span>
                    </div>
                </div>
                <div class="w-1/2">
                    <label class="font-bold text-xs text-blue-900 block mb-1">ฟอนต์หัวบัตร (Gov):</label>
                    <div class="flex items-center gap-2">
                        <input type="number" id="inp_govFontSize" step="0.5"
                            class="border p-1 w-full text-center rounded font-bold" oninput="updateBaseSettings()">
                        <span class="text-sm font-bold text-gray-600">pt</span>
                    </div>
                </div>
            </div>

            <div class="set-row border-b pb-1 mb-2 sticky top-0 bg-white pt-2">
                <div><span class="text-[10px] text-gray-500 font-bold">ข้อมูล ( ↔️ = หดได้ )</span></div>
                <div class="set-head">X</div>
                <div class="set-head">Y</div>
                <div class="set-head">W</div>
                <div class="set-head text-green-700">H</div>
                <div class="set-head text-blue-600">Pt</div>
            </div>

            <div id="settings-container" class="pb-10"></div>
        </div>

        <div class="absolute -right-11 top-1/3 transform -translate-y-1/2">
            <button onclick="toggleSidebar()" id="sidebarTabBtn"
                class="bg-blue-800 text-white p-2 rounded-r-lg shadow-[4px_0_8px_rgba(0,0,0,0.3)] flex flex-col items-center gap-3 border border-blue-900 border-l-0 transition-all hover:bg-blue-700">
                <i class="fas fa-sliders-h text-lg"></i>
                <span style="writing-mode: vertical-rl;" class="font-bold tracking-wider py-1">ตั้งค่าพิกัด</span>
            </button>
        </div>
    </div>

    <div id="preview-wrapper" class="flex-1 flex flex-col items-center justify-start w-full relative pt-10">

        <div class="no-print mb-4 text-center">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">พรีวิวบัตร (85.6 x 53.98 mm)</h1>

            <div class="flex justify-center gap-4 mb-4">
                <button onclick="printAndComplete()"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg text-lg transition transform hover:scale-105 border-2 border-white">
                    <i class="fas fa-print"></i> สั่งพิมพ์และเปลี่ยนสถานะทันที
                </button>
            </div>

            <div
                class="flex items-center justify-center gap-4 bg-white px-6 py-3 rounded-full shadow border border-gray-200">
                <span class="text-sm font-bold text-gray-700"><i class="fas fa-search-plus text-blue-600"></i>
                    ซูมดูพรีวิว:</span>
                <button onclick="setZoom(1)"
                    class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-full text-xs font-bold border transition">100%</button>
                <input type="range" id="zoomSlider" min="0.5" max="4" step="0.1" value="1" class="w-48 cursor-pointer"
                    oninput="updateZoom(this.value)">
                <span id="zoomValue" class="text-sm font-bold text-blue-700 w-12 text-right">100%</span>
            </div>
        </div>

        <div class="card-box" id="print-card-area">
            <img src="<?= getBase64Img($req['photo_path'])?>" id="el_photo"
                style="position:absolute; object-fit:cover; object-position:top center; z-index: 1;">

            <div class="el flex items-center" id="el_gov" style="z-index: 10;">ข้าราชการตำรวจ</div>
            <div class="el flex items-center" id="el_no_lbl" style="z-index: 10;">เลขที่</div>
            <div class="el flex items-center" id="el_no_val" style="z-index: 10;">
                <?= $card_serial_thai?>
            </div>
            <div class="el flex items-center" id="el_name_lbl" style="z-index: 10;">ชื่อ</div>
            <div class="el flex items-center" id="el_name_val" style="z-index: 10;"><span class="shrink-text">
                    <?= htmlspecialchars($full_name_rank)?>
                </span></div>
            <div class="el flex items-center" id="el_pos_lbl" style="z-index: 10;">ตำแหน่ง</div>
            <div class="el flex items-center" id="el_pos_val" style="z-index: 10;"><span class="shrink-text">
                    <?= htmlspecialchars($req['position'])?>
                </span></div>
            <div class="el flex items-center" id="el_org_val" style="z-index: 10;"><span class="shrink-text">
                    <?= htmlspecialchars($req['org_name'])?>
                </span></div>
            <div class="el flex items-center" id="el_id_lbl" style="z-index: 10;">เลขประจำตัวประชาชน</div>
            <div class="el flex items-center" id="el_id_val" style="z-index: 10;">
                <?= $id_card_thai?>
            </div>

            <?php if ($req['signature_file']): ?>
            <img src="<?= getBase64Img($req['signature_file'])?>" id="el_app_sig"
                style="position:absolute; object-fit:contain; z-index: 1;">
            <?php
endif; ?>

            <div class="el flex items-center" id="el_app_rank" style="z-index: 10;"><span class="shrink-text">
                    <?= htmlspecialchars($req['rank_name'])?>
                </span></div>

            <div class="el flex items-center" id="el_app_sig_lbl" style="z-index: 10;">ลายมือชื่อ</div>
            <div class="el flex items-center" id="el_blood_lbl" style="z-index: 10;">หมู่โลหิต</div>
            <div class="el flex items-center" id="el_blood_val" style="z-index: 10;">
                <?= $blood_th?>
            </div>
            <div class="el flex items-center" id="el_issue_lbl" style="z-index: 10;">วันออกบัตร</div>
            <div class="el flex items-center" id="el_issue_val" style="z-index: 10;">
                <?= $issue_date_thai?>
            </div>
            <div class="el flex items-center" id="el_exp_lbl" style="z-index: 10;">บัตรหมดอายุ</div>
            <div class="el flex items-center" id="el_exp_val" style="z-index: 10;">
                <?= $expiry_date_thai?>
            </div>

            <?php if ($issuer_sig_path): ?>
            <img src="<?= getBase64Img($issuer_sig_path)?>" id="el_com_sig"
                style="position:absolute; object-fit:contain; z-index: 1;">
            <?php
endif; ?>
            <?php if ($inspector_sig_path): ?>
            <img src="<?= getBase64Img($inspector_sig_path)?>" id="el_ins_sig"
                style="position:absolute; object-fit:contain; z-index: 1;">
            <?php
endif; ?>

            <div class="el flex items-center" id="el_com_rank" style="z-index: 10;"><span class="shrink-text">
                    <?= htmlspecialchars($req['issuer_rank'] ?? '')?>
                </span></div>
            <div class="el flex items-center" id="el_com_pos_lbl" style="z-index: 10;">ตำแหน่ง</div>
            <div class="el flex items-center" id="el_com_pos_val" style="z-index: 10;"><span class="shrink-text">
                    <?= htmlspecialchars($req['issuer_pos'] ?? '')?>
                </span></div>
            <div class="el flex items-center" id="el_com_lbl" style="z-index: 10;">ผู้ออกบัตร</div>
        </div>

        <div class="pb-32"></div>
    </div>

    <script>
        function updateZoom(val) {
            document.getElementById('zoomValue').innerText = Math.round(val * 100) + '%';
            const cardArea = document.getElementById('print-card-area');
            cardArea.style.transform = `scale(${val})`;

            let extraHeight = (val > 1) ? ((val - 1) * 200) : 0;
            cardArea.style.marginBottom = `${extraHeight}px`;
        }

        function setZoom(val) {
            document.getElementById('zoomSlider').value = val;
            updateZoom(val);
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('settingsSidebar');
            const tabBtn = document.getElementById('sidebarTabBtn');
            sidebar.classList.toggle('-translate-x-full');

            if (sidebar.classList.contains('-translate-x-full')) {
                tabBtn.innerHTML = '<i class="fas fa-sliders-h text-lg"></i><span style="writing-mode: vertical-rl;" class="font-bold tracking-wider py-1">ตั้งค่าพิกัด</span>';
                tabBtn.classList.replace('bg-red-700', 'bg-blue-800');
            } else {
                tabBtn.innerHTML = '<i class="fas fa-times text-lg"></i><span style="writing-mode: vertical-rl;" class="font-bold tracking-wider py-1">ปิดหน้าต่าง</span>';
                tabBtn.classList.replace('bg-blue-800', 'bg-red-700');
            }
        }

        const defaultConfig = {
            baseFontSize: 10,
            govFontSize: 10.5,
            isLocked: true,
            elements: {
                photo: { name: 'รูปถ่าย', x: 2.75, y: 2.2, w: 25, h: 30, f: '' },
                gov: { name: 'คำว่า "ข้าราชการตำรวจ"', x: 44.9, y: 0, w: 29, h: 6, f: '' },
                no_lbl: { name: 'คำว่า "เลขที่"', x: 35, y: 4, w: 8, h: 6, f: '' },
                no_val: { name: 'เลขทะเบียนบัตร', x: 45, y: 4, w: 38, h: 6, f: '' },
                name_lbl: { name: 'คำว่า "ชื่อ"', x: 31, y: 10, w: 7, h: 6, f: '' },
                name_val: { name: 'ยศ-ชื่อ-นาม ↔️', x: 37, y: 10, w: 48, h: 6, f: '' },
                pos_lbl: { name: 'คำว่า "ตำแหน่ง"', x: 31, y: 16, w: 15, h: 6, f: '' },
                pos_val: { name: 'ตำแหน่ง ↔️', x: 45, y: 16, w: 40, h: 6, f: '' },
                org_val: { name: 'สังกัด ↔️', x: 45, y: 20, w: 39, h: 6, f: '' },
                id_lbl: { name: 'คำว่า "เลข ปชช."', x: 31, y: 24, w: 35, h: 6, f: '' },
                id_val: { name: 'เลขบัตร ปชช. (ค่า)', x: 31, y: 28, w: 53, h: 6, f: '' },
                app_rank: { name: 'ยศ (ซ้ายล่าง) ↔️', x: 2.75, y: 31, w: 15, h: 6, f: '' },
                app_sig: { name: 'ลายเซ็น (ผู้ขอ)', x: 9, y: 33, w: 15, h: 8, f: '' },
                app_sig_lbl: { name: 'คำว่า "ลายมือชื่อ"', x: 11, y: 39, w: 13, h: 6, f: '' },
                blood_lbl: { name: 'คำว่า "หมู่โลหิต"', x: 2, y: 44, w: 12, h: 6, f: '' },
                blood_val: { name: 'กรุ๊ปเลือด (ค่า)', x: 16, y: 44, w: 7, h: 6, f: '' },
                issue_lbl: { name: 'คำว่า "วันออกบัตร"', x: 2, y: 48, w: 16, h: 6, f: 8.5 },
                issue_val: { name: 'วันออกบัตร (ค่า)', x: 16.5, y: 48, w: 21, h: 6, f: 8.5 },
                exp_lbl: { name: 'คำว่า "บัตรหมดอายุ"', x: 37, y: 48, w: 18, h: 6, f: 8.5 },
                exp_val: { name: 'บัตรหมดอายุ (ค่า)', x: 54, y: 48, w: 24, h: 6, f: 8.5 },
                com_rank: { name: 'ยศ (ผู้ออกบัตร) ↔️', x: 43, y: 36, w: 12, h: 6, f: '' },
                com_sig: { name: 'ลายเซ็น (ผู้ออก)', x: 57.2, y: 30.5, w: 21, h: 15, f: '' },
                com_pos_lbl: { name: 'คำว่า "ตำแหน่ง" (ขวา)', x: 43, y: 40, w: 15, h: 6, f: '' },
                com_pos_val: { name: 'ตำแหน่ง (ผู้ออก) ↔️', x: 56, y: 40, w: 29, h: 6, f: '' },
                com_lbl: { name: 'คำว่า "ผู้ออกบัตร"', x: 57.5, y: 44, w: 15, h: 6, f: '' },
                ins_sig: { name: 'ลายเซ็น(ผู้ตรวจ)', x: 74.2, y: 48, w: 10, h: 6, f: '' }
            }
        };

        let currentConfig = JSON.parse(JSON.stringify(defaultConfig));

        async function fetchPresets() {
            let fd = new FormData();
            fd.append('ajax_action', 'load_presets');
            let res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
            if (res.status === 'success') {
                const sel = document.getElementById('preset_select');
                let currentVal = sel.value;
                sel.innerHTML = '<option value="default">-- ค่าเริ่มต้น (Default Config) --</option>';
                res.data.forEach(p => {
                    sel.innerHTML += `<option value="${p.id}">${p.preset_name}</option>`;
                });
                if ([...sel.options].some(o => o.value === currentVal)) sel.value = currentVal;
            }
        }

        async function loadPresetFromDB() {
            let pid = document.getElementById('preset_select').value;
            if (pid === 'default') {
                currentConfig = JSON.parse(JSON.stringify(defaultConfig));
                renderInputs(); applySettings(); return;
            }
            let fd = new FormData();
            fd.append('ajax_action', 'get_preset');
            fd.append('preset_id', pid);
            let res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
            if (res.status === 'success') {
                currentConfig = JSON.parse(res.data);

                if (currentConfig.govFontSize === undefined) currentConfig.govFontSize = 10.5;
                currentConfig.isLocked = true;

                for (let k in defaultConfig.elements) {
                    if (!currentConfig.elements[k]) currentConfig.elements[k] = defaultConfig.elements[k];
                    if (currentConfig.elements[k].f === undefined) currentConfig.elements[k].f = '';
                    if (currentConfig.elements[k].h === undefined) currentConfig.elements[k].h = defaultConfig.elements[k].h;
                }

                renderInputs(); applySettings();
            }
        }

        async function saveAsNewPreset() {
            let name = prompt("ตั้งชื่อ Pre-set ใหม่:");
            if (!name || name.trim() === "") return;
            let fd = new FormData();
            fd.append('ajax_action', 'save_new_preset');
            fd.append('preset_name', name.trim());
            fd.append('config_data', JSON.stringify(currentConfig));
            let res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
            if (res.status === 'success') {
                await fetchPresets();
                document.getElementById('preset_select').value = res.id;
                alert("บันทึก Pre-set ใหม่สำเร็จ!");
            }
        }

        async function updateCurrentPreset() {
            let pid = document.getElementById('preset_select').value;
            if (pid === 'default') { alert("ไม่สามารถเซฟทับค่าเริ่มต้นได้ โปรดกด 'ใหม่'"); return; }
            if (!confirm("ยืนยันการเซฟทับพิกัดทั้งหมดในโปรไฟล์นี้?")) return;

            let fd = new FormData();
            fd.append('ajax_action', 'update_preset');
            fd.append('preset_id', pid);
            fd.append('config_data', JSON.stringify(currentConfig));
            let res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
            if (res.status === 'success') alert("อัปเดตข้อมูลลงฐานข้อมูลแล้ว!");
        }

        async function deleteCurrentPreset() {
            let pid = document.getElementById('preset_select').value;
            if (pid === 'default') { alert("ไม่สามารถลบค่าเริ่มต้นได้"); return; }
            if (!confirm("ต้องการลบ Pre-set นี้ทิ้งอย่างถาวรหรือไม่?")) return;

            let fd = new FormData();
            fd.append('ajax_action', 'delete_preset');
            fd.append('preset_id', pid);
            let res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
            if (res.status === 'success') {
                await fetchPresets();
                loadPresetFromDB();
            }
        }

        function toggleLock() {
            currentConfig.isLocked = !currentConfig.isLocked;
            renderInputs();
        }

        function renderInputs() {
            const isLocked = currentConfig.isLocked;
            const lockBtn = document.getElementById('lockBtn');

            if (isLocked) {
                lockBtn.innerHTML = '<i class="fas fa-lock"></i> ล็อคอยู่ (คลิกปลด)';
                lockBtn.className = 'bg-red-100 text-red-700 border border-red-300 hover:bg-red-200 px-3 py-1 rounded text-sm font-bold shadow transition';
            } else {
                lockBtn.innerHTML = '<i class="fas fa-unlock"></i> ปลดล็อคแล้ว (คลิกเพื่อล็อค)';
                lockBtn.className = 'bg-green-100 text-green-700 border border-green-300 hover:bg-green-200 px-3 py-1 rounded text-sm font-bold shadow transition';
            }

            document.getElementById('inp_baseFontSize').value = currentConfig.baseFontSize || 10;
            document.getElementById('inp_govFontSize').value = currentConfig.govFontSize || 10.5;
            document.getElementById('inp_baseFontSize').disabled = isLocked;
            document.getElementById('inp_govFontSize').disabled = isLocked;

            const container = document.getElementById('settings-container');
            container.innerHTML = '';

            for (const key in currentConfig.elements) {
                const el = currentConfig.elements[key];
                const disabledAttr = isLocked ? 'disabled' : '';

                const row = document.createElement('div');
                row.className = 'set-row';
                row.innerHTML = `
                    <div class="text-[10px] font-semibold text-gray-700 truncate" title="${el.name}">${el.name}</div>
                    <input type="number" step="0.1" value="${el.x}" oninput="updateConfig('${key}', 'x', this.value)" ${disabledAttr} title="X (ซ้าย)">
                    <input type="number" step="0.1" value="${el.y}" oninput="updateConfig('${key}', 'y', this.value)" ${disabledAttr} title="Y (บน)">
                    <input type="number" step="0.1" value="${el.w}" oninput="updateConfig('${key}', 'w', this.value)" ${disabledAttr} title="W (กว้าง)">
                    <input type="number" step="0.1" value="${el.h}" oninput="updateConfig('${key}', 'h', this.value)" ${disabledAttr} title="H (สูง)" class="bg-green-50 text-green-800">
                    <input type="number" step="0.5" value="${el.f || ''}" placeholder="Base" oninput="updateConfig('${key}', 'f', this.value)" ${disabledAttr} class="bg-blue-50 text-blue-800">
                `;
                container.appendChild(row);
            }
        }

        function updateBaseSettings() {
            currentConfig.baseFontSize = parseFloat(document.getElementById('inp_baseFontSize').value) || 10;
            currentConfig.govFontSize = parseFloat(document.getElementById('inp_govFontSize').value) || 10.5;
            applySettings();
        }

        function updateConfig(key, axis, value) {
            if (axis === 'f') {
                currentConfig.elements[key][axis] = value === '' ? '' : parseFloat(value);
            } else {
                currentConfig.elements[key][axis] = parseFloat(value) || 0;
            }
            applySettings();
        }

        function applySettings() {
            document.documentElement.style.setProperty('--base-font-size', (currentConfig.baseFontSize || 10) + 'pt');
            document.documentElement.style.setProperty('--gov-font-size', (currentConfig.govFontSize || 10.5) + 'pt');

            for (const key in currentConfig.elements) {
                const el = currentConfig.elements[key];
                const domEl = document.getElementById('el_' + key);
                if (domEl) {
                    domEl.style.left = el.x + 'mm';
                    domEl.style.top = el.y + 'mm';
                    domEl.style.width = el.w + 'mm';
                    domEl.style.height = el.h + 'mm';

                    if (el.f !== undefined && el.f !== '') {
                        domEl.style.fontSize = el.f + 'pt';
                    } else {
                        domEl.style.fontSize = '';
                    }
                }
            }

            localStorage.setItem('idcard_temp_config', JSON.stringify(currentConfig));
            applyAutoShrink();
        }

        function applyAutoShrink() {
            const baseSize = currentConfig.baseFontSize || 10;
            const govSize = currentConfig.govFontSize || 10.5;

            document.querySelectorAll('.shrink-text').forEach(span => {
                const container = span.parentElement;
                const key = container.id.replace('el_', '');
                const elConfig = currentConfig.elements[key];

                let startingSize = baseSize;
                if (key === 'gov') startingSize = govSize;
                if (elConfig && elConfig.f !== undefined && elConfig.f !== '') {
                    startingSize = parseFloat(elConfig.f);
                }

                span.style.fontSize = startingSize + 'pt';
                let currentSize = startingSize;

                while (span.offsetWidth > container.offsetWidth && currentSize > 6) {
                    currentSize -= 0.5;
                    span.style.fontSize = currentSize + 'pt';
                }
            });
        }

        function resetDefaults() {
            if (confirm("โหลดพิกัดกลับไปเป็นค่าเริ่มต้นจากระบบ?")) {
                currentConfig = JSON.parse(JSON.stringify(defaultConfig));
                document.getElementById('preset_select').value = 'default';
                renderInputs(); applySettings();
            }
        }

        window.onload = () => {
            fetchPresets().then(() => {
                const temp = localStorage.getItem('idcard_temp_config');
                if (temp) {
                    currentConfig = JSON.parse(temp);
                    currentConfig.isLocked = true;

                    for (let k in defaultConfig.elements) {
                        if (currentConfig.elements[k].h === undefined) {
                            currentConfig.elements[k].h = defaultConfig.elements[k].h;
                        }
                    }
                    renderInputs(); applySettings();
                } else {
                    renderInputs(); applySettings();
                }
            });

            setZoom(1.5);
        };

        function printAndComplete() {
            // 1. สั่งเปิดหน้าต่างปริ้นท์
            window.print();

            // 2. เมื่อหน้าต่างปริ้นท์ถูกปิด ให้ใช้ SweetAlert2 ถามยืนยันเพื่อความพรีเมียม
            setTimeout(() => {
                Swal.fire({
                    title: 'สั่งพิมพ์สำเร็จใช่หรือไม่?',
                    text: 'ต้องการเปลี่ยนสถานะเป็น "พิมพ์บัตรแล้ว / รอรับ" และกลับหน้าแดชบอร์ดหรือไม่?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: '<i class="fas fa-check-circle"></i> ใช่, สำเร็จแล้ว',
                    cancelButtonText: 'ยังไม่พิมพ์',
                    reverseButtons: true,
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'กำลังอัปเดตสถานะ...',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });

                        fetch('api/admin/api_admin.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'update_status',
                                id: <?= $id?>,
                                status: 'READY_PICKUP',
                                reason: null
                            })
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    // 🟢 บวกแต้มการพิมพ์
                                    fetch('api/admin/api_admin.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ action: 'increment_print_count', id: <?= $id?> })
                                    });

                                    Swal.fire({
                                        title: 'สำเร็จ!',
                                        text: 'เปลี่ยนสถานะเป็น "พิมพ์บัตรแล้ว / รอรับ" เรียบร้อย',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.href = 'admin_dashboard.php';
                                    });
                                } else {
                                    Swal.fire('เกิดข้อผิดพลาด', data.message || 'ไม่สามารถอัปเดตสถานะได้', 'error');
                                }
                            })
                            .catch(err => {
                                console.error(err);
                                Swal.fire('เชื่อมต่อล้มเหลว', 'ไม่สามารถติดต่อ API ได้', 'error');
                            });
                    }
                });
            }, 500);
        }
    </script>
</body>

</html>