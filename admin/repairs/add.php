<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = $_POST['title'] ?? '';
  $model = $_POST['model'] ?? '';
  $issue = $_POST['issue'] ?? '';
  $fix_detail = $_POST['fix_detail'] ?? '';
  $category = $_POST['category'] ?? '';

  $image = '';
  if (!empty($_FILES['image']['name'])) {
    $filename = time() . '_' . $_FILES['image']['name'];
    $path = '../../uploads/' . $filename;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
      $image = $filename;
    }
  }

  $stmt = $pdo->prepare("INSERT INTO repairs (title, model, issue, fix_detail, image, category, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
  $stmt->execute([$title, $model, $issue, $fix_detail, $image, $category]);

  header("Location: index.php");
  exit;
}
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <span>เพิ่มผลงานใหม่</span>
    <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
  </div>

  <h2 style="margin: 20px 0;">เพิ่มข้อมูลผลงาน</h2>

  <form action="" method="POST" enctype="multipart/form-data" class="form-section">
    <label>ชื่อผลงาน:</label>
    <input type="text" name="title" required>

    <label>รุ่น (Model):</label>
    <input type="text" name="model" required>

    <label>ปัญหา:</label>
    <textarea name="issue" rows="3" required></textarea>

    <label>รายละเอียดการซ่อม:</label>
    <textarea name="fix_detail" rows="5" required></textarea>

    <label>หมวดหมู่ (ถ้ามี):</label>
    <input type="text" name="category">

    <label>อัปโหลดรูปภาพ:</label>
    <input type="file" name="image" id="image" accept="image/*" onchange="previewImage(event)">
    <div id="imagePreview" style="margin-top:10px;"></div>

      <button type="submit">บันทึกข้อมูล</button>
      <a href="index.php" class="back-link">← ย้อนกลับ</a>
  </form>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<script>
function previewImage(event) {
  const input = event.target;
  const previewContainer = document.getElementById('imagePreview');
  previewContainer.innerHTML = '';

  if (input.files && input.files[0]) {
    const img = document.createElement('img');
    img.src = URL.createObjectURL(input.files[0]);
    img.style.width = '120px';
    img.style.borderRadius = '8px';
    img.style.marginTop = '10px';
    previewContainer.appendChild(img);
  }
}
</script>
