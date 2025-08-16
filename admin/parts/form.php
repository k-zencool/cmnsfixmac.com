<?php
/********************************************************************
 * admin/parts/form.php  (single-form: meta + adjust in one submit)
 * เวอร์ชันตัด is_active ออกทั้งหมด
 *
 * ตารางหลัก:
 *  - parts_new (part_code, part_name, part_number, device_models, category,
 *               image_url, min_stock, location, quantity)
 *  - parts_docs (doc_id, doc_type, ref_no, remarks, user_id, created_at)
 *  - parts_doc_lines (line_id, doc_id, part_code, qty, location_from, location_to, unit_cost)
 ********************************************************************/

// ========== SETUP ==========
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin','manager']);

$pageTitle = "ฟอร์มชนิดอะไหล่";
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

define('UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

function safeUploadName(string $orig): string {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  if ($base==='') $base = 'part';
  return $base . '_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)),0,12) . '.' . $ext;
}

// ค่าพื้นฐาน UI
$categories = ['MacBook','iMac','iPhone','iPad','Apple Watch','Other'];

// รับ part_code (โหมดแก้ไข)
$pc = isset($_GET['part_code']) ? trim($_GET['part_code']) : '';

// สร้างค่าเริ่มต้น
$meta = [
  'part_code'     => $pc,
  'part_name'     => '',
  'part_number'   => '',
  'device_models' => '',
  'category'      => 'MacBook',
  'image_url'     => null,
  'min_stock'     => 0,
];

// โหลด metadata ตัวแทน
if ($pc !== '') {
  $st = $pdo->prepare("
    SELECT part_code, part_name, part_number, device_models, category, image_url, min_stock
    FROM parts_new
    WHERE part_code = ?
    ORDER BY location
    LIMIT 1
  ");
  $st->execute([$pc]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $meta = array_merge($meta, $row);
  }
}

// โหลดยอดคงเหลือต่อโลเคชัน
$locations = [];
if ($meta['part_code'] !== '') {
  $st = $pdo->prepare("
    SELECT location, SUM(quantity) AS qty
    FROM parts_new
    WHERE part_code = ?
    GROUP BY location
    ORDER BY location
  ");
  $st->execute([$meta['part_code']]);
  $locations = $st->fetchAll(PDO::FETCH_ASSOC);
}

// ========== POST: SAVE (meta + adjust) ==========
$errors = [];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_all') {
  // --- รับค่า meta
  $old_code = $pc; // ของเดิมใน URL
  $meta['part_code']     = trim($_POST['part_code'] ?? '');
  $meta['part_name']     = trim($_POST['part_name'] ?? '');
  $meta['part_number']   = trim($_POST['part_number'] ?? '');
  $meta['device_models'] = trim($_POST['device_models'] ?? '');
  $meta['category']      = trim($_POST['category'] ?? 'MacBook');
  $meta['min_stock']     = (int)($_POST['min_stock'] ?? 0);

  if ($meta['part_code'] === '')   $errors[] = "กรอกรหัส Part Code";
  if ($meta['part_name'] === '')   $errors[] = "กรอกชื่ออะไหล่";
  if ($meta['part_number'] === '') $errors[] = "กรอกเลขอะไหล่";
  if (!in_array($meta['category'],$categories,true)) $errors[] = "หมวดหมู่ไม่ถูกต้อง";

  // --- อัปโหลดรูป (ถ้ามี)
  if (!empty($_FILES['image']['name'])) {
    $f = $_FILES['image'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        $errors[] = "ไฟล์รูปต้องเป็น jpg, jpeg, png หรือ webp";
      } elseif ($f['size'] > 5*1024*1024) {
        $errors[] = "ไฟล์รูปใหญ่เกิน 5MB";
      } else {
        $new = safeUploadName($f['name']);
        if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR.$new)) {
          $errors[] = "อัปโหลดรูปไม่สำเร็จ";
        } else {
          $meta['image_url'] = $new;
        }
      }
    } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
      $errors[] = "อัปโหลดรูปผิดพลาด (code {$f['error']})";
    }
  }

  // --- รับค่าปรับยอด
  $adj_loc = '';
  if (isset($_POST['adj_location'])) {
    $v = trim($_POST['adj_location']);
    if ($v === '_new') $adj_loc = trim($_POST['adj_location_new'] ?? '');
    else $adj_loc = $v;
  } else {
    $adj_loc = trim($_POST['adj_location_text'] ?? '');
  }
  $adj_desired = max(0, (int)($_POST['adj_desired'] ?? 0));
  $ref_no      = trim($_POST['ref_no'] ?? '');
  $remarks     = trim($_POST['remarks'] ?? '');

  if ($adj_loc === '') $errors[] = "กรอก/เลือกโลเคชันที่จะปรับยอด";

  $user_id = $_SESSION['admin_id'] ?? ($_SESSION['user']['id'] ?? null);
  if (!$user_id) $errors[] = "ไม่พบผู้ใช้งาน (session)";

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // มีแถว part_code เดิมไหม
      $exists = 0;
      if ($old_code !== '') {
        $st = $pdo->prepare("SELECT COUNT(*) FROM parts_new WHERE part_code=?");
        $st->execute([$old_code]);
        $exists = (int)$st->fetchColumn();
      }

      // ถ้ายังไม่มี -> แทรกแถวตั้งต้น (location=main, quantity=0)
      if ($exists === 0) {
        $pdo->prepare("
          INSERT INTO parts_new (part_code, part_name, part_number, device_models, category,
                                 image_url, min_stock, location, quantity)
          VALUES (?,?,?,?,?,?,?,?, 0)
        ")->execute([
          $meta['part_code'], $meta['part_name'], $meta['part_number'], $meta['device_models'],
          $meta['category'], $meta['image_url'], $meta['min_stock'], 'main'
        ]);
        $old_code = $meta['part_code'];
      }

      // อัปเดต metadata ทุกแถวของ part_code เดิม
      if ($old_code === $meta['part_code']) {
        $pdo->prepare("
          UPDATE parts_new
          SET part_name=?, part_number=?, device_models=?, category=?,
              image_url = COALESCE(?, image_url),  -- มีรูปใหม่ค่อยแทน
              min_stock=?
          WHERE part_code=?
        ")->execute([
          $meta['part_name'], $meta['part_number'], $meta['device_models'], $meta['category'],
          $meta['image_url'], $meta['min_stock'], $old_code
        ]);
      } else {
        // เปลี่ยนรหัส part_code: ย้ายทั้งชุด + อัปเดตบรรทัดเอกสารให้ด้วย
        $pdo->prepare("
          UPDATE parts_new
          SET part_code=?, part_name=?, part_number=?, device_models=?, category=?,
              image_url=COALESCE(?, image_url), min_stock=min_stock
          WHERE part_code=?
        ")->execute([
          $meta['part_code'], $meta['part_name'], $meta['part_number'], $meta['device_models'],
          $meta['category'], $meta['image_url'], $old_code
        ]);
        $pdo->prepare("UPDATE parts_doc_lines SET part_code=? WHERE part_code=?")
            ->execute([$meta['part_code'], $old_code]);
        $old_code = $meta['part_code'];
      }

      // ===== ปรับยอด (ADJUST) =====
      $st = $pdo->prepare("
        SELECT COALESCE(SUM(quantity),0)
        FROM parts_new
        WHERE part_code=? AND location=?
        FOR UPDATE
      ");
      $st->execute([$meta['part_code'], $adj_loc]);
      $currentQty = (int)$st->fetchColumn();
      $delta = $adj_desired - $currentQty;

      if ($delta !== 0) {
        // header ADJUST
        $pdo->prepare("
          INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
          VALUES ('ADJUST', ?, ?, ?, NOW())
        ")->execute([$ref_no ?: null, $remarks ?: "manual adjust ($adj_loc)", $user_id]);
        $doc_id = (int)$pdo->lastInsertId();

        // line
        $pdo->prepare("
          INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
          VALUES (?,?,?,?,?,NULL)
        ")->execute([$doc_id, $meta['part_code'], $delta, $delta<0?$adj_loc:null, $delta>0?$adj_loc:null]);

        // ให้มีแถว location นี้ก่อน (ถ้ายังไม่เคยมี)
        $stC = $pdo->prepare("SELECT COUNT(*) FROM parts_new WHERE part_code=? AND location=?");
        $stC->execute([$meta['part_code'], $adj_loc]);
        if ((int)$stC->fetchColumn() === 0) {
          $pdo->prepare("
            INSERT INTO parts_new (part_code, part_name, part_number, device_models, category,
                                   image_url, min_stock, location, quantity)
            SELECT part_code, part_name, part_number, device_models, category,
                   image_url, min_stock, ?, 0
            FROM parts_new
            WHERE part_code=?
            LIMIT 1
          ")->execute([$adj_loc, $meta['part_code']]);
        }

        // ปรับยอด
        $stHave = $pdo->prepare("SELECT SUM(quantity) FROM parts_new WHERE part_code=? AND location=? FOR UPDATE");
        $stHave->execute([$meta['part_code'], $adj_loc]);
        $have = (int)$stHave->fetchColumn();
        if ($have + $delta < 0) {
          throw new Exception("คงเหลือที่ {$adj_loc} ไม่พอจะปรับเป็น {$adj_desired}");
        }

        $pdo->prepare("
          UPDATE parts_new
          SET quantity = quantity + ?
          WHERE part_code=? AND location=?
        ")->execute([$delta, $meta['part_code'], $adj_loc]);
      }

      $pdo->commit();
      header("Location: index.php?tab=new&msg=".urlencode("บันทึกข้อมูลเรียบร้อย"));
      exit;
    } catch (Throwable $ex) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = $ex->getMessage();
    }
  }
}

// ========= TEMPLATE =========
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?> <?= $meta['part_code'] ? ' - แก้ไข: '.h($meta['part_code']) : '' ?></span>
    <a href="index.php?tab=new" class="btn-secondary">← กลับรายการ</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $e) echo '<div>'.h($e).'</div>'; ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card" style="padding:16px;border-radius:12px;">
    <input type="hidden" name="action" value="save_all">

    <div class="table-container">
      <table class="data-table">
        <tbody>
          <tr>
            <th style="width:220px;">Part Code *</th>
            <td><input type="text" name="part_code" class="filter-input" required value="<?= h($meta['part_code']) ?>" placeholder="เช่น BAT-A1819-MB"></td>
          </tr>
          <tr>
            <th>ชื่ออะไหล่ *</th>
            <td><input type="text" name="part_name" class="filter-input" required value="<?= h($meta['part_name']) ?>" placeholder="เช่น BATTERY A1819"></td>
          </tr>
          <tr>
            <th>เลขอะไหล่ *</th>
            <td><input type="text" name="part_number" class="filter-input" required value="<?= h($meta['part_number']) ?>" placeholder="เช่น A1819 หรือ 661-xxxx"></td>
          </tr>
          <tr>
            <th>ใช้กับรุ่น (Model)</th>
            <td><input type="text" name="device_models" class="filter-input" value="<?= h($meta['device_models']) ?>" placeholder="เช่น A1706, A1708"></td>
          </tr>
          <tr>
            <th>หมวดหมู่</th>
            <td>
              <select name="category" class="filter-input">
                <?php foreach ($categories as $c): ?>
                  <option value="<?= h($c) ?>" <?= $meta['category'] === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>

          <?php
            $defLoc = 'main'; $defQty = 0;
            if (!empty($locations)) {
              $hasMain = false;
              foreach ($locations as $r) if ($r['location']==='main') { $hasMain=true; $defQty=(int)$r['qty']; }
              if ($hasMain) $defLoc='main';
              else { $defLoc = $locations[0]['location']; $defQty=(int)$locations[0]['qty']; }
            }
          ?>

          <tr>
            <th>โลเคชัน *</th>
            <td>
              <?php if (!empty($locations)): ?>
                <select name="adj_location" id="adj_location" class="filter-input" required style="max-width:260px;">
                  <?php foreach ($locations as $r): $L=$r['location']; $Q=(int)$r['qty']; ?>
                    <option value="<?= h($L) ?>" data-qty="<?= $Q ?>" <?= $L===$defLoc?'selected':'' ?>>
                      <?= h($L) ?> (คงเหลือ <?= $Q ?>)
                    </option>
                  <?php endforeach; ?>
                  <option value="_new">+ เพิ่มใหม่…</option>
                </select>
                <input type="text" name="adj_location_new" id="adj_location_new" class="filter-input" placeholder="พิมพ์โลเคชันใหม่" style="display:none;max-width:220px;">
              <?php else: ?>
                <input type="text" name="adj_location_text" id="adj_location_text" class="filter-input" required value="main" placeholder="เช่น main, shelf-A" style="max-width:220px;">
              <?php endif; ?>
            </td>
          </tr>

          <tr>
            <th>ปรับจำนวนคงเหลือ *</th>
            <td>
              <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span class="muted">คงเหลือปัจจุบัน</span>
                <input type="text" id="adj_current" class="filter-input" value="<?= (int)$defQty ?>" readonly style="max-width:120px;">
                <span class="muted">ปรับเป็น (จำนวนใหม่)</span>
                <input type="number" name="adj_desired" id="adj_desired" class="filter-input" min="0" required value="<?= (int)$defQty ?>" style="max-width:160px;">
              </div>
              <div class="muted" style="margin-top:6px;">ระบบจะคำนวณส่วนต่างแล้วทำเอกสาร ADJUST ให้อัตโนมัติ</div>
            </td>
          </tr>

          <tr>
            <th>ขั้นต่ำ (เตือนต่ำ)</th>
            <td><input type="number" name="min_stock" class="filter-input" min="0" value="<?= (int)$meta['min_stock'] ?>"></td>
          </tr>

          <tr>
            <th>รูปภาพ</th>
            <td>
              <?php if (!empty($meta['image_url'])): ?>
                <div style="display:flex;align-items:center;gap:12px;">
                  <img src="../../uploads/parts/<?= h($meta['image_url']) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:10px;border:1px solid #eee;">
                  <span class="muted"><?= h($meta['image_url']) ?></span>
                </div>
                <div style="height:8px;"></div>
              <?php endif; ?>
              <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="filter-input">
              <div class="muted" style="font-size:12px;">รองรับ jpg, jpeg, png, webp ขนาดไม่เกิน 5MB</div>
            </td>
          </tr>

          <tr>
            <th>เลขอ้างอิง</th>
            <td><input type="text" name="ref_no" class="filter-input" placeholder="ใบงาน/ชื่อช่าง ฯลฯ"></td>
          </tr>
          <tr>
            <th>หมายเหตุ</th>
            <td><input type="text" name="remarks" class="filter-input" placeholder="โน้ตสั้นๆ"></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div style="display:flex;gap:10px;margin-top:14px;">
      <button class="btn-primary" type="submit">บันทึก</button>
      <a class="btn-secondary" href="index.php?tab=new">ยกเลิก</a>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
// อัปเดตคงเหลือเมื่อเปลี่ยนโลเคชัน + toggle ช่องเพิ่มใหม่
(function(){
  var sel = document.getElementById('adj_location');
  var boxNew = document.getElementById('adj_location_new');
  var cur = document.getElementById('adj_current');
  var des = document.getElementById('adj_desired');
  if (!sel) return;

  function refresh(){
    if (sel.value === '_new') {
      if (boxNew) boxNew.style.display = '';
      if (cur) cur.value = 0;
      if (des && (des.value==='' || +des.value<0)) des.value = 0;
    } else {
      if (boxNew) boxNew.style.display = 'none';
      var opt = sel.options[sel.selectedIndex];
      var q = opt ? (opt.getAttribute('data-qty')||'0') : '0';
      if (cur) cur.value = q;
      if (des && (des.value==='' || +des.value<0)) des.value = q;
    }
  }
  sel.addEventListener('change', refresh);
  refresh();
})();
</script>
