<?php
/**
 * includes/line_helper.php — LINE Messaging API helper (คู่กับ telegram_helper.php)
 *
 * รันคู่ Telegram ได้: alert เรียก sendLineToAdmins() ต่อท้าย sendTelegram().
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
