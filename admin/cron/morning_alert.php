<?php
// admin/cron/morning_alert.php — รายงานเช้า (LINE Flex summary card)

date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';

// ── กันยิง URL มั่ว: อนุญาตเฉพาะรันผ่าน CLI (cron) หรือแนบ ?key=<CRON_KEY> ที่ถูกต้อง ──
$cron_key = $_ENV['CRON_KEY'] ?? '';
if (PHP_SAPI !== 'cli' && !($cron_key !== '' && hash_equals($cron_key, (string)($_GET['key'] ?? '')))) {
    http_response_code(403);
    exit('forbidden');
}

require_once __DIR__ . '/../../includes/notify_settings.php';
require_once __DIR__ . '/../../includes/line_helper.php';

$report_date = date('d/m/Y');
$report_time = date('H:i');

// ลำดับสถานะที่แสดงในรายงาน
$order_show = ['QS', 'NCS', 'WC', 'OK', 'WP', 'RW', 'FN', 'NCF', 'XX'];

// ดึงเฉพาะงานที่ยังอยู่ในร้าน (ตัด DV=ส่งมอบ, RT=คืนเครื่อง ออก)
try {
    $all_jobs = $pdo->query("SELECT status FROM tracking WHERE status NOT IN ('DV','RT') ORDER BY created_at ASC")
                    ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Morning Alert DB Error: " . $e->getMessage());
    exit;
}

// จัดกลุ่มงานตามสถานะ
$grouped = [];
foreach ($all_jobs as $job) {
    $grouped[$job['status']][] = $job;
}

// LINE: การ์ด Flex สรุปใบเดียว (นับแยกตามสถานะ) — ดูรายละเอียดพิมพ์ /today เอา
$lineRows = [];
foreach ($order_show as $st) {
    if (!empty($grouped[$st])) {
        $m = line_tracking_status($st);
        $lineRows[] = ['label' => $m['label'], 'value' => count($grouped[$st]), 'color' => $m['color']];
    }
}
$lineMsgs = [
    line_report_flex('รายงานเช้า', "$report_date · $report_time น.", (string)count($all_jobs), 'รายการ', $lineRows, '#0ea5e9'),
];

// ส่งถ้าเปิดรอบเช้า + เปิดช่อง LINE (Notification Center)
// channel 'line_reports' = บอทรายงานแยกโควต้า — ยังไม่ตั้ง token จะ fallback ไปบอทหลักเอง
$lineOut = ['skipped' => true];
$pushOut = ['skipped' => true];
if (notif_bool($pdo, 'notify_morning_enabled', true)) {
    if (notif_bool($pdo, 'notify_line_enabled', true)) {
        $lineOut = line_alert_send($pdo, $lineMsgs, 'reports');
    }
    // แจ้งเตือนผ่านแอป (Web Push — ฟรี) คู่กับ LINE
    require_once __DIR__ . '/../../includes/push_helper.php';
    $pushTop = array_slice($lineRows, 0, 3);
    $pushOut = push_report_notify($pdo, 'รายงานเช้า — งานค้าง ' . count($all_jobs) . ' รายการ',
        implode(' · ', array_map(function ($r) { return $r['label'] . ' ' . $r['value']; }, $pushTop)));
}

echo "<h3>Morning Report</h3>";
echo "<pre>LINE: " . htmlspecialchars(json_encode($lineOut, JSON_UNESCAPED_UNICODE)) . "</pre>";
echo "<pre>PUSH: " . htmlspecialchars(json_encode($pushOut, JSON_UNESCAPED_UNICODE)) . "</pre>";
