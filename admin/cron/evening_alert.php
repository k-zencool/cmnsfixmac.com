<?php
// admin/cron/evening_alert.php

// 1. ตั้งค่า
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';

// ── กันยิง URL มั่ว: อนุญาตเฉพาะรันผ่าน CLI (cron) หรือแนบ ?key=<CRON_KEY> ที่ถูกต้อง ──
$cron_key = $_ENV['CRON_KEY'] ?? '';
if (PHP_SAPI !== 'cli' && !($cron_key !== '' && hash_equals($cron_key, (string)($_GET['key'] ?? '')))) {
    http_response_code(403);
    exit('forbidden');
}

require_once __DIR__ . '/telegram_helper.php';

$report_date = date('d/m/Y');
$report_time = date('H:i');
$today = date('Y-m-d');

// 2. นิยามสถานะ (Icons)
$status_def = [
    'FN'  => ['label' => 'ซ่อมเสร็จ (รอรับ)',     'icon' => '✅'],
    'DV'  => ['label' => 'ส่งมอบแล้ว (Complete)', 'icon' => '📦'],
    'RT'  => ['label' => 'คืนเครื่อง (Return)',    'icon' => '↩️'],
    'XX'  => ['label' => 'ยกเลิก (Cancel)',       'icon' => '❌'],
    'OK'  => ['label' => 'เริ่มดำเนินการซ่อม',     'icon' => '🛠'],
    'WC'  => ['label' => 'ลูกค้าอนุมัติซ่อม',       'icon' => '👍'],
    'WP'  => ['label' => 'รออะไหล่',             'icon' => '🔩'],
    // เพิ่มเผื่อไว้
    'QS'  => ['label' => 'เปิดใบงานใหม่',         'icon' => '📝'],
];

// 3. ดึงงานรับเข้าใหม่ (New Orders) 
// ✅ เพิ่ม device_type, problem_details
try {
    $sql_new = "SELECT ticket_number, customer_name, device_type, device_model, problem_details, appointment_date
                FROM tracking 
                WHERE DATE(created_at) = :today 
                ORDER BY created_at ASC";
    $stmt = $pdo->prepare($sql_new);
    $stmt->execute([':today' => $today]);
    $new_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. ดึงงานที่มีความเคลื่อนไหว (Updates)
    // ✅ เพิ่ม device_type, problem_details
    $sql_update = "SELECT ticket_number, customer_name, device_type, device_model, problem_details, status, appointment_date
                   FROM tracking 
                   WHERE DATE(updated_at) = :today 
                   AND status IN ('FN', 'DV', 'RT', 'XX', 'OK', 'WC', 'WP') 
                   ORDER BY updated_at ASC";
    $stmt = $pdo->prepare($sql_update);
    $stmt->execute([':today' => $today]);
    $moved_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Evening Alert DB Error: " . $e->getMessage());
    exit;
}

// 5. สร้างรายงาน
$msg = "🔔 <b>DAILY OPERATION REPORT (รายงานเย็น)</b>\n";
$msg .= "📅 $report_date | ⏰ $report_time น.\n";
$msg .= "〰〰〰〰〰〰〰〰〰〰\n"; 

// --- SECTION 1: งานรับเข้าใหม่ ---
$count_new = count($new_jobs);

$msg .= "\n📥 <u><b>งานรับเข้าใหม่ ($count_new)</b></u>\n";

if ($count_new > 0) {
    foreach ($new_jobs as $job) {
        // Prepare Data
        $clean_problem = strip_tags($job['problem_details'] ?? '-');
        $clean_problem = str_replace(['&nbsp;', '&amp;'], [' ', '&'], $clean_problem);
        $problem_short = mb_strimwidth(trim($clean_problem), 0, 40, '...');
        
        $due_info = "";
        if (!empty($job['appointment_date'])) {
            $d = date('d/m', strtotime($job['appointment_date']));
            $due_info = " 🗓$d";
        }

        $type = $job['device_type'] ? "[{$job['device_type']}]" : "";

        // Format 3 บรรทัด
        $msg .= "  ▪️ <code>{$job['ticket_number']}</code> : {$type} <b>{$job['device_model']}</b>\n";
        $msg .= "      └ 👤 {$job['customer_name']}{$due_info}\n";
        $msg .= "      └ 🔧 {$problem_short}\n";
    }
} else {
    $msg .= "  ▪️ <i>ไม่พบรายการรับเข้าใหม่</i>\n";
}
$msg .= "➖➖➖➖➖➖➖➖➖➖\n";


