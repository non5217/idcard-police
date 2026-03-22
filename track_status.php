<?php
// idcard/track_status.php
require_once 'connect.php';
if (session_status() === PHP_SESSION_NONE)
    session_start();

// 🟢 ระบบ Token สำหรับแชร์ลิงก์ (ไม่ต้อง Login ใหม่)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $parts = explode('.', $token);
    if (count($parts) === 2) {
        $payload = $parts[0];
        $signature = $parts[1];

        $expected_sig = hash_hmac('sha256', $payload, TOKEN_SECRET);
        if (hash_equals($expected_sig, $signature)) {
            $data = json_decode(base64_decode($payload), true);
            if ($data && isset($data['id_card']) && isset($data['exp']) && $data['exp'] > time()) {
                // Token ถูกต้องและยังไม่หมดอายุ -> Auto Login
                $_SESSION['public_access'] = true;
                $_SESSION['id_card_public'] = $data['id_card'];
            }
        }
    }
}

// เช็คสิทธิ์ (ต้องผ่านการกรอกเลขบัตรมาก่อน หรือผ่าน Token)
if (!isset($_SESSION['public_access']) || !isset($_SESSION['id_card_public'])) {
    header("Location: index.php");
    exit();
}

$id_card = $_SESSION['id_card_public'];

// 🟢 สร้าง Link สำหรับปุ่มแชร์
$payload_share = base64_encode(json_encode([
    'id_card' => $id_card,
    'exp' => time() + (86400 * 30) // 30 วัน
]));
$signature_share = hash_hmac('sha256', $payload_share, TOKEN_SECRET);
$tracking_token_share = $payload_share . '.' . $signature_share;

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// Get current directory path properly
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$share_url = $protocol . "://" . $host . $path . "/track_status.php?token=" . $tracking_token_share;

// 🟢 หากมีการกดปุ่ม "แก้ไขข้อมูล" จากหน้านี้
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_request') {
    $edit_id = $_POST['request_id'];

    // ยืนยันว่า request นี้เป็นของบัตรประชาชนที่ Login เข้ามาจริง
    $stmt_check = $conn->prepare("SELECT * FROM idcard_requests WHERE id = ? AND id_card_number = ? LIMIT 1");
    $stmt_check->execute([$edit_id, $id_card]);
    if ($req_data = $stmt_check->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['edit_request_id'] = $edit_id;
        $_SESSION['form_prefill'] = $req_data;
        header("Location: request.php");
        exit();
    }
}

