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

// 2. แยกประเภทข้อมูล
$chat_id = null;
$message = "";
$user_id = null;
$user_name = "";
$chat_type = "private";
$is_callback = false;

if (isset($update['callback_query'])) {
    $is_callback = true;
    $chat_id   = $update['callback_query']['message']['chat']['id'];
    $message   = $update['callback_query']['data'];
    $user_id   = $update['callback_query']['from']['id'];
    $user_name = $update['callback_query']['from']['first_name'];
    $chat_type = $update['callback_query']['message']['chat']['type'];
} else if (isset($update['message'])) {
    $chat_id   = $update['message']['chat']['id'];
    $message   = trim($update['message']['text']);
    $user_id   = $update['message']['from']['id'];
    $user_name = $update['message']['from']['first_name'];
    $chat_type = $update['message']['chat']['type'];
} else {
    exit;
}

// ==================================================================================
// 🔄 MULTI-COMMAND PROCESSOR
// ==================================================================================
// แปลง " /" เป็นขึ้นบรรทัดใหม่ เพื่อรองรับคำสั่งต่อเนื่องในบรรทัดเดียว
$processed_msg = str_replace(' /', "\n/", $message);
$command_lines = explode("\n", $processed_msg);

$output_buffer = []; 

// ==================================================================================
// 🔐 AUTHENTICATION
// ==================================================================================
$current_user = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE telegram_chat_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// --- กรณี: ยังไม่ได้ยืนยันตัวตน ---
if (!$current_user) {
    $parts = preg_split('/\s+/', trim($command_lines[0]));
    $cmd = strtolower($parts[0]);

    if ($cmd == "/link") {
        if ($chat_type != 'private' && !$is_callback) {
            sendTelegram("🚫 <b>ความปลอดภัย:</b> กรุณาทำรายการในแชทส่วนตัวกับบอทเท่านั้น", $chat_id); exit;
        }
        if (count($parts) < 3) {
            sendTelegram("⚠️ <b>การยืนยันตัวตน:</b>\nพิมพ์: <code>/link [username] [password]</code>", $chat_id); exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$parts[1]]);
            $db_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($db_user && password_verify($parts[2], $db_user['password'])) {
                $pdo->prepare("UPDATE admin_users SET telegram_chat_id = ? WHERE id = ?")->execute([$user_id, $db_user['id']]);
                sendTelegram("✅ <b>ยืนยันตัวตนสำเร็จ</b>\nยินดีต้อนรับคุณ <b>{$db_user['username']}</b> ครับ", $chat_id);
            } else {
                sendTelegram("❌ <b>ข้อมูลไม่ถูกต้อง</b>\nกรุณาตรวจสอบ Username หรือ Password", $chat_id);
            }
        } catch (Exception $e) {}
        exit;
    } else {
        if ($chat_type == 'private') sendTelegram("🔒 <b>กรุณายืนยันตัวตนก่อนใช้งาน</b>\nพิมพ์: <code>/link [username] [password]</code>", $chat_id);
        exit;
    }
}

// ==================================================================================
// ✅ LOGGED IN ZONE
// ==================================================================================
$admin_id   = $current_user['id'];
$admin_name = $current_user['username'];

// สถานะงาน (ภาษาทางการ)
$status_map = [
    'QS' => 'รอตรวจสอบราคา',
    'WC' => 'รออนุมัติ/มัดจำ',
    'OK' => 'กำลังซ่อม',
    'RW' => 'งานแก้ไข/เคลม',
    'WP' => 'รออะไหล่',
    'FN' => 'ซ่อมเสร็จ (รอรับ)',
    'NCS' => 'ติดต่อไม่ได้ (เสนอราคา)',
    'NCF' => 'ติดต่อไม่ได้ (แจ้งรับ)',
    'XX' => 'ยกเลิก',
    'DV' => 'ส่งมอบแล้ว',
    'RT' => 'คืนเครื่อง'
];

$inline_menu = [
    'inline_keyboard' => [
        [
            ['text' => '📋 งานค้างหน้าร้าน', 'callback_data' => '/pending'],
            ['text' => '📅 สรุปยอดวันนี้', 'callback_data' => '/today']
        ],
        [
            ['text' => 'ℹ️ คู่มือการใช้งาน', 'callback_data' => '/help_code']
        ]
    ]
];

