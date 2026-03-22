<?php
// idcard/link_line.php
require_once 'connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// หน้าสำหรับผูกบัญชี LINE (LIFF)
// ขั้นตอน: 1. เปิดผ่านหน้า LIFF 2. ดึง userId 3. บันทึกลงตาราง idcard_requests

$req_id = $_GET['req_id'] ?? '';
$id_card = $_SESSION['id_card_public'] ?? '';

if (!$id_card) {
    die("กรุณาเข้าสู่ระบบติดตามสถานะก่อนทำการผูกบัญชี LINE");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เชื่อมต่อ LINE - Police ID Card</title>
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Sarabun', sans-serif; }</style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center">
        <div id="loading" class="space-y-4">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
            <p class="text-gray-600">กำลังเชื่อมต่อกับ LINE...</p>
        </div>

        <div id="error" class="hidden space-y-4">
            <div class="text-red-500 text-5xl mb-4"><i class="fas fa-exclamation-circle"></i></div>
            <h2 class="text-xl font-bold text-gray-800">เกิดข้อผิดพลาด</h2>
            <p id="errorMsg" class="text-gray-600"></p>
            <button onclick="liff.closeWindow()" class="bg-gray-200 text-gray-800 px-6 py-2 rounded-lg font-bold">ปิดหน้านี้</button>
        </div>

        <div id="success" class="hidden space-y-4">
            <div class="text-green-500 text-5xl mb-4"><i class="fas fa-check-circle"></i></div>
            <h2 class="text-xl font-bold text-gray-800">เชื่อมต่อสำเร็จ!</h2>
            <p class="text-gray-600">คุณจะได้รับการแจ้งเตือนสถานะทำบัตรผ่านทาง LINE เมื่อมีการเปลี่ยนแปลง</p>
            <button onclick="liff.closeWindow()" class="bg-green-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg">กลับไปที่ LINE</button>
        </div>
    </div>

    <script>
        const LIFF_ID = ''; // คุณ @USER ต้องนำ LIFF ID มาใส่ที่นี่

        async function main() {
            if (!LIFF_ID) {
                showError("ยังไม่ได้ตั้งค่า LIFF ID กรุณาติดต่อผู้ดูแลระบบ");
                return;
            }

            try {
                await liff.init({ liffId: LIFF_ID });
                if (!liff.isLoggedIn()) {
                    liff.login();
                    return;
                }

                const profile = await liff.getProfile();
                const userId = profile.userId;

                // ส่งไปบันทึกที่ Server
                const response = await fetch('link_line_save.php', {
                    method: 'POST',
                    headers: { 'Content-Type: application/x-www-form-urlencoded' },
                    body: `userId=${userId}&req_id=<?= $req_id ?>`
                });

                const result = await response.json();
                if (result.success) {
                    document.getElementById('loading').classList.add('hidden');
                    document.getElementById('success').classList.remove('hidden');
                } else {
                    showError(result.message || "ไม่สามารถบันทึกข้อมูลได้");
                }

            } catch (err) {
                showError("LIFF Initialization failed: " + err);
            }
        }

        function showError(msg) {
            document.getElementById('loading').classList.add('hidden');
            document.getElementById('error').classList.remove('hidden');
            document.getElementById('errorMsg').innerText = msg;
        }

        main();
    </script>
</body>
</html>
