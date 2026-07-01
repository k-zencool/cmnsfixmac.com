<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function json_out(array $d): void { header('Content-Type: application/json'); echo json_encode($d); exit; }
function redirect_back(): void {
    $url = $_POST['_redirect'] ?? 'index.php';
    header('Location: ' . $url);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// การเขียนทั้งหมดต้องมี content.write (lookup เป็น read เปิดให้ทุกยศ)
if (in_array($action, ['create_warranty', 'add_claim', 'edit_claim', 'void_warranty'], true)) {
    require_perms_json(['content.write']);
}

// ── GET: lookup tracking job ──────────────────────────────────────
if ($action === 'lookup_tracking') {
    $q = trim($_GET['q'] ?? '');
    if (!$q) json_out(['ok' => false]);
    $row = $pdo->prepare("SELECT id, ticket_number, customer_name, customer_phone,
                           CONCAT(COALESCE(device_type,''), ' ', COALESCE(device_model,'')) AS device_model,
                           serial_number AS serial_no
                           FROM tracking WHERE ticket_number = ? OR id = ? LIMIT 1");
    $row->execute([$q, is_numeric($q) ? (int)$q : 0]);
    $t = $row->fetch(PDO::FETCH_ASSOC);
    if ($t) json_out(['ok' => true] + $t);
    json_out(['ok' => false]);
}

// ── POST actions ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

if ($action === 'create_warranty') {
    $tid     = (int)($_POST['tracking_id'] ?? 0) ?: null;
    $cname   = trim($_POST['customer_name'] ?? '');
    $cphone  = trim($_POST['customer_phone'] ?? '');
    $device  = trim($_POST['device_model'] ?? '');
    $serial  = trim($_POST['serial_no'] ?? '');
    $summary = trim($_POST['repair_summary'] ?? '');
    $w_days  = (int)($_POST['warranty_days'] ?? 90);
    $start   = $_POST['start_date'] ?? date('Y-m-d');
    $end     = date('Y-m-d', strtotime("$start +$w_days days"));

    if (!$cname || !$device) {
        json_out(['ok' => false, 'msg' => 'กรุณากรอกชื่อลูกค้าและรุ่นเครื่อง']);
    }
    $wno = w_next_warranty_no($pdo);
    $pdo->prepare("INSERT INTO warranties
                   (warranty_no, tracking_id, customer_name, customer_phone,
                    device_model, serial_no, repair_summary, warranty_days, start_date, end_date, created_by)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$wno, $tid, $cname, $cphone, $device, $serial, $summary, $w_days, $start, $end,
                   $_SESSION['user_id'] ?? null]);
    $new_id = $pdo->lastInsertId();
    json_out(['ok' => true, 'warranty_no' => $wno, 'id' => $new_id,
              'view_url'  => "/admin/warranty/view.php?id=$new_id",
              'print_url' => "/admin/warranty/print.php?id=$new_id"]);
}

if ($action === 'add_claim') {
    $wid        = (int)($_POST['warranty_id'] ?? 0);
    $claim_date = $_POST['claim_date'] ?? date('Y-m-d');
    $issue      = trim($_POST['issue_desc'] ?? '');
    $status     = $_POST['status'] ?? 'pending';
    $resolution = trim($_POST['resolution'] ?? '');
    $cno        = w_next_claim_no($pdo);

    $pdo->prepare("INSERT INTO warranty_claims
                   (claim_no, warranty_id, claim_date, issue_desc, status, resolution, handled_by)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute([$cno, $wid, $claim_date, $issue, $status, $resolution, $_SESSION['user_id'] ?? null]);

    $_SESSION['success'] = "บันทึกการเคลม $cno เรียบร้อย";
    redirect_back();
}

if ($action === 'edit_claim') {
    $cid        = (int)($_POST['claim_id'] ?? 0);
    $issue      = trim($_POST['issue_desc'] ?? '');
    $status     = $_POST['status'] ?? 'pending';
    $resolution = trim($_POST['resolution'] ?? '');
    $resolved   = in_array($status, ['resolved','rejected']) ? date('Y-m-d H:i:s') : null;

    $pdo->prepare("UPDATE warranty_claims
                   SET issue_desc=?, status=?, resolution=?, resolved_at=?, handled_by=?, updated_at=NOW()
                   WHERE id=?")
        ->execute([$issue, $status, $resolution, $resolved, $_SESSION['user_id'] ?? null, $cid]);

    $_SESSION['success'] = "อัปเดตการเคลมเรียบร้อย";
    redirect_back();
}

if ($action === 'void_warranty') {
    $wid    = (int)($_POST['warranty_id'] ?? 0);
    $reason = trim($_POST['void_reason'] ?? '');
    $pdo->prepare("UPDATE warranties SET status='voided', void_reason=?, updated_at=NOW() WHERE id=?")
        ->execute([$reason, $wid]);
    $_SESSION['success'] = "ยกเลิกใบประกันเรียบร้อย";
    redirect_back();
}
