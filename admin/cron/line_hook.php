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
        line_reply_messages($pdo, $replyToken, line_handle_command($pdo, $admin, $text), $token);
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
    line_reply_messages($pdo, $replyToken, line_handle_command($pdo, $admin, $text), $token);
}

echo 'ok';


/**
 * ประมวลคำสั่ง — คืน array ของ LINE message object (text / flex)
 */
function line_handle_command(PDO $pdo, array $admin, string $text): array {
    $name = !empty($admin['line_display_name']) ? $admin['line_display_name'] : $admin['username'];
    $cmd  = mb_strtolower(trim($text));

    if ($cmd === '/help' || $cmd === 'help' || $cmd === 'เมนู') {
        return [line_text_msg(
            "📋 คำสั่งที่ใช้ได้\n\n" .
            "🔎 /status <เลขงาน>\n     ดูรายละเอียดงานซ่อม\n\n" .
            "📦 /today\n     สรุปงานค้างในร้าน แยกตามสถานะ"
        )];
    }

    if (mb_strpos($cmd, '/status') === 0) {
        $tk = trim(mb_substr($text, 7));
        if ($tk === '') return [line_text_msg("พิมพ์: /status <เลขงาน>\nเช่น /status 6812-001")];
        $s = $pdo->prepare("SELECT * FROM tracking WHERE ticket_number = ? LIMIT 1");
        $s->execute([$tk]);
        $j = $s->fetch(PDO::FETCH_ASSOC);
        if (!$j) return [line_text_msg("❌ ไม่พบงานเลขที่ {$tk}")];
        return [line_job_flex($j)];
    }

    if ($cmd === '/today' || $cmd === 'today') {
        $rows  = $pdo->query("SELECT status, COUNT(*) c FROM tracking WHERE status NOT IN ('DV','RT') GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
        $total = array_sum(array_column($rows, 'c'));
        $order = ['QS' => 0, 'WC' => 1, 'OK' => 2, 'RW' => 3, 'FN' => 4, 'XX' => 5, 'NCF' => 6, 'NCS' => 7];
        usort($rows, fn($a, $b) => ($order[$a['status']] ?? 99) <=> ($order[$b['status']] ?? 99));
        $body = "📦 งานค้างในร้าน: {$total} งาน";
        foreach ($rows as $r) {
            $st = line_tracking_status($r['status']);
            $body .= "\n{$st['emoji']} {$st['label']}: {$r['c']}";
        }
        $body .= "\n\nพิมพ์ /status <เลขงาน> เพื่อดูรายละเอียด";
        return [line_text_msg($body)];
    }

    return [line_text_msg("สวัสดี {$name} 👋\nพิมพ์ /help เพื่อดูคำสั่ง")];
}

/** สร้าง Flex Message การ์ดงานซ่อม 1 ใบ */
function line_job_flex(array $j): array {
    $st   = line_tracking_status($j['status'] ?? '');
    $rows = [];
    $add  = function (string $label, $val) use (&$rows) {
        $val = trim(line_html_to_text((string)$val));   // problem_details ฯลฯ เป็น HTML จาก editor
        if ($val === '' || $val === '0000-00-00') return;
        $rows[] = ['type' => 'box', 'layout' => 'baseline', 'spacing' => 'sm', 'contents' => [
            ['type' => 'text', 'text' => $label, 'color' => '#94a3b8', 'size' => 'sm', 'flex' => 2],
            ['type' => 'text', 'text' => mb_substr($val, 0, 300), 'wrap' => true, 'color' => '#1f2937', 'size' => 'sm', 'flex' => 5],
        ]];
    };

    $device = trim(($j['device_type'] ?? '') . ' ' . ($j['device_model'] ?? '') . ' ' . ($j['device_series'] ?? ''));
    $cost   = ($j['estimated_cost'] ?? '') !== '' && $j['estimated_cost'] !== null && (float)$j['estimated_cost'] > 0
              ? '฿' . number_format((float)$j['estimated_cost']) : '';

    $add('ลูกค้า',   $j['customer_name'] ?? '');
    $add('เบอร์',    $j['customer_phone'] ?? '');
    $add('เครื่อง',  $device);
    $add('อาการ',    $j['problem_details'] ?? '');
    $add('อุปกรณ์',  $j['accessories'] ?? '');
    $add('ค่าซ่อม',  $cost);
    $add('นัดหมาย',  $j['appointment_date'] ?? '');
    $add('รับเครื่อง', $j['pickup_date'] ?? '');
    $add('โน้ตช่าง', $j['technician_note'] ?? '');

    return [
        'type'    => 'flex',
        'altText' => '🧾 งาน ' . ($j['ticket_number'] ?? '') . ' — ' . $st['label'],
        'contents' => [
            'type' => 'bubble',
            'size' => 'mega',
            'header' => [
                'type' => 'box', 'layout' => 'vertical', 'backgroundColor' => $st['color'],
                'paddingAll' => '16px', 'spacing' => 'xs', 'contents' => [
                    ['type' => 'text', 'text' => '🧾 ' . ($j['ticket_number'] ?? '-'), 'color' => '#ffffff', 'weight' => 'bold', 'size' => 'lg'],
                    ['type' => 'text', 'text' => $st['emoji'] . ' ' . $st['label'], 'color' => '#ffffff', 'size' => 'sm'],
                ],
            ],
            'body' => [
                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'md', 'paddingAll' => '16px',
                'contents' => $rows ?: [['type' => 'text', 'text' => 'ไม่มีรายละเอียด', 'color' => '#94a3b8', 'size' => 'sm']],
            ],
        ],
    ];
}
