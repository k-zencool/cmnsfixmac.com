<?php
// admin/cron/telegram_helper.php

// ✅ เพิ่ม $keyboard = null เป็นตัวแปรตัวที่ 3
function sendTelegram($message, $custom_chat_id = null, $keyboard = null) {
    
    $token = "8591838440:AAHnX02kZP2HezDycBuMF4wS4tIWrRPrNRM";
    
    // เลขกลุ่มหลัก (CMNS Alerts) หรือเลขส่วนตัวมึงก็ได้ ตั้งเป็นค่า Default
    $default_chat_id = "-1002778708648"; 
    
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