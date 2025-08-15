<?php
/********************************************************************
 * admin/parts/form.php
 * แก้ไขข้อมูลชนิดอะไหล่ (metadata จาก parts_new ตัวแทนของ part_code)
 * + ปรับจำนวนคงเหลือต่อโลเคชันด้วยเอกสาร ADJUST
 *
 * ตารางที่ใช้:
 *  - parts_new (part_code, part_name, part_number, device_models, category,
 *               image_url, min_stock, is_active, location, quantity)
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

// รับ part_code จาก query (ถ้ามาจาก index ปุ่มแก้ไข)
$pc = isset($_GET['part_code']) ? trim($_GET['part_code']) : '';
$errors = [];
$msg = '';

// ค่าพื้นฐาน
$categories = ['MacBook','iMac','iPhone','iPad','Apple Watch','Other'];

// ========== โหลดข้อมูลเดิม ==========
$meta = [
  'part_code'     => $pc,
  'part_name'     => '',
  'part_number'   => '',
  'device_models' => '',
  'category'      => 'MacBook',
  'image_url'     => null,
  'min_stock'     => 0,
  'is_active'     => 1
];

// ดึง metadata ตัวแทนจาก parts_new แถวใดแถวหนึ่งของ part_code
if ($pc !== '') {
  $st = $pdo->prepare("
    SELECT part_code, part_name, part_number, device_models, category, image_url, min_stock, is_active
    FROM parts_new
    WHERE part_code = ?
    ORDER BY location
    LIMIT 1
  ");
  $st->execute([$pc]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $meta = array_merge($meta, $row);
  } else {
    // ไม่เคยมี part_code นี้ ให้เริ่มว่างๆ เพื่อ “สร้างใหม่”
    $meta['part_code'] = $pc;
  }
}

// ดึงยอดคงเหลือต่อโลเคชันของ part_code (ไว้ให้แก้จำนวน)
$locations = [];
if ($pc !== '') {
  $st = $pdo->prepare("
    SELECT location, SUM(quantity) AS qty
    FROM parts_new
    WHERE part_code = ?
    GROUP BY location
    ORDER BY location
  ");
  $st->execute([$pc]);
  $locations = $st->fetchAll(PDO::FETCH_ASSOC);
}

// ========== POST: บันทึก metadata ==========
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '') === 'save_meta') {
  $meta['part_code']     = trim($_POST['part_code'] ?? '');
  $meta['part_name']     = trim($_POST['part_name'] ?? '');
  $meta['part_number']   = trim($_POST['part_number'] ?? '');
  $meta['device_models'] = trim($_POST['device_models'] ?? '');
  $meta['category']      = trim($_POST['category'] ?? 'MacBook');
  $meta['min_stock']     = (int)($_POST['min_stock'] ?? 0);
  $meta['is_active']     = isset($_POST['is_active']) ? 1 : 0;

  if ($meta['part_code']==='')   $errors[] = "กรอกรหัส part_code";
  if ($meta['part_name']==='')   $errors[] = "กรอกชื่ออะไหล่";
  if ($meta['part_number']==='') $errors[] = "กรอกเลขอะไหล่";
  if (!in_array($meta['category'],$categories,true)) $errors[] = "หมวดหมู่ไม่ถูกต้อง";

  // อัปโหลดรูปถ้ามี
  $uploadName = null;
  if (!empty($_FILES['image']['name'])) {
    $f = $_FILES['image'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      $allow = ['jpg','jpeg','png','webp'];
      if (!in_array($ext,$allow,true)) {
        $errors[] = "ไฟล์รูปต้องเป็น jpg, jpeg, png หรือ webp";
      } elseif ($f['size'] > 5*1024*1024) {
        $errors[] = "ไฟล์รูปใหญ่เกิน 5MB";
      } else {
        $uploadDir = __DIR__ . '/../../uploads/parts/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir,0775,true);
        $uploadName = 'part_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)),0,12) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $uploadDir.$uploadName)) {
          $errors[] = "อัปโหลดรูปไม่สำเร็จ";
          $uploadName = null;
        }
      }
    } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
      $errors[] = "อัปโหลดรูปผิดพลาด (code {$f['error']})";
    }
  }

  if (!$errors) {
    // ถ้ามีรูปใหม่ แทนที่
    if ($uploadName) $meta['image_url'] = $uploadName;

    // อัปเดตทุกแถวของ part_code ให้ metadata ตรงกัน
    // ถ้ายังไม่มีแถวเลย เราจะสร้างแถวเปล่าที่ location=main, quantity=0
    try {
      $pdo->beginTransaction();

      $chk = $pdo->prepare("SELECT COUNT(*) FROM parts_new WHERE part_code=?");
      $chk->execute([$meta['part_code']]);
      $exists = (int)$chk->fetchColumn();

      if ($exists === 0) {
        $ins = $pdo->prepare("
          INSERT INTO parts_new (part_code, part_name, part_number, device_models, category, image_url, min_stock, is_active, location, quantity)
          VALUES (?,?,?,?,?,?,?,?,?,0)
        ");
        $ins->execute([
          $meta['part_code'], $meta['part_name'], $meta['part_number'], $meta['device_models'],
          $meta['category'], $meta['image_url'], $meta['min_stock'], $meta['is_active'], 'main'
        ]);
      } else {
        $upd = $pdo->prepare("
          UPDATE parts_new
          SET part_name=?, part_number=?, device_models=?, category=?, image_url=?, min_stock=?, is_active=?
          WHERE part_code=?
        ");
        $upd->execute([
          $meta['part_name'], $meta['part_number'], $meta['device_models'], $meta['category'],
          $meta['image_url'], $meta['min_stock'], $meta['is_active'],
          $meta['part_code']
        ]);
      }

      $pdo->commit();
      $msg = "บันทึกข้อมูลเรียบร้อย";
      // ปรับ URL ให้อยู่กับ part_code ปัจจุบัน
      header("Location: form.php?part_code=".urlencode($meta['part_code'])."&saved=1");
      exit;
    } catch (Throwable $ex) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = $ex->getMessage();
    }
  }
}

// ========== POST: ปรับจำนวนคงเหลือ (ADJUST) ==========
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '') === 'adjust_qty') {
  $pcPost = trim($_POST['part_code'] ?? '');
  if ($pcPost === '') $errors[] = "ไม่มี part_code";

  // รับชุด location[] และ desired_qty[]
  $locs = $_POST['location'] ?? [];
  $des  = $_POST['desired_qty'] ?? [];

  // ผู้ทำรายการ
  $user_id = $_SESSION['admin_id'] ?? null; // แก้ให้ตรงระบบของมึง
  if (!$user_id) $errors[] = "ไม่พบผู้ใช้งาน (session)";

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // สร้างหัวเอกสาร ADJUST เดียว แล้วใส่หลายบรรทัด
      $ref  = trim($_POST['ref_no'] ?? '');
      $note = trim($_POST['remarks'] ?? 'manual adjust via form');

      $pdo->prepare("INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id) VALUES ('ADJUST', ?, ?, ?)")
          ->execute([$ref ?: null, $note, $user_id]);
      $doc_id = (int)$pdo->lastInsertId();

      // ดึงยอดปัจจุบันต่อโลเคชันไว้เทียบ
      $curMap = [];
      $stCur = $pdo->prepare("SELECT location, SUM(quantity) qty FROM parts_new WHERE part_code=? GROUP BY location");
      $stCur->execute([$pcPost]);
      foreach ($stCur->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $curMap[$r['location']] = (int)$r['qty'];
      }

      // ปรับทีละโลเคชัน
      for ($i=0; $i<count($locs); $i++) {
        $L   = trim($locs[$i]);
        $dst = max(0, (int)$des[$i]); // ไม่ให้ติดลบ
        $cur = (int)($curMap[$L] ?? 0);
        $delta = $dst - $cur; // อยากได้เท่าไรลบของเดิม เหลือส่วนต่างที่ต้องบวก/ลบ

        if ($delta === 0) continue; // ไม่ต้องทำอะไร

        // เขียนบรรทัดเอกสาร (qty จะเป็นค่าบวกหรือลบก็ได้สำหรับ ADJUST)
        $pdo->prepare("
          INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
          VALUES (?,?,?,?,?,NULL)
        ")->execute([
          $doc_id, $pcPost, $delta, $delta<0 ? $L : null, $delta>0 ? $L : null
        ]);

        // อัปเดตยอดใน parts_new
        if ($delta > 0) {
          // เพิ่มของ: หากไม่มีแถว location นี้ ให้สร้าง
          $pdo->prepare("
            INSERT INTO parts_new (part_code, part_name, part_number, device_models, category, image_url, min_stock, is_active, location, quantity)
            SELECT part_code, part_name, part_number, device_models, category, image_url, min_stock, is_active, ?, 0
            FROM parts_new WHERE part_code=? LIMIT 1
          ")->execute([$L, $pcPost]); // เฉพาะกรณีไม่มี
          $pdo->prepare("
            UPDATE parts_new SET quantity = quantity + ?
            WHERE part_code=? AND location=?
          ")->execute([$delta, $pcPost, $L]);
        } else {
          // ลดของ: ต้องไม่ให้ติดลบ
          $stHave = $pdo->prepare("SELECT SUM(quantity) FROM parts_new WHERE part_code=? AND location=? FOR UPDATE");
          $stHave->execute([$pcPost,$L]);
          $have = (int)$stHave->fetchColumn();
          if ($have + $delta < 0) { // delta เป็นลบ
            throw new Exception("คงเหลือที่ {$L} ไม่พอจะปรับเป็น {$dst}");
          }
          // ตัดออก delta เป็นลบ เช่น -3
          $pdo->prepare("
            UPDATE parts_new SET quantity = quantity + ?
            WHERE part_code=? AND location=?
          ")->execute([$delta, $pcPost, $L]);
        }
      }

      $pdo->commit();
      header("Location: form.php?part_code=".urlencode($pcPost)."&adjusted=1");
      exit;
    } catch (Throwable $ex) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = $ex->getMessage();
    }
  }
}

// แสดงข้อความ flash
if (!empty($_GET['saved']))    $msg = "บันทึกข้อมูลเรียบร้อย";
if (!empty($_GET['adjusted'])) $msg = "ปรับจำนวนเรียบร้อย";

// ========== TEMPLATE ==========
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
  </div>

  <div class="section-header">
    <h2><?= $pc ? 'แก้ไข: '.h($pc) : 'เพิ่มชนิดใหม่' ?></h2>
    <a href="index.php?tab=new" class="btn-secondary">← กลับรายการ</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ============ ส่วนที่ 1: ฟอร์มข้อมูลชนิดอะไหล่ (metadata) ============ -->
  <form method="post" enctype="multipart/form-data" class="card" style="padding:16px;border-radius:12px;margin-bottom:16px;">
    <input type="hidden" name="action" value="save_meta">
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
                <?php foreach($categories as $c): ?>
                  <option value="<?= h($c) ?>" <?= $meta['category']===$c?'selected':'' ?>><?= h($c) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th>ขั้นต่ำ (เตือนต่ำ)</th>
            <td><input type="number" name="min_stock" class="filter-input" min="0" value="<?= (int)$meta['min_stock'] ?>"></td>
          </tr>
          <tr>
            <th>สถานะใช้งาน</th>
            <td>
              <label class="checkline">
                <input type="checkbox" name="is_active" value="1" <?= $meta['is_active']? 'checked':'' ?>>
                <span>ใช้งานอยู่</span>
              </label>
            </td>
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
        </tbody>
      </table>
    </div>
    <div style="display:flex;gap:10px;margin-top:14px;">
      <button class="btn-primary" type="submit">บันทึก</button>
      <a class="btn-secondary" href="index.php?tab=new">ยกเลิก</a>
    </div>
  </form>

  <!-- ============ ส่วนที่ 2: ปรับจำนวนคงเหลือต่อโลเคชัน (ADJUST) ============ -->
  <form method="post" class="card" style="padding:16px;border-radius:12px;">
    <input type="hidden" name="action" value="adjust_qty">
    <input type="hidden" name="part_code" value="<?= h($meta['part_code']) ?>">

    <h3 style="margin:0 0 10px;">ปรับจำนวนคงเหลือ</h3>
    <div class="muted" style="margin-bottom:10px;">กรอก “จำนวนที่อยากให้เป็น” ระบบจะคำนวณส่วนต่างแล้วทำเอกสาร ADJUST ให้อัตโนมัติ</div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>โลเคชัน</th>
            <th>คงเหลือปัจจุบัน</th>
            <th>ปรับเป็น (จำนวนใหม่)</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($locations): foreach($locations as $r): ?>
            <tr>
              <td>
                <input type="text" name="location[]" class="filter-input" value="<?= h($r['location']) ?>" readonly>
              </td>
              <td class="muted" style="min-width:100px;"><?= (int)$r['qty'] ?></td>
              <td>
                <input type="number" name="desired_qty[]" class="filter-input" min="0" value="<?= (int)$r['qty'] ?>">
              </td>
            </tr>
          <?php endforeach; else: ?>
            <!-- ถ้ายังไม่มี location เลย ให้เริ่มที่ main -->
            <tr>
              <td><input type="text" name="location[]" class="filter-input" value="main"></td>
              <td class="muted">0</td>
              <td><input type="number" name="desired_qty[]" class="filter-input" min="0" value="0"></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div style="display:flex;gap:10px;align-items:center;margin-top:10px;">
      <input type="text" name="ref_no" class="filter-input" placeholder="เลขอ้างอิง (ถ้ามี)" style="max-width:220px;">
      <input type="text" name="remarks" class="filter-input" placeholder="หมายเหตุ" style="flex:1;">
      <button class="btn-primary" type="submit">บันทึกการปรับ</button>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
