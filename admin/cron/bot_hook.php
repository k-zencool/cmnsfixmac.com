<?php
// admin/cron/bot_hook.php

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/telegram_helper.php';

// 1. รับค่าจาก Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

// 2. แยกประเภทข้อมูล (Message ปกติ VS การกดปุ่ม Callback)
$chat_id = null;
$message = "";
$user_id = null;
$user_name = "";
$chat_type = "private";
$is_callback = false;

// A. กรณีเป็น "การกดปุ่ม" (Callback Query)
if (isset($update['callback_query'])) {
    $is_callback = true;
    $chat_id   = $update['callback_query']['message']['chat']['id'];
    $message   = $update['callback_query']['data']; // ค่าคำสั่งที่ซ่อนในปุ่ม (เช่น /pending)
    $user_id   = $update['callback_query']['from']['id'];
    $user_name = $update['callback_query']['from']['first_name'];
    $chat_type = $update['callback_query']['message']['chat']['type'];

    // (Optional) ยิง answerCallbackQuery เพื่อปิดวงกลมหมุนๆ ได้ แต่ข้ามไปก่อนได้
}
// B. กรณีเป็น "การพิมพ์ข้อความ" ปกติ
else if (isset($update['message'])) {
    $chat_id   = $update['message']['chat']['id'];
    $message   = trim($update['message']['text']);
    $user_id   = $update['message']['from']['id'];
    $user_name = $update['message']['from']['first_name'];
    $chat_type = $update['message']['chat']['type'];
} else {
    exit;
}

// แยกคำสั่ง
$parts = preg_split('/\s+/', $message);
$command = strtolower($parts[0]);

// ==================================================================================
// 🎮 CONFIG: ปุ่มเมนูแบบ Inline (ฝังใต้ข้อความ)
// ==================================================================================
$inline_menu = [
    'inline_keyboard' => [
        [
            ['text' => '📋 งานค้างหน้าร้าน', 'callback_data' => '/pending'],
            ['text' => '📅 สรุปยอดวันนี้', 'callback_data' => '/today']
        ],
        [
            ['text' => 'ℹ️ วิธีใช้ / รหัสสถานะ', 'callback_data' => '/help_code']
        ]
    ]
];

// ==================================================================================
// 🔐 AUTHENTICATION (ระบบยืนยันตัวตน)
// ==================================================================================

$current_user = null;

try {
    // เช็คว่า Telegram ID นี้ ผูกกับ User คนไหนใน DB
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE telegram_chat_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// --- CASE 1: ยังไม่ยืนยันตัวตน (Guest) ---
if (!$current_user) {

    // อนุญาตให้ใช้แค่คำสั่ง /link
    if ($command == "/link") {

        // ห้ามพิมพ์รหัสในกลุ่ม (ยกเว้นกดปุ่ม callback มา แต่มันคงไม่กด link ผ่าน callback หรอก)
        if ($chat_type != 'private' && !$is_callback) {
            sendTelegram("🚫 <b>อันตราย!</b> กรุณาพิมพ์รหัสในแชทส่วนตัวกับบอทเท่านั้นครับ", $chat_id);
            exit;
        }

        if (count($parts) < 3) {
            sendTelegram("⚠️ <b>วิธีเชื่อมต่อบัญชี</b>\nพิมพ์: <code>/link [username] [password]</code>", $chat_id);
            exit;
        }

        $input_user = $parts[1];
        $input_pass = $parts[2];

        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$input_user]);
            $db_user = $stmt->fetch(PDO::FETCH_ASSOC);

            // ✅ ตรวจสอบรหัสผ่าน (รองรับ Hash ใน DB)
            if ($db_user && password_verify($input_pass, $db_user['password'])) {

                // บันทึก Telegram ID ลง DB
                $upd = $pdo->prepare("UPDATE admin_users SET telegram_chat_id = ? WHERE id = ?");
                $upd->execute([$user_id, $db_user['id']]);

                // ส่งข้อความต้อนรับ พร้อมปุ่มเมนู
                sendTelegram(
                    "✅ <b>เชื่อมต่อสำเร็จ!</b>\nยินดีต้อนรับคุณ <b>{$db_user['username']}</b>\nเลือกเมนูใช้งานได้เลยครับ 👇",
                    $chat_id,
                    $inline_menu
                );
            } else {
                sendTelegram("❌ <b>เข้าสู่ระบบล้มเหลว</b>\nUsername หรือ Password ไม่ถูกต้อง", $chat_id);
            }
        } catch (Exception $e) {
            sendTelegram("⚠️ System Error: " . $e->getMessage(), $chat_id);
        }
        exit;
    }

    // ถ้ายังไม่ Link แล้วพิมพ์คำสั่งอื่น
    else {
        if ($chat_type == 'private') {
            sendTelegram("🔒 <b>คุณยังไม่ได้ยืนยันตัวตน</b>\nกรุณาพิมพ์:\n<code>/link [username] [password]</code>", $chat_id);
        }
        exit;
    }
}

