<?php
/*
 * edit.php (CLEANED UP - EDIT-ONLY)
 * - [GEMINI EDIT v24 - THE REAL PATH FIX]
 * - PHP (Save): NOW SAVES FULL PATH (e.g., '/uploads/articles/pic.jpg')
 * - HTML (Display): NOW READS FULL PATH FROM DB (No prefix)
 * - This fixes the "Double Path" bug
 * - All other v22 features (Modals, JS Validation, CSRF) are kept.
 */

// ================================================================
// 1) Auth & DB
// ================================================================
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// [กูแก้] เพิ่ม CSRF Token (สำหรับฟอร์มหลัก)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// ---------------- Helpers ----------------
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function postv($k,$d=null){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function post_arr($k){ return isset($_POST[$k]) && is_array($_POST[$k]) ? $_POST[$k] : []; }

// Upload config & helpers
// [กูแก้] ตั้งค่าขนาดไฟล์ตามใจมึง
const MAX_MAIN_SIZE = 5 * 1024 * 1024; // 5MB
const MAX_GALLERY_SIZE = 5 * 1024 * 1024; // 5MB (สำหรับรูปย่อย)
$ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp']; // อนุญาตไฟล์ต้นฉบับ
$ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp']; // อนุญาต Mime type ต้นฉบับ

// <-- [กูเพิ่ม] คำนวณ MB ไว้โชว์
$max_main_mb = round(MAX_MAIN_SIZE / 1024 / 1024, 1);
$max_gallery_mb = round(MAX_GALLERY_SIZE / 1024 / 1024, 1);
// --- [จบจุดที่เพิ่ม] ---

function sanitize_filename($name){ $name = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $name); return substr($name, -180); }

// [กูแก้] อัปเกรดฟังก์ชันเช็คไฟล์
function valid_image_upload(array $file, int $maxSize, array $allowedMime): bool {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) return false;
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return false;
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) return false;

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (empty($ext) || !in_array($ext, $GLOBALS['ALLOWED_EXT'], true)) {
        return false;
    }
    return true;
}

// ---------------- Init ----------------
// [กูแก้!!] นี่คือ Path ที่มึงขอ
$upload_dir_path = __DIR__ . '/../../uploads/articles'; 
$upload_dir_url  = '/uploads/articles'; // [กูแก้] path สำหรับ DB
if (!is_dir($upload_dir_path)) {
  @mkdir($upload_dir_path, 0775, true);
}

$id = max(0, (int)getv('id', 0));
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$is_edit_mode = true;
$page_title = "แก้ไขบทความ (ID: $id)";
$error = '';
$success = getv('saved') ? 'บันทึกข้อมูลเรียบร้อยแล้ว!' : '';

// ================================================================
// 3) Load for edit (ย้ายมาทำก่อน)
// ================================================================
$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
  die('ไม่พบบทความนี้ (Article not found)');
}

$stmtImages = $pdo->prepare("SELECT * FROM article_images WHERE article_id = ? ORDER BY id ASC");
$stmtImages->execute([$id]);
$additionalImages = $stmtImages->fetchAll(PDO::FETCH_ASSOC);


