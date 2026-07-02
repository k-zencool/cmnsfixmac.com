<?php
/**
 * admin/cron/line_hook.php — LINE Messaging API webhook (คู่กับ bot_hook.php ของ Telegram)
 *
 * ตั้ง Webhook URL นี้ใน LINE Developers console:
 *   https://<โดเมน>/admin/cron/line_hook.php
 *
 * Flow:
 *   1. verify X-Line-Signature (กัน webhook ปลอม)
 *   2. parse events[]
 *   3. auth ด้วย whitelist admin_users.line_user_id
 *      - อยู่ใน whitelist → ประมวลคำสั่ง + reply
 *      - ไม่อยู่ → ออกรหัสลงทะเบียน ให้ super_admin อนุมัติหลังบ้าน (ไม่ปล่อยข้อมูล)
 */
header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/line_helper.php';

$rawBody   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
$secret    = line_get_secret($pdo);

// 1) verify signature — ถ้าไม่ผ่าน จบเลย
if (!line_verify_signature($rawBody, $signature, $secret)) {
    http_response_code(403);
    echo 'bad signature';
    exit;
}

$update = json_decode($rawBody, true);
if (empty($update['events'])) { echo 'no events'; exit; }

$token = line_get_token($pdo);

foreach ($update['events'] as $ev) {
    $type       = $ev['type'] ?? '';
    $replyToken = $ev['replyToken'] ?? '';
    $userId     = $ev['source']['userId'] ?? '';

    // เฉพาะข้อความ text จาก user (ไม่รับ event อื่นในเฟสนี้)
    if ($type !== 'message' || ($ev['message']['type'] ?? '') !== 'text' || !$userId) {
        continue;
    }

    $text = trim($ev['message']['text'] ?? '');

    // 2) auth: whitelist
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE line_user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        // 3) ยังไม่ได้รับสิทธิ์ → ออกรหัสลงทะเบียน ไม่ปล่อยข้อมูลใดๆ
        $code = line_register_pending($pdo, $userId);
        line_reply($pdo, $replyToken,
            "🔒 บัญชีนี้ยังไม่ได้รับสิทธิ์เข้าถึงข้อมูลร้าน\n\n" .
            "แจ้งรหัสนี้ให้แอดมินเพื่อยืนยันตัวตน:\n👉 {$code}\n\n" .
            "แอดมินจะอนุมัติให้ในระบบหลังบ้าน", $token);
        continue;
    }

    // 4) ผ่าน whitelist → ประมวลคำสั่ง
    $reply = line_handle_command($pdo, $admin, $text);
    line_reply($pdo, $replyToken, $reply, $token);
}

echo 'ok';


/**
 * ประมวลคำสั่งพื้นฐาน (เฟส 1) — เช็คสถานะงานซ่อม
 * NOTE: ยังไม่ได้พอร์ตคำสั่งเต็มจาก bot_hook.php (AI/หลายคำสั่ง) — เฟสถัดไป
 */
function line_handle_command(PDO $pdo, array $admin, string $text): string {
    $name = !empty($admin['line_display_name']) ? $admin['line_display_name'] : $admin['username'];
    $cmd  = mb_strtolower(trim($text));

    if ($cmd === '/help' || $cmd === 'help' || $cmd === 'เมนู') {
        return "📋 คำสั่งที่ใช้ได้:\n" .
               "/today — งานค้างในร้านตอนนี้\n" .
               "/status <เลขงาน> — เช็คสถานะงาน";
    }

    if (mb_strpos($cmd, '/status') === 0) {
        $tk = trim(mb_substr($text, 7));
        if ($tk === '') return "พิมพ์: /status <เลขงาน>";
        $s = $pdo->prepare("SELECT ticket_number, customer_name, device_model, status FROM tracking WHERE ticket_number = ? LIMIT 1");
        $s->execute([$tk]);
        $j = $s->fetch(PDO::FETCH_ASSOC);
        if (!$j) return "❌ ไม่พบงาน {$tk}";
        return "🧾 งาน {$j['ticket_number']}\n" .
               "ลูกค้า: {$j['customer_name']}\n" .
               "เครื่อง: {$j['device_model']}\n" .
               "สถานะ: {$j['status']}";
    }

    if ($cmd === '/today' || $cmd === 'today') {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM tracking WHERE status NOT IN ('DV','RT')")->fetchColumn();
        return "📦 งานค้างในร้าน: {$n} งาน\nพิมพ์ /status <เลขงาน> เพื่อดูรายตัว";
    }

    return "สวัสดี {$name} 👋\nพิมพ์ /help เพื่อดูคำสั่ง";
}
