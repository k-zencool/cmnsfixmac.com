<?php
/*
 * add.php (ARTICLES - FINAL COMPLETE V10)
 * - [CORE] AJAX Submission (No page reload on error).
 * - [UI] Identical layout to edit.php (Grid buttons, Boxed Gallery).
 * - [CSS] Trix Editor fixes included (Bold/Italic/Lists).
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// Helper Function
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ================================================================
// 1) AJAX HANDLER (POST ONLY)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); 

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'message' => 'Token ไม่ถูกต้อง (กรุณารีเฟรชหน้าจอ)']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Variables
        $title = $_POST['title'] ?? '';
        $slug = trim($_POST['slug'] ?? '');
        $content = $_POST['content'] ?? '';
        $excerpt = $_POST['excerpt'] ?? '';
        $title_en = $_POST['title_en'] ?? '';
        $slug_en = trim($_POST['slug_en'] ?? '');
        $content_en = $_POST['content_en'] ?? '';
        $excerpt_en = $_POST['excerpt_en'] ?? '';
        $category = $_POST['category'] ?? 'tip';
        $youtube_url = $_POST['youtube_url'] ?? '';
        $status = isset($_POST['status']) ? 1 : 0;

        // Config
        $MAX_MAIN = 5 * 1024 * 1024;
        $MAX_GALLERY = 5 * 1024 * 1024;
        $ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
        $upload_path = __DIR__ . '/../../uploads/articles';
        $upload_url  = '/uploads/articles';
        if (!is_dir($upload_path)) @mkdir($upload_path, 0775, true);

        function validate_img_server($f, $max, $mime) {
            return ($f['error'] === UPLOAD_ERR_OK && $f['size'] <= $max && in_array((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']), $mime));
        }
        function clean_name_server($n) { return preg_replace('/[^a-zA-Z0-9\._-]/', '_', $n); }

        // 2. Handle Main Image
        $mainImageDb = '';
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if($_FILES['main_image']['error'] !== UPLOAD_ERR_OK) throw new Exception('รูปหลักมีปัญหาการอัปโหลด');
            if (!validate_img_server($_FILES['main_image'], $MAX_MAIN, $ALLOWED_MIME)) throw new Exception('รูปหลักไม่ถูกต้อง (ใหญ่เกิน 5MB หรือผิดประเภท)');
            
            $ext = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
            $fname = time() . '_' . clean_name_server($_FILES['main_image']['name']);
            if(!move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_path . '/' . $fname)) throw new Exception('ย้ายไฟล์รูปหลักไม่สำเร็จ');
            $mainImageDb = $upload_url . '/' . $fname;
        }

        // 3. Insert Article
        $sql = "INSERT INTO articles (admin_id, title, slug, category, content, excerpt, youtube_url, image, status, created_at, title_en, slug_en, content_en, excerpt_en) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['admin_id'], $title, $slug, $category, $content, $excerpt, $youtube_url, $mainImageDb, $status, 
            $title_en, $slug_en, $content_en, $excerpt_en
        ]);
        $newId = $pdo->lastInsertId();

        // 4. Handle Gallery (New)
        if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
            $ins = $pdo->prepare("INSERT INTO article_images (article_id, image_path, caption, caption_en) VALUES (?,?,?,?)");
            
            foreach ($_FILES['additional_images']['tmp_name'] as $i => $tmp) {
                $err = $_FILES['additional_images']['error'][$i];
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                
                // Check Server Error
                if ($err !== UPLOAD_ERR_OK) {
                    $msg = "Error Code $err";
                    if($err == 1) $msg = "ไฟล์ใหญ่เกินค่า Server Limit (php.ini)";
                    $pdo->rollBack(); // Cancel everything
                    echo json_encode([
                        'status' => 'error', 
                        'message' => "รูปรายการที่ ".($i+1)." มีปัญหา: $msg",
                        'bad_input_index' => $i
                    ]);
                    exit;
                }

                $chk = ['tmp_name' => $tmp, 'error' => 0, 'size' => $_FILES['additional_images']['size'][$i]];
                if (!validate_img_server($chk, $MAX_GALLERY, $ALLOWED_MIME)) {
                    $pdo->rollBack();
                    echo json_encode([
                        'status' => 'error', 
                        'message' => "รูปรายการที่ ".($i+1)." ไม่ผ่านการตรวจสอบ (ขนาด/ประเภท)",
                        'bad_input_index' => $i
                    ]);
                    exit;
                }

                $ext = strtolower(pathinfo($_FILES['additional_images']['name'][$i], PATHINFO_EXTENSION));
                $fname = time() . '-' . uniqid() . '.' . $ext;
                if(move_uploaded_file($tmp, $upload_path . '/' . $fname)){
                    $ins->execute([$newId, $upload_url . '/' . $fname, $_POST['caption_detail'][$i]??'', $_POST['caption_detail_en'][$i]??'']);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'redirect' => "index.php"]); // Success -> Go to Index

    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ================================================================
// 2) VIEW RENDERING
// ================================================================
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<style>
  /* --- DRAG & DROP & PREVIEW --- */
  .drop-zone { border: 2px dashed #ccc; border-radius: 8px; padding: 25px; text-align: center; color: #777; cursor: pointer; transition: all .2s; background: #fff; }
  .drop-zone.is-dragover { border-color: var(--primary, #007aff); background: var(--primary-ghost, #f4f8ff); }
  .drop-zone input[type=file] { display: none; }
  
  .image-preview { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; min-height: 20px; }
  .image-preview-item { position: relative; border: 1px solid #ddd; border-radius: 5px; padding: 5px; background: #f9f9f9; }
  .image-preview-item img { max-width: 100px; max-height: 100px; object-fit: cover; border-radius: 4px; display: block; }
  .js-delete-preview { position: absolute; top: -8px; right: -8px; width: 22px; height: 22px; background: #c00; color: white; border: 2px solid white; border-radius: 50%; font-size: 14px; font-weight: bold; line-height: 18px; text-align: center; cursor: pointer; padding: 0; z-index: 2; }

  /* --- GALLERY ITEMS --- */
  .gallery-item-box { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #fff; position: relative; }
  .gallery-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  .gallery-title { font-weight: bold; font-size: 1.1em; color: #333; }
  
  .btn-remove-text { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; padding: 4px 12px; border-radius: 4px; font-size: 0.85rem; cursor: pointer; transition: background 0.2s; }
  .btn-remove-text:hover { background: #ef9a9a; color: #fff; border-color: #ef9a9a; }

  /* --- PUBLISH BOX --- */
  .publish-box { margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 8px; border: 1px solid #eee; }

  /* --- BUTTONS ACTION ROW (FIXED HEIGHT 50PX) --- */
  .actions-row { 
      margin-top: 20px; 
      display: grid; 
      grid-template-columns: 1fr 1fr; 
      gap: 20px; 
  }
  .actions-row button, .actions-row a {
      width: 100%;
      height: 50px; /* Force Height */
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 1rem;
      font-weight: 600;
      text-decoration: none;
      font-family: inherit;
      line-height: normal;
      margin: 0 !important;
      padding: 0 !important;
      box-sizing: border-box !important;
      border-radius: 6px;
      cursor: pointer;
      appearance: none;
      outline: none;
      border: none;
  }
  .btn-save { background: #4169e1; color: white; transition: background 0.2s; } 
  .btn-save:hover { background: #3154b3; }
  .btn-cancel { background: #e0e0e0; color: #333; border: 1px solid #ccc !important; transition: background 0.2s; }
  .btn-cancel:hover { background: #d0d0d0; }

  /* --- TRIX EDITOR FIXES (LISTS & BOLD) --- */
  trix-editor {
      min-height: 300px !important;
      border: 1px solid #ddd !important;
      border-radius: 8px !important;
      padding: 20px !important;
      background: #fff;
      font-size: 16px;
      line-height: 1.6;
      overflow-y: auto;
  }
  trix-editor ul, trix-editor ol, .trix-content ul, .trix-content ol {
      padding-left: 30px !important; 
      margin-bottom: 15px !important;
      list-style-position: outside !important;
  }
  trix-editor li, .trix-content li { margin-bottom: 5px; }
  trix-editor strong, .trix-content strong { font-weight: bold !important; color: #000; }
  trix-editor em, .trix-content em { font-style: italic !important; }
  trix-editor u, .trix-content u { text-decoration: underline !important; }
  trix-editor h1, .trix-content h1 { font-size: 1.5em !important; font-weight: bold !important; margin: 15px 0 10px 0 !important; }
  trix-editor a, .trix-content a { color: #007aff !important; text-decoration: underline !important; }

  /* --- OVERLAYS --- */
  .loading-overlay, .alert-overlay, .confirm-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; flex-direction: column; align-items: center; justify-content: center; }
  .loading-overlay.show, .alert-overlay.show, .confirm-overlay.show { display: flex; }
  .loading-spinner { border: 4px solid #f3f3f3; border-top: 4px solid var(--primary, #007aff); border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin-bottom: 20px; }
  @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
  .alert-dialog, .confirm-dialog { background: #fff; padding: 25px 30px; border-radius: 16px; width: 90%; max-width: 400px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
  .cmodal-btn-primary { background: #007aff; color: white; padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; margin-top:15px; font-weight:600; }
  .cmodal-btn-confirm { background: #c00; color: white; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; margin: 0 5px; font-weight:600;}
  .cmodal-btn-cancel { background: #f0f0f0; color: #333; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; margin: 0 5px; font-weight:600;}
</style>

<main class="main">
  <div class="topbar"><span>เพิ่มบทความใหม่ (Add New)</span><a href="index.php" class="view-site">← กลับ</a></div>
  <div class="form-section">
    
    <form id="addArticleForm"> <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
      
      <fieldset>
        <legend>Thai</legend>
        <label>Title:</label><input type="text" name="title" required>
        <label>Slug:</label><input type="text" name="slug" required>
        <label>Content:</label><input id="content" type="hidden" name="content"><trix-editor input="content"></trix-editor>
        <label>Excerpt:</label><textarea name="excerpt" rows="3"></textarea>
      </fieldset>

      <hr style="margin: 20px 0;">
      <fieldset>
        <legend>English</legend>
        <label>Title (EN):</label><input type="text" name="title_en">
        <label>Slug (EN):</label><input type="text" name="slug_en">
        <label>Content (EN):</label><input id="content_en" type="hidden" name="content_en"><trix-editor input="content_en"></trix-editor>
        <label>Excerpt (EN):</label><textarea name="excerpt_en" rows="3"></textarea>
      </fieldset>

      <hr style="margin: 20px 0;">
      <fieldset>
        <legend>Images & Settings</legend>
        <label>Category:</label>
        <select name="category">
          <option value="tip">Tips</option>
          <option value="repair">Repair</option>
          <option value="update">Update</option>
        </select>
        <label>YouTube ID:</label><input type="text" name="youtube_url">

        <label style="margin-top:20px; font-weight:bold;">Main Image:</label>
        <input type="file" id="main_image" name="main_image" accept="image/*" style="display:none">
        <div class="drop-zone" id="main_drop"><span class="material-symbols-rounded" style="font-size:30px">image</span><br>คลิกเพื่อเลือกรูปหลัก (หรือลากมาวาง)</div>
        <div class="image-preview" id="main_preview"></div>

        <label style="margin-top:30px; font-weight:bold; display:block;">Gallery (รูปประกอบ):</label>
        <p style="font-size:0.85rem; color:#666; margin-bottom:15px;">ใส่ได้สูงสุด 5 รูป (5MB/รูป)</p>
        
        <div id="gallery_wrapper">
            </div>
        <button type="button" id="add_gallery_btn" class="btn-secondary" style="width:100%; padding:10px; background:#f0f0f0; border:1px solid #ccc; margin-top:10px; border-radius:6px; cursor:pointer;">+ เพิ่มรูปประกอบ</button>
      </fieldset>

      <div class="publish-box">
          <label class="publish-label" style="display:flex; gap:10px; cursor:pointer;">
              <input type="checkbox" name="status" id="status" checked style="width:20px; height:20px;"> 
              <span>เผยแพร่บทความ (Publish)</span>
          </label>
      </div>

      <div class="actions-row">
          <button type="submit" class="btn-save">บันทึกข้อมูล (Save)</button>
          <a href="index.php" class="btn-cancel">ยกเลิก</a>
      </div>
    </form>
  </div>
</main>

<div id="loadingOverlay" class="loading-overlay"><div class="loading-spinner"></div><p style="color:white">กำลังบันทึก...</p></div>
<div id="alertOverlay" class="alert-overlay"><div class="alert-dialog"><span class="material-symbols-rounded" style="font-size:48px;color:#f59e0b;">warning</span><p id="alertMessage"></p><button id="alertBtn" class="cmodal-btn-primary">OK</button></div></div>
<div id="confirmOverlay" class="confirm-overlay"><div class="confirm-dialog"><p id="confirmMessage"></p><button id="confirmCancel" class="cmodal-btn-cancel">Cancel</button><button id="confirmOk" class="cmodal-btn-confirm">OK</button></div></div>

<?php include '../../templates/footer_admin.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const MAX_BYTES = 5242880; // 5MB
    const MAX_TOTAL = 5;
    const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    const ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'webp'];

    const alertOv = document.getElementById('alertOverlay');
    const alertMsg = document.getElementById('alertMessage');
    document.getElementById('alertBtn').onclick = () => alertOv.classList.remove('show');
    function myAlert(msg) { alertMsg.textContent = msg; alertOv.classList.add('show'); }

    const confOv = document.getElementById('confirmOverlay');
    const confMsg = document.getElementById('confirmMessage');
    let confCb = null;
    document.getElementById('confirmCancel').onclick = () => { confOv.classList.remove('show'); confCb=null; };
    document.getElementById('confirmOk').onclick = () => { if(confCb) confCb(); confOv.classList.remove('show'); };
    function myConfirm(msg, cb) { confMsg.textContent = msg; confCb = cb; confOv.classList.add('show'); }

    function validateFile(file) {
        if(!file) return null;
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if(file.size > MAX_BYTES) return `ไฟล์ใหญ่เกิน! (${(file.size/1024/1024).toFixed(2)}MB)`;
        if(!ALLOWED_EXTS.includes(ext)) return `นามสกุล .${ext} ไม่รองรับ!`;
        return null;
    }

    function createPreview(file, container, inputEl) {
        container.innerHTML = '';
        if(!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.className = 'image-preview-item';
            div.innerHTML = `<img src="${e.target.result}"><button type="button" class="js-delete-preview" onclick="this.closest('.image-preview-item').remove(); document.getElementById('${inputEl.id}').value='';">&times;</button>`;
            div.querySelector('.js-delete-preview').onclick = () => { inputEl.value = ''; container.innerHTML = ''; };
            container.appendChild(div);
        };
        reader.readAsDataURL(file);
    }

    // AJAX SUBMIT
    const form = document.getElementById('addArticleForm');
    const loader = document.getElementById('loadingOverlay');
    
    form.onsubmit = async (e) => {
        e.preventDefault();
        loader.classList.add('show');
        const formData = new FormData(form);
        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            const data = await res.json();
            loader.classList.remove('show');
            if (data.status === 'success') window.location.href = data.redirect;
            else {
                myAlert(data.message || 'เกิดข้อผิดพลาด');
                if(data.bad_input_index !== undefined) {
                    const inputs = document.getElementsByName('additional_images[]');
                    if(inputs[data.bad_input_index]) {
                        inputs[data.bad_input_index].value = '';
                        const preview = inputs[data.bad_input_index].nextElementSibling.nextElementSibling;
                        if(preview) preview.innerHTML = '';
                    }
                }
            }
        } catch (err) {
            loader.classList.remove('show');
            myAlert('การเชื่อมต่อขัดข้อง: ' + err.message);
        }
    };

    // Main Image Logic
    const mDrop = document.getElementById('main_drop');
    const mInput = document.getElementById('main_image');
    mDrop.onclick = () => mInput.click();
    mDrop.ondragover = e => { e.preventDefault(); mDrop.classList.add('is-dragover'); };
    mDrop.ondragleave = () => mDrop.classList.remove('is-dragover');
    mDrop.ondrop = e => {
        e.preventDefault(); mDrop.classList.remove('is-dragover');
        if(e.dataTransfer.files.length) {
            const f = e.dataTransfer.files[0];
            const err = validateFile(f);
            if(err) { mInput.value=''; document.getElementById('main_preview').innerHTML=''; myAlert(err); return; }
            mInput.files = e.dataTransfer.files;
            createPreview(f, document.getElementById('main_preview'), mInput);
        }
    };
    mInput.onchange = () => {
        if(mInput.files.length) {
            const err = validateFile(mInput.files[0]);
            if(err) { mInput.value=''; document.getElementById('main_preview').innerHTML=''; myAlert(err); return; }
            createPreview(mInput.files[0], document.getElementById('main_preview'), mInput);
        }
    };

    // Gallery Logic
    let gIdx = 0;
    const wrapper = document.getElementById('gallery_wrapper');
    
    function updateIndexes() {
        document.querySelectorAll('.gallery-item-box').forEach((box, idx) => {
            const title = box.querySelector('.gallery-title');
            if(title) title.textContent = `รูปที่ ${idx + 1}`;
        });
    }

    document.getElementById('add_gallery_btn').onclick = () => {
        if(document.querySelectorAll('.gallery-item-box').length >= MAX_TOTAL) return myAlert(`เพิ่มได้สูงสุด ${MAX_TOTAL} รูปครับ`);
        
        const div = document.createElement('div');
        div.className = 'gallery-item-box';
        const inputId = `g_input_${gIdx}`;
        
        div.innerHTML = `
            <div class="gallery-header">
                <span class="gallery-title">รูปที่ ...</span>
                <button type="button" class="btn-remove-text rem-btn">ลบรายการนี้</button>
            </div>
            <input type="file" name="additional_images[]" id="${inputId}" class="gallery-input" accept="image/*" style="display:none">
            <div class="drop-zone gallery-drop-zone"><span class="material-symbols-rounded">cloud_upload</span><br>เลือกรูปภาพ</div>
            <div class="image-preview gallery-preview"></div>
            <div style="display:grid; gap:10px; grid-template-columns: 1fr 1fr; margin-top:15px;">
                <div><label>คำอธิบาย (TH):</label><input type="hidden" id="trix_th_${gIdx}" name="caption_detail[]"><trix-editor input="trix_th_${gIdx}" style="min-height:80px;"></trix-editor></div>
                <div><label>Caption (EN):</label><input type="hidden" id="trix_en_${gIdx}" name="caption_detail_en[]"><trix-editor input="trix_en_${gIdx}" style="min-height:80px;"></trix-editor></div>
            </div>
        `;
        wrapper.appendChild(div);
        updateIndexes();

        const drop = div.querySelector('.gallery-drop-zone');
        const input = div.querySelector('.gallery-input');
        const preview = div.querySelector('.gallery-preview');
        const rem = div.querySelector('.rem-btn');

        rem.onclick = () => myConfirm("ลบช่องนี้?", () => { div.remove(); updateIndexes(); });
        drop.onclick = () => input.click();
        drop.ondragover = e => { e.preventDefault(); drop.classList.add('is-dragover'); };
        drop.ondragleave = () => drop.classList.remove('is-dragover');
        drop.ondrop = e => {
            e.preventDefault(); drop.classList.remove('is-dragover');
            if(e.dataTransfer.files.length) {
                const f = e.dataTransfer.files[0];
                const err = validateFile(f);
                if(err) { input.value=''; preview.innerHTML=''; myAlert(err); return; }
                input.files = e.dataTransfer.files;
                createPreview(f, preview, input);
            }
        };
        input.onchange = () => {
            if(input.files.length) {
                const err = validateFile(input.files[0]);
                if(err) { input.value=''; preview.innerHTML=''; myAlert(err); return; }
                createPreview(input.files[0], preview, input);
            }
        };
        gIdx++;
    };
});
</script>