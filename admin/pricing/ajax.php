<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$id     = intval($_POST['id'] ?? 0);

if (!$id) { echo json_encode(['ok' => false, 'msg' => 'missing id']); exit; }

if ($action === 'toggle') {
    $field = $_POST['field'] ?? '';
    if (!in_array($field, ['is_active', 'show_on_web'], true)) {
        echo json_encode(['ok' => false, 'msg' => 'invalid field']); exit;
    }
    $pdo->prepare("UPDATE service_pricing SET `$field` = 1 - `$field`, updated_at = NOW() WHERE id = ?")->execute([$id]);
    $stmt = $pdo->prepare("SELECT `$field` FROM service_pricing WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['ok' => true, 'value' => (int)$stmt->fetchColumn()]);
    exit;
}

if ($action === 'delete') {
    $pdo->prepare("DELETE FROM service_pricing WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'unknown action']);
