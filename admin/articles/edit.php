<?php
/*
 * edit.php (ARTICLES - FINAL COMPLETE V10)
 * - [PHP] Full Gallery Management (Add, Delete, Replace).
 * - [UI] Fixed Button Heights & Centering.
 * - [CSS] Fixed Trix Editor styles (Bold/Italic/Lists).
 */

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// Helper
function h($s)
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// ================================================================
// 1) AJAX HANDLER
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        echo json_encode(['status' => 'error', 'message' => 'Token ไม่ถูกต้อง (CSRF Mismatch)']);
        exit;
    }

    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) throw new Exception('Missing ID');

        $pdo->beginTransaction();

        // 1. Update Text Data
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

        $MAX_MAIN = 5 * 1024 * 1024; // 5MB
        $ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
        $upload_path = __DIR__ . '/../../uploads/articles';
        $upload_url  = '/uploads/articles';
        if (!is_dir($upload_path)) @mkdir($upload_path, 0775, true);

        function validate_img_server($f, $max, $mime)
        {
            return ($f['error'] === UPLOAD_ERR_OK && $f['size'] <= $max && in_array((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']), $mime));
        }
        function clean_name_server($n)
        {
            return preg_replace('/[^a-zA-Z0-9\._-]/', '_', $n);
        }

        // 2. Handle Main Image
        $stmt = $pdo->prepare("SELECT image FROM articles WHERE id = ?");
        $stmt->execute([$id]);
        $currImg = $stmt->fetchColumn();

        $newMainImage = $currImg;
        // Case: Delete requested
        if (isset($_POST['delete_main_image']) && $_POST['delete_main_image'] == '1') {
            if ($currImg && file_exists(__DIR__ . '/../../' . $currImg)) @unlink(__DIR__ . '/../../' . $currImg);
            $newMainImage = null;
        }
        // Case: New upload
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if (!validate_img_server($_FILES['main_image'], $MAX_MAIN, $ALLOWED_MIME)) throw new Exception('รูปหลักไม่ถูกต้อง (ใหญ่เกิน 5MB หรือผิดประเภท)');
            if ($currImg && file_exists(__DIR__ . '/../../' . $currImg)) @unlink(__DIR__ . '/../../' . $currImg); // Remove old
            $fname = time() . '_main_' . clean_name_server($_FILES['main_image']['name']);
            if (!move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_path . '/' . $fname)) throw new Exception('ย้ายไฟล์รูปหลักไม่สำเร็จ');
            $newMainImage = $upload_url . '/' . $fname;
        }

        $sql = "UPDATE articles SET title=?, slug=?, category=?, content=?, excerpt=?, youtube_url=?, image=?, status=?, title_en=?, slug_en=?, content_en=?, excerpt_en=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $slug, $category, $content, $excerpt, $youtube_url, $newMainImage, $status, $title_en, $slug_en, $content_en, $excerpt_en, $id]);

        // 3. Handle Gallery - DELETE
        $deleted_ids = [];
        if (isset($_POST['delete_gallery_ids'])) {
            $del = $pdo->prepare("DELETE FROM article_images WHERE id=? AND article_id=?");
            $sel = $pdo->prepare("SELECT image_path FROM article_images WHERE id=?");
            foreach ($_POST['delete_gallery_ids'] as $did) {
                $sel->execute([$did]);
                $p = $sel->fetchColumn();
                if ($p && file_exists(__DIR__ . '/../../' . $p)) @unlink(__DIR__ . '/../../' . $p);
                $del->execute([$did, $id]);
                $deleted_ids[] = $did;
            }
        }

        // 4. Handle Gallery - UPDATE CAPTIONS
        if (isset($_POST['existing_captions_th'])) {
            $upd = $pdo->prepare("UPDATE article_images SET caption=?, caption_en=? WHERE id=? AND article_id=?");
            foreach ($_POST['existing_captions_th'] as $gid => $val) {
                if (in_array($gid, $deleted_ids)) continue;
                $upd->execute([$val, $_POST['existing_captions_en'][$gid] ?? '', $gid, $id]);
            }
        }

        // 5. Handle Gallery - REPLACE IMAGE
        if (isset($_FILES['replace_gallery_image'])) {
            $selOne = $pdo->prepare("SELECT image_path FROM article_images WHERE id=? AND article_id=?");
            $updImg = $pdo->prepare("UPDATE article_images SET image_path=? WHERE id=?");

            foreach ($_FILES['replace_gallery_image']['name'] as $gid => $fName) {
                if (in_array($gid, $deleted_ids)) continue;

                $err = $_FILES['replace_gallery_image']['error'][$gid];
                if ($err === UPLOAD_ERR_NO_FILE) continue;

                if ($err !== UPLOAD_ERR_OK) throw new Exception("Error uploading replacement for ID $gid");

                $tmpName = $_FILES['replace_gallery_image']['tmp_name'][$gid];
                $fSize = $_FILES['replace_gallery_image']['size'][$gid];
                $chk = ['tmp_name' => $tmpName, 'error' => 0, 'size' => $fSize];
                if (!validate_img_server($chk, $MAX_MAIN, $ALLOWED_MIME)) throw new Exception("รูปใหม่สำหรับ ID $gid ไม่ถูกต้อง");

                // Get Old Image to delete
                $selOne->execute([$gid, $id]);
                $oldPath = $selOne->fetchColumn();
                if ($oldPath && file_exists(__DIR__ . '/../../' . $oldPath)) @unlink(__DIR__ . '/../../' . $oldPath);

                // Upload New
                $ext = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                $newFname = time() . "-rep-" . uniqid() . '.' . $ext;
                if (move_uploaded_file($tmpName, $upload_path . '/' . $newFname)) {
                    $updImg->execute([$upload_url . '/' . $newFname, $gid]);
                }
            }
        }

        // 6. Handle Gallery - INSERT NEW
        if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
            $ins = $pdo->prepare("INSERT INTO article_images (article_id, image_path, caption, caption_en) VALUES (?,?,?,?)");
            foreach ($_FILES['additional_images']['tmp_name'] as $i => $tmp) {
                $err = $_FILES['additional_images']['error'][$i];
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                if ($err !== UPLOAD_ERR_OK) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'error', 'message' => "รูปใหม่รายการที่ " . ($i + 1) . " มีปัญหา", 'bad_input_index' => $i]);
                    exit;
                }
                $chk = ['tmp_name' => $tmp, 'error' => 0, 'size' => $_FILES['additional_images']['size'][$i]];
                if (!validate_img_server($chk, $MAX_MAIN, $ALLOWED_MIME)) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'error', 'message' => "รูปใหม่รายการที่ " . ($i + 1) . " ผิดประเภท/ใหญ่เกิน", 'bad_input_index' => $i]);
                    exit;
                }
                $ext = strtolower(pathinfo($_FILES['additional_images']['name'][$i], PATHINFO_EXTENSION));
                $fname = time() . '-' . uniqid() . '.' . $ext;
                if (move_uploaded_file($tmp, $upload_path . '/' . $fname)) {
                    $ins->execute([$id, $upload_url . '/' . $fname, $_POST['caption_detail'][$i] ?? '', $_POST['caption_detail_en'][$i] ?? '']);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'redirect' => "edit.php?id=$id&updated=1"]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ================================================================
