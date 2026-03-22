<?php
// idcard/admin_report.php
require_once 'connect.php';
require_once 'admin_auth.php'; // 🔒 เฉพาะ Admin

// 🛑 ตรวจสอบสิทธิ์ (หน้านี้ให้เฉพาะ Super_Admin หรือ Admin ดูได้)
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['Super_Admin', 'Admin'])) {
    die("
        <div style='text-align:center; padding: 50px; font-family: sans-serif;'>
            <h1 style='color: red;'>⛔ Access Denied</h1>
            <p>เฉพาะ Super_Admin หรือ Admin เท่านั้นที่สามารถดูรายงานและประวัติการใช้งานได้</p>
            <a href='admin_dashboard.php'>กลับหน้าหลัก</a>
        </div>
    ");
}

// 🟢 กำหนด Tab ปัจจุบัน (ค่าเริ่มต้นคือ report)
$active_tab = $_GET['tab'] ?? 'report';

// =========================================================
// 🟢 1. ส่วนระบบ Export ไฟล์ข้อมูลเป็น CSV (Excel)
// =========================================================
if (isset($_GET['export_year'])) {
    $export_year = (int)$_GET['export_year'];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="IDCard_Report_Year_' . $export_year . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // ใส่ BOM

    fputcsv($output, ['ลำดับ', 'เลขทะเบียนบัตร', 'ประเภทบัตร', 'ยศ', 'ชื่อ-สกุล', 'ตำแหน่ง', 'สังกัด', 'วันที่ออกบัตร', 'วันหมดอายุ', 'สถานะ']);

    $sql_export = "SELECT r.generated_card_no, t.type_name, k.rank_name, r.full_name, r.position, o.org_name, r.issue_date, r.expire_date, r.status
            FROM idcard_requests r
            LEFT JOIN idcard_ranks k ON r.rank_id = k.id
            LEFT JOIN idcard_organizations o ON r.org_id = o.id
            LEFT JOIN idcard_card_types t ON r.card_type_id = t.id
            WHERE r.card_year = ? AND r.status IN ('SENT_TO_PRINT', 'READY_PICKUP', 'COMPLETED')
            ORDER BY r.card_sequence ASC";

    $stmt_export = $conn->prepare($sql_export);
    $stmt_export->execute([$export_year]);

    $i = 1;
    while ($row = $stmt_export->fetch(PDO::FETCH_ASSOC)) {
        $status_label = [
            'SENT_TO_PRINT' => 'รอพิมพ์บัตร',
            'READY_PICKUP' => 'พิมพ์บัตรแล้ว / รอรับ',
            'COMPLETED' => 'รับบัตรแล้ว (จบงาน)'
        ][$row['status']] ?? $row['status'];

        $issue_th = (!empty($row['issue_date']) && $row['issue_date'] != '0000-00-00') ? date('d/m/', strtotime($row['issue_date'])) . (date('Y', strtotime($row['issue_date'])) + 543) : '-';
        $expire_th = '-';
        if ($row['expire_date'] === '9999-12-31') {
            $expire_th = 'ตลอดชีพ';
        }
        elseif (!empty($row['expire_date']) && $row['expire_date'] != '0000-00-00') {
            $expire_th = date('d/m/', strtotime($row['expire_date'])) . (date('Y', strtotime($row['expire_date'])) + 543);
        }

        fputcsv($output, [
            $i++, $row['generated_card_no'], $row['type_name'], $row['rank_name'], $row['full_name'],
            $row['position'], $row['org_name'], $issue_th, $expire_th, $status_label
        ]);
    }
    fclose($output);
    exit();
}

// =========================================================
// 🟢 2. ดึงข้อมูลสำหรับตารางสรุปสถิติ (Report Tab)
// =========================================================
$types_stmt = $conn->query("SELECT id, type_name FROM idcard_card_types ORDER BY id ASC");
$all_types = $types_stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_summary = "SELECT r.card_year, r.card_type_id, COUNT(r.id) as total,
                       SUM(CASE WHEN r.print_count > 1 THEN r.print_count - 1 ELSE 0 END) as defective_total
                FROM idcard_requests r
                WHERE r.status IN ('SENT_TO_PRINT', 'READY_PICKUP', 'COMPLETED') 
                  AND r.card_year IS NOT NULL AND r.card_year > 0
                GROUP BY r.card_year, r.card_type_id
                ORDER BY r.card_year DESC";
$summary_stmt = $conn->query($sql_summary);
$raw_data = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);

