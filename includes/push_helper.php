<?php
/**
 * includes/push_helper.php — Web Push (แจ้งเตือนผ่านแอป PWA) แบบไม่พึ่ง library
 *
 * ใช้แค่ openssl + curl + hash_hkdf (มีครบบน PHP 7.4/cPanel) — ฟรี ไม่มีโควต้าแบบ LINE
 * มาตรฐาน: VAPID (RFC 8292, ES256 JWT) + Message Encryption (RFC 8291, aes128gcm)
 *
 * VAPID keypair เก็บใน chat_platform_config (platform='webpush'):
 *   access_token = public key 65 byte (base64url) · secret_key = private scalar d 32 byte (base64url)
 * standalone + function_exists guards (include ซ้ำได้ เหมือน line_helper)
 */

if (!function_exists('push_b64url')) {

    function push_b64url(string $bin): string {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    function push_b64url_decode(string $s): string {
        return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    /** ค่า EC จาก openssl อาจสั้นกว่า 32 byte (ตัด leading zero) — pad กลับให้ครบ */
    function push_pad32(string $bin): string {
        return str_pad($bin, 32, "\x00", STR_PAD_LEFT);
    }

    /** สร้าง EC P-256 keypair → ['d'=>32B, 'pub'=>65B (0x04||x||y)] */
    function push_ec_keygen(): array {
        $res = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($res === false) throw new RuntimeException('openssl EC keygen failed');
        $det = openssl_pkey_get_details($res);
        return [
            'd'   => push_pad32($det['ec']['d']),
            'pub' => "\x04" . push_pad32($det['ec']['x']) . push_pad32($det['ec']['y']),
            'res' => $res,
        ];
    }

    /** ประกอบ private key PEM (SEC1) จาก d + pub — DER template ของ P-256 ล้วนๆ */
    function push_ec_private_pem(string $d32, string $pub65): string {
        $der = hex2bin('30770201010420') . $d32
             . hex2bin('a00a06082a8648ce3d030107')
             . hex2bin('a144034200') . $pub65;
        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }

    /** ประกอบ public key PEM (SPKI) จาก raw point 65 byte */
    function push_ec_public_pem(string $pub65): string {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $pub65;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** แปลงลายเซ็น ECDSA จาก DER → raw r||s (64 byte) ตามที่ JWT ES256 ต้องการ */
    function push_der_sig_to_raw(string $der): string {
        $off = 2;
        if (ord($der[1]) & 0x80) $off += ord($der[1]) & 0x7f; // long-form length
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$off]) !== 0x02) throw new RuntimeException('bad DER sig');
            $len = ord($der[$off + 1]);
            $int = substr($der, $off + 2, $len);
            $int = ltrim($int, "\x00");
            $out .= push_pad32($int);
            $off += 2 + $len;
        }
        return $out;
    }

    /** VAPID keypair จาก DB — ยังไม่มีก็สร้างแล้วเก็บ (ครั้งเดียวตลอดชีพ อย่าลบ ไม่งั้น subscription เก่าพังหมด) */
    function push_vapid_keys(PDO $pdo): array {
        $s = $pdo->prepare("SELECT access_token, secret_key FROM chat_platform_config WHERE platform = 'webpush'");
        $s->execute();
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['access_token']) && !empty($row['secret_key'])) {
            return ['pub' => push_b64url_decode($row['access_token']), 'd' => push_b64url_decode($row['secret_key'])];
        }
        $kp = push_ec_keygen();
        $pdo->prepare("
            INSERT INTO chat_platform_config (platform, page_name, access_token, secret_key, updated_at)
            VALUES ('webpush', 'Web Push VAPID (อย่าลบ)', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), secret_key = VALUES(secret_key), updated_at = NOW()
        ")->execute([push_b64url($kp['pub']), push_b64url($kp['d'])]);
        return ['pub' => $kp['pub'], 'd' => $kp['d']];
    }

    /** public key (base64url) สำหรับ JS pushManager.subscribe */
    function push_vapid_public(PDO $pdo): string {
        return push_b64url(push_vapid_keys($pdo)['pub']);
    }

    /** Authorization header ของ VAPID: ES256 JWT ต่อ origin ของ push service */
    function push_vapid_auth(string $endpoint, array $vapid): string {
        $u   = parse_url($endpoint);
        $aud = $u['scheme'] . '://' . $u['host'];
        $head   = push_b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = push_b64url(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => 'mailto:cmns54321@gmail.com']));
        $pem = push_ec_private_pem($vapid['d'], $vapid['pub']);
        if (!openssl_sign("$head.$claims", $der, $pem, OPENSSL_ALGO_SHA256)) throw new RuntimeException('vapid sign failed');
        $jwt = "$head.$claims." . push_b64url(push_der_sig_to_raw($der));
        return 'vapid t=' . $jwt . ', k=' . push_b64url($vapid['pub']);
    }

    /**
     * เข้ารหัส payload ตาม RFC 8291 (aes128gcm, single record)
     * $test: ['as'=>keypair,'salt'=>16B] สำหรับเทสแบบ deterministic เท่านั้น
     */
    function push_encrypt(string $payload, string $p256dh_b64u, string $auth_b64u, ?array $test = null): string {
        $ua_pub = push_b64url_decode($p256dh_b64u);          // 65B public key ของ browser
        $auth   = push_b64url_decode($auth_b64u);            // 16B auth secret
        if (strlen($ua_pub) !== 65 || strlen($auth) !== 16) throw new RuntimeException('bad subscription keys');

        $as   = $test['as'] ?? push_ec_keygen();             // ephemeral keypair ฝั่ง server
        $salt = $test['salt'] ?? random_bytes(16);

        $ecdh = openssl_pkey_derive(push_ec_public_pem($ua_pub), push_ec_private_pem($as['d'], $as['pub']), 32);
        if ($ecdh === false) throw new RuntimeException('ECDH failed');

        // HKDF chain ตาม RFC 8291
        $ikm   = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\x00" . $ua_pub . $as['pub'], $auth);
        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        // plaintext + 0x02 = delimiter ของ record สุดท้าย
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) throw new RuntimeException('AES-GCM failed');

        // header block: salt(16) || record size uint32 || idlen(1) || as_pub(65) || ciphertext||tag
        return $salt . pack('N', 4096) . chr(65) . $as['pub'] . $cipher . $tag;
    }

    /** ส่ง 1 subscription — คืน ['code'=>HTTP status] (201=สำเร็จ, 404/410=ตายแล้วให้ลบ) */
    function push_send_one(array $sub, string $payloadJson, array $vapid): array {
        try {
            $body = push_encrypt($payloadJson, $sub['p256dh'], $sub['auth']);
            $auth = push_vapid_auth($sub['endpoint'], $vapid);
        } catch (Throwable $e) {
            return ['code' => 0, 'err' => $e->getMessage()];
        }
        $ch = curl_init($sub['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $auth,
                'Content-Encoding: aes128gcm',
                'Content-Type: application/octet-stream',
                'Content-Length: ' . strlen($body),
                'TTL: 86400',
                'Urgency: high',
            ],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'body' => (string)$res, 'err' => $err];
    }

    /**
     * ยิงแจ้งเตือนหาทุกอุปกรณ์ที่ subscribe ไว้ · ลบ subscription ที่ตายแล้ว (404/410) ทิ้งอัตโนมัติ
     * คืน ['recipients'=>n,'sent'=>n,'failed'=>n]
     */
    function push_broadcast(PDO $pdo, string $title, string $body, string $url = '/admin/dashboard/', string $tag = ''): array {
        $out = ['recipients' => 0, 'sent' => 0, 'failed' => 0];
        try {
            $subs  = $pdo->query("SELECT id, endpoint, p256dh, auth FROM push_subscriptions")->fetchAll(PDO::FETCH_ASSOC);
            if (!$subs) return $out;
            $vapid = push_vapid_keys($pdo);
        } catch (Throwable $e) {
            $out['err'] = $e->getMessage();
            return $out;
        }
        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag], JSON_UNESCAPED_UNICODE);
        $out['recipients'] = count($subs);
        foreach ($subs as $sub) {
            $r = push_send_one($sub, $payload, $vapid);
            if (in_array($r['code'], [200, 201], true)) {
                $out['sent']++;
                try { $pdo->prepare("UPDATE push_subscriptions SET last_ok_at = NOW() WHERE id = ?")->execute([$sub['id']]); } catch (Throwable $e) {}
            } else {
                $out['failed']++;
                $out['err'] = 'HTTP ' . $r['code'] . ' ' . mb_strimwidth((string)($r['err'] ?? $r['body'] ?? ''), 0, 120, '…');
                if (in_array($r['code'], [404, 410], true)) {
                    // อุปกรณ์ถอน permission / subscription หมดอายุ — ลบทิ้ง
                    try { $pdo->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$sub['id']]); } catch (Throwable $e) {}
                }
            }
        }
        return $out;
    }

    /** การ์ดงานซ่อม → แอป (คู่กับ line_job_notify) — fail-safe ไม่มีทาง block การบันทึกงาน */
    function push_job_notify(PDO $pdo, string $title, string $body, string $url): array {
        try {
            require_once __DIR__ . '/notify_settings.php';
            if (!notif_bool($pdo, 'notify_push_enabled', true)) return ['skipped' => 'push off'];
            if (!notif_bool($pdo, 'notify_jobs_enabled', true)) return ['skipped' => 'jobs off'];
            return push_broadcast($pdo, $title, $body, $url, 'job');
        } catch (Throwable $e) {
            return ['err' => $e->getMessage()];
        }
    }

    /** รายงานเช้า/เย็น → แอป (cron เช็คสวิตช์รอบเช้า-เย็นเองแล้ว เหลือเช็คแค่สวิตช์ push) */
    function push_report_notify(PDO $pdo, string $title, string $body): array {
        try {
            require_once __DIR__ . '/notify_settings.php';
            if (!notif_bool($pdo, 'notify_push_enabled', true)) return ['skipped' => 'push off'];
            return push_broadcast($pdo, $title, $body, '/admin/tracking/', 'report');
        } catch (Throwable $e) {
            return ['err' => $e->getMessage()];
        }
    }

} // function_exists guard
