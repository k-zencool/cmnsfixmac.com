<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php'; 
require_login(); 

function e($string) { 
  return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
  $admin_id = $_SESSION['admin_id'] ?? null; 

  if (!$admin_id) {
    die('Error: You must be logged in to perform this action.'); 
  }

  $title = $_POST['title'] ?? '';
  $slug = trim($_POST['slug'] ?? '');
  $content = $_POST['content'] ?? '';
  $excerpt = $_POST['excerpt'] ?? '';

  $title_en = $_POST['title_en'] ?? '';
  $slug_en = trim($_POST['slug_en'] ?? '');
  $content_en = $_POST['content_en'] ?? '';
  $excerpt_en = $_POST['excerpt_en'] ?? '';

  $category = $_POST['category'] ?? '';
  $youtube_url = $_POST['youtube_url'] ?? '';
  $status = isset($_POST['status']) ? 1 : 0;

  $mainImage = '';
  // --- FIX: VALIDATE MAIN IMAGE FOR WEBP FORMAT ---
  if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] == UPLOAD_ERR_OK) {
      $fileInfo = pathinfo($_FILES['main_image']['name']);
      $fileExtension = strtolower($fileInfo['extension']);

      if ($fileExtension === 'webp') {
          // สร้างชื่อไฟล์ใหม่และบังคับเป็น .webp
          $mainImage = time() . '-' . uniqid() . '.webp';
          move_uploaded_file($_FILES['main_image']['tmp_name'], '../../uploads/' . $mainImage);
      } else {
          die('Error: รูปภาพหลักต้องเป็นไฟล์ .webp เท่านั้น');
      }
  }

  $stmt = $pdo->prepare("INSERT INTO articles 
                          (admin_id, title, slug, category, content, excerpt, youtube_url, image, status, created_at, 
                           title_en, slug_en, content_en, excerpt_en)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)");
  
  if ($stmt->execute([
    $admin_id, $title, $slug, $category, $content, $excerpt, $youtube_url, $mainImage, $status,
    $title_en, $slug_en, $content_en, $excerpt_en
  ])) {
    $article_id = $pdo->lastInsertId();

    if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
      foreach ($_FILES['additional_images']['tmp_name'] as $index => $tmpName) {
        if (isset($_FILES['additional_images']['error'][$index]) && $_FILES['additional_images']['error'][$index] === UPLOAD_ERR_OK) {
          
          // --- FIX: VALIDATE ADDITIONAL IMAGES FOR WEBP FORMAT ---
          $fileInfo = pathinfo($_FILES['additional_images']['name'][$index]);
          $fileExtension = strtolower($fileInfo['extension']);

          if ($fileExtension === 'webp') {
              $fileName = time() . '-' . uniqid() . '.webp';
              move_uploaded_file($tmpName, '../../uploads/' . $fileName);
              
              $caption_th = $_POST['caption_detail'][$index] ?? '';
              $caption_en = $_POST['caption_detail_en'][$index] ?? '';

              $imgStmt = $pdo->prepare("INSERT INTO article_images (article_id, image_path, caption, caption_en) VALUES (?, ?, ?, ?)");
              $imgStmt->execute([$article_id, $fileName, $caption_th, $caption_en]);
          } else {
              // ถ้าไม่ใช่ .webp ก็ข้ามไฟล์นี้ไปเลย
              continue; 
          }
        }
      }
    }

    header("Location: index.php");
    exit;
  } else {
    echo "Error saving article.";
  }
}

