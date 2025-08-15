<?php
// admin/parts/restock.php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'manager']);

$pageTitle = "เติมสต็อก (มือ 1)";

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$errors = [];
$msg = '';
$code = trim($_GET['part_code'] ?? '');

// โหลดสรุปอะไหล่ไว้โชว์หัวฟอร์ม ถ้ามี part_code
$part = null;
if ($code !== '') {
  $st = $pdo->prepare("
    SELECT
      pn.part_code,
      MAX(pn.part_name)       AS part_name,
      MAX(pn.part_number)     AS part_number,
      MAX(pn.device_models)   AS device_models,
      MAX(pn.category)        AS category,
      MAX(pn.image_url)       AS image_url,
      MAX(pn.min_stock)       AS min_stock,
      MAX(pn.is_active)       AS is_active,
      SUM(pn.quantity)        AS qty
    FROM parts_new pn
    WHERE pn.part_code = ?
    GROUP BY pn.part_code
    LIMIT 1
  ");
  $st->execute([$code]);
  $part = $st->fetch(PDO::FETCH_ASSOC);
}

// บันทึก
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code      = trim($_POST['part_code'] ?? '');
  $location  = trim($_POST['location'] ?? 'main');
  $qty       = max(0, (int)($_POST['qty'] ?? 0));
  $unit_cost = strlen($_POST['unit_cost'] ?? '') ? (float)$_POST['unit_cost'] : null;
  $ref_no    = trim($_POST['ref_no'] ?? '');
  $remarks   = trim($_POST['remarks'] ?? '');
  $user_id   = $_SESSION['admin_id'] ?? 1;

  // meta เฉพาะกรณี part_code ยังไม่เคยมีมาก่อนในตาราง (อนุญาตเติมครั้งแรก)
  $part_name     = trim($_POST['part_name'] ?? '');
  $part_number   = trim($_POST['part_number'] ?? '');
  $device_models = trim($_POST['device_models'] ?? '');
  $category      = trim($_POST['category'] ?? '');

  if ($code === '') $errors[] = "กรอกรหัสอะไหล่ (part_code)";
  if ($qty <= 0)    $errors[] = "จำนวนต้องมากกว่า 0";

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // เอกสารหัว
      $pdo->prepare("INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id)
                     VALUES ('IN', ?, ?, ?)")
          ->execute([$ref_no ?: null, $remarks !== '' ? $remarks : "receive into {$location}", $user_id]);
      $doc_id = (int)$pdo->lastInsertId();

      // ไลน์เอกสาร
      $pdo->prepare("INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_to, unit_cost)
                     VALUES (?,?,?,?,?)")
          ->execute([$doc_id, $code, $qty, $location, $unit_cost]);

      // อัปเดตคงเหลือ (ถ้ายังไม่มีแถวนี้มาก่อน ให้ใส่เมทาดาต้าตามที่ส่งมา)
      $pdo->prepare("
        INSERT INTO parts_new
          (part_code, part_name, part_number, device_models, category, image_url, location, quantity, min_stock, is_active)
        VALUES
          (?, ?, ?, ?, ?, NULL, ?, ?, 0, 1)
        ON DUPLICATE KEY UPDATE
          quantity = quantity + VALUES(quantity)
      ")->execute([$code, $part_name, $part_number, $device_models, $category, $location, $qty]);

      // บันทึกประวัติอย่างง่าย ถ้าตาราง parts_history มี
      try {
        $pdo->prepare("INSERT INTO parts_history (event_type, part_code, ref_doc_id, payload, user_id)
                       VALUES ('new_in', ?, ?, JSON_OBJECT('location', ?, 'qty', ?, 'unit_cost', ?), ?)")
            ->execute([$code, $doc_id, $location, $qty, $unit_cost, $user_id]);
      } catch (Throwable $ignore) {
        // ถ้ายังไม่ได้สร้างตาราง history ก็ปล่อยผ่าน ไม่ใช่วันสิ้นโลก
      }

      $pdo->commit();
      header("Location: index.php?tab=new&restocked=1");
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = $e->getMessage();
    }
  }
}

// โหลดอีกทีหลังโพสต์พัง จะได้โชว์หัวฟอร์ม
if ($code !== '' && !$part) {
  $part = [
    'part_code' => $code, 'part_name' => '', 'part_number' => '', 'device_models' => '',
    'category' => '', 'image_url' => null, 'min_stock' => 0, 'is_active' => 1, 'qty' => 0
  ];
}

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
  </div>

  <div class="section-header">
    <h2>เติมสต็อก (มือ 1)</h2>
    <a href="index.php?tab=new" class="btn-secondary">← กลับรายการ</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($part): ?>
    <div class="card" style="padding:12px;border-radius:10px;margin-bottom:12px;">
      <div style="display:flex;gap:12px;align-items:center;">
        <?php if (!empty($part['image_url'])): ?>
          <img src="../../uploads/parts/<?= h($part['image_url']) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #eee;">
        <?php else: ?>
          <div style="width:56px;height:56px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">
            ไม่มีรูป
          </div>
        <?php endif; ?>
        <div>
          <div><strong><?= h($part['part_name'] ?: '(ยังไม่ตั้งชื่อ)') ?></strong></div>
          <div class="muted" style="font-size:12px;">รหัส: <?= h($part['part_code']) ?> | เลขอะไหล่: <?= h($part['part_number']) ?></div>
          <div class="muted" style="font-size:12px;">รุ่น: <?= h($part['device_models']) ?> | ประเภท: <?= h($part['category']) ?></div>
          <div class="muted" style="font-size:12px;">คงเหลือรวม: <?= (int)$part['qty'] ?></div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" class="card" style="padding:16px;border-radius:12px;max-width:720px;">
    <div class="table-container">
      <table class="data-table">
        <tbody>
          <tr>
            <th style="width:220px;">รหัสอะไหล่ (part_code) *</th>
            <td>
              <input type="text" name="part_code" class="filter-input" required value="<?= h($code) ?>" placeholder="เช่น BAT-A1819-MAC">
              <div class="muted" style="font-size:12px;">ต้องตรงกับรหัสที่ใช้ในหน้ารายการ</div>
            </td>
          </tr>

          <?php if (!$part || ($part && !$part['part_name'])): // เผื่อเติมครั้งแรก ยังไม่มี meta ?>
          <tr>
            <th>ชื่ออะไหล่ (ครั้งแรกเท่านั้น)</th>
            <td><input type="text" name="part_name" class="filter-input" placeholder="เช่น BATTERY A1819"></td>
          </tr>
          <tr>
            <th>เลขอะไหล่</th>
            <td><input type="text" name="part_number" class="filter-input" placeholder="เช่น A1819 หรือ 661-02536"></td>
          </tr>
          <tr>
            <th>ใช้กับรุ่น</th>
            <td><input type="text" name="device_models" class="filter-input" placeholder="เช่น A1706, A1708"></td>
          </tr>
          <tr>
            <th>หมวดหมู่</th>
            <td><input type="text" name="category" class="filter-input" placeholder="เช่น MacBook, iPhone"></td>
          </tr>
          <?php endif; ?>

          <tr>
            <th>ที่เก็บ (location) *</th>
            <td><input type="text" name="location" class="filter-input" required value="main"></td>
          </tr>
          <tr>
            <th>จำนวนรับเข้า *</th>
            <td><input type="number" name="qty" class="filter-input" min="1" required value="1"></td>
          </tr>
          <tr>
            <th>ต้นทุนต่อหน่วย</th>
            <td><input type="number" step="0.01" name="unit_cost" class="filter-input" placeholder="ตัวเลขทศนิยมได้"></td>
          </tr>
          <tr>
            <th>เลขอ้างอิง (Ref No.)</th>
            <td><input type="text" name="ref_no" class="filter-input" placeholder="PO-xxx, ใบเสร็จ ฯลฯ"></td>
          </tr>
          <tr>
            <th>หมายเหตุ</th>
            <td><input type="text" name="remarks" class="filter-input" placeholder="บันทึกเพิ่มเติม"></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div style="display:flex;gap:10px;margin-top:14px;">
      <button class="btn-primary" type="submit">บันทึกการเติมสต็อก</button>
      <a class="btn-secondary" href="index.php?tab=new">ยกเลิก</a>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
