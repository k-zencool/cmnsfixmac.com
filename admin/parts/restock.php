<?php

/********************************************************************
 * admin/parts/restock.php
 * รับเข้า/เติมสต็อก (สร้างเอกสาร IN + เพิ่มจำนวนใน parts_new)
 *
 * ใช้ตาราง:
 *   parts_new(part_code, part_name, part_number, device_models, category,
 *             image_url, min_stock, location, quantity)
 *   parts_docs(doc_id, doc_type, ref_no, remarks, user_id, created_at)
 *   parts_doc_lines(line_id, doc_id, part_code, qty, location_from, location_to, unit_cost)
 *
 * หมายเหตุ:
 *  - ไม่มีคอลัมน์ is_active แล้ว
 *  - รองรับเลือกโลเคชันเดิมหรือเพิ่มใหม่ (+ เพิ่มใหม่…)
 ********************************************************************/

// ========== SETUP ==========
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();                       // เข้าหน้าได้ต้องล็อกอิน
require_perms(['parts.new.restock']);  // และต้องมีสิทธิ์เติมสต็อก


$pageTitle = "รับเข้า/เติมสต็อก";
function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function img_src($v)
{
  $v = trim((string)$v);
  if ($v === '') return '';
  if (preg_match('~^https?://~i', $v) || $v[0] === '/') return $v;
  return '../../uploads/parts/' . $v;
}

// ========== STATE ==========
$code = trim($_GET['part_code'] ?? '');
$errors = [];

