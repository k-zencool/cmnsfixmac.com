<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// ดึงข้อมูลวิดีโอ
$stmt = $pdo->query("SELECT * FROM youtube_videos ORDER BY created_at DESC");
$videos = $stmt->fetchAll();

include '../../templates/header_admin.php';
include '../../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <span>วิดีโอ YouTube</span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="section-header">
    <h2>จัดการวิดีโอ YouTube</h2>
    <a href="add_video.php" class="btn-primary">+ เพิ่มวิดีโอใหม่</a>
  </div>

  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>ชื่อวิดีโอ</th>
          <th>วิดีโอ</th>
          <th>รายละเอียด</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($videos)): ?>
          <?php foreach ($videos as $i => $video): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($video['title']) ?></td>
            <td style="max-width: 240px;">
              <div class="youtube-embed">
                <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($video['video_id']) ?>"
                        frameborder="0" allowfullscreen loading="lazy"></iframe>
              </div>
            </td>
            <td><?= nl2br(htmlspecialchars($video['description'])) ?></td>
            <td>
              <a href="edit_video.php?id=<?= $video['id'] ?>" class="btn-edit">แก้ไข</a>
              <a href="delete_video.php?id=<?= $video['id'] ?>" class="btn-delete" onclick="return confirm('ยืนยันการลบวิดีโอนี้?')">ลบ</a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" style="text-align:center; color: #888;">ยังไม่มีวิดีโอ</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>
