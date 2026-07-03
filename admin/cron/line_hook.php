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
 *   3. รองรับทั้งแชท 1:1 และกลุ่ม:
 *      - 1:1: whitelist → คำสั่ง / ไม่รู้จัก → ออกรหัสลงทะเบียน
 *      - กลุ่ม: บันทึกกลุ่มตอนถูกเชิญ (join) เพื่อ push แจ้งเตือน,
 *              ตอบเฉพาะคำสั่งขึ้นต้น '/' จากพนักงานที่ whitelist (กันสแปม)
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
    $src        = $ev['source'] ?? [];
    $srcType    = $src['type'] ?? '';                        // user | group | room
    $userId     = $src['userId'] ?? '';
    $groupId    = $src['groupId'] ?? ($src['roomId'] ?? '');
    $inGroup    = ($srcType === 'group' || $srcType === 'room');

    // ── bot ถูกเชิญเข้ากลุ่ม → บันทึกกลุ่ม + ทักทาย ──
    if ($type === 'join' && $inGroup && $groupId) {
        $name = '';
        if ($srcType === 'group') {
            $s = line_group_summary($pdo, $groupId, $token);
            if (($s['code'] ?? 0) === 200) $name = $s['body']['groupName'] ?? '';
        }
        try { line_register_group($pdo, $groupId, $name ?: null, $userId ?: null); } catch (Exception $e) { /* table ยังไม่ migrate */ }
        line_reply($pdo, $replyToken,
            "สวัสดีครับ 🤖 บอท CMNS เข้ากลุ่มแล้ว\n" .
            "• กลุ่มนี้จะได้รับแจ้งเตือนงานจากร้าน\n" .
            "• พนักงานที่ลงทะเบียนแล้ว พิมพ์ /help เพื่อใช้คำสั่ง", $token);
        continue;
    }

    // ── bot ถูกเตะ/ออกจากกลุ่ม → ปิดแจ้งเตือนกลุ่มนั้น ──
    if ($type === 'leave' && $groupId) {
        try { line_deactivate_group($pdo, $groupId); } catch (Exception $e) { /* ignore */ }
        continue;
    }

    // ── เฉพาะข้อความ text ──
    if ($type !== 'message' || ($ev['message']['type'] ?? '') !== 'text') continue;
    $text = trim($ev['message']['text'] ?? '');

    // auth ด้วย whitelist (userId มีทั้งใน 1:1 และในกลุ่ม)
    $admin = null;
    if ($userId) {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE line_user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── ในกลุ่ม: ตอบเฉพาะคำสั่งขึ้นต้น '/' (กันสแปม) ──
    if ($inGroup) {
        if ($text === '' || $text[0] !== '/') continue;   // ข้อความคุยกันทั่วไป → เงียบ
        if (!$admin) {
            line_reply($pdo, $replyToken,
                "❌ คุณยังไม่มีสิทธิ์ใช้คำสั่ง — ทักบอทตรงๆ (แชท 1:1) เพื่อลงทะเบียนก่อน", $token);
            continue;
        }
        line_reply($pdo, $replyToken, line_handle_command($pdo, $admin, $text), $token);
        continue;
    }

    // ── แชท 1:1 ──
    if (!$userId) continue;
    if (!$admin) {
        // ยังไม่ได้รับสิทธิ์ → ออกรหัสลงทะเบียน ไม่ปล่อยข้อมูลใดๆ
        $code = line_register_pending($pdo, $userId);
        line_reply($pdo, $replyToken,
            "🔒 บัญชีนี้ยังไม่ได้รับสิทธิ์เข้าถึงข้อมูลร้าน\n\n" .
            "แจ้งรหัสนี้ให้แอดมินเพื่อยืนยันตัวตน:\n👉 {$code}\n\n" .
            "แอดมินจะอนุมัติให้ในระบบหลังบ้าน", $token);
        continue;
    }
    line_reply($pdo, $replyToken, line_handle_command($pdo, $admin, $text), $token);
}

echo 'ok';


/**
 * ประมวลคำสั่งพื้นฐาน — เช็คสถานะงานซ่อม
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
