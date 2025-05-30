<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_GET['id'])) {
  header("Location: index.php");
  exit;
}

$id = intval($_GET['id']);

// ดึงข้อมูลเก่า
$stmt = $pdo->prepare("SELECT * FROM repairs WHERE id = ?");
$stmt->execute([$id]);
$repair = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$repair) {
  echo "ไม่พบข้อมูลผลงานนี้";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = $_POST['title'] ?? '';
  $model = $_POST['model'] ?? '';
  $issue = $_POST['issue'] ?? '';
  $fix_detail = $_POST['fix_detail'] ?? '';
  $category = $_POST['category'] ?? '';
  $image = $repair['image'];

  // ถ้ามีการอัปโหลดรูปใหม่
  if (!empty($_FILES['image']['name'])) {
    $newImage = time() . '_' . $_FILES['image']['name'];
    $targetPath = __DIR__ . '/../../uploads/' . $newImage;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
      // ลบรูปเก่า
      if ($image && file_exists("../../uploads/$image")) {
        unlink("../../uploads/$image");
      }
      $image = $newImage;
    }
  }

  // อัปเดตข้อมูล
  $stmt = $pdo->prepare("UPDATE repairs SET title=?, model=?, issue=?, fix_detail=?, category=?, image=? WHERE id=?");
  $stmt->execute([$title, $model, $issue, $fix_detail, $category, $image, $id]);

  header("Location: index.php");
  exit;
}
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>แก้ไขผลงาน</span>
    <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
  </div>

  <h2 style="margin: 20px 0;">แก้ไขผลงาน</h2>

  <form action="" method="POST" enctype="multipart/form-data" class="form-section">
    <label>ชื่อผลงาน:</label>
    <input type="text" name="title" value="<?= htmlspecialchars($repair['title']) ?>" required>

    <label>รุ่น (Model):</label>
    <input type="text" name="model" value="<?= htmlspecialchars($repair['model']) ?>" required>

    <label>ปัญหา:</label>
    <textarea name="issue" rows="3" required><?= htmlspecialchars($repair['issue']) ?></textarea>

    <label>รายละเอียดการซ่อม:</label>
    <textarea name="fix_detail" rows="5" required><?= htmlspecialchars($repair['fix_detail']) ?></textarea>

    <label>หมวดหมู่:</label>
    <input type="text" name="category" value="<?= htmlspecialchars($repair['category']) ?>">

    <label>อัปโหลดรูปใหม่ (ถ้าต้องการเปลี่ยน):</label>
    <input type="file" name="image" accept="image/*" onchange="previewImage(event)">

    <div id="imagePreview" style="margin-top:10px;">
      <?php if ($repair['image']): ?>
        <img src="../../uploads/<?= htmlspecialchars($repair['image']) ?>" class="preview-thumb">
      <?php endif; ?>
    </div>

      <button type="submit">บันทึกการแก้ไข</button>
      <a href="index.php" class="back-link">← ย้อนกลับ</a>
  </form>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<script>
function previewImage(event) {
  const preview = document.getElementById('imagePreview');
  preview.innerHTML = '';
  const file = event.target.files[0];
  if (file) {
    const img = document.createElement('img');
    img.src = URL.createObjectURL(file);
    img.className = 'preview-thumb';
    preview.appendChild(img);
  }
}
</script>
