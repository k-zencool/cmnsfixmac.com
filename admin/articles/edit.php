<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); 

function e($string) { 
  return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

if (!isset($_GET['id'])) {
  header('Location: index.php');
  exit;
}

$id = intval($_GET['id']);

// Handle form submission for updating
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
  $current_editor_id = $_SESSION['admin_id'] ?? null;
  if (!$current_editor_id) {
    die('Error: You must be logged in to perform this action.');
  }

  // Thai Fields
  $title = $_POST['title'] ?? '';
  $slug = trim($_POST['slug'] ?? '');
  $content = $_POST['content'] ?? '';
  $excerpt = $_POST['excerpt'] ?? '';

  // English Fields
  $title_en = $_POST['title_en'] ?? '';
  $slug_en = trim($_POST['slug_en'] ?? '');
  $content_en = $_POST['content_en'] ?? '';
  $excerpt_en = $_POST['excerpt_en'] ?? '';

  // General Fields
  $category = $_POST['category'] ?? '';
  $youtube_url = $_POST['youtube_url'] ?? '';
  $status = isset($_POST['status']) ? 1 : 0;

  // Fetch current main image to keep it if no new one is uploaded
  $stmtImg = $pdo->prepare("SELECT image FROM articles WHERE id = ?");
  $stmtImg->execute([$id]);
  $imageName = $stmtImg->fetchColumn();

  // Handle new main image upload
  if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
    // ## FIX: VALIDATE FOR WEBP ##
    $fileInfo = pathinfo($_FILES['image']['name']);
    $fileExtension = strtolower($fileInfo['extension']);

    if ($fileExtension === 'webp') {
      $newImageName = time() . '-' . uniqid() . '.webp';
      $targetPath = __DIR__ . '/../../uploads/' . $newImageName;
      if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
        // Delete old image if it exists
        if ($imageName && file_exists(__DIR__ . "/../../uploads/" . $imageName)) {
          unlink(__DIR__ . "/../../uploads/" . $imageName);
        }
        $imageName = $newImageName; // Update image name to the new one
      }
    } else {
        die('Error: รูปภาพหลักต้องเป็นไฟล์ .webp เท่านั้น');
    }
  }

  $stmtUpdate = $pdo->prepare("UPDATE articles SET 
                                title=?, slug=?, excerpt=?, content=?, category=?, image=?, youtube_url=?, status=?,
                                title_en=?, slug_en=?, excerpt_en=?, content_en=?
                              WHERE id=?");
                              
  $stmtUpdate->execute([
    $title, $slug, $excerpt, $content, $category, $imageName, $youtube_url, $status,
    $title_en, $slug_en, $excerpt_en, $content_en,
    $id
  ]);

  // Update existing captions
  if (isset($_POST['existing_captions_th'])) {
    foreach ($_POST['existing_captions_th'] as $imageId => $caption_th) {
      $caption_en = $_POST['existing_captions_en'][$imageId] ?? '';
      $stmtCaption = $pdo->prepare("UPDATE article_images SET caption = ?, caption_en = ? WHERE id = ?");
      $stmtCaption->execute([$caption_th, $caption_en, $imageId]);
    }
  }

  // Handle new additional images
  if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
    foreach ($_FILES['additional_images']['tmp_name'] as $index => $tmpName) {
      if (isset($_FILES['additional_images']['error'][$index]) && $_FILES['additional_images']['error'][$index] === UPLOAD_ERR_OK) {
        // ## FIX: VALIDATE FOR WEBP ##
        $fileInfo = pathinfo($_FILES['additional_images']['name'][$index]);
        $fileExtension = strtolower($fileInfo['extension']);

        if ($fileExtension === 'webp') {
            $fileName = time() . '-' . uniqid() . '.webp';
            move_uploaded_file($tmpName, '../../uploads/' . $fileName);
            
            $caption_th_new = $_POST['caption_detail_th'][$index] ?? '';
            $caption_en_new = $_POST['caption_detail_en'][$index] ?? '';
            
            $pdo->prepare("INSERT INTO article_images (article_id, image_path, caption, caption_en) VALUES (?, ?, ?, ?)")
                ->execute([$id, $fileName, $caption_th_new, $caption_en_new]);
        } else {
            // Skip non-webp files
            continue;
        }
      }
    }
  }

  header("Location: index.php");
  exit;
}

// Fetch existing data for form display
$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
  die('ไม่พบบทความนี้ (Article not found)');
}