$report_data = [];
$grand_total = 0;
$grand_defective = 0;
foreach ($raw_data as $row) {
    $year = $row['card_year'];
    $type_id = $row['card_type_id'];
    if (!isset($report_data[$year]))
        $report_data[$year] = ['total_year' => 0, 'defective_year' => 0];

    $report_data[$year][$type_id] = $row['total'];
    $report_data[$year]['total_year'] += $row['total'];
    $report_data[$year]['defective_year'] += (int)$row['defective_total'];

    $grand_total += $row['total'];
    $grand_defective += (int)$row['defective_total'];
}

// 🟢 2.1 ดึงสรุปสถานะทั้งหมด (สำหรับแดชบอร์ดหน้ารายงาน)
$status_counts_raw = $conn->query("SELECT status, COUNT(*) as count FROM idcard_requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$total_all_req = array_sum($status_counts_raw);
$total_issued = ($status_counts_raw['SENT_TO_PRINT'] ?? 0) + ($status_counts_raw['READY_PICKUP'] ?? 0) + ($status_counts_raw['COMPLETED'] ?? 0);
$total_pending = ($status_counts_raw['PENDING_CHECK'] ?? 0) + ($status_counts_raw['PENDING_APPROVAL'] ?? 0);
$total_rejected = $status_counts_raw['REJECTED'] ?? 0;

// =========================================================
// 🟢 3. ดึงข้อมูลประวัติการใช้งาน (Logs Tab)
// =========================================================
$limit = 100;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$filter_user = trim($_GET['filter_user'] ?? '');
$filter_action = trim($_GET['filter_action'] ?? '');

$where_sql = "1=1";
$params = [];

if ($search !== '') {
    $where_sql .= " AND (l.action_detail LIKE ? OR l.user_identifier LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_user !== '') {
    $where_sql .= " AND l.user_type = ?";
    $params[] = $filter_user;
}
if ($filter_action !== '') {
    $where_sql .= " AND l.action_type LIKE ?";
    $params[] = "%$filter_action%";
}

$stmt_total = $conn->prepare("SELECT COUNT(*) FROM idcard_logs l WHERE $where_sql");
$stmt_total->execute($params);
$total_logs = $stmt_total->fetchColumn();
$total_pages = ceil($total_logs / $limit);

$stmt = $conn->prepare("
    SELECT l.*, 
           c.first_name AS admin_fname, 
           c.last_name AS admin_lname, 
           c.rank AS admin_rank,
           (SELECT full_name FROM idcard_requests r WHERE CAST(r.id_card_number AS BINARY) = CAST(l.user_identifier AS BINARY) ORDER BY id DESC LIMIT 1) AS public_fullname
    FROM idcard_logs l
    LEFT JOIN central_users c ON (l.user_type = 'ADMIN' AND l.user_identifier = c.id)
    WHERE $where_sql 
    ORDER BY l.created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// สร้าง Query String สำหรับแบ่งหน้า โดยผูก Tab เข้าไปด้วย
$query_string = "&tab=logs&search=" . urlencode($search) . "&filter_user=" . urlencode($filter_user) . "&filter_action=" . urlencode($filter_action);

// จัดเตรียมข้อมูล Reference เอาไว้ให้ฝั่ง JS ใช้แปลภาษา (Data Diff)
$map_orgs = $conn->query("SELECT id, org_name FROM idcard_organizations")->fetchAll(PDO::FETCH_KEY_PAIR);
$map_pos = $conn->query("SELECT id, position_name FROM idcard_positions")->fetchAll(PDO::FETCH_KEY_PAIR);
$map_ranks = $conn->query("SELECT id, rank_name FROM idcard_ranks")->fetchAll(PDO::FETCH_KEY_PAIR);
$map_types = $conn->query("SELECT id, type_name FROM idcard_card_types")->fetchAll(PDO::FETCH_KEY_PAIR);

function getActionBadge($type)
{
    if (strpos($type, 'VIEW') !== false)
        return 'bg-blue-100 text-blue-800 border-blue-200';
    if (strpos($type, 'UPDATE') !== false || strpos($type, 'CHANGE') !== false || strpos($type, 'EDIT') !== false || strpos($type, 'SETTING') !== false)
        return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    if (strpos($type, 'DELETE') !== false || strpos($type, 'REVOKE') !== false)
        return 'bg-red-100 text-red-800 border-red-200';
    if (strpos($type, 'CREATE') !== false || strpos($type, 'ADD') !== false || strpos($type, 'ASSIGN') !== false)
        return 'bg-green-100 text-green-800 border-green-200';
    return 'bg-gray-100 text-gray-800 border-gray-200';
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานและประวัติการใช้งาน - Admin</title>
    <link rel="icon" type="image/png" href="https://portal.pathumthani.police.go.th/assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        .custom-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 10px;
        }
    </style>
</head>

<body class="bg-gray-100 pb-20">

    <?php include 'admin_navbar.php'; ?>

    <div class="container mx-auto mt-8 p-4 max-w-7xl">

        <div class="border-b border-gray-300 mb-6">
            <nav class="-mb-px flex space-x-6">
                <a href="?tab=report"
                    class="<?= $active_tab == 'report' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'?> whitespace-nowrap py-4 px-4 border-b-4 font-bold text-lg transition flex items-center gap-2">
                    <i class="fas fa-chart-bar"></i> รายงานสถิติ
                </a>
                <a href="?tab=logs"
                    class="<?= $active_tab == 'logs' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'?> whitespace-nowrap py-4 px-4 border-b-4 font-bold text-lg transition flex items-center gap-2">
                    <i class="fas fa-history"></i> ประวัติการใช้งาน (Logs)
                </a>
            </nav>
        </div>

        <div class="<?= $active_tab == 'report' ? 'block' : 'hidden'?> animate-[fadeIn_0.3s_ease-out]">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-800">ภาพรวมสถานะคำขอทั้งหมด</h2>
            </div>

            <!-- 📊 Status Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-md border-l-8 border-blue-500 transform transition hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">คำร้องทั้งหมด</p>
                            <h3 class="text-3xl font-black text-blue-600 mt-1"><?= number_format($total_all_req) ?></h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full text-blue-600">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-4">จำนวนการยื่นคำร้องรวมทุกสถานะ</p>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md border-l-8 border-green-500 transform transition hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">ออกบัตรไปแล้ว</p>
                            <h3 class="text-3xl font-black text-green-600 mt-1"><?= number_format($total_issued) ?></h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full text-green-600">
                            <i class="fas fa-id-card fa-2x"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-4">พิมพ์บัตรแล้ว/รอรับ/รับบัตรแล้ว</p>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md border-l-8 border-yellow-500 transform transition hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">รอทำบัตร</p>
                            <h3 class="text-3xl font-black text-yellow-600 mt-1"><?= number_format($total_pending) ?></h3>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full text-yellow-600">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-4">รอตรวจสอบ และ รออนุมัติ</p>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md border-l-8 border-red-500 transform transition hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold text-gray-500 uppercase tracking-wider">ยื่นไม่ผ่าน/ยกเลิก</p>
                            <h3 class="text-3xl font-black text-red-600 mt-1"><?= number_format($total_rejected) ?></h3>
                        </div>
                        <div class="bg-red-100 p-3 rounded-full text-red-600">
                            <i class="fas fa-times-circle fa-2x"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-4">คำร้องที่ถูกปฏิเสธ หรือถูกยกเลิก</p>
                </div>
            </div>

            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold text-gray-800">สรุปจำนวนบัตรแยกตามปี พ.ศ. และประเภทบัตร</h2>
                <div class="flex flex-col md:flex-row gap-3">
                    <div class="bg-blue-900 text-white px-6 py-2 rounded-lg shadow-md font-bold text-lg">
                        ยอดออกบัตรสะสมทั้งหมด: <span class="text-yellow-300 text-2xl">
                            <?= number_format($grand_total)?>
                        </span> ใบ
                    </div>
                    <div class="bg-red-900 text-white px-6 py-2 rounded-lg shadow-md font-bold text-lg">
                        ยอดบัตรเสียสะสม: <span class="text-yellow-300 text-2xl">
                            <?= number_format($grand_defective)?>
                        </span> ใบ
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6 border-t-4 border-blue-600">
                <?php if (empty($report_data)): ?>
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded text-center">
                    <i class="fas fa-info-circle"></i> ยังไม่มีข้อมูลการออกบัตรในระบบ
                </div>
                <?php
else: ?>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full bg-white text-center">
                        <thead class="bg-gray-200 text-gray-700">
                            <tr>
                                <th class="py-3 px-4 font-bold border-b border-r text-left">ปี พ.ศ.</th>
                                <?php foreach ($all_types as $type): ?>
                                <th class="py-3 px-4 font-bold border-b border-r">
                                    <?= htmlspecialchars($type['type_name'])?>
                                </th>
                                <?php
    endforeach; ?>
                                <th class="py-3 px-4 font-bold border-b border-r bg-red-100 text-red-900">บัตรเสีย (ใบ)
                                </th>
                                <th class="py-3 px-4 font-bold border-b border-r bg-blue-100 text-blue-900">รวมทั้งหมด
                                    (ใบ)</th>
                                <th class="py-3 px-4 font-bold border-b">ดาวน์โหลดข้อมูล</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $year => $data): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="py-3 px-4 border-r text-left font-bold text-lg text-gray-800">
                                    <?= $year?>
                                </td>
                                <?php foreach ($all_types as $type):
            $count = isset($data[$type['id']]) ? $data[$type['id']] : 0; ?>
                                <td class="py-3 px-4 border-r text-gray-600">
                                    <?= $count > 0 ? number_format($count) : '<span class="text-gray-300">-</span>'?>
                                </td>
                                <?php
        endforeach; ?>
                                <td class="py-3 px-4 border-r font-bold text-red-700 bg-red-50/50">
                                    <?= number_format($data['defective_year'])?>
                                </td>
                                <td class="py-3 px-4 border-r font-bold text-blue-700 bg-blue-50/50">
                                    <?= number_format($data['total_year'])?>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="admin_report.php?export_year=<?= $year?>"
                                        class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-bold shadow transition"><i
                                            class="fas fa-file-excel"></i> Export CSV</a>
                                </td>
                            </tr>
                            <?php
    endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
endif; ?>
                <p class="text-xs text-gray-500 mt-4">* หมายเหตุ: ระบบจะนับและ Export เฉพาะคำขอที่มีสถานะ
                    "รอพิมพ์บัตร", "รอรับบัตร" และ "รับบัตรแล้ว" เท่านั้น</p>
            </div>
        </div>

        <div class="<?= $active_tab == 'logs' ? 'block' : 'hidden'?> animate-[fadeIn_0.3s_ease-out]">

            <div class="bg-white p-4 rounded-xl shadow-sm mb-6 border border-gray-200">
                <form action="" method="GET" class="flex flex-col md:flex-row gap-3">
                    <input type="hidden" name="tab" value="logs">
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-gray-500 mb-1">กลุ่มผู้ใช้งาน</label>
                        <select name="filter_user"
                            class="w-full border p-2 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50">
                            <option value="">-- ทั้งหมด --</option>
                            <option value="ADMIN" <?=$filter_user==='ADMIN' ? 'selected' : '' ?>>👨‍✈️ เจ้าหน้าที่
                                (Admin)</option>
                            <option value="PUBLIC" <?=$filter_user==='PUBLIC' ? 'selected' : '' ?>>🧑 ประชาชน (Public)
                            </option>
                            <option value="GUEST" <?=$filter_user==='GUEST' ? 'selected' : '' ?>>👤 ผู้เยี่ยมชม (Guest)
                            </option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-bold text-gray-500 mb-1">ประเภทการทำงาน</label>
                        <select name="filter_action"
                            class="w-full border p-2 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50">
                            <option value="">-- ทั้งหมด --</option>
                            <option value="VIEW" <?=$filter_action==='VIEW' ? 'selected' : '' ?>>👁️ เปิดดูข้อมูล
                            </option>
                            <option value="CREATE" <?=$filter_action==='CREATE' ? 'selected' : '' ?>>➕ สร้างใหม่
                            </option>
                            <option value="UPDATE" <?=$filter_action==='UPDATE' ? 'selected' : '' ?>>✏️ แก้ไข/อัปเดต
                            </option>
                            <option value="STATUS" <?=$filter_action==='STATUS' ? 'selected' : '' ?>>🔄 เปลี่ยนสถานะ
                            </option>
                            <option value="DELETE" <?=$filter_action==='DELETE' ? 'selected' : '' ?>>❌ ลบข้อมูล</option>
                            <option value="SETTING" <?=$filter_action==='SETTING' ? 'selected' : '' ?>>⚙️ ตั้งค่าระบบ
                            </option>
                        </select>
                    </div>
                    <div class="flex-2">
                        <label class="block text-xs font-bold text-gray-500 mb-1">ค้นหารายละเอียด / ID</label>
                        <div class="flex">
                            <input type="text" name="search" value="<?= htmlspecialchars($search)?>"
                                placeholder="พิมพ์คำค้นหา..."
                                class="border p-2 rounded-l-lg outline-none focus:ring-2 focus:ring-indigo-500 w-full md:w-64 bg-gray-50">
                            <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-r-lg shadow transition"><i
                                    class="fas fa-search"></i> ค้นหา</button>
                        </div>
                    </div>
                    <?php if ($search || $filter_user || $filter_action): ?>
                    <div class="flex items-end">
                        <a href="admin_report.php?tab=logs"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg shadow-sm transition whitespace-nowrap h-[42px] flex items-center">
                            <i class="fas fa-times mr-1"></i> ล้างตัวกรอง
                        </a>
                    </div>
                    <?php
endif; ?>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-md overflow-hidden border-t-4 border-indigo-500">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="p-3 border-b text-center">วัน-เวลา</th>
                                <th class="p-3 border-b">ผู้กระทำ</th>
                                <th class="p-3 border-b text-center">ประเภท (Action)</th>
                                <th class="p-3 border-b w-1/3">รายละเอียด</th>
                                <th class="p-3 border-b">IP Address</th>
                                <th class="p-3 border-b text-center">ข้อมูลเชิงลึก</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logs) > 0):
    foreach ($logs as $l): ?>
                            <tr class="border-b hover:bg-indigo-50 transition">
                                <td class="p-3 text-center whitespace-nowrap">
                                    <div class="font-bold text-gray-700">
                                        <?= date('d/m/Y', strtotime($l['created_at']))?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= date('H:i:s', strtotime($l['created_at']))?> น.
                                    </div>
                                </td>
                                <td class="p-3">
                                    <?php
        $display_name = htmlspecialchars($l['user_identifier']);
        $sub_text = $l['user_type'];
        if ($l['user_type'] === 'ADMIN' && !empty($l['admin_fname'])) {
            $display_name = htmlspecialchars(($l['admin_rank'] ?? '') . $l['admin_fname'] . ' ' . $l['admin_lname']);
            $sub_text = "Admin ID: " . $l['user_identifier'];
        }
        elseif ($l['user_type'] === 'PUBLIC' && !empty($l['public_fullname'])) {
            $display_name = htmlspecialchars($l['public_fullname']);
            $sub_text = "ประชาชน (ID: " . $l['user_identifier'] . ")";
        }
        elseif ($l['user_type'] === 'GUEST') {
            $display_name = "บุคคลทั่วไป / ระบบ";
        }
