<?php
// idcard/admin_dashboard.php
require_once 'connect.php';
require_once 'admin_auth.php'; // 🔒 ล็อกสิทธิ์เฉพาะเจ้าหน้าที่
saveLog($conn, 'VIEW_DASHBOARD', 'เปิดดูหน้าแดชบอร์ดจัดการคำขอ (หน้าแรก)');

function getStatusBadgeAdmin($status)
{
    $c = [
        'PENDING_CHECK' => 'bg-yellow-100 text-yellow-800',
        'PENDING_APPROVAL' => 'bg-blue-100 text-blue-800',
        'SENT_TO_PRINT' => 'bg-purple-100 text-purple-800',
        'READY_PICKUP' => 'bg-green-100 text-green-800',
        'COMPLETED' => 'bg-gray-100 text-gray-800',
        'REJECTED' => 'bg-red-100 text-red-800'
    ];
    return $c[$status] ?? 'bg-gray-100 text-gray-800';
}

function getStatusLabelAdmin($status)
{
    $l = [
        'PENDING_CHECK' => 'รอตรวจสอบ',
        'PENDING_APPROVAL' => 'รออนุมัติ',
        'SENT_TO_PRINT' => 'รอพิมพ์บัตร',
        'READY_PICKUP' => 'พิมพ์บัตรแล้ว / รอรับ',
        'COMPLETED' => 'รับบัตรแล้ว (จบงาน)',
        'REJECTED' => 'ปฏิเสธคำขอ'
    ];
    return $l[$status] ?? 'ไม่ทราบสถานะ';
}

// ========================================================
// 🟢 1. จัดการระบบค้นหา (Search)
// ========================================================
$search = trim($_GET['search'] ?? '');
$filter_year = trim($_GET['filter_year'] ?? '');
$search_condition = "";
$params = [];

if ($filter_year !== '') {
    $search_condition .= " AND r.request_year = ?";
    $params[] = $filter_year;
}

if ($search !== '') {
    // ค้นหาจาก ชื่อ-สกุล, เลข ปชช, หรือเลขบัตร
    $search_condition .= " AND (r.full_name LIKE ? OR r.id_card_number LIKE ? OR r.generated_card_no LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

// โหลดรายการปีทั้งหมดมาทำ Dropdown
$stmt_years = $conn->query("SELECT DISTINCT request_year FROM idcard_requests WHERE request_year IS NOT NULL ORDER BY request_year DESC");
$available_years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);

// ========================================================
// 🟢 2. ฟังก์ชันดึงข้อมูลแยกตามสถานะ (บล็อกละไม่เกิน 10)
// ========================================================
function fetchRequestsByStatus($conn, $status, $page, $limit, $search_cond, $params)
{
    $offset = ($page - 1) * $limit;

    // นับจำนวนทั้งหมดของสถานะนั้นๆ
    $sql_count = "SELECT COUNT(*) FROM idcard_requests r WHERE r.status = ?" . $search_cond;
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute(array_merge([$status], $params));
    $total = $stmt_count->fetchColumn();

    // 🟢 กำหนดการเรียงลำดับ: ถ้าเป็น "พิมพ์บัตรแล้ว" หรือ "รับแล้ว" ให้เรียงตามเลขบัตรล่าสุด (ปี และ ลำดับ)
    $order_sql = "ORDER BY r.created_at DESC";
    if (in_array($status, ['READY_PICKUP', 'COMPLETED'])) {
        $order_sql = "ORDER BY r.card_year DESC, r.card_sequence DESC, r.created_at DESC";
    }

    // ดึงข้อมูล 10 รายการ
    $sql = "SELECT r.*, k.rank_name, o.org_name, t.type_name,
            (SELECT COUNT(*) FROM idcard_requests sub WHERE sub.id_card_number = r.id_card_number) as history_count
            FROM idcard_requests r
            LEFT JOIN idcard_ranks k ON r.rank_id = k.id
            LEFT JOIN idcard_organizations o ON r.org_id = o.id
            LEFT JOIN idcard_card_types t ON r.card_type_id = t.id
            WHERE r.status = ?" . $search_cond . " 
            $order_sql LIMIT $limit OFFSET $offset";

    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge([$status], $params));
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'data' => $data,
        'total' => $total,
        'pages' => ceil($total / $limit),
        'page' => $page
    ];
}