$stmtImages = $pdo->prepare("SELECT * FROM article_images WHERE article_id = ?");
$stmtImages->execute([$id]);
$additionalImages = $stmtImages->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>
<main class="main" id="main-content">
  <div class="topbar">
    <span>แก้ไขบทความ (Edit Article #<?= e($id) ?>)</span>
    <a href="index.php" class="view-site">← ย้อนกลับ</a>
  </div>

  <div class="form-section">
    <form method="POST" enctype="multipart/form-data">
        
      <fieldset>
        <legend>ข้อมูลภาษาไทย (Thai Content)</legend>
        <label for="title">ชื่อบทความ (Title TH):</label>
        <input type="text" id="title" name="title" value="<?= e($article['title']) ?>" required>

        <label for="slug">Slug (URL TH):</label>
        <input type="text" id="slug" name="slug" value="<?= e($article['slug']) ?>" required>

        <label for="content">เนื้อหาหลัก (Content TH):</label>
        <input id="content" type="hidden" name="content" value="<?= e($article['content']) ?>">
        <trix-editor input="content"></trix-editor>

        <label for="excerpt">สรุปเนื้อหา (Excerpt TH):</label>
        <textarea id="excerpt" name="excerpt" rows="3"><?= e($article['excerpt']) ?></textarea>
      </fieldset>

      <hr style="margin: 20px 0;">

      <fieldset>
        <legend>ข้อมูลภาษาอังกฤษ (English Content)</legend>
        <label for="title_en">ชื่อบทความ (Title EN):</label>
        <input type="text" id="title_en" name="title_en" value="<?= e($article['title_en'] ?? '') ?>">

        <label for="slug_en">Slug (URL EN):</label>
        <input type="text" id="slug_en" name="slug_en" value="<?= e($article['slug_en'] ?? '') ?>">

        <label for="content_en">เนื้อหาหลัก (Content EN):</label>
        <input id="content_en" type="hidden" name="content_en" value="<?= e($article['content_en'] ?? '') ?>">
        <trix-editor input="content_en"></trix-editor>

        <label for="excerpt_en">สรุปเนื้อหา (Excerpt EN):</label>
        <textarea id="excerpt_en" name="excerpt_en" rows="3"><?= e($article['excerpt_en'] ?? '') ?></textarea>
      </fieldset>

      <hr style="margin: 20px 0;">

      <fieldset>
        <legend>ข้อมูลทั่วไป (General Information)</legend>
        <div class="form-group">
          <label for="category">หมวดหมู่ (Category):</label>
          <select id="category" name="category">
            <option value="tip" <?= ($article['category'] ?? '') === 'tip' ? 'selected' : '' ?>>เทคนิค (Tips & Tricks)</option>
            <option value="repair" <?= ($article['category'] ?? '') === 'repair' ? 'selected' : '' ?>>การซ่อม (Repair Insights)</option>
            <option value="update" <?= ($article['category'] ?? '') === 'update' ? 'selected' : '' ?>>อัปเดต (Updates)</option>
          </select>
        </div>

        <label for="youtube_url">YouTube Video ID:</label>
        <input type="text" id="youtube_url" name="youtube_url" value="<?= e($article['youtube_url'] ?? '') ?>">

        <label for="image">เปลี่ยนภาพหลัก (Replace Main Image):</label>
        <input type="file" id="image" name="image" accept="image/webp" onchange="previewMainImage(this)">
        <div id="mainImagePreview" style="margin-top:10px;">
          <?php if (!empty($article['image'])): ?>
            <p style="margin-bottom:5px;">รูปปัจจุบัน:</p>
            <img src="../../uploads/<?= e($article['image']) ?>" style="max-width:200px; border-radius:8px;" alt="Current main image">
          <?php endif; ?>
        </div>
      </fieldset>

      <hr style="margin: 20px 0;">

      <fieldset>
        <legend>จัดการภาพเพิ่มเติม (Manage Additional Images)</legend>
        
        <label>ภาพเพิ่มเติมที่มีอยู่:</label>
        <div id="existing-images-container">
          <?php foreach ($additionalImages as $index => $img): ?>
            <div class="additional-image-group" style="border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 8px;">
              <div style="display:flex; align-items:flex-start; gap:15px;">
                <img src="../../<?= e($img['image_path']) ?>" style="max-width:100px; border-radius:8px;">
                <div style="flex-grow:1;">
                  <label for="existing-caption-th-<?= $img['id'] ?>">คำอธิบายภาพ (Caption TH):</label>
                  <input id="existing-caption-th-<?= $img['id'] ?>" type="hidden" name="existing_captions_th[<?= $img['id'] ?>]" value="<?= e($img['caption'] ?? '') ?>">
                  <trix-editor input="existing-caption-th-<?= $img['id'] ?>"></trix-editor>
                  
                  <label for="existing-caption-en-<?= $img['id'] ?>" style="margin-top:10px;">คำอธิบายภาพ (Caption EN):</label>
                  <input id="existing-caption-en-<?= $img['id'] ?>" type="hidden" name="existing_captions_en[<?= $img['id'] ?>]" value="<?= e($img['caption_en'] ?? '') ?>">
                  <trix-editor input="existing-caption-en-<?= $img['id'] ?>"></trix-editor>
                </div>
              </div>
              <a href="delete_image.php?id=<?= $img['id'] ?>&article_id=<?= $id ?>" class="btn-delete" onclick="return confirm('แน่ใจนะว่าจะลบภาพและคำอธิบายนี้?')" style="color:red; text-decoration:none; margin-top:10px; display:inline-block;">ลบภาพนี้</a>
            </div>
          <?php endforeach; ?>
        </div>

        <label style="margin-top:20px; display:block;">เพิ่มภาพเพิ่มเติมใหม่ + คำอธิบาย:</label>
        <div id="additional-container"></div>
        <button type="button" onclick="addMoreImages()" style="margin-top: 10px;">
          <span class="material-symbols-rounded" style="vertical-align: middle;">add_photo_alternate</span> Add More Images
        </button>
      </fieldset>

      <div class="form-actions" style="margin-top:20px;">
        <div class="form-checkbox">
          <input type="checkbox" name="status" id="status" <?= !empty($article['status']) ? 'checked' : '' ?>>
          <label for="status">เผยแพร่บทความ (Publish)</label>
        </div>
      </div>

      <div style="margin-top:20px;">
        <button type="submit">บันทึกการเปลี่ยนแปลง (Save Changes)</button>
        <a href="index.php" class="back-link" style="margin-left: 10px;">← ย้อนกลับ (Back)</a>
      </div>
    </form>
  </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<script type="text/javascript" src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>

<script>
let imageIndex = <?= count($additionalImages) ?>;

function addMoreImages() {
  const container = document.getElementById('additional-container');
  const div = document.createElement('div');
  div.className = 'additional-image-group';
  div.style.border = '1px solid #ddd';
  div.style.padding = '15px';
  div.style.borderRadius = '8px';
  div.style.marginBottom = '15px';

  const captionIdTh = 'caption-th-new-' + imageIndex;
  const captionIdEn = 'caption-en-new-' + imageIndex;

  div.innerHTML = 
    '<div style="text-align: right;">' +
        '<button type="button" class="remove-image-btn" onclick="removeImageGroup(this)" style="background: #ff4d4d; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer;">✕</button>' +
    '</div>' +
    '<label>เลือกรูปใหม่ (Select New Image):</label>' +
    '<input type="file" name="additional_images[]" onchange="previewAdditionalImage(this)" accept="image/webp">' +
    '<img src="" alt="Preview" style="display: none; max-width: 150px; border-radius: 8px; margin: 10px 0;">' +
    '<label for="' + captionIdTh + '" style="margin-top: 10px;">คำอธิบายภาพ (Caption TH):</label>' +
    '<input id="' + captionIdTh + '" type="hidden" name="caption_detail_th[]">' +
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

  // เก็บ HTML ของรูปเดิมที่แสดงอยู่ตอนแรกไว้ก่อน
  const originalImageHTML = `<?= json_encode(
      !empty($article['image'])
      ? '<p style="margin-bottom:5px;">รูปปัจจุบัน:</p><img src="../../uploads/' . e($article['image']) . '" style="max-width:200px; border-radius:8px;" alt="Current main image">'
      : ''
  ) ?>`;

  if (input.files && input.files[0]) {
    // กรณีที่ 1: ผู้ใช้เลือกไฟล์ใหม่
    const reader = new FileReader();
    reader.onload = function (e) {
      // แสดงพรีวิวของรูปใหม่ที่เลือก
      previewContainer.innerHTML = '<img src="' + e.target.result + '" style="max-width:200px; border-radius:8px;">';
    };
    reader.readAsDataURL(input.files[0]);
  } else {
    // กรณีที่ 2: ผู้ใช้กด Cancel หรือล้างไฟล์ที่เลือก
    // ให้เอารูปเดิมกลับมาแสดงเหมือนตอนเปิดหน้าเว็บ
    previewContainer.innerHTML = originalImageHTML;
  }
}
function removeImageGroup(button) {
  const group = button.closest('.additional-image-group');
  if (group) {
    group.remove();
  }
}
</script>