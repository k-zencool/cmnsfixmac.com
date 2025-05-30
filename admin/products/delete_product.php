<?php
session_start();
include_once __DIR__ . '/../../includes/db.php';
include_once __DIR__ . '/../../includes/auth.php';

$id = $_GET['id'] ?? null;

if ($id) {
    // ลบรูปหลักจากโฟลเดอร์ uploads (ถ้ามี)
    $stmt = $pdo->prepare("SELECT main_image FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if ($product && !empty($product['main_image'])) {
        $imagePath = __DIR__ . '/../../uploads/' . $product['main_image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    // ลบรูปเสริมจากฐานข้อมูลและไฟล์
    $stmt = $pdo->prepare("SELECT image FROM product_images WHERE product_id = ?");
    $stmt->execute([$id]);
    $images = $stmt->fetchAll();

    foreach ($images as $img) {
        $imgPath = __DIR__ . '/../../uploads/' . $img['image'];
        if (file_exists($imgPath)) {
            unlink($imgPath);
        }
    }

    $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
}

header("Location: index.php");
exit;
