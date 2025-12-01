<?php
/*
 * add.php (CLEANED UP - ADD-ONLY)
 * - [GEMINI EDIT v19 - NO GD / ADD-ONLY]
 * - Changed upload path to /uploads/repairs/
 * - Ripped out WebP-only logic
 * - Replaced with 5MB validation + move_uploaded_file
 * - Added CSRF protection
 * - Added Client-side validation (JS)
 * - Added Custom Modals (Alert, Loading)
 * - Added "Fake JPG" (HEIC) detection
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
$ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp']; // อนุญาตไฟล์ต้นฉบับ
$ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp']; // อนุญาต Mime type ต้นฉบับ

// <-- [กูเพิ่ม] คำนวณ MB ไว้โชว์
$max_main_mb = round(MAX_MAIN_SIZE / 1024 / 1024, 1);
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
// [กูแก้] รื้อ $is_edit_mode ทิ้ง... นี่คือหน้า ADD
$page_title = 'เพิ่มผลงานใหม่';
$error = '';

// [กูแก้!!] นี่คือ Path ที่มึงขอ
$upload_dir_path = __DIR__ . '/../../uploads/repairs'; 
$upload_dir_url  = '/uploads/repairs';
if (!is_dir($upload_dir_path)) {
  @mkdir($upload_dir_path, 0775, true);
}


// ================================================================
// 2) Handle POST (Save)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // [กูแก้] เช็ค CSRF Token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'Token ไม่ถูกต้อง, กรุณาลองใหม่';
    } else {
        try {
            $admin_id = $_SESSION['admin_id'] ?? null;
            if (!$admin_id) {
                throw new Exception('เซสชั่นหมดอายุ, กรุณาล็อกอินใหม่'); 
            }

            // Thai fields
            $title = $_POST['title'] ?? '';
            $model = $_POST['model'] ?? '';
            $issue = $_POST['issue'] ?? '';
            $fix_detail = $_POST['fix_detail'] ?? '';
            $category = $_POST['category'] ?? '';

            // NEW: English fields
            $title_en = $_POST['title_en'] ?? '';
            $issue_en = $_POST['issue_en'] ?? '';
            $fix_detail_en = $_POST['fix_detail_en'] ?? '';

            // [กูแก้!!] ผ่าตัดระบบอัปโหลด
            $image = ''; // เริ่มว่างๆ
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                 
                 // 1. เช็คไฟล์ (ใช้ MAX_MAIN_SIZE)
                 if (!valid_image_upload($_FILES['image'], MAX_MAIN_SIZE, $ALLOWED_MIME)) { 
                    throw new Exception('ไฟล์รูปหลักไม่ถูกต้อง/ใหญ่เกิน/ชนิดผิด (สูงสุด ' . $max_main_mb . 'MB)'); 
                 }
                 
                 // 2. กลับไปใช้ชื่อไฟล์แบบเดิม (มี .ext)
                 $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)); 
                 $filename = time() . '_' . sanitize_filename($_FILES['image']['name']);
                 
                 // 3. ย้ายไฟล์ (ไม่ต้องแปลง)
                 $target_file = $upload_dir_path . '/' . $filename; 
                 if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) { 
                    throw new Exception('ย้ายไฟล์รูปหลักไม่สำเร็จ'); 
                 }
                 
                 // 4. [สำคัญ] path ที่เก็บลง DB (แค่ชื่อไฟล์)
                 $image = $filename; 
            }
            // [จบจุดที่กูแก้]

            $stmt = $pdo->prepare("INSERT INTO repairs (admin_id, title, model, issue, fix_detail, image, category, created_at, title_en, issue_en, fix_detail_en) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)");
            
            if ($stmt->execute([$admin_id, $title, $model, $issue, $fix_detail, $image, $category, $title_en, $issue_en, $fix_detail_en])) {
                $_SESSION['success_message'] = "ผลงานซ่อมถูกเพิ่มเรียบร้อยแล้ว";
                header("Location: index.php");
                exit;
            } else {
                throw new Exception("บันทึกข้อมูลลง Database ไม่สำเร็จ");
            }
        } catch (Exception $e) {
            $error = 'ฉิบหาย! บันทึกไม่สำเร็จ: '.$e->getMessage();
            error_log('[add.php Exception] '.$e->getMessage());
        }
    } // จบ else (CSRF check)
} // จบ if ($_SERVER['REQUEST_METHOD'] === 'POST')

// ================================================================
// 3) Load for edit
// ================================================================
// [กูแก้] รื้อทิ้ง... เพราะนี่คือหน้า ADD... ไม่ต้อง Load
// ...
// ================================================================

// ================================================================
// 4) HTML Output
// ================================================================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<style>
  /* [กูแก้] ก๊อป CSS ทั้งก้อนจาก edit_listing.php มา... */
  /* (กูลบ .eav-item, .policy-..., .report-..., .kpi-... ทิ้งให้... เหลือแต่ของที่มึงใช้) */
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
  textarea.small{min-height:80px}
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
    <span><?= h($page_title) ?></span>
    <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
  </div>

  <h2 style="margin: 20px 0;">เพิ่มข้อมูลผลงาน (Add New Repair Work)</h2>
  
  <?php if ($error): ?><div class="msg msg-error"><?= h($error) ?></div><?php endif; ?>
  
  <form action="add.php" method="POST" enctype="multipart/form-data" class="form-section">
    <input type="hidden" id="csrf_token" name="csrf_token" value="<?= h($CSRF) ?>">

    <fieldset>
        <legend>ข้อมูลภาษาไทย (Thai Content)</legend>
        <label for="title">ชื่อผลงาน (Title TH):</label>
        <input type="text" name="title" id="title" required>

        <label for="issue">ปัญหา (Issue TH):</label>
        <textarea name="issue" id="issue" rows="3" required></textarea>

        <label for="fix_detail">รายละเอียดการซ่อม (Repair Details TH):</label>
        <textarea name="fix_detail" id="fix_detail" rows="5" required></textarea>
    </fieldset>

    <hr style="margin: 20px 0;">

    <fieldset>
        <legend>ข้อมูลภาษาอังกฤษ (English Content)</legend>
        <label for="title_en">ชื่อผลงาน (Title EN):</label>
        <input type="text" name="title_en" id="title_en"> <label for="issue_en">ปัญหา (Issue EN):</label>
        <textarea name="issue_en" id="issue_en" rows="3"></textarea>

        <label for="fix_detail_en">รายละเอียดการซ่อม (Repair Details EN):</label>
        <textarea name="fix_detail_en" id="fix_detail_en" rows="5"></textarea>
    </fieldset>
    
    <hr style="margin: 20px 0;">

    <fieldset>
        <legend>ข้อมูลทั่วไป (General Information)</legend>
        <label for="model">รุ่น (Model):</label>
        <input type="text" name="model" id="model" required>

        <label for="category">หมวดหมู่ (Category):</label>
        <input type="text" name="category" id="category"> 
        
        <label for="main_image_drop_zone">อัปโหลดรูปภาพหลัก (Main Image):</label>
        <p class="form-helper-text">อนุญาต .jpg, .png, .webp (สูงสุด <?= $max_main_mb ?>MB)</p>
        <input type="file" name="image" id="main_image_file" accept="image/*">
        <div class="drop-zone" id="main_image_drop_zone">
            <span class="material-symbols-rounded" style="font-size:32px">upload_file</span>
            <div>ลากไฟล์มาใส่ หรือ คลิกเพื่อเลือก</div>
        </div>
        <div class="image-preview" id="main_image_preview">
            </div>
        </fieldset>

    <div style="margin-top: 20px;">
      <button type="submit" class="btn-primary">บันทึกข้อมูล (Save Data)</button>
      <a href="index.php" class="btn-secondary" style="margin-left: 10px;">← ย้อนกลับ (Back)</a>
    </div>
  </form>
