<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

const MAX_IMG_SIZE  = 10 * 1024 * 1024;
const MAX_IMGS      = 5;
const IMG_MAX_W     = 1200;
const IMG_QUALITY   = 82;
$ALLOWED_MIME = ['image/jpeg','image/png','image/webp'];

$CATEGORIES = ['MacBook','iPhone','iPad','iMac','MacMini','AirPods','AppleWatch','Adapter','Other'];

function make_slug(string $s): string {
    $s = strtolower(preg_replace('/[^\x00-\x7F]/u', '', $s));
    $s = preg_replace('/[^a-z0-9\s-]/', '', $s);
    $s = trim(preg_replace('/[\s-]+/', '-', $s), '-');
    return $s ?: ('repair-' . date('Ymd') . '-' . substr(uniqid(), -5));
}

function process_webp(string $tmp, string $dest, int $maxW = IMG_MAX_W, int $q = IMG_QUALITY): bool {
    $info = @getimagesize($tmp);
    if (!$info) return false;
    [$w, $h, $type] = $info;
    $src = match($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
        default        => false,
    };
    if (!$src) return false;
    if ($w > $maxW) {
        $nh  = (int)round($h * $maxW / $w);
        $dst = imagecreatetruecolor($maxW, $nh);
        imagecopyresampled($dst, $src, 0,0,0,0, $maxW, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }
    $ok = imagewebp($src, $dest, $q);
    imagedestroy($src);
    return $ok;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$repair = $pdo->prepare("SELECT * FROM repairs WHERE id = ?");
$repair->execute([$id]);
$repair = $repair->fetch(PDO::FETCH_ASSOC);
if (!$repair) { header('Location: index.php'); exit; }

// Load existing images from repair_images
$img_stmt = $pdo->prepare("SELECT * FROM repair_images WHERE repair_id = ? ORDER BY sort_order ASC, id ASC");
$img_stmt->execute([$id]);
$existing_images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no repair_images but has legacy repairs.image, show it as a virtual entry
if (empty($existing_images) && !empty($repair['image'])) {
    $existing_images = [['id' => 'legacy', 'image_path' => $repair['image'], 'sort_order' => 0, 'is_cover' => 1, 'caption' => '']];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'CSRF token ไม่ถูกต้อง';
    } else {
        try {
            $tracking_id   = (int)($_POST['tracking_id'] ?? 0) ?: null;
            $title         = trim($_POST['title']       ?? '');
            $title_en      = trim($_POST['title_en']    ?? '');
            $slug          = trim($_POST['slug']        ?? '');
            $meta_desc     = trim($_POST['meta_desc']   ?? '');
            $category      = trim($_POST['category']    ?? '');
            $model         = trim($_POST['model']       ?? '');
            $issue         = trim($_POST['issue']       ?? '');
            $issue_en      = trim($_POST['issue_en']    ?? '');
            $fix_detail    = trim($_POST['fix_detail']  ?? '');
            $fix_detail_en = trim($_POST['fix_detail_en'] ?? '');
            $delete_ids    = array_map('intval', $_POST['delete_images'] ?? []);

            if (!$title)      throw new Exception('กรุณากรอกชื่อผลงาน');
            if (!$category)   throw new Exception('กรุณาเลือกหมวดหมู่');
            if (!$model)      throw new Exception('กรุณากรอกรุ่นเครื่อง');
            if (!$issue)      throw new Exception('กรุณากรอกอาการ');
            if (!$fix_detail) throw new Exception('กรุณากรอกรายละเอียดการซ่อม');

            if (!$slug) $slug = make_slug($title);
            $slug = make_slug($slug);

            // Unique slug check (exclude self)
            $chk = $pdo->prepare("SELECT id FROM repairs WHERE slug = ? AND id != ?");
            $chk->execute([$slug, $id]);
            if ($chk->fetchColumn()) $slug .= '-' . substr(uniqid(), -4);

            $pdo->beginTransaction();

            // Delete marked images
            foreach ($delete_ids as $del_id) {
                if (!$del_id) continue;
                $row = $pdo->prepare("SELECT image_path FROM repair_images WHERE id = ? AND repair_id = ?");
                $row->execute([$del_id, $id]);
                $path = $row->fetchColumn();
                if ($path) {
                    $abs = __DIR__ . '/../../' . ltrim($path, '/');
                    if (file_exists($abs)) @unlink($abs);
                }
                $pdo->prepare("DELETE FROM repair_images WHERE id = ? AND repair_id = ?")->execute([$del_id, $id]);
            }

            // Upload new images
            $current_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM repair_images WHERE repair_id = ?");
            $current_count_stmt->execute([$id]);
            $current_count = (int)$current_count_stmt->fetchColumn();
            $slots_left = MAX_IMGS - $current_count;

            $new_images = [];
            $files = $_FILES['images'] ?? [];
            if (!empty($files['tmp_name']) && $slots_left > 0) {
                $ym = date('Y/m');
                $upload_dir = __DIR__ . '/../../uploads/repairs/' . $ym;
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

                foreach ($files['tmp_name'] as $i => $tmp) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                    if (count($new_images) >= $slots_left) break;
                    if ($files['size'][$i] > MAX_IMG_SIZE) continue;
                    if (!is_uploaded_file($tmp)) continue;
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                    if (!in_array($mime, $ALLOWED_MIME, true)) continue;

                    $fname = bin2hex(random_bytes(8)) . '.webp';
                    $dest  = $upload_dir . '/' . $fname;
                    if (process_webp($tmp, $dest)) {
                        $new_images[] = '/uploads/repairs/' . $ym . '/' . $fname;
                    }
                }
            }

            // Get max sort_order
            $max_ord = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM repair_images WHERE repair_id = ?");
            $max_ord->execute([$id]);
            $sort_base = (int)$max_ord->fetchColumn() + 1;

            foreach ($new_images as $j => $path) {
                $pdo->prepare("INSERT INTO repair_images (repair_id, image_path, sort_order, is_cover) VALUES (?,?,?,0)")
                    ->execute([$id, $path, $sort_base + $j]);
            }

            // Recompute cover
            $first = $pdo->prepare("SELECT id, image_path FROM repair_images WHERE repair_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
            $first->execute([$id]);
            $cover_row = $first->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("UPDATE repair_images SET is_cover = 0 WHERE repair_id = ?")->execute([$id]);
            $cover_path = '';
            if ($cover_row) {
                $pdo->prepare("UPDATE repair_images SET is_cover = 1 WHERE id = ?")->execute([$cover_row['id']]);
                $cover_path = $cover_row['image_path'];
            }

            $pdo->prepare("
                UPDATE repairs SET
                  tracking_id=?, title=?, slug=?, meta_desc=?, model=?, issue=?, fix_detail=?,
                  image=?, category=?, title_en=?, issue_en=?, fix_detail_en=?
                WHERE id=?
            ")->execute([
                $tracking_id, $title, $slug, $meta_desc ?: null, $model, $issue, $fix_detail,
                $cover_path ?: $repair['image'], $category,
                $title_en ?: null, $issue_en ?: null, $fix_detail_en ?: null, $id
            ]);

            $pdo->commit();
            $_SESSION['flash'] = 'บันทึกเรียบร้อยแล้ว';
            header('Location: index.php');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
            // Reload images
            $img_stmt->execute([$id]);
            $existing_images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// Load linked tracking job
$linked_tracking = null;
if ($repair['tracking_id']) {
    $t = $pdo->prepare("SELECT id, ticket_number, customer_name, device_type, device_model, status FROM tracking WHERE id = ?");
    $t->execute([$repair['tracking_id']]);
    $linked_tracking = $t->fetch(PDO::FETCH_ASSOC);
}

$current_img_count = count(array_filter($existing_images, fn($x) => $x['id'] !== 'legacy'));
$slots_available   = MAX_IMGS - $current_img_count;

$pageTitle = 'แก้ไขผลงาน';
include __DIR__ . '/../templates/header_admin.php';
?>
<style>
.rp-wrap{max-width:860px;margin:0 auto;padding:24px 0 60px;}
.rp-card{background:var(--bg-surface,#fff);border:1px solid var(--border,#e5e7eb);border-radius:14px;padding:24px;margin-bottom:20px;}
.rp-card h3{margin:0 0 16px;font-size:15px;font-weight:700;color:var(--text-main,#111);display:flex;align-items:center;gap:8px;}
.rp-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.rp-label{font-size:12px;font-weight:600;color:var(--text-muted,#6b7280);margin-bottom:5px;display:block;text-transform:uppercase;letter-spacing:.4px;}
.rp-input,.rp-select,.rp-textarea{width:100%;padding:9px 12px;border:1.5px solid var(--border,#e5e7eb);border-radius:8px;font-size:14px;background:var(--bg-surface,#fff);color:var(--text-main,#111);box-sizing:border-box;transition:border-color .15s;}
.rp-input:focus,.rp-select:focus,.rp-textarea:focus{outline:none;border-color:var(--primary,#3b82f6);}
.rp-textarea{min-height:100px;resize:vertical;font-family:inherit;}
.rp-hint{font-size:11px;color:var(--text-muted,#9ca3af);margin-top:4px;}
.char-count{float:right;font-size:11px;color:var(--text-muted,#9ca3af);}
.tracking-search-wrap{position:relative;}
.tracking-results{position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid var(--primary,#3b82f6);border-top:none;border-radius:0 0 10px 10px;z-index:50;max-height:240px;overflow-y:auto;box-shadow:0 4px 16px rgba(0,0,0,.1);}
.tracking-item{padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border,#f3f4f6);font-size:13px;}
.tracking-item:hover{background:var(--bg-surface-alt,#f8fafc);}
.tracking-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;background:#3b82f614;border:1px solid #3b82f644;border-radius:8px;font-size:13px;margin-top:10px;width:100%;box-sizing:border-box;}
.tracking-badge .tb-clear{cursor:pointer;color:#ef4444;font-weight:700;margin-left:auto;}
/* Existing images */
.existing-imgs{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px;}
.ex-img-item{position:relative;aspect-ratio:1;border-radius:10px;overflow:hidden;background:#f3f4f6;}
.ex-img-item img{width:100%;height:100%;object-fit:cover;}
.ex-img-item .ex-del{position:absolute;top:4px;right:4px;width:22px;height:22px;background:#ef4444;color:#fff;border-radius:50%;border:2px solid #fff;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;z-index:2;}
.ex-img-item.marked{opacity:.35;}
.ex-img-item .cover-badge{position:absolute;bottom:4px;left:4px;background:#10b981;color:#fff;font-size:9px;font-weight:700;padding:2px 5px;border-radius:4px;}
/* New image slots */
.img-slots{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-top:4px;}
.img-slot{aspect-ratio:1;border:2px dashed var(--border,#e5e7eb);border-radius:10px;position:relative;overflow:hidden;background:var(--bg-surface-alt,#f9fafb);}
.img-slot.has-img{border-style:solid;border-color:var(--primary,#3b82f6);}
.img-slot img{width:100%;height:100%;object-fit:cover;}
.img-slot .slot-label{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-muted,#9ca3af);font-size:11px;gap:4px;cursor:pointer;}
.img-slot .slot-rm{position:absolute;top:4px;right:4px;width:20px;height:20px;background:#ef4444;color:#fff;border-radius:50%;border:none;cursor:pointer;font-size:12px;display:none;align-items:center;justify-content:center;}
.img-slot.has-img .slot-rm{display:flex;}
.img-dropzone{border:2px dashed var(--border,#e5e7eb);border-radius:10px;padding:16px;text-align:center;color:var(--text-muted,#9ca3af);cursor:pointer;transition:all .2s;margin-top:10px;}
.img-dropzone.drag-over{border-color:var(--primary,#3b82f6);background:#3b82f608;}
.rp-actions{display:flex;align-items:center;gap:12px;padding-top:4px;}
.btn-save{padding:11px 28px;background:var(--primary,#3b82f6);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;}
.btn-save:hover{opacity:.9;}
.btn-cancel{padding:11px 20px;background:none;border:1.5px solid var(--border,#e5e7eb);border-radius:9px;font-size:14px;color:var(--text-main,#111);cursor:pointer;text-decoration:none;}
.msg-error{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;}
</style>

<div class="rp-wrap">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <a href="index.php" style="color:var(--text-muted);text-decoration:none;font-size:13px;">← กลับ</a>
    <h2 style="margin:0;font-size:20px;">แก้ไขผลงาน #<?= $id ?></h2>
  </div>

  <?php if ($error): ?><div class="msg-error"><?= h($error) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="repair-form">
    <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
    <input type="hidden" name="tracking_id" id="tracking_id_input" value="<?= h($repair['tracking_id'] ?? '') ?>">

    <!-- Tracking -->
    <div class="rp-card">
      <h3><span class="material-symbols-rounded" style="font-size:18px;color:#3b82f6;">link</span> ผูกกับงานซ่อม</h3>
      <?php if ($linked_tracking): ?>
        <div id="tracking_selected">
          <div class="tracking-badge">
            <span class="material-symbols-rounded" style="font-size:16px;color:#3b82f6;">task_alt</span>
            <div>
              <div style="font-weight:700;font-size:13px;"><?= h($linked_tracking['ticket_number']) ?></div>
              <div style="font-size:12px;color:var(--text-muted);"><?= h($linked_tracking['device_type'].' '.($linked_tracking['device_model']??'')) ?> · <?= h($linked_tracking['customer_name']??'') ?></div>
            </div>
            <button type="button" class="tb-clear" onclick="clearTracking()">✕</button>
          </div>
        </div>
        <div id="tracking_search_wrap" style="display:none;">
      <?php else: ?>
        <div id="tracking_selected" style="display:none;"></div>
        <div id="tracking_search_wrap">
      <?php endif; ?>
          <div class="tracking-search-wrap">
            <input type="text" id="tracking_search" class="rp-input" placeholder="🔍 ค้นหา ticket / ชื่อลูกค้า (เฉพาะงานที่เสร็จแล้ว)" autocomplete="off">
            <div class="tracking-results" id="tracking_results" style="display:none;"></div>
          </div>
        </div>
    </div>

    <!-- Images -->
    <div class="rp-card">
      <h3><span class="material-symbols-rounded" style="font-size:18px;color:#f59e0b;">photo_library</span> รูปภาพ (สูงสุด <?= MAX_IMGS ?> รูป)</h3>

      <?php if ($existing_images): ?>
        <p class="rp-label" style="margin-bottom:10px;">รูปปัจจุบัน — กด ✕ เพื่อลบ</p>
        <div class="existing-imgs">
          <?php foreach($existing_images as $img): ?>
            <div class="ex-img-item" id="eximg-<?= h($img['id']) ?>">
              <img src="<?= h($img['image_path']) ?>" alt="" loading="lazy">
              <?php if ($img['is_cover']): ?><div class="cover-badge">ปก</div><?php endif; ?>
              <?php if ($img['id'] !== 'legacy'): ?>
                <button type="button" class="ex-del" onclick="markDelete(<?= (int)$img['id'] ?>)">✕</button>
                <input type="checkbox" name="delete_images[]" id="del-<?= (int)$img['id'] ?>" value="<?= (int)$img['id'] ?>" style="display:none;">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($slots_available > 0): ?>
        <p class="rp-label">เพิ่มรูปใหม่ (เหลือ <?= $slots_available ?> ช่อง)</p>
        <div class="img-slots" id="img-slots">
          <?php for($i=0;$i<$slots_available;$i++): ?>
          <div class="img-slot" id="slot-<?= $i ?>">
            <div class="slot-label">
              <span class="material-symbols-rounded" style="font-size:22px;">add_photo_alternate</span>
              <span>รูปใหม่</span>
            </div>
            <button type="button" class="slot-rm" onclick="removeSlot(<?= $i ?>)">✕</button>
          </div>
          <?php endfor; ?>
        </div>
        <div class="img-dropzone" id="img-dropzone" onclick="document.getElementById('images_input').click()">
          <span class="material-symbols-rounded" style="font-size:24px;display:block;margin-bottom:4px;">cloud_upload</span>
          คลิกหรือลากรูปมาใส่ · ระบบแปลง WebP อัตโนมัติ
        </div>
        <input type="file" id="images_input" name="images[]" multiple accept="image/jpeg,image/png,image/webp" style="display:none;">
      <?php else: ?>
        <p class="rp-hint">รูปเต็มแล้ว (<?= MAX_IMGS ?>/<?= MAX_IMGS ?>) — ลบรูปเก่าก่อนเพื่อเพิ่มใหม่</p>
      <?php endif; ?>
    </div>

    <!-- Title + SEO -->
    <div class="rp-card">
      <h3><span class="material-symbols-rounded" style="font-size:18px;color:#8b5cf6;">title</span> หัวข้อ &amp; SEO</h3>
      <div class="rp-grid" style="margin-bottom:14px;">
        <div>
          <label class="rp-label">ชื่อผลงาน (TH) <span style="color:#ef4444">*</span></label>
          <input type="text" name="title" class="rp-input" value="<?= h($repair['title']) ?>" required>
        </div>
        <div>
          <label class="rp-label">Title (EN)</label>
          <input type="text" name="title_en" class="rp-input" value="<?= h($repair['title_en'] ?? '') ?>">
        </div>
      </div>
      <div class="rp-grid" style="margin-bottom:14px;">
        <div>
          <label class="rp-label">Slug (URL) <span style="color:#ef4444">*</span></label>
          <input type="text" name="slug" class="rp-input" value="<?= h($repair['slug'] ?? '') ?>" placeholder="repair-macbook-screen" pattern="[a-z0-9\-]+" required>
          <p class="rp-hint">ใช้ตัวเล็ก a-z, 0-9, ขีด (-) · URL: /work/<?= h($repair['slug'] ?? 'slug') ?></p>
        </div>
        <div>
          <label class="rp-label">Meta Description <span id="meta_count" class="char-count"><?= strlen($repair['meta_desc'] ?? '') ?>/160</span></label>
          <input type="text" name="meta_desc" id="meta_input" class="rp-input" value="<?= h($repair['meta_desc'] ?? '') ?>" maxlength="160" oninput="document.getElementById('meta_count').textContent=this.value.length+'/160'">
        </div>
      </div>
      <div class="rp-grid">
        <div>
          <label class="rp-label">หมวดหมู่ <span style="color:#ef4444">*</span></label>
          <select name="category" class="rp-select" required>
            <option value="">-- เลือก --</option>
            <?php foreach($CATEGORIES as $c): ?>
              <option value="<?= h($c) ?>" <?= ($repair['category'] ?? '') === $c ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="rp-label">รุ่นเครื่อง <span style="color:#ef4444">*</span></label>
          <input type="text" name="model" class="rp-input" id="model_input" value="<?= h($repair['model']) ?>" required>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="rp-card">
      <h3><span class="material-symbols-rounded" style="font-size:18px;color:#10b981;">article</span> เนื้อหา</h3>
      <div class="rp-grid" style="margin-bottom:14px;">
        <div>
          <label class="rp-label">อาการ / ปัญหา (TH) <span style="color:#ef4444">*</span></label>
          <textarea name="issue" id="issue_input" class="rp-textarea" required><?= h($repair['issue']) ?></textarea>
        </div>
        <div>
          <label class="rp-label">Problem (EN)</label>
          <textarea name="issue_en" class="rp-textarea"><?= h($repair['issue_en'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="rp-grid">
        <div>
          <label class="rp-label">รายละเอียดการซ่อม (TH) <span style="color:#ef4444">*</span></label>
          <textarea name="fix_detail" class="rp-textarea" style="min-height:140px;" required><?= h($repair['fix_detail']) ?></textarea>
        </div>
        <div>
          <label class="rp-label">Repair Detail (EN)</label>
          <textarea name="fix_detail_en" class="rp-textarea" style="min-height:140px;"><?= h($repair['fix_detail_en'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="rp-actions">
      <button type="submit" class="btn-save">
        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:-3px;">save</span>
        บันทึก
      </button>
      <a href="index.php" class="btn-cancel">ยกเลิก</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
// ── Delete existing image ─────────────────────────────────────
function markDelete(imgId) {
  const item = document.getElementById('eximg-' + imgId);
  const chk  = document.getElementById('del-' + imgId);
  if (!item || !chk) return;
  const marked = chk.checked;
  chk.checked = !marked;
  item.classList.toggle('marked', !marked);
}

// ── Tracking search ───────────────────────────────────────────
const trackResults = document.getElementById('tracking_results');
const trackSel     = document.getElementById('tracking_selected');
const trackWrap    = document.getElementById('tracking_search_wrap');
let searchTimer;

const trackSearch = document.getElementById('tracking_search');
if (trackSearch) {
  trackSearch.addEventListener('input', function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 1) { trackResults.style.display = 'none'; return; }
    searchTimer = setTimeout(() => {
      fetch('api_tracking.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(rows => {
          if (!rows.length) { trackResults.style.display = 'none'; return; }
          trackResults.innerHTML = rows.map(r =>
            `<div class="tracking-item" onclick='selectTracking(${JSON.stringify(r)})'>
              <div style="font-weight:700;color:var(--primary,#3b82f6);">${r.ticket_number} · ${r.device_type} ${r.device_model||''}</div>
              <div style="color:var(--text-muted);font-size:12px;">${r.customer_name||''} · สถานะ: ${r.status}</div>
            </div>`).join('');
          trackResults.style.display = 'block';
        });
    }, 300);
  });
}

document.addEventListener('click', e => {
  if (!e.target.closest('.tracking-search-wrap')) {
    if (trackResults) trackResults.style.display = 'none';
  }
});

function selectTracking(r) {
  document.getElementById('tracking_id_input').value = r.id;
  trackSel.innerHTML = `<div class="tracking-badge">
    <span class="material-symbols-rounded" style="font-size:16px;color:#3b82f6;">task_alt</span>
    <div>
      <div style="font-weight:700;font-size:13px;">${r.ticket_number}</div>
      <div style="font-size:12px;color:var(--text-muted);">${r.device_type} ${r.device_model||''} · ${r.customer_name||''}</div>
    </div>
    <button type="button" class="tb-clear" onclick="clearTracking()">✕</button>
  </div>`;
  trackSel.style.display = 'block';
  if (trackWrap) trackWrap.style.display = 'none';
  if (r.device_model && document.getElementById('model_input'))
    document.getElementById('model_input').value = r.device_model;
  if (r.problem_details && document.getElementById('issue_input') && !document.getElementById('issue_input').value)
    document.getElementById('issue_input').value = r.problem_details;
}

function clearTracking() {
  document.getElementById('tracking_id_input').value = '';
  trackSel.style.display = 'none';
  if (trackSel) trackSel.innerHTML = '';
  if (trackWrap) trackWrap.style.display = 'block';
}

// ── New image upload ──────────────────────────────────────────
const SLOTS_AVAIL = <?= $slots_available ?>;
let imageFiles = [];

function renderSlots() {
  for (let i = 0; i < SLOTS_AVAIL; i++) {
    const slot = document.getElementById('slot-' + i);
    if (!slot) continue;
    const label = slot.querySelector('.slot-label');
    const oldImg = slot.querySelector('img');
    if (oldImg) oldImg.remove();
    const file = imageFiles[i];
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
  syncFileInput();
}

function syncFileInput() {
  const inp = document.getElementById('images_input');
  if (!inp) return;
  const dt = new DataTransfer();
  imageFiles.forEach(f => dt.items.add(f));
  inp.files = dt.files;
}

function addFiles(newFiles) {
  for (const f of newFiles) {
    if (imageFiles.length >= SLOTS_AVAIL) break;
    if (!['image/jpeg','image/png','image/webp'].includes(f.type)) continue;
    if (f.size > 10*1024*1024) continue;
    imageFiles.push(f);
  }
  renderSlots();
}

function removeSlot(i) {
  imageFiles.splice(i, 1);
  renderSlots();
}

for (let i = 0; i < SLOTS_AVAIL; i++) {
  const slot = document.getElementById('slot-' + i);
  if (slot) {
    const lbl = slot.querySelector('.slot-label');
    if (lbl) lbl.addEventListener('click', () => document.getElementById('images_input')?.click());
  }
}

const imagesInp = document.getElementById('images_input');
if (imagesInp) {
  imagesInp.addEventListener('change', function() {
    addFiles(Array.from(this.files));
    this.value = '';
  });
}

const dz = document.getElementById('img-dropzone');
if (dz) {
  dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('drag-over'); });
  dz.addEventListener('dragleave', ()  => dz.classList.remove('drag-over'));
  dz.addEventListener('drop', e => {
    e.preventDefault();
    dz.classList.remove('drag-over');
    addFiles(Array.from(e.dataTransfer.files));
  });
}
</script>