// Helper สร้าง Query String สำหรับปุ่มเปลี่ยนหน้า
function buildQueryString($updates)
{
    $query = $_GET;
    foreach ($updates as $k => $v) {
        $query[$k] = $v;
    }
    return '?' . http_build_query($query);
}

function renderPaginationMulti($current_page, $total_pages, $param_target)
{
    if ($total_pages <= 1)
        return '';
    $anchor = '#block_' . $param_target;
    $html = '<div class="flex justify-center mt-4 gap-1.5 flex-wrap">';
    if ($current_page > 1) {
        $prev = $current_page - 1;
        $html .= "<a href='" . buildQueryString([$param_target => $prev]) . $anchor . "' class='px-3.5 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 hover:border-gray-400 transition shadow-sm text-sm font-medium'>&laquo; ก่อน</a>";
    }
    for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
        $active = $i == $current_page ? 'bg-blue-600 text-white border-blue-600 shadow-md' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:border-gray-400';
        $html .= "<a href='" . buildQueryString([$param_target => $i]) . $anchor . "' class='px-3.5 py-1.5 border rounded-lg transition shadow-sm text-sm font-medium $active'>$i</a>";
    }
    if ($current_page < $total_pages) {
        $next = $current_page + 1;
        $html .= "<a href='" . buildQueryString([$param_target => $next]) . $anchor . "' class='px-3.5 py-1.5 bg-white border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 hover:border-gray-400 transition shadow-sm text-sm font-medium'>ถัดไป &raquo;</a>";
    }
    $html .= '</div>';
    return $html;
}

// ========================================================
// 🟢 3. กำหนดคุณสมบัติของทั้ง 6 บล็อก
// ========================================================
$blocks = [
    [
        'status' => 'PENDING_CHECK',
        'title' => 'รายการคำขอ "รอตรวจสอบ"',
        'icon' => 'fas fa-search',
        'border' => 'border-yellow-400',
        'text_icon' => 'text-yellow-600',
        'badge' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'param' => 'p_chk'
    ],
    [
        'status' => 'PENDING_APPROVAL',
        'title' => 'รายการคำขอ "รออนุมัติ"',
        'icon' => 'fas fa-user-check',
        'border' => 'border-blue-400',
        'text_icon' => 'text-blue-600',
        'badge' => 'bg-blue-100 text-blue-800 border-blue-300',
        'param' => 'p_app'
    ],
    [
        'status' => 'SENT_TO_PRINT',
        'title' => 'รายการคำขอ "รอพิมพ์บัตร"',
        'icon' => 'fas fa-print',
        'border' => 'border-purple-400',
        'text_icon' => 'text-purple-600',
        'badge' => 'bg-purple-100 text-purple-800 border-purple-300',
        'param' => 'p_prt'
    ],
    [
        'status' => 'READY_PICKUP',
        'title' => 'รายการคำขอ "พิมพ์บัตรแล้ว / รอรับ"',
        'icon' => 'fas fa-id-badge',
        'border' => 'border-green-400',
        'text_icon' => 'text-green-600',
        'badge' => 'bg-green-100 text-green-800 border-green-300',
        'param' => 'p_rdy'
    ],
    [
        'status' => 'COMPLETED',
        'title' => 'รายการคำขอ "รับบัตรแล้ว (จบงาน)"',
        'icon' => 'fas fa-check-circle',
        'border' => 'border-gray-400',
        'text_icon' => 'text-gray-600',
        'badge' => 'bg-gray-100 text-gray-800 border-gray-300',
        'param' => 'p_cmp'
    ],
    [
        'status' => 'REJECTED',
        'title' => 'รายการคำขอ "ถูกปฏิเสธ / ยกเลิก"',
        'icon' => 'fas fa-ban',
        'border' => 'border-red-400',
        'text_icon' => 'text-red-600',
        'badge' => 'bg-red-100 text-red-800 border-red-300',
        'param' => 'p_rej'
    ]
];

