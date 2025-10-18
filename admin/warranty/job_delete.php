<?php
/********************************************************************
 * admin/warranty/job_delete.php — ลบงานประกัน (ต้องหมดประกัน และไม่มีเคลมผูก)
 ********************************************************************/
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();
require_perms(['warranty.jobs.delete']);

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  header('Location: index.php?tab=jobs&err='.rawurlencode('คำขอไม่ถูกต้อง')); exit;
}

// โหลดข้อมูลงาน
$st = $pdo->prepare("
  SELECT id, warranty_no, warranty_status, DATEDIFF(warranty_until, CURDATE()) AS days_left
  FROM warranty_jobs
  WHERE id=:id
");
$st->execute([':id'=>$id]);
$job = $st->fetch(PDO::FETCH_ASSOC);
if (!$job) {
  header('Location: index.php?tab=jobs&err='.rawurlencode('ไม่พบน้ำหนักงานประกันนี้')); exit;
}

// ตรวจเงื่อนไข “ต้องหมดประกันก่อน”
$daysLeft = isset($job['days_left']) ? (int)$job['days_left'] : null;
$isExpired = ($daysLeft !== null && $daysLeft < 0) || in_array((string)$job['warranty_status'], ['expired','void'], true);
if (!$isExpired) {
  header('Location: job_view.php?id='.$id.'&err='.rawurlencode('ต้องหมดประกันก่อนถึงจะลบได้'));
  exit;
}

// กันลบถ้ามีเคลมผูกอยู่
$stc = $pdo->prepare("SELECT COUNT(*) FROM warranty_claims WHERE job_id=:id");
$stc->execute([':id'=>$id]);
$claimCount = (int)$stc->fetchColumn();
if ($claimCount > 0) {
  header('Location: job_view.php?id='.$id.'&err='.rawurlencode('ลบไม่ได้: มีเคลมผูกอยู่ ('.$claimCount.' รายการ)'));
  exit;
}

// ลบจริง
$del = $pdo->prepare("DELETE FROM warranty_jobs WHERE id=:id");
$del->execute([':id'=>$id]);

header('Location: index.php?tab=jobs&msg='.rawurlencode('ลบงานประกันแล้ว: '.$job['warranty_no']));
exit;
