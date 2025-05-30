<?php
session_start();
include_once __DIR__ . '/../../includes/db.php';
include_once __DIR__ . '/../../includes/auth.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM youtube_videos WHERE id = ?");
$stmt->execute([$id]);
$video = $stmt->fetch();

if (!$video) {
    echo "ไม่พบวิดีโอที่ต้องการแก้ไข";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $video_id = $_POST['video_id'] ?? '';
    $description = $_POST['description'] ?? '';

    $updateStmt = $pdo->prepare("UPDATE youtube_videos SET title = ?, video_id = ?, description = ? WHERE id = ?");
    $updateStmt->execute([$title, $video_id, $description, $id]);

    header('Location: index.php');
    exit;
}

include '../../templates/header_admin.php';
include '../../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>แก้ไขวิดีโอ</span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="form-section" style="max-width: 100%; padding: 40px;">
    <form action="" method="POST">
      <h2 style="font-size: 24px; margin-bottom: 20px;">🛠 แก้ไขวิดีโอ YouTube</h2>

      <label for="title">ชื่อวิดีโอ:</label>
      <input type="text" name="title" id="title" value="<?= htmlspecialchars($video['title']); ?>" required>

      <label for="video_id">YouTube Video ID:</label>
      <input type="text" name="video_id" id="video_id" value="<?= htmlspecialchars($video['video_id']); ?>" required oninput="updatePreview()">

      <div id="video-preview" style="margin: 20px 0; <?= $video['video_id'] ? '' : 'display:none;' ?>">
        <iframe width="100%" height="480" src="https://www.youtube.com/embed/<?= htmlspecialchars($video['video_id']) ?>" frameborder="0" allowfullscreen></iframe>
      </div>

      <label for="description">รายละเอียด:</label>
      <textarea name="description" id="description" rows="8" required><?= htmlspecialchars($video['description']); ?></textarea>

      <button type="submit">บันทึกการเปลี่ยนแปลง</button>
      <a href="index.php" class="back-link">← ย้อนกลับ</a>
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