// ดึงข้อมูลล่วงหน้า เพื่อเช็คว่ามีข้อมูลรวมทั้งหมดกี่รายการ
$total_found = 0;
$block_results = [];
foreach ($blocks as $k => $b) {
    $page = isset($_GET[$b['param']]) ? max(1, (int)$_GET[$b['param']]) : 1;
    $res = fetchRequestsByStatus($conn, $b['status'], $page, 10, $search_condition, $params);
    $block_results[$k] = $res;
    $total_found += $res['total'];
}

// 🟢 ฟังก์ชันเรนเดอร์แถวในตาราง (แก้ไขให้รับพารามิเตอร์ $index เพื่อแสดงลำดับ)
function renderTableRow($req, $index, $is_rejected = false)
{
    $st = $req['status'];
    $st_badge = getStatusBadgeAdmin($st);
    $st_label = getStatusLabelAdmin($st);

    // วันที่ยื่น และ วันที่แก้ไขล่าสุด
    $created_at = $req['created_at'];
    $updated_at = $req['updated_at'] ?? $created_at;

    // เช็คยื่นคำขอเกิน 30 วัน
    $created_date = new DateTime($req['created_at']);
    $now = new DateTime();
    $diff = $now->diff($created_date)->days;
    $is_over_30 = ($diff >= 30 && !in_array($st, ['COMPLETED', 'REJECTED']));

    $row_class = $is_over_30 ? "bg-red-50 hover:bg-red-100 border-b border-red-200" : "bg-white hover:bg-gray-50 border-b border-gray-100";
    if ($is_rejected)
        $row_class = "bg-gray-50 hover:bg-gray-100 border-b";

    $type_name = $req['type_name'] ?? 'ไม่ระบุ';
    $type_badge = (strpos($type_name, 'บำนาญ') !== false || strpos($type_name, 'เกษียณ') !== false)
        ? 'bg-yellow-200 text-yellow-800 border border-yellow-400'
        : 'bg-green-200 text-green-800 border border-green-400';

    $formatted_id = sprintf('%04d/%s', $req['id'], $req['request_year'] ?? (date('Y', strtotime($req['created_at'])) + 543));

    echo "<tr class='transition duration-150 $row_class'>";

    // 🟢 แสดงผลลำดับที่ (แทน ID เดิม)
    echo "<td class='py-4 px-5 text-center font-bold text-gray-400 text-sm'>
        <div>{$index}</div>
        <div class='text-[10px] mt-1 text-gray-500 font-mono'>{$formatted_id}</div>
    </td>";

    echo "<td class='py-4 px-5 whitespace-nowrap'>";
    // วันที่ยื่น
    echo "<div class='mb-1.5'>";
    echo "<span class='text-[10px] text-gray-400 uppercase block tracking-wider'>ยื่น</span>";
    echo "<span class='text-sm " . ($is_over_30 ? 'text-red-600 font-semibold' : 'text-gray-700') . "'>" . date('d/m/Y', strtotime($created_at)) . " ";
    echo "<span class='text-xs " . ($is_over_30 ? 'text-red-400' : 'text-gray-400') . "'>" . date('H:i', strtotime($created_at)) . "</span></span>";
    if ($is_over_30)
        echo "<br><span class='text-[10px] text-red-500 font-semibold bg-red-50 px-1.5 py-0.5 rounded mt-0.5 inline-block'><i class='fas fa-exclamation-triangle'></i> เกิน 30 วัน</span>";
    echo "</div>";

    // วันที่แก้ไขล่าสุด
    echo "<div class='pt-1.5 border-t border-gray-100/80'>";
    echo "<span class='text-[10px] text-gray-400 uppercase block tracking-wider'>แก้ไข</span>";
    echo "<span class='text-sm text-blue-600'>" . date('d/m/Y', strtotime($updated_at)) . " ";
    echo "<span class='text-xs text-blue-400'>" . date('H:i', strtotime($updated_at)) . "</span></span>";
    echo "</div>";
    echo "</td>";

    echo "<td class='py-4 px-5'>";
    if ($is_rejected)
        echo "<del class='text-gray-400'>";
    echo "<div class='font-semibold text-gray-800 text-sm'>" . htmlspecialchars($req['rank_name'] . $req['full_name']) . "</div>";
    if ($is_rejected)
        echo "</del>";

    echo "<div class='text-xs text-gray-400 mt-1 font-mono tracking-wide'><i class='fas fa-id-card text-blue-400'></i> " . htmlspecialchars($req['id_card_number']) . "</div>";

    if ($is_rejected && !empty($req['reject_reason']))
        echo "<div class='text-xs text-red-500 mt-1.5'><i class='fas fa-info-circle'></i> " . htmlspecialchars($req['reject_reason']) . "</div>";

    if ($req['history_count'] > 1) {
        $prev_count = $req['history_count'] - 1;
        echo "<div class='mt-1.5 text-[10px] font-semibold text-red-600 bg-red-50 border border-red-200 px-2 py-0.5 rounded-md inline-block'><i class='fas fa-exclamation-circle'></i> ยื่นซ้ำ (เก่า $prev_count ครั้ง)</div>";
    }
    echo "</td>";

    echo "<td class='py-4 px-5 text-center'><span class='px-3 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap $type_badge'>" . htmlspecialchars($type_name) . "</span></td>";
    echo "<td class='py-4 px-5 text-sm text-gray-600'>" . htmlspecialchars($req['position'] ?? '-') . "<br><span class='text-xs text-gray-400'>" . htmlspecialchars($req['org_name']) . "</span></td>";

    echo "<td class='py-4 px-5 text-center'>";
    echo "<span class='px-3 py-1 rounded-full text-[11px] font-medium whitespace-nowrap $st_badge'>$st_label</span>";
    if (in_array($st, ['SENT_TO_PRINT', 'READY_PICKUP', 'COMPLETED']) && !empty($req['generated_card_no'])) {
        echo "<div class='mt-2 text-[10px] font-semibold text-blue-700 bg-blue-50/80 border border-blue-100 px-2 py-0.5 rounded-md inline-block whitespace-nowrap'><i class='fas fa-hashtag'></i> {$req['generated_card_no']}</div>";

        // 🖨️ แสดงจำนวนครั้งที่พิมพ์
        $p_count = $req['print_count'] ?? 0;
        if ($p_count > 0) {
            echo "<div class='mt-1 text-[10px] text-purple-600 bg-purple-50/80 border border-purple-100 px-2 py-0.5 rounded-md block whitespace-nowrap'><i class='fas fa-print'></i> พิมพ์แล้ว $p_count ครั้ง</div>";
        }
    }
    echo "</td>";

    echo "<td class='py-4 px-5 text-center space-x-1 whitespace-nowrap'>";

    echo "<a href='admin_edit.php?id={$req['id']}' class='inline-flex items-center gap-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium shadow-sm transition' title='เปิดข้อมูล/ดำเนินการ'><i class='fas fa-search'></i> ตรวจสอบ</a> ";

    if (in_array($st, ['SENT_TO_PRINT', 'READY_PICKUP', 'COMPLETED'])) {
        // 👮 เฉพาะ Super_Admin และ Admin เท่านั้นที่เห็นปุ่มพิมพ์
        if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['Super_Admin', 'Admin'])) {
            echo "<a href='admin_print_card.php?id={$req['id']}' class='inline-flex items-center gap-1 bg-purple-500 hover:bg-purple-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium shadow-sm transition' title='พิมพ์บัตร'><i class='fas fa-print'></i> พิมพ์</a> ";
        }
    }

    if ($st === 'READY_PICKUP') {
        echo "<button onclick='markAsCompleted({$req['id']})' class='inline-flex items-center gap-1 bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium shadow-sm transition mt-1 md:mt-0' title='เปลี่ยนสถานะเป็นรับบัตรแล้ว'><i class='fas fa-check-double'></i> ส่งมอบ</button>";
    }

    echo "</td>";
    echo "</tr>";
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ระบบจัดการคำขอ - Admin</title>
    <link rel="icon" type="image/png" href="https://portal.pathumthani.police.go.th/assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f0 100%);
            min-height: 100vh;
        }

        .block-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.92);
        }

        .block-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        }

        table tbody tr {
            transition: all 0.15s ease;
        }

        table tbody tr:hover {
            transform: translateX(2px);
        }

        .scroll-mt {
            scroll-margin-top: 80px;
        }
    </style>