// Default form values
$title = $slug = $category = $excerpt = $content = $youtube_url = '';
$title_en = $slug_en = $content_en = $excerpt_en = '';
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>เพิ่มบทความใหม่ (Add New Article)</span>
    <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
  </div>

  <div class="form-section">
    <form method="POST" enctype="multipart/form-data">
        
      <fieldset>
        <legend>ข้อมูลภาษาไทย (Thai Content)</legend>
        <label for="title">ชื่อบทความ (Title TH):</label>
        <input type="text" id="title" name="title" value="<?= e($title) ?>" required>

        <label for="slug">Slug (URL TH):</label>
        <input type="text" id="slug" name="slug" value="<?= e($slug) ?>" placeholder="how-to-fix-macbook-screen-th" required>

        <label for="content">เนื้อหาหลัก (Content TH):</label>
        <input id="content" type="hidden" name="content" value="<?= e($content) ?>">
        <trix-editor input="content"></trix-editor>

        <label for="excerpt">สรุปเนื้อหา (Excerpt TH):</label>
        <textarea id="excerpt" name="excerpt" rows="3"><?= e($excerpt) ?></textarea>
      </fieldset>

      <hr style="margin: 20px 0;">

      <fieldset>
        <legend>ข้อมูลภาษาอังกฤษ (English Content)</legend>
        <label for="title_en">ชื่อบทความ (Title EN):</label>
        <input type="text" id="title_en" name="title_en" value="<?= e($title_en) ?>">

        <label for="slug_en">Slug (URL EN):</label>
        <input type="text" id="slug_en" name="slug_en" value="<?= e($slug_en) ?>" placeholder="how-to-fix-macbook-screen-en">

        <label for="content_en">เนื้อหาหลัก (Content EN):</label>
        <input id="content_en" type="hidden" name="content_en" value="<?= e($content_en) ?>">
        <trix-editor input="content_en"></trix-editor>

        <label for="excerpt_en">สรุปเนื้อหา (Excerpt EN):</label>
        <textarea id="excerpt_en" name="excerpt_en" rows="3"><?= e($excerpt_en) ?></textarea>
      </fieldset>

      <hr style="margin: 20px 0;">

      <fieldset>
        <legend>ข้อมูลทั่วไป (General Information)</legend>
        <label for="category">หมวดหมู่:</label>
        <select id="category" name="category">
          <option value="tip">เทคนิค (Tips)</option>
          <option value="repair">การซ่อม (Repair)</option>
          <option value="update">อัปเดต (Update)</option>
        </select>

        <label for="youtube_url">YouTube Video ID:</label>
        <input type="text" id="youtube_url" name="youtube_url" value="<?= e($youtube_url) ?>" placeholder="เช่น qW5i8_o6gXU">

        <label for="main_image">รูปภาพหลัก:</label>
        <input type="file" id="main_image" name="main_image" accept="image/webp" onchange="previewMainImage(this)">
        <div id="mainImagePreview"></div>

        <label style="margin-top:20px; display:block;">ภาพเพิ่มเติม + คำอธิบาย (Additional Images + Captions):</label>
        <div id="additional-container"></div>

        <button type="button" onclick="addMoreImages()" style="margin-top: 10px;">
          <span class="material-symbols-rounded" style="vertical-align: middle;">add_photo_alternate</span> เพิ่มรูปเพิ่มเติม
        </button>
      </fieldset>

      <div class="form-actions" style="margin-top: 20px;">
        <div class="form-checkbox">
          <input type="checkbox" name="status" id="status" checked>
          <label for="status">เผยแพร่บทความ (Publish)</label>
        </div>
      </div>

      <div style="margin-top: 20px;">
        <button type="submit">บันทึกบทความ</button>
        <a href="index.php" class="back-link" style="margin-left: 10px;">← ย้อนกลับ</a>
      </div>
    </form>
  </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>

<script>
let imageIndex = 0; 

function addMoreImages() {
  const container = document.getElementById('additional-container');
  const div = document.createElement('div');
  div.className = 'additional-image-group';
  div.style.border = '1px solid #ddd';
  div.style.padding = '15px';
  div.style.borderRadius = '8px';
  div.style.marginBottom = '15px';

  const captionIdTh = 'caption-th-' + imageIndex;
  const captionIdEn = 'caption-en-' + imageIndex;

  // ใช้วิธีต่อสตริงแบบเก่า ปลอดภัยกว่าเยอะ
  div.innerHTML = 
    '<div style="text-align: right;">' +
      '<button type="button" class="remove-image-btn" onclick="removeImageGroup(this)" style="background: #ff4d4d; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer;">✕</button>' +
    '</div>' +
    '<label>เลือกรูป (Select Image):</label>' +
    '<input type="file" name="additional_images[]" onchange="previewAdditionalImage(this)" accept="image/webp">' +
    '<img src="" alt="Preview" style="display: none; max-width: 150px; border-radius: 8px; margin: 10px 0;">' +
    '<label for="' + captionIdTh + '" style="margin-top: 10px;">คำอธิบายภาพ (Caption TH):</label>' +
    '<input id="' + captionIdTh + '" type="hidden" name="caption_detail[]">' +
    '<trix-editor input="' + captionIdTh + '"></trix-editor>' +
    '<label for="' + captionIdEn + '" style="margin-top: 10px;">คำอธิบายภาพ (Caption EN):</label>' +
    '<input id="' + captionIdEn + '" type="hidden" name="caption_detail_en[]">' +
    '<trix-editor input="' + captionIdEn + '"></trix-editor>';
    
  container.appendChild(div);
  imageIndex++;
}

function previewAdditionalImage(input) {
  const file = input.files[0];
  const imgPreview = input.parentElement.querySelector('img');
  if (file && imgPreview) {
    const reader = new FileReader();
    reader.onload = function (e) {
      imgPreview.src = e.target.result;
      imgPreview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  } else if (imgPreview) {
      imgPreview.src = '';
      imgPreview.style.display = 'none';
  }
}

function previewMainImage(input) {
  const previewContainer = document.getElementById('mainImagePreview');
  previewContainer.innerHTML = '';
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function (e) {
      // ใช้วิธีต่อสตริงเหมือนกัน
      previewContainer.innerHTML = '<img src="' + e.target.result + '" style="max-width:200px; border-radius:8px; margin-top:10px;">';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function removeImageGroup(button) {
  const group = button.closest('.additional-image-group');
  if (group) {
    group.remove();
  }
}

// Add one image group by default when page loads
document.addEventListener('DOMContentLoaded', () => {
    addMoreImages();
});
</script>