// ==================================================================================
// ✅ LOGGED IN ZONE (ผู้ใช้ยืนยันตัวตนแล้ว)
// ==================================================================================

$admin_id   = $current_user['id'];       // เอาไว้บันทึก updated_by
$admin_name = $current_user['username'];

$status_map = [
    'QS' => 'รอตรวจสอบราคา',
    'WC' => 'รออนุมัติ/มัดจำ',
    'OK' => 'กำลังดำเนินการซ่อม',
    'RW' => 'งานแก้ไข/เคลม',
    'WP' => 'รออะไหล่',
    'FN' => 'ซ่อมเสร็จ (รอรับ)',
    'NCS' => 'ติดต่อไม่ได้ (เสนอราคา)',
    'NCF' => 'ติดต่อไม่ได้ (แจ้งรับ)',
    'XX' => 'ยกเลิก',
    'DV' => 'ส่งมอบแล้ว',
    'RT' => 'คืนเครื่อง'
];

// -------------------------------------------------------------
// 🎮 COMMAND HANDLERS
// -------------------------------------------------------------

// 1️⃣ เมนูหลัก (/menu)
if ($command == "/menu" || $command == "/start") {
    $msg = "🤖 <b>FixMac System</b> (User: $admin_name)\n";
    $msg .= "เลือกเมนูที่ต้องการได้เลยครับ 👇";
    sendTelegram($msg, $chat_id, $inline_menu);
}

// 2️⃣ วิธีใช้ / รหัสสถานะ (กดจากปุ่ม)
else if ($command == "/help_code") {
    $msg = "ℹ️ <b>รหัสสถานะ:</b>\n";
    foreach ($status_map as $code => $desc) {
        $msg .= "• <code>$code</code> : $desc\n";
    }
    $msg .= "\n🛠 <b>รายการคำสั่ง:</b>";
    $msg .= "\n📋 <code>/pending</code> : ดูงานค้างหน้าร้าน";
    $msg .= "\n📅 <code>/today</code> : สรุปยอดประจำวัน";
    $msg .= "\n🔹 <code>/check [เลขงาน]</code> : เช็คสถานะงาน";
    $msg .= "\n🔸 <code>/up [เลขงาน] [สถานะ]</code> : อัปเดตงาน";
    $msg .= "\n🔓 <code>/unlink</code> : ออกจากระบบ";

    // ส่งกลับไปพร้อมปุ่มเมนูเดิม เพื่อให้กดต่อได้ง่าย
    sendTelegram($msg, $chat_id, $inline_menu);
}

