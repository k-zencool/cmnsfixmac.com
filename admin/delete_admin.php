<?php
session_start();
include_once realpath(__DIR__ . '/../includes/db.php');
include_once realpath(__DIR__ . '/../includes/auth.php');

$id = $_GET['id'] ?? null;

// ป้องกันไม่ให้ลบตัวเอง
if ($id) {
  // ดึงชื่อแอดมินตาม id
  $stmt = $pdo->prepare("SELECT username FROM admin_users WHERE id = ?");
  $stmt->execute([$id]);
  $admin = $stmt->fetch();

  if ($admin && $admin['username'] !== $_SESSION['admin_username']) {
    // ลบแอดมิน
    $delete = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
    $delete->execute([$id]);
  }
}

// หลังลบ กลับไปหน้า list
header("Location: admin_list.php");
exit();
?>
