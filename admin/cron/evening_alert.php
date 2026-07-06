<?php
// admin/cron/evening_alert.php — รายงานเย็น (LINE Flex summary card)

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
$today = date('Y-m-d');

try {
    // งานรับเข้าใหม่วันนี้ (นับจำนวน)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tracking WHERE DATE(created_at) = :today");
    $stmt->execute([':today' => $today]);
    $count_new = (int)$stmt->fetchColumn();

    // งานที่มีการอัปเดตสถานะวันนี้
    $stmt = $pdo->prepare("SELECT status FROM tracking
                           WHERE DATE(updated_at) = :today
                           AND status IN ('FN','DV','RT','XX','OK','WC','WP')
                           ORDER BY updated_at ASC");
    $stmt->execute([':today' => $today]);
    $moved_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Evening Alert DB Error: " . $e->getMessage());
    exit;
}

// จัดกลุ่มงานที่อัปเดตตามสถานะ
$grouped_moved = [];
foreach ($moved_jobs as $job) {
    $grouped_moved[$job['status']][] = $job;
}

// LINE: การ์ด Flex สรุปใบเดียว (นับแยกตามสถานะ) — ดูรายละเอียดพิมพ์ /today เอา
$lineRows = [['label' => 'งานรับเข้าใหม่', 'value' => $count_new, 'color' => '#2563eb']];
foreach ($grouped_moved as $st => $list) {
    $m = line_tracking_status($st);
    $lineRows[] = ['label' => $m['label'], 'value' => count($list), 'color' => $m['color']];
}
$lineMsgs = [
    line_report_flex('รายงานเย็น', "$report_date · $report_time น.", (string)$count_new, 'รับเข้าใหม่', $lineRows, '#334155'),
];

// ส่งถ้าเปิดรอบเย็น + เปิดช่อง LINE (Notification Center)
$lineOut = ['skipped' => true];
if (notif_bool($pdo, 'notify_evening_enabled', true) && notif_bool($pdo, 'notify_line_enabled', true)) {
    $lineOut = line_alert_send($pdo, $lineMsgs);
}

echo "<h3>Evening Report</h3>";
echo "<pre>LINE: " . htmlspecialchars(json_encode($lineOut, JSON_UNESCAPED_UNICODE)) . "</pre>";
