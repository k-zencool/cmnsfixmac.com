<?php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!isset($_GET['id'])) {
  die('ไม่พบรหัสบทความ');
}

$id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
  die('ไม่พบบทความนี้');
}

$stmtImages = $pdo->prepare("SELECT * FROM article_images WHERE article_id = ?");
$stmtImages->execute([$id]);
$additionalImages = $stmtImages->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = $_POST['title'];
  $slug = $_POST['slug'];
  $excerpt = $_POST['excerpt'];
  $content = $_POST['content'];
  $category = $_POST['category'];
  $youtube_url = $_POST['youtube_url'];
  $status = isset($_POST['status']) ? 1 : 0;

  $imageName = $article['image'];
  if (!empty($_FILES['image']['name'])) {
    $imageName = time() . '-' . basename($_FILES['image']['name']);
    move_uploaded_file($_FILES['image']['tmp_name'], '../../uploads/' . $imageName);
  }

  $stmt = $pdo->prepare("UPDATE articles SET title=?, slug=?, excerpt=?, content=?, category=?, image=?, youtube_url=?, status=? WHERE id=?");
  $stmt->execute([$title, $slug, $excerpt, $content, $category, $imageName, $youtube_url, $status, $id]);

  // อัปเดตคำอธิบายภาพเดิม
  if (isset($_POST['existing_captions'])) {
    foreach ($_POST['existing_captions'] as $imageId => $caption) {
      $stmt = $pdo->prepare("UPDATE article_images SET caption = ? WHERE id = ?");
      $stmt->execute([$caption, $imageId]);
    }
  }

  // อัปโหลดรูปเพิ่มเติมใหม่
  if (!empty($_FILES['additional_images']['name'][0])) {
    foreach ($_FILES['additional_images']['tmp_name'] as $index => $tmpName) {
      if ($_FILES['additional_images']['error'][$index] === UPLOAD_ERR_OK) {
        $fileName = time() . '-' . basename($_FILES['additional_images']['name'][$index]);
        move_uploaded_file($tmpName, '../../uploads/' . $fileName);
        $caption = $_POST['caption_detail'][$index] ?? '';
        $pdo->prepare("INSERT INTO article_images (article_id, image_path, caption) VALUES (?, ?, ?)")
            ->execute([$id, $fileName, $caption]);
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
    <span>แก้ไขบทความ</span>
    <a href="index.php" class="view-site">← ย้อนกลับ</a>
  </div>

  <div class="form-section">
    <form method="POST" enctype="multipart/form-data">
      <label>ชื่อบทความ:</label>
      <input type="text" name="title" value="<?= htmlspecialchars($article['title']) ?>" required>

      <label>Slug (URL):</label>
      <input type="text" name="slug" value="<?= htmlspecialchars($article['slug']) ?>" required>
      <div class="form-group">
      <label>หมวดหมู่:</label>
      <select name="category">
        <option value="tip" <?= $article['category'] === 'tip' ? 'selected' : '' ?>>เทคนิค</option>
        <option value="repair" <?= $article['category'] === 'repair' ? 'selected' : '' ?>>การซ่อม</option>
        <option value="update" <?= $article['category'] === 'update' ? 'selected' : '' ?>>อัปเดต</option>
      </select>
      </div>


      <label>เนื้อหาหลัก:</label>
      <input id="content" type="hidden" name="content" value="<?= htmlspecialchars($article['content']) ?>">
      <trix-editor input="content"></trix-editor>

      <label>สรุปเนื้อหา (Excerpt):</label>
      <textarea name="excerpt"><?= htmlspecialchars($article['excerpt']) ?></textarea>


      <label>YouTube Video ID:</label>
      <input type="text" name="youtube_url" value="<?= htmlspecialchars($article['youtube_url']) ?>">

      <label>ภาพหลัก:</label>
      <input type="file" name="image" accept="image/*" onchange="previewMainImage(this)">
      <div id="mainImagePreview">
        <?php if ($article['image']): ?>
          <img src="../../uploads/<?= htmlspecialchars($article['image']) ?>" style="width:120px; border-radius:8px;">
        <?php endif; ?>
      </div>

      <label>ภาพเพิ่มเติมที่มีอยู่:</label>
        <?php foreach ($additionalImages as $index => $img): ?>
          <div class="additional-image-group">
            
            <!-- ✅ รูปภาพแสดงบนสุด -->
            <img src="../../uploads/<?= htmlspecialchars($img['image_path']) ?>" style="width:100px; border-radius:8px;">

            <!-- ✅ ปุ่มอัปโหลดรูปใหม่ -->
            <label style="margin-top: 10px;">เปลี่ยนรูปนี้ (ถ้าต้องการ):</label>
            <input type="file" name="replace_image[<?= $img['id'] ?>]" accept="image/*">

            <!-- ✅ คำอธิบาย -->
            <input id="existing-caption-<?= $index ?>" type="hidden" name="existing_captions[<?= $img['id'] ?>]" value="<?= htmlspecialchars($img['caption']) ?>">
            <trix-editor input="existing-caption-<?= $index ?>"></trix-editor>


            <!-- ✅ ปุ่มลบ -->
            <a href="delete_image.php?id=<?= $img['id'] ?>&article_id=<?= $id ?>" class="btn-delete" onclick="return confirm('ลบภาพนี้หรือไม่?')">ลบภาพ</a>
          </div>
        <?php endforeach; ?>



      <label>ภาพเพิ่มเติมใหม่ + คำอธิบาย:</label>
        <div id="additional-container">
          <div class="additional-image-group">
            
            <!-- ✅ รูป Preview อยู่ด้านบน -->
            <img src="" alt="Preview" style="display: none; width: 100px; border-radius: 8px; margin-bottom: 12px;">
            
            <!-- ✅ input เลือกรูป -->
            <input type="file" name="additional_images[]" accept="image/*" onchange="previewImage(this)">

            <!-- ✅ คำอธิบาย -->
            <input id="caption-rich-0" type="hidden" name="caption_detail[]">
            <trix-editor input="caption-rich-0" style="margin-bottom: 16px;"></trix-editor>

       
            
          </div>
        </div>

      <button type="button" onclick="addMoreImages()" style="margin-top: 10px;">
        <span class="material-symbols-rounded">add_photo_alternate</span> เพิ่มรูปเพิ่มเติม
      </button>



    <div class="form-actions">
      <div class="form-checkbox">
        <input type="checkbox" name="status" id="status" <?= $article['status'] ? 'checked' : '' ?>>
        <label for="status">เผยแพร่บทความ</label>
      </div>
    </div>

        <button type="submit">บันทึกการเปลี่ยนแปลง</button>
        <a href="index.php" class="back-link">← ย้อนกลับ</a>
      
    </form>
  </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<!-- Trix Editor -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trix@2.0.0/dist/trix.css">
<script src="https://cdn.jsdelivr.net/npm/trix@2.0.0/dist/trix.umd.min.js"></script>

<!-- Image Preview Script -->
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
    <img src="" alt="Preview" style="display: none;">
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
      preview.innerHTML = `<img src="${e.target.result}" style="width:120px; border-radius:8px;">`;
    };
    reader.readAsDataURL(file);
  }
}
</script>