// --- SECTION 2: ความคืบหน้างาน ---
$grouped_moved = [];
foreach ($moved_jobs as $job) {
    $st = $job['status'];
    if (!isset($grouped_moved[$st])) $grouped_moved[$st] = [];
    $grouped_moved[$st][] = $job;
}

$msg .= "\n🏁 <u><b>สถานะงานที่มีการอัปเดต</b></u>\n";

if (count($moved_jobs) > 0) {
    foreach ($grouped_moved as $st => $list) {
        $info = $status_def[$st] ?? ['label' => $st, 'icon' => '🔹'];
        $c = count($list);
        
        $msg .= "{$info['icon']} <b>{$info['label']} ($c)</b>\n";
        
        foreach ($list as $job) {
            // Prepare Data
            $clean_problem = strip_tags($job['problem_details'] ?? '-');
            $clean_problem = str_replace(['&nbsp;', '&amp;'], [' ', '&'], $clean_problem);
            $problem_short = mb_strimwidth(trim($clean_problem), 0, 40, '...');

            $due_info = "";
            if (!empty($job['appointment_date'])) {
                $d = date('d/m', strtotime($job['appointment_date']));
                $due_info = " 🗓$d";
            }
            
            $type = $job['device_type'] ? "[{$job['device_type']}]" : "";

            // Format 3 บรรทัด
            $msg .= "  ▪️ <code>{$job['ticket_number']}</code> : {$type} <b>{$job['device_model']}</b>\n";
            $msg .= "      └ 👤 {$job['customer_name']}{$due_info}\n";
            $msg .= "      └ 🔧 {$problem_short}\n";
        }
        $msg .= "\n";
    }
} else {
    $msg .= "  ▪️ <i>ไม่มีรายการเปลี่ยนแปลงสถานะ</i>\n";
}

// 6. Footer
$msg .= "➖➖➖➖➖➖➖➖➖➖\n"; 
$msg .= "<i>🤖 Generated by Jarvis FixMac</i>";

// ── กัน: เคารพสวิตช์เปิด/ปิดจาก Notification Center ──
require_once __DIR__ . '/../../includes/notify_settings.php';
$round_on = notif_bool($pdo, 'notify_evening_enabled', true);

// 7. ส่ง Telegram (ถ้าเปิดช่อง Telegram + เปิดรอบเย็น)
$res = '(skipped)';
if ($round_on && notif_bool($pdo, 'notify_telegram_enabled', true)) {
    $res = sendTelegram($msg);
}

// 7.1 ส่งเข้า LINE คู่กัน (การ์ด Flex สรุป) — ถ้าเปิดช่อง LINE + เปิดรอบเย็น
require_once __DIR__ . '/../../includes/line_helper.php';
// LINE: การ์ด Flex สรุปใบเดียว (นับแยกตามสถานะ) — ดูรายละเอียดพิมพ์ /today เอา
$lineRows = [['label' => 'งานรับเข้าใหม่', 'value' => $count_new, 'color' => '#2563eb']];
foreach ($grouped_moved as $st => $list) {
    $m = line_tracking_status($st);
    $lineRows[] = ['label' => $m['label'], 'value' => count($list), 'color' => $m['color']];
}
$lineMsgs = [
    line_report_flex('รายงานเย็น', "$report_date · $report_time น.", (string)$count_new, 'รับเข้าใหม่', $lineRows, '#334155'),
];
$lineOut = ['skipped' => true];
if ($round_on && notif_bool($pdo, 'notify_line_enabled', true)) {
    $lineOut = line_alert_send($pdo, $lineMsgs);
}

echo "<h3>✅ Evening Report Updated (Official Format)</h3>";
echo "<pre>Telegram: " . htmlspecialchars($res) . "</pre>";
echo "<pre>LINE: " . htmlspecialchars(json_encode($lineOut, JSON_UNESCAPED_UNICODE)) . "</pre>";
?>