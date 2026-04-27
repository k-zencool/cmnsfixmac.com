<?php
/********************************************************************
 * admin/parts/donor_split.php
 * แยกอะไหล่จากเครื่อง (ทีละชิ้น) -> เข้า "อะไหล่มือ 2"
 * * UPDATE: 
 * - เปลี่ยนระบบจาก part_code เป็น used_sku ตามมาตรฐาน
 * - รองรับ Schema ใหม่ของ parts_donors (Type, Series, Code)
 * - UI แบบเดิมพร้อมระบบ Preview รูปภาพ
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_perms(['parts.donor.view']);

$pageTitle = "แยกอะไหล่จากเครื่อง";

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function val($arr, $k, $d = '') { return isset($arr[$k]) ? trim((string)$arr[$k]) : $d; }

define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(PARTS_UPLOAD_DIR)) @mkdir(PARTS_UPLOAD_DIR, 0775, true);

// ========================== [SKU LOGIC] ==========================
// ดึง Logic มาจากหน้า form_used.php เพื่อให้รหัสรันต่อกัน

function detectPrefix($name, $model, $cat) {
    $text = strtolower($name . ' ' . $model . ' ' . $cat);
    if (strpos($text, 'iphone') !== false)     return 'IP';
    if (strpos($text, 'ipad') !== false)       return 'PD';
    if (strpos($text, 'imac') !== false)       return 'IM';
    if (strpos($text, 'watch') !== false)      return 'WA';
    if (strpos($text, 'airpods') !== false)    return 'AP';
    if (strpos($text, 'pencil') !== false)     return 'PE';
    if (strpos($text, 'mac mini') !== false)   return 'MM';
    if (strpos($text, 'adapter') !== false)    return 'AC';
    return 'MB'; // Default เป็น MacBook
}

function generateUsedSKU(PDO $pdo, $partName, $deviceModel, $cat) {
    $prefixType = detectPrefix($partName, $deviceModel, $cat);
    $ym = date('Ym');
    // ค้นหาลำดับล่าสุดจากเลขท้าย SKU
    $sql = "SELECT CAST(SUBSTRING_INDEX(used_sku, '-A', -1) AS UNSIGNED) as max_seq 
            FROM parts_used WHERE used_sku LIKE '%-A%' ORDER BY max_seq DESC LIMIT 1";
    $stmt = $pdo->query($sql);
    $maxSeq = (int)$stmt->fetchColumn(); 
    return sprintf("U-%s-%s-A%04d", $prefixType, $ym, $maxSeq + 1);
}

function safeUploadName(string $orig): string {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i', '-', pathinfo($orig, PATHINFO_FILENAME));
  if ($base === '') $base = 'used';
  return $base . '-' . date('Ymd_His') . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
}

function img_src_any($v) {
  $v = trim((string)$v);
  if ($v === '') return '';
  if (preg_match('~^https?://~i', $v) || $v[0] === '/') return $v;
  return '../../uploads/parts/' . $v;
}

// ========================== [STATE & LOAD] ==========================
$donor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$donor_id) {
  header("Location: index.php?tab=donor&err=" . urlencode("ไม่ได้ระบุ id"));
  exit;
}
$user_id = $_SESSION['admin_id'] ?? ($_SESSION['user']['id'] ?? null);

/* LOAD DONOR */
$st = $pdo->prepare("SELECT * FROM parts_donors WHERE id=? LIMIT 1");
$st->execute([$donor_id]);
$donor = $st->fetch(PDO::FETCH_ASSOC);
if (!$donor) {
  header("Location: index.php?tab=donor&err=" . urlencode("ไม่พบเครื่อง"));
  exit;
}

// สร้างชื่อรุ่นจาก Type + Series + Code
$donor_model_str = trim(
    ($donor['device_type'] ?? '') . ' ' . 
    ($donor['device_series'] ?? '') . ' ' . 
    ($donor['model_code'] ?? '')
);
if ($donor_model_str === '') $donor_model_str = 'Donor #' . $donor_id;

