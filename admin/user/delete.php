<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

$id = (int)($_GET['id'] ?? 0);
$ajax = !empty($_GET['ajax']);

if (!$id) {
    if ($ajax) { header('Content-Type: application/json'); echo '{"ok":false}'; exit; }
    header('Location: index.php'); exit;
}

if ($id === (int)$_SESSION['admin_id']) {
    if ($ajax) { header('Content-Type: application/json'); echo '{"ok":false,"msg":"cannot_delete_self"}'; exit; }
    header('Location: index.php'); exit;
}

try {
    $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = 'ลบผู้ใช้งานเรียบร้อยแล้ว';
    if ($ajax) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
} catch (PDOException $e) {
    if ($ajax) { header('Content-Type: application/json'); echo '{"ok":false}'; exit; }
}

header('Location: index.php');
exit;
