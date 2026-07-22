<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_perms_json(['users.manage']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'CSRF token ไม่ถูกต้อง']);
    exit;
}

$sessionId = (int)($_POST['session_id'] ?? 0);
if (!$sessionId) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบ session ที่ต้องการ']);
    exit;
}

$stmt = $pdo->prepare("SELECT admin_id, session_hash FROM admin_sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบ session นี้ในระบบ']);
    exit;
}

if (hash_equals($target['session_hash'], hash('sha256', session_id()))) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่สามารถบังคับออกจาก session ของตัวเองได้ ใช้ปุ่มออกจากระบบแทน']);
    exit;
}

$pdo->prepare("UPDATE admin_sessions SET revoked_at = NOW(), revoked_by = ? WHERE id = ? AND revoked_at IS NULL")
    ->execute([(int)$_SESSION['admin_id'], $sessionId]);

echo json_encode(['ok' => true]);