foreach ($command_lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    $parts = preg_split('/\s+/', $line);
    $command = strtolower($parts[0]);

    // --- 1. /menu ---
    if ($command == "/menu" || $command == "/start") {
        sendTelegram("🤖 <b>ระบบจัดการร้าน FixMac</b>\nผู้ใช้งาน: <b>$admin_name</b>\nเลือกเมนูใช้งานได้เลยครับ 👇", $chat_id, $inline_menu);
        exit;
    }

    // --- 2. /help ---
    else if ($command == "/help_code") {
        $msg = "ℹ️ <b>คู่มือการใช้งานระบบ</b>\n\n";
        
        $msg .= "🛠 <b>คำสั่งจัดการงาน:</b>\n";
        $msg .= "• <code>/check [เลขงาน]</code>\n  ตรวจสอบรายละเอียดและสถานะงาน\n";
        $msg .= "• <code>/up [เลขงาน] [สถานะ]</code>\n  อัปเดตสถานะงานซ่อม\n";
        $msg .= "• <code>/price [เลขงาน] [ราคา]</code>\n  บันทึกราคาประเมิน/ค่าซ่อม\n\n";
        
        $msg .= "📊 <b>คำสั่งดูข้อมูล:</b>\n";
        $msg .= "• <code>/pending</code> : ดูรายการงานคงค้างทั้งหมด\n";
        $msg .= "• <code>/today</code> : ดูสรุปยอดรับเข้า/ส่งออก ประจำวัน\n\n";
        
        $msg .= "🔑 <b>รหัสสถานะ (Status Code):</b>\n";
        foreach ($status_map as $c => $d) {
            $msg .= "• <code>$c</code> : $d\n";
        }
        
        $msg .= "\n💡 <b>Tip:</b> สามารถพิมพ์คำสั่งต่อเนื่องได้โดยเว้นวรรคด้วย /\nตัวอย่าง: <code>/up V1001 OK /price V1001 2500</code>";
        
        sendTelegram($msg, $chat_id, $inline_menu);
        exit;
    }

    // --- 3. /pending (ละเอียด) ---
    else if ($command == "/pending") {
        try {
            $sql = "SELECT ticket_number, customer_name, customer_phone, device_model, problem_details, status, estimated_cost
                    FROM tracking 
                    WHERE status IN ('QS','WC','OK','RW','WP','NCS','NCF') 
                    ORDER BY created_at ASC LIMIT 15";
            $jobs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            
            $msg = "📋 <b>รายงานสถานะงานคงค้าง (" . count($jobs) . " รายการ)</b>\n";
            $msg .= "➖➖➖➖➖➖➖➖➖➖\n";
            
            if($jobs) {
                foreach($jobs as $j) {
                    $icon = ($j['status']=='OK') ? '🔵' : (($j['status']=='FN') ? '🟢' : '🔸');
                    $st_text = $status_map[$j['status']] ?? $j['status'];
                    $problem = mb_substr(strip_tags($j['problem_details']), 0, 40) . "..";
                    $price = ($j['estimated_cost'] > 0) ? number_format($j['estimated_cost']) : "-";
                    
                    $msg .= "$icon <b>{$j['ticket_number']}</b> ($st_text)\n";
                    $msg .= "👤 {$j['customer_name']} ({$j['customer_phone']})\n";
                    $msg .= "📱 {$j['device_model']}\n";
                    $msg .= "🔧 $problem | 💰 $price บ.\n";
                    $msg .= "----------------------------\n";
                }
            } else {
                $msg .= "🎉 <b>ยอดเยี่ยม! ไม่พบงานคงค้างในระบบครับ</b>";
            }
            $output_buffer[] = $msg;
        } catch(Exception $e){}
    }

    // --- 4. /today (ละเอียด) ---
    else if ($command == "/today") {
        try {
            $today = date('Y-m-d');
            $date_th = date('d/m/Y');
            
            // 1. รับเข้า
            $new = $pdo->query("SELECT COUNT(*) FROM tracking WHERE DATE(created_at)='$today'")->fetchColumn();
            
            // 2. งานเสร็จ (FN) - พร้อมรายละเอียด
            $sql_fn = "SELECT ticket_number, customer_name, device_model, problem_details FROM tracking WHERE DATE(updated_at)='$today' AND status='FN'";
            $fns = $pdo->query($sql_fn)->fetchAll(PDO::FETCH_ASSOC);
            
            // 3. ส่งมอบ (DV) - พร้อมรายละเอียด
            $sql_dv = "SELECT ticket_number, customer_name, device_model, estimated_cost FROM tracking WHERE DATE(updated_at)='$today' AND status='DV'";
            $dvs = $pdo->query($sql_dv)->fetchAll(PDO::FETCH_ASSOC);

            // สร้างข้อความ
            $msg = "📅 <b>สรุปรายงานประจำวัน ($date_th)</b>\n";
            $msg .= "➖➖➖➖➖➖➖➖➖➖\n";
            $msg .= "📥 <b>รับงานใหม่: $new เครื่อง</b>\n\n";
            
            $msg .= "✅ <b>ซ่อมเสร็จ (" . count($fns) . "):</b>\n";
            if($fns) {
                foreach($fns as $f) {
                    $prob = mb_substr(strip_tags($f['problem_details']), 0, 20);
                    $msg .= "• <code>{$f['ticket_number']}</code>: {$f['device_model']}\n";
                    $msg .= "  └ {$f['customer_name']} ($prob)\n";
                }
            } else { $msg .= "- ไม่มี -\n"; }
            $msg .= "\n";

            $msg .= "📦 <b>ส่งมอบแล้ว (" . count($dvs) . "):</b>\n";
            if($dvs) {
                foreach($dvs as $d) {
                    $price = number_format($d['estimated_cost']);
                    $msg .= "• <code>{$d['ticket_number']}</code>: {$d['device_model']}\n";
                    $msg .= "  └ {$d['customer_name']} (💰 $price)\n";
                }
            } else { $msg .= "- ไม่มี -\n"; }
            
            $output_buffer[] = $msg;
        } catch(Exception $e){}
    }

    // --- 5. /check ---
    else if ($command == "/check") {
        if(count($parts) < 2) continue;
        $t = strtoupper($parts[1]);
        
        $sql = "SELECT * FROM tracking WHERE ticket_number = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$t]);
        $j = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($j) {
            $st_full = $status_map[$j['status']] ?? $j['status'];
            $cost = number_format($j['estimated_cost']);
            $detail = strip_tags($j['problem_details']);
            $date_in = date('d/m/Y H:i', strtotime($j['created_at']));
            $cust_info = "{$j['customer_name']} ({$j['customer_phone']})";

            $msg = "📄 <b>รายละเอียดงาน: $t</b>\n";
            $msg .= "----------------------------------\n";
            $msg .= "👤 <b>ลูกค้า:</b> $cust_info\n";
            $msg .= "📱 <b>อุปกรณ์:</b> {$j['device_model']}\n";
            $msg .= "🔧 <b>อาการ:</b> $detail\n";
            $msg .= "📅 <b>วันที่รับ:</b> $date_in\n";
            $msg .= "💰 <b>ราคาประเมิน:</b> $cost บาท\n";
            $msg .= "📊 <b>สถานะ:</b> $st_full";
            
            $output_buffer[] = $msg;
        } else {
            $output_buffer[] = "❌ <b>ไม่พบข้อมูล:</b> หมายเลขงาน $t";
        }
    }

    // --- 6. /up ---
    else if ($command == "/up") {
        if(count($parts) < 3) { 
            $output_buffer[] = "⚠️ <b>คำสั่งไม่ถูกต้อง:</b> /up [เลขงาน] [สถานะ]"; continue; 
        }
        $t = strtoupper($parts[1]);
        $s = strtoupper($parts[2]);
        
        if(!isset($status_map[$s])) { 
            $output_buffer[] = "🚫 <b>สถานะผิด:</b> รหัส ($s) ไม่มีในระบบ"; continue; 
        }
        
        $chk = $pdo->prepare("SELECT id FROM tracking WHERE ticket_number = ?");
        $chk->execute([$t]);
        
        if($chk->fetch()) {
            $sql = "UPDATE tracking SET status = ?, updated_at = NOW(), updated_by = ? WHERE ticket_number = ?";
            $pdo->prepare($sql)->execute([$s, $admin_id, $t]);
            
            $st_full = $status_map[$s];
            $output_buffer[] = "✅ <b>อัปเดตสถานะสำเร็จ</b>\n🔖 <b>$t</b> ➝ <b>$st_full</b>";
        } else {
            $output_buffer[] = "❌ <b>ไม่พบข้อมูล:</b> $t";
        }
    }

    // --- 7. /price ---
    else if ($command == "/price") {
        if(count($parts) < 3) { 
            $output_buffer[] = "⚠️ <b>คำสั่งไม่ถูกต้อง:</b> /price [เลขงาน] [ราคา]"; continue; 
        }
        
        $t = strtoupper($parts[1]);
        $p = (float)str_replace(',', '', $parts[2]);

        $chk = $pdo->prepare("SELECT id, estimated_cost FROM tracking WHERE ticket_number = ?");
        $chk->execute([$t]);
        $j = $chk->fetch(PDO::FETCH_ASSOC);
        
        if($j) {
            $sql = "UPDATE tracking SET estimated_cost = ?, updated_at = NOW(), updated_by = ? WHERE ticket_number = ?";
            $pdo->prepare($sql)->execute([$p, $admin_id, $t]);
            
            $old_p = number_format($j['estimated_cost']);
            $new_p = number_format($p);
            
            $output_buffer[] = "💰 <b>บันทึกราคาสำเร็จ</b>\n🔖 <b>$t</b>: <s>$old_p</s> ➝ <b>$new_p</b> บาท";
        } else {
            $output_buffer[] = "❌ <b>ไม่พบข้อมูล:</b> $t";
        }
    }
    
    // --- 8. /unlink ---
    else if ($command == "/unlink") {
        $pdo->prepare("UPDATE admin_users SET telegram_chat_id = NULL WHERE id = ?")->execute([$admin_id]);
        sendTelegram("👋 <b>ออกจากระบบเรียบร้อย</b>\nขอบคุณที่ใช้บริการครับ", $chat_id);
        exit;
    }
}

// ส่งผลลัพธ์รวม
if (!empty($output_buffer)) {
    $final_msg = implode("\n\n══════════════════\n\n", $output_buffer);
    sendTelegram($final_msg, $chat_id);
}
?>