<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$stmt = $pdo->query("SELECT * FROM repairs ORDER BY created_at DESC");
$repairs = $stmt->fetchAll();

include '../../templates/header_admin.php'; 
include '../../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <span>รายการผลงานทั้งหมด</span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

    <div class="section-header">
      <h2>ผลงานทั้งหมด</h2>
      <a href="add.php" class="btn-primary">+ เพิ่มผลงาน</a>
    </div>

    <div class="table-container">
      <table class="data-table">
<thead>
  <tr>
    <th>#</th>
    <th>รูป</th>
    <th>ชื่อผลงาน</th>
    <th>รุ่น</th>
    <th>หมวดหมู่</th>
    <th>วันที่สร้าง</th>
    <th>จัดการ</th>
  </tr>
</thead>
<tbody>
  <?php foreach ($repairs as $index => $repair): ?>
    <tr>
      <td><?= $index + 1 ?></td> <!-- ✅ ลำดับ -->
      <td>
        <?php if (!empty($repair['image'])): ?>
          <img src="../../uploads/<?= htmlspecialchars($repair['image']) ?>" class="thumb" alt="">
        <?php else: ?>
          <span style="color:#aaa;">ไม่มีรูป</span>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($repair['title']) ?></td>
      <td><?= htmlspecialchars($repair['model']) ?></td>
      <td><?= htmlspecialchars($repair['category'] ?? '-') ?></td>
      <td><?= date('d/m/Y', strtotime($repair['created_at'])) ?></td>
      <td>
        <a href="edit.php?id=<?= $repair['id'] ?>" class="btn-edit">แก้ไข</a>
        <a href="delete.php?id=<?= $repair['id'] ?>" class="btn-delete" onclick="return confirm('ยืนยันการลบใช่ไหม?')">ลบ</a>
      </td>
    </tr>
  <?php endforeach; ?>
</tbody>

    </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>
