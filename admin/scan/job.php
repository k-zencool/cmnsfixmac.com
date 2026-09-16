<?php
/* =========================================================
   admin/scan/job.php — scanned sticker → job sheet data (JSON)

   The scanner opens the job sheet over the live camera instead of
   navigating away: every page load of a home-screen web app on iOS asks
   for camera permission again, so staying on the scan page is what keeps
   the prompt away. Same auth as resolve.php (login, no extra perm).

   GET ?t=<ticket_number>
     200 {ok:true,  job:{…jv_payload…}}
     200 {ok:false, reason:'unused'|'notfound'}  — scan.js falls back to resolve.php
     401 {ok:false, reason:'auth'}
   ========================================================= */
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sticker_lib.php';
require_once __DIR__ . '/../../includes/job_view_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'auth']);
    exit;
}
touch_admin_session();

$ticket = trim((string)($_GET['t'] ?? ''));
if ($ticket === '' || mb_strlen($ticket) > 50) {
    echo json_encode(['ok' => false, 'reason' => 'notfound']);
    exit;
}

$st = $pdo->prepare("SELECT * FROM tracking WHERE ticket_number = ? LIMIT 1");
$st->execute([$ticket]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['ok' => false, 'reason' => stk_parse_no($ticket) !== null ? 'unused' : 'notfound']);
    exit;
}

echo json_encode(['ok' => true, 'job' => jv_payload($row)], JSON_UNESCAPED_UNICODE);
