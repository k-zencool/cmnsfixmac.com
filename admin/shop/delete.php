<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID']); exit; }

// Get listing images before delete (for disk cleanup)
$imgs = $pdo->prepare("SELECT url FROM shop_images WHERE listing_id = ?");
$imgs->execute([$id]);
$imgUrls = $imgs->fetchAll(PDO::FETCH_COLUMN);

$del = $pdo->prepare("DELETE FROM shop_listings WHERE id = ?");
$del->execute([$id]);

if ($del->rowCount()) {
    // Delete image files from disk
    $root = realpath(__DIR__ . '/../../uploads');
    foreach ($imgUrls as $url) {
        $abs = realpath(__DIR__ . '/../../' . ltrim($url, '/'));
        if ($abs && $root && str_starts_with($abs, $root)) @unlink($abs);
    }
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบรายการ']);
}
