<?php
// idcard/line_notify_helper.php
require_once 'env_loader.php';

function sendLineNotification($id_card_number, $message) {
    global $conn;

    $channel_access_token = $_ENV['LINE_ACCESS_TOKEN'] ?? '';
    if (empty($channel_access_token)) return false;

    // Find all active subscriptions for this ID card
    try {
        $stmt = $conn->prepare("SELECT line_user_id FROM idcard_line_subscriptions WHERE id_card_number = ? AND is_active = 1");
        $stmt->execute([$id_card_number]);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subs)) return true; // No one to notify

        foreach ($subs as $sub) {
            $userId = $sub['line_user_id'];
            
            $messages = [
                [
                    'type' => 'text',
                    'text' => $message
                ]
            ];

            $ch = curl_init("https://api.line.me/v2/bot/message/push");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $channel_access_token
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'to' => $userId,
                'messages' => $messages
            ]));

            curl_exec($ch);
            curl_close($ch);
        }

        return true;
    } catch (PDOException $e) {
        error_log("LINE Notify Error: " . $e->getMessage());
        return false;
    }
}
