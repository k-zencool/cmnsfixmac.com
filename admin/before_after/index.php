<?php
session_start();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_login(); 

// ดึงข้อมูลทั้งหมดจากตาราง photo_projects โดยเรียงจากใหม่ไปเก่า
$stmt = $pdo->query("SELECT * FROM photo_projects ORDER BY id DESC");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>คลังผลงาน Before-After</span>
    <a href="upload_form.php" class="view-site">→ สร้างโปรเจกต์ใหม่</a>
  </div>

  <div class="section-header">
    <h2>รายการโปรเจกต์ทั้งหมด</h2>
    <a href="upload_form.php" class="btn-primary">+ สร้างโปรเจกต์ใหม่</a>
  </div>

  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>รูปผลงาน</th>
          <th>เลขงาน</th>
          <th>รุ่นอุปกรณ์</th>
          <th>สถานะ</th>
          <th>วันที่สร้าง</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($projects)): ?>
          <tr>
            <td colspan="7" class="text-center">ยังไม่มีผลงานที่สร้างไว้</td>
          </tr>
        <?php else: ?>
          <?php foreach ($projects as $index => $project): ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <td>
                <?php if (!empty($project['combined_image_path'])): ?>
                  <a href="/<?= htmlspecialchars($project['combined_image_path']) ?>" target="_blank">
                    <img src="/<?= htmlspecialchars($project['combined_image_path']) ?>" class="thumb">
                  </a>
                <?php else: ?>
                  <span style="color:#aaa;">รอสร้างรูป</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($project['job_number']) ?></td>
              <td><?= htmlspecialchars($project['device_model']) ?></td>
              <td>
                <?php if (!empty($project['combined_image_path'])): ?>
                  <span class="badge badge-success">สร้างแล้ว</span>
                <?php else: ?>
                  <span class="badge badge-danger">ยังไม่สร้าง</span>
                <?php endif; ?>
              </td>
              <td><?= date('d/m/Y H:i', strtotime($project['created_at'])) ?></td>
              <td>
                <?php if (!empty($project['combined_image_path'])): ?>
                  <a href="/<?= htmlspecialchars($project['combined_image_path']) ?>" class="btn-edit" download>ดาวน์โหลด</a>
                <?php endif; ?>
                <a href="delete_project.php?id=<?= $project['id'] ?>" class="btn-delete" onclick="return confirm('แน่ใจว่าจะลบโปรเจกต์นี้?')">ลบ</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>