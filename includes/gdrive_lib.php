<?php
/**
 * includes/gdrive_lib.php — Google Drive (OAuth + Drive v3) สำหรับรูปเครื่องลูกค้า
 *
 * ทำมือด้วย curl (ไม่ใช้ google/apiclient — หนักเกินสำหรับ FTP deploy)
 * scope = drive.file → เว็บเห็น/แก้ได้แค่ไฟล์ที่เว็บสร้างเอง ไม่แตะไฟล์อื่นใน Drive
 *
 * config อยู่ใน gdrive_config (id = 1): client id/secret (fallback .env
 * GDRIVE_CLIENT_ID / GDRIVE_CLIENT_SECRET), refresh token ของบัญชีที่เชื่อม,
 * access token ที่ cache ไว้ + โฟลเดอร์ราก "CMNS Job Photos"
 *
 * standalone + function_exists guards (include ซ้ำได้)
 */

if (!function_exists('gd_cfg')) {

    define('GD_SCOPE',       'https://www.googleapis.com/auth/drive.file');
    define('GD_ROOT_NAME',   'CMNS Job Photos');
    define('GD_FOLDER_MIME', 'application/vnd.google-apps.folder');

    /** stage key => Thai label (ใช้ทั้งชื่อไฟล์และ UI) */
    function gd_stages(): array {
        return ['intake' => 'รับเครื่อง', 'repair' => 'ระหว่างซ่อม', 'return' => 'ส่งคืน'];
    }

    function gd_cfg(PDO $pdo): array {
        $r = $pdo->query("SELECT * FROM gdrive_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
        $r['client_id']     = ($r['client_id'] ?? '')     ?: ($_ENV['GDRIVE_CLIENT_ID'] ?? '');
        $r['client_secret'] = ($r['client_secret'] ?? '') ?: ($_ENV['GDRIVE_CLIENT_SECRET'] ?? '');
        return $r;
    }

    function gd_set(PDO $pdo, array $fields): void {
        $pdo->exec("INSERT IGNORE INTO gdrive_config (id) VALUES (1)");
        $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
        $pdo->prepare("UPDATE gdrive_config SET $sets WHERE id = 1")->execute(array_values($fields));
    }

    function gd_is_connected(PDO $pdo): bool {
        try {
            $c = gd_cfg($pdo);
            return !empty($c['refresh_token']) && $c['client_id'] !== '' && $c['client_secret'] !== '';
        } catch (Exception $e) {
            return false;   // table not migrated yet
        }
    }

    /** callback URL ของเครื่องที่เปิดอยู่ (localhost:8000 / โดเมนจริง) — ต้องตรงกับที่ใส่ใน Google Cloud */
    function gd_redirect_uri(): string {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
              || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return ($https ? 'https' : 'http') . '://' . $host . '/admin/settings/gdrive_callback.php';
    }

    function gd_auth_url(PDO $pdo, string $state): string {
        $c = gd_cfg($pdo);
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $c['client_id'],
            'redirect_uri'  => gd_redirect_uri(),
            'response_type' => 'code',
            'scope'         => GD_SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',      // always hand back a refresh token
            'state'         => $state,
        ]);
    }

    /**
     * low-level HTTP — returns [status, decoded JSON|null, raw body]
     * $body: array = form-encoded, string = sent as-is (set Content-Type in $headers)
     */
    function gd_http(string $method, string $url, ?string $token = null, $body = null, array $headers = [], int $timeout = 30): array {
        $ch = curl_init($url);
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;
        if (is_array($body)) $body = http_build_query($body);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($raw === false) return [0, null, $err];
        return [$code, json_decode($raw, true), $raw];
    }

    /** Google error payload → short readable text */
    function gd_err_text(?array $j, string $raw = ''): string {
        if (isset($j['error']['message'])) return (string)$j['error']['message'];
        if (isset($j['error_description'])) return (string)$j['error_description'];
        if (isset($j['error']) && is_string($j['error'])) return $j['error'];
        return mb_substr($raw, 0, 200) ?: 'ไม่มีคำตอบจาก Google';
    }

    /** OAuth code → tokens, saved. Returns null on success, error text otherwise. */
    function gd_exchange_code(PDO $pdo, string $code, int $adminId): ?string {
        $c = gd_cfg($pdo);
        [$st, $j, $raw] = gd_http('POST', 'https://oauth2.googleapis.com/token', null, [
            'code'          => $code,
            'client_id'     => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'redirect_uri'  => gd_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ]);
        if ($st !== 200 || empty($j['access_token'])) return gd_err_text($j, $raw);
        if (empty($j['refresh_token'])) return 'Google ไม่ส่ง refresh token กลับมา — ลองกดเชื่อมใหม่';

        $email = null;
        [$st2, $about] = gd_http('GET', 'https://www.googleapis.com/drive/v3/about?fields=user(emailAddress)', $j['access_token']);
        if ($st2 === 200) $email = $about['user']['emailAddress'] ?? null;

        gd_set($pdo, [
            'refresh_token'     => $j['refresh_token'],
            'access_token'      => $j['access_token'],
            'access_expires_at' => date('Y-m-d H:i:s', time() + (int)($j['expires_in'] ?? 3600) - 60),
            'account_email'     => $email,
            'root_folder_id'    => null,          // new account → new root folder
            'connected_at'      => date('Y-m-d H:i:s'),
            'connected_by'      => $adminId,
            'last_error'        => null,
        ]);
        return null;
    }

    /** a valid access token (refreshing when needed) — throws RuntimeException */
    function gd_token(PDO $pdo): string {
        $c = gd_cfg($pdo);
        if (empty($c['refresh_token'])) throw new RuntimeException('ยังไม่ได้เชื่อม Google Drive');
        if (!empty($c['access_token']) && strtotime($c['access_expires_at'] ?? '') > time()) {
            return $c['access_token'];
        }
        [$st, $j, $raw] = gd_http('POST', 'https://oauth2.googleapis.com/token', null, [
            'client_id'     => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'refresh_token' => $c['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]);
        if ($st !== 200 || empty($j['access_token'])) {
            $msg = gd_err_text($j, $raw);
            // invalid_grant = revoked / password changed / app left in "Testing" (7-day tokens)
            if (($j['error'] ?? '') === 'invalid_grant') {
                gd_set($pdo, ['refresh_token' => null, 'access_token' => null,
                              'last_error' => 'สิทธิ์ Google Drive หมดอายุหรือถูกยกเลิก — ต้องกดเชื่อมใหม่']);
                throw new RuntimeException('สิทธิ์ Google Drive หมดอายุ — ให้เจ้าของกดเชื่อมใหม่ในหน้าตั้งค่า');
            }
            gd_set($pdo, ['last_error' => mb_substr('refresh: ' . $msg, 0, 500)]);
            throw new RuntimeException('ต่อ Google ไม่ได้: ' . $msg);
        }
        gd_set($pdo, [
            'access_token'      => $j['access_token'],
            'access_expires_at' => date('Y-m-d H:i:s', time() + (int)($j['expires_in'] ?? 3600) - 60),
        ]);
        return $j['access_token'];
    }

    /** Drive JSON call with the shop token — throws on non-2xx */
    function gd_api(PDO $pdo, string $method, string $path, $json = null, array $query = []): array {
        $url = 'https://www.googleapis.com/drive/v3/' . $path . ($query ? '?' . http_build_query($query) : '');
        [$st, $j, $raw] = gd_http($method, $url, gd_token($pdo),
            $json === null ? null : json_encode($json, JSON_UNESCAPED_UNICODE),
            $json === null ? [] : ['Content-Type: application/json; charset=UTF-8']);
        if ($st < 200 || $st >= 300) throw new RuntimeException(gd_err_text($j, $raw), $st);
        return $j ?? [];
    }

    function gd_create_folder(PDO $pdo, string $name, ?string $parent = null): string {
        $meta = ['name' => $name, 'mimeType' => GD_FOLDER_MIME];
        if ($parent) $meta['parents'] = [$parent];
        return gd_api($pdo, 'POST', 'files', $meta, ['fields' => 'id'])['id'];
    }

    /** true if the folder is still there and not in the trash */
    function gd_folder_alive(PDO $pdo, string $id): bool {
        try {
            $f = gd_api($pdo, 'GET', 'files/' . rawurlencode($id), null, ['fields' => 'id,trashed']);
            return empty($f['trashed']);
        } catch (RuntimeException $e) {
            if ($e->getCode() === 404) return false;
            throw $e;
        }
    }

    function gd_root_folder(PDO $pdo): string {
        $id = gd_cfg($pdo)['root_folder_id'] ?? '';
        if ($id && gd_folder_alive($pdo, $id)) return $id;
        $id = gd_create_folder($pdo, GD_ROOT_NAME);
        gd_set($pdo, ['root_folder_id' => $id]);
        return $id;
    }

    function gd_job_folder_name(array $job): string {
        $parts = array_filter([
            $job['ticket_number'] ?? '',
            trim((string)($job['customer_name'] ?? '')),
            trim(($job['device_type'] ?? '') . ' ' . ($job['device_model'] ?? '')),
        ], fn($s) => $s !== '' && $s !== '-');
        return mb_substr(preg_replace('/[\\\\\/:*?"<>|\r\n]+/u', ' ', implode(' - ', $parts)), 0, 120);
    }

    /** a saved folder id — stage '' = the job folder itself; null when none was made yet */
    function gd_job_folder_id(PDO $pdo, int $jobId, string $stage = ''): ?string {
        $s = $pdo->prepare("SELECT folder_id FROM tracking_drive_folders WHERE tracking_id = ? AND stage = ?");
        $s->execute([$jobId, $stage]);
        return $s->fetchColumn() ?: null;
    }

    function gd_save_folder(PDO $pdo, int $jobId, string $stage, string $id): void {
        $pdo->prepare("REPLACE INTO tracking_drive_folders (tracking_id, stage, folder_id) VALUES (?, ?, ?)")
            ->execute([$jobId, $stage, $id]);
    }

    /** sub-folder name — numbered so Drive's A-Z sort keeps the work order */
    function gd_stage_folder_name(string $stage): string {
        $i = array_search($stage, array_keys(gd_stages()), true);
        return ($i + 1) . '. ' . gd_stages()[$stage];
    }

    /**
     * the job's folder — made (or re-made if someone trashed it) on demand,
     * always together with its 3 stage sub-folders
     */
    function gd_job_folder(PDO $pdo, array $job): string {
        $jobId = (int)$job['id'];
        $id = gd_job_folder_id($pdo, $jobId);
        if ($id && gd_folder_alive($pdo, $id)) return $id;
        $id = gd_create_folder($pdo, gd_job_folder_name($job), gd_root_folder($pdo));
        gd_save_folder($pdo, $jobId, '', $id);
        foreach (array_keys(gd_stages()) as $st) {
            gd_save_folder($pdo, $jobId, $st, gd_create_folder($pdo, gd_stage_folder_name($st), $id));
        }
        return $id;
    }

    /** the stage sub-folder the photo goes in */
    function gd_stage_folder(PDO $pdo, array $job, string $stage): string {
        $jobId = (int)$job['id'];
        $id = gd_job_folder_id($pdo, $jobId, $stage);
        if ($id && gd_folder_alive($pdo, $id)) return $id;
        $parent = gd_job_folder($pdo, $job);          // a brand-new job folder comes with all 3
        // fill in whichever sub-folders are missing, so the job always shows all 3
        foreach (array_keys(gd_stages()) as $st) {
            $sid = gd_job_folder_id($pdo, $jobId, $st);
            if ($sid && ($st !== $stage || gd_folder_alive($pdo, $sid))) continue;
            gd_save_folder($pdo, $jobId, $st, gd_create_folder($pdo, gd_stage_folder_name($st), $parent));
        }
        return gd_job_folder_id($pdo, $jobId, $stage);
    }

    /** multipart upload — returns Drive file id */
    function gd_upload(PDO $pdo, string $folderId, string $name, string $bytes, string $mime): string {
        $boundary = 'cmns' . bin2hex(random_bytes(8));
        $meta = json_encode(['name' => $name, 'parents' => [$folderId]], JSON_UNESCAPED_UNICODE);
        $body = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n"
              . "--$boundary\r\nContent-Type: $mime\r\n\r\n$bytes\r\n--$boundary--";
        [$st, $j, $raw] = gd_http('POST',
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id',
            gd_token($pdo), $body, ['Content-Type: multipart/related; boundary=' . $boundary], 120);
        if ($st !== 200 || empty($j['id'])) throw new RuntimeException('อัปขึ้น Drive ไม่สำเร็จ: ' . gd_err_text($j, $raw), $st);
        return $j['id'];
    }

    /** rename / re-describe / move a file in one call */
    function gd_update_file(PDO $pdo, string $fileId, array $meta, ?string $toFolder = null): void {
        $q = ['fields' => 'id'];
        if ($toFolder) {
            $f = gd_api($pdo, 'GET', 'files/' . rawurlencode($fileId), null, ['fields' => 'parents']);
            $q['addParents']    = $toFolder;
            $q['removeParents'] = implode(',', array_diff($f['parents'] ?? [], [$toFolder]));
        }
        gd_api($pdo, 'PATCH', 'files/' . rawurlencode($fileId), $meta ?: new stdClass(), $q);
    }

    /** move to Drive trash (recoverable for 30 days); a file that's already gone counts as done */
    function gd_trash(PDO $pdo, string $fileId): void {
        try {
            gd_api($pdo, 'PATCH', 'files/' . rawurlencode($fileId), ['trashed' => true], ['fields' => 'id']);
        } catch (RuntimeException $e) {
            if ($e->getCode() !== 404) throw $e;
        }
    }

    /**
     * image bytes for the admin proxy — [bytes, mime]
     * $thumb: Drive's own thumbnail at ~$px wide (falls back to the full file
     * while Drive is still making it, right after an upload)
     */
    function gd_fetch_image(PDO $pdo, string $fileId, bool $thumb, int $px = 480): array {
        $token = gd_token($pdo);
        if ($thumb) {
            $f = gd_api($pdo, 'GET', 'files/' . rawurlencode($fileId), null, ['fields' => 'thumbnailLink']);
            if (!empty($f['thumbnailLink'])) {
                $url = preg_replace('/=s\d+$/', '=s' . $px, $f['thumbnailLink']);
                [$st, , $raw] = gd_http('GET', $url, $token);
                if ($st === 200 && $raw !== '') return [$raw, 'image/jpeg'];
            }
        }
        [$st, $j, $raw] = gd_http('GET',
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?alt=media', $token, null, [], 60);
        if ($st !== 200) throw new RuntimeException(gd_err_text($j, $raw), $st);
        return [$raw, 'image/jpeg'];
    }

    /** storage used/limit of the connected account (bytes) — null if unknown */
    function gd_quota(PDO $pdo): ?array {
        $a = gd_api($pdo, 'GET', 'about', null, ['fields' => 'storageQuota,user(emailAddress)']);
        $q = $a['storageQuota'] ?? null;
        if (!$q) return null;
        return ['used' => (int)($q['usage'] ?? 0), 'limit' => isset($q['limit']) ? (int)$q['limit'] : null,
                'email' => $a['user']['emailAddress'] ?? null];
    }

    /** revoke + forget the connection (client id/secret stay) */
    function gd_disconnect(PDO $pdo): void {
        $c = gd_cfg($pdo);
        if (!empty($c['refresh_token'])) {
            gd_http('POST', 'https://oauth2.googleapis.com/revoke', null, ['token' => $c['refresh_token']]);
        }
        gd_set($pdo, ['refresh_token' => null, 'access_token' => null, 'access_expires_at' => null,
                      'account_email' => null, 'root_folder_id' => null, 'last_error' => null]);
    }

    function gd_folder_url(string $id): string {
        return 'https://drive.google.com/drive/folders/' . rawurlencode($id);
    }

    function gd_file_url(string $id): string {
        return 'https://drive.google.com/file/d/' . rawurlencode($id) . '/view';
    }
}
