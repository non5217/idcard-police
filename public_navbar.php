<?php
// idcard/public_navbar.php
if (session_status() === PHP_SESSION_NONE) {
    // session_start(); 
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="bg-blue-900 text-white shadow-lg sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            
            <a href="index.php" class="flex items-center gap-3 group">
                <img src="assets/logo.png" class="h-10 md:h-12 w-auto transform group-hover:scale-110 transition duration-300 drop-shadow-md" alt="โลโก้ตำรวจ" onerror="this.style.display='none'">
                <div>
                    <h1 class="text-base md:text-xl font-bold leading-tight group-hover:text-yellow-300 transition">ระบบทำบัตรประจำตัวข้าราชการ <span class="hidden md:inline">| HR-ID Card</span></h1>
                    <span class="text-[10px] md:text-xs text-blue-200 block">ตำรวจภูธรจังหวัดปทุมธานี</span>
                </div>
            </a>

            <div class="flex items-center gap-4">
                <a href="/portal" class="hidden md:flex items-center gap-1 hover:text-yellow-300 transition <?= $current_page=='index.php'?'text-yellow-300 font-bold':'' ?>">
                    <i class="fas fa-home"></i> หน้าหลัก
                </a>
                
                <?php if (isset($_SESSION['public_access'])): ?>
                    <a href="index.php?clear=1" onclick="return confirm('ข้อมูลที่กำลังทำรายการจะหายไป ต้องการยกเลิกและกลับหน้าหลักหรือไม่?');" 
                       class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-full text-[12px] md:text-sm font-bold transition flex items-center gap-1.5 md:gap-2 shadow-md">
                        <i class="fas fa-sign-out-alt"></i> <span class="hidden md:inline">ยกเลิก / เริ่มใหม่</span><span class="md:hidden">ออก</span>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="bg-blue-800 hover:bg-blue-700 border border-blue-600 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-full text-[12px] md:text-sm font-bold transition flex items-center gap-1.5 md:gap-2 shadow-md">
                        <i class="fas fa-user-shield"></i> <span class="hidden md:inline">สำหรับเจ้าหน้าที่</span><span class="md:hidden">เข้าสู่ระบบ</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
