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
require_perms(['content.write']); // แก้บทความ: หน้าร้าน+ ขึ้นไป

$isModal = !empty($_GET['modal']);

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

    // ── Image helpers ───────────────────────────────────────────
    function art_process_webp(string $tmp, string $dest, int $maxW = 1200, int $q = 82) {
        $info = @getimagesize($tmp);
        if (!$info) return false;
        [$w, $h, $type] = $info;
        switch ($type) {
            case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($tmp); break;
            case IMAGETYPE_PNG:  $src = @imagecreatefrompng($tmp);  break;
            case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($tmp); break;
            default:             $src = false;
        }
        if (!$src) return false;
        if ($w > $maxW) {
            $nh = (int)round($h * $maxW / $w);
            $dst = imagecreatetruecolor($maxW, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
            imagedestroy($src); $src = $dst;
            $w = $maxW; $h = $nh;
        }
        $ok = imagewebp($src, $dest, $q);
        imagedestroy($src);
        if (!$ok) return false;
        return ['w' => $w, 'h' => $h, 'size' => filesize($dest)];
    }
    function art_upload_slot(): array {
        $ym = date('Y/m');
        $dir = __DIR__ . '/../../uploads/articles/' . $ym;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $name = bin2hex(random_bytes(8)) . '.webp';
        return [$dir . '/' . $name, '/uploads/articles/' . $ym . '/' . $name];
    }
    function art_safe_unlink(string $path): void {
        $root = realpath(__DIR__ . '/../../uploads');
        $abs  = realpath(__DIR__ . '/../../' . ltrim($path, '/'));
        if ($abs && $root && str_starts_with($abs, $root)) @unlink($abs);
    }
    function art_mime_ok(string $tmp): bool {
        return in_array((new finfo(FILEINFO_MIME_TYPE))->file($tmp), ['image/jpeg','image/png','image/webp'], true);
    }

    try {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new Exception('Missing ID');

        $pdo->beginTransaction();

        $title       = trim($_POST['title']       ?? '');
        $slug        = trim($_POST['slug']        ?? '');
        $content     = $_POST['content']     ?? '';
        $excerpt     = trim($_POST['excerpt']     ?? '');
        $title_en    = trim($_POST['title_en']    ?? '');
        $slug_en     = trim($_POST['slug_en']     ?? '');
        $content_en  = $_POST['content_en']  ?? '';
        $excerpt_en  = trim($_POST['excerpt_en']  ?? '');
        $category    = trim($_POST['category']    ?? 'tip');
        $youtube_url = trim($_POST['youtube_url'] ?? '');
        $status      = (int)($_POST['status']     ?? 0);

        if (!$title) throw new Exception('กรุณากรอกชื่อบทความ');

        // ── Main Image ────────────────────────────────────────────
        $currImg = $pdo->prepare("SELECT image, og_image_width, og_image_height FROM articles WHERE id=?");
        $currImg->execute([$id]);
        $currRow    = $currImg->fetch(PDO::FETCH_ASSOC);
        $currImgPath = $currRow['image'] ?? '';
        $newMainImage = $currImgPath;
        $ogW = $currRow['og_image_width']; $ogH = $currRow['og_image_height'];

        if (!empty($_POST['delete_main_image']) && (int)$_POST['delete_main_image'] === 1) {
            if ($currImgPath) art_safe_unlink($currImgPath);
            $newMainImage = null; $ogW = null; $ogH = null;
        }

        $f = $_FILES['main_image'] ?? null;
        if ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('รูปหลักมีปัญหา (code ' . $f['error'] . ')');
            if ($f['size'] > 10 * 1024 * 1024) throw new Exception('รูปหลักใหญ่เกิน 10MB');
            if (!art_mime_ok($f['tmp_name'])) throw new Exception('รูปหลักผิดประเภท');
            if ($currImgPath) art_safe_unlink($currImgPath);
            [$destPath, $destUrl] = art_upload_slot();
            $dims = art_process_webp($f['tmp_name'], $destPath);
            if (!$dims) throw new Exception('แปลงรูปหลักเป็น WebP ไม่สำเร็จ');
            $newMainImage = $destUrl; $ogW = $dims['w']; $ogH = $dims['h'];
        }

        // ── Update Article ────────────────────────────────────────
        $pdo->prepare("UPDATE articles SET title=?,slug=?,category=?,content=?,excerpt=?,youtube_url=?,
            image=?,og_image_width=?,og_image_height=?,status=?,
            title_en=?,slug_en=?,content_en=?,excerpt_en=? WHERE id=?")
          ->execute([$title,$slug,$category,$content,$excerpt,$youtube_url,
            $newMainImage,$ogW,$ogH,$status,
            $title_en,$slug_en,$content_en,$excerpt_en,$id]);

        // ── Gallery: DELETE ───────────────────────────────────────
        $deleted_ids = [];
        foreach ((array)($_POST['delete_gallery_ids'] ?? []) as $did) {
            $did = (int)$did;
            $row = $pdo->prepare("SELECT image_path FROM article_images WHERE id=? AND article_id=?");
            $row->execute([$did, $id]);
            if ($p = $row->fetchColumn()) art_safe_unlink($p);
            $pdo->prepare("DELETE FROM article_images WHERE id=? AND article_id=?")->execute([$did, $id]);
            $deleted_ids[] = $did;
        }

        // ── Gallery: UPDATE alt + captions ────────────────────────
        if (!empty($_POST['existing_captions_th'])) {
            $upd = $pdo->prepare("UPDATE article_images SET alt=?,alt_en=?,caption=?,caption_en=? WHERE id=? AND article_id=?");
            foreach ($_POST['existing_captions_th'] as $gid => $cap) {
                $gid = (int)$gid;
                if (in_array($gid, $deleted_ids)) continue;
                $upd->execute([
                    trim($_POST['existing_alts_th'][$gid]    ?? '') ?: null,
                    trim($_POST['existing_alts_en'][$gid]    ?? '') ?: null,
                    trim($cap) ?: null,
                    trim($_POST['existing_captions_en'][$gid] ?? '') ?: null,
                    $gid, $id,
                ]);
            }
        }

        // ── Gallery: INSERT NEW (WebP) ────────────────────────────
        if (!empty($_FILES['additional_images']['tmp_name'][0])) {
            $maxOrd = (int)$pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM article_images WHERE article_id=?")
                         ->execute([$id]) ? $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM article_images WHERE article_id=$id")->fetchColumn() : 0;
            $ins = $pdo->prepare("INSERT INTO article_images
                (article_id,image_path,alt,alt_en,caption,caption_en,width,height,file_size,sort_order,is_cover)
                VALUES (?,?,?,?,?,?,?,?,?,?,0)");
            foreach ($_FILES['additional_images']['tmp_name'] as $i => $tmp) {
                $err = $_FILES['additional_images']['error'][$i];
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                if ($err !== UPLOAD_ERR_OK) throw new Exception("รูปประกอบที่ " . ($i+1) . " มีปัญหา");
                if ($_FILES['additional_images']['size'][$i] > 10 * 1024 * 1024) throw new Exception("รูปประกอบที่ " . ($i+1) . " ใหญ่เกิน 10MB");
                if (!art_mime_ok($tmp)) throw new Exception("รูปประกอบที่ " . ($i+1) . " ผิดประเภท");
                [$destPath, $destUrl] = art_upload_slot();
                $dims = art_process_webp($tmp, $destPath);
                if (!$dims) throw new Exception("แปลงรูปประกอบที่ " . ($i+1) . " ไม่สำเร็จ");
                $ins->execute([
                    $id, $destUrl,
                    trim($_POST['alt_detail'][$i]       ?? '') ?: null,
                    trim($_POST['alt_detail_en'][$i]    ?? '') ?: null,
                    trim($_POST['caption_detail'][$i]   ?? '') ?: null,
                    trim($_POST['caption_detail_en'][$i]?? '') ?: null,
                    $dims['w'], $dims['h'], $dims['size'], ++$maxOrd,
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'redirect' => "edit.php?id=$id&updated=1", 'modal' => $isModal]);
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

$_art_role  = $_SESSION['admin_role'] ?? '';
$_art_owner = (int)($article['admin_id'] ?? 0);
if ($_art_owner !== 0 && $_art_owner !== (int)$_SESSION['admin_id'] && $_art_role !== 'super_admin') {
    if ($isModal) { echo json_encode(['status'=>'error','message'=>'คุณไม่มีสิทธิ์แก้ไขบทความนี้']); exit; }
    $_SESSION['flash'] = 'คุณไม่มีสิทธิ์แก้ไขบทความนี้';
    header('Location: index.php'); exit;
}

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

// ================================================================
// 3) VIEW RENDERING
// ================================================================
$catRows = [];
try {
    $catRows = $pdo->query("SELECT DISTINCT category FROM articles WHERE category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

if ($isModal): ?>
<!DOCTYPE html><html lang="th">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('admin_theme')||'dark');</script>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
<link rel="stylesheet" href="/admin/templates/assets/css/admin.css?v=<?= time() ?>">
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<?php else: ?>
<?php include __DIR__ . '/../templates/header_admin.php'; ?>
<?php endif; ?>

<style>
html,body{margin:0;padding:0;}
.modal-mode{height:100vh;overflow:hidden;display:flex;flex-direction:column;background:var(--bg-surface);}
#ifrm-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);background:var(--bg-surface);flex-shrink:0;}
#ifrm-header h2{margin:0;font-size:15px;font-weight:700;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:600px;}
#ifrm-body{flex:1;overflow-y:auto;padding:0;}
#ifrm-footer{display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-surface);flex-shrink:0;gap:8px;}
.tab-nav{display:flex;gap:4px;padding:14px 20px 0;background:var(--bg-surface);border-bottom:1px solid var(--border);flex-shrink:0;}
.tab-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px 8px 0 0;border:1px solid transparent;border-bottom:none;font-size:13px;font-weight:600;cursor:pointer;color:var(--text-muted);background:transparent;transition:all .18s;}
.tab-btn.active{color:var(--primary);background:var(--bg-surface);border-color:var(--border);margin-bottom:-1px;border-bottom:1px solid var(--bg-surface);}
.tab-btn.done .tab-step{background:#10b981;color:#fff;}
.tab-step{width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;background:var(--border);color:var(--text-muted);}
.tab-btn.active .tab-step{background:var(--primary);color:#fff;}
.tab-pane{display:none;padding:20px;}.tab-pane.active{display:block;}
.rp-wrap{max-width:900px;margin:0 auto;padding:32px 24px;}
.rp-card{background:var(--bg-surface);border:1px solid var(--border);border-radius:12px;padding:20px 24px;margin-bottom:16px;}
.rp-card-title{font-size:13px;font-weight:700;color:var(--primary);margin-bottom:16px;display:flex;align-items:center;gap:6px;}
.rp-card-title .material-symbols-rounded{font-size:17px;}
.rp-label{font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:5px;display:block;text-transform:uppercase;letter-spacing:.4px;}
.rp-input,.rp-select,.rp-textarea{width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-main);font-size:14px;outline:none;font-family:'Sarabun',sans-serif;box-sizing:border-box;}
.rp-input:focus,.rp-select:focus,.rp-textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.rp-textarea{resize:vertical;min-height:80px;}
.rp-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.rp-field{margin-bottom:14px;}
.rp-hint{font-size:11px;color:var(--text-muted);margin-top:4px;}
.rp-slug-row{display:flex;gap:6px;align-items:center;}.rp-slug-row input{flex:1;}
.btn-slug{padding:7px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-muted);font-size:12px;cursor:pointer;white-space:nowrap;font-family:'Sarabun',sans-serif;}
.btn-slug:hover{border-color:var(--primary);color:var(--primary);}
.btn-save{display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:8px;background:var(--primary,#3b82f6);color:#fff;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:'Sarabun',sans-serif;transition:opacity .2s;}
.btn-save:hover{opacity:.88;}
.btn-cancel{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;background:transparent;color:var(--text-muted);font-size:14px;font-weight:600;border:1px solid var(--border);cursor:pointer;font-family:'Sarabun',sans-serif;}
.btn-cancel:hover{border-color:var(--text-muted);color:var(--text-main);}
.img-slots{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-top:4px;}
.img-slot{aspect-ratio:1;border:2px dashed var(--border);border-radius:10px;position:relative;overflow:hidden;background:var(--bg-surface-alt);}
.img-slot.has-img{border-style:solid;border-color:var(--primary);}
.img-slot.deleted{opacity:.25;pointer-events:none;}
.img-slot img{width:100%;height:100%;object-fit:cover;}
.slot-label{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-muted);font-size:11px;gap:4px;cursor:pointer;}
.slot-rm{position:absolute;top:4px;right:4px;width:20px;height:20px;background:#ef4444;color:#fff;border-radius:50%;border:none;cursor:pointer;font-size:12px;display:none;align-items:center;justify-content:center;line-height:1;}
.img-slot.has-img .slot-rm{display:flex;}
.cover-badge{position:absolute;bottom:4px;left:4px;background:#10b981;color:#fff;font-size:9px;font-weight:700;padding:2px 5px;border-radius:4px;}
.img-dropzone{border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;color:var(--text-muted);cursor:pointer;transition:all .2s;margin-top:10px;}
.img-dropzone.drag-over{border-color:var(--primary);background:rgba(37,99,235,.05);}
.rp-editor{border:1px solid var(--border);border-radius:8px;overflow:hidden;}
.rp-editor .ql-toolbar.ql-snow{border:none;border-bottom:1px solid var(--border);background:var(--bg-surface-alt);padding:6px 10px;}
.rp-editor .ql-container.ql-snow{border:none;}
.rp-editor .ql-editor{min-height:180px;padding:10px 12px;line-height:1.7;font-family:'Sarabun',sans-serif;font-size:15px;color:var(--text-main);}
[data-theme="dark"] .rp-editor .ql-toolbar.ql-snow .ql-stroke{stroke:#9ca3af;}
[data-theme="dark"] .rp-editor .ql-toolbar.ql-snow .ql-fill{fill:#9ca3af;}
[data-theme="dark"] .rp-editor .ql-toolbar.ql-snow .ql-picker-label{color:#9ca3af;}
[data-theme="dark"] .rp-editor .ql-editor{color:var(--text-main);}
[data-theme="dark"] .rp-editor .ql-editor.ql-blank::before{color:var(--text-muted);}
.subtab-nav{display:flex;gap:8px;margin-bottom:16px;}
.subtab-btn{padding:5px 14px;border-radius:6px;border:1px solid var(--border);font-size:13px;font-weight:600;cursor:pointer;color:var(--text-muted);background:transparent;font-family:'Sarabun',sans-serif;}
.subtab-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);}
.subtab-pane{display:none;}.subtab-pane.active{display:block;}
.msg-error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#dc2626;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;}
.msg-success{background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);color:#10b981;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;}
.rp-confirm-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9999;align-items:center;justify-content:center;}
.rp-confirm-overlay.show{display:flex;}
.rp-confirm-box{background:var(--bg-surface);width:90%;max-width:340px;border-radius:14px;overflow:hidden;border:1px solid var(--border);box-shadow:0 8px 32px rgba(0,0,0,.2);}
.rp-confirm-head{padding:18px 20px 12px;text-align:center;background:rgba(239,68,68,.05);border-bottom:1px solid var(--border);}
.rp-confirm-body{padding:14px 20px;text-align:center;color:var(--text-main);font-size:14px;line-height:1.6;}
.rp-confirm-foot{padding:10px 16px;border-top:1px solid var(--border);background:var(--bg-surface-alt);display:flex;gap:8px;justify-content:flex-end;}
.rp-page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;}
.rp-page-header h1{font-size:22px;font-weight:700;color:var(--text-main);margin:0;display:flex;align-items:center;gap:8px;}
.del-chk-label{display:flex;align-items:center;gap:6px;font-size:12px;color:#ef4444;cursor:pointer;margin-top:6px;}
</style>

<?php if ($isModal): ?>
<body class="modal-mode">
<div id="ifrm-header">
    <h2><span class="material-symbols-rounded" style="font-size:17px;vertical-align:-3px;">edit_note</span> แก้ไข: <?= h($title) ?></h2>
    <button type="button" onclick="safeClose()"
            style="background:var(--bg-surface-alt);border:1px solid var(--border);width:34px;height:34px;border-radius:9px;cursor:pointer;color:var(--text-muted);display:flex;align-items:center;justify-content:center;transition:.2s;padding:0;"
            onmouseover="this.style.background='#ef4444';this.style.color='#fff'" onmouseout="this.style.background='var(--bg-surface-alt)';this.style.color='var(--text-muted)'">
        <span class="material-symbols-rounded" style="font-size:18px;">close</span>
    </button>
</div>
<div class="tab-nav">
    <button type="button" class="tab-btn active" data-tab="1" onclick="gotoTab(1)"><span class="tab-step">1</span> ข้อมูล / SEO</button>
    <button type="button" class="tab-btn" data-tab="2" onclick="gotoTab(2)"><span class="tab-step">2</span> รูปภาพ</button>
    <button type="button" class="tab-btn" data-tab="3" onclick="gotoTab(3)"><span class="tab-step">3</span> เนื้อหา</button>
</div>
<div id="ifrm-body">
<?php else: ?>
<div class="rp-wrap">
<div class="rp-page-header">
    <h1><span class="material-symbols-rounded" style="color:var(--primary);">edit_note</span> แก้ไขบทความ</h1>
    <a href="index.php" class="btn-cancel"><span class="material-symbols-rounded" style="font-size:14px;">arrow_back</span> กลับ</a>
</div>
<?php if ($success): ?>
<div class="msg-success"><?= h($success) ?></div>
<?php endif; ?>
<?php endif; ?>

<div id="msg-error" class="msg-error" style="display:none;<?= $isModal ? 'margin:12px 20px 0;' : '' ?>"></div>

<form id="article-form" method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">

<?php if (!$isModal): ?>
<div class="tab-nav" style="margin-bottom:0;">
    <button type="button" class="tab-btn active" data-tab="1" onclick="gotoTab(1)"><span class="tab-step">1</span> ข้อมูล / SEO</button>
    <button type="button" class="tab-btn" data-tab="2" onclick="gotoTab(2)"><span class="tab-step">2</span> รูปภาพ</button>
    <button type="button" class="tab-btn" data-tab="3" onclick="gotoTab(3)"><span class="tab-step">3</span> เนื้อหา</button>
</div>
<?php endif; ?>

<!-- ══ TAB 1: ข้อมูล / SEO ══ -->
<div id="tab-1" class="tab-pane active">
    <div class="rp-card">
        <div class="rp-card-title"><span class="material-symbols-rounded">tune</span> ข้อมูลหลัก</div>
        <div class="rp-grid">
            <div class="rp-field">
                <label class="rp-label">ชื่อบทความ (TH) <span style="color:#ef4444">*</span></label>
                <input type="text" name="title" class="rp-input" value="<?= h($title) ?>" required id="inp-title">
            </div>
            <div class="rp-field">
                <label class="rp-label">ชื่อบทความ (EN)</label>
                <input type="text" name="title_en" class="rp-input" value="<?= h($title_en) ?>" id="inp-title-en">
            </div>
        </div>
        <div class="rp-grid">
            <div class="rp-field">
                <label class="rp-label">Slug (TH)</label>
                <div class="rp-slug-row">
                    <input type="text" name="slug" class="rp-input" value="<?= h($slug) ?>" id="inp-slug">
                    <button type="button" class="btn-slug" onclick="autoSlug('th')">Auto</button>
                </div>
                <div class="rp-hint">URL: /article/<b id="slug-preview"><?= h($slug) ?></b></div>
            </div>
            <div class="rp-field">
                <label class="rp-label">Slug (EN)</label>
                <div class="rp-slug-row">
                    <input type="text" name="slug_en" class="rp-input" value="<?= h($slug_en) ?>" id="inp-slug-en">
                    <button type="button" class="btn-slug" onclick="autoSlug('en')">Auto</button>
                </div>
            </div>
        </div>
        <div class="rp-grid">
            <div class="rp-field">
                <label class="rp-label">หมวดหมู่</label>
                <input type="text" name="category" class="rp-input" value="<?= h($category) ?>" list="cat-list" id="inp-cat">
                <datalist id="cat-list">
                    <?php foreach ($catRows as $c): ?>
                        <option value="<?= h($c) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="rp-field">
                <label class="rp-label">สถานะ</label>
                <select name="status" class="rp-select">
                    <option value="1" <?= (int)$status === 1 ? 'selected' : '' ?>>เผยแพร่</option>
                    <option value="0" <?= (int)$status === 0 ? 'selected' : '' ?>>ซ่อน</option>
                </select>
            </div>
        </div>
        <div class="rp-field">
            <label class="rp-label">YouTube URL (ถ้ามี)</label>
            <input type="text" name="youtube_url" class="rp-input" value="<?= h($youtube_url) ?>">
        </div>
    </div>
</div>

<!-- ══ TAB 2: รูปภาพ ══ -->
<?php
$existingGalleryCount = count($existing_images);
$maxNewFiles = max(0, 4 - $existingGalleryCount);
?>
<div id="tab-2" class="tab-pane">
    <div class="rp-card">
        <div class="rp-card-title"><span class="material-symbols-rounded" style="color:#f59e0b;">photo_library</span> รูปภาพ (สูงสุด 5 รูป — รูปแรก = ปก)</div>
        <div class="img-slots" id="img-slots">
            <!-- Slot 0: main image -->
            <div class="img-slot <?= $current_main_image ? 'has-img' : '' ?>" id="slot-0">
                <div class="slot-label"<?= $current_main_image ? ' style="display:none"' : '' ?>>
                    <span class="material-symbols-rounded" style="font-size:22px;">add_photo_alternate</span>
                    <span>ปก</span>
                </div>
                <?php if ($current_main_image): ?>
                <img src="<?= h($current_main_image) ?>" alt="">
                <button type="button" class="slot-rm" onclick="deleteMainImg()">✕</button>
                <?php endif; ?>
                <div class="cover-badge">ปก</div>
            </div>
            <!-- Existing gallery slots -->
            <?php foreach ($existing_images as $eimg): ?>
            <div class="img-slot has-img" id="slot-ex-<?= (int)$eimg['id'] ?>">
                <img src="<?= h($eimg['image_path']) ?>" alt="">
                <button type="button" class="slot-rm" onclick="markDelGallery(<?= (int)$eimg['id'] ?>)">✕</button>
                <input type="hidden" name="delete_gallery_ids[]" id="del-gal-<?= (int)$eimg['id'] ?>" value="" disabled>
            </div>
            <?php endforeach; ?>
            <!-- Empty new slots -->
            <?php for ($ni = 0; $ni < $maxNewFiles; $ni++): ?>
            <div class="img-slot" id="new-slot-<?= $ni ?>">
                <div class="slot-label">
                    <span class="material-symbols-rounded" style="font-size:22px;">add_photo_alternate</span>
                    <span>เพิ่มรูป</span>
                </div>
            </div>
            <?php endfor; ?>
        </div>
        <div class="img-dropzone" id="img-dropzone">
            <span class="material-symbols-rounded" style="font-size:28px;display:block;margin-bottom:6px;">cloud_upload</span>
            คลิกหรือลากรูปมาใส่ · JPG, PNG, WebP · ระบบแปลง WebP อัตโนมัติ
        </div>
        <input type="hidden" name="delete_main_image" id="del-main-img" value="0">
        <input type="file" id="main_img_input" name="main_image" accept="image/jpeg,image/png,image/webp" style="display:none">
        <input type="file" id="gallery_input" name="additional_images[]" multiple accept="image/jpeg,image/png,image/webp" style="display:none">
        <input type="file" id="picker_input" multiple accept="image/jpeg,image/png,image/webp" style="display:none">
        <p class="rp-hint" style="margin-top:8px;">ขนาดสูงสุด 10MB/รูป · ระบบ resize เป็น WebP ก่อนบันทึก</p>
        <?php if ($existing_images || $maxNewFiles > 0): ?>
        <div style="margin-top:16px;border-top:1px solid var(--border);padding-top:14px;">
            <div class="rp-label" style="margin-bottom:10px;">คำอธิบายใต้ภาพ (ไม่บังคับ)</div>
            <?php foreach ($existing_images as $idx => $eimg): ?>
            <div id="cap-ex-row-<?= (int)$eimg['id'] ?>" style="display:grid;grid-template-columns:44px 1fr 1fr;gap:8px;align-items:center;margin-bottom:8px;transition:opacity .2s;">
                <span style="font-size:11px;color:var(--text-muted);font-weight:600;white-space:nowrap;">รูป <?= $idx+2 ?></span>
                <input type="text" name="existing_captions_th[<?= (int)$eimg['id'] ?>]" class="rp-input" value="<?= h($eimg['caption'] ?? '') ?>" placeholder="คำอธิบาย (TH)" style="font-size:12px;padding:5px 8px;">
                <input type="text" name="existing_captions_en[<?= (int)$eimg['id'] ?>]" class="rp-input" value="<?= h($eimg['caption_en'] ?? '') ?>" placeholder="Caption (EN)" style="font-size:12px;padding:5px 8px;">
            </div>
            <?php endforeach; ?>
            <?php for ($ni = 0; $ni < $maxNewFiles; $ni++): ?>
            <div id="new-cap-row-<?= $ni ?>" style="display:grid;grid-template-columns:44px 1fr 1fr;gap:8px;align-items:center;margin-bottom:8px;opacity:.3;transition:opacity .2s;">
                <span style="font-size:11px;color:var(--text-muted);font-weight:600;white-space:nowrap;">รูป <?= 1 + $existingGalleryCount + $ni + 1 ?></span>
                <input type="text" name="caption_detail[]" id="new-cap-th-<?= $ni ?>" class="rp-input" placeholder="คำอธิบาย (TH)" style="font-size:12px;padding:5px 8px;">
                <input type="text" name="caption_detail_en[]" id="new-cap-en-<?= $ni ?>" class="rp-input" placeholder="Caption (EN)" style="font-size:12px;padding:5px 8px;">
            </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ TAB 3: เนื้อหา ══ -->
<div id="tab-3" class="tab-pane">
    <div class="rp-card">
        <div class="subtab-nav">
            <button type="button" class="subtab-btn active" onclick="gotoSubtab(1)">ภาษาไทย</button>
            <button type="button" class="subtab-btn" onclick="gotoSubtab(2)">English</button>
        </div>
        <div id="subtab-1" class="subtab-pane active">
            <div class="rp-field">
                <label class="rp-label">เนื้อหา (TH)</label>
                <div class="rp-editor" id="editor-content-th" data-init="<?= h($content ?? '') ?>"></div>
                <input type="hidden" name="content" id="content-th-val" value="<?= h($content ?? '') ?>">
            </div>
            <div class="rp-field" style="margin-top:14px;">
                <label class="rp-label">ข้อความย่อ (TH)</label>
                <textarea name="excerpt" class="rp-textarea" rows="3"><?= h($excerpt ?? '') ?></textarea>
            </div>
        </div>
        <div id="subtab-2" class="subtab-pane">
            <div class="rp-field">
                <label class="rp-label">Content (EN)</label>
                <div class="rp-editor" id="editor-content-en" data-init="<?= h($content_en ?? '') ?>"></div>
                <input type="hidden" name="content_en" id="content-en-val" value="<?= h($content_en ?? '') ?>">
            </div>
            <div class="rp-field" style="margin-top:14px;">
                <label class="rp-label">Excerpt (EN)</label>
                <textarea name="excerpt_en" class="rp-textarea" rows="3"><?= h($excerpt_en ?? '') ?></textarea>
            </div>
        </div>
    </div>
</div>

</form>

<?php if ($isModal): ?>
</div><!-- #ifrm-body -->
<?php else: ?>
<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
    <a href="index.php" class="btn-cancel">ยกเลิก</a>
    <button type="button" class="btn-save" id="btn-save-main">
        <span class="material-symbols-rounded" style="font-size:15px;">save</span> บันทึก
    </button>
</div>
</div><!-- .rp-wrap -->
<?php endif; ?>

<?php if ($isModal): ?>
<div id="ifrm-footer">
    <button type="button" class="btn-cancel" onclick="safeClose()">
        <span class="material-symbols-rounded" style="font-size:14px;">close</span> ยกเลิก
    </button>
    <div style="display:flex;gap:8px;">
        <button type="button" id="btn-prev" class="btn-cancel" onclick="prevTab()" style="visibility:hidden;">
            <span class="material-symbols-rounded" style="font-size:14px;">arrow_back</span> ก่อนหน้า
        </button>
        <button type="button" id="btn-action" class="btn-save" onclick="nextTab()">
            ถัดไป <span class="material-symbols-rounded" style="font-size:15px;">arrow_forward</span>
        </button>
    </div>
</div>
<div id="rp-confirm" class="rp-confirm-overlay">
    <div class="rp-confirm-box">
        <div class="rp-confirm-head">
            <div style="width:40px;height:40px;border-radius:50%;background:rgba(239,68,68,.12);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                <span class="material-symbols-rounded" style="font-size:20px;">warning</span>
            </div>
            <h3 style="margin:0;font-size:14px;font-weight:700;color:#dc2626;">ออกโดยไม่บันทึก?</h3>
        </div>
        <div class="rp-confirm-body">การแก้ไขจะหายทั้งหมด<br><span style="font-size:12px;color:#ef4444;">ยืนยันจะปิดหน้านี้หรือไม่?</span></div>
        <div class="rp-confirm-foot">
            <button type="button" class="btn-cancel" onclick="closeConfirm()" style="font-size:13px;">อยู่ต่อ</button>
            <button type="button" class="btn-save" style="background:#ef4444;font-size:13px;" onclick="confirmClose()">ออกเลย</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const IS_MODAL = <?= $isModal ? 'true' : 'false' ?>;
const TOTAL_TABS = 3;
let currentTab = 1;
let formDirty = false;
let qTh = null, qEn = null, quillsInited = false;

const QUILL_TB = [
    [{ header:[2,3,false] }],
    ['bold','italic','underline'],
    [{ list:'ordered' },{ list:'bullet' }],
    ['link'],['clean']
];

function initQuillEditors() {
    if (quillsInited) return;
    quillsInited = true;
    const thEl = document.getElementById('editor-content-th');
    const enEl = document.getElementById('editor-content-en');
    qTh = new Quill(thEl, { theme:'snow', placeholder:'เนื้อหาบทความ...', modules:{ toolbar: QUILL_TB } });
    qEn = new Quill(enEl, { theme:'snow', placeholder:'Article content...', modules:{ toolbar: QUILL_TB } });
    // Block pasted images that point to a local temp file (file://, blob:, /var/folders…)
    // instead of an uploaded/remote URL — these break on production.
    [qTh, qEn].forEach(q => blockLocalImagePaste(q));
    // Load existing content
    const initTh = thEl?.getAttribute('data-init') || '';
    const initEn = enEl?.getAttribute('data-init') || '';
    if (initTh.trim()) qTh.clipboard.dangerouslyPasteHTML(initTh);
    if (initEn.trim()) qEn.clipboard.dangerouslyPasteHTML(initEn);
    qTh.on('text-change', () => { formDirty = true; });
    qEn.on('text-change', () => { formDirty = true; });
}

// Drop any pasted <img> whose src is a local/temporary file path.
// Real images must be added via the gallery uploader, not pasted screenshots.
function blockLocalImagePaste(q) {
    if (!q) return;
    const BAD = /^(file:|blob:)|\/var\/folders\/|\/private\/var\/|TemporaryItems|^data:/i;
    let warned = false;
    q.clipboard.addMatcher('IMG', (node, delta) => {
        const src = node.getAttribute('src') || '';
        if (BAD.test(src)) {
            if (!warned) { warned = true;
                alert('วางรูปจากเครื่องตรงๆ ไม่ได้ — รูปจะพังบนเว็บจริง\nให้อัปโหลดผ่านแกลเลอรีรูปภาพแทน'); }
            return { ops: [] }; // strip it
        }
        return delta;
    });
}

function syncQuill() {
    if (qTh) document.getElementById('content-th-val').value = qTh.root.innerHTML;
    if (qEn) document.getElementById('content-en-val').value = qEn.root.innerHTML;
}

// Tabs
function gotoTab(n) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + n)?.classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(b => {
        const ti = parseInt(b.dataset.tab);
        b.classList.remove('active','done');
        if (ti === n) b.classList.add('active');
        else if (ti < n) b.classList.add('done');
    });
    if (IS_MODAL) {
        const prev = document.getElementById('btn-prev');
        if (prev) prev.style.visibility = n > 1 ? 'visible' : 'hidden';
        const btn = document.getElementById('btn-action');
        if (btn) {
            if (n === TOTAL_TABS) {
                btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:15px;">save</span> บันทึก';
                btn.onclick = doSave;
            } else {
                btn.innerHTML = 'ถัดไป <span class="material-symbols-rounded" style="font-size:15px;">arrow_forward</span>';
                btn.onclick = nextTab;
            }
        }
    }
    if (n === 3) initQuillEditors();
    currentTab = n;
    if (IS_MODAL) document.getElementById('ifrm-body')?.scrollTo(0, 0);
}

function nextTab() { if (currentTab < TOTAL_TABS) gotoTab(currentTab + 1); }
function prevTab() { if (currentTab > 1) gotoTab(currentTab - 1); }

function gotoSubtab(n) {
    document.querySelectorAll('.subtab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('subtab-' + n)?.classList.add('active');
    document.querySelectorAll('.subtab-btn').forEach((b, i) => b.classList.toggle('active', i + 1 === n));
    if (n === 2 && qEn) qEn.update?.();
}

// Form dirty
document.getElementById('article-form').addEventListener('input', () => { formDirty = true; });
document.getElementById('article-form').addEventListener('change', () => { formDirty = true; });

// Slug
function makeSlug(s) {
    return s.toLowerCase()
            .replace(/[^\x00-\x7F]/g, c => {
                const m={'ก':'k','ข':'k','ค':'k','ง':'ng','จ':'j','ช':'ch','ซ':'s','ด':'d','ต':'t','น':'n','บ':'b','ป':'p','ผ':'p','พ':'p','ม':'m','ย':'y','ร':'r','ล':'l','ว':'w','ส':'s','ห':'h','อ':'a','ะ':'-','า':'-','เ':'e','แ':'ae','โ':'o','ใ':'ai','ไ':'ai'};
                return m[c]||'';
            })
            .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
}
function autoSlug(lang) {
    if (lang === 'th') {
        document.getElementById('inp-slug').value = makeSlug(document.getElementById('inp-title')?.value||'');
        updateSlugPreview();
    } else {
        document.getElementById('inp-slug-en').value = makeSlug(document.getElementById('inp-title-en')?.value||'');
    }
}
function updateSlugPreview() {
    const p = document.getElementById('slug-preview');
    if (p) p.textContent = document.getElementById('inp-slug')?.value||'...';
}
document.getElementById('inp-slug')?.addEventListener('input', updateSlugPreview);

// ── Image upload (repairs-style slots) ───────────────────────
const MAX_NEW_FILES = <?= $maxNewFiles ?>;
let newFiles = [];

// Main image: slot-0
function deleteMainImg() {
    const slot = document.getElementById('slot-0');
    document.getElementById('del-main-img').value = '1';
    document.getElementById('main_img_input').value = '';
    slot.querySelector('img')?.remove();
    slot.querySelector('.slot-rm')?.remove();
    slot.classList.remove('has-img');
    const label = slot.querySelector('.slot-label');
    if (label) label.style.display = '';
    formDirty = true;
}

document.getElementById('main_img_input').addEventListener('change', function() {
    const file = this.files[0]; if (!file) return;
    const slot = document.getElementById('slot-0');
    slot.querySelector('img')?.remove();
    slot.querySelector('.slot-rm')?.remove();
    const label = slot.querySelector('.slot-label');
    if (label) label.style.display = 'none';
    slot.classList.add('has-img');
    const img = document.createElement('img');
    img.src = URL.createObjectURL(file);
    slot.insertBefore(img, slot.querySelector('.cover-badge'));
    const rm = document.createElement('button');
    rm.type = 'button'; rm.className = 'slot-rm'; rm.textContent = '✕';
    rm.onclick = deleteMainImg;
    slot.insertBefore(rm, slot.querySelector('.cover-badge'));
    document.getElementById('del-main-img').value = '0';
    formDirty = true;
});

document.getElementById('slot-0')?.querySelector('.slot-label')
    ?.addEventListener('click', () => document.getElementById('main_img_input').click());

// Gallery existing: mark delete
function markDelGallery(id) {
    const slot   = document.getElementById('slot-ex-' + id);
    const inp    = document.getElementById('del-gal-' + id);
    const capRow = document.getElementById('cap-ex-row-' + id);
    if (!slot || !inp) return;
    inp.disabled = false;
    inp.value = id;
    slot.classList.add('deleted');
    if (capRow) capRow.style.opacity = '.2';
    formDirty = true;
}

// New gallery files
function syncNewFiles() {
    const dt = new DataTransfer();
    newFiles.forEach(f => dt.items.add(f));
    document.getElementById('gallery_input').files = dt.files;
}

function renderNewSlots() {
    for (let i = 0; i < MAX_NEW_FILES; i++) {
        const slot   = document.getElementById('new-slot-' + i);
        const capRow = document.getElementById('new-cap-row-' + i);
        if (!slot) continue;
        const file  = newFiles[i];
        const label = slot.querySelector('.slot-label');
        slot.querySelector('img')?.remove();
        slot.querySelector('.slot-rm')?.remove();
        if (file) {
            slot.classList.add('has-img');
            if (label) label.style.display = 'none';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            slot.insertBefore(img, slot.firstChild);
            const rm = document.createElement('button');
            rm.type = 'button'; rm.className = 'slot-rm'; rm.textContent = '✕';
            rm.onclick = (function(idx){ return function(){ removeNewFile(idx); }; })(i);
            slot.appendChild(rm);
        } else {
            slot.classList.remove('has-img');
            if (label) label.style.display = '';
        }
        if (capRow) capRow.style.opacity = file ? '1' : '.3';
    }
}

function addNewFiles(files) {
    for (const f of files) {
        if (newFiles.length >= MAX_NEW_FILES) break;
        if (!['image/jpeg','image/png','image/webp'].includes(f.type)) continue;
        if (f.size > 10 * 1024 * 1024) continue;
        newFiles.push(f);
    }
    renderNewSlots();
    syncNewFiles();
    formDirty = true;
}

function removeNewFile(i) {
    newFiles.splice(i, 1);
    renderNewSlots();
    syncNewFiles();
    formDirty = true;
}

// Click empty new slot → picker
for (let ni = 0; ni < MAX_NEW_FILES; ni++) {
    document.getElementById('new-slot-' + ni)?.querySelector('.slot-label')
        ?.addEventListener('click', () => document.getElementById('picker_input').click());
}

// Picker input
document.getElementById('picker_input').addEventListener('change', function() {
    addNewFiles(Array.from(this.files));
    this.value = '';
});

// Dropzone
const _dz = document.getElementById('img-dropzone');
_dz.addEventListener('click', () => document.getElementById('picker_input').click());
_dz.addEventListener('dragover',  e => { e.preventDefault(); _dz.classList.add('drag-over'); });
_dz.addEventListener('dragleave', ()  => _dz.classList.remove('drag-over'));
_dz.addEventListener('drop', e => {
    e.preventDefault();
    _dz.classList.remove('drag-over');
    addNewFiles(Array.from(e.dataTransfer.files));
});

// Save
async function doSave() {
    syncQuill();
    const errEl = document.getElementById('msg-error');
    errEl.style.display = 'none';
    const btn = IS_MODAL ? document.getElementById('btn-action') : document.getElementById('btn-save-main');
    const origInner = btn?.innerHTML;
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:15px;animation:spin 1s linear infinite;">progress_activity</span> กำลังบันทึก...'; }
    try {
        const res = await fetch(window.location.href, { method:'POST', body: new FormData(document.getElementById('article-form')) });
        const data = await res.json();
        if (data.status === 'success') {
            if (data.modal) { window.parent.postMessage('article-saved','*'); return; }
            window.location.href = data.redirect;
        } else {
            errEl.textContent = data.message || 'เกิดข้อผิดพลาด';
            errEl.style.display = 'block';
            if (btn) { btn.disabled = false; btn.innerHTML = origInner; }
        }
    } catch(e) {
        errEl.textContent = 'ไม่สามารถเชื่อมต่อได้';
        errEl.style.display = 'block';
        if (btn) { btn.disabled = false; btn.innerHTML = origInner; }
    }
}
document.getElementById('btn-save-main')?.addEventListener('click', doSave);

function safeClose() {
    if (!formDirty) { window.parent.closeArticleModal(); return; }
    document.getElementById('rp-confirm')?.classList.add('show');
}
function closeConfirm() { document.getElementById('rp-confirm')?.classList.remove('show'); }
function confirmClose() { formDirty = false; window.parent.closeArticleModal(); }


const style = document.createElement('style');
style.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(style);
</script>

<?php if (!$isModal): ?>
<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
<?php endif; ?>