?>
                                    <div class="font-bold text-indigo-800">
                                        <?= $display_name?>
                                    </div>
                                    <div class="text-[11px] text-gray-500"><i class="fas fa-user-tag"></i>
                                        <?= $sub_text?>
                                    </div>
                                </td>
                                <td class="p-3 text-center"><span
                                        class="px-2 py-1 border rounded text-[11px] font-bold whitespace-nowrap <?= getActionBadge($l['action_type'])?>">
                                        <?= $l['action_type']?>
                                    </span></td>
                                <td class="p-3 text-gray-700 leading-tight">
                                    <?= htmlspecialchars($l['action_detail'])?>
                                    <?php if ($l['target_id']): ?>
                                    <div class="text-xs text-blue-500 mt-1 font-semibold">Target ID: #
                                        <?= $l['target_id']?>
                                    </div>
                                    <?php
        endif; ?>
                                </td>
                                <td class="p-3">
                                    <div class="text-xs font-mono bg-gray-100 p-1 rounded inline-block">
                                        <?= htmlspecialchars($l['ip_address'])?>
                                    </div>
                                </td>
                                <td class="p-3 text-center">
                                    <?php if (!empty($l['old_data']) || !empty($l['new_data'])): ?>
                                    <button type="button" onclick="viewDataModal(this)"
                                        data-user="<?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8')?>"
                                        data-type="<?= htmlspecialchars($l['action_type'], ENT_QUOTES, 'UTF-8')?>"
                                        data-old="<?= htmlspecialchars($l['old_data'] ?? '{}', ENT_QUOTES, 'UTF-8')?>"
                                        data-new="<?= htmlspecialchars($l['new_data'] ?? '{}', ENT_QUOTES, 'UTF-8')?>"
                                        class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 px-3 py-1.5 rounded shadow-sm text-xs font-bold transition whitespace-nowrap"><i
                                            class="fas fa-list text-[10px]"></i> เช็ค Diff</button>
                                    <?php
        else: ?><span class="text-xs text-gray-400">-</span>
                                    <?php
        endif; ?>
                                </td>
                            </tr>
                            <?php
    endforeach;
