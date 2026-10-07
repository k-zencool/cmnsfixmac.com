<?php
/* =========================================================
   admin/tracking/photo_api.php — รูปเครื่องลูกค้าต่องาน (Google Drive)

   Looking needs a login only; uploading/editing/deleting needs jobs.write.
   Files live on Drive only — images reach the browser through ?action=img.

   GET  ?action=list&job=<id>                       {ok, connected, can_write, folder_url, photos:[…]}
   GET  ?action=browse[&q=][&stage=][&offset=]      {ok, connected, can_write, photos:[…], more}  every job, newest first
   GET  ?action=img&id=<photo>[&size=thumb][&dl=1]  image bytes (private, cached)
   POST action=upload  job=<id> stage=… photo=<file> {ok, photo, folder_url}
   POST action=caption id=<photo> caption=…          {ok, photo}
   POST action=move    ids[]=… stage=…               {ok, photos:[…], failed:[{id,msg}]}
   POST action=delete  ids[]=…  (or id=…)            {ok, deleted:[…], failed:[{id,msg}]}
   401 {ok:false, reason:'auth'} · 403/4xx/5xx {ok:false, msg}
   ========================================================= */
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/gdrive_lib.php';

const PHOTO_API = '/admin/tracking/photo_api.php';

function out(array $data, int $code = 200): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['admin_id'])) out(['ok' => false, 'reason' => 'auth'], 401);
touch_admin_session();
// release the session lock now — every call here waits on Drive, and a held
// lock makes a page's thumbnails load one after another ($_SESSION stays readable)
session_write_close();

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

// writes only from our own pages (fetch sends this header; a cross-site form can't)
if ($isPost && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    out(['ok' => false, 'msg' => 'bad request'], 400);
}

const PHOTO_SELECT = "
    SELECT p.*, a.username AS by_name,
           t.ticket_number, t.customer_name, t.device_type, t.device_model
    FROM tracking_photos p
    LEFT JOIN admin_users a ON a.id = p.uploaded_by
    LEFT JOIN tracking t    ON t.id = p.tracking_id";

