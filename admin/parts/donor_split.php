<?php

/********************************************************************
 * admin/parts/donor_split.php
 * แยกอะไหล่จากเครื่องซาก (ทีละชิ้น) -> เข้า "อะไหล่มือ 2"
 * - เฮดการ์ด: รูป/รุ่น/ซีเรียล/ทุน/สถานะ  (class เข้ากับ index.php)
 * - ตาราง "รายการที่แยกแล้ว" อยู่ด้านบน (แก้ที่เก็บได้ทีละชิ้น)
 * - ฟอร์มด้านล่างใช้สไตล์เดียวกับ form_used.php
 * - Log เอกสารลง parts_docs(doc_type='USED') + parts_doc_lines(+1)
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_perms(['parts.donor.view']);

$pageTitle = "แยกอะไหล่จากเครื่องซาก (ทีละชิ้น)";

function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function val($arr, $k, $d = '')
{
  return isset($arr[$k]) ? trim((string)$arr[$k]) : $d;
}

define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(PARTS_UPLOAD_DIR)) @mkdir(PARTS_UPLOAD_DIR, 0775, true);

function safeUploadName(string $orig): string
{
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i', '-', pathinfo($orig, PATHINFO_FILENAME));
  if ($base === '') $base = 'used';
  return $base . '-' . date('Ymd_His') . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
}
function img_src_any($v)
{
  $v = trim((string)$v);
  if ($v === '') return '';
  if (preg_match('~^https?://~i', $v) || $v[0] === '/') return $v;
  return '../../uploads/parts/' . $v;  // ไฟล์นี้อยู่ /admin/parts/
}

/* --------------------- STATE --------------------- */
$donor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$donor_id) {
  header("Location: index.php?tab=donor&err=" . urlencode("ไม่ได้ระบุ id"));
  exit;
}
$user_id = $_SESSION['admin_id'] ?? ($_SESSION['user']['id'] ?? null);

/* --------------------- LOAD DONOR --------------------- */
$st = $pdo->prepare("SELECT * FROM parts_donors WHERE id=? LIMIT 1");
$st->execute([$donor_id]);
$donor = $st->fetch(PDO::FETCH_ASSOC);
if (!$donor) {
  header("Location: index.php?tab=donor&err=" . urlencode("ไม่พบเครื่องซาก"));
  exit;
}

$errors = [];
$msg = val($_GET, 'msg');