else: ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-gray-500 font-bold bg-gray-50">
                                    ไม่พบประวัติการใช้งานที่ตรงกับเงื่อนไข</td>
                            </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="p-4 bg-gray-50 border-t flex flex-wrap justify-center gap-1 text-sm">
                    <?php if ($page > 1): ?>
                    <a href="?page=1<?= $query_string?>"
                        class="px-3 py-1 bg-white border rounded text-gray-600 hover:bg-gray-100">&laquo; หน้าแรก</a>
                    <a href="?page=<?= $page - 1?><?= $query_string?>"
                        class="px-3 py-1 bg-white border rounded text-gray-600 hover:bg-gray-100">&lt;</a>
                    <?php
    endif; ?>
                    <?php
    $start_p = max(1, $page - 3);
    $end_p = min($total_pages, $page + 3);
    for ($i = $start_p; $i <= $end_p; $i++):
        $active = ($i == $page) ? 'bg-indigo-600 text-white border-indigo-600 font-bold' : 'bg-white text-gray-600 hover:bg-gray-100';
?>
                    <a href="?page=<?= $i?><?= $query_string?>" class="px-3 py-1 border rounded <?= $active?>">
                        <?= $i?>
                    </a>
                    <?php
    endfor; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1?><?= $query_string?>"
                        class="px-3 py-1 bg-white border rounded text-gray-600 hover:bg-gray-100">&gt;</a>
                    <a href="?page=<?= $total_pages?><?= $query_string?>"
                        class="px-3 py-1 bg-white border rounded text-gray-600 hover:bg-gray-100">หน้าสุดท้าย
                        &raquo;</a>
                    <?php
    endif; ?>
                </div>
                <?php