// โหลดเมทาดาต้าตัวแทน + คงเหลือรวม + คงเหลือตามโลเคชัน
$meta = null;
$locs = [];
if ($code !== '') {
  // summary
  $st = $pdo->prepare("
    SELECT
      part_code,
      MAX(part_name)     AS part_name,
      MAX(part_number)   AS part_number,
      MAX(device_models) AS device_models,
      MAX(category)      AS category,
      MAX(image_url)     AS image_url,
      MAX(min_stock)     AS min_stock,
      SUM(quantity)      AS qty
    FROM parts_new
    WHERE part_code=?
    GROUP BY part_code
    LIMIT 1
  ");
  $st->execute([$code]);
  $meta = $st->fetch(PDO::FETCH_ASSOC);

  // locations
  $st2 = $pdo->prepare("
    SELECT location, SUM(quantity) AS qty
    FROM parts_new
    WHERE part_code=?
    GROUP BY location
    ORDER BY location
  ");
  $st2->execute([$code]);
  $locs = $st2->fetchAll(PDO::FETCH_ASSOC);
}

// ========== POST: รับเข้า ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restock') {
  $code     = trim($_POST['part_code'] ?? '');
  $locSel   = trim($_POST['location'] ?? '');
  $locNew   = trim($_POST['location_new'] ?? '');
  $location = $locSel === '_new' ? $locNew : $locSel;

  $qty      = max(0, (int)($_POST['qty'] ?? 0));
  $unitCost = trim($_POST['unit_cost'] ?? '');
  $unitCost = ($unitCost === '' ? null : (float)$unitCost);
  $ref_no   = trim($_POST['ref_no'] ?? '');
  $remarks  = trim($_POST['remarks'] ?? '');

  $user_id  = $_SESSION['admin_id'] ?? ($_SESSION['user']['id'] ?? null);

  if ($code === '')      $errors[] = "กรอกรหัสอะไหล่";
  if ($location === '')  $errors[] = "กรอก/เลือกโลเคชัน";
  if ($qty <= 0)         $errors[] = "จำนวนต้องมากกว่า 0";
  if (!$user_id)         $errors[] = "ไม่พบผู้ใช้งานในระบบ";

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // เมทาดาต้าสำหรับคัดลอกตอนสร้างโลเคชันใหม่ (ถ้ายังไม่มี)
      $stM = $pdo->prepare("
        SELECT part_code, part_name, part_number, device_models, category, image_url, min_stock
        FROM parts_new
        WHERE part_code=?
        ORDER BY location
        LIMIT 1
      ");
      $stM->execute([$code]);
      $m = $stM->fetch(PDO::FETCH_ASSOC);

      if (!$m) {
        // ยังไม่มี part_code นี้เลย – สร้างแถวตั้งต้น (main, 0)
        $pdo->prepare("
          INSERT INTO parts_new (part_code, part_name, part_number, device_models, category,
                                 image_url, min_stock, location, quantity)
          VALUES (?, '', '', '', 'Other', NULL, 0, 'main', 0)
        ")->execute([$code]);
        // ดึงกลับมาใช้อีกครั้ง
        $m = [
          'part_code' => $code,
          'part_name' => '',
          'part_number' => '',
          'device_models' => '',
          'category' => 'Other',
          'image_url' => null,
          'min_stock' => 0
        ];
      }

      // สร้างเอกสาร IN
      $pdo->prepare("
        INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
        VALUES ('IN', ?, ?, ?, NOW())
      ")->execute([$ref_no ?: null, $remarks !== '' ? $remarks : "restock to {$location}", $user_id]);
      $doc_id = (int)$pdo->lastInsertId();

      // เพิ่มบรรทัดเอกสาร
      $pdo->prepare("
        INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
        VALUES (?, ?, ?, NULL, ?, ?)
      ")->execute([$doc_id, $code, $qty, $location, $unitCost]);

      // ให้มีแถวโลเคชันนี้ก่อน (ถ้ายังไม่มี)
      $stC = $pdo->prepare("SELECT COUNT(*) FROM parts_new WHERE part_code=? AND location=?");
      $stC->execute([$code, $location]);
      if ((int)$stC->fetchColumn() === 0) {
        $pdo->prepare("
          INSERT INTO parts_new (part_code, part_name, part_number, device_models, category,
                                 image_url, min_stock, location, quantity)
          VALUES (?,?,?,?,?,?,?, ?, 0)
        ")->execute([
          $m['part_code'],
          $m['part_name'],
          $m['part_number'],
          $m['device_models'],
          $m['category'],
          $m['image_url'],
          (int)$m['min_stock'],
          $location
        ]);
      }

      // อัปเดตสต็อก
      $pdo->prepare("
        UPDATE parts_new
        SET quantity = quantity + ?
        WHERE part_code=? AND location=?
      ")->execute([$qty, $code, $location]);

      $pdo->commit();
      header("Location: index.php?tab=new&msg=" . urlencode("รับเข้าเรียบร้อย"));
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = $e->getMessage();
    }
  }
}

// ========== TEMPLATE ==========
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">

  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
    <a href="index.php?tab=new" class="view-site">← กลับรายการ</a>
  </div>
  
<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $e): ?>
      <div><?= h($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($meta): ?>
  <section class="card part-summary">
    <?php $img = img_src($meta['image_url'] ?? ''); ?>
    <div class="part-summary__media">
      <?php if ($img): ?>
        <img src="<?= h($img) ?>" class="part-summary__img" alt="">
      <?php else: ?>
        <div class="part-summary__placeholder">ไม่มีรูป</div>
      <?php endif; ?>
    </div>
    <div class="part-summary__body">
      <strong class="part-summary__title"><?= h($meta['part_name'] ?: $code) ?></strong>
      <div class="muted small">รหัส: <?= h($code) ?> | เลข: <?= h($meta['part_number']) ?></div>
      <div class="muted small">รุ่น: <?= h($meta['device_models']) ?> | คงเหลือรวม: <?= (int)$meta['qty'] ?></div>
      <?php if ($locs): ?>
        <div class="chips" style="margin-top:6px">
          <?php foreach ($locs as $l): ?>
            <span class="badge"><?= h($l['location']) ?>: <?= (int)$l['qty'] ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<form method="post" class="card restock-form" novalidate>
  <input type="hidden" name="action" value="restock">

  <div class="form-grid">
    <!-- รหัสอะไหล่ -->
    <div class="form-item">
      <label class="form-label" for="part_code">รหัสอะไหล่ *</label>
      <input id="part_code" class="input filter-input" name="part_code" required value="<?= h($code) ?>">
    </div>

    <!-- รับเข้าไปที่ -->
    <div class="form-item">
      <label class="form-label" for="location">รับเข้าไปที่ *</label>
      <?php if (!empty($locs)): ?>
        <div class="fx-col gap-6">
          <select name="location" id="location" class="input filter-input" required>
            <?php
              $hasMain = false;
              foreach($locs as $l){ if(($l['location'] ?? '') === 'main') $hasMain = true; }
            ?>
            <?php foreach ($locs as $l): ?>
              <option value="<?= h($l['location']) ?>" <?= ($hasMain && $l['location']==='main')?'selected':'' ?>>
                <?= h($l['location']) ?> (คงเหลือ <?= (int)$l['qty'] ?>)
              </option>
            <?php endforeach; ?>
            <option value="_new">+ เพิ่มใหม่…</option>
          </select>
          <input type="text" name="location_new" id="location_new" class="input filter-input hide" placeholder="พิมพ์โลเคชันใหม่">
        </div>
      <?php else: ?>
        <input class="input filter-input" name="location" id="location" required value="main">
      <?php endif; ?>
    </div>

    <!-- จำนวนที่รับเข้า -->
    <div class="form-item">
      <label class="form-label" for="qty">จำนวนที่รับเข้า *</label>
      <input id="qty" class="input filter-input" type="number" min="1" name="qty" required value="1" inputmode="numeric">
    </div>

    <!-- ต้นทุน/หน่วย -->
    <div class="form-item">
      <label class="form-label" for="unit_cost">ต้นทุน/หน่วย</label>
      <input id="unit_cost" class="input filter-input" type="number" step="0.01" name="unit_cost" placeholder="เช่น 250.00" inputmode="decimal">
    </div>

    <!-- เลขอ้างอิง -->
    <div class="form-item" id="ref_wrap">
      <label class="form-label" for="ref_no">เลขอ้างอิง</label>
      <input id="ref_no" class="input filter-input" name="ref_no" placeholder="ใบงาน, เลข PO ฯลฯ">
    </div>

    <!-- หมายเหตุ -->
    <div class="form-item" id="remarks_wrap">
      <label class="form-label" for="remarks">หมายเหตุ</label>
      <input id="remarks" class="input filter-input" name="remarks" placeholder="โน้ตสั้นๆ">
    </div>
  </div>

  <div class="form-actions">
    <button class="btn-primary" type="submit">บันทึกรับเข้า</button>
    <a class="btn-secondary" href="index.php?tab=new">ยกเลิก</a>
  </div>
</form>

</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
  // toggle ช่อง "เพิ่มโลเคชันใหม่" และโชว์คงเหลือของที่เลือก
  (function() {
    var sel = document.getElementById('location');
    var boxNew = document.getElementById('location_new');
    if (!sel) return;

    function refresh() {
      if (sel.value === '_new') {
        if (boxNew) boxNew.style.display = '';
      } else {
        if (boxNew) boxNew.style.display = 'none';
      }
    }
    sel.addEventListener('change', refresh);
    refresh();
  })();
</script>