// 2) VIEW RENDERING
// ================================================================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die('Error: Missing ID');

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$article) die('Error: Article not found');

$stmtImg = $pdo->prepare("SELECT * FROM article_images WHERE article_id = ? ORDER BY id ASC");
$stmtImg->execute([$id]);
$existing_images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$title = $article['title'];
$slug = $article['slug'];
$content = $article['content'];
$excerpt = $article['excerpt'];
$category = $article['category'];
$youtube_url = $article['youtube_url'];
$current_main_image = $article['image'];
$status = $article['status'];
$title_en = $article['title_en'];
$slug_en = $article['slug_en'];
$content_en = $article['content_en'];
$excerpt_en = $article['excerpt_en'];

$success = isset($_GET['updated']) ? 'บันทึกข้อมูลเรียบร้อย!' : '';

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>


<style>
    /* --- DRAG & DROP & PREVIEW --- */
    .drop-zone {
        border: 2px dashed #ccc;
        border-radius: 8px;
        padding: 25px;
        text-align: center;
        color: #777;
        cursor: pointer;
        transition: all .2s;
        background: #fff;
    }

    .drop-zone.is-dragover {
        border-color: var(--primary, #007aff);
        background: var(--primary-ghost, #f4f8ff);
    }

    .drop-zone input[type=file] {
        display: none;
    }

    .image-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 15px;
        min-height: 20px;
    }

    .image-preview-item {
        position: relative;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 5px;
        background: #f9f9f9;
    }

    .image-preview-item img {
        max-width: 100px;
        max-height: 100px;
        object-fit: cover;
        border-radius: 4px;
        display: block;
    }

    .js-delete-preview {
        position: absolute;
        top: -8px;
        right: -8px;
        width: 22px;
        height: 22px;
        background: #c00;
        color: white;
        border: 2px solid white;
        border-radius: 50%;
        font-size: 14px;
        font-weight: bold;
        line-height: 18px;
        text-align: center;
        cursor: pointer;
        padding: 0;
        z-index: 2;
    }

    /* --- GALLERY ITEMS --- */
    .gallery-item-box {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        background: #fff;
        position: relative;
    }

    .gallery-item-box.is-deleted {
        opacity: 0.5;
        background: #ffebee;
        border-color: #ffcdd2;
    }

    .gallery-item-box.is-deleted::after {
        content: "เตรียมลบรายการนี้";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #c00;
        font-weight: bold;
        font-size: 1.2rem;
        background: rgba(255, 255, 255, 0.8);
        padding: 5px 10px;
        border-radius: 4px;
    }

    .gallery-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .gallery-title {
        font-weight: bold;
        font-size: 1.1em;
        color: #333;
    }

    .btn-remove-text {
        background: #ffebee;
        color: #c62828;
        border: 1px solid #ffcdd2;
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-remove-text:hover {
        background: #ef9a9a;
        color: #fff;
        border-color: #ef9a9a;
    }

    .btn-replace-img {
        background: #e3f2fd;
        color: #1565c0;
        border: 1px solid #bbdefb;
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 0.85rem;
        cursor: pointer;
        display: inline-block;
        margin-top: 5px;
    }

    .btn-replace-img:hover {
        background: #bbdefb;
    }

    /* --- PUBLISH BOX --- */
    .publish-box {
        margin-top: 20px;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 8px;
        border: 1px solid #eee;
    }

    /* --- BUTTONS ACTION ROW (FIXED HEIGHT 50PX) --- */
    .actions-row {
        margin-top: 20px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .actions-row button,
    .actions-row a {
        width: 100%;
        height: 50px;
        /* Force Height */
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

    .btn-save {
        background: #4169e1;
        color: white;
        transition: background 0.2s;
    }

    .btn-save:hover {
        background: #3154b3;
    }

    .btn-cancel {
        background: #e0e0e0;
        color: #333;
        border: 1px solid #ccc !important;
        transition: background 0.2s;
    }

    .btn-cancel:hover {
        background: #d0d0d0;
    }

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

    /* Fix List Overflow */
    trix-editor ul,
    trix-editor ol,
    .trix-content ul,
    .trix-content ol {
        padding-left: 30px !important;
        margin-bottom: 15px !important;
        list-style-position: outside !important;
    }

    trix-editor li,
    .trix-content li {
        margin-bottom: 5px;
    }

    /* Fix Bold/Italic visibility */
    trix-editor strong,
    .trix-content strong {
        font-weight: bold !important;
        color: #000;
    }

    trix-editor em,
    .trix-content em {
        font-style: italic !important;
    }

    trix-editor u,
    .trix-content u {
        text-decoration: underline !important;
    }

    trix-editor h1,
    .trix-content h1 {
        font-size: 1.5em !important;
        font-weight: bold !important;
        margin: 15px 0 10px 0 !important;
    }

    trix-editor a,
    .trix-content a {
        color: #007aff !important;
        text-decoration: underline !important;
    }

    /* --- OVERLAYS --- */
    .loading-overlay,
    .alert-overlay,
    .confirm-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 9999;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .loading-overlay.show,
    .alert-overlay.show,
    .confirm-overlay.show {
        display: flex;
    }

    .loading-spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid var(--primary, #007aff);
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
        margin-bottom: 20px;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .alert-dialog,
    .confirm-dialog {
        background: #fff;
        padding: 25px 30px;
        border-radius: 16px;
        width: 90%;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .cmodal-btn-primary {
        background: #007aff;
        color: white;
        padding: 10px 20px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        margin-top: 15px;
        font-weight: 600;
    }

    .cmodal-btn-confirm {
        background: #c00;
        color: white;
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        margin: 0 5px;
        font-weight: 600;
    }

    .cmodal-btn-cancel {
        background: #f0f0f0;
        color: #333;
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        margin: 0 5px;
        font-weight: 600;
    }

    .msg-success {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        background: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #2e7d32;
    }
</style>


<main class="main">
    <div class="topbar"><span><?= h($title) ?> (Edit)</span><a href="index.php" class="view-site">← กลับ</a></div>
    <div class="form-section">
        <?php if ($success): ?><div class="msg msg-success"><?= h($success) ?></div><?php endif; ?>

        <form id="editArticleForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">

            <fieldset>
                <legend>Thai</legend>
                <label>Title:</label><input type="text" name="title" value="<?= h($title) ?>" required>
                <label>Slug:</label><input type="text" name="slug" value="<?= h($slug) ?>" required>
                <label>Content:</label><input id="content" type="hidden" name="content" value="<?= h($content) ?>"><trix-editor input="content"></trix-editor>
                <label>Excerpt:</label><textarea name="excerpt" rows="3"><?= h($excerpt) ?></textarea>
            </fieldset>
            <hr style="margin: 20px 0;">
            <fieldset>
                <legend>English</legend>
                <label>Title (EN):</label><input type="text" name="title_en" value="<?= h($title_en) ?>">
                <label>Slug (EN):</label><input type="text" name="slug_en" value="<?= h($slug_en) ?>">
                <label>Content (EN):</label><input id="content_en" type="hidden" name="content_en" value="<?= h($content_en) ?>"><trix-editor input="content_en"></trix-editor>
                <label>Excerpt (EN):</label><textarea name="excerpt_en" rows="3"><?= h($excerpt_en) ?></textarea>
            </fieldset>

            <hr style="margin: 20px 0;">
            <fieldset>
                <legend>Images & Settings</legend>
                <label>Category:</label>
                <select name="category">
                    <option value="tip" <?= $category == 'tip' ? 'selected' : '' ?>>Tips</option>
                    <option value="repair" <?= $category == 'repair' ? 'selected' : '' ?>>Repair</option>
                    <option value="update" <?= $category == 'update' ? 'selected' : '' ?>>Update</option>
                </select>
                <label>YouTube ID:</label><input type="text" name="youtube_url" value="<?= h($youtube_url) ?>">

                <label style="margin-top:20px; font-weight:bold;">Main Image:</label>
                <?php if ($current_main_image): ?>
                    <div style="padding:10px; background:#f5f5f5; display:flex; align-items:center; gap:10px; margin-bottom:10px; border-radius:4px;">
                        <img src="<?= h($current_main_image) ?>" style="height:60px; object-fit:cover;">
                        <label style="margin:0; cursor:pointer;"><input type="checkbox" name="delete_main_image" value="1" style="width:auto; height:auto;"> ลบรูปนี้</label>
                    </div>
                <?php endif; ?>
                <input type="file" id="main_image" name="main_image" accept="image/*" style="display:none">
                <div class="drop-zone" id="main_drop"><span class="material-symbols-rounded" style="font-size:30px">image</span><br>คลิกเพื่อเปลี่ยนรูปหลัก (หรือลากมาวาง)</div>
                <div class="image-preview" id="main_preview"></div>

                <label style="margin-top:30px; font-weight:bold; display:block;">Gallery (รูปประกอบ):</label>
                <p style="font-size:0.85rem; color:#666; margin-bottom:15px;">คุณสามารถแก้ไขคำอธิบาย ลบรูปเดิม หรือเปลี่ยนรูปใหม่ได้</p>

                <div id="gallery_wrapper">
                    <?php if (!empty($existing_images)): foreach ($existing_images as $index => $img): ?>
                            <div class="gallery-item-box" id="exist_box_<?= $img['id'] ?>">
                                <div class="gallery-header">
                                    <span class="gallery-title">รูปที่ <?= $index + 1 ?></span>
                                    <label class="btn-remove-text">
                                        <input type="checkbox" name="delete_gallery_ids[]" value="<?= $img['id'] ?>" style="display:none;"
                                            onchange="toggleExist(this, 'exist_box_<?= $img['id'] ?>')">
                                        <span class="txt">ลบรูปนี้</span>
                                    </label>
                                </div>

                                <div style="display:flex; gap:15px; margin-bottom:15px; align-items: flex-start;">
                                    <div style="text-align:center; width: 120px;">
                                        <img id="view_img_<?= $img['id'] ?>" src="<?= h($img['image_path']) ?>" style="width:100px; height:100px; object-fit:cover; border:1px solid #ddd; border-radius:4px; display:block; margin: 0 auto;">

                                        <input type="file" id="rep_file_<?= $img['id'] ?>" name="replace_gallery_image[<?= $img['id'] ?>]" accept="image/*" style="display:none;" onchange="previewReplace(this, <?= $img['id'] ?>)">
                                        <button type="button" class="btn-replace-img" onclick="document.getElementById('rep_file_<?= $img['id'] ?>').click()">เปลี่ยนรูป</button>
                                    </div>

                                    <div style="flex:1;">
                                        <div id="prev_rep_<?= $img['id'] ?>" style="margin-bottom:5px; color:#007aff; font-size:0.9rem; font-weight:bold; display:none;">
                                            * กำลังจะเปลี่ยนเป็นรูปใหม่
                                        </div>

                                        <div style="display:grid; gap:10px; grid-template-columns: 1fr 1fr;">
                                            <div><label>คำอธิบาย (TH):</label><input type="hidden" name="existing_captions_th[<?= $img['id'] ?>]" value="<?= h($img['caption']) ?>" id="t_th_<?= $img['id'] ?>"><trix-editor input="t_th_<?= $img['id'] ?>" style="min-height:80px;"></trix-editor></div>
                                            <div><label>Caption (EN):</label><input type="hidden" name="existing_captions_en[<?= $img['id'] ?>]" value="<?= h($img['caption_en']) ?>" id="t_en_<?= $img['id'] ?>"><trix-editor input="t_en_<?= $img['id'] ?>" style="min-height:80px;"></trix-editor></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    <?php endforeach;
                    endif; ?>
                </div>

                <button type="button" id="add_gallery_btn" class="btn-secondary" style="width:100%; padding:10px; background:#f0f0f0; border:1px solid #ccc; margin-top:10px; border-radius:6px; cursor:pointer;">+ เพิ่มรูปใหม่ (Add New)</button>
            </fieldset>

            <div class="publish-box">
                <label class="publish-label" style="display:flex; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="status" id="status" <?= $status ? 'checked' : '' ?> style="width:20px; height:20px;">
                    <span>เผยแพร่บทความ (Publish)</span>
                </label>
            </div>

            <div class="actions-row">
                <button type="submit" class="btn-save">บันทึกการแก้ไข (Save Changes)</button>
                <a href="index.php" class="btn-cancel">ยกเลิก</a>
            </div>
        </form>
    </div>
</main>

<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner"></div>
    <p style="color:white">กำลังบันทึกข้อมูล...</p>
</div>
<div id="alertOverlay" class="alert-overlay">
    <div class="alert-dialog"><span class="material-symbols-rounded" style="font-size:48px;color:#f59e0b;">warning</span>
        <p id="alertMessage"></p><button id="alertBtn" class="cmodal-btn-primary">OK</button>
    </div>
</div>
<div id="confirmOverlay" class="confirm-overlay">
    <div class="confirm-dialog">
        <p id="confirmMessage"></p><button id="confirmCancel" class="cmodal-btn-cancel">Cancel</button><button id="confirmOk" class="cmodal-btn-confirm">OK</button>
    </div>
</div>

<?php include '../../templates/footer_admin.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>

<script>
    // --- UI Helpers ---
    const alertOv = document.getElementById('alertOverlay');
    const alertMsg = document.getElementById('alertMessage');
    document.getElementById('alertBtn').onclick = () => alertOv.classList.remove('show');

    function myAlert(msg) {
        alertMsg.textContent = msg;
        alertOv.classList.add('show');
    }

    const confOv = document.getElementById('confirmOverlay');
    const confMsg = document.getElementById('confirmMessage');
    let confCb = null;
    document.getElementById('confirmCancel').onclick = () => {
        confOv.classList.remove('show');
        confCb = null;
    };
    document.getElementById('confirmOk').onclick = () => {
        if (confCb) confCb();
        confOv.classList.remove('show');
    };

    function myConfirm(msg, cb) {
        confMsg.textContent = msg;
        confCb = cb;
        confOv.classList.add('show');
    }

    // --- File Validation & Preview ---
    function validate(file) {
        if (!file) return null;
        const MAX = 5 * 1024 * 1024;
        const EXTS = ['jpg', 'jpeg', 'png', 'webp'];
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (file.size > MAX) return `ไฟล์ใหญ่เกิน 5MB (${(file.size/1024/1024).toFixed(2)}MB)`;
        if (!EXTS.includes(ext)) return `นามสกุล .${ext} ไม่รองรับ!`;
        return null;
    }

    // Preview for Main Image / New Gallery Items
    function previewImg(file, container, input) {
        container.innerHTML = '';
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            container.innerHTML = `<div class="image-preview-item"><img src="${e.target.result}"><button type="button" class="js-delete-preview" onclick="this.closest('.image-preview-item').remove(); document.getElementById('${input.id}').value='';">&times;</button></div>`;
        };
        reader.readAsDataURL(file);
    }

    // Preview for REPLACING existing images
    function previewReplace(input, id) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const err = validate(file);
            if (err) {
                myAlert(err);
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                // Update the <img> tag directly to show the new one
                document.getElementById('view_img_' + id).src = e.target.result;
                // Show label indicating change
                document.getElementById('prev_rep_' + id).style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    }

    // Toggle "Delete" state for existing images
    function toggleExist(chk, id) {
        const box = document.getElementById(id);
        const txt = box.querySelector('.btn-remove-text .txt');
        const repBtn = box.querySelector('.btn-replace-img');
        const repInput = box.querySelector('input[type="file"]');

        if (chk.checked) {
            box.classList.add('is-deleted');
            txt.innerHTML = 'ยกเลิกการลบ';
            // If deleting, disable replace functionality visual
            if (repBtn) repBtn.style.display = 'none';
            // Clear replace input if any
            if (repInput) repInput.value = '';
        } else {
            box.classList.remove('is-deleted');
            txt.innerHTML = 'ลบรูปนี้';
            if (repBtn) repBtn.style.display = 'inline-block';
        }
    }

    // --- Dynamic Gallery (New Items) ---
    let gIdx = 0;
    document.addEventListener('DOMContentLoaded', () => {
        const wrapper = document.getElementById('gallery_wrapper');
        const MAX_TOTAL = 10;

        // Main Image Logic
        const mDrop = document.getElementById('main_drop');
        const mInput = document.getElementById('main_image');
        mDrop.onclick = () => mInput.click();
        mDrop.ondragover = e => {
            e.preventDefault();
            mDrop.classList.add('is-dragover');
        };
        mDrop.ondragleave = () => mDrop.classList.remove('is-dragover');
        mDrop.ondrop = e => {
            e.preventDefault();
            mDrop.classList.remove('is-dragover');
            if (e.dataTransfer.files.length) {
                mInput.files = e.dataTransfer.files;
                const err = validate(mInput.files[0]);
                if (err) {
                    mInput.value = '';
                    myAlert(err);
                    return;
                }
                previewImg(mInput.files[0], document.getElementById('main_preview'), mInput);
            }
        };
        mInput.onchange = () => {
            if (mInput.files.length) {
                const err = validate(mInput.files[0]);
                if (err) {
                    mInput.value = '';
                    myAlert(err);
                    return;
                }
                previewImg(mInput.files[0], document.getElementById('main_preview'), mInput);
            }
        };

        // Add New Gallery Item
        document.getElementById('add_gallery_btn').onclick = () => {
            const total = document.querySelectorAll('.gallery-item-box:not(.is-deleted)').length;
            if (total >= MAX_TOTAL) return myAlert(`เพิ่มได้สูงสุด ${MAX_TOTAL} รูป`);

            const div = document.createElement('div');
            div.className = 'gallery-item-box';
            div.innerHTML = `
            <div class="gallery-header"><span class="gallery-title">รูปใหม่ (New)</span><button type="button" class="btn-remove-text rem-btn">ลบรายการนี้</button></div>
            <input type="file" name="additional_images[]" id="g_input_${gIdx}" style="display:none">
            <div class="drop-zone" id="g_drop_${gIdx}"><span class="material-symbols-rounded">cloud_upload</span><br>เลือกรูปภาพ</div>
            <div class="image-preview" id="g_prev_${gIdx}"></div>
            <div style="display:grid; gap:10px; grid-template-columns: 1fr 1fr; margin-top:15px;">
                <div><label>คำอธิบาย (TH):</label><input type="hidden" id="trix_th_${gIdx}" name="caption_detail[]"><trix-editor input="trix_th_${gIdx}" style="min-height:80px;"></trix-editor></div>
                <div><label>Caption (EN):</label><input type="hidden" id="trix_en_${gIdx}" name="caption_detail_en[]"><trix-editor input="trix_en_${gIdx}" style="min-height:80px;"></trix-editor></div>
            </div>
        `;
            wrapper.appendChild(div);

            const drop = div.querySelector(`#g_drop_${gIdx}`);
            const input = div.querySelector(`#g_input_${gIdx}`);
            const prev = div.querySelector(`#g_prev_${gIdx}`);
            const rem = div.querySelector(`.rem-btn`);

            rem.onclick = () => div.remove();

            drop.onclick = () => input.click();
            drop.ondragover = e => {
                e.preventDefault();
                drop.classList.add('is-dragover');
            };
            drop.ondragleave = () => drop.classList.remove('is-dragover');
            drop.ondrop = e => {
                e.preventDefault();
                drop.classList.remove('is-dragover');
                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    const err = validate(input.files[0]);
                    if (err) {
                        input.value = '';
                        myAlert(err);
                        return;
                    }
                    previewImg(input.files[0], prev, input);
                }
            };
            input.onchange = () => {
                if (input.files.length) {
                    const err = validate(input.files[0]);
                    if (err) {
                        input.value = '';
                        myAlert(err);
                        return;
                    }
                    previewImg(input.files[0], prev, input);
                }
            };
            gIdx++;
        };

        // Form Submit
        const form = document.getElementById('editArticleForm');
        const loader = document.getElementById('loadingOverlay');
        form.onsubmit = async (e) => {
            e.preventDefault();
            loader.classList.add('show');
            const formData = new FormData(form);
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                loader.classList.remove('show');

                if (data.status === 'success') {
                    window.location.href = data.redirect;
                } else {
                    myAlert(data.message || 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ');
                    if (data.bad_input_index !== undefined) {
                        const inputs = document.getElementsByName('additional_images[]');
                        if (inputs[data.bad_input_index]) {
                            inputs[data.bad_input_index].value = '';
                        }
                    }
                }
            } catch (err) {
                loader.classList.remove('show');
                myAlert('การเชื่อมต่อขัดข้อง: ' + err.message);
            }
        };
    });
</script>