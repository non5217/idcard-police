<?php
// idcard/notifications.php
require_once 'connect.php';

/**
 * ส่งการแจ้งเตือนเมื่อมีการยื่นคำขอใหม่
 */
function sendIDCardNotification($conn, $requestId) {
    // 1. ดึงข้อมูลคำขอ
    $sql = "SELECT r.*, k.rank_name, o.org_name, t.type_name 
            FROM idcard_requests r
            LEFT JOIN idcard_ranks k ON r.rank_id = k.id
            LEFT JOIN idcard_organizations o ON r.org_id = o.id
            LEFT JOIN idcard_card_types t ON r.card_type_id = t.id
            WHERE r.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$req) return;

    // 2. ดึงค่าการตั้งค่าจากฐานข้อมูล
    $nt_settings = $conn->query("SELECT setting_key, setting_value FROM idcard_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $discord_url = $nt_settings['discord_webhook_url'] ?? '';
    
    // LINE Settings
    $line_channel_token = $nt_settings['line_channel_access_token'] ?? '';
    $line_user_id = $nt_settings['line_user_id'] ?? '';
    $line_msg_count = (int)($nt_settings['line_msg_count'] ?? 0);
    $line_last_reset = $nt_settings['line_last_reset'] ?? '';

    $tg_bot_token = $nt_settings['telegram_bot_token'] ?? '';
    $tg_chat_id = $nt_settings['telegram_chat_id'] ?? '';

    // 🟢 ระบบ Reset โควต้า LINE รายเดือน (ทุกวันที่ 1)
    $current_month = date('Y-m');
    if ($line_last_reset !== $current_month) {
        $conn->prepare("UPDATE idcard_settings SET setting_value = ? WHERE setting_key = 'line_msg_count'")->execute(['0']);
        $conn->prepare("UPDATE idcard_settings SET setting_value = ? WHERE setting_key = 'line_last_reset'")->execute([$current_month]);
        $line_msg_count = 0;
    }

    // 3. เตรียมข้อความ
    $rank_name = $req['rank_name'] ?? '';
    $full_name = $req['full_name'];
    $position = $req['position'] ?? '-';
    $org_name = $req['org_name'] ?? '-';
    $phone = $req['phone'] ?? '-';
    $id_card = $req['id_card_number'];
    $card_type = $req['type_name'] ?? '-';

    $message = "🔔 **มีรายการยื่นคำขอทำบัตรใหม่เข้ามา!**\n";
    $message .= "----------------------------------\n";
    $message .= "👤 ยศ/ชื่อ/สกุล: {$rank_name}{$full_name}\n";
    $message .= "🆔 เลขบัตร ปชช.: {$id_card}\n";
    $message .= "📍 ตำแหน่ง: {$position}\n";
    $message .= "🏢 สังกัด: {$org_name}\n";
    $message .= "📞 เบอร์โทร: {$phone}\n";
    $message .= "💳 ประเภทบัตร: {$card_type}\n";
    $message .= "----------------------------------\n";
    $message .= "🔗 จัดการคำขอ: " . (isset($_SERVER['HTTP_HOST']) ? "https://" . $_SERVER['HTTP_HOST'] : "") . "/idcard/admin_edit.php?id={$requestId}";

    // --- 🟢 ส่ง Discord ---
    if (!empty($discord_url)) {
        $data = ["content" => $message];
        sendCurl($discord_url, json_encode($data), ["Content-Type: application/json"]);
    }

    // --- 🟢 ส่ง LINE Messaging API ---
    if (!empty($line_channel_token) && !empty($line_user_id) && $line_msg_count < 300) {
        $line_url = "https://api.line.me/v2/bot/message/push";
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $manage_url = $protocol . $host . "/idcard/admin_edit.php?id={$requestId}";
        
        // --- 🎨 สร้าง Flex Message JSON (Card) ---
        $flex_json = [
            'type' => 'bubble',
            'header' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '🔔 มีรายการยื่นคำขอใหม่',
                        'weight' => 'bold',
                        'size' => 'lg',
                        'color' => '#ffffff'
                    ]
                ],
                'backgroundColor' => '#ef4444'
            ],
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'md',
                'contents' => [
                    [
                        'type' => 'box',
                        'layout' => 'vertical',
                        'spacing' => 'sm',
                        'contents' => [
                            ['type' => 'text', 'text' => "👤 {$rank_name}{$full_name}", 'weight' => 'bold', 'size' => 'md', 'wrap' => true],
                            ['type' => 'separator', 'margin' => 'md'],
                            [
                                'type' => 'box',
                                'layout' => 'horizontal', // เปลี่ยนจาก grid เป็น horizontal
                                'margin' => 'md',
                                'spacing' => 'sm',
                                'contents' => [
                                    ['type' => 'text', 'text' => 'เลขบัตร:', 'size' => 'xs', 'color' => '#aaaaaa', 'flex' => 2],
                                    ['type' => 'text', 'text' => $id_card, 'size' => 'xs', 'color' => '#333333', 'flex' => 4, 'wrap' => true]
                                ]
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'horizontal', // เปลี่ยนจาก grid เป็น horizontal
                                'spacing' => 'sm',
                                'contents' => [
                                    ['type' => 'text', 'text' => 'ตำแหน่ง:', 'size' => 'xs', 'color' => '#aaaaaa', 'flex' => 2],
                                    ['type' => 'text', 'text' => $position, 'size' => 'xs', 'color' => '#333333', 'flex' => 4, 'wrap' => true]
                                ]
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'horizontal', // เปลี่ยนจาก grid เป็น horizontal
                                'spacing' => 'sm',
                                'contents' => [
                                    ['type' => 'text', 'text' => 'สังกัด:', 'size' => 'xs', 'color' => '#aaaaaa', 'flex' => 2],
                                    ['type' => 'text', 'text' => $org_name, 'size' => 'xs', 'color' => '#333333', 'flex' => 4, 'wrap' => true]
                                ]
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'horizontal', // เปลี่ยนจาก grid เป็น horizontal
                                'spacing' => 'sm',
                                'contents' => [
                                    ['type' => 'text', 'text' => 'เบอร์โทร:', 'size' => 'xs', 'color' => '#aaaaaa', 'flex' => 2],
                                    ['type' => 'text', 'text' => $phone, 'size' => 'xs', 'color' => '#333333', 'flex' => 4, 'wrap' => true]
                                ]
                            ],
                            [
                                'type' => 'box',
                                'layout' => 'horizontal', // เปลี่ยนจาก grid เป็น horizontal
                                'spacing' => 'sm',
                                'contents' => [
                                    ['type' => 'text', 'text' => 'ประเภทบัตร:', 'size' => 'xs', 'color' => '#aaaaaa', 'flex' => 2],
                                    ['type' => 'text', 'text' => $card_type, 'size' => 'xs', 'color' => '#333333', 'flex' => 4, 'wrap' => true]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'footer' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'button',
                        'action' => [
                            'type' => 'uri',
                            'label' => 'จัดการคำขอ',
                            'uri' => $manage_url
                        ],
                        'style' => 'primary',
                        'color' => '#ef4444'
                    ]
                ]
            ]
        ];

        $post_data = [
            'to' => $line_user_id,
            'messages' => [
                [
                    'type' => 'flex',
                    'altText' => 'มีรายการยื่นคำขอทำบัตรใหม่เข้ามา',
                    'contents' => $flex_json
                ]
            ]
        ];

        $result = sendCurl($line_url, json_encode($post_data), [
            "Content-Type: application/json",
            "Authorization: Bearer {$line_channel_token}"
        ]);

        // ตรวจสอบว่าส่งสำเร็จหรือไม่ (LINE API จะคืนค่า {} ถ้าสำเร็จ)
        $res_json = json_decode($result, true);
        if ($result === '{}' || (isset($res_json) && empty($res_json['message']))) {
            // อัปเดตตัวนับ
            $conn->prepare("UPDATE idcard_settings SET setting_value = setting_value + 1 WHERE setting_key = 'line_msg_count'")->execute();
        } else {
            // บันทึก Error Log ถ้าส่งไม่สำเร็จ
            $error_msg = $res_json['message'] ?? 'Unknown Error';
            $details = $res_json['details'] ?? [];
            $full_error = "LINE API Error: " . $error_msg . " Details: " . json_encode($details);
            error_log($full_error);
            // บันทึก Log ลงระบบด้วยเผื่อ Admin อยากดู (ถ้ามีหน้าดู Log)
            saveLog($conn, 'LINE_NOTIFY_ERROR', $full_error);
        }
    }

    // --- 🟢 ส่ง Telegram ---
    if (!empty($tg_bot_token) && !empty($tg_chat_id)) {
        $tg_url = "https://api.telegram.org/bot{$tg_bot_token}/sendMessage";
        $tg_data = [
            'chat_id' => $tg_chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ];
        sendCurl($tg_url, http_build_query($tg_data));
    }
}

/**
 * Helper function สำหรับส่ง cURL
 */
function sendCurl($url, $postData, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