</main>

<div id="loadingOverlay" class="loading-overlay">
  <div class="loading-spinner"></div>
  <p>กำลังบันทึกข้อมูล...</p>
</div>

<div id="alertOverlay" class="alert-overlay">
  <div class="alert-dialog">
    <span class="alert-icon material-symbols-rounded">warning</span>
    <p id="alertMessage">เกิดข้อผิดพลาด</p>
    <div class="alert-actions">
      <button type="button" id="alertBtnOk" class="cmodal-btn-primary">ตกลง</button>
    </div>
  </div>
</div>

<div id="confirmOverlay" class="confirm-overlay">
  <div class="confirm-dialog">
    <p id="confirmMessage">คุณต้องการลบรูปนี้ใช่หรือไม่?</p>
    <div class="confirm-actions">
      <button type="button" id="confirmBtnCancel" class="cmodal-btn-cancel">ยกเลิก</button>
      <button type="button" id="confirmBtnOk" class="cmodal-btn-confirm">ยืนยัน</button>
    </div>
  </div>
</div>
<?php include '../../templates/footer_admin.php'; ?>

<script>
  document.addEventListener('DOMContentLoaded', () => {

    // --- [กูแก้] Logic "กำลังบันทึก" ---
    const mainForm = document.querySelector('.form-section');
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (mainForm && loadingOverlay) {
        mainForm.addEventListener('submit', () => {
            loadingOverlay.classList.add('show');
        });
    }

    // --- [กูเพิ่ม!!] Logic "แจ้งเตือน" กลางจอ ---
    const alertOverlay = document.getElementById('alertOverlay');
    const alertMsgEl = document.getElementById('alertMessage');
    const alertOkBtn = document.getElementById('alertBtnOk');

    function showCustomAlert(message) {
        if (!alertOverlay || !alertMsgEl) {
            alert(message); // Fallback
            return;
        }
        alertMsgEl.textContent = message;
        alertOverlay.classList.add('show');
    }
    alertOkBtn.addEventListener('click', () => {
        alertOverlay.classList.remove('show');
    });
    // --- [จบ Logic แจ้งเตือน] ---

    // --- [กูแก้!!] สร้าง "เครื่องเช็คไฟล์" ---
    const MAX_FILE_SIZE_MAIN = <?= MAX_MAIN_SIZE ?>;
    const ALLOWED_MIME_TYPES = <?= json_encode($ALLOWED_MIME) ?>;
    const ALLOWED_EXTS = <?= json_encode($ALLOWED_EXT) ?>;
    const MAX_MB_MAIN = <?= $max_main_mb ?>;

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
    // --- [จบ "เครื่องเช็คไฟล์"] ---


    // [กูแก้!!] ผ่าตัด "รูปหลัก" (นี่คือไฟล์เดียวที่มึงมี)
    (function setupMainImageDropZone() {
        const dropZoneEl = document.getElementById('main_image_drop_zone');
        const fileInputEl = document.getElementById('main_image_file');
        const previewEl = document.getElementById('main_image_preview');
        if (!dropZoneEl || !fileInputEl || !previewEl) return;
        
        // (หน้า Add ไม่มีพรีวิวการ์ด... เลยลบทิ้ง)

        function handleMainPreview(file) {
            previewEl.innerHTML = ''; // ล้างของเก่า
            
            if (!file) {
                // (หน้า Add ไม่มีรูปเก่า... เลยลบทิ้ง)
                return;
            }
            
            const reader = new FileReader();
            reader.onload = e => { 
                const url = e.target.result;
                // พรีวิวในฟอร์ม
                const item = document.createElement('div');
                item.className = 'image-preview-item js-preview-item';
                const img = document.createElement('img');
                img.src = url;
                img.alt = file.name;
                item.appendChild(img);
                previewEl.appendChild(item);
            };
            
            // [กูแก้!!] เพิ่มตัวดักไฟล์ "ไส้เน่า" (HEIC)
            reader.onerror = () => {
                showCustomAlert(`ไฟล์ "${file.name}" เสียหายหรืออ่านไม่ได้ (อาจเป็น HEIC ที่ถูกเปลี่ยนชื่อ)`);
                fileInputEl.value = null; // [CRITICAL] Clear the input
                handleMainPreview(null); // Clear the preview
            };
            
            reader.readAsDataURL(file);
        }
        
        function createFileList(file){ if(!file) return null; const dt=new DataTransfer(); dt.items.add(file); return dt.files; }

        dropZoneEl.addEventListener('click', () => fileInputEl.click());
        dropZoneEl.addEventListener('dragover', e => { e.preventDefault(); galleryDropZone.classList.add('is-dragover'); });
        ['dragleave','dragend'].forEach(t => dropZoneEl.addEventListener(t, () => galleryDropZone.classList.remove('is-dragover')));
        
        // [กูแก้] Event Drop รูปหลัก
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
        
        // [กูแก้] Event Change รูปหลัก
        fileInputEl.addEventListener('change', function() {
            if (!this.files.length) {
                handleMainPreview(null);
                return;
            }
            
            const file = this.files[0];
            const error = validateFile(file, MAX_FILE_SIZE_MAIN, MAX_MB_MAIN, ALLOWED_MIME_TYPES, ALLOWED_EXTS);

            if (error) {
                showCustomAlert(error);
                this.value = null; // ล้างไฟล์ที่เลือก
                handleMainPreview(null);
                return;
            }

            handleMainPreview(file);
        });
    })();


    // [กูแก้] รื้อ JS "Live Preview" (การ์ดขวา) ทิ้ง...
    // เพราะหน้า Add ของมึง... ไม่มี "การ์ดพรีวิว" (ดีแล้ว... รก)

  }); // End DOMContentLoaded
</script>