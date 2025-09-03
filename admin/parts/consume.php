<?php

/********************************************************************
 * admin/parts/consume.php  (RBAC-ready: มือ 1 + มือ 2)
 * เบิก/ตัดจ่ายอะไหล่:
 *   - มือ 1 (parts_new) : หักจำนวนจาก location ที่เลือก + เอกสาร CONSUME
 *   - มือ 2 (parts_used): สร้าง CONSUME (qty=1, from 'used') แล้วลบแถวออกเลย
 *
 * รองรับพารามฯ:
 *   มือ 1: ?type=new&part_code=...
 *   มือ 2: ?type=used&used_id=...  (หรือ ?mode=used&id=...)
 ********************************************************************/

// ========================== [SETUP & GUARD] ==========================
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function img_src($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  if (preg_match('~^https?://~i',$v) || $v[0]==='/') return $v;
  return '../../uploads/parts/'.$v;
}
function back_to($tab, $qs=''){
  $u = "index.php?tab=".urlencode($tab);
  if ($qs) $u .= "&$qs";
  header("Location: $u"); exit;
}

// กำหนดโหมดจาก GET/POST ก่อนเช็คสิทธิ์
$rawType = $_POST['mode'] ?? $_GET['type'] ?? $_GET['mode'] ?? 'new';
$type    = ($rawType === 'used') ? 'used' : 'new';

// เช็คสิทธิ์ตามโหมด
if ($type === 'new')  require_perms(['parts.new.consume']);
if ($type === 'used') require_perms(['parts.used.consume']);

$pageTitle = "ตัดจ่าย/เบิก";

// ========================== [STATE] ==================================
$code     = trim($_GET['part_code'] ?? '');                                      // มือ 1
$used_id  = isset($_GET['used_id']) ? (int)$_GET['used_id'] : (int)($_GET['id'] ?? 0); // มือ 2
$errors   = [];
$user_id  = $_SESSION['user']['id'] ?? ($_SESSION['admin_id'] ?? null);

// ========================== [LOAD: มือ 1 SUMMARY + LOCATIONS] ========
$part_new = null;  // meta + qty รวม
$locs     = [];    // คงเหลือต่อ location

