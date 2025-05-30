<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$id = $_GET['id'] ?? null;

if ($id) {
  // ดึงชื่อไฟล์ภาพก่อนลบ
  $stmt = $pdo->prepare("SELECT image FROM repairs WHERE id = ?");
  $stmt->execute([$id]);
  $image = $stmt->fetchColumn();

  // ลบภาพจากโฟลเดอร์ uploads
  if ($image && file_exists("../../uploads/$image")) {
    unlink("../../uploads/$image");
  }

  // ลบข้อมูลจากฐานข้อมูล
  $delete = $pdo->prepare("DELETE FROM repairs WHERE id = ?");
  $delete->execute([$id]);
}

// กลับไปยังหน้าหลัก
header("Location: index.php");
exit;