// ดึงข้อมูลคำขอ *ทั้งหมด* ของคนนี้ (ไม่เอาที่ยกเลิก)
$stmt = $conn->prepare("SELECT r.*, k.rank_name 
                        FROM idcard_requests r 
                        LEFT JOIN idcard_ranks k ON r.rank_id = k.id 
                        WHERE id_card_number = ? AND status != 'CANCELLED'
                        ORDER BY id DESC");
$stmt->execute([$id_card]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getReasonText($req)
{
    if (empty($req['request_reason']))
        return '-';

    $reason = $req['request_reason'];
    $detail = $req['request_reason_detail'] ?? '';
    $other = $req['request_reason_other'] ?? '';

    $main_reasons = [
        'FIRST' => 'ทำบัตรครั้งแรก',
        'NEW' => 'ทำบัตรใหม่',
        'CHANGE' => 'ขอเปลี่ยนบัตร'
    ];

    $detail_reasons = [
        'EXPIRED' => 'บัตรเดิมหมดอายุ',
        'LOST' => 'บัตรหายหรือถูกทำลาย',
        'CHANGE_POS' => 'เปลี่ยนตำแหน่ง/เลื่อนยศ',
        'CHANGE_NAME' => 'เปลี่ยนชื่อตัว',
        'CHANGE_SURNAME' => 'เปลี่ยนชื่อสกุล',
        'CHANGE_BOTH' => 'เปลี่ยนชื่อตัวและสกุล',
        'DAMAGED' => 'ชำรุด',
        'RETIRED' => 'ผู้รับบำเหน็จบำนาญ',
        'OTHER' => 'อื่นๆ'
    ];

    $text = $main_reasons[$reason] ?? $reason;
    if ($detail) {
        $detail_text = $detail_reasons[$detail] ?? $detail;
        if ($detail === 'OTHER' && $other) {
            $text .= ' (' . htmlspecialchars($other) . ')';
        }
        else {
            $text .= ' (' . $detail_text . ')';
        }
    }
    return $text;
}

function getStatusBadge($status)
{
    $colors = [
        'PENDING_CHECK' => 'bg-yellow-100 text-yellow-800',
        'PENDING_APPROVAL' => 'bg-blue-100 text-blue-800',
        'SENT_TO_PRINT' => 'bg-purple-100 text-purple-800',
        'READY_PICKUP' => 'bg-green-100 text-green-800',
        'COMPLETED' => 'bg-gray-100 text-gray-800',
        'REJECTED' => 'bg-red-100 text-red-800'
    ];

    $labels = [
        'PENDING_CHECK' => 'รอตรวจสอบ',
        'PENDING_APPROVAL' => 'รออนุมัติ',
        'SENT_TO_PRINT' => 'รอพิมพ์บัตร',
        'READY_PICKUP' => 'พิมพ์บัตรแล้ว / รอรับ',
        'COMPLETED' => 'รับบัตรแล้ว (จบงาน)',
        'REJECTED' => 'ปฏิเสธคำขอ'
    ];

    $t = $labels[$status] ?? 'ไม่ทราบสถานะ';
    $c = $colors[$status] ?? 'bg-gray-200 text-gray-800';

    return "<span class='px-3 py-1 rounded-full text-sm font-bold shadow-sm $c'>$t</span>";
}

// 🟢 ฟังก์ชันสำหรับวาด Timeline
function renderTimeline($current_status, $reject_reason = '')
{
    $steps = [
        'PENDING_CHECK' => ['label' => 'รอตรวจสอบ', 'icon' => 'fas fa-file-signature'],
        'PENDING_APPROVAL' => ['label' => 'รออนุมัติ', 'icon' => 'fas fa-user-check'],
        'SENT_TO_PRINT' => ['label' => 'รอพิมพ์บัตร', 'icon' => 'fas fa-print'],
        'READY_PICKUP' => ['label' => 'บัตรเสร็จแล้ว', 'icon' => 'fas fa-id-card'],
        'COMPLETED' => ['label' => 'รับบัตรแล้ว (จบงาน)', 'icon' => 'fas fa-check-circle']
    ];

    // กรณีถูกปฏิเสธ จะแสดง UI แบบพิเศษ
    if ($current_status === 'REJECTED') {
        echo '<div class="relative pl-6 sm:pl-8 pb-4">';
        echo '<div class="absolute left-3 top-2 h-full w-0.5 bg-red-200"></div>';
        echo '<div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-red-500 border-4 border-white flex items-center justify-center shadow"><i class="fas fa-times text-white text-[10px]"></i></div>';
        echo '<h4 class="font-bold text-red-600 mb-1 text-sm sm:text-base">คำขอถูกปฏิเสธ</h4>';
        echo '<div class="bg-red-50 border border-red-200 text-red-800 p-3 rounded-lg text-xs sm:text-sm mt-2">';
        echo '<strong>เหตุผล:</strong> ' . htmlspecialchars($reject_reason);
        echo '</div>';
        echo '</div>';
        return;
    }

    $status_keys = array_keys($steps);
    $current_index = array_search($current_status, $status_keys);

    if ($current_index === false)
        $current_index = -1; // Fallback for unknown status

    $html = '<div class="relative pl-6 sm:pl-8 space-y-4 sm:space-y-6">';

    foreach ($status_keys as $index => $key) {
        $step = $steps[$key];
        $is_past = $index < $current_index;
        $is_current = $index === $current_index;
        $is_future = $index > $current_index;

        // กำหนดสีตามสถานะ
        if ($is_past || $is_current) {
            $circleColor = 'bg-blue-500 border-white';
            $iconColor = 'text-white';
            $textColor = 'text-gray-900 font-bold';
            $lineColor = ($is_past) ? 'bg-blue-500' : 'bg-gray-200'; // เส้นเชื่อมเชื่อมไปอันถัดไป
        }
        else {
            $circleColor = 'bg-gray-200 border-white';
            $iconColor = 'text-gray-400';
            $textColor = 'text-gray-400';
            $lineColor = 'bg-gray-200';
        }

        // สำหรับจุดสุดท้าย ไม่ต้องมีเส้นเชื่อมลงไปต่อ
        $is_last = ($index === count($status_keys) - 1);

        $html .= '<div class="relative">';
        // เส้นเชื่อมแนวตั้ง (ถ้าไม่ใช่อันสุดท้าย)
        if (!$is_last) {
            $html .= '<div class="absolute left-3 top-7 bottom-[-20px] sm:bottom-[-24px] w-0.5 ' . $lineColor . '"></div>';
        }

        // วงกลมจุดสถานะ
        $html .= '<div class="absolute left-0 top-1 w-6 h-6 rounded-full ' . $circleColor . ' border-4 flex items-center justify-center shadow z-10">';
        if ($is_past) {
            $html .= '<i class="fas fa-check text-white text-[10px]"></i>';
        }
        else {
            $html .= '<div class="w-2 h-2 rounded-full ' . ($is_current ? 'bg-white' : 'bg-gray-400') . '"></div>';
        }
        $html .= '</div>';

        // เนื้อหา
        $html .= '<div class="pl-8">';
        $html .= '<div class="flex items-center gap-2 ' . $textColor . '">';
        $html .= '<i class="' . $step['icon'] . ' text-base ' . ($is_current ? 'text-blue-500' : $iconColor) . '"></i>';
        $html .= '<span class="text-sm sm:text-sm font-semibold">' . $step['label'] . '</span>';
        $html .= '</div>';

        if ($is_current) {
            $html .= '<p class="text-[10px] sm:text-xs text-blue-600 mt-0.5">อยู่ระหว่างขั้นตอนนี้</p>';
        }

        $html .= '</div>';
        $html .= '</div>'; // End relative step
    }

    $html .= '</div>';
    echo $html;
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สถานะคำขอ - Police ID Card</title>
    <link rel="icon" type="image/png" href="https://portal.pathumthani.police.go.th/assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        .timeline-card {
            transition: all 0.3s ease;
        }

        .timeline-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen pb-12">
    <?php include 'public_navbar.php'; ?>

    <div class="max-w-4xl mx-auto mt-6 px-4">
        <div
            class="bg-yellow-600 text-white p-5 rounded-t-xl flex flex-col sm:flex-row justify-between items-center gap-4 sm:gap-2 text-center sm:text-left shadow-md">
            <h1 class="text-xl sm:text-2xl font-bold"><i class="fas fa-search-location mr-2"></i> ผลการค้นหาสถานะ</h1>
            <a href="index.php"
                class="bg-yellow-700 hover:bg-yellow-800 px-4 py-2 rounded-lg font-semibold transition text-sm w-full sm:w-auto text-center">
                <i class="fas fa-home mr-1"></i> กลับหน้าหลัก
            </a>
        </div>

        <div class="bg-white p-6 shadow-md rounded-b-xl border-t-0 mb-8">
            <div class="text-center">
                <div class="text-gray-500 text-xs sm:text-sm font-semibold uppercase tracking-wider mb-1">
                    เลขประจำตัวประชาชน</div>
                <div
                    class="text-2xl sm:text-3xl font-bold text-gray-800 tracking-widest bg-gray-50 inline-block px-4 sm:px-6 py-2 rounded-lg border mb-6 break-all">
                    <?= htmlspecialchars($id_card)?>
                </div>
            </div>

            <!-- Share Link Input Box -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 sm:p-4 mb-4">
                <div class="mb-3 sm:mb-2 text-sm font-bold text-blue-800 flex items-start sm:items-center gap-2">
                    <i class="fas fa-share-alt mt-1 sm:mt-0"></i>
                    <span>ลิงก์สำหรับส่งต่อหรือติดตามสถานะ <span
                            class="block sm:inline text-xs sm:text-sm font-normal sm:font-bold opacity-80">(มีอายุ 30
                            วัน)</span></span>
                </div>
                <div class="flex flex-col sm:flex-row shadow-sm gap-2 sm:gap-0 mt-2">
                    <input type="text" id="shareUrlInput" value="<?= $share_url?>" readonly
                        class="bg-white border text-gray-700 text-base sm:text-sm rounded-lg sm:rounded-l-lg sm:rounded-tr-none focus:ring-blue-500 focus:border-blue-500 block w-full p-3 sm:p-2.5 outline-none font-mono">
                    <div class="flex w-full sm:w-auto gap-2 sm:gap-0 mt-1 sm:mt-0">
                        <button type="button" onclick="copyShareLink()"
                            class="flex-1 sm:flex-none text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-bold sm:font-medium text-base sm:text-sm px-4 py-3 sm:py-2.5 text-center transition rounded-lg sm:rounded-none sm:border-r border-blue-500">
                            <i class="fas fa-copy"></i> คัดลอก
                        </button>
                        <button type="button" onclick="shareToLine()"
                            class="flex-1 sm:flex-none text-white bg-[#00B900] hover:bg-[#009b00] focus:ring-4 focus:outline-none focus:ring-green-300 font-bold sm:font-medium text-base sm:text-sm px-4 py-3 sm:py-2.5 text-center transition rounded-lg sm:rounded-r-lg sm:rounded-bl-none">
                            <i class="fab fa-line text-lg"></i> ส่งไลน์
                        </button>
                    </div>
                </div>
            </div>

            <!-- 🔔 LINE Notification Linking -->
            <?php
            // Check if already linked
            $stmt_line = $conn->prepare("SELECT id FROM idcard_line_subscriptions WHERE id_card_number = ? AND is_active = 1 LIMIT 1");
            $stmt_line->execute([$id_card]);
            $is_line_linked = $stmt_line->fetch();

            if (isset($_SESSION['line_link_success'])) {
                echo '<div class="bg-green-100 border border-green-200 text-green-800 p-4 rounded-lg mb-4 text-center animate-bounce">
                        <i class="fas fa-check-circle mr-2"></i> <strong>สำเร็จ!</strong> เชื่อมต่อ LINE เพื่อรับแจ้งเตือนเรียบร้อยแล้ว
                      </div>';
                unset($_SESSION['line_link_success']);
            }
            ?>

            <div class="bg-white border-2 border-dashed border-gray-200 rounded-xl p-4 sm:p-6 text-center">
                <?php if ($is_line_linked): ?>
                    <div class="text-green-600 font-bold flex flex-col items-center gap-2">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-xl">
                            <i class="fab fa-line"></i>
                        </div>
                        <div>คุณได้เชื่อมต่อ LINE เพื่อรับการแจ้งเตือนแล้ว</div>
                        <p class="text-xs text-gray-500 font-normal">ระบบจะส่งข้อความหาท่านเมื่อสถานะมีการเปลี่ยนแปลง</p>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center gap-4">
                        <div class="text-gray-700 font-semibold text-sm sm:text-base">
                            <i class="fas fa-bell text-yellow-500 mr-2"></i> ต้องการรับการแจ้งเตือนผ่าน LINE หรือไม่?
                        </div>
                        <p class="text-xs text-gray-500">หากสถานะบัตรมีการเปลี่ยนแปลง ระบบจะส่งข้อความแจ้งท่านทันที</p>
                        <a href="line_login.php" 
                           class="bg-[#00B900] hover:bg-[#009b00] text-white px-6 py-3 rounded-full font-bold flex items-center gap-2 shadow-lg transition transform hover:scale-105 active:scale-95">
                            <i class="fab fa-line text-2xl"></i> เชื่อมต่อ LINE รับแจ้งเตือนสถานะ
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($requests) > 0): ?>
            <div class="mt-4 text-center text-gray-600">
                พบประวัติคำขอทั้งหมด <span class="font-bold text-blue-600 text-lg">
                    <?= count($requests)?>
                </span> รายการ
            </div>
            <?php
endif; ?>
        </div>

        <?php if (count($requests) > 0): ?>
        <div class="space-y-6">
            <?php foreach ($requests as $index => $req): ?>
            <div class="bg-white rounded-xl shadow-md border border-gray-100 timeline-card overflow-hidden">

                <!-- Card Header -->
                <div
                    class="bg-gray-50 px-4 sm:px-6 py-4 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-4">
                    <div class="w-full sm:w-auto flex flex-col items-start">
                        <span
                            class="bg-blue-100 text-blue-800 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide font-mono mb-2 sm:mb-0">
                            คำขอเลขที่:
                            <?= sprintf('%04d/%s', $req['id'], $req['request_year'] ?? (date('Y', strtotime($req['created_at'])) + 543))?>
                        </span>
                        <h3 class="text-lg sm:text-lg font-bold text-gray-800 mt-2 sm:mt-2 line-clamp-2">
                            <?= htmlspecialchars($req['rank_name'] . $req['full_name'])?>
                        </h3>
                        <p class="text-sm sm:text-sm text-gray-500 mt-1">
                            <i class="far fa-calendar-alt mr-1"></i> ยื่นเมื่อ:
                            <?= date('d/m/Y H:i', strtotime($req['created_at']))?>
                        </p>
                    </div>
                    <div
                        class="w-full sm:w-auto flex items-center justify-between sm:block border-t sm:border-t-0 pt-3 sm:pt-0 mt-2 sm:mt-0">
                        <div class="text-xs sm:text-sm text-gray-500 font-semibold sm:mb-2">สถานะระบบ:</div>
                        <?= getStatusBadge($req['status'])?>
                    </div>
                </div>

                <!-- Card Body (Timeline) -->
                <div class="p-4 sm:p-6 md:p-8 grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 items-start">

                    <!-- ฝั่งซ้าย: Timeline -->
                    <div class="bg-white rounded-lg p-2">
                        <?php renderTimeline($req['status'], $req['reject_reason'] ?? ''); ?>
                    </div>

                    <!-- ฝั่งขวา: Action Buttons & Info -->
                    <div class="bg-blue-50/50 rounded-xl p-6 border border-blue-50 h-full flex flex-col justify-center">
                        <h4 class="font-bold text-gray-800 mb-4 border-b pb-2"><i
                                class="fas fa-info-circle text-blue-500 mr-2"></i> ข้อมูลเพิ่มเติม</h4>

                        <div class="space-y-4 sm:space-y-3 text-base sm:text-sm text-gray-700 mb-6 flex-grow">
                            <div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
                                <span class="text-gray-500 font-semibold sm:font-normal">เหตุผลการขอ:</span>
                                <span
                                    class="font-bold sm:font-semibold text-left sm:text-right text-blue-800 sm:text-gray-800 max-w-full sm:max-w-[70%] bg-blue-50 sm:bg-transparent p-2 sm:p-0 rounded-md sm:rounded-none">
                                    <?= getReasonText($req)?>
                                </span>
                            </div>
                            <?php if (!empty($req['old_card_number'])): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-500">เลขบัตรเดิม:</span>
                                <span class="font-semibold text-right">
                                    <?= htmlspecialchars($req['old_card_number'])?>
                                </span>
                            </div>
                            <?php
        endif; ?>
                        </div>

                        <?php
        // เช็คว่าสถานะนี้อนุญาตให้แก้ไขเนื้อหาได้ไหม
        $can_edit = in_array($req['status'], ['PENDING_CHECK', 'PENDING_APPROVAL', 'SENT_TO_PRINT', 'REJECTED']);
        if ($can_edit):
?>
                        <div class="mt-auto">
                            <form method="POST" action="track_status.php"
                                onsubmit="return confirm('โปรดทราบ: หากท่านแก้ไขข้อมูลแล้วกดบันทึก สถานะคำขอใบนี้จะกลับไปเริ่มต้นที่ \'รอตรวจสอบ\' อีกครั้ง เพื่อให้เจ้าหน้าที่ตรวจสอบข้อมูลใหม่\n\nยืนยันแก้ไขข้อมูลหรือไม่?');">
                                <input type="hidden" name="action" value="edit_request">
                                <input type="hidden" name="request_id" value="<?= $req['id']?>">
                                <button type="submit"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition flex items-center justify-center gap-2 group">
                                    <i class="fas fa-edit group-hover:scale-110 transition-transform"></i>
                                    แก้ไขข้อมูลคำขอนี้
                                </button>
                            </form>
                            <p class="text-xs text-center text-gray-500 mt-3 flex items-start justify-center gap-1">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5"></i>
                                หากบันทึกใหม่ สถานะจะกลับไป<br>เป็น 'รอตรวจสอบ' เท่านั้น
                            </p>
                        </div>
                        <?php
        else: ?>
                        <div class="mt-auto text-center bg-gray-100 py-3 px-4 rounded-lg border">
                            <p class="text-gray-600 font-semibold text-sm">
                                <i class="fas fa-lock mr-1"></i> คำขอนี้เสร็จสมบูรณ์แล้ว ไม่สามารถแก้ไขได้
                            </p>
                        </div>
                        <?php
        endif; ?>
                    </div>

                </div>
            </div>
            <?php
    endforeach; ?>
        </div>
        <?php
else: ?>
        <div class="bg-white rounded-xl shadow-md p-10 text-center border-t-4 border-gray-300">
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-folder-open text-4xl text-gray-400"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-700 mb-2">ไม่พบประวัติคำขอ</h2>
            <p class="text-gray-500 mb-6">ยังไม่มีประวัติการยื่นขอมีบัตรของเลขประจำตัวประชาชนนี้ในระบบ</p>
            <a href="request.php"
                class="inline-block bg-blue-600 text-white font-bold px-8 py-3 rounded-lg shadow-md hover:bg-blue-700 transition">
                <i class="fas fa-plus-circle mr-2"></i> สร้างคำขอใหม่
            </a>
        </div>
        <?php
endif; ?>
    </div>
    <!-- SweetAlert2 for notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function copyShareLink() {
            const copyText = document.getElementById("shareUrlInput");
            copyText.select();
            copyText.setSelectionRange(0, 99999); // สำหรับมือถือ

            navigator.clipboard.writeText(copyText.value).then(() => {
                Swal.fire({
                    title: 'คัดลอกลิงก์สำเร็จ!',
                    text: 'คัดลอกลิงก์ลงคลิปบอร์ดแล้ว คุณสามารถกดวาง (Paste) เพื่อส่งให้ผู้อื่นได้เลย',
                    icon: 'success',
                    timer: 4000,
                    showConfirmButton: true,
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#2563eb'
                });
            }).catch(err => {
                console.error('Copy failed: ', err);
                Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถคัดลอกลิงก์ได้ กรุณาลองใหม่', 'error');
            });
        }

        function shareToLine() {
            const shareUrl = "<?= $share_url?>";
            const text = encodeURIComponent("ตรวจสอบสถานะคำขอทำบัตรประจำตัวตำรวจ: " + shareUrl);
            window.open("https://line.me/R/msg/text/?" + text, "_blank");
        }
    </script>
</body>

</html>