/* --------------------- POST: MOVE LOCATION --------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && val($_POST, 'action') === 'move_location') {
  require_perms(['parts.donor.split']);
  $used_id  = (int)($_POST['used_id'] ?? 0);
  $location = val($_POST, 'location', 'used');

  try {
    $pdo->prepare("UPDATE parts_used SET location=?, updated_at=NOW() WHERE id=? AND donor_id=?")
      ->execute([$location, $used_id, $donor_id]);
    header("Location: donor_split.php?id=" . $donor_id . "&msg=" . urlencode("อัปเดตที่เก็บเรียบร้อย"));
    exit;
  } catch (Throwable $e) {
    $errors[] = $e->getMessage();
  }
}

/* --------------------- POST: SAVE ONE (แยก 1 ชิ้น) --------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && val($_POST, 'action') === 'save_one') {
  require_perms(['parts.donor.split']);

  $part_name     = val($_POST, 'part_name');
  $part_code     = strtoupper(val($_POST, 'part_code'));
  $part_number   = val($_POST, 'part_number');
  $device_models = val($_POST, 'device_models', $donor['device_models']);
  $category      = val($_POST, 'category', 'Other');
  $remarks       = val($_POST, 'remarks', 'จาก donor #' . $donor_id);
  $location      = val($_POST, 'location', 'used');

  // อัปโหลดรูป (สไตล์เดียวกับ form_used.php)
  $image_url = null;
  if (!empty($_FILES['image']['name'])) {
    $f = $_FILES['image'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $errors[] = "ไฟล์รูปต้องเป็น jpg, jpeg, png หรือ webp";
      } elseif ($f['size'] > 5 * 1024 * 1024) {
        $errors[] = "ไฟล์รูปใหญ่เกิน 5MB";
      } else {
        $new = safeUploadName($f['name']);
        if (!move_uploaded_file($f['tmp_name'], PARTS_UPLOAD_DIR . $new)) $errors[] = "อัปโหลดรูปไม่สำเร็จ";
        else $image_url = $new;
      }
    } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
      $errors[] = "อัปโหลดรูปผิดพลาด (code {$f['error']})";
    }
  }

  if ($part_name === '') $errors[] = "กรุณากรอกชื่ออะไหล่";

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // บันทึกเข้าตาราง parts_used
      $pdo->prepare("
        INSERT INTO parts_used
          (part_code, part_name, part_number, device_models, category,
           image_url, location, remarks, donor_id, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?, NOW(), NOW())
      ")->execute([
        $part_code,
        $part_name,
        $part_number,
        $device_models,
        $category,
        $image_url,
        $location,
        $remarks,
        $donor_id
      ]);
      $used_id = (int)$pdo->lastInsertId();

      // ===== Log เอกสาร: USED (+1) =====
      $ref  = 'DONOR:' . $donor_id;
      $note = 'เพิ่มชิ้นมือ 2: ' . ($part_name ?: $part_code) . ' (จาก donor #' . $donor_id . ')';
      $pdo->prepare("
        INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
        VALUES ('USED', ?, ?, ?, NOW())
      ")->execute([$ref, $note, $user_id]);
      $doc_id = (int)$pdo->lastInsertId();

      $pdo->prepare("
        INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
        VALUES (?, ?, 1, NULL, ?, NULL)
      ")->execute([$doc_id, $part_code, $location]);

      // อัปเดตสถานะ donor เป็น stripped ถ้ายังไม่ใช่
      if (($donor['status'] ?? '') !== 'stripped') {
        $pdo->prepare("UPDATE parts_donors SET status='stripped', updated_at=NOW() WHERE id=?")
          ->execute([$donor_id]);
        $donor['status'] = 'stripped';
      }

      $pdo->commit();

      if (!empty($_POST['consume_now'])) {
        header("Location: consume.php?type=used&used_id=" . $used_id);
      } else {
        header("Location: donor_split.php?id=" . $donor_id . "&msg=" . urlencode("เพิ่มชิ้นเข้ามือ 2 แล้ว"));
      }
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = $e->getMessage();
    }
  }
}

/* --------------------- LOAD: รายการที่แยกแล้ว (สด + เคยเบิก) --------------------- */
$st2 = $pdo->prepare("
  SELECT 
    id, donor_id, part_code, part_name, device_models, category, image_url, location,
    0 AS is_log, NULL AS consumed_at
  FROM parts_used
  WHERE donor_id=?
  UNION ALL
  SELECT
    NULL AS id, donor_id, part_code, part_name, device_models, category, image_url, location,
    1 AS is_log, consumed_at
  FROM parts_used_log
  WHERE donor_id=?
  ORDER BY is_log ASC, COALESCE(consumed_at, '1970-01-01') DESC
");
$st2->execute([$donor_id, $donor_id]);
$used_parts = $st2->fetchAll(PDO::FETCH_ASSOC);

/* --------------------- TEMPLATE --------------------- */
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">

  <div class="topbar">
    <span><?= h($pageTitle) ?> #<?= (int)$donor_id ?> (<?= h($donor['device_models']) ?>)</span>
    <a href="index.php?tab=donor" class="view-site">← กลับรายการเครื่องซาก</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div><?php endif; ?>

  <!-- การ์ดหัว -->
  <div class="card part-summary">
    <?php $dsrc = img_src_any($donor['image_url'] ?? ''); ?>
    <div class="part-summary__media">
      <?php if ($dsrc): ?>
        <img src="<?= h($dsrc) ?>" class="part-summary__img" alt="">
      <?php else: ?>
        <div class="part-summary__placeholder">ไม่มีรูป</div>
      <?php endif; ?>
    </div>
    <div class="part-summary__meta">
      <div><strong>รุ่น:</strong> <?= h($donor['device_models']) ?></div>
      <div><strong>Serial:</strong> <?= h($donor['serial_no']) ?></div>
      <div><strong>ทุน:</strong> <?= $donor['purchase_cost'] !== null ? number_format($donor['purchase_cost'], 2) : '-' ?></div>
      <div><strong>สถานะ:</strong> <span class="badge"><?= h($donor['status']) ?></span></div>
    </div>
  </div>

  <!-- รายการที่แยกแล้ว -->
  <h3 class="card-title">รายการที่แยกแล้ว</h3>

  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>รูป</th>
          <th>Part Code</th>
          <th>ชื่ออะไหล่</th>
          <th>รุ่น</th>
          <th>หมวด</th>
          <th>ที่เก็บ</th>
          <th>จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($used_parts): foreach ($used_parts as $i => $p): ?>
            <?php $psrc = img_src_any($p['image_url'] ?? '');
            $isLog = (int)($p['is_log'] ?? 0) === 1; ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <?php if ($psrc): ?>
                  <button type="button" class="thumb-btn" data-src="<?= h($psrc) ?>">
                    <img src="<?= h($psrc) ?>" class="thumb thumb--sm" alt="">
                  </button>
                <?php else: ?>
                  <div class="thumb thumb--sm thumb--empty">-</div>
                <?php endif; ?>
              </td>

              <td><?= h($p['part_code']) ?></td>
              <td>
                <?= h($p['part_name']) ?>
                <?php if ($isLog && !empty($p['consumed_at'])): ?>
                  <div class="muted small">(เบิกแล้ว: <?= h($p['consumed_at']) ?>)</div>
                <?php endif; ?>
              </td>
              <td><?= h($p['device_models']) ?></td>
              <td><span class="badge"><?= h($p['category'] ?: 'Other') ?></span></td>
              <td>
                <?php if (!$isLog && can('parts.donor.split')): ?>
                  <form method="post" class="inline-group">
                    <input type="hidden" name="action" value="move_location">
                    <input type="hidden" name="used_id" value="<?= (int)($p['id'] ?? 0) ?>">
                    <input name="location" class="input filter-input" value="<?= h($p['location']) ?>" style="max-width:160px;">
                    <button class="btn-secondary" type="submit">บันทึก</button>
                  </form>
                <?php else: ?>
                  <span class="muted"><?= h($p['location']) ?></span>
                <?php endif; ?>
              </td>
              <td class="no-wrap actions-cell">
                <?php if ($isLog): ?>
                  <span class="badge badge-gray">เบิกแล้ว</span>
                <?php else: ?>
                  <?php if (can('parts.used.consume')): ?>
                    <a class="btn-primary" href="consume.php?type=used&used_id=<?= (int)($p['id'] ?? 0) ?>">เบิก</a>
                  <?php endif; ?>
                  <?php if (can('parts.used.update')): ?>
                    <a class="btn-secondary" href="form_used.php?id=<?= (int)($p['id'] ?? 0) ?>">เปิดในมือ 2</a>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach;
        else: ?>
          <tr>
            <td colspan="8" class="text-center">ยังไม่มีรายการที่แยก</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Image Preview Modal -->
  <div id="imgPreviewOverlay" class="imgpv-overlay" aria-hidden="true">
    <div class="imgpv-dialog" role="dialog" aria-modal="true" aria-label="ตัวอย่างรูป">
      <button type="button" class="imgpv-close" aria-label="ปิด">✕</button>
      <img id="imgPreview" src="" alt="" class="imgpv-img">
    </div>
  </div>

  <!-- ฟอร์มเพิ่มชิ้นเข้ามือ 2 (สไตล์เดียวกับ form_used.php) -->
  <form id="usedForm" method="post" enctype="multipart/form-data" class="card restock-form" style="margin-top:16px;" novalidate>
    <input type="hidden" name="action" id="usedAction" value="save_one">
    <input type="hidden" name="id" value="">
    <input type="hidden" name="remove_image" id="remove_image" value="0">

    <div class="form-grid">
      <!-- รูป -->
      <div class="form-item">
        <label class="form-label">รูป</label>
        <div class="image-upload-ui" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <div id="uImgWrap" style="position:relative;width:100px;height:100px;border:1px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fafafa;cursor:pointer;" title="คลิกหรือลากไฟล์มาวาง">
            <span id="uImgText" class="muted small">ลากรูปมาวาง</span>
            <button type="button" id="uRemoveBtn" style="display:none;position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);">×</button>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;min-width:220px;">
            <label for="image" class="btn-secondary" style="cursor:pointer;display:inline-flex;align-items:center;gap:8px;">เลือกรูปจากเครื่อง</label>
            <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp" style="display:none">
            <div class="muted small">รองรับ jpg, jpeg, png, webp ≤ 5MB</div>
          </div>
        </div>
      </div>

      <!-- แสดง donor_id (ล็อกค่าไว้) -->
      <div class="form-item">
        <label class="form-label" for="donor_id_display">เชื่อมกับเครื่องซาก</label>
        <input id="donor_id_display" class="input filter-input" value="<?= (int)$donor_id ?>" disabled>
        <small class="form-hint">รายการนี้ถูกสร้างจากเครื่องซากนี้โดยอัตโนมัติ</small>
      </div>

      <div class="form-item">
        <label class="form-label" for="part_name">ชื่ออะไหล่ *</label>
        <input id="part_name" name="part_name" class="input filter-input" required placeholder="เช่น Top Case, Screen, Battery">
      </div>

      <div class="form-item">
        <label class="form-label">รหัสอะไหล่ (ภายใน)</label>
        <input name="part_code" id="part_code" class="input filter-input"
          placeholder="เว้นว่างไว้ ระบบจะสร้างให้อัตโนมัติ">
      </div>


      <div class="form-item">
        <label class="form-label" for="part_number">เลขอะไหล่</label>
        <input id="part_number" name="part_number" class="input filter-input" placeholder="661-xxxx / Axxxx">
      </div>

      <div class="form-item">
        <label class="form-label" for="device_models">รุ่นอุปกรณ์</label>
        <input id="device_models" name="device_models" class="input filter-input" value="<?= h($donor['device_models']) ?>" placeholder="เช่น A1706, A2159">
      </div>

      <div class="form-item">
        <label class="form-label" for="category">หมวด</label>
        <input id="category" name="category" class="input filter-input" placeholder="screen/battery/board/..." list="catlist">
      </div>

      <div class="form-item">
        <label class="form-label" for="location">ที่เก็บ</label>
        <input id="location" name="location" class="input filter-input" value="used" placeholder="เช่น used, shelf-A3">
      </div>

      <div class="form-item" style="grid-column:1 / -1">
        <label class="form-label" for="remarks">หมายเหตุ</label>
        <textarea id="remarks" name="remarks" class="input filter-input" rows="3" placeholder="รายละเอียด/สภาพ/ที่มา ฯลฯ">จาก donor #<?= (int)$donor_id ?></textarea>
      </div>

      <div class="form-actions" style="grid-column:1 / -1">
        <button class="btn-primary" type="submit">เพิ่มชิ้นเข้ามือ 2</button>
        <label class="checkbox-inline" style="margin-left:8px;">
          <input type="checkbox" name="consume_now" value="1">
          <span>บันทึกแล้วเบิกทันที</span>
        </label>
      </div>
    </div>
  </form>

  <script>
  // Image UI (drag & drop + preview + remove) — เหมือน form_used.php
  (function() {
    var input   = document.getElementById('image');
    var wrap    = document.getElementById('uImgWrap');
    var remove  = document.getElementById('uRemoveBtn');
    var img     = document.getElementById('uImg');
    var rmField = document.getElementById('remove_image');
    var existed = false; // เพิ่มใหม่เสมอ

    function showPreview(file) {
      if (!file) return;
      if (!/image\/(png|jpe?g|webp)/i.test(file.type)) {
        alert('ไฟล์ไม่ใช่รูปภาพที่รองรับ');
        return;
      }
      var reader = new FileReader();
      reader.onload = function(e) {
        if (!img) {
          img = document.createElement('img');
          img.id = 'uImg';
          img.alt = 'preview';
          img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
        }
        wrap.innerHTML = '';
        wrap.appendChild(img);
        img.src = e.target.result;

        if (!remove) {
          remove = document.createElement('button');
          remove.id = 'uRemoveBtn';
          remove.type = 'button';
          remove.textContent = '×';
          remove.style.cssText =
            'position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;' +
            'background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;' +
            'line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);';
          remove.addEventListener('click', clearImage);
        }
        wrap.appendChild(remove);
        remove.style.display = '';
        if (rmField) rmField.value = 0;
      };
      reader.readAsDataURL(file);
    }

    function clearImage(e) {
      if (e) e.stopPropagation();
      if (input) input.value = '';
      wrap.innerHTML = '<span id="uImgText" class="muted small">ลากรูปมาวาง</span>';
      if (remove) {
        wrap.appendChild(remove);
        remove.style.display = 'none';
      }
      img = null;
      if (rmField && existed) rmField.value = 1;
    }

    wrap.addEventListener('click', function() {
      if (input) input.click();
    });

    function setBorder(c) { wrap.style.borderColor = c; }
    wrap.addEventListener('dragover', function(e) {
      e.preventDefault();
      setBorder('#3b82f6');
    });
    wrap.addEventListener('dragleave', function() {
      setBorder('#cbd5e1');
    });
    wrap.addEventListener('drop', function(e) {
      e.preventDefault();
      setBorder('#cbd5e1');
      var f = e.dataTransfer.files && e.dataTransfer.files[0];
      if (f) {
        input.files = e.dataTransfer.files;
        showPreview(f);
      }
    });
    if (input) input.addEventListener('change', function() {
      var f = input.files && input.files[0];
      if (f) showPreview(f);
    });
    if (remove) remove.addEventListener('click', clearImage);
  })();

  // ===== เติม "รหัสอะไหล่" อัตโนมัติเมื่อช่องว่าง (ไม่มีปุ่ม) =====
  (function () {
    function pad2(n){ return (n < 10 ? '0' : '') + n; }
    function genCode(cat, model){
      var map = {screen:'SCR', battery:'BATT', keyboard:'KB', trackpad:'TP',
                 speaker:'SPK', camera:'CAM', board:'BRD', cable:'CBL',
                 fan:'FAN', hinge:'HNG', case:'CASE', Other:'OTH'};
      var cc = map[String(cat||'').toLowerCase()] || 'OTH';
      var m  = String(model||'').replace(/[^A-Za-z0-9]+/g,'').slice(0,6).toUpperCase() || 'MDL';
      var d=new Date();
      var t=d.getHours()+''+pad2(d.getMinutes())+pad2(d.getSeconds());
      return cc + '-' + m + '-' + t;
    }

    var pc  = document.getElementById('part_code');
    var cat = document.getElementById('category');
    var mdl = document.getElementById('device_models');
    if (!pc) return;

    var userEdited = false;

    function seedIfEmpty(){
      if (userEdited) return;
      if (pc.value.trim() !== '') return;
      pc.value = genCode(cat ? cat.value : 'Other', mdl ? mdl.value : '');
    }

    // ไม่ทับค่าถ้าผู้ใช้เริ่มพิมพ์เอง
    pc.addEventListener('input', function(){ userEdited = pc.value.trim() !== ''; });

    // ถ้าเปลี่ยนหมวด/รุ่นแล้วยังว่างอยู่ -> เติมให้
    ['change','blur'].forEach(function(evt){
      if (cat) cat.addEventListener(evt, seedIfEmpty);
      if (mdl) mdl.addEventListener(evt, seedIfEmpty);
    });

    // เรียกครั้งแรกทันที (สคริปต์อยู่ท้ายหน้าแล้ว)
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', seedIfEmpty);
    } else {
      seedIfEmpty();
    }
  })();
</script>


  <script>
    (function() {
      var overlay = document.getElementById('imgPreviewOverlay');
      var imgEl = document.getElementById('imgPreview');

      function openPreview(src) {
        if (!overlay || !imgEl) return;
        imgEl.src = src;
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
      }

      function closePreview() {
        if (!overlay) return;
        overlay.classList.remove('show');
        overlay.setAttribute('aria-hidden', 'true');
        if (imgEl) imgEl.src = '';
      }

      // เปิดจากปุ่มรูป
      document.addEventListener('click', function(e) {
        var btn = e.target.closest ? e.target.closest('.thumb-btn') : null;
        if (!btn) return;
        var src = btn.getAttribute('data-src');
        if (src) openPreview(src);
      });

      // ปิด overlay
      if (overlay) {
        overlay.addEventListener('click', function(e) {
          if (e.target === overlay || e.target.classList.contains('imgpv-close')) {
            closePreview();
          }
        });
      }
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay && overlay.classList.contains('show')) closePreview();
      });
    })();
  </script>