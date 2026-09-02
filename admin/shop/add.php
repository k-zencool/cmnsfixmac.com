<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_perms(['content.write']); // เพิ่มสินค้า shop: หน้าร้าน+ ขึ้นไป

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$isModal = !empty($_GET['modal']);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

// ── Load inventory item ───────────────────────────────────────
$inv_id = (int)($_GET['inv_id'] ?? 0);
if (!$inv_id) { header('Location: index.php'); exit; }

$inv = $pdo->prepare("SELECT * FROM inventory WHERE id = ? AND type = 'sale'");
$inv->execute([$inv_id]);
$inv = $inv->fetch(PDO::FETCH_ASSOC);
if (!$inv) { header('Location: index.php'); exit; }

// Already listed?
$already = (int)$pdo->prepare("SELECT COUNT(*) FROM shop_listings WHERE inventory_id = ?")->execute([$inv_id])
    ? (int)$pdo->query("SELECT COUNT(*) FROM shop_listings WHERE inventory_id = $inv_id")->fetchColumn() : 0;
if ($already) { $_SESSION['flash'] = 'สินค้านี้อยู่ในร้านแล้ว'; header('Location: index.php'); exit; }

$categories = $pdo->query("SELECT * FROM shop_categories ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

// ── Image helpers ─────────────────────────────────────────────
function shop_process_webp(string $tmp, string $dest, int $maxW = 1200, int $q = 82) {
    $info = @getimagesize($tmp); if (!$info) return false;
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
        imagedestroy($src); $src = $dst; $w = $maxW; $h = $nh;
    }
    $ok = imagewebp($src, $dest, $q); imagedestroy($src);
    if (!$ok) return false;
    return ['w' => $w, 'h' => $h, 'size' => filesize($dest)];
}
function shop_upload_slot(): array {
    $ym = date('Y/m');
    $dir = __DIR__ . '/../../uploads/shop/' . $ym;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(8)) . '.webp';
    return [$dir . '/' . $name, '/uploads/shop/' . $ym . '/' . $name];
}
function shop_mime_ok(string $tmp): bool {
    return in_array((new finfo(FILEINFO_MIME_TYPE))->file($tmp), ['image/jpeg','image/png','image/webp'], true);
}
function shop_make_slug(string $s): string {
    $s = strtolower(preg_replace('/[^\x00-\x7F]/u', '', $s));
    $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
    return trim(preg_replace('/[\s-]+/', '-', $s), '-') ?: ('shop-' . date('Ymd') . '-' . substr(uniqid(), -5));
}