</head>

<body class="pb-20">
    <?php include 'admin_navbar.php'; ?>
    <div class="mx-auto mt-8 px-4 sm:px-6 lg:px-8 max-w-[1600px]">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-tasks text-blue-600"></i>
                    แดชบอร์ดจัดการคำขอ</h1>
                <p class="text-sm text-gray-400 mt-1">จัดเรียงตามสถานะ – คลิกดูรายละเอียดหรือดำเนินการ</p>
            </div>
            <a href="admin_create.php"
                class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl shadow-lg font-semibold flex items-center gap-2 transition transform hover:-translate-y-0.5 text-sm">
                <i class="fas fa-plus-circle"></i> สร้างข้อมูลใหม่
            </a>
        </div>

        <div class="block-card p-5 rounded-2xl shadow-sm mb-8 border border-gray-200/60">
            <form method="GET" class="flex flex-col md:flex-row gap-3 items-end">
                <div class="w-full md:w-auto">
                    <label
                        class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wider">ปีคำขอ</label>
                    <select name="filter_year"
                        class="border border-gray-200 p-2.5 rounded-xl w-full md:w-32 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none bg-gray-50/50 text-sm">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($available_years as $y): ?>
                        <option value="<?= $y?>" <?= $filter_year == $y ? 'selected' : '' ?>>
                            <?= $y?>
                        </option>
                        <?php
