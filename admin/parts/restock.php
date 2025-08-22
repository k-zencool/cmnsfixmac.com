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
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function img_src($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  if (preg_match('~^https?://~i',$v) || $v[0]==='/') return $v;
  return '../../uploads/parts/'.$v;
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
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '') === 'restock') {
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
          'part_code'=>$code, 'part_name'=>'', 'part_number'=>'',
          'device_models'=>'', 'category'=>'Other', 'image_url'=>null, 'min_stock'=>0
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
          $m['part_code'], $m['part_name'], $m['part_number'], $m['device_models'],
          $m['category'], $m['image_url'], (int)$m['min_stock'], $location
        ]);
      }

      // อัปเดตสต็อก
      $pdo->prepare("
        UPDATE parts_new
        SET quantity = quantity + ?
        WHERE part_code=? AND location=?
      ")->execute([$qty, $code, $location]);

      $pdo->commit();
      header("Location: index.php?tab=new&msg=".urlencode("รับเข้าเรียบร้อย"));
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
    <a class="btn-secondary" href="index.php?tab=new">← กลับรายการ</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($meta): ?>
    <div class="card" style="padding:12px;border-radius:10px;margin-bottom:12px;">
      <div style="display:flex;gap:12px;align-items:center;">
        <?php $img = img_src($meta['image_url'] ?? ''); ?>
        <?php if ($img): ?>
          <img src="<?= h($img) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #eee;" alt="">
        <?php else: ?>
          <div style="width:56px;height:56px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">ไม่มีรูป</div>
        <?php endif; ?>
        <div>
          <strong><?= h($meta['part_name'] ?: $code) ?></strong>
          <div class="muted" style="font-size:12px;">รหัส: <?= h($code) ?> | เลข: <?= h($meta['part_number']) ?></div>
          <div class="muted" style="font-size:12px;">รุ่น: <?= h($meta['device_models']) ?> | คงเหลือรวม: <?= (int)$meta['qty'] ?></div>
          <?php if ($locs): ?>
            <div class="muted" style="font-size:12px;margin-top:4px;">
              <?php foreach ($locs as $l): ?>
                <span class="badge" style="margin-right:6px"><?= h($l['location']) ?>: <?= (int)$l['qty'] ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" class="card" style="padding:16px;border-radius:12px;max-width:760px;">
    <input type="hidden" name="action" value="restock">

    <div class="table-container">
      <table class="data-table"><tbody>
        <tr>
          <th style="width:220px;">รหัสอะไหล่ *</th>
          <td><input class="filter-input" name="part_code" required value="<?= h($code) ?>"></td>
        </tr>

        <tr>
          <th>รับเข้าไปที่ *</th>
          <td>
            <?php if (!empty($locs)): ?>
              <select name="location" id="location" class="filter-input" required style="max-width:260px;">
                <?php
                  $def='main'; $hasMain=false;
                  foreach($locs as $l){ if($l['location']==='main') $hasMain=true; }
                ?>
                <?php foreach ($locs as $l): ?>
                  <option value="<?= h($l['location']) ?>" data-qty="<?= (int)$l['qty'] ?>" <?= ($hasMain && $l['location']==='main')?'selected':'' ?>>
                    <?= h($l['location']) ?> (คงเหลือ <?= (int)$l['qty'] ?>)
                  </option>
                <?php endforeach; ?>
                <option value="_new">+ เพิ่มใหม่…</option>
              </select>
              <input type="text" name="location_new" id="location_new" class="filter-input" placeholder="พิมพ์โลเคชันใหม่" style="display:none;max-width:220px;">
            <?php else: ?>
              <input class="filter-input" name="location" required value="main" style="max-width:220px;">
            <?php endif; ?>
          </td>
        </tr>

        <tr>
          <th>จำนวนที่รับเข้า *</th>
          <td><input class="filter-input" type="number" min="1" name="qty" required value="1" style="max-width:160px;"></td>
        </tr>

        <tr>
          <th>ต้นทุน/หน่วย</th>
          <td><input class="filter-input" type="number" step="0.01" name="unit_cost" placeholder="เช่น 250.00" style="max-width:180px;"></td>
        </tr>

        <tr>
          <th>เลขอ้างอิง</th>
          <td><input class="filter-input" name="ref_no" placeholder="ใบงาน, เลข PO ฯลฯ"></td>
        </tr>

        <tr>
          <th>หมายเหตุ</th>
          <td><input class="filter-input" name="remarks" placeholder="โน้ตสั้นๆ"></td>
        </tr>
      </tbody></table>
    </div>

    <div style="display:flex;gap:10px;margin-top:14px;">
      <button class="btn-primary" type="submit">บันทึกรับเข้า</button>
      <a class="btn-secondary" href="index.php?tab=new">ยกเลิก</a>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
// toggle ช่อง "เพิ่มโลเคชันใหม่" และโชว์คงเหลือของที่เลือก
(function(){
  var sel = document.getElementById('location');
  var boxNew = document.getElementById('location_new');
  if (!sel) return;
  function refresh(){
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
