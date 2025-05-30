<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$title = $_POST['title'] ?? '';
$slug = $_POST['slug'] ?? '';
$category = $_POST['category'] ?? '';
$excerpt = $_POST['excerpt'] ?? '';
$content = $_POST['content'] ?? '';
$youtube_url = $_POST['youtube_url'] ?? '';
$status = isset($_POST['status']) ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mainImage = '';
  if (!empty($_FILES['main_image']['name'])) {
    $mainImage = time() . '-' . uniqid() . '-' . $_FILES['main_image']['name'];
    move_uploaded_file($_FILES['main_image']['tmp_name'], '../../uploads/' . $mainImage);
  }

  $stmt = $pdo->prepare("INSERT INTO articles (title, slug, category, content, excerpt, youtube_url, image, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
  $stmt->execute([$title, $slug, $category, $content, $excerpt, $youtube_url, $mainImage, $status]);
  $article_id = $pdo->lastInsertId();

  if (!empty($_FILES['additional_images']['name'][0])) {
    foreach ($_FILES['additional_images']['tmp_name'] as $index => $tmpName) {
      if ($_FILES['additional_images']['error'][$index] === UPLOAD_ERR_OK) {
        $fileName = time() . '-' . uniqid() . '-' . $_FILES['additional_images']['name'][$index];
        move_uploaded_file($tmpName, '../../uploads/' . $fileName);
        $caption = $_POST['caption_detail'][$index] ?? '';
        $pdo->prepare("INSERT INTO article_images (article_id, image_path, caption) VALUES (?, ?, ?)")
            ->execute([$article_id, $fileName, $caption]);
      }
    }
  }

  header("Location: index.php");
  exit;
}
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>เพิ่มบทความใหม่</span>
    <a href="index.php" class="view-site">← ย้อนกลับ</a>
  </div>

  <div class="form-section">
    <form method="POST" enctype="multipart/form-data">
      <label>ชื่อบทความ:</label>
      <input type="text" name="title" required>

      <label>Slug (URL):</label>
      <input type="text" name="slug" required>

      <div class="form-group">
        <label>หมวดหมู่:</label>
        <select name="category">
          <option value="tip">เทคนิค</option>
          <option value="repair">การซ่อม</option>
          <option value="update">อัปเดต</option>
        </select>
      </div>

      <label>เนื้อหาหลัก:</label>
      <input id="content" type="hidden" name="content">
      <trix-editor input="content"></trix-editor>

      <label>สรุปเนื้อหา (Excerpt):</label>
      <textarea name="excerpt" rows="3"></textarea>

      <label>YouTube Video ID:</label>
      <input type="text" name="youtube_url">

      <label>รูปภาพหลัก:</label>
      <input type="file" name="main_image" accept="image/*" onchange="previewMainImage(this)">
      <div id="mainImagePreview"></div>

      <label>ภาพเพิ่มเติม + คำอธิบาย:</label>
      <div id="additional-container">
        <div class="additional-image-group">

          <!-- ✅ Preview รูป -->
          <img src="" alt="Preview" style="display: none; width: 100px; border-radius: 8px; margin-bottom: 12px;">

          <!-- ✅ ปุ่มเลือกรูป -->
          <input type="file" name="additional_images[]" accept="image/*" onchange="previewImage(this)">

          <!-- ✅ คำอธิบาย -->
          <input id="caption-rich-0" type="hidden" name="caption_detail[]">
          <trix-editor input="caption-rich-0" style="margin-bottom: 16px;"></trix-editor>

          
          <!-- ✅ ปุ่มลบ -->
          <button type="button" class="remove-image-btn" onclick="removeImageGroup(this)">🗑 ลบชุดภาพนี้</button>
          
        </div>
      </div>

      
      <button type="button" onclick="addMoreImages()" style="margin-top: 10px;">
        <span class="material-symbols-rounded">add_photo_alternate</span> เพิ่มรูปเพิ่มเติม
      </button>

          <div class="form-actions">
              <div class="form-checkbox">
                <input type="checkbox" name="status" id="status" checked>
                <label for="status">เผยแพร่บทความ</label>
              </div>
          </div>

        <button type="submit">บันทึกบทความ</button>
        <a href="index.php" class="back-link">← ย้อนกลับ</a>
    </form>
  </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<!-- Trix Editor -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trix@2.0.0/dist/trix.css">
<script src="https://cdn.jsdelivr.net/npm/trix@2.0.0/dist/trix.umd.min.js"></script>

<!-- JS -->
<script>
let imageIndex = 1;

function addMoreImages() {
  const container = document.getElementById('additional-container');
  const div = document.createElement('div');
  div.className = 'additional-image-group';
  const captionId = `caption-rich-${imageIndex++}`;
  div.innerHTML = `
    <input type="file" name="additional_images[]" onchange="previewImage(this)">
    <input id="${captionId}" type="hidden" name="caption_detail[]">
    <trix-editor input="${captionId}"></trix-editor>
    <img src="" alt="Preview">
    <button type="button" class="remove-image-btn" onclick="removeImageGroup(this)">🗑 ลบชุดภาพนี้</button>
  `;
  container.appendChild(div);
}

function previewImage(input) {
  const file = input.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      const img = input.parentElement.querySelector('img');
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
}

function previewMainImage(input) {
  const file = input.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      const preview = document.getElementById('mainImagePreview');
      preview.innerHTML = `<img src="${e.target.result}" style="width:120px; border-radius:8px; margin-top:10px;">`;
    };
    reader.readAsDataURL(file);
  }
}

function removeImageGroup(button) {
  const group = button.closest('.additional-image-group');
  group.remove();
}
</script>
