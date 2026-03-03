<?php
// idcard/admin_navbar.php

// ป้องกัน session_start ซ้ำ
if (session_status() === PHP_SESSION_NONE) {
// session_start(); 
}

$admin_name = $_SESSION['fullname'] ?? 'Admin System';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="bg-white shadow border-b border-gray-200 mb-6 sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center h-16">

            <div class="flex items-center gap-8">
                <div class="flex items-center gap-3">
                    <img src="assets/logo.png" class="h-10 w-auto drop-shadow-sm" alt="โลโก้ตำรวจ">
                    <div>
                        <span class="text-xl font-bold text-blue-900 block leading-tight">ID Card Police</span>
                        <span class="text-xs text-gray-500 block">ระบบจัดการบัตรข้าราชการ</span>
                    </div>
                </div>

                <div class="hidden md:flex items-center gap-2">

                    <a href="admin_dashboard.php"
                        class="px-3 py-2 rounded-md font-bold transition <?=($current_page == 'admin_dashboard.php' || $current_page == 'admin_edit.php') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'?>">
                        <i class="fas fa-list-alt mr-1"></i> รายการคำขอ
                    </a>

                    <a href="admin_settings.php"
                        class="px-3 py-2 rounded-md font-bold transition <?=($current_page == 'admin_settings.php') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'?>">
                        <i class="fas fa-cog mr-1"></i> ตั้งค่าระบบ
                    </a>
                    <a href="admin_manage_staff.php"
                        class="px-3 py-2 rounded-md font-bold transition <?=($current_page == 'admin_manage_staff.php') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'?>">
                        <i class="fas fa-users-cog mr-1"></i> จัดการสิทธิ์
                    </a>
                    <a href="admin_report.php"
                        class="px-3 py-2 rounded-md font-bold transition <?=($current_page == 'admin_report.php') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'?>">
                        <i class="fas fa-chart-bar mr-1"></i> Logs & รายงานสถิติ
                    </a>
                    <a href="../portal/"
                        class="px-3 py-2 rounded-md font-bold text-purple-600 hover:bg-purple-50 transition border border-transparent hover:border-purple-200">
                        <i class="fas fa-cogs mr-1"></i> Portal กลาง
                    </a>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="hidden md:flex flex-col items-end">
                    <span class="text-sm font-bold text-gray-700">
                        <?= htmlspecialchars($admin_name)?>
                    </span>
                    <span class="text-xs text-green-600 flex items-center gap-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> ออนไลน์
                    </span>
                </div>

                <div class="h-8 w-px bg-gray-300 hidden md:block"></div>

                <a href="logout.php" onclick="return confirm('ต้องการออกจากระบบทำบัตร ใช่หรือไม่?');"
                    class="flex items-center gap-2 text-gray-600 hover:text-red-600 hover:bg-red-50 px-3 py-2 rounded-md transition font-medium text-sm">
                    <i class="fas fa-sign-out-alt text-lg"></i>
                    <span class="hidden md:inline">ออกจากระบบ</span>
                </a>
            </div>

        </div>
    </div>
</nav>