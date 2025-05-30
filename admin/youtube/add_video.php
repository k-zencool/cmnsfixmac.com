<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = $_POST['title'] ?? '';
  $video_id = $_POST['video_id'] ?? '';
  $description = $_POST['description'] ?? '';

  $stmt = $pdo->prepare("INSERT INTO youtube_videos (title, video_id, description, created_at) VALUES (?, ?, ?, NOW())");
  $stmt->execute([$title, $video_id, $description]);

  header("Location: index.php");
  exit;
}

include '../../templates/header_admin.php';
include '../../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>เพิ่มวิดีโอ YouTube</span>
    <a href="index.php" class="view-site">← ย้อนกลับ</a>
  </div>

  <div class="form-section">
    <form action="" method="POST">
      <label for="title">ชื่อวิดีโอ:</label>
      <input type="text" name="title" id="title" required>

      <label for="video_id">YouTube Video ID:</label>
      <input type="text" name="video_id" id="video_id" required oninput="updatePreview()">

      <div id="video-preview" style="margin: 20px 0; display:none;">
        <iframe width="560" height="315" frameborder="0" allowfullscreen></iframe>
      </div>

      <label for="description">รายละเอียด:</label>
      <textarea name="description" id="description" rows="5"></textarea>

      <button type="submit">บันทึกวิดีโอ</button>
    </form>
  </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<script>
function updatePreview() {
  const id = document.getElementById('video_id').value.trim();
  const preview = document.getElementById('video-preview');
  const iframe = preview.querySelector('iframe');

  if (id) {
    iframe.src = `https://www.youtube.com/embed/${id}`;
    preview.style.display = 'block';
  } else {
    iframe.src = '';
    preview.style.display = 'none';
  }
}
</script>