$default_remark = 'แยกจาก ' . (!empty($donor['internal_id']) ? $donor['internal_id'] : "Donor #$donor_id");

$errors = [];
$msg = val($_GET, 'msg');

// ========================== [ACTIONS] ==========================

/* MOVE LOCATION */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && val($_POST, 'action') === 'move_location') {
  require_perms(['parts.donor.split']);
  $used_id  = (int)($_POST['used_id'] ?? 0);
  $location = val($_POST, 'location', 'used');

  try {
    $pdo->prepare("UPDATE parts_used SET location=?, updated_at=NOW() WHERE id=? AND donor_id=?")
      ->execute([$location, $used_id, $donor_id]);
    header("Location: donor_split.php?id=$donor_id&msg=" . urlencode("อัปเดตที่เก็บเรียบร้อย"));
    exit;
  } catch (Throwable $e) { $errors[] = $e->getMessage(); }
}

/* SAVE ONE (แยกอะไหล่) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && val($_POST, 'action') === 'save_one') {
  require_perms(['parts.donor.split']);

  $part_name     = val($_POST, 'part_name');
  $device_models = val($_POST, 'device_models', $donor_model_str);
  $category      = val($_POST, 'category', 'MacBook');
  $part_number   = val($_POST, 'part_number');
  $remarks       = val($_POST, 'remarks', $default_remark);
  $location      = val($_POST, 'location', 'used');

  // GEN SKU ใหม่ตามมาตรฐาน U-XX-YYYYMM-AXXXX
  $used_sku = generateUsedSKU($pdo, $part_name, $device_models, $category);

  $image_url = null;
  if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
      $new = safeUploadName($_FILES['image']['name']);
      if (move_uploaded_file($_FILES['image']['tmp_name'], PARTS_UPLOAD_DIR . $new)) $image_url = $new;
  }

  if ($part_name === '') $errors[] = "กรุณากรอกชื่ออะไหล่";

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      /* 1. บันทึกลง parts_used */
      $pdo->prepare("
        INSERT INTO parts_used
          (used_sku, part_name, part_number, device_models, category,
           image_url, location, remarks, donor_id, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?, NOW(), NOW())
      ")->execute([$used_sku, $part_name, $part_number, $device_models, $category, $image_url, $location, $remarks, $donor_id]);
      $used_id = (int)$pdo->lastInsertId();

      /* 2. Log ลงเอกสาร (parts_docs) */
      $note = "เพิ่มชิ้นมือ 2 [$used_sku]: $part_name (จาก Donor #$donor_id)";
      $pdo->prepare("INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at) VALUES ('USED', ?, ?, ?, NOW())")
          ->execute(["DONOR:$donor_id", $note, $user_id]);

      /* 3. อัปเดตสถานะเครื่อง Donor */
      if (($donor['status'] ?? '') !== 'stripped') {
        $pdo->prepare("UPDATE parts_donors SET status='stripped', updated_at=NOW() WHERE id=?")->execute([$donor_id]);
      }

      $pdo->commit();

      if (!empty($_POST['consume_now'])) {
        header("Location: consume.php?type=used&used_id=" . $used_id);
      } else {
        header("Location: donor_split.php?id=$donor_id&msg=" . urlencode("สำเร็จ! SKU: $used_sku"));
      }
      exit;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $errors[] = $e->getMessage(); }
  }
}