function photo_row(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare(PHOTO_SELECT . " WHERE p.id = ?");
    $s->execute([$id]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

function photo_payload(array $p): array {
    $id = (int)$p['id'];
    return [
        'id'       => $id,
        'stage'    => $p['stage'],
        'name'     => $p['file_name'],
        'caption'  => $p['caption'] ?? '',
        'by'       => $p['by_name'] ?? null,
        'at'       => $p['created_at'],
        'job'      => [
            'id'     => (int)$p['tracking_id'],
            'ticket' => $p['ticket_number'] ?? '',
            'name'   => $p['customer_name'] ?? '',
            'device' => trim(($p['device_type'] ?? '') . ' ' . ($p['device_model'] ?? '')),
        ],
        'thumb'    => PHOTO_API . '?action=img&size=thumb&id=' . $id,
        'full'     => PHOTO_API . '?action=img&id=' . $id,
        'download' => PHOTO_API . '?action=img&dl=1&id=' . $id,
        'drive'    => gd_file_url($p['drive_file_id']),
    ];
}

/** ids[] from the request, as ints */
function req_ids(): array {
    $ids = $_POST['ids'] ?? (isset($_POST['id']) ? [$_POST['id']] : []);
    return array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
}

try {
    /* ── image proxy (Drive files are private) ── */
    if ($action === 'img') {
        $p = photo_row($pdo, (int)($_GET['id'] ?? 0));
        if (!$p) { http_response_code(404); exit; }
        $thumb = ($_GET['size'] ?? '') === 'thumb';
        [$bytes, $mime] = gd_fetch_image($pdo, $p['drive_file_id'], $thumb);
        // session_start() already sent no-store headers — drop them or the browser never caches
        header_remove('Cache-Control'); header_remove('Pragma'); header_remove('Expires');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($bytes));
        // a photo id never points at different bytes → cache for good, but only in this browser
        header('Cache-Control: private, max-age=31536000, immutable');
        if (!empty($_GET['dl'])) {
            header("Content-Disposition: attachment; filename=\"photo-{$p['id']}.jpg\"; filename*=UTF-8''" . rawurlencode($p['file_name']));
        }
        echo $bytes;
        exit;
    }

    /* ── one job ── */
    if ($action === 'list') {
        $jobId = (int)($_GET['job'] ?? 0);
        $s = $pdo->prepare(PHOTO_SELECT . " WHERE p.tracking_id = ? ORDER BY p.created_at, p.id");
        $s->execute([$jobId]);
        $folder = gd_job_folder_id($pdo, $jobId);
        out([
            'ok'         => true,
            'connected'  => gd_is_connected($pdo),
            'can_write'  => can('jobs.write'),
            'folder_url' => $folder ? gd_folder_url($folder) : null,
            'photos'     => array_map('photo_payload', $s->fetchAll(PDO::FETCH_ASSOC)),
        ]);
    }

    /* ── every job, newest first (settings → รูปเครื่องลูกค้า) ── */
    if ($action === 'browse') {
        $limit  = 60;
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $where  = [];
        $args   = [];
        $stage  = $_GET['stage'] ?? '';
        if (isset(gd_stages()[$stage])) { $where[] = 'p.stage = ?'; $args[] = $stage; }
        $q = trim($_GET['q'] ?? '');
        if ($q !== '') {
            $where[] = "(t.ticket_number LIKE ? OR t.customer_name LIKE ? OR t.customer_phone LIKE ?
                         OR t.device_model LIKE ? OR p.caption LIKE ?)";
            array_push($args, ...array_fill(0, 5, '%' . $q . '%'));
        }
        $sql = PHOTO_SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY p.created_at DESC, p.id DESC LIMIT " . ($limit + 1) . " OFFSET $offset";
        $s = $pdo->prepare($sql);
        $s->execute($args);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        out([
            'ok'        => true,
            'connected' => gd_is_connected($pdo),
            'can_write' => can('jobs.write'),
            'more'      => count($rows) > $limit,
            'photos'    => array_map('photo_payload', array_slice($rows, 0, $limit)),
        ]);
    }

    if (!$isPost) out(['ok' => false, 'msg' => 'unknown action'], 400);
    if (!can('jobs.write')) out(['ok' => false, 'msg' => 'ไม่มีสิทธิ์จัดการรูปงานซ่อม'], 403);

    $stages = gd_stages();

    /* ── upload one photo ── */
    if ($action === 'upload') {
        $stage = $_POST['stage'] ?? '';
        if (!isset($stages[$stage])) out(['ok' => false, 'msg' => 'ขั้นตอนไม่ถูกต้อง'], 400);

        $s = $pdo->prepare("SELECT id, ticket_number, customer_name, device_type, device_model FROM tracking WHERE id = ?");
        $s->execute([(int)($_POST['job'] ?? 0)]);
        $job = $s->fetch(PDO::FETCH_ASSOC);
        if (!$job) out(['ok' => false, 'msg' => 'ไม่พบงานซ่อม'], 404);

        $f = $_FILES['photo'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            $big = $f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            out(['ok' => false, 'msg' => $big ? 'ไฟล์ใหญ่เกินที่เซิร์ฟเวอร์รับ' : 'ไม่ได้รับไฟล์'], 400);
        }
        // the page re-encodes every photo as JPEG before sending; check the bytes, not the name
        $info = @getimagesize($f['tmp_name']);
        $mimeOk = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!$info || !isset($mimeOk[$info['mime']])) out(['ok' => false, 'msg' => 'ไฟล์นี้ไม่ใช่รูป JPG/PNG/WebP'], 400);
        if ($f['size'] > 15 * 1024 * 1024) out(['ok' => false, 'msg' => 'รูปใหญ่เกิน 15MB'], 400);

        $n = $pdo->prepare("SELECT COUNT(*) FROM tracking_photos WHERE tracking_id = ? AND stage = ?");
        $n->execute([$job['id'], $stage]);
        $name = sprintf('%s_%s_%s_%02d.%s',
            preg_replace('/[^\w-]+/u', '', $job['ticket_number']) ?: 'job' . $job['id'],
            $stages[$stage], date('Ymd-His'), (int)$n->fetchColumn() + 1, $mimeOk[$info['mime']]);

        $folder = gd_stage_folder($pdo, $job, $stage);
        $fileId = gd_upload($pdo, $folder, $name, file_get_contents($f['tmp_name']), $info['mime']);

        $pdo->prepare("INSERT INTO tracking_photos
                (tracking_id, stage, drive_file_id, file_name, size_bytes, width, height, uploaded_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$job['id'], $stage, $fileId, $name, (int)$f['size'],
                       min(65535, (int)$info[0]), min(65535, (int)$info[1]), (int)$_SESSION['admin_id'],
                       date('Y-m-d H:i:s')]);   // shop time, not the DB server's clock
        $p = photo_row($pdo, (int)$pdo->lastInsertId());
        out(['ok' => true, 'photo' => photo_payload($p), 'folder_url' => gd_folder_url(gd_job_folder_id($pdo, (int)$job['id']))]);
    }

    /* ── note under a photo (also the Drive file's description) ── */
    if ($action === 'caption') {
        $p = photo_row($pdo, (int)($_POST['id'] ?? 0));
        if (!$p) out(['ok' => false, 'msg' => 'ไม่พบรูปนี้'], 404);
        $cap = mb_substr(trim(preg_replace('/\s+/u', ' ', $_POST['caption'] ?? '')), 0, 500);
        $pdo->prepare("UPDATE tracking_photos SET caption = ? WHERE id = ?")->execute([$cap !== '' ? $cap : null, $p['id']]);
        try { gd_update_file($pdo, $p['drive_file_id'], ['description' => $cap]); }
        catch (RuntimeException $e) { /* the note is ours; Drive's copy is a nicety */ }
        out(['ok' => true, 'photo' => photo_payload(photo_row($pdo, (int)$p['id']))]);
    }

    /* ── move to another stage: Drive sub-folder + the stage word in the file name ── */
    if ($action === 'move') {
        $to = $_POST['stage'] ?? '';
        if (!isset($stages[$to])) out(['ok' => false, 'msg' => 'ขั้นตอนไม่ถูกต้อง'], 400);
        $done = []; $failed = [];
        foreach (req_ids() as $id) {
            $p = photo_row($pdo, $id);
            if (!$p) continue;
            if ($p['stage'] === $to) { $done[] = photo_payload($p); continue; }
            try {
                $job = ['id' => $p['tracking_id'], 'ticket_number' => $p['ticket_number'],
                        'customer_name' => $p['customer_name'], 'device_type' => $p['device_type'], 'device_model' => $p['device_model']];
                $name = str_replace('_' . $stages[$p['stage']] . '_', '_' . $stages[$to] . '_', $p['file_name']);
                gd_update_file($pdo, $p['drive_file_id'], ['name' => $name], gd_stage_folder($pdo, $job, $to));
                $pdo->prepare("UPDATE tracking_photos SET stage = ?, file_name = ? WHERE id = ?")->execute([$to, $name, $id]);
                $done[] = photo_payload(photo_row($pdo, $id));
            } catch (RuntimeException $e) {
                $failed[] = ['id' => $id, 'msg' => $e->getMessage()];
            }
        }
        out(['ok' => !$failed, 'photos' => $done, 'failed' => $failed,
             'msg' => $failed ? 'ย้ายไม่สำเร็จ ' . count($failed) . ' รูป: ' . $failed[0]['msg'] : null]);
    }

    /* ── delete (Drive trash, recoverable 30 days) ── */
    if ($action === 'delete') {
        $deleted = []; $failed = [];
        foreach (req_ids() as $id) {
            $p = photo_row($pdo, $id);
            if (!$p) { $deleted[] = $id; continue; }   // already gone
            try {
                gd_trash($pdo, $p['drive_file_id']);
                $pdo->prepare("DELETE FROM tracking_photos WHERE id = ?")->execute([$id]);
                $deleted[] = $id;
            } catch (RuntimeException $e) {
                $failed[] = ['id' => $id, 'msg' => $e->getMessage()];
            }
        }
        out(['ok' => !$failed, 'deleted' => $deleted, 'failed' => $failed,
             'msg' => $failed ? 'ลบไม่สำเร็จ ' . count($failed) . ' รูป: ' . $failed[0]['msg'] : null]);
    }

    out(['ok' => false, 'msg' => 'unknown action'], 400);

} catch (RuntimeException $e) {
    if ($action === 'img') { http_response_code(502); exit; }
    out(['ok' => false, 'msg' => $e->getMessage()], 502);
}
