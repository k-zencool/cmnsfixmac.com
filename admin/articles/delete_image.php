<?php
require_once '../../includes/db.php';

if (!isset($_GET['id']) || !isset($_GET['article_id'])) {
  die('ข้อมูลไม่ครบ');
}

$imageId = intval($_GET['id']);
$articleId = intval($_GET['article_id']);

// ดึง path ไฟล์
$stmt = $pdo->prepare("SELECT image_path FROM article_images WHERE id = ?");
$stmt->execute([$imageId]);
$image = $stmt->fetch();

if ($image) {
  @unlink('../../uploads/' . $image['image_path']);
  $pdo->prepare("DELETE FROM article_images WHERE id = ?")->execute([$imageId]);
}

header("Location: edit.php?id=$articleId");
exit;
?>
