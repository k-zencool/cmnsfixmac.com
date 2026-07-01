<?php
/********************************************************************
 * admin/manager/process_reverse.php
 * AJAX: ย้อนกลับ (reverse) รายการใน ledger — เฉพาะ manager/super_admin
 ********************************************************************/
session_start();
require_once '../../includes/db.php';
require_once '../../includes/manager_lib.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

if (!mgr_can_control()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'เฉพาะผู้จัดการเท่านั้น']);
    exit;
}

$action_id = (int)($_POST['action_id'] ?? 0);
$note      = trim($_POST['note'] ?? '');

if (!$action_id) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบรายการ']);
    exit;
}

echo json_encode(mgr_reverse($pdo, $action_id, $note));
