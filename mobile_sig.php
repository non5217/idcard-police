<?php
// idcard/mobile_sig.php
require_once 'connect.php';

$sig_id = $_GET['sid'] ?? '';
if (empty($sig_id)) {
    die("Error: Missing signature ID.");
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ลงลายมือชื่อ - Police ID Card</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .main-container {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            min-height: 100vh;
        }

        #signature-pad {
            background-color: white;
            width: 100%;
            height: 100%;
            cursor: crosshair;
            touch-action: none;
            /* Lock touch only on the canvas */
        }

        .canvas-wrapper {
            width: 100%;
            aspect-ratio: 2 / 1;
            border: 3px dashed #cbd5e0;
            border-radius: 1.25rem;
            background-color: white;
            overflow: hidden;
            position: relative;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        .checkerboard-bg {
            background-image: linear-gradient(45deg, #f7fafc 25%, transparent 25%), linear-gradient(-45deg, #f7fafc 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #f7fafc 75%), linear-gradient(-45deg, transparent 75%, #f7fafc 75%);
            background-size: 24px 24px;
            background-position: 0 0, 0 12px, 12px -12px, -12px 0px;
        }

        .btn-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>

<body class="checkerboard-bg">
    <div class="main-container">
        <div class="mb-4 text-center">
            <div class="inline-block p-3 bg-white rounded-2xl shadow-sm mb-2">
                <i class="fas fa-signature text-2xl text-blue-600"></i>
            </div>
            <h1 class="text-xl font-bold text-gray-800">ลงลายมือชื่อ</h1>
            <p class="text-gray-500 text-xs mt-1">กรุณาวางมือถือแนวนอนเพื่อพื้นที่การเซ็นที่กว้างขึ้น<br>หากเซ็นเสร็จแล้วเลื่อนลงด้านล่างเพื่อกดบันทึก</p>
        </div>

        <div class="flex-grow flex items-center justify-center py-4">
            <div class="canvas-wrapper">
                <canvas id="signature-pad"></canvas>

                <!-- Floating Buttons -->
                <div class="absolute bottom-4 left-4 right-4 flex justify-between items-center pointer-events-none">
                    <button id="clear-btn"
                        class="pointer-events-auto bg-white/95 backdrop-blur border border-gray-200 text-gray-600 px-5 py-3 rounded-2xl text-sm font-bold shadow-lg active:scale-95 transition-transform flex items-center">
                        <i class="fas fa-redo-alt mr-2"></i> ล้าง
                    </button>
                    <button id="save-btn"
                        class="pointer-events-auto bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-4 rounded-2xl shadow-xl text-lg transition-all active:scale-[0.98] flex items-center">
                        <i class="fas fa-check-circle mr-2"></i> ส่งลายเซ็น
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-2 pb-6 text-center text-gray-400 text-[10px]">
            &copy; 2026 Police ID Card System
        </div>
    </div>

    <script>
        const canvas = document.getElementById('signature-pad');
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 0)',
            penColor: 'rgb(0, 0, 0)',
            minWidth: 2.5,
            maxWidth: 6
        });

        function resizeCanvas() {
            // Capture existing signature data before resizing
            const existingData = !signaturePad.isEmpty() ? signaturePad.toData() : null;

            const wrapper = canvas.parentElement;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);

            // Set canvas size based on wrapper width, keeping 2:1 ratio
            const width = wrapper.offsetWidth;
            const height = width / 2;

            canvas.width = width * ratio;
            canvas.height = height * ratio;
            canvas.getContext("2d").scale(ratio, ratio);

            // Restore signature data if it existed
            if (existingData) {
                signaturePad.fromData(existingData);
            } else {
                signaturePad.clear();
            }
        }

        window.addEventListener("resize", resizeCanvas);
        window.addEventListener("orientationchange", () => setTimeout(resizeCanvas, 200));
        resizeCanvas();

        document.getElementById('clear-btn').addEventListener('click', () => {
            signaturePad.clear();
        });

        document.getElementById('save-btn').addEventListener('click', async () => {
            if (signaturePad.isEmpty()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ยังไม่ลงชื่อ',
                    text: 'กรุณาเซ็นชื่อในช่องว่างก่อนกดบันทึกนะครับ',
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }

            // Export at 500x250 regardless of screen size to match desktop form
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = 500;
            tempCanvas.height = 250;
            const tempCtx = tempCanvas.getContext('2d');
            
            // Draw signature onto the 500x250 canvas
            tempCtx.drawImage(canvas, 0, 0, 500, 250);
            const sigData = tempCanvas.toDataURL('image/png');
            
            const sigId = '<?= $sig_id ?>';

            Swal.fire({
                title: 'กำลังส่งข้อมูล...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const formData = new FormData();
                formData.append('sig_id', sigId);
                formData.append('sig_data', sigData);

                const response = await fetch('api/save_mobile_sig.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: 'บันทึกลายเซ็นเรียบร้อยแล้ว คุณสามารถปิดหน้าต่างนี้และกลับไปที่หน้าจอคอมพิวเตอร์ได้ครับ',
                        confirmButtonText: 'ปิดหน้าต่าง',
                        allowOutsideClick: false
                    }).then(() => {
                        window.close();
                        // For mobile browsers that don't allow window.close()
                        document.body.innerHTML = `
                            <div class="h-screen flex flex-col items-center justify-center p-8 text-center bg-white">
                                <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                                <h1 class="text-2xl font-bold text-gray-800">บันทึกสำเร็จ</h1>
                                <p class="text-gray-600 mt-2">ลายเซ็นของคุณถูกส่งไปยังคอมพิวเตอร์แล้ว กรุณากลับไปดำเนินการต่อที่หน้าจอคอมพิวเตอร์ครับ</p>
                            </div>
                        `;
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message || 'ไม่สามารถบันทึกได้' });
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'ขออภัย', text: 'เกิดข้อผิดพลาดในการเชื่อมต่อกรุณาลองใหม่อีกครั้ง' });
            }
        });
    </script>
</body>

</html>