// ── POST handler ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo json_encode(['status'=>'error','message'=>'CSRF ไม่ถูกต้อง']); exit;
    }
    try {
        $pdo->beginTransaction();

        $category_id   = (int)($_POST['category_id'] ?? 0);
        $title         = trim($_POST['title'] ?? '');
        $price         = (float)($_POST['price'] ?? 0);
        $price_original= ($_POST['price_original'] !== '') ? (float)$_POST['price_original'] : null;
        $status        = in_array($_POST['status']??'', ['draft','published','reserved','sold']) ? $_POST['status'] : 'draft';
        $description   = $_POST['description'] ?? '';
        $description_en= $_POST['description_en'] ?? '';

        if (!$category_id) throw new Exception('กรุณาเลือกหมวดหมู่');
        if ($price <= 0)   throw new Exception('กรุณากรอกราคา');

        // Auto-generate slug
        $base_slug = shop_make_slug($title ?: $inv['name']);
        $slug = $base_slug;
        $n = 1;
        while ($pdo->query("SELECT COUNT(*) FROM shop_listings WHERE slug='$slug'")->fetchColumn()) {
            $slug = $base_slug . '-' . (++$n);
        }

        // Process images
        $images = [];
        $files = $_FILES['images'] ?? [];
        if (!empty($files['tmp_name'])) {
            foreach ($files['tmp_name'] as $i => $tmp) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                if (count($images) >= 8) break;
                if ($files['size'][$i] > 10*1024*1024) continue;
                if (!is_uploaded_file($tmp)) continue;
                if (!shop_mime_ok($tmp)) continue;
                [$destPath, $destUrl] = shop_upload_slot();
                $dims = shop_process_webp($tmp, $destPath);
                if (!$dims) continue;
                $images[] = ['url'=>$destUrl, 'w'=>$dims['w'], 'h'=>$dims['h']];
            }
        }

        $cover     = $images[0]['url'] ?? null;
        $cover_w   = $images[0]['w']   ?? null;
        $cover_h   = $images[0]['h']   ?? null;

        // Insert listing
        $pdo->prepare("INSERT INTO shop_listings
            (inventory_id, category_id, slug, title, description, description_en,
             price, price_original, cover_image, cover_w, cover_h, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
          ->execute([$inv_id, $category_id, $slug,
            $title ?: null, $description ?: null, $description_en ?: null,
            $price, $price_original, $cover, $cover_w, $cover_h, $status]);
        $lid = (int)$pdo->lastInsertId();

        // Insert images
        foreach ($images as $ord => $img) {
            $pdo->prepare("INSERT INTO shop_images (listing_id, url, sort_order, is_cover, width, height) VALUES (?,?,?,?,?,?)")
              ->execute([$lid, $img['url'], $ord, $ord===0?1:0, $img['w'], $img['h']]);
        }

        $pdo->commit();
        echo json_encode(['status'=>'success','redirect'=>'index.php','modal'=>$isModal]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

$pageTitle = 'เพิ่มสินค้าลงร้าน';
$specs = array_filter([
    $inv['cpu_spec'], $inv['ram_spec'], $inv['storage_spec'], $inv['gpu_spec'],
]);
$grade = $inv['condition_grade'] ?? '';
$gc = str_starts_with($grade,'A') ? '#065f46:#d1fae5' : (str_starts_with($grade,'B') ? '#92400e:#fef3c7' : '#991b1b:#fee2e2');
[$gc_text, $gc_bg] = explode(':', $gc);

// Auto-map parts_categories → shop_categories
$_cat_map = [1=>1, 2=>2, 3=>3, 4=>4, 5=>5, 6=>4, 7=>5, 8=>7, 9=>6];
$_auto_cat = $_cat_map[(int)($inv['category_id'] ?? 0)] ?? 0;
if ($isModal): ?>
<!DOCTYPE html><html lang="th">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('admin_theme')||'dark');</script>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
<link rel="stylesheet" href="/admin/templates/assets/css/admin.css?v=<?= asset_ver('/admin/templates/assets/css/admin.css') ?>">
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
</head>
<body class="modal-mode">
<?php else: include __DIR__ . '/../templates/header_admin.php'; endif; ?>
<style>
html,body{margin:0;padding:0;}
.modal-mode{height:100vh;overflow:hidden;display:flex;flex-direction:column;background:var(--bg-surface);}
#ifrm-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);background:var(--bg-surface);flex-shrink:0;}
#ifrm-header h2{margin:0;font-size:15px;font-weight:700;color:var(--text-main);}
#ifrm-body{flex:1;overflow:hidden;display:flex;flex-direction:column;}
#ifrm-footer{flex-shrink:0;display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-surface);}
.modal-mode .rp-wrap{flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden;padding:0;max-width:100%;margin:0;}
.modal-mode #shop-form{flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden;}
.modal-mode .tab-nav{flex-shrink:0;padding:10px 20px 0;}
.modal-mode .tab-pane{display:none;}
.modal-mode .tab-pane.active{flex:1;overflow-y:auto;padding:16px 20px 8px;}
.tab-nav{display:flex;gap:3px;background:var(--bg-surface-alt);border:1px solid var(--border);border-radius:12px;padding:4px;}
.tab-btn{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;padding:9px 6px;border:none;background:transparent;cursor:pointer;color:var(--text-muted);font-family:inherit;font-size:11px;font-weight:600;transition:.15s;border-radius:9px;line-height:1.3;}
.tab-btn:hover:not(.active){background:var(--bg-surface);}
.tab-btn.active{background:var(--primary);color:#fff;}
.tab-btn.done{color:#10b981;}
.tab-step{width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;background:var(--border);color:var(--text-muted);flex-shrink:0;}
.tab-btn.active .tab-step{background:rgba(255,255,255,.25);color:#fff;}
.tab-btn.done .tab-step{background:#10b98120;color:#10b981;}
.tab-pane{display:none;}.tab-pane.active{display:block;}
.rp-wrap{max-width:900px;margin:0 auto;padding:32px 24px 60px;}
.rp-card{background:var(--bg-surface);border:1px solid var(--border);border-radius:12px;padding:20px 24px;margin-bottom:16px;}
.rp-card-title{font-size:13px;font-weight:700;color:var(--primary);margin-bottom:16px;display:flex;align-items:center;gap:6px;}
.rp-card-title .material-symbols-rounded{font-size:17px;}
.rp-label{font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:5px;display:block;text-transform:uppercase;letter-spacing:.4px;}
.rp-input,.rp-select,.rp-textarea{width:100%;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-main);font-size:14px;outline:none;font-family:'Sarabun',sans-serif;box-sizing:border-box;}
.rp-input:focus,.rp-select:focus,.rp-textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.rp-textarea{resize:vertical;min-height:100px;}
.rp-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.rp-field{margin-bottom:14px;}
.rp-hint{font-size:11px;color:var(--text-muted);margin-top:4px;}
.btn-save{display:inline-flex;align-items:center;gap:6px;padding:10px 24px;border-radius:8px;background:var(--primary);color:#fff;font-size:14px;font-weight:700;border:none;cursor:pointer;font-family:'Sarabun',sans-serif;transition:opacity .2s;}
.btn-save:hover{opacity:.88;}
.btn-cancel{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border-radius:8px;background:transparent;color:var(--text-muted);font-size:14px;font-weight:600;border:1px solid var(--border);cursor:pointer;font-family:'Sarabun',sans-serif;text-decoration:none;}

/* Inventory info card */
.inv-card{background:var(--bg-surface-alt);border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:14px;}
.inv-img{width:64px;height:64px;border-radius:8px;object-fit:cover;border:1px solid var(--border);background:var(--bg-surface);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;}
.inv-img img{width:100%;height:100%;object-fit:cover;}
.inv-img .material-symbols-rounded{font-size:26px;color:var(--text-muted);}

/* Slots */
.img-slots{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-top:4px;}
.img-slot{aspect-ratio:1;border:2px dashed var(--border);border-radius:10px;position:relative;overflow:hidden;background:var(--bg-surface-alt);}
.img-slot.has-img{border-style:solid;border-color:var(--primary);}
.img-slot img{width:100%;height:100%;object-fit:cover;}
.slot-label{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-muted);font-size:11px;gap:4px;cursor:pointer;}
.slot-rm{position:absolute;top:4px;right:4px;width:20px;height:20px;background:#ef4444;color:#fff;border-radius:50%;border:none;cursor:pointer;font-size:12px;display:none;align-items:center;justify-content:center;line-height:1;}
.img-slot.has-img .slot-rm{display:flex;}
.cover-badge{position:absolute;bottom:4px;left:4px;background:#10b981;color:#fff;font-size:9px;font-weight:700;padding:2px 5px;border-radius:4px;}
.img-dropzone{border:2px dashed var(--border);border-radius:10px;padding:18px;text-align:center;color:var(--text-muted);cursor:pointer;transition:all .2s;margin-top:10px;}
.img-dropzone.drag-over{border-color:var(--primary);background:rgba(37,99,235,.05);}
.msg-error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#dc2626;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px;}
/* subtabs */
.subtab-nav{display:flex;gap:8px;margin-bottom:14px;}
.subtab-btn{padding:5px 14px;border-radius:6px;border:1px solid var(--border);font-size:13px;font-weight:600;cursor:pointer;color:var(--text-muted);background:transparent;font-family:'Sarabun',sans-serif;}
.subtab-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);}
.subtab-pane{display:none;}.subtab-pane.active{display:block;}
</style>

<?php if ($isModal): ?>
<div id="ifrm-header">
    <h2><span class="material-symbols-rounded" style="font-size:17px;vertical-align:-3px;">add_circle</span> เพิ่มสินค้าลงร้าน</h2>
    <button type="button" onclick="window.parent.postMessage('shop-close','*')"
            style="background:var(--bg-surface-alt);border:1px solid var(--border);width:34px;height:34px;border-radius:9px;cursor:pointer;color:var(--text-muted);display:flex;align-items:center;justify-content:center;padding:0;"
            onmouseover="this.style.background='#ef4444';this.style.color='#fff'" onmouseout="this.style.background='var(--bg-surface-alt)';this.style.color='var(--text-muted)'">
        <span class="material-symbols-rounded" style="font-size:18px;">close</span>
    </button>
</div>
<div id="ifrm-body">
<?php endif; ?>
<div class="rp-wrap">
<?php if (!$isModal): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <h1 style="margin:0;font-size:20px;font-weight:700;display:flex;align-items:center;gap:8px;">
        <span class="material-symbols-rounded" style="color:var(--primary);">add_circle</span> เพิ่มสินค้าลงร้าน
    </h1>
    <a href="index.php" class="btn-cancel">
        <span class="material-symbols-rounded" style="font-size:14px;">arrow_back</span> กลับ
    </a>
</div>
<?php endif; ?>

<!-- Inventory item card -->
<div class="inv-card">
    <div class="inv-img">
        <?php if ($inv['image']): ?>
        <img src="<?= h($inv['image']) ?>" alt="">
        <?php else: ?>
        <span class="material-symbols-rounded">laptop_mac</span>
        <?php endif; ?>
    </div>
    <div style="flex:1;min-width:0;">
        <div style="font-weight:700;font-size:15px;"><?= h($inv['name']) ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">SKU: <?= h($inv['sku']) ?></div>
        <?php if ($specs): ?>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= h(implode(' · ', $specs)) ?></div>
        <?php endif; ?>
    </div>
    <div style="text-align:right;flex-shrink:0;">
        <?php if ($grade): ?>
        <span style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:800;background:<?= $gc_bg ?>;color:<?= $gc_text ?>;">Grade <?= h($grade) ?></span>
        <?php endif; ?>
        <div style="font-size:13px;color:var(--text-muted);margin-top:6px;">ราคาในคลัง: ฿<?= number_format($inv['sell_price']) ?></div>
    </div>
</div>

<div id="msg-error" class="msg-error" style="display:none;"></div>

<form id="shop-form" method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">

<?php if ($isModal): ?>
<div class="tab-nav">
    <button type="button" class="tab-btn active" onclick="gotoTab(1)" id="tn-1">
        <div class="tab-step">1</div><span>ข้อมูล</span>
    </button>
    <button type="button" class="tab-btn" onclick="gotoTab(2)" id="tn-2">
        <div class="tab-step">2</div><span>รูปภาพ</span>
    </button>
    <button type="button" class="tab-btn" onclick="gotoTab(3)" id="tn-3">
        <div class="tab-step">3</div><span>คำอธิบาย</span>
    </button>
</div>
<div id="tab-1" class="tab-pane active">
<?php endif; ?>

<!-- ข้อมูลหลัก -->
<div class="rp-card">
    <div class="rp-card-title"><span class="material-symbols-rounded">tune</span> ข้อมูลหลัก</div>
    <div class="rp-grid">
        <div class="rp-field">
            <label class="rp-label">หมวดหมู่ร้านค้า <span style="color:#ef4444">*</span></label>
            <select name="category_id" class="rp-select" required>
                <option value="">-- เลือกหมวด --</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $_auto_cat == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="rp-field">
            <label class="rp-label">สถานะ</label>
            <select name="status" class="rp-select">
                <option value="published">เผยแพร่ทันที</option>
                <option value="draft">Draft (ซ่อน)</option>
            </select>
        </div>
    </div>
    <div class="rp-field">
        <label class="rp-label">ชื่อสินค้า (ปล่อยว่างเพื่อใช้ชื่อจากคลัง)</label>
        <input type="text" name="title" class="rp-input" placeholder="<?= h($inv['name']) ?>">
    </div>
    <div class="rp-grid">
        <div class="rp-field">
            <label class="rp-label">ราคาขาย (฿) <span style="color:#ef4444">*</span></label>
            <input type="number" name="price" class="rp-input" value="<?= $inv['sell_price'] ?>" min="0" step="1" required>
        </div>
        <div class="rp-field">
            <label class="rp-label">ราคาเดิม (฿) — ขีดทับ (ถ้ามี)</label>
            <input type="number" name="price_original" class="rp-input" min="0" step="1" placeholder="ปล่อยว่างถ้าไม่มี">
        </div>
    </div>
</div>

<?php if ($isModal): ?></div><!-- /tab-1 -->
<div id="tab-2" class="tab-pane">
<?php endif; ?>

<!-- รูปภาพ -->
<div class="rp-card">
    <div class="rp-card-title"><span class="material-symbols-rounded" style="color:#f59e0b;">photo_library</span> รูปภาพ (สูงสุด 8 รูป — รูปแรก = ปก)</div>
    <div class="img-slots" id="img-slots">
        <?php for ($i = 0; $i < 8; $i++): ?>
        <div class="img-slot" id="slot-<?= $i ?>">
            <div class="slot-label">
                <span class="material-symbols-rounded" style="font-size:20px;">add_photo_alternate</span>
                <span><?= $i === 0 ? 'ปก' : ($i + 1) ?></span>
            </div>
            <button type="button" class="slot-rm" onclick="removeSlot(<?= $i ?>)">✕</button>
            <?php if ($i === 0): ?><div class="cover-badge">ปก</div><?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
    <div class="img-dropzone" id="img-dropzone">
        <span class="material-symbols-rounded" style="font-size:26px;display:block;margin-bottom:5px;">cloud_upload</span>
        คลิกหรือลากรูปมาใส่ · JPG, PNG, WebP · ระบบแปลง WebP อัตโนมัติ
    </div>
    <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/webp" style="display:none;">
    <input type="file" id="picker_input" multiple accept="image/jpeg,image/png,image/webp" style="display:none;">
    <p class="rp-hint" style="margin-top:8px;">สูงสุด 10MB/รูป · resize เป็น WebP ก่อนบันทึก</p>
</div>

<?php if ($isModal): ?></div><!-- /tab-2 -->
<div id="tab-3" class="tab-pane">
<?php endif; ?>

<!-- คำอธิบาย -->
<div class="rp-card">
    <div class="rp-card-title"><span class="material-symbols-rounded">article</span> คำอธิบายสินค้า</div>
    <div class="subtab-nav">
        <button type="button" class="subtab-btn active" onclick="gotoSub(1)">ภาษาไทย</button>
        <button type="button" class="subtab-btn" onclick="gotoSub(2)">English</button>
    </div>
    <div id="sub-1" class="subtab-pane active">
        <div class="rp-editor" id="editor-th"></div>
        <input type="hidden" name="description" id="desc-th-val">
    </div>
    <div id="sub-2" class="subtab-pane">
        <div class="rp-editor" id="editor-en"></div>
        <input type="hidden" name="description_en" id="desc-en-val">
    </div>
</div>

<?php if ($isModal): ?>
</div><!-- /tab-3 -->
<?php else: ?>
<div style="display:flex;gap:10px;justify-content:flex-end;padding-top:8px;">
    <a href="index.php" class="btn-cancel">ยกเลิก</a>
    <button type="button" class="btn-save" id="btn-save">
        <span class="material-symbols-rounded" style="font-size:15px;">save</span> บันทึกลงร้านค้า
    </button>
</div>
<?php endif; ?>
</form>
</div>
<?php if ($isModal): ?>
</div><!-- #ifrm-body -->
<div id="ifrm-footer">
    <button type="button" onclick="window.parent.postMessage('shop-close','*')" class="btn-cancel">
        <span class="material-symbols-rounded" style="font-size:14px;">close</span> ยกเลิก
    </button>
    <div style="display:flex;gap:8px;">
        <button type="button" id="btn-prev" class="btn-cancel" onclick="prevTab()" style="visibility:hidden;display:inline-flex;align-items:center;gap:6px;">
            <span class="material-symbols-rounded" style="font-size:14px;">arrow_back</span> ก่อนหน้า
        </button>
        <button type="button" id="btn-action" class="btn-save" onclick="nextTab()" style="display:inline-flex;align-items:center;gap:6px;min-width:130px;justify-content:center;">
            <span id="btn-action-icon" class="material-symbols-rounded" style="font-size:15px;">arrow_forward</span>
            <span id="btn-action-text">ถัดไป</span>
        </button>
    </div>
</div>
<?php endif; ?>
<?php if (!$isModal): ?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<?php endif; ?>
<style>
.rp-editor{border:1px solid var(--border);border-radius:8px;overflow:hidden;}
.rp-editor .ql-toolbar.ql-snow{border:none;border-bottom:1px solid var(--border);background:var(--bg-surface-alt);padding:6px 10px;}
.rp-editor .ql-container.ql-snow{border:none;}
.rp-editor .ql-editor{min-height:140px;padding:10px 12px;line-height:1.7;font-family:'Sarabun',sans-serif;font-size:14px;color:var(--text-main);}
[data-theme="dark"] .rp-editor .ql-toolbar.ql-snow .ql-stroke{stroke:#9ca3af;}
[data-theme="dark"] .rp-editor .ql-toolbar.ql-snow .ql-fill{fill:#9ca3af;}
[data-theme="dark"] .rp-editor .ql-editor{color:var(--text-main);}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
const _isModal = <?= $isModal ? 'true' : 'false' ?>;
// ── Quill (lazy — init on tab 3) ──────────────────────────────
const QB = [[{header:[2,3,false]}],['bold','italic','underline'],[{list:'ordered'},{list:'bullet'}],['link'],['clean']];
let qTh = null, qEn = null, _quillInited = false;
function initQuill() {
    if (_quillInited) return;
    _quillInited = true;
    qTh = new Quill('#editor-th', {theme:'snow', placeholder:'คำอธิบายสินค้าภาษาไทย...', modules:{toolbar:QB}});
    qEn = new Quill('#editor-en', {theme:'snow', placeholder:'Product description in English...', modules:{toolbar:QB}});
}
if (!_isModal) initQuill(); // non-modal: init immediately

function gotoSub(n) {
    document.querySelectorAll('.subtab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('sub-'+n)?.classList.add('active');
    document.querySelectorAll('.subtab-btn').forEach((b,i) => b.classList.toggle('active', i+1===n));
    if (n===2) qEn?.update?.();
}

// ── Tabs (modal only) ─────────────────────────────────────────
let _curTab = 1;
const TOTAL_TABS = 3;
function gotoTab(n) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-'+n)?.classList.add('active');
    document.querySelectorAll('.tab-btn').forEach((b,i) => {
        b.classList.remove('active','done');
        if (i+1===n) b.classList.add('active');
        else if (i+1<n) b.classList.add('done');
    });
    const prev = document.getElementById('btn-prev');
    const act  = document.getElementById('btn-action');
    const icon = document.getElementById('btn-action-icon');
    const text = document.getElementById('btn-action-text');
    if (prev) prev.style.visibility = n > 1 ? 'visible' : 'hidden';
    if (n === TOTAL_TABS) {
        initQuill();
        if (act)  { act.onclick = doSave; }
        if (icon) icon.textContent = 'save';
        if (text) text.textContent = 'บันทึก';
    } else {
        if (act)  { act.onclick = nextTab; }
        if (icon) icon.textContent = 'arrow_forward';
        if (text) text.textContent = 'ถัดไป';
    }
    _curTab = n;
}
function nextTab() { if (_curTab < TOTAL_TABS) gotoTab(_curTab + 1); }
function prevTab() { if (_curTab > 1) gotoTab(_curTab - 1); }

// ── Image slots ───────────────────────────────────────────────
const MAX_SLOTS = 8;
let imageFiles = new Array(MAX_SLOTS).fill(null);

function renderSlots() {
    for (let i = 0; i < MAX_SLOTS; i++) {
        const slot = document.getElementById('slot-'+i);
        if (!slot) continue;
        const label = slot.querySelector('.slot-label');
        const file  = imageFiles[i];
        slot.querySelector('img')?.remove();
        if (file) {
            slot.classList.add('has-img');
            if (label) label.style.display = 'none';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            slot.insertBefore(img, slot.firstChild);
        } else {
            slot.classList.remove('has-img');
            if (label) label.style.display = '';
        }
    }
    syncFiles();
}

function syncFiles() {
    const dt = new DataTransfer();
    imageFiles.forEach(f => { if (f) dt.items.add(f); });
    document.getElementById('images').files = dt.files;
}

function addFiles(files) {
    for (const f of files) {
        if (!['image/jpeg','image/png','image/webp'].includes(f.type)) continue;
        if (f.size > 10*1024*1024) continue;
        const idx = imageFiles.indexOf(null);
        if (idx === -1) break;
        imageFiles[idx] = f;
    }
    renderSlots();
}

function removeSlot(i) {
    imageFiles.splice(i, 1); imageFiles.push(null);
    renderSlots();
}

for (let i = 0; i < MAX_SLOTS; i++) {
    document.getElementById('slot-'+i)?.querySelector('.slot-label')
        ?.addEventListener('click', () => document.getElementById('picker_input').click());
}
document.getElementById('picker_input').addEventListener('change', function() {
    addFiles(Array.from(this.files)); this.value = '';
});
const dz = document.getElementById('img-dropzone');
dz.addEventListener('click', () => document.getElementById('picker_input').click());
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
dz.addEventListener('drop', e => { e.preventDefault(); dz.classList.remove('drag-over'); addFiles(Array.from(e.dataTransfer.files)); });

// ── Save ──────────────────────────────────────────────────────
async function doSave() {
    if (qTh) document.getElementById('desc-th-val').value = qTh.root.innerHTML;
    if (qEn) document.getElementById('desc-en-val').value = qEn.root.innerHTML;
    const errEl = document.getElementById('msg-error');
    errEl.style.display = 'none';
    const btn = document.getElementById(_isModal ? 'btn-action' : 'btn-save');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:15px;animation:spin 1s linear infinite;">progress_activity</span> กำลังบันทึก...'; }
    try {
        const res = await fetch('', {method:'POST', body: new FormData(document.getElementById('shop-form'))});
        const data = await res.json();
        if (data.status === 'success') {
            if (data.modal) { window.parent.postMessage('shop-saved', '*'); return; }
            window.location.href = data.redirect;
        } else {
            errEl.textContent = data.message || 'เกิดข้อผิดพลาด';
            errEl.style.display = 'block';
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = _isModal
                    ? '<span id="btn-action-icon" class="material-symbols-rounded" style="font-size:15px;">save</span><span id="btn-action-text">บันทึก</span>'
                    : '<span class="material-symbols-rounded" style="font-size:15px;">save</span> บันทึกลงร้านค้า';
            }
        }
    } catch(e) {
        errEl.textContent = 'ไม่สามารถเชื่อมต่อได้';
        errEl.style.display = 'block';
        if (btn) btn.disabled = false;
    }
}
document.getElementById('btn-save')?.addEventListener('click', doSave);
if (_isModal) {
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') window.parent.postMessage('shop-close', '*');
    });
}
</script>
<?php if ($isModal): ?></body></html><?php else: include __DIR__ . '/../templates/footer_admin.php'; endif; ?>