// ================================================================
// 2) Handle POST (Save)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // [กูแก้] เช็ค CSRF Token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Token ไม่ถูกต้อง, กรุณาลองใหม่';
    } else {
        try {
            $pdo->beginTransaction();
            
            $admin_id = $_SESSION['admin_id'] ?? null;
            if (!$admin_id) {
                throw new Exception('เซสชั่นหมดอายุ, กรุณาล็อกอินใหม่'); 
            }
            
            // [กูแก้] ดึงค่าจากฟอร์ม
            $title = $_POST['title'] ?? ($article['title'] ?? ''); 
            $slug = trim($_POST['slug'] ?? '');
            $content = $_POST['content'] ?? '';
            $excerpt = $_POST['excerpt'] ?? '';

            $title_en = $_POST['title_en'] ?? ($article['title_en'] ?? '');
            $slug_en = trim($_POST['slug_en'] ?? '');
            $content_en = $_POST['content_en'] ?? '';
            $excerpt_en = $_POST['excerpt_en'] ?? '';

            $category = $_POST['category'] ?? ($article['category'] ?? '');
            $youtube_url = $_POST['youtube_url'] ?? ($article['youtube_url'] ?? '');
            $status = isset($_POST['status']) ? 1 : 0;

            // [กูแก้!!] $imageName ตอนนี้คือ Path เต็ม (เช่น /uploads/articles/pic.jpg)
            $imageName = $article['image']; 
            $imageFilename = basename($imageName); // -> 'pic.jpg'

            // [กูแก้!!] ผ่าตัดระบบอัปโหลด + ลบ (รูปหลัก)
            
            // 2.1: เช็คว่า "มาร์ค" ให้ลบรูปเก่ามั้ย?
            if (postv('delete_existing_image') === '1') {
                if ($imageFilename && file_exists($upload_dir_path . "/" . $imageFilename)) {
                    @unlink($upload_dir_path . "/" . $imageFilename);
                }
                $imageName = ''; // ล้างชื่อไฟล์ใน DB
            }

            // 2.2: เช็คว่ามี "รูปใหม่" อัปโหลดมาทับมั้ย?
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                 
                 if (!valid_image_upload($_FILES['image'], MAX_MAIN_SIZE, $ALLOWED_MIME)) { 
                    throw new Exception('ไฟล์รูปหลักไม่ถูกต้อง/ใหญ่เกิน/ชนิดผิด (สูงสุด ' . $max_main_mb . 'MB)'); 
                 }
                 
                 $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)); 
                 $new_filename = time() . '_' . sanitize_filename($_FILES['image']['name']);
                 
                 $target_file = $upload_dir_path . '/' . $new_filename; 
                 if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) { 
                    throw new Exception('ย้ายไฟล์รูปหลักไม่สำเร็จ'); 
                 }

                 // [สำคัญ] ถ้าอัปไฟล์ใหม่... ต้องลบไฟล์ "เก่า" ทิ้ง
                 if ($imageFilename && file_exists($upload_dir_path . "/" . $imageFilename)) {
                    @unlink($upload_dir_path . "/" . $imageFilename);
                 }
                 
                 // [กูแก้!!] เซฟ "Path เต็ม"
                 $imageName = $upload_dir_url . '/' . $new_filename; 
            }
            // [จบจุดที่กูแก้]

            $stmtUpdate = $pdo->prepare("UPDATE articles SET 
                                          title=?, slug=?, excerpt=?, content=?, category=?, image=?, youtube_url=?, status=?,
                                          title_en=?, slug_en=?, excerpt_en=?, content_en=?
                                        WHERE id=?");
                                        
            $stmtUpdate->execute([
              $title, $slug, $excerpt, $content, $category, $imageName, $youtube_url, $status,
              $title_en, $slug_en, $excerpt_en, $content_en,
              $id
            ]);

            // [กูแก้] อัปเดต Caption รูปเก่า
            if (isset($_POST['existing_captions_th'])) {
              $stmtCaption = $pdo->prepare("UPDATE article_images SET caption = ?, caption_en = ? WHERE id = ? AND article_id = ?");
              foreach ($_POST['existing_captions_th'] as $img_id => $caption_th) {
                $caption_en = $_POST['existing_captions_en'][$img_id] ?? '';
                $stmtCaption->execute([$caption_th, $caption_en, $img_id, $id]); 
              }
            }

            // [กูแก้!!] ผ่าตัดระบบอัปโหลด (รูปย่อย)
            if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
              $imgStmt = $pdo->prepare("INSERT INTO article_images (article_id, image_path, caption, caption_en) VALUES (?, ?, ?, ?)");
                
              foreach ($_FILES['additional_images']['tmp_name'] as $index => $tmpName) {
                  if (isset($_FILES['additional_images']['error'][$index]) && $_FILES['additional_images']['error'][$index] === UPLOAD_ERR_OK) {
                      
                      $file_check = [
                          'name' => $_FILES['additional_images']['name'][$index],
                          'type' => $_FILES['additional_images']['type'][$index],
                          'tmp_name' => $tmpName,
                          'error' => $_FILES['additional_images']['error'][$index],
                          'size' => $_FILES['additional_images']['size'][$index]
                      ];
                      
                      if (!valid_image_upload($file_check, MAX_GALLERY_SIZE, $ALLOWED_MIME)) {
                          error_log("Skipped invalid gallery file: " . $file_check['name']);
                          continue;
                      }
                      
                      $ext = strtolower(pathinfo($file_check['name'], PATHINFO_EXTENSION));
                      $fileName = time() . '-' . uniqid() . '.' . $ext;
                      $target_file = $upload_dir_path . '/' . $fileName;

                      if (move_uploaded_file($tmpName, $target_file)) {
                          $caption_th = $_POST['caption_detail_th'][$index] ?? ''; 
                          $caption_en = $_POST['caption_detail_en'][$index] ?? ''; 
                          
                          // [กูแก้!!] เซฟ "Path เต็ม"
                          $db_path = $upload_dir_url . '/' . $fileName; 
                          $imgStmt->execute([$id, $db_path, $caption_th, $caption_en]);
                      }
                  }
              }
            }
            // [จบจุดที่กูแก้]

            $pdo->commit();
            header("Location: index.php"); // เด้งกลับ Index (ถูกแล้ว)
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'ฉิบหาย! บันทึกไม่สำเร็จ: '.$e->getMessage();
            error_log('[edit.php Exception] '.$e->getMessage());
        }
    } // จบ else (CSRF check)
} // จบ if ($_SERVER['REQUEST_METHOD'] === 'POST')


