<?php
// idcard/navbar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = isset($_SESSION['role']) && ($_SESSION['role'] === 'Super_Admin' || $_SESSION['role'] === 'Approver');
?>
<nav class="bg-gray-900 text-white shadow-lg no-print">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center gap-3">
                <img src="assets/logo.png" class="h-8 w-auto mr-1" alt="โลโก้ตำรวจ">
                <a href="index.php" class="text-xl font-bold tracking-wider hover:text-blue-300">
                    Police ID Card
                </a>
            </div>

            <div class="hidden md:flex items-center gap-6">
                <a href="index.php" class="<?= $current_page == 'index.php' ? 'text-blue-400 font-bold' : 'text-gray-300 hover:text-white' ?>">
                    หน้าหลัก
                </a>
                

                <?php if ($is_admin): ?>
                    <div class="h-6 w-px bg-gray-700 mx-2"></div> <a href="admin_dashboard.php" class="<?= $current_page == 'admin_dashboard.php' ? 'text-yellow-400 font-bold' : 'text-yellow-200 hover:text-white' ?>">
                        <i class="fas fa-user-shield"></i> ระบบเจ้าหน้าที่
                    </a>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <div class="text-sm font-semibold"><?= htmlspecialchars($_SESSION['fullname'] ?? 'ผู้ใช้งาน') ?></div>
                    <div class="text-xs text-gray-400"><?= $_SESSION['role'] ?? 'User' ?></div>
                </div>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm transition shadow-md">
                    ออกจากระบบ
                </a>
            </div>
        </div>
    </div>
</nav>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
