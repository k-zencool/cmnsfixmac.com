<?php
session_start();
include_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/auth.php';

// ดึงข้อมูลแอดมินทั้งหมด
$stmt = $pdo->query("SELECT * FROM admin_users ORDER BY id ASC");
$admins = $stmt->fetchAll();

// หากมีการลบ
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId !== 1) { // ห้ามลบแอดมิน id=1 (Super Admin)
        $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->execute([$deleteId]);
    }
    header('Location: admin_list.php');
    exit;
}

include '../templates/header_admin.php';
include '../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>รายชื่อแอดมิน</span>
    <a href="../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="form-section" style="max-width: 100%; padding: 40px;">
    <h2 style="font-size: 24px; margin-bottom: 20px;">👥 รายชื่อแอดมิน</h2>

    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Username</th>
          <th>วันที่สร้าง</th>
          <th>การจัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($admins as $admin): ?>
        <tr>
          <td><?= htmlspecialchars($admin['id']) ?></td>
          <td><?= htmlspecialchars($admin['username']) ?></td>
          <td><?= date('d/m/Y', strtotime($admin['created_at'])) ?></td>
          <td>
            <?php if ($admin['id'] != 1): ?>
              <a href="admin_list.php?delete=<?= $admin['id'] ?>" class="btn-delete" onclick="return confirm('ยืนยันการลบแอดมินคนนี้?');">ลบ</a>
            <?php else: ?>
              <span style="color: #6b7280;">Super Admin</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <p style="font-size: 13px; color: #6b7280; margin-top: 15px;">* หมายเหตุ: ไม่สามารถลบ Super Admin (ID 1) ได้เพื่อความปลอดภัย</p>
  </div>
</main>

<?php include '../templates/footer_admin.php'; ?>