if ($type === 'new' && $code !== '') {
  // summary
  $st = $pdo->prepare("
    SELECT part_code,
           MAX(part_name)     AS part_name,
           MAX(part_number)   AS part_number,
           MAX(device_models) AS device_models,
           MAX(category)      AS category,
           MAX(image_url)     AS image_url,
           SUM(quantity)      AS qty
    FROM parts_new
    WHERE part_code=?
    GROUP BY part_code
    LIMIT 1
  ");
  $st->execute([$code]);
  $part_new = $st->fetch(PDO::FETCH_ASSOC);

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

// ========================== [LOAD: มือ 2 ITEM] =======================
$used = null;
if ($type === 'used' && $used_id > 0) {
  $st = $pdo->prepare("
    SELECT id, part_code, part_name, part_number, device_models, category,
           image_url, location, remarks, created_at, updated_at
    FROM parts_used
    WHERE id=? LIMIT 1
  ");
  $st->execute([$used_id]);
  $used = $st->fetch(PDO::FETCH_ASSOC);
}

// ========================== [POST: ACTION] ===========================
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $mode = ($_POST['mode'] ?? 'new') === 'used' ? 'used' : 'new';

  // ย้ำสิทธิ์ตามโหมดที่ submit เข้ามา
  if ($mode === 'new')  require_perms(['parts.new.consume']);
  if ($mode === 'used') require_perms(['parts.used.consume']);

  // ---------- มือ 1: CONSUME จาก parts_new ----------
  if ($mode === 'new') {
    $code     = trim($_POST['part_code'] ?? '');
    $location = trim($_POST['location'] ?? 'main');
    $qty      = max(0, (int)($_POST['qty'] ?? 0));
    $ref_no   = trim($_POST['ref_no'] ?? '');
    $remarks  = trim($_POST['remarks'] ?? '');

    if ($code==='')     $errors[] = "กรอกรหัสอะไหล่";
    if ($location==='') $errors[] = "กรอก/เลือกที่เก็บ";
    if ($qty<=0)        $errors[] = "จำนวนต้องมากกว่า 0";
    if (!$user_id)      $errors[] = "ไม่พบผู้ใช้งานในระบบ";

    if (!$errors) {
      try {
        $pdo->beginTransaction();

        // ล็อกยอดที่ location
        $st = $pdo->prepare("SELECT quantity FROM parts_new WHERE part_code=? AND location=? FOR UPDATE");
        $st->execute([$code,$location]);
        $have = (int)($st->fetchColumn() ?? 0);
        if ($have < $qty) throw new Exception("คงเหลือไม่พอในที่เก็บนี้ (เหลือ {$have})");

        // Header
        $pdo->prepare("
          INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
          VALUES ('CONSUME', ?, ?, ?, NOW())
        ")->execute([$ref_no ?: null, $remarks !== '' ? $remarks : "consume from {$location}", $user_id]);
        $doc_id = (int)$pdo->lastInsertId();

        // Line
        $pdo->prepare("
          INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
          VALUES (?, ?, ?, ?, NULL, NULL)
        ")->execute([$doc_id, $code, $qty, $location]);

        // หักสต็อก
        $pdo->prepare("
          UPDATE parts_new SET quantity = quantity - ?
          WHERE part_code=? AND location=?
        ")->execute([$qty, $code, $location]);

        $pdo->commit();
        back_to('new','msg=ตัดจ่ายเรียบร้อย');
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = $e->getMessage();
      }
    }
  }

  // ---------- มือ 2: CONSUME แล้วลบออกเลย ----------
  if ($mode === 'used') {
    $used_id = (int)($_POST['used_id'] ?? 0);
    $ref_no  = trim($_POST['ref_no'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($used_id <= 0) $errors[] = "ไม่พบรายการมือ 2 ที่จะตัดจ่าย";
    if (!$user_id)     $errors[] = "ไม่พบผู้ใช้งานในระบบ";

    if (!$errors) {
      try {
        $pdo->beginTransaction();

        // ล็อกแถวชิ้นมือ 2
        $st = $pdo->prepare("SELECT * FROM parts_used WHERE id=? FOR UPDATE");
        $st->execute([$used_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception("ไม่พบข้อมูลชิ้นมือ 2");

        // Header เอกสาร
        $pdo->prepare("
          INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
          VALUES ('CONSUME', ?, ?, ?, NOW())
        ")->execute([$ref_no ?: null, $remarks !== '' ? $remarks : "consume (used item #{$used_id})", $user_id]);
        $doc_id = (int)$pdo->lastInsertId();

        // Line (qty=1 จาก location 'used')
        $pdo->prepare("
          INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
          VALUES (?, ?, 1, 'used', NULL, NULL)
        ")->execute([$doc_id, $row['part_code']]);

        // LOG ลง parts_used_log ก่อนลบ
        $pdo->prepare("
          INSERT INTO parts_used_log
            (used_id, donor_id, part_code, part_name, part_number, device_models, category,
             image_url, location, remarks, action, consumed_at, created_at)
          VALUES (?,?,?,?,?,?,?,?,?,?, 'CONSUME', NOW(), NOW())
        ")->execute([
          $row['id'] ?? null,
          $row['donor_id'] ?? null,
          $row['part_code'] ?? null,
          $row['part_name'] ?? null,
          $row['part_number'] ?? null,
          $row['device_models'] ?? null,
          $row['category'] ?? null,
          $row['image_url'] ?? null,
          $row['location'] ?? null,
          $row['remarks'] ?? null,
        ]);

        // ลบตัวจริงออก
        $pdo->prepare("DELETE FROM parts_used WHERE id=?")->execute([$used_id]);

        $pdo->commit();
        back_to('used','msg=ตัดจ่ายชิ้นมือ2แล้ว');
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = $e->getMessage();
      }
    }
  }
}

// ========================== [TEMPLATE] ===============================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main restock-main" id="main-content">

  <div class="topbar">
    <span><?= h($pageTitle) ?> <?= $type==='used' ? '(มือ 2)' : '(มือ 1)' ?></span>
    <a href="index.php?tab=<?= $type==='used' ? 'used' : 'new' ?>" class="view-site">← กลับรายการ</a>
  </div>

  
  <div class="section-header">
    <h2>ตัดจ่าย/เบิก</h2>
    <div style="display:flex; gap:8px; align-items:center;">
      <?php if ($type==='new'): ?>
        <a class="btn-secondary" href="consume.php?type=used<?= $used_id ? '&used_id='.(int)$used_id : '' ?>">โหมดมือ 2</a>
      <?php else: ?>
        <a class="btn-secondary" href="consume.php?type=new<?= $code ? '&part_code='.urlencode($code) : '' ?>">โหมดมือ 1</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($type==='new'): ?>
    <?php if ($part_new): ?>
      <!-- สรุปสินค้า (มือ 1) : สไตล์เดียวกับ restock.php -->
      <section class="card part-summary">
        <?php $img = img_src($part_new['image_url'] ?? ''); ?>
        <div class="part-summary__media">
          <?php if ($img): ?>
            <img src="<?= h($img) ?>" class="part-summary__img" alt="">
          <?php else: ?>
            <div class="part-summary__placeholder">ไม่มีรูป</div>
          <?php endif; ?>
        </div>
        <div class="part-summary__body">
          <strong class="part-summary__title"><?= h($part_new['part_name'] ?: $code) ?></strong>
          <div class="muted small">รหัส: <?= h($code) ?> | เลข: <?= h($part_new['part_number']) ?></div>
          <div class="muted small">รุ่น: <?= h($part_new['device_models']) ?> | คงเหลือรวม: <?= (int)$part_new['qty'] ?></div>
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

    <!-- ฟอร์มเบิก มือ 1 -->
    <form method="post" class="card restock-form" novalidate>
      <input type="hidden" name="mode" value="new">
      <div class="form-grid">
        <div class="form-item">
          <label class="form-label" for="part_code">รหัสอะไหล่ *</label>
          <input id="part_code" class="filter-input input" name="part_code" required value="<?= h($code) ?>">
        </div>

        <div class="form-item">
          <label class="form-label" for="location">ที่เก็บ *</label>
          <?php if (!empty($locs)): ?>
            <?php $hasMain=false; foreach($locs as $l){ if(($l['location'] ?? '')==='main') $hasMain=true; } ?>
            <select id="location" name="location" class="filter-input input" required>
              <?php foreach ($locs as $l): ?>
                <option value="<?= h($l['location']) ?>" <?= ($hasMain && $l['location']==='main')?'selected':'' ?>>
                  <?= h($l['location']) ?> (คงเหลือ <?= (int)$l['qty'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input id="location" class="filter-input input" name="location" required value="main">
          <?php endif; ?>
        </div>

        <div class="form-item">
          <label class="form-label" for="qty">จำนวนที่เบิก *</label>
          <input id="qty" class="filter-input input" type="number" min="1" name="qty" required value="1">
        </div>

        <div class="form-item">
          <label class="form-label" for="ref_no">เลขอ้างอิง</label>
          <input id="ref_no" class="filter-input input" name="ref_no" placeholder="ใบงาน, ชื่อช่าง ฯลฯ">
        </div>

        <div class="form-item">
          <label class="form-label" for="remarks">หมายเหตุ</label>
          <input id="remarks" class="filter-input input" name="remarks" placeholder="โน้ตสั้นๆ">
        </div>

        <div class="form-actions">
          <button class="btn-primary" type="submit">บันทึกการเบิก</button>
          <a class="btn-secondary" href="index.php?tab=new">ยกเลิก</a>
        </div>
      </div>
    </form>

  <?php else: ?>
    <?php if ($used): ?>
      <!-- สรุปสินค้า (มือ 2) -->
      <section class="card part-summary">
        <?php $img = img_src($used['image_url'] ?? ''); ?>
        <div class="part-summary__media">
          <?php if ($img): ?>
            <img src="<?= h($img) ?>" class="part-summary__img" alt="">
          <?php else: ?>
            <div class="part-summary__placeholder">ไม่มีรูป</div>
          <?php endif; ?>
        </div>
        <div class="part-summary__body">
          <strong class="part-summary__title"><?= h($used['part_name'] ?: $used['part_code']) ?></strong>
          <div class="muted small">รหัส: <?= h($used['part_code']) ?> | เลข: <?= h($used['part_number']) ?></div>
          <div class="muted small">รุ่น: <?= h($used['device_models']) ?> | ที่เก็บ: <?= h($used['location']) ?></div>
          <?php if (trim((string)$used['remarks'])!==''): ?>
            <div class="muted small">หมายเหตุ: <?= h($used['remarks']) ?></div>
          <?php endif; ?>
        </div>
      </section>
    <?php else: ?>
      <div class="alert alert-info">เลือกชิ้นมือ 2 จากหน้า “มือ 2” แล้วกลับเข้าหน้านี้</div>
    <?php endif; ?>

    <!-- ฟอร์มเบิก มือ 2 -->
    <form method="post" class="card restock-form" novalidate>
      <input type="hidden" name="mode" value="used">
      <input type="hidden" name="used_id" value="<?= (int)$used_id ?>">
      <div class="form-grid">
        <div class="form-item">
          <label class="form-label">รายการ *</label>
          <input class="filter-input input" value="<?= $used ? h($used['part_code'].' — '.$used['part_name']) : '' ?>" disabled>
        </div>

        <div class="form-item">
          <label class="form-label">จำนวน</label>
          <input class="filter-input input" value="1" disabled>
        </div>

        <div class="form-item">
          <label class="form-label" for="ref_no_u">เลขอ้างอิง</label>
          <input id="ref_no_u" class="filter-input input" name="ref_no" placeholder="ใบงาน, ชื่อช่าง ฯลฯ">
        </div>

        <div class="form-item">
          <label class="form-label" for="remarks_u">หมายเหตุ</label>
          <input id="remarks_u" class="filter-input input" name="remarks" placeholder="โน้ตสั้นๆ">
        </div>

        <div class="form-actions">
          <button class="btn-primary" type="submit" <?= $used ? '' : 'disabled' ?>>บันทึกการเบิก</button>
          <a class="btn-secondary" href="index.php?tab=used">ยกเลิก</a>
        </div>
      </div>
    </form>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