endforeach; ?>
                    </select>
                </div>
                <div class="w-full md:flex-1">
                    <label
                        class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wider">ค้นหารายการคำขอ</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-300"></i>
                        </div>
                        <input type="text" name="search" value="<?= htmlspecialchars($search)?>"
                            placeholder="ชื่อ-สกุล, เลขประจำตัวประชาชน, เลขทะเบียนบัตร ..."
                            class="pl-10 border border-gray-200 p-2.5 rounded-xl w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none bg-gray-50/50 text-sm">
                    </div>
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <button type="submit"
                        class="bg-gray-800 hover:bg-gray-900 text-white px-6 py-2.5 rounded-xl font-semibold shadow-sm transition flex-1 md:flex-none text-sm">ค้นหา</button>
                    <?php if ($search !== '' || $filter_year !== ''): ?>
                    <a href="admin_dashboard.php"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2.5 rounded-xl font-semibold shadow-sm transition flex items-center justify-center text-sm"><i
                            class="fas fa-times mr-1"></i> ล้าง</a>
                    <?php
endif; ?>
                </div>
            </form>
        </div>

        <?php if ($total_found > 0): ?>

        <?php foreach ($blocks as $k => $b): ?>
        <?php
        $res = $block_results[$k];
        if ($res['total'] > 0):
