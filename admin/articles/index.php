<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$stmt = $pdo->query("SELECT * FROM articles ORDER BY created_at DESC");
$articles = $stmt->fetchAll();

include '../../templates/header_admin.php'; 
include '../../templates/sidebar_admin.php'; 
?>

<main class="main" id="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <span>บทความทั้งหมด</span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

    <div class="section-header">
      <h2>จัดการบทความ</h2>
      <a href="add.php" class="btn-primary">+ เพิ่มบทความ</a>
    </div>

  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>รูป</th>
          <th>ชื่อบทความ</th>
          <th>หมวดหมู่</th>
          <th>วันที่</th>
          <th>สถานะ</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($articles as $index => $row): ?>
        <tr>
          <td><?= $index + 1 ?></td>
          <td>
            <?php if (!empty($row['image'])): ?>
              <img src="../../uploads/<?= htmlspecialchars($row['image']) ?>" class="thumb" alt="ภาพบทความ">
            <?php else: ?>
              <span style="color:#aaa;">ไม่มีรูป</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['category']) ?></td>
          <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
          <td>
            <?php if ($row['status']): ?>
              <span class="badge badge-success">เผยแพร่</span>
            <?php else: ?>
              <span class="badge badge-danger">ซ่อน</span>
            <?php endif; ?>
          </td>
<td>
  <a href="edit.php?id=<?= $row['id'] ?>" class="btn-edit">แก้ไข</a>
  <a href="delete.php?id=<?= $row['id'] ?>" class="btn-delete">ลบ</a>
</td>

        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>