// 3️⃣ งานค้างหน้าร้าน (/pending) - 🔥 แสดงรายละเอียดครบ
else if ($command == "/pending") {
    try {
        // ดึงข้อมูลละเอียด: Type, Model, Series, Problem
        $sql = "SELECT ticket_number, device_type, device_model, device_series, problem_details, status 
                FROM tracking 
                WHERE status IN ('QS', 'WC', 'OK', 'RW', 'WP', 'NCS', 'NCF') 
                ORDER BY created_at ASC LIMIT 15";

        $stmt = $pdo->query($sql);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = count($jobs);
        $msg = "📋 <b>งานค้างหน้าร้าน ($count รายการ)</b>\n";
        $msg .= "➖➖➖➖➖➖➖➖➖➖\n";

        if ($count > 0) {
            foreach ($jobs as $j) {
                // เลือก Icon Status
                $icon = '🔸';
                if ($j['status'] == 'OK') $icon = '🔵'; // กำลังซ่อม
                if ($j['status'] == 'WP') $icon = '🟣'; // รออะไหล่
                if ($j['status'] == 'WC') $icon = '🟡'; // รออนุมัติ
                if ($j['status'] == 'FN') $icon = '🟢'; // เสร็จแล้ว

                // รวมชื่อรุ่นให้ครบ (Type + Series + Model)
                // ตัวอย่าง: MacBook Pro M1 A2338
                $device_full = trim("{$j['device_type']} {$j['device_series']} {$j['device_model']}");
                if (empty($device_full)) $device_full = "ไม่ระบุรุ่น";

                // จัดการอาการเสีย (ตัดคำ)
                $problem = strip_tags($j['problem_details']);
                if (mb_strlen($problem) > 40) {
                    $problem = mb_substr($problem, 0, 38) . "..";
                }
                if (empty($problem)) $problem = "-";

                // ชื่อสถานะไทย
                $status_txt = $status_map[$j['status']] ?? $j['status'];

                // จัดรูปแบบข้อความ
                $msg .= "$icon <b>{$j['ticket_number']}</b> : <i>$status_txt</i>\n";
                $msg .= "📱 $device_full\n";
                $msg .= "🔧 $problem\n";
                $msg .= "----------------------------\n";
            }
        } else {
            $msg .= "🎉 <b>เยี่ยมมาก! ไม่มีงานค้างหน้าร้านครับ</b>";
        }

        // ส่งข้อความ + แนบปุ่มเมนูเดิมไปให้กดต่อ
        sendTelegram($msg, $chat_id, $inline_menu);
    } catch (Exception $e) {
        sendTelegram("⚠️ Error: " . $e->getMessage(), $chat_id);
    }
}

// 4️⃣ สรุปยอด (/today)
// 4️⃣ สรุปยอด (/today) - 🔥 [อัปเกรด: แยกสถานะ + โชว์รายละเอียด]
else if ($command == "/today") {
    try {
        $today = date('Y-m-d');
        
        // 1. นับยอดรับเข้าใหม่ (New)
        $new_count = $pdo->query("SELECT COUNT(*) FROM tracking WHERE DATE(created_at) = '$today'")->fetchColumn();
        
        // 2. ดึงรายการงานที่จบวันนี้ (FN, DV, RT) มาแยกกลุ่ม
        $sql = "SELECT ticket_number, device_model, status FROM tracking 
                WHERE DATE(updated_at) = ? AND status IN ('FN', 'DV', 'RT')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$today]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ตัวแปรเก็บรายการแยกตามกลุ่ม
        $list_fn = []; // ซ่อมเสร็จ (รอรับ)
        $list_dv = []; // ส่งมอบแล้ว
        $list_rt = []; // คืนเครื่อง

        foreach ($rows as $r) {
            // จัดรูปแบบบรรทัด: • V1001 : iPhone 13
            $line = "• <code>{$r['ticket_number']}</code> : {$r['device_model']}";
            
            if ($r['status'] == 'FN') $list_fn[] = $line;
            else if ($r['status'] == 'DV') $list_dv[] = $line;
            else if ($r['status'] == 'RT') $list_rt[] = $line;
        }

        // --- สร้างข้อความตอบกลับ ---
        $msg = "📅 <b>สรุปยอดประจำวัน ($today)</b>\n";
        $msg .= "📥 <b>รับเข้าใหม่: $new_count เครื่อง</b>\n";
        $msg .= "〰〰〰〰〰〰〰〰\n";

        // กลุ่ม 1: ซ่อมเสร็จ (รอรับ)
        $msg .= "✅ <b>ซ่อมเสร็จ (รอรับ): " . count($list_fn) . "</b>\n";
        if (!empty($list_fn)) {
            $msg .= implode("\n", $list_fn) . "\n";
        }
        $msg .= "\n";

        // กลุ่ม 2: ส่งมอบแล้ว
        $msg .= "📦 <b>ส่งมอบแล้ว: " . count($list_dv) . "</b>\n";
        if (!empty($list_dv)) {
            $msg .= implode("\n", $list_dv) . "\n";
        }
        $msg .= "\n";

        // กลุ่ม 3: คืนเครื่อง
        $msg .= "↩️ <b>คืนเครื่อง: " . count($list_rt) . "</b>\n";
        if (!empty($list_rt)) {
            $msg .= implode("\n", $list_rt) . "\n";
        }
        
        sendTelegram($msg, $chat_id, $inline_menu);
    } catch (Exception $e) {
        sendTelegram("⚠️ Error: " . $e->getMessage(), $chat_id);
    }
}

