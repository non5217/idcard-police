<?php
// idcard/admin_manage_staff.php
require_once 'connect.php';
require_once 'admin_auth.php'; // 🔒 ตรวจสอบการล็อกอิน

// 🛑 ตรวจสอบสิทธิ์ (อนุญาตเฉพาะ Super_Admin และ Admin เท่านั้น)
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['Super_Admin', 'Admin'])) {
    die("
        <div style='text-align:center; padding: 50px; font-family: sans-serif;'>
            <h1 style='color: red;'>⛔ Access Denied</h1>
            <p>เฉพาะ Super_Admin หรือ Admin เท่านั้นที่สามารถจัดการสิทธิ์ผู้ใช้งานได้</p>
            <a href='admin_dashboard.php'>กลับหน้าหลัก</a>
        </div>
    ");
}

// 🟢 1. บันทึก Log เมื่อมีการเปิดดูหน้าจัดการสิทธิ์ (ทำเฉพาะตอนที่เป็น GET Request)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    saveLog($conn, 'VIEW_MANAGE_STAFF', 'เปิดดูหน้าจัดการสิทธิ์เจ้าหน้าที่');
}

$current_admin_id = $_SESSION['user_id'];
$current_admin_role = $_SESSION['role']; // เก็บสิทธิ์ของคนที่กำลังใช้งาน
$message = '';

// ==========================================
// 1. จัดการคำสั่งบันทึก / ลบสิทธิ์ (POST Request)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 🟢 ตรวจสอบ CSRF Token ก่อนให้สิทธิ์
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("<script>alert('⛔ Security Error: ตรวจพบความพยายามเจาะระบบ (CSRF)'); window.history.back();</script>");
    }    
    $action = $_POST['action'] ?? '';
    $target_user_id = $_POST['target_user_id'] ?? 0;

    if ($target_user_id > 0) {
        
        // 🛡️ เช็คสิทธิ์ปัจจุบันของเป้าหมายก่อนทำการแก้ไขใดๆ
        $stmt_check_target = $conn->prepare("SELECT role FROM idcard_staff_roles WHERE console_user_id = ?");
        $stmt_check_target->execute([$target_user_id]);
        $target_data = $stmt_check_target->fetch(PDO::FETCH_ASSOC);
        $target_current_role = $target_data ? $target_data['role'] : null;

        // 🛑 กฎเหล็ก: ถ้าคนทำรายการเป็นแค่ Admin ห้ามแตะต้อง Super_Admin เด็ดขาด
        if ($current_admin_role === 'Admin' && $target_current_role === 'Super_Admin') {
            $message = "❌ ปฏิเสธการเข้าถึง: คุณไม่มีสิทธิ์แก้ไขข้อมูลของ Super Admin!";
        } 
        // 🛑 ป้องกันการลบสิทธิ์ตัวเอง
        elseif ($target_user_id == $current_admin_id) {
            $message = "❌ ไม่สามารถแก้ไขหรือปลดสิทธิ์ตัวเองผ่านหน้านี้ได้!";
        } 
        else {
            // ดำเนินการตามปกติหากผ่านเงื่อนไขการตรวจสอบ
            if ($action === 'assign_role') {
                $new_role = $_POST['role'];
                
                // 🛑 ป้องกัน Admin แอบส่งค่า POST เพื่อตั้งคนอื่นเป็น Super_Admin
                if ($current_admin_role === 'Admin' && $new_role === 'Super_Admin') {
                    $message = "❌ ปฏิเสธการเข้าถึง: Admin ไม่สามารถแต่งตั้ง Super Admin ได้!";
                } else {
                    if ($target_data) {
                        // ถ้ามีอยู่แล้วให้ Update
                        $stmt = $conn->prepare("UPDATE idcard_staff_roles SET role = ?, assigned_by = ? WHERE console_user_id = ?");
                        $stmt->execute([$new_role, $current_admin_id, $target_user_id]);
                        
                        // 🟢 2. บันทึก Log การเปลี่ยนสิทธิ์ (เก็บทั้งสิทธิ์เก่าและสิทธิ์ใหม่)
                        saveLog($conn, 'UPDATE_ROLE', "ปรับเปลี่ยนสิทธิ์ผู้ใช้ ID: $target_user_id เป็น $new_role", $target_user_id, ['role' => $target_current_role], ['role' => $new_role]);
                        
                        $message = "อัปเดตสิทธิ์เรียบร้อยแล้ว!";
                    } else {
                        // ถ้ายังไม่มีให้ Insert ใหม่
                        $stmt = $conn->prepare("INSERT INTO idcard_staff_roles (console_user_id, role, assigned_by, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$target_user_id, $new_role, $current_admin_id]);
                        
                        // 🟢 3. บันทึก Log การแต่งตั้งสิทธิ์ใหม่
                        saveLog($conn, 'ASSIGN_ROLE', "แต่งตั้งเจ้าหน้าที่ใหม่ ID: $target_user_id ให้เป็น $new_role", $target_user_id, null, ['role' => $new_role]);
                        
                        $message = "แต่งตั้งเจ้าหน้าที่ใหม่เรียบร้อยแล้ว!";
                    }
                }
            } elseif ($action === 'revoke_role') {
                $stmt = $conn->prepare("DELETE FROM idcard_staff_roles WHERE console_user_id = ?");
                $stmt->execute([$target_user_id]);
                
                // 🟢 4. บันทึก Log การปลดสิทธิ์ (เก็บข้อมูลสิทธิ์เก่าเอาไว้เป็นหลักฐาน)
                saveLog($conn, 'REVOKE_ROLE', "ปลดสิทธิ์เจ้าหน้าที่ ID: $target_user_id (สิทธิ์เดิม: $target_current_role)", $target_user_id, ['role' => $target_current_role], null);
                
                $message = "ปลดสิทธิ์เจ้าหน้าที่เรียบร้อยแล้ว!";
            }
        }
    }
}