/* LOAD LIST: อะไหล่ที่แยกแล้ว + อะไหล่ที่เคยเบิกไปแล้ว (Log) */
$st2 = $pdo->prepare("
  SELECT id, used_sku, part_name, device_models, category, image_url, location, 0 AS is_log, NULL AS consumed_at
  FROM parts_used WHERE donor_id=?
  UNION ALL
  SELECT NULL, used_sku, part_name, device_models, category, image_url, location, 1, consumed_at
  FROM parts_used_log WHERE donor_id=?
  ORDER BY is_log ASC, id DESC
");
$st2->execute([$donor_id, $donor_id]);
$used_parts = $st2->fetchAll(PDO::FETCH_ASSOC);

// ========================== [TEMPLATE] ==========================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<style>
/* CSS Styling */
.asset-tag { font-family: 'SFMono-Regular', monospace; font-weight: 700; color: #1e3a8a; background: #dbeafe; border: 1px solid #93c5fd; padding: 2px 8px; border-radius: 4px; }
.sku-badge { background: #1f2937; color: #fbbf24; font-family: monospace; padding: 3px 7px; border-radius: 4px; font-weight: bold; font-size: 0.85rem; }
.imgpv-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center; z-index: 9999; }
.imgpv-overlay.show { display: flex; }
.imgpv-img { max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
.imgpv-close { position: absolute; top: 20px; right: 20px; color: #fff; font-size: 30px; cursor: pointer; }
.thumb-btn { background: none; border: none; padding: 0; cursor: pointer; }
</style>

<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
    <a href="index.php?tab=donor" class="view-site">← กลับรายการเครื่อง</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div><?php endif; ?>

  <div class="card part-summary">
    <div class="part-summary__media">
      <?php $dsrc = img_src_any($donor['image_url'] ?? ''); ?>
      <?php if ($dsrc): ?><img src="<?= h($dsrc) ?>" class="part-summary__img"><?php else: ?><div class="part-summary__placeholder">ไม่มีรูป</div><?php endif; ?>
    </div>
    <div class="part-summary__meta">
      <div style="margin-bottom: 8px;"><span class="muted small">Asset Tag:</span> <?php if(!empty($donor['internal_id'])): ?><span class="asset-tag"><?= h($donor['internal_id']) ?></span><?php else: ?>-<?php endif; ?></div>
      <div><strong>รุ่น:</strong> <?= h($donor_model_str) ?></div>
      <div><strong>Serial:</strong> <?= h($donor['serial_no']) ?></div>
      <div><strong>สถานะ:</strong> <span class="badge"><?= h($donor['status']) ?></span></div>
    </div>
  </div>

  <h3 class="card-title" style="margin-top:25px;">🛠 รายการอะไหล่ที่แยกออกแล้ว</h3>
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>รูป</th>
          <th>Used SKU</th>
          <th>ชื่ออะไหล่</th>
          <th>รุ่น</th>
          <th>ที่เก็บ</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($used_parts): foreach ($used_parts as $i => $p): 
            $psrc = img_src_any($p['image_url']); $isLog = (int)$p['is_log'] === 1; ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <?php if ($psrc): ?>
                  <button type="button" class="thumb-btn" data-src="<?= h($psrc) ?>"><img src="<?= h($psrc) ?>" class="thumb thumb--sm"></button>
                <?php else: ?>-<?php endif; ?>
              </td>
              <td><span class="sku-badge"><?= h($p['used_sku']) ?></span></td>
              <td>
                <?= h($p['part_name']) ?>
                <?php if ($isLog): ?><div class="muted small">(เบิกไปแล้วเมื่อ: <?= h($p['consumed_at']) ?>)</div><?php endif; ?>
              </td>
              <td><?= h($p['device_models']) ?></td>
              <td>
                <?php if (!$isLog): ?>
                  <form method="post" class="inline-group" style="display:flex; gap:5px;">
                    <input type="hidden" name="action" value="move_location">
                    <input type="hidden" name="used_id" value="<?= (int)$p['id'] ?>">
                    <input name="location" class="input filter-input" value="<?= h($p['location']) ?>" style="max-width:100px; padding:4px 8px; font-size:13px;">
                    <button class="btn-secondary btn-sm" type="submit">OK</button>
                  </form>
                <?php else: ?><?= h($p['location']) ?><?php endif; ?>
              </td>
              <td class="actions-cell">
                <?php if (!$isLog): ?>
                  <a class="btn-primary" href="consume.php?type=used&used_id=<?= (int)$p['id'] ?>">เบิก</a>
                  <a class="btn-secondary" href="form_used.php?id=<?= (int)$p['id'] ?>">แก้ไข</a>
                <?php else: ?><span class="badge badge-gray">จ่ายออกแล้ว</span><?php endif; ?>
              </td>
            </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="7" class="text-center muted">ยังไม่มีการแยกอะไหล่จากเครื่องนี้</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <h3 class="card-title" style="margin-top:35px;">➕ เพิ่มชิ้นส่วนอะไหล่ (แยกเข้าคลังมือ 2)</h3>
  <form id="usedForm" method="post" enctype="multipart/form-data" class="card restock-form" novalidate>
    <input type="hidden" name="action" value="save_one">
    <div class="form-grid">
      <div class="form-item">
        <label class="form-label">รูปภาพอะไหล่</label>
        <div id="uImgWrap" style="width:100%; height:120px; border:2px dashed #cbd5e1; border-radius:12px; display:flex; align-items:center; justify-content:center; cursor:pointer; overflow:hidden; background:#fafafa;">
          <span class="muted small">คลิกหรือลากรูปมาวาง</span>
        </div>
        <input type="file" name="image" id="imageInput" style="display:none;" accept="image/*">
      </div>

      <div class="form-item">
        <label class="form-label">ชื่ออะไหล่ *</label>
        <input name="part_name" class="input" required placeholder="เช่น หน้าจอ LCD, แบตเตอรี่, บอร์ด">
      </div>

      <div class="form-item">
        <label class="form-label">หมวดหมู่</label>
        <select name="category" class="input">
          <option value="MacBook">MacBook</option>
          <option value="iPhone">iPhone</option>
          <option value="iPad">iPad</option>
          <option value="iMac">iMac</option>
          <option value="Watch">Apple Watch</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="form-item">
        <label class="form-label">รุ่นอุปกรณ์ (Model)</label>
        <input name="device_models" class="input" value="<?= h($donor_model_str) ?>">
      </div>

      <div class="form-item">
        <label class="form-label">Part Number / Serial อะไหล่</label>
        <input name="part_number" class="input" placeholder="ถ้ามีรหัสเฉพาะชิ้นส่วน">
      </div>

      <div class="form-item">
        <label class="form-label">ที่เก็บ (Location)</label>
        <input name="location" class="input" value="used" placeholder="เช่น กล่องมือ 2">
      </div>

      <div class="form-item" style="grid-column: 1 / -1;">
        <label class="form-label">หมายเหตุ / สภาพอะไหล่</label>
        <textarea name="remarks" class="input" rows="2"><?= h($default_remark) ?></textarea>
      </div>

      <div class="form-actions" style="grid-column: 1 / -1; display:flex; align-items:center; gap:20px;">
        <button class="btn-primary" type="submit" style="padding:12px 30px;">บันทึกเข้าสต็อกมือ 2</button>
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
          <input type="checkbox" name="consume_now" value="1" style="width:18px; height:18px;">
          <span>บันทึกแล้วไปหน้าเบิกจ่ายทันที</span>
        </label>
      </div>
    </div>
  </form>

  <div id="imgPreviewOverlay" class="imgpv-overlay">
    <span class="imgpv-close">×</span>
    <img id="imgPreview" class="imgpv-img" src="">
  </div>
</main>

<script>
// Image Preview & Upload Logic
const wrap = document.getElementById('uImgWrap');
const input = document.getElementById('imageInput');

wrap.onclick = () => input.click();
input.onchange = (e) => {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (event) => {
            wrap.innerHTML = `<img src="${event.target.result}" style="width:100%; height:100%; object-fit:cover;">`;
        };
        reader.readAsDataURL(file);
    }
};

// Preview Overlay Logic
const overlay = document.getElementById('imgPreviewOverlay');
const previewImg = document.getElementById('imgPreview');
document.querySelectorAll('.thumb-btn').forEach(btn => {
    btn.onclick = () => {
        previewImg.src = btn.dataset.src;
        overlay.classList.add('show');
    };
});
overlay.onclick = () => overlay.classList.remove('show');
</script>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>