?>
        <div id="block_<?= $b['param']?>"
            class="block-card rounded-2xl shadow-md overflow-hidden mb-8 border border-gray-200/60 scroll-mt"
            style="border-top: 3px solid; border-image: linear-gradient(90deg, var(--tw-gradient-from, #94a3b8), var(--tw-gradient-to, transparent)) 1;">
            <div
                class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gradient-to-r from-gray-50/80 to-transparent">
                <h2 class="text-lg font-semibold text-gray-700"><i
                        class="<?= $b['icon']?> <?= $b['text_icon']?> mr-1"></i>
                    <?= $b['title']?>
                </h2>
                <span class="<?= $b['badge']?> text-[11px] font-semibold px-3 py-1 rounded-full border">
                    <?= $res['total']?> รายการ
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm <?= $b['status'] === 'REJECTED' ? 'opacity-80' : ''?>">
                    <thead class="bg-gray-50/80 text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="py-3 px-5 text-center text-[11px] uppercase tracking-wider font-semibold">#</th>
                            <th class="py-3 px-5 text-left text-[11px] uppercase tracking-wider font-semibold">วันที่
                            </th>
                            <th class="py-3 px-5 text-left text-[11px] uppercase tracking-wider font-semibold">ชื่อ-สกุล
                            </th>
                            <th class="py-3 px-5 text-center text-[11px] uppercase tracking-wider font-semibold">ประเภท
                            </th>
                            <th class="py-3 px-5 text-left text-[11px] uppercase tracking-wider font-semibold">ตำแหน่ง
                            </th>
                            <th class="py-3 px-5 text-center text-[11px] uppercase tracking-wider font-semibold">สถานะ
                            </th>
                            <th class="py-3 px-5 text-center text-[11px] uppercase tracking-wider font-semibold w-44">
                                จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
            // 🟢 คำนวณลำดับที่ให้รันนิ่งตามการแบ่งหน้า (หน้า 1 เริ่มที่ 1, หน้า 2 เริ่มที่ 11)
            $start_no = (($res['page'] - 1) * 10) + 1;
            foreach ($res['data'] as $idx => $req) {
                renderTableRow($req, $start_no + $idx, $b['status'] === 'REJECTED');
            }
?>
                    </tbody>
                </table>
            </div>

            <?php if ($res['pages'] > 1): ?>
            <div class="p-4 bg-gray-50/50 border-t border-gray-100">
                <?= renderPaginationMulti($res['page'], $res['pages'], $b['param'])?>
            </div>
            <?php
            endif; ?>
        </div>
        <?php
        endif; ?>
        <?php
    endforeach; ?>

        <?php
else: ?>
        <div class="bg-white p-12 rounded-xl shadow border border-gray-200 text-center">
            <i class="fas fa-folder-open text-6xl text-gray-300 mb-4 block"></i>
            <h3 class="text-xl font-bold text-gray-500">ไม่พบข้อมูลคำขอในระบบ</h3>
            <?php if ($search): ?>
            <p class="text-gray-400 mt-2">ไม่มีข้อมูลที่ตรงกับคำค้นหา "
                <?= htmlspecialchars($search)?>"
            </p>
            <?php
    endif; ?>
        </div>
        <?php
endif; ?>

    </div>

    <script>
        function markAsCompleted(id) {
            Swal.fire({
                title: 'ยืนยันการส่งมอบบัตร?',
                text: "ต้องการเปลี่ยนสถานะเป็น 'รับบัตรแล้ว (จบงาน)' ใช่หรือไม่?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ใช่, รับบัตรแล้ว',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                    fetch('api/admin/api_admin.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'update_status',
                            id: id,
                            status: 'COMPLETED',
                            reason: null
                        })
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'สำเร็จ!',
                                    text: 'ส่งมอบบัตรเรียบร้อยแล้ว รายการจะถูกย้ายไปอยู่บล็อกรับบัตรแล้ว',
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => location.reload());
                            } else {
                                Swal.fire('Error', data.message || 'ไม่สามารถอัปเดตสถานะได้', 'error');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อระบบ', 'error');
                        });
                }
            });
        }

        // 🟢 Scroll memory: เลื่อนไปยัง block ที่กำลังดูอยู่เมื่อเปลี่ยนหน้า
        document.addEventListener('DOMContentLoaded', function () {
            if (window.location.hash) {
                const target = document.querySelector(window.location.hash);
                if (target) {
                    setTimeout(() => {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 100);
                }
            }
        });
    </script>
</body>

</html>