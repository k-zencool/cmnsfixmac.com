<?php
/**
 * includes/line_helper.php — LINE Messaging API helper
 *
 * ความปลอดภัย: ส่งได้เฉพาะ line_user_id ที่อยู่ใน whitelist (admin_users) แบบ 1:1
 * → คนนอกที่แอด OA มาเฉยๆ จะไม่มีทางได้รับข้อมูลร้าน.
 *
 * standalone + function_exists guards (include ซ้ำได้ เหมือน warranty_lib.php)
 */

if (!function_exists('line_get_token')) {

    /** token จาก chat_platform_config (DB) → fallback .env */
    function line_get_token(PDO $pdo): string {
        try {
            $s = $pdo->query("SELECT access_token FROM chat_platform_config WHERE platform='line'");
            $t = $s ? $s->fetchColumn() : '';
            if ($t) return (string)$t;
        } catch (Exception $e) { /* ignore */ }
        return (string)($_ENV['LINE_CHANNEL_ACCESS_TOKEN'] ?? '');
    }

    /** channel secret (ใช้ verify webhook signature) */
    function line_get_secret(PDO $pdo): string {
        try {
            $s = $pdo->query("SELECT secret_key FROM chat_platform_config WHERE platform='line'");
            $t = $s ? $s->fetchColumn() : '';
            if ($t) return (string)$t;
        } catch (Exception $e) { /* ignore */ }
        return (string)($_ENV['LINE_CHANNEL_SECRET'] ?? '');
    }

    /** low-level POST → LINE API */
    function line_api_post(string $url, array $payload, string $token): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($res ?: '{}', true) ?? [], 'err' => $err];
    }

    /** แปลง HTML ของ Telegram (<b>,<code>,<br>) เป็น plain text — LINE ไม่รองรับ HTML */
    function line_html_to_text(string $html): string {
        $t = preg_replace('/<br\s*\/?>/i', "\n", $html);
        return html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    }

    /** push ข้อความ text → userId เดียว */
    function line_push(PDO $pdo, string $to, string $text, ?string $token = null): array {
        $token = $token ?? line_get_token($pdo);
        if (!$token || !$to) return ['code' => 0, 'body' => [], 'err' => 'no token/recipient'];
        return line_api_post('https://api.line.me/v2/bot/message/push', [
            'to'       => $to,
            'messages' => [['type' => 'text', 'text' => mb_substr(line_html_to_text($text), 0, 4900)]],
        ], $token);
    }

    /** ตอบกลับด้วย replyToken (ฟรี ไม่กินโควต้า — ใช้ใน webhook) */
    function line_reply(PDO $pdo, string $replyToken, string $text, ?string $token = null): array {
        $token = $token ?? line_get_token($pdo);
        if (!$token || !$replyToken) return ['code' => 0, 'body' => [], 'err' => 'no token/replyToken'];
        return line_api_post('https://api.line.me/v2/bot/message/reply', [
            'replyToken' => $replyToken,
            'messages'   => [['type' => 'text', 'text' => mb_substr(line_html_to_text($text), 0, 4900)]],
        ], $token);
    }

    /** ส่งหลาย message object (text/flex/…) ด้วย replyToken */
    function line_reply_messages(PDO $pdo, string $replyToken, array $messages, ?string $token = null): array {
        $token = $token ?? line_get_token($pdo);
        if (!$token || !$replyToken || !$messages) return ['code' => 0, 'body' => [], 'err' => 'no token/replyToken/messages'];
        return line_api_post('https://api.line.me/v2/bot/message/reply', [
            'replyToken' => $replyToken,
            'messages'   => array_slice(array_values($messages), 0, 5),
        ], $token);
    }

    /** message object แบบ text (plain — ไม่ strip HTML เพราะสร้างเองแล้ว) */
    function line_text_msg(string $text): array {
        return ['type' => 'text', 'text' => mb_substr($text, 0, 4900)];
    }

    /** map สถานะงานซ่อม: code → [label ไทย, สี hex, emoji] (ตรงกับ admin/tracking) */
    function line_tracking_status(string $code): array {
        static $map = [
            'QS'  => ['รอเช็คราคา',           '#f59e0b', '🏷️'],
            'WC'  => ['รอคอนเฟิร์ม',          '#2563eb', '💬'],
            'OK'  => ['กำลังซ่อม',            '#8b5cf6', '🔧'],
            'WP'  => ['รออะไหล่',             '#0ea5e9', '📦'],
            'RW'  => ['งานแก้ / เคลม',        '#ef4444', '🔁'],
            'FN'  => ['ซ่อมเสร็จ (รอรับ)',    '#10b981', '✅'],
            'NCF' => ['ติดต่อไม่ได้ (เสร็จ)',  '#94a3b8', '📵'],
            'NCS' => ['ติดต่อไม่ได้ (เสนอ)',   '#94a3b8', '📵'],
            'XX'  => ['ยกเลิก (รอรับคืน)',    '#ef4444', '❌'],
            'DV'  => ['ส่งมอบแล้ว',           '#475569', '📦'],
            'RT'  => ['ยกเลิก (คืนแล้ว)',     '#475569', '↩️'],
        ];
        $c = strtoupper(trim($code));
        return isset($map[$c])
            ? ['code' => $c, 'label' => $map[$c][0], 'color' => $map[$c][1], 'emoji' => $map[$c][2]]
            : ['code' => $c, 'label' => ($c ?: '-'), 'color' => '#94a3b8', 'emoji' => '•'];
    }

    /**
     * ส่งหา admin ที่ลงทะเบียน LINE ทุกคน แบบ 1:1 (สำหรับ cron แจ้งเตือน)
     * คืน ['recipients'=>n,'sent'=>n,'failed'=>n]
     */
    function sendLineToAdmins(PDO $pdo, string $text): array {
        $out   = ['recipients' => 0, 'sent' => 0, 'failed' => 0];
        $token = line_get_token($pdo);
        if (!$token) { $out['err'] = 'LINE token ยังไม่ได้ตั้งค่า'; return $out; }
        try {
            $ids = $pdo->query("SELECT line_user_id FROM admin_users WHERE line_user_id IS NOT NULL AND line_user_id <> ''")
                       ->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) { $out['err'] = $e->getMessage(); return $out; }
        $out['recipients'] = count($ids);
        foreach ($ids as $uid) {
            $r = line_push($pdo, (string)$uid, $text, $token);
            if (($r['code'] ?? 0) === 200) $out['sent']++; else $out['failed']++;
        }
        return $out;
    }

    /** verify X-Line-Signature = base64(HMAC-SHA256(secret, rawBody)) */
    function line_verify_signature(string $rawBody, string $signature, string $secret): bool {
        if (!$secret || !$signature) return false;
        $hash = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        return hash_equals($hash, $signature);
    }

    /** low-level GET → LINE API */
    function line_api_get(string $url, string $token): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($res ?: '{}', true) ?? [], 'err' => $err];
    }

    /**
     * ตรวจว่า access token ใช้งานได้จริง + คืนข้อมูล bot (basicId/displayName)
     * ใช้ยืนยันว่า token ตรงกับ channel ไหน (GET /v2/bot/info)
     */
    function line_bot_info(PDO $pdo, ?string $token = null): array {
        $token = $token ?? line_get_token($pdo);
        if (!$token) return ['code' => 0, 'body' => [], 'err' => 'no token'];
        return line_api_get('https://api.line.me/v2/bot/info', $token);
    }

    /** รหัสสั้น 6 หลัก (ตัดตัวอักษรกำกวม O/0/I/1) สำหรับลงทะเบียน */
    function line_gen_code(): string {
        return strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
    }

    /** เก็บคนที่ทักมาแต่ยังไม่อยู่ใน whitelist + ออกรหัสให้ (idempotent: มีแล้วคืนรหัสเดิม) */
    function line_register_pending(PDO $pdo, string $userId, ?string $name = null): string {
        $s = $pdo->prepare("SELECT code FROM line_pending_links WHERE line_user_id = ?");
        $s->execute([$userId]);
        $code = $s->fetchColumn();
        if ($code) return (string)$code;
        $code = line_gen_code();
        $ins = $pdo->prepare("INSERT INTO line_pending_links (line_user_id, display_name, code) VALUES (?,?,?)");
        $ins->execute([$userId, $name, $code]);
        return $code;
    }

    // ── Rich alerts (Flex summary + full detail, split so nothing gets cut) ──

    /** push หลาย message object (สูงสุด 5) → recipient เดียว */
    function line_push_messages(PDO $pdo, string $to, array $messages, ?string $token = null): array {
        $token = $token ?? line_get_token($pdo);
        if (!$token || !$to || !$messages) return ['code' => 0, 'body' => [], 'err' => 'no token/to/messages'];
        return line_api_post('https://api.line.me/v2/bot/message/push', [
            'to'       => $to,
            'messages' => array_slice(array_values($messages), 0, 5),
        ], $token);
    }

    /** ตัดข้อความยาวเป็นหลาย text message (กันโดน limit 5000/ข้อความ) — คืน array ของ message object */
    function line_text_chunks(string $text, int $max = 4800, int $limit = 4): array {
        $chunks = []; $cur = '';
        foreach (explode("\n", $text) as $ln) {
            if ($cur !== '' && (mb_strlen($cur) + mb_strlen($ln) + 1) > $max) { $chunks[] = $cur; $cur = $ln; }
            else { $cur = ($cur === '') ? $ln : $cur . "\n" . $ln; }
        }
        if ($cur !== '') $chunks[] = $cur;
        return array_map('line_text_msg', array_slice($chunks, 0, $limit));
    }

    /**
     * การ์ด Flex สรุปรายงาน — สไตล์ Apple minimal (พื้นขาว, ไม่มีอิโมจิ, bullet สีแทนไอคอนสถานะ,
     * ตัวเลขใหญ่, footer แบรนด์). ใช้เฉพาะ primitive มาตรฐาน (box baseline/vertical + text +
     * separator) — เลี่ยง filler/width/height/cornerRadius ที่ LINE reject.
     * $rows = [['label'=>'รอเช็คราคา','value'=>5,'color'=>'#f59e0b'], ...]
     */
    function line_report_flex(string $title, string $meta, string $bigNumber, string $bigLabel, array $rows, string $headerColor = '#0ea5e9'): array {
        $ink = '#1d1d1f'; $sub = '#6e6e73'; $muted = '#86868b';

        $body = [];
        // หัวข้อ
        $body[] = ['type' => 'text', 'text' => $title, 'color' => $ink, 'weight' => 'bold', 'size' => 'md', 'wrap' => true];
        // ตัวเลขใหญ่
        if ($bigNumber !== '') $body[] = ['type' => 'box', 'layout' => 'baseline', 'margin' => 'md', 'contents' => [
            ['type' => 'text', 'text' => $bigNumber, 'color' => $ink, 'weight' => 'bold', 'size' => '4xl', 'flex' => 0],
            ['type' => 'text', 'text' => $bigLabel, 'color' => $sub, 'size' => 'sm', 'margin' => 'md', 'flex' => 0],
        ]];
        if ($meta !== '') $body[] = ['type' => 'text', 'text' => $meta, 'color' => $muted, 'size' => 'xs', 'margin' => 'sm'];
        $body[] = ['type' => 'separator', 'margin' => 'xl'];

        // รายการสถานะ: bullet สี + label + ตัวเลข
        $i = 0;
        foreach ($rows as $r) {
            $body[] = ['type' => 'box', 'layout' => 'baseline', 'spacing' => 'sm', 'margin' => ($i === 0 ? 'lg' : 'md'), 'contents' => [
                ['type' => 'text', 'text' => '●', 'color' => $r['color'] ?? '#c7c7cc', 'size' => 'xs', 'flex' => 0],
                ['type' => 'text', 'text' => (string)$r['label'], 'color' => $ink, 'size' => 'sm', 'flex' => 6, 'wrap' => true],
                ['type' => 'text', 'text' => (string)$r['value'], 'color' => $ink, 'weight' => 'bold', 'size' => 'sm', 'align' => 'end', 'flex' => 1],
            ]];
            $i++;
        }
        if ($i === 0) $body[] = ['type' => 'text', 'text' => '—', 'color' => $muted, 'size' => 'sm', 'margin' => 'lg'];

        // footer แบรนด์
        $body[] = ['type' => 'separator', 'margin' => 'xl'];
        $body[] = ['type' => 'text', 'text' => 'CMNS FixMac', 'color' => '#b0b0b5', 'size' => 'xs', 'align' => 'center', 'margin' => 'lg'];

        return [
            'type' => 'flex',
            'altText' => trim("$title $bigNumber $bigLabel"),
            'contents' => [
                'type' => 'bubble', 'size' => 'mega',
                'body' => ['type' => 'box', 'layout' => 'vertical', 'paddingAll' => '20px', 'contents' => $body],
            ],
        ];
    }

    /**
     * การ์ดแจ้งเตือนงานซ่อมรายใบ (เปิดงานใหม่ / แก้ไขงาน) — สไตล์ Apple minimal
     * เดียวกับ line_report_flex ใช้เฉพาะ primitive มาตรฐานที่ LINE ไม่ reject
     * $rows = [['label'=>'ลูกค้า','value'=>'สมชาย','color'=>optional,'bold'=>optional], ...]
     * $editUrl ต้องเป็น https เท่านั้นถึงจะแนบปุ่ม (LINE reject uri ที่ไม่ใช่ https)
     */
    function line_job_flex(string $title, string $ticket, string $meta, array $rows, string $editUrl = '', string $accent = '#0ea5e9'): array {
        $ink = '#1d1d1f'; $sub = '#6e6e73'; $muted = '#86868b';

        $body = [];
        $body[] = ['type' => 'text', 'text' => $title, 'color' => $accent, 'weight' => 'bold', 'size' => 'sm'];
        $body[] = ['type' => 'text', 'text' => $ticket, 'color' => $ink, 'weight' => 'bold', 'size' => '3xl', 'margin' => 'sm'];
        if ($meta !== '') $body[] = ['type' => 'text', 'text' => $meta, 'color' => $muted, 'size' => 'xs', 'margin' => 'sm', 'wrap' => true];
        $body[] = ['type' => 'separator', 'margin' => 'xl'];

        $i = 0;
        foreach ($rows as $r) {
            $body[] = ['type' => 'box', 'layout' => 'baseline', 'spacing' => 'md', 'margin' => ($i === 0 ? 'lg' : 'md'), 'contents' => [
                ['type' => 'text', 'text' => (string)$r['label'], 'color' => $sub, 'size' => 'xs', 'flex' => 2],
                ['type' => 'text', 'text' => (string)$r['value'], 'color' => $r['color'] ?? $ink,
                 'size' => 'sm', 'flex' => 5, 'wrap' => true,
                 'weight' => !empty($r['bold']) ? 'bold' : 'regular'],
            ]];
            $i++;
        }
        if ($i === 0) $body[] = ['type' => 'text', 'text' => '—', 'color' => $muted, 'size' => 'sm', 'margin' => 'lg'];

        $body[] = ['type' => 'separator', 'margin' => 'xl'];
        $body[] = ['type' => 'text', 'text' => 'CMNS FixMac', 'color' => '#b0b0b5', 'size' => 'xs', 'align' => 'center', 'margin' => 'lg'];

        $bubble = [
            'type' => 'bubble', 'size' => 'mega',
            'body' => ['type' => 'box', 'layout' => 'vertical', 'paddingAll' => '20px', 'contents' => $body],
        ];
        // ปุ่มลิงก์เข้าหน้า edit — เฉพาะ https (LINE ไม่รับ http)
        if ($editUrl !== '' && strpos($editUrl, 'https://') === 0) {
            $bubble['footer'] = ['type' => 'box', 'layout' => 'vertical', 'paddingAll' => '12px', 'contents' => [
                ['type' => 'button', 'style' => 'primary', 'height' => 'sm', 'color' => $accent,
                 'action' => ['type' => 'uri', 'label' => 'เปิดหน้างานซ่อม', 'uri' => $editUrl]],
            ]];
        }

        return ['type' => 'flex', 'altText' => trim("$title $ticket"), 'contents' => $bubble];
    }

    /**
     * ยิงการ์ดงานซ่อมถ้าสวิตช์เปิด (LINE channel + jobs toggle ใน Notification Center)
     * fail-safe: พังเงียบ ไม่มีทาง block การบันทึกงาน
     */
    function line_job_notify(PDO $pdo, array $flexMsg): array {
        try {
            require_once __DIR__ . '/notify_settings.php';
            if (!notif_bool($pdo, 'notify_line_enabled', true))  return ['skipped' => 'line off'];
            if (!notif_bool($pdo, 'notify_jobs_enabled', true))  return ['skipped' => 'jobs off'];
            return line_alert_send($pdo, [$flexMsg]);
        } catch (Throwable $e) {
            return ['err' => $e->getMessage()];
        }
    }

    /** ส่งชุด message (flex+text) ไปหา admin ที่ลงทะเบียน + กลุ่มที่เปิดอยู่ ทีเดียว */
    function line_alert_send(PDO $pdo, array $messages): array {
        $out = ['recipients' => 0, 'sent' => 0, 'failed' => 0];
        $token = line_get_token($pdo);
        if (!$token) { $out['err'] = 'no token'; return $out; }
        $recips = [];
        try {
            $recips = $pdo->query("SELECT line_user_id FROM admin_users WHERE line_user_id IS NOT NULL AND line_user_id <> ''")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) { $out['err'] = $e->getMessage(); }
        try {
            $recips = array_merge($recips, $pdo->query("SELECT group_id FROM line_groups WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN));
        } catch (Exception $e) { /* ตาราง line_groups อาจยังไม่ migrate */ }
        $out['recipients'] = count($recips);
        foreach ($recips as $to) {
            $r = line_push_messages($pdo, (string)$to, $messages, $token);
            if (($r['code'] ?? 0) === 200) {
                $out['sent']++;
            } else {
                $out['failed']++;
                // เก็บเหตุผล fail ล่าสุด (ช่วย debug: 401=token, 400=ผู้รับ invalid/กลุ่มเก่า)
                $out['err'] = 'HTTP ' . ($r['code'] ?? 0) . ' ' . mb_strimwidth((string)($r['body']['message'] ?? $r['err'] ?? ''), 0, 120, '…');
            }
        }
        return $out;
    }

    // ── Groups ────────────────────────────────────────────────

    /** ชื่อกลุ่มจาก LINE (GET /v2/bot/group/{id}/summary) — ต้องเปิดสิทธิ์ให้บอทเข้ากลุ่มก่อน */
    function line_group_summary(PDO $pdo, string $groupId, ?string $token = null): array {
        $token = $token ?? line_get_token($pdo);
        if (!$token || !$groupId) return ['code' => 0, 'body' => [], 'err' => 'no token/group'];
        return line_api_get('https://api.line.me/v2/bot/group/' . rawurlencode($groupId) . '/summary', $token);
    }

    /** บันทึก/เปิดใช้งานกลุ่ม (idempotent) — เรียกตอน bot ถูกเชิญเข้ากลุ่ม */
    function line_register_group(PDO $pdo, string $groupId, ?string $name = null, ?string $addedBy = null): void {
        $pdo->prepare("
            INSERT INTO line_groups (group_id, group_name, added_by, is_active)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                group_name = COALESCE(VALUES(group_name), group_name),
                is_active  = 1,
                updated_at = NOW()
        ")->execute([$groupId, $name, $addedBy]);
    }

    /** ปิดใช้งานกลุ่ม (ตอน bot ถูกเตะออก / leave) — ไม่ลบประวัติ */
    function line_deactivate_group(PDO $pdo, string $groupId): void {
        $pdo->prepare("UPDATE line_groups SET is_active = 0, updated_at = NOW() WHERE group_id = ?")
            ->execute([$groupId]);
    }

    /** สั่งให้บอทออกจากกลุ่ม (POST /leave) */
    function line_leave_group(PDO $pdo, string $groupId, ?string $token = null): array {
        $token = $token ?? line_get_token($pdo);
        if (!$token || !$groupId) return ['code' => 0, 'body' => [], 'err' => 'no token/group'];
        return line_api_post('https://api.line.me/v2/bot/group/' . rawurlencode($groupId) . '/leave', [], $token);
    }

    /** push แจ้งเตือนเข้าทุกกลุ่มที่เปิดใช้งาน (คู่กับ sendLineToAdmins) */
    function sendLineToGroups(PDO $pdo, string $text): array {
        $out   = ['recipients' => 0, 'sent' => 0, 'failed' => 0];
        $token = line_get_token($pdo);
        if (!$token) { $out['err'] = 'no token'; return $out; }
        try {
            $ids = $pdo->query("SELECT group_id FROM line_groups WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) { $out['err'] = $e->getMessage(); return $out; }
        $out['recipients'] = count($ids);
        foreach ($ids as $gid) {
            $r = line_push($pdo, (string)$gid, $text, $token);
            if (($r['code'] ?? 0) === 200) $out['sent']++; else $out['failed']++;
        }
        return $out;
    }

} // function_exists guard