// 5️⃣ เช็คสถานะ (พิมพ์เอง /check)
else if ($command == "/check") {
    if (count($parts) < 2) {
        sendTelegram("⚠️ ระบุเลขงาน เช่น: <code>/check V1001</code>", $chat_id);
        exit;
    }
    $ticket = strtoupper($parts[1]);

    try {
        $stmt = $pdo->prepare("SELECT * FROM tracking WHERE ticket_number = ?");
        $stmt->execute([$ticket]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($job) {
            $st_label = $status_map[$job['status']] ?? $job['status'];
            $clean_problem = strip_tags($job['problem_details']);
            $updated_date = date('d/m/Y H:i', strtotime($job['updated_at']));

            $msg = "📄 <b>รายละเอียดงาน: $ticket</b>\n";
            $msg .= "📱 {$job['device_model']}\n";
            $msg .= "🔧 $clean_problem\n";
            $msg .= "📊 <b>$st_label</b>\n";
            $msg .= "📅 $updated_date";

            sendTelegram($msg, $chat_id); // เช็คงานอาจจะไม่ต้องแนบปุ่มก็ได้ จะได้ไม่รก
        } else {
            sendTelegram("❌ ไม่พบงาน <b>$ticket</b>", $chat_id);
        }
    } catch (Exception $e) {
    }
}

// 6️⃣ อัปเดตสถานะ (/up) + บันทึกคนทำ (updated_by)
else if ($command == "/up") {
    if (count($parts) < 3) {
        sendTelegram("⚠️ พิมพ์: <code>/up [เลขงาน] [สถานะ]</code>", $chat_id);
        exit;
    }
    $ticket = strtoupper($parts[1]);
    $status = strtoupper($parts[2]);

    if (!array_key_exists($status, $status_map)) {
        sendTelegram("🚫 สถานะผิด! กดดูรหัสที่เมนูวิธีใช้", $chat_id);
        exit;
    }

    try {
        // เช็คว่ามีงานไหม
        $stmt = $pdo->prepare("SELECT id, device_model FROM tracking WHERE ticket_number = ?");
        $stmt->execute([$ticket]);
        $job = $stmt->fetch();

        if ($job) {
            // ✅ อัปเดตสถานะ + เวลา + คนทำ (updated_by)
            $sql = "UPDATE tracking 
                    SET status = ?, 
                        updated_at = NOW(), 
                        updated_by = ? 
                    WHERE ticket_number = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $admin_id, $ticket]);

            $st_text = $status_map[$status];

            $msg = "✅ <b>อัปเดตเรียบร้อย ($admin_name)</b>\n";
            $msg .= "🔖 <b>$ticket</b> -> <b>$st_text</b>";

            sendTelegram($msg, $chat_id);
        } else {
            sendTelegram("❌ ไม่พบงาน $ticket", $chat_id);
        }
    } catch (Exception $e) {
        sendTelegram("⚠️ Error: " . $e->getMessage(), $chat_id);
    }
}

// 7️⃣ Unlink (/unlink)
else if ($command == "/unlink") {
    try {
        $upd = $pdo->prepare("UPDATE admin_users SET telegram_chat_id = NULL WHERE id = ?");
        $upd->execute([$admin_id]);
        sendTelegram("👋 <b>ออกจากระบบเรียบร้อย</b>", $chat_id);
    } catch (Exception $e) {
    }
}

// 8️⃣ Ping
else if ($command == "/ping") {
    sendTelegram("🏓 <b>Pong!</b> ($admin_name)", $chat_id);
}
