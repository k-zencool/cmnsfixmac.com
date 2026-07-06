<?php
// admin/cron/telegram_helper.php

require_once __DIR__ . '/../../includes/notify_settings.php';

// ✅ เพิ่ม $keyboard = null เป็นตัวแปรตัวที่ 3
function sendTelegram($message, $custom_chat_id = null, $keyboard = null) {

    // ค่า fallback เดิม (ใช้เมื่อ DB ยังไม่ตั้งค่า/ยังไม่ migrate) — token นี้ถูก revoke แล้วบน prod
    $token           = "8591838440:AAHnX02kZP2HezDycBuMF4wS4tIWrRPrNRM";
    $default_chat_id = "-1002778708648";

    // อ่าน token + default chat_id จาก DB (notification_settings) ถ้ามี
    if (function_exists('notif_get') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $token           = notif_get($GLOBALS['pdo'], 'telegram_bot_token', $token);
        $default_chat_id = notif_get($GLOBALS['pdo'], 'telegram_chat_id', $default_chat_id);
    }

    // ถ้ามีการส่ง ID มาเฉพาะ (จาก bot_hook) ให้ใช้ ID นั้น
    // ถ้าไม่มี ให้ส่งเข้า Default
    $chat_id = $custom_chat_id ? $custom_chat_id : $default_chat_id;
    
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML' 
    ];

    // 🔥 ส่วนที่เพิ่ม: เช็คว่ามีปุ่มส่งมาไหม ถ้ามีให้แนบไปด้วย
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}
?>