// ==========================================
// 2. ดึงข้อมูลมาแสดงผล (GET Request)
// ==========================================

// ค้นหาผู้ใช้งานจากระบบกลาง (central_users)
$search_query = trim($_GET['search'] ?? '');
$search_results = [];
if (!empty($search_query)) {
    $stmt_search = $conn->prepare("
        SELECT id, username, CONCAT(IFNULL(rank, ''), first_name, ' ', last_name) AS full_name 
        FROM central_users 
        WHERE username LIKE ? OR first_name LIKE ? OR last_name LIKE ?
        LIMIT 20
    ");
    $stmt_search->execute(["%$search_query%", "%$search_query%", "%$search_query%"]);
    $search_results = $stmt_search->fetchAll(PDO::FETCH_ASSOC);
}

// ดึงรายชื่อเจ้าหน้าที่ทั้งหมดที่มีสิทธิ์ในระบบปัจจุบัน
$stmt_staff = $conn->query("
    SELECT r.console_user_id, r.role, r.created_at, c.username, 
           CONCAT(IFNULL(c.rank, ''), c.first_name, ' ', c.last_name) AS full_name
    FROM idcard_staff_roles r
    LEFT JOIN central_users c ON r.console_user_id = c.id
    ORDER BY 
        CASE r.role 
            WHEN 'Super_Admin' THEN 1 
            WHEN 'Admin' THEN 2 
            WHEN 'Staff' THEN 3 
            ELSE 4 
        END ASC, 
        r.created_at DESC
");
$staff_list = $stmt_staff->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการสิทธิ์เจ้าหน้าที่</title>
    <link rel="icon" type="image/png" href="https://portal.pathumthani.police.go.th/assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Sarabun', sans-serif; } </style>
</head>
<body class="bg-gray-100 pb-20">

    <?php include 'admin_navbar.php'; ?>

    <div class="container mx-auto mt-8 p-4 max-w-6xl">
        
        <?php if($message): ?>
            <div class="<?= strpos($message, '❌') !== false ? 'bg-red-100 border-red-500 text-red-700' : 'bg-green-100 border-green-500 text-green-700' ?> border-l-4 p-4 mb-6" role="alert">
                <p class="font-bold">แจ้งเตือนระบบ</p>
                <p><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-6">
            <h2 class="text-3xl font-bold text-gray-800"><i class="fas fa-users-cog text-blue-700"></i> จัดการสิทธิ์เจ้าหน้าที่ (Manage Roles)</h2>
            <a href="admin_dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded shadow"><i class="fas fa-arrow-left"></i> กลับหน้า Dashboard</a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-1 bg-white rounded-lg shadow-lg p-6 border-t-4 border-blue-500">
                <h3 class="text-xl font-bold mb-4 text-gray-700"><i class="fas fa-search"></i> ค้นหาผู้ใช้เพื่อแต่งตั้ง</h3>
                
                <form action="admin_manage_staff.php" method="GET" class="mb-6">
                    <label class="block text-sm text-gray-600 mb-1">ค้นหาจาก Username หรือ ชื่อ</label>
                    <div class="flex">
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" class="w-full border p-2 rounded-l bg-gray-50 outline-none focus:border-blue-500" placeholder="พิมพ์ชื่อ...">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-r hover:bg-blue-700"><i class="fas fa-search"></i></button>
                    </div>
                </form>

                <?php if (!empty($search_query)): ?>
                    <div class="bg-gray-50 p-4 rounded border">
                        <h4 class="font-bold mb-2 text-sm text-gray-600">ผลการค้นหา (<?= count($search_results) ?> รายการ)</h4>
                        <?php if (count($search_results) > 0): ?>
                            <ul class="space-y-3">
                                <?php foreach ($search_results as $u): ?>
                                    <li class="bg-white p-3 rounded shadow-sm border text-sm flex flex-col gap-2">
                                        <div>
                                            <span class="font-bold text-blue-800"><?= htmlspecialchars($u['full_name'] ?? 'ไม่มีชื่อ') ?></span><br>
                                            <span class="text-xs text-gray-500">ID: <?= $u['id'] ?> | User: <?= htmlspecialchars($u['username'] ?? '-') ?></span>
                                        </div>
                                        
                                        <form action="admin_manage_staff.php" method="POST" class="flex gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="assign_role">
                                            <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                            <select name="role" class="border rounded p-1 text-xs w-full bg-blue-50">
                                                <option value="Staff">Staff (เจ้าหน้าที่)</option>
                                                <option value="Admin">Admin (ผู้ดูแล)</option>
                                                <?php if ($current_admin_role === 'Super_Admin'): ?>
                                                    <option value="Super_Admin">Super Admin</option>
                                                <?php endif; ?>
                                            </select>
                                            <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700 whitespace-nowrap"><i class="fas fa-plus"></i> เพิ่ม</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-sm text-red-500">ไม่พบข้อมูลผู้ใช้งาน</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-2 bg-white rounded-lg shadow-lg p-6 border-t-4 border-green-500">
                <h3 class="text-xl font-bold mb-4 text-gray-700"><i class="fas fa-user-shield"></i> รายชื่อเจ้าหน้าที่ในระบบปัจจุบัน</h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border">
                        <thead class="bg-gray-200 text-gray-700">
                            <tr>
                                <th class="py-3 px-4 text-left font-bold border-b">ID</th>
                                <th class="py-3 px-4 text-left font-bold border-b">ชื่อ-สกุล (Username)</th>
                                <th class="py-3 px-4 text-center font-bold border-b">สิทธิ์ (Role)</th>
                                <th class="py-3 px-4 text-center font-bold border-b">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_list as $staff): 
                                $is_me = ($staff['console_user_id'] == $current_admin_id);
                                // 🛡️ ตรรกะ: Admin ธรรมดา ไม่มีสิทธิ์แก้ไข Super_Admin
                                $can_edit = true;
                                if ($current_admin_role === 'Admin' && $staff['role'] === 'Super_Admin') {
                                    $can_edit = false;
                                }
                            ?>
                                <tr class="border-b hover:bg-gray-50 <?= $is_me ? 'bg-blue-50' : '' ?>">
                                    <td class="py-3 px-4 text-sm"><?= $staff['console_user_id'] ?></td>
                                    <td class="py-3 px-4">
                                        <p class="font-bold text-gray-800"><?= htmlspecialchars($staff['full_name'] ?? 'Unknown') ?> <?= $is_me ? '<span class="text-blue-500 text-xs">(คุณ)</span>' : '' ?></p>
                                        <p class="text-xs text-gray-500">User: <?= htmlspecialchars($staff['username'] ?? '-') ?></p>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <?php 
                                            $role_color = 'bg-gray-200 text-gray-800';
                                            if ($staff['role'] == 'Super_Admin') $role_color = 'bg-red-100 text-red-800 border border-red-200';
                                            if ($staff['role'] == 'Admin') $role_color = 'bg-purple-100 text-purple-800 border border-purple-200';
                                            if ($staff['role'] == 'Staff') $role_color = 'bg-green-100 text-green-800 border border-green-200';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold <?= $role_color ?>">
                                            <?= $staff['role'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <?php if ($is_me): ?>
                                            <span class="text-xs text-blue-500 font-bold italic">ผู้ใช้งานปัจจุบัน</span>
                                        <?php elseif (!$can_edit): ?>
                                            <span class="text-xs text-red-400 italic">ไม่มีสิทธิ์จัดการ</span>
                                        <?php else: ?>
                                            <div class="flex justify-center gap-2">
                                                <form action="admin_manage_staff.php" method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="action" value="assign_role">
                                                    <input type="hidden" name="target_user_id" value="<?= $staff['console_user_id'] ?>">
                                                    <select name="role" class="border rounded px-2 py-1 text-xs" onchange="this.form.submit()">
                                                        <option value="Staff" <?= $staff['role']=='Staff'?'selected':'' ?>>Staff</option>
                                                        <option value="Admin" <?= $staff['role']=='Admin'?'selected':'' ?>>Admin</option>
                                                        <?php if ($current_admin_role === 'Super_Admin'): ?>
                                                            <option value="Super_Admin" <?= $staff['role']=='Super_Admin'?'selected':'' ?>>Super Admin</option>
                                                        <?php endif; ?>
                                                    </select>
                                                </form>
                                                
                                                <form action="admin_manage_staff.php" method="POST" class="inline" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะปลดสิทธิ์เจ้าหน้าที่ท่านนี้?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="action" value="revoke_role">
                                                    <input type="hidden" name="target_user_id" value="<?= $staff['console_user_id'] ?>">
                                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded text-xs hover:bg-red-600" title="ปลดสิทธิ์"><i class="fas fa-trash-alt"></i></button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

</body>
</html>
