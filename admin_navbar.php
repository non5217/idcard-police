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

            <div class="flex items-center gap-4 md:gap-8">
                <!-- Mobile Hamburger -->
                <button onclick="toggleMobileMenu()" class="md:hidden text-gray-600 hover:text-blue-900 focus:outline-none p-2 rounded-lg hover:bg-gray-100 transition">
                    <i class="fas fa-bars text-xl"></i>
                </button>

                <a href="admin_dashboard.php" class="flex items-center gap-3">
                    <img src="assets/logo.png" class="h-10 w-auto drop-shadow-sm" alt="โลโก้ตำรวจ">
                    <div class="hidden sm:block">
                        <span class="text-xl font-bold text-blue-900 block leading-tight">ID Card Police</span>
                        <span class="text-xs text-gray-500 block">ระบบจัดการบัตรข้าราชการ</span>
                    </div>
                </a>

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
                    <span class="text-sm font-bold text-gray-700"><?= htmlspecialchars($admin_name)?></span>
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

    <!-- Mobile Sidebar / Drawer Overlay -->
    <div id="side-drawer-overlay" onclick="toggleMobileMenu()" class="fixed inset-0 bg-black bg-opacity-50 z-[60] hidden transition-opacity duration-300 opacity-0"></div>

    <!-- Mobile Sidebar / Drawer Content -->
    <div id="side-drawer" class="fixed top-0 left-0 bottom-0 w-72 bg-white z-[70] transform -translate-x-full transition-transform duration-300 ease-in-out shadow-2xl flex flex-col">
        <div class="p-6 bg-blue-900 text-white">
            <div class="flex items-center gap-3 mb-4">
                <img src="assets/logo.png" class="h-12 w-auto drop-shadow-sm" alt="โลโก้ตำรวจ">
                <div>
                    <span class="text-lg font-bold block leading-tight">Admin Menu</span>
                    <span class="text-xs text-blue-200 block">ID Card Police System</span>
                </div>
            </div>
            <div class="border-t border-blue-800 pt-4 mt-2">
                <div class="flex flex-col">
                    <span class="text-sm font-bold"><?= htmlspecialchars($admin_name)?></span>
                    <span class="text-xs text-green-300 flex items-center gap-1 mt-1">
                        <span class="w-2 h-2 bg-green-400 rounded-full"></span> ออนไลน์
                    </span>
                </div>
            </div>
        </div>

        <div class="flex-grow py-4 overflow-y-auto">
            <div class="px-3 space-y-1">
                <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-bold transition <?=($current_page == 'admin_dashboard.php' || $current_page == 'admin_edit.php')? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'?>">
                    <i class="fas fa-list-alt w-6"></i> รายการคำขอ
                </a>
                <a href="admin_settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-bold transition <?=($current_page == 'admin_settings.php')? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'?>">
                    <i class="fas fa-cog w-6"></i> ตั้งค่าระบบ
                </a>
                <a href="admin_manage_staff.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-bold transition <?=($current_page == 'admin_manage_staff.php')? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'?>">
                    <i class="fas fa-users-cog w-6"></i> จัดการสิทธิ์
                </a>
                <a href="admin_report.php" class="flex items-center gap-3 px-4 py-3 rounded-lg font-bold transition <?=($current_page == 'admin_report.php')? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100'?>">
                    <i class="fas fa-chart-bar w-6"></i> Logs & รายงานสถิติ
                </a>
                <div class="border-t border-gray-100 my-2"></div>
                <a href="../portal/" class="flex items-center gap-3 px-4 py-3 rounded-lg font-bold text-purple-600 hover:bg-purple-50 transition">
                    <i class="fas fa-external-link-alt w-6"></i> กลับ Portal กลาง
                </a>
            </div>
        </div>

        <div class="p-4 border-t border-gray-100">
            <a href="logout.php" onclick="return confirm('ต้องการออกจากระบบทำบัตร ใช่หรือไม่?');" class="flex items-center justify-center gap-2 w-full bg-red-50 text-red-600 py-3 rounded-lg font-bold hover:bg-red-100 transition">
                <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
            </a>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            const drawer = document.getElementById('side-drawer');
            const overlay = document.getElementById('side-drawer-overlay');
            
            if (drawer.classList.contains('-translate-x-full')) {
                // Open
                drawer.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
                document.body.style.overflow = 'hidden'; // Prevent scroll
            } else {
                // Close
                drawer.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => {
                    overlay.classList.add('hidden');
                    document.body.style.overflow = ''; // Restore scroll
                }, 300);
            }
        }
        
        // Close menu on resize if screen becomes larger
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                const drawer = document.getElementById('side-drawer');
                if (!drawer.classList.contains('-translate-x-full')) {
                    toggleMobileMenu();
                }
            }
        });
    </script>
</nav>