// ================================================================
// 4) HTML Output
// ================================================================
include __DIR__ . '/../templates/header_admin.php';
?>

<style>
  /* [CSS ทั้งก้อน... เหมือนเดิม] */
  .drop-zone{border:2px dashed #ccc;border-radius:8px;padding:25px;text-align:center;color:#777;cursor:pointer;transition:all .2s}
  .drop-zone.is-dragover{border-color:var(--primary,#007aff);background:var(--primary-ghost,#f4f8ff)}
  .drop-zone input[type=file]{display:none}
  .image-preview{display:flex;flex-wrap:wrap;gap:10px;margin-top:15px}
  .image-preview-item{position:relative;border:1px solid var(--ui-border,#ddd);border-radius:5px;padding:5px;background:var(--ui-bg-alt,#f9f9f9)}
  .image-preview-item img{max-width:100px;max-height:100px;object-fit:cover;border-radius:4px;display:block}
  .js-delete-preview {
      position: absolute; top: -8px; right: -8px; width: 22px; height: 22px;
      background: #c00; color: white; border: 2px solid white;
      border-radius: 50%; font-size: 14px; font-weight: bold;
      line-height: 18px; text-align: center; cursor: pointer;
      padding: 0; z-index: 2; box-shadow: 0 1px 3px rgba(0,0,0,0.3);
  }
  .js-delete-preview:hover { background: #a00; }
  .is-marked-for-delete { display: none !important; }
  .existing-image img{border:2px solid var(--primary,#007aff)}
  .msg{padding:15px;border-radius:5px;margin-bottom:20px;font-weight:500}
  .msg-error{background:#ffebee;color:#c62828;border:1px solid #c62828}
  .msg-success{background:#e8f5e9;color:#2e7d32;border:1px solid #2e7d32}
  .form-helper-text {
      font-size: 0.85rem; color: #555;
      margin: -5px 0 10px; line-height: 1.4;
  }
  .loading-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6);
    z-index: 9998; flex-direction: column; align-items: center; justify-content: center;
    color: white; font-size: 1.2rem; font-weight: 600;
  }
  .loading-overlay.show { display: flex; }
  .loading-spinner {
    border: 4px solid #f3f3f3; border-top: 4px solid var(--primary, #007aff);
    border-radius: 50%; width: 50px; height: 50px;
    animation: spin 1s linear infinite; margin-bottom: 20px;
  }
  @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
  .confirm-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6);
    z-index: 9999; align-items: center; justify-content: center;
  }
  .confirm-overlay.show { display: flex; }
  .confirm-dialog {
    background: var(--ui-surface, #fff); padding: 25px 30px;
    border-radius: var(--radius-lg, 16px); box-shadow: var(--shadow-card, 0 4px 12px rgba(0,0,0,.08));
    width: 90%; max-width: 400px; text-align: center;
  }
  .confirm-dialog p { font-size: 1.1rem; font-weight: 600; margin: 0 0 20px; color: var(--text-default, #222); }
  .confirm-actions { display: flex; justify-content: center; gap: 15px; }
  .cmodal-btn-cancel,
  .cmodal-btn-confirm,
  .cmodal-btn-primary {
    flex: 1; padding: 10px 20px; font-size: 1rem; font-weight: 600;
    border: none; cursor: pointer; text-decoration: none; line-height: 1.5;
    border-radius: 6px; transition: background-color 0.2s, border-color 0.2s;
  }
  .cmodal-btn-cancel { background-color: #f0f0f0; color: #333; border: 1px solid #ccc; }
  .cmodal-btn-cancel:hover { background-color: #e0e0e0; }
  .cmodal-btn-confirm { background: #c00; color: white; border: 1px solid #c00; }
  .cmodal-btn-confirm:hover { background: #a00; }
  .cmodal-btn-primary { background: var(--primary, #007aff); color: white; border: 1px solid var(--primary, #007aff); }
  .cmodal-btn-primary:hover { background: #0056b3; }
  .alert-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6);
    z-index: 10000; align-items: center; justify-content: center;
  }
  .alert-overlay.show { display: flex; }
  .alert-dialog {
    background: var(--ui-surface, #fff); padding: 25px 30px;
    border-radius: var(--radius-lg, 16px); box-shadow: var(--shadow-card, 0 4px 12px rgba(0,0,0,.08));
    width: 90%; max-width: 400px; text-align: center;
    display: flex; flex-direction: column; align-items: center;
  }
  .alert-icon { font-size: 48px; color: #f59e0b; margin-bottom: 15px; user-select: none; }
  .alert-dialog p { font-size: 1.1rem; font-weight: 600; margin: 0 0 20px; color: var(--text-default, #222); white-space: pre-wrap; }
  .alert-actions { width: 100%; }
</style>

<main class="main" id="main-content">
  <div class="topbar">
    <span>แก้ไขบทความ (Edit Article #<?= h($id) ?>)</span>
    <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
  </div>

  <div class="form-section">
    <?php if ($error): ?><div class="msg msg-error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="msg msg-success"><?= h($success) ?></div><?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" id="csrf_token" name="csrf_token" value="<?= h($CSRF) ?>">
        <input type="hidden" name="delete_existing_image" id="delete_existing_image" value="0">
        
      <fieldset>
        <legend>ข้อมูลภาษาไทย (Thai Content)</legend>
        <label for="title">ชื่อบทความ (Title TH):</label>
        <input type="text" id="title" name="title" value="<?= h($article['title']) ?>" required>

        <label for="slug">Slug (URL TH):</label>
        <input type="text" id="slug" name="slug" value="<?= h($article['slug']) ?>" required>

        <label for="content">เนื้อหาหลัก (Content TH):</label>
        <input id="content" type="hidden" name="content" value="<?= h($article['content']) ?>">
        <trix-editor input="content"></trix-editor>

        <label for="excerpt">สรุปเนื้อหา (Excerpt TH):</label>
        <textarea id="excerpt" name="excerpt" rows="3"><?= h($article['excerpt']) ?></textarea>
      </fieldset>

      <hr style="margin: 20px 0;">

      <fieldset>
        <legend>ข้อมูลภาษาอังกฤษ (English Content)</legend>
        <label for="title_en">ชื่อบทความ (Title EN):</label>
        <input type="text" id="title_en" name="title_en" value="<?= h($article['title_en'] ?? '') ?>">

        <label for="slug_en">Slug (URL EN):</label>
        <input type="text" id="slug_en" name="slug_en" value="<?= h($article['slug_en'] ?? '') ?>">

        <label for="content_en">เนื้อหาหลัก (Content EN):</label>
        <input id="content_en" type="hidden" name="content_en" value="<?= h($article['content_en'] ?? '') ?>">
        <trix-editor input="content_en"></trix-editor>

        <label for="excerpt_en">สรุปเนื้อหา (Excerpt EN):</label>
        <textarea id="excerpt_en" name="excerpt_en" rows="3"><?= h($article['excerpt_en'] ?? '') ?></textarea>
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
        <input type="text" id="youtube_url" name="youtube_url" value="<?= h($article['youtube_url'] ?? '') ?>">

        <label for="main_image_drop_zone">เปลี่ยนภาพหลัก (Replace Main Image):</label>
        <p class="form-helper-text">อนุญาต .jpg, .png, .webp (สูงสุด <?= $max_main_mb ?>MB)</p>
        <input type="file" id="main_image" name="main_image" accept="image/*">
        <div class="drop-zone" id="main_image_drop_zone">
            <span class="material-symbols-rounded" style="font-size:32px">upload_file</span>
            <div>ลากไฟล์ใหม่มาใส่ หรือ คลิกเพื่อเปลี่ยน</div>
        </div>
        
        <div class="image-preview" id="mainImagePreview">
          <?php if (!empty($article['image'])): ?>
            <div class="image-preview-item existing-image" data-id="1">
              <img src="<?= h($article['image']) ?>" alt="Current main image">
              <button type="button" class="js-delete-preview js-delete-existing" aria-label="ลบรูปเก่า">&times;</button>
            </div>
          <?php endif; ?>
          </div>
        <input type="hidden" name="existing_main_image" value="<?= h($article['image']) ?>">
        </fieldset>

      <hr style="margin: 20px 0;">

      <fieldset>
        <legend>จัดการภาพเพิ่มเติม (Manage Additional Images)</legend>
        
        <label>ภาพเพิ่มเติมที่มีอยู่:</label>
        <div id="existing-images-container">
          <?php foreach ($additionalImages as $index => $img): ?>
            <div class="additional-image-group" style="border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 8px;">
              <div style="display:flex; align-items:flex-start; gap:15px;">
                <img src="<?= h($img['image_path']) ?>" style="max-width:100px; border-radius:8px;">
                <div style="flex-grow:1;">
                  <label for="existing-caption-th-<?= $img['id'] ?>">คำอธิบายภาพ (Caption TH):</label>
                  <input id="existing-caption-th-<?= $img['id'] ?>" type="hidden" name="existing_captions_th[<?= $img['id'] ?>]" value="<?= h($img['caption'] ?? '') ?>">
                  <trix-editor input="existing-caption-th-<?= $img['id'] ?>"></trix-editor>
                  
                  <label for="existing-caption-en-<?= $img['id'] ?>" style="margin-top:10px;">คำอธิบายภาพ (Caption EN):</label>
                  <input id="existing-caption-en-<?= $img['id'] ?>" type="hidden" name="existing_captions_en[<?= $img['id'] ?>]" value="<?= h($img['caption_en'] ?? '') ?>">
                  <trix-editor input="existing-caption-en-<?= $img['id'] ?>"></trix-editor>
                </div>
              </div>
              <a href="delete_image.php?id=<?= $img['id'] ?>&article_id=<?= $id ?>&csrf=<?= $CSRF ?>" class="btn-delete js-delete-gallery-db" style="color:red; text-decoration:none; margin-top:10px; display:inline-block;">ลบภาพนี้</a>
            </div>
          <?php endforeach; ?>
        </div>

        <label style="margin-top:20px; display:block;">เพิ่มภาพเพิ่มเติมใหม่ + คำอธิบาย:</label>
        <p class="form-helper-text">อนุญาต .jpg, .png, .webp (ไฟล์ละไม่เกิน <?= $max_gallery_mb ?>MB). รวมไม่เกิน 5 รูป</p>
        <div id="additional-container"></div>
        <button type="button" id="addMoreImagesBtn" style="margin-top: 10px;" class="btn-secondary">
          <span class="material-symbols-rounded" style="vertical-align: middle;">add_photo_alternate</span> เพิ่มรูปเพิ่มเติม
        </button>
      </fieldset>

      <div class="form-actions" style="margin-top:20px;">
        <div class="form-checkbox">
          <input type="checkbox" name="status" id="status" <?= !empty($article['status']) ? 'checked' : '' ?>>
          <label for="status">เผยแพร่บทความ (Publish)</label>
        </div>
      </div>

      <div style="margin-top:20px;">
        <button type="submit" class="btn-primary">บันทึกการเปลี่ยนแปลง (Save Changes)</button>
        <a href="index.php" class="btn-secondary" style="margin-left: 10px;">← ย้อนกลับ (Back)</a>
      </div>
    </form>
  </div>
</main>

<div id="loadingOverlay" class="loading-overlay"> ... </div>
<div id="alertOverlay" class="alert-overlay"> ... </div>
<div id="confirmOverlay" class="confirm-overlay"> ... </div>

<?php include '/../templates/footer_admin.php'; ?>

<link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<script type="text/javascript" src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', () => {

    // ... (JS ทั้งหมด... เหมือนเดิม... ไม่ต้องแก้) ...
    // (JS (v22) มัน "ฉลาด" อยู่แล้ว... มันไม่ได้พัง)

    // --- [กูแก้] Logic "กำลังบันทึก" ---
    const mainForm = document.querySelector('.form-section > form'); 
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (mainForm && loadingOverlay) {
        mainForm.addEventListener('submit', (e) => {
            loadingOverlay.classList.add('show');
        });
    }

    // --- [กูเพิ่ม!!] Logic "แจ้งเตือน" กลางจอ ---
    const alertOverlay = document.getElementById('alertOverlay');
    const alertMsgEl = document.getElementById('alertMessage');
    const alertOkBtn = document.getElementById('alertBtnOk');
    function showCustomAlert(message) {
        if (!alertOverlay || !alertMsgEl) { alert(message); return; }
        alertMsgEl.textContent = message;
        alertOverlay.classList.add('show');
    }
    alertOkBtn.addEventListener('click', () => {
        alertOverlay.classList.remove('show');
    });

    // --- [กูเพิ่ม!!] Logic "ยืนยัน" กลางจอ ---
    const confirmOverlay = document.getElementById('confirmOverlay');
    const confirmMsgEl = document.getElementById('confirmMessage');
    const confirmOkBtn = document.getElementById('confirmBtnOk');
    const confirmCancelBtn = document.getElementById('confirmBtnCancel');
    let confirmCallback = null; 
    function showCustomConfirm(message, onConfirm) {
        if (!confirmOverlay || !confirmMsgEl) {
            if (confirm(message)) onConfirm();
            return;
        }
        confirmMsgEl.textContent = message;
        confirmCallback = onConfirm; 
        confirmOverlay.classList.add('show');
    }
    confirmCancelBtn.addEventListener('click', () => {
        confirmOverlay.classList.remove('show');
        confirmCallback = null;
    });
    confirmOkBtn.addEventListener('click', () => {
        if (typeof confirmCallback === 'function') confirmCallback();
        confirmOverlay.classList.remove('show');
        confirmCallback = null;
    });
    
    // --- [กูเพิ่ม!!] ดักลิงก์ "ลบรูปย่อย" ให้ใช้ป๊อปอัพสวยๆ
    document.addEventListener('click', function(e) {
        const deleteLink = e.target.closest('.js-delete-gallery-db');
        if (deleteLink) {
            e.preventDefault(); // หยุดลิงก์
            const href = deleteLink.href;
            showCustomConfirm('แน่ใจนะว่าจะลบภาพและคำอธิบายนี้? (ลบถาวร!)', () => {
                window.location.href = href; // ไปต่อ
            });
        }
    });


    // --- [กูแก้!!] สร้าง "เครื่องเช็คไฟล์" ---
    const MAX_FILE_SIZE_MAIN = <?= MAX_MAIN_SIZE ?>;
    const MAX_FILE_SIZE_GALLERY = <?= MAX_GALLERY_SIZE ?>;
    const ALLOWED_MIME_TYPES = <?= json_encode($ALLOWED_MIME) ?>;
    const ALLOWED_EXTS = <?= json_encode($ALLOWED_EXT) ?>;
    const MAX_MB_MAIN = <?= $max_main_mb ?>;
    const MAX_MB_GALLERY = <?= $max_gallery_mb ?>;
    const MAX_GALLERY_FILES = 5; 

    function validateFile(file, maxSize, maxMb, allowedMimes, allowedExts) {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (!allowedMimes.includes(file.type) || !allowedExts.includes(ext)) {
            return `ไฟล์ชนิด "${ext || '??'}" ใช้งานไม่ได้ (ต้องเป็น .jpg, .png, .webp เท่านั้น)`;
        }
        if (file.size > maxSize) {
            return `ไฟล์ "${file.name}" (${(file.size / 1024 / 1024).toFixed(1)}MB) ใหญ่เกิน (สูงสุด ${maxMb}MB)`;
        }
        return null; // No error
    }

    // [กูแก้!!] ผ่าตัด "รูปหลัก"
    (function setupMainImageDropZone() {
        const dropZoneEl = document.getElementById('main_image_drop_zone');
        const fileInputEl = document.getElementById('main_image');
        const previewEl = document.getElementById('mainImagePreview');
        const deleteImageInput = document.getElementById('delete_existing_image');
        if (!dropZoneEl || !fileInputEl || !previewEl || !deleteImageInput) return;

        function handleMainPreview(file) {
            // 1. ล้างพรีวิว "ไฟล์ใหม่" (คลาส .new-upload)
            previewEl.querySelectorAll('.js-preview-item.new-upload').forEach(el => el.remove());
            // 2. เอารูปเก่า (ถ้ามี) กลับมา
            previewEl.querySelectorAll('.existing-image').forEach(el => el.classList.remove('is-marked-for-delete'));
            deleteImageInput.value = '0'; // รีเซ็ตค่า

            if (!file) return; // ถ้าไม่มีไฟล์... ก็ไม่ต้องทำไร
            
            const reader = new FileReader();
            reader.onload = e => { 
                const url = e.target.result;
                previewEl.querySelectorAll('.existing-image').forEach(el => el.classList.add('is-marked-for-delete'));
                
                const item = document.createElement('div');
                item.className = 'image-preview-item js-preview-item new-upload';
                const img = document.createElement('img');
                img.src = url;
                img.alt = file.name;
                item.appendChild(img);
                
                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'js-delete-preview js-delete-new';
                delBtn.innerHTML = '&times;';
                delBtn.setAttribute('aria-label', 'ลบรูปใหม่นี้');
                item.appendChild(delBtn);
                
                previewEl.appendChild(item);
            };
            
            reader.onerror = () => {
                showCustomAlert(`ไฟล์ "${file.name}" เสียหายหรืออ่านไม่ได้ (อาจเป็น HEIC ที่ถูกเปลี่ยนชื่อ)`);
                fileInputEl.value = null; 
                handleMainPreview(null); 
            };
            
            reader.readAsDataURL(file);
        }
        
        function createFileList(file){ if(!file) return null; const dt=new DataTransfer(); dt.items.add(file); return dt.files; }

        dropZoneEl.addEventListener('click', () => fileInputEl.click());
        dropZoneEl.addEventListener('dragover', e => { e.preventDefault(); dropZoneEl.classList.add('is-dragover'); });
        ['dragleave','dragend'].forEach(t => dropZoneEl.addEventListener(t, () => dropZoneEl.classList.remove('is-dragover')));
        
        dropZoneEl.addEventListener('drop', e => {
            e.preventDefault(); 
            dropZoneEl.classList.remove('is-dragover');
            if (!e.dataTransfer.files.length) return;
            
            const file = e.dataTransfer.files[0];
            const error = validateFile(file, MAX_FILE_SIZE_MAIN, MAX_MB_MAIN, ALLOWED_MIME_TYPES, ALLOWED_EXTS);
            
            if (error) {
                showCustomAlert(error);
                return;
            }
            
            fileInputEl.files = createFileList(file);
            handleMainPreview(file);
        });
        
        fileInputEl.addEventListener('change', function() {
            if (!this.files.length) {
                handleMainPreview(null);
                return;
            }
            const file = this.files[0];
            const error = validateFile(file, MAX_FILE_SIZE_MAIN, MAX_MB_MAIN, ALLOWED_MIME_TYPES, ALLOWED_EXTS);
            if (error) {
                showCustomAlert(error);
                this.value = null;
                handleMainPreview(null);
                return;
            }
            handleMainPreview(file);
        });

        previewEl.addEventListener('click', function(e) {
            // 1. ลบ "รูปเก่า"
            if (e.target.classList.contains('js-delete-existing')) {
                e.preventDefault();
                const item = e.target.closest('.existing-image');
                if (!item) return;

                showCustomConfirm('คุณต้องการลบรูปนี้ (ตอนกดบันทึก) ใช่หรือไม่?', () => {
                    item.classList.add('is-marked-for-delete'); // ซ่อน
                    deleteImageInput.value = '1'; // บอก PHP ว่า "มึงลบนะ"
                });
            }

            // 2. ลบ "รูปใหม่" (ที่เพิ่งลากมา)
            if (e.target.classList.contains('js-delete-new')) {
                e.preventDefault();
                fileInputEl.value = null; // ล้างไฟล์ใน input
                handleMainPreview(null);  // รีเซ็ตพรีวิว (รูปเก่าจะโผล่กลับมา)
            }
        });
    })();


    // [กูแก้!!] ผ่าตัด "รูปย่อย" (Gallery)
    let imageIndex = <?= count($additionalImages) ?>; 
    const addMoreBtn = document.getElementById('addMoreImagesBtn');
    const container = document.getElementById('additional-container');

    function checkGalleryLimit() {
        const existingCount = document.querySelectorAll('#existing-images-container .additional-image-group').length;
        const newCount = container.querySelectorAll('.additional-image-group').length;
        const total = existingCount + newCount;
        
        if (total >= MAX_GALLERY_FILES) {
            addMoreBtn.style.display = 'none';
        } else {
            addMoreBtn.style.display = 'block';
        }
        return total < MAX_GALLERY_FILES;
    }

    // [กูแก้] เปลี่ยน addMoreImages ให้เป็น global
    window.addMoreImages = function() {
        if (!checkGalleryLimit()) {
            showCustomAlert(`อัปโหลดรูปย่อยได้สูงสุด ${MAX_GALLERY_FILES} รูปเท่านั้น`);
            return;
        }

        const div = document.createElement('div');
        div.className = 'additional-image-group';
        div.style.border = '1px solid #ddd';
        div.style.padding = '15px';
        div.style.borderRadius = '8px';
        div.style.marginBottom = '15px';

        const captionIdTh = 'caption-th-new-' + imageIndex;
        const captionIdEn = 'caption-en-new-' + imageIndex;

        div.innerHTML = `
            <div style="text-align: right;">
                <button type="button" class="remove-image-btn" style="background: #ff4d4d; color: white; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer;">✕</button>
            </div>
            <label>เลือกรูปใหม่ (Select New Image):</label>
            <p class="form-helper-text">สูงสุด ${MAX_MB_GALLERY}MB</p>
            <input type="file" name="additional_images[]" class="gallery-file-input" accept="image/*">
            <div class="drop-zone gallery-drop-zone">
                <span class="material-symbols-rounded" style="font-size:32px">upload_file</span>
                <div>ลากไฟล์มาใส่ หรือ คลิกเพื่อเลือก</div>
            </div>
            <div class="image-preview">
                </div>
            <label for="${captionIdTh}" style="margin-top: 10px;">คำอธิบายภาพ (Caption TH):</label>
            <input id="${captionIdTh}" type="hidden" name="caption_detail_th[]">
            <trix-editor input="${captionIdTh}"></trix-editor>
            <label for="${captionIdEn}" style="margin-top: 10px;">คำอธิบายภาพ (Caption EN):</label>
            <input id="${captionIdEn}" type="hidden" name="caption_detail_en[]">
            <trix-editor input="${captionIdEn}"></trix-editor>
        `;
        
        container.appendChild(div);
        
        setTimeout(() => {
            setupGalleryDropZone(div); // ผูก Event ให้ Drop Zone ใหม่
        }, 0);

        imageIndex++;
        checkGalleryLimit(); // เช็คโควต้าอีกที
    }

    // [กูแก้!!] นี่คือ "สายไฟ"
    if (addMoreBtn) {
        addMoreBtn.addEventListener('click', window.addMoreImages);
    }
    
    // [กูแก้] ฟังก์ชันสำหรับ "ผูก" Event ให้ Drop Zone รูปย่อย
    function setupGalleryDropZone(groupElement) {
        const dropZoneEl = groupElement.querySelector('.gallery-drop-zone');
        const fileInputEl = groupElement.querySelector('.gallery-file-input');
        const previewEl = groupElement.querySelector('.image-preview');
        
        function handleGalleryPreview(file) {
            previewEl.innerHTML = ''; // ล้างของเก่า (เพราะรับได้ทีละรูป)
            if (!file) return;

            const reader = new FileReader();
            reader.onload = e => {
                const url = e.target.result;
                const item = document.createElement('div');
                item.className = 'image-preview-item js-preview-item new-upload';
                const img = document.createElement('img');
                img.src = url;
                img.alt = file.name;
                item.appendChild(img);
                
                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'js-delete-preview js-delete-new';
                delBtn.innerHTML = '&times;';
                delBtn.setAttribute('aria-label', 'ลบรูปใหม่นี้');
                item.appendChild(delBtn);
                
                previewEl.appendChild(item);
            };
            reader.onerror = () => {
                showCustomAlert(`ไฟล์ "${file.name}" เสียหายหรืออ่านไม่ได้ (อาจเป็น HEIC ที่ถูกเปลี่ยนชื่อ)`);
                fileInputEl.value = null;
                handleGalleryPreview(null);
            };
            reader.readAsDataURL(file);
        }

        function createFileList(file){ if(!file) return null; const dt=new DataTransfer(); dt.items.add(file); return dt.files; }

        dropZoneEl.addEventListener('click', () => fileInputEl.click());
        dropZoneEl.addEventListener('dragover', e => { e.preventDefault(); dropZoneEl.classList.add('is-dragover'); });
        ['dragleave','dragend'].forEach(t => dropZoneEl.addEventListener(t, () => dropZoneEl.classList.remove('is-dragover')));

        dropZoneEl.addEventListener('drop', e => {
            e.preventDefault(); 
            dropZoneEl.classList.remove('is-dragover');
            if (!e.dataTransfer.files.length) return;
            
            const file = e.dataTransfer.files[0];
            const error = validateFile(file, MAX_FILE_SIZE_GALLERY, MAX_MB_GALLERY, ALLOWED_MIME_TYPES, ALLOWED_EXTS);
            if (error) {
                showCustomAlert(error);
                return;
            }
            fileInputEl.files = createFileList(file);
            handleGalleryPreview(file);
        });

        fileInputEl.addEventListener('change', function() {
            if (!this.files.length) {
                handleGalleryPreview(null);
                return;
            }
            const file = this.files[0];
            const error = validateFile(file, MAX_FILE_SIZE_GALLERY, MAX_MB_GALLERY, ALLOWED_MIME_TYPES, ALLOWED_EXTS);
            if (error) {
                showCustomAlert(error);
                this.value = null;
                handleGalleryPreview(null);
                return;
            }
            handleGalleryPreview(file);
        });

        previewEl.addEventListener('click', function(e) {
            if (e.target.classList.contains('js-delete-new')) {
                e.preventDefault();
                fileInputEl.value = null; 
                handleGalleryPreview(null);
            }
        });

        // [กูแก้] ผูก Event ให้ปุ่มลบ (X) แดงๆ
        const removeBtn = groupElement.querySelector('.remove-image-btn');
        if(removeBtn) {
            removeBtn.addEventListener('click', () => removeImageGroup(removeBtn));
        }
    }

    // [กูแก้] รื้อฟังก์ชันเก่าทิ้ง...
    window.removeImageGroup = function(button) {
      const group = button.closest('.additional-image-group');
      if (group) {
        showCustomConfirm("คุณต้องการลบช่องอัปโหลดรูปนี้ใช่หรือไม่?", () => {
             group.remove();
             checkGalleryLimit(); // [กูเพิ่ม] ลบแล้วต้องคืนโควต้า
        });
      }
    }

    // [กูแก้] รันครั้งแรก...
    checkGalleryLimit(); 
    
  }); // End DOMContentLoaded
</script>