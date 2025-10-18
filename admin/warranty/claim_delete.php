<?php
/********************************************************************
 * admin/warranty/claim_delete.php — ลบเคลม (POST เท่านั้น)
 ********************************************************************/
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_perms(['warranty.claims.delete']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php?tab=claims&err=วิธีการเรียกไม่ถูกต้อง'); exit;
}

$id = (int)($_POST['id'] ?? 0);
$back = trim($_POST['back'] ?? 'index.php?tab=claims');

if ($id <= 0) { header("Location: {$back}&err=ไม่พบเคลมที่จะลบ"); exit; }

// เก็บ job_id ไว้เผื่อเด้งกลับหน้ารายการของงาน
$st = $pdo->prepare("SELECT job_id FROM warranty_claims WHERE id=:id");
$st->execute([':id'=>$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
$job_id = $row ? (int)$row['job_id'] : 0;

$st = $pdo->prepare("DELETE FROM warranty_claims WHERE id=:id");
$st->execute([':id'=>$id]);

if ($back === 'job' && $job_id > 0) {
  header("Location: job_view.php?id={$job_id}&msg=ลบเคลมแล้ว");
} else {
  header("Location: index.php?tab=claims&msg=ลบเคลมแล้ว");
}
exit;
