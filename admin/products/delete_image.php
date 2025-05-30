<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$id = $_GET['id'] ?? null;
$product_id = $_GET['product_id'] ?? null;

if ($id && $product_id) {
  $stmt = $pdo->prepare("SELECT image FROM product_images WHERE id = ?");
  $stmt->execute([$id]);
  $image = $stmt->fetchColumn();

  if ($image && file_exists("../../uploads/$image")) {
    unlink("../../uploads/$image");
  }

  $del = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
  $del->execute([$id]);
}

header("Location: edit_product.php?id=" . intval($product_id));
exit;