endif; ?>
            </div>
        </div>

    </div>

    <div id="dataModal"
        class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div
            class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col transform transition-transform scale-100">
            <div class="flex justify-between items-center p-4 border-b bg-gray-50 rounded-t-xl">
                <h3 class="text-lg font-bold text-gray-800"><i class="fas fa-list-alt text-indigo-600"></i>
                    รายละเอียดการเปลี่ยนแปลงข้อมูล (Data Diff)</h3>
                <button onclick="document.getElementById('dataModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-red-500 transition"><i class="fas fa-times text-xl"></i></button>
            </div>

            <div class="p-4 border-b flex flex-wrap gap-4 items-center bg-white">
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 font-bold">ผู้ทำรายการ</div>
                        <div id="modal_action_user" class="text-sm font-bold text-gray-800"></div>
                    </div>
                </div>
                <div class="flex items-center gap-2 border-l pl-4">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700"><i
                            class="fas fa-tag"></i></div>
                    <div>
                        <div class="text-xs text-gray-500 font-bold">ประเภทรายการ</div>
                        <div id="modal_action_type" class="text-sm font-bold text-gray-800"></div>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-auto custom-scroll bg-gray-50 relative">
                <table class="w-full text-sm text-left border-collapse table-fixed break-words min-w-[600px]">
                    <thead class="bg-gray-200 text-gray-700 sticky top-0 shadow-sm z-10">
                        <tr>
                            <th class="p-3 border-b border-r w-1/4 font-bold text-center">หัวข้อ / ฟิลด์ข้อมูล</th>
                            <th class="p-3 border-b border-r w-[37.5%] font-bold text-center text-red-600 bg-red-50"><i
                                    class="fas fa-minus-circle"></i> ข้อมูลเดิม (Old Data)</th>
                            <th class="p-3 border-b w-[37.5%] font-bold text-center text-green-600 bg-green-50"><i
                                    class="fas fa-plus-circle"></i> ข้อมูลใหม่ (New Data)</th>
                        </tr>
                    </thead>
                    <tbody id="modal_diff_table" class="bg-white">
                        <!-- Dynamic Diff Table Rows -->
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t bg-gray-50 flex justify-end rounded-b-xl">
                <button onclick="document.getElementById('dataModal').classList.add('hidden')"
                    class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-bold shadow transition">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
        const mapOrgs = <?= json_encode($map_orgs ?? [], JSON_UNESCAPED_UNICODE) ?>;
        const mapPos = <?= json_encode($map_pos ?? [], JSON_UNESCAPED_UNICODE) ?>;
        const mapRanks = <?= json_encode($map_ranks ?? [], JSON_UNESCAPED_UNICODE) ?>;
        const mapTypes = <?= json_encode($map_types ?? [], JSON_UNESCAPED_UNICODE) ?>;

        const fieldTranslations = {
            'org_id': 'หน่วย/สังกัด (Organization)',
            'pos_id': 'ตำแหน่ง (Position)',
            'rank_id': 'ยศ (Rank)',
            'org_name': 'ชื่อหน่วยงาน (Org Name)',
            'position_name': 'ชื่อตำแหน่ง (Position Name)',
            'card_type_id': 'ประเภทบัตร (Card Type)',
            'first_name': 'ชื่อ (First Name)',
            'last_name': 'นามสกุล (Last Name)',
            'full_name': 'ชื่อ-สกุล (Full Name)',
            'id_card_number': 'เลขประจำตัวประชาชน',
            'status': 'สถานะ (Status)',
            'role': 'ระดับสิทธิ์ (Role)'
        };

        function translateKey(key) {
            return fieldTranslations[key] || key;
        }

        function translateValue(key, value) {
            if (value === null || value === '') return value;

            if (key === 'org_id' && mapOrgs[value]) return `${mapOrgs[value]} (ID: ${value})`;
            if (key === 'pos_id' && mapPos[value]) return `${mapPos[value]} (ID: ${value})`;
            if (key === 'rank_id' && mapRanks[value]) return `${mapRanks[value]} (ID: ${value})`;
            if (key === 'card_type_id' && mapTypes[value]) return `${mapTypes[value]} (ID: ${value})`;

            if (key === 'status') {
                const statusMap = {
                    'ACTIVE': 'ใช้งานปกติ (ACTIVE)',
                    'INACTIVE': 'ระงับการใช้งาน (INACTIVE)',
                    'PENDING': 'รอตรวจสอบ (PENDING)',
                    'COMPLETED': 'รับบัตรแล้ว (COMPLETED)',
                    'SENT_TO_PRINT': 'ส่งพิมพ์ (SENT_TO_PRINT)',
                    'READY_PICKUP': 'รอรับบัตร (READY_PICKUP)'
                };
                return statusMap[value] || value;
            }

            return value;
        }

        function escapeHTML(str) {
            return str.replace(/[&<>'"]/g,
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag] || tag)
            );
        }

        function formatValue(rawKey, valObj) {
            if (valObj === null || valObj === '') return '<span class="text-gray-400 italic">- ว่างเปล่า -</span>';

            let valStr = typeof valObj === 'object' ? JSON.stringify(valObj, null, 2) : String(valObj);

            // Format Address fields as readable Thai address
            if (rawKey === 'address_json' || rawKey === 'contact_address_json') {
                try {
                    let addr = typeof valObj === 'object' ? valObj : JSON.parse(valStr);
                    if (typeof addr === 'object' && addr !== null) {
                        let line1 = `บ้านเลขที่ ${escapeHTML(addr.house_no || '-')} หมู่ ${escapeHTML(addr.moo || '-')} ถนน ${escapeHTML(addr.road || '-')}`;
                        let line2 = `ตำบล${escapeHTML(addr.tambon || '-')} อำเภอ${escapeHTML(addr.amphoe || '-')}`;
                        let line3 = `จังหวัด${escapeHTML(addr.province || '-')} รหัสไปรษณีย์ ${escapeHTML(addr.zipcode || '-')}`;
                        return `<div class="text-sm leading-relaxed bg-blue-50 border border-blue-100 rounded-lg p-3 text-gray-700 space-y-0.5">
                                    <div><i class="fas fa-home text-blue-400 mr-1.5 text-xs"></i>${line1}</div>
                                    <div><i class="fas fa-map-marker-alt text-blue-400 mr-1.5 text-xs"></i>${line2}</div>
                                    <div><i class="fas fa-map text-blue-400 mr-1.5 text-xs"></i>${line3}</div>
                                </div>`;
                    }
                } catch (e) { /* fallback */ }
            }

            // Format other JSON fields as code block
            if (rawKey.includes('json') || typeof valObj === 'object') {
                try {
                    let parsed = typeof valObj === 'object' ? valObj : (valStr.trim().startsWith('{') || valStr.trim().startsWith('[') ? JSON.parse(valStr) : valStr);
                    let pretty = typeof parsed === 'object' ? JSON.stringify(parsed, null, 2) : parsed;
                    return `<div class="max-h-48 overflow-auto custom-scroll bg-gray-900 text-green-400 p-3 rounded-lg text-[11px] font-mono whitespace-pre text-left shadow-inner leading-relaxed">${escapeHTML(String(pretty))}</div>`;
                } catch (e) { /* fallback to normal text */ }
            }

            // Format Image and Signature Base64 / Paths
            if (['photo_path', 'signature_file', 'file_path', 'pic_path'].includes(rawKey)) {
                if (valStr.startsWith('data:image') || valStr.match(/\.(jpeg|jpg|gif|png|webp|svg)$/i)) {
                    let imgSrc = valStr.startsWith('data:image') ? escapeHTML(valStr) : `secure_image.php?f=${encodeURIComponent(valStr)}`;
                    return `<div class="flex flex-col gap-2 items-center lg:items-start bg-gray-50 p-2 rounded-lg border">
                                <a href="${imgSrc}" target="_blank" class="block w-full max-w-full bg-white rounded shadow-sm border p-1 transition hover:scale-[1.02]">
                                    <img src="${imgSrc}" class="h-20 w-full object-contain mx-auto" alt="Preview Image">
                                </a>
                                <div class="w-full truncate text-[9px] text-gray-400 cursor-help" title="${escapeHTML(valStr)}"><i class="fas fa-image mr-1 text-gray-300"></i>${escapeHTML(valStr.split('/').pop() || valStr)}</div>
                            </div>`;
                }
                return `<div class="w-full truncate text-xs bg-gray-100 p-2 rounded-lg border cursor-help" title="${escapeHTML(valStr)}"><i class="fas fa-file-alt text-gray-400 mr-1"></i> ${escapeHTML(valStr).substring(0, 45)}${valStr.length > 45 ? '...' : ''}</div>`;
            }

            // Fallback for long generic text bounds keeping
            if (valStr.length > 150) {
                return `<div class="max-h-32 overflow-auto custom-scroll text-[13px] whitespace-pre-wrap bg-gray-50 p-2.5 rounded-lg border leading-tight">${escapeHTML(valStr)}</div>`;
            }

            // Default span tag binding
            return `<span class="break-words">${escapeHTML(valStr)}</span>`;
        }

        function viewDataModal(btn) {
            document.getElementById('modal_action_user').textContent = btn.getAttribute('data-user') || 'ไม่ระบุ';
            const actionType = btn.getAttribute('data-type') || 'ไม่ระบุ';

            let badgeClass = 'bg-gray-100 text-gray-800';
            if (actionType.includes('UPDATE') || actionType.includes('SETTING') || actionType.includes('CHANGE')) badgeClass = 'bg-yellow-100 text-yellow-800 border border-yellow-200';
            else if (actionType.includes('CREATE') || actionType.includes('ADD')) badgeClass = 'bg-green-100 text-green-800 border border-green-200';
            else if (actionType.includes('DELETE') || actionType.includes('REVOKE')) badgeClass = 'bg-red-100 text-red-800 border border-red-200';
            else if (actionType.includes('VIEW')) badgeClass = 'bg-blue-100 text-blue-800 border border-blue-200';

            document.getElementById('modal_action_type').innerHTML = `<span class="px-2 py-1 rounded text-xs font-bold ${badgeClass}">${actionType}</span>`;

            let oldDataStr = btn.getAttribute('data-old');
            let newDataStr = btn.getAttribute('data-new');

            let oldData = {}; let newData = {};

            try { if (oldDataStr && oldDataStr !== 'null') oldData = JSON.parse(oldDataStr); } catch (e) { }
            try { if (newDataStr && newDataStr !== 'null') newData = JSON.parse(newDataStr); } catch (e) { }

            if (typeof oldData !== 'object' || oldData === null) oldData = { 'raw_data': oldDataStr };
            if (typeof newData !== 'object' || newData === null) newData = { 'raw_data': newDataStr };

            let allKeys = new Set([...Object.keys(oldData), ...Object.keys(newData)]);
            let tbody = document.getElementById('modal_diff_table');
            tbody.innerHTML = '';

            if (allKeys.size === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="p-8 text-center text-gray-500 font-bold bg-gray-50">ไม่มีการเปลี่ยนแปลงข้อมูล</td></tr>';
            } else {
                allKeys.forEach(rawKey => {
                    let oldRawVal = oldData[rawKey] !== undefined ? oldData[rawKey] : null;
                    let newRawVal = newData[rawKey] !== undefined ? newData[rawKey] : null;

                    let isChanged = (JSON.stringify(oldRawVal) !== JSON.stringify(newRawVal));

                    let oldVal = translateValue(rawKey, oldRawVal);
                    let newVal = translateValue(rawKey, newRawVal);

                    let tr = document.createElement('tr');
                    tr.className = 'border-b hover:bg-gray-50 transition duration-150';

                    let oldLabel = formatValue(rawKey, oldVal);
                    let newLabel = formatValue(rawKey, newVal);

                    let oldClass = (isChanged && oldRawVal !== null) ? 'bg-red-50/50 text-red-700' : 'text-gray-600';
                    let newClass = (isChanged && newRawVal !== null) ? 'bg-green-50/50 text-green-700' : 'text-gray-600';

                    let displayKey = translateKey(rawKey);

                    let tdKey = `<th class="p-4 border-r font-[600] text-gray-700 bg-gray-100/50 align-top w-1/4 break-words whitespace-pre-wrap">${escapeHTML(displayKey)}<br><span class="text-[10px] text-gray-400 font-normal">(${escapeHTML(rawKey)})</span></th>`;
                    let tdOld = `<td class="p-3 border-r align-top w-[37.5%] min-w-0 ${oldClass} overflow-hidden break-words"><div class="w-full">${oldLabel}</div></td>`;
                    let tdNew = `<td class="p-3 align-top w-[37.5%] min-w-0 ${newClass} overflow-hidden break-words"><div class="w-full">${newLabel}</div></td>`;

                    tr.innerHTML = tdKey + tdOld + tdNew;
                    tbody.appendChild(tr);
                });
            }
            document.getElementById('dataModal').classList.remove('hidden');
        }
    </script>
</body>

</html>