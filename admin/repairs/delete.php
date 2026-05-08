<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

try {
    $pdo->beginTransaction();

    // Get all image paths (repair_images + legacy repairs.image)
    $img_rows = $pdo->prepare("SELECT image_path FROM repair_images WHERE repair_id = ?");
    $img_rows->execute([$id]);
    $paths = $img_rows->fetchAll(PDO::FETCH_COLUMN);

    $legacy = $pdo->prepare("SELECT image FROM repairs WHERE id = ?");
    $legacy->execute([$id]);
    $legacy_img = $legacy->fetchColumn();
    if ($legacy_img) $paths[] = $legacy_img;

    // Delete DB records (repair_images cascade on DELETE of repairs)
    $pdo->prepare("DELETE FROM repairs WHERE id = ?")->execute([$id]);
    $pdo->commit();

    // Delete image files
    $upload_root = realpath(__DIR__ . '/../../uploads');
    foreach ($paths as $path) {
        if (!$path) continue;
        $abs = realpath(__DIR__ . '/../../' . ltrim($path, '/'));
        if ($abs && $upload_root && str_starts_with($abs, $upload_root)) {
            @unlink($abs);
        }
    }

    $_SESSION['flash'] = 'ลบผลงานเรียบร้อยแล้ว';
    if (!empty($_GET['ajax'])) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('repairs/delete: ' . $e->getMessage());
    if (!empty($_GET['ajax'])) { header('Content-Type: application/json'); echo '{"ok":false}'; exit; }
}

header('Location: index.php');
exit;
