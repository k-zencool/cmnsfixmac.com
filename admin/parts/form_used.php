<?php
/********************************************************************
 * admin/parts/form_used.php
 * ใช้หน้าเดียว:
 *  - เพิ่มชิ้นมือ 2 (ไม่มี ?id)
 *  - แก้ไขรายละเอียดชิ้นมือ 2 (?id=...)
 *  - เปลี่ยนสถานะ (เบิก/จ่าย consumed, จอง reserved, ชำรุด defect)
 *  - ลบชิ้นมือ 2 (delete)
 *
 * ตารางที่ใช้:
 *   - parts_used(id, part_code, part_name, part_number, device_models, category,
 *                image_url, serial_no, status, remarks, created_at, updated_at)
 *   - parts_new (เก็บหัวชนิด/เมทาดาต้า ถ้ายังไม่มีจะสร้างให้อัตโนมัติ)
 *   - parts_docs(doc_id, doc_type, ref_no, remarks, user_id, created_at)
 *   - parts_doc_lines(line_id, doc_id, part_code, qty, location_from, location_to, unit_cost)
 *
 * หมายเหตุเรื่อง history:
 *   - เพิ่มชิ้นมือ 2: บันทึก IN, qty=1, location_to='used'
 *   - เปลี่ยนสถานะเป็น consumed: บันทึก CONSUME, qty=1, location_from='used'
 *   - เปลี่ยนสถานะเป็น reserved/defect: บันทึก ADJUST, qty=0 (แค่ log เหตุการณ์)
 *   - ลบชิ้น: บันทึก ADJUST, qty=-1, location_from='used'
 ********************************************************************/

// =============== [SETUP & AUTH] ===============
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'manager']);

$pageTitle = "ชิ้นอะไหล่มือ 2";
$user_id = $_SESSION['user']['id'] ?? null; // ให้แสดงชื่อใน history ได้

// =============== [HELPERS] ====================
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function val($arr,$k,$d=''){ return isset($arr[$k]) ? trim((string)$arr[$k]) : $d; }
function redirect_used($qs=''){
  $q = "index.php?tab=used";
  if ($qs) $q .= "&{$qs}";
  header("Location: {$q}");
  exit;
}

// =============== [LOAD DATA IF EDIT] =========
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$defaults = [
  'part_code'     => '',
  'part_name'     => '',
  'part_number'   => '',
  'device_models' => '',
  'category'      => '',
  'image_url'     => '',
  'serial_no'     => '',
  'status'        => 'in_stock',
  'remarks'       => ''
];

$item = $defaults;
if ($id) {
  $st = $pdo->prepare("SELECT * FROM parts_used WHERE id=?");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    redirect_used('err=ไม่พบข้อมูล');
  }
  $item = array_merge($item, $row);
}

// =============== [ACTIONS] ====================
// 1) บันทึกข้อมูลหลัก (เพิ่มใหม่ หรือ อัปเดต)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='save_core') {
  $id            = (int)($_POST['id'] ?? 0);
  $part_code     = val($_POST,'part_code');
  $part_name     = val($_POST,'part_name');
  $part_number   = val($_POST,'part_number');
  $device_models = val($_POST,'device_models');
  $category      = val($_POST,'category');
  $image_url     = val($_POST,'image_url');
  $serial_no     = val($_POST,'serial_no');
  $status        = val($_POST,'status','in_stock');
  $remarks       = val($_POST,'remarks');

  $errors = [];
  if ($part_code==='') $errors[] = "กรุณากรอก Part Code";
  if ($part_name==='') $errors[] = "กรุณากรอกชื่ออะไหล่";

  if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_keep']   = $_POST;
    if ($id) {
      header("Location: form_used.php?id=".$id);
    } else {
      header("Location: form_used.php");
    }
    exit;
  }

  try {
    $pdo->beginTransaction();

    // สร้างหัวชนิดใน parts_new ถ้ายังไม่มี part_code นี้
    $q = $pdo->prepare("SELECT 1 FROM parts_new WHERE part_code=? LIMIT 1");
    $q->execute([$part_code]);
    if (!$q->fetchColumn()) {
      $insHead = $pdo->prepare("
        INSERT INTO parts_new
        (part_code, part_name, part_number, device_models, category, image_url,
         min_stock, is_active, location, quantity)
        VALUES (?,?,?,?,?,?, 0, 1, 'used', 0)
      ");
      $insHead->execute([
        $part_code, $part_name, $part_number, $device_models, $category, $image_url
      ]);
    }

    if ($id) {
      // update ข้อมูลชิ้น
      $upd = $pdo->prepare("
        UPDATE parts_used
        SET part_code=?, part_name=?, part_number=?, device_models=?, category=?,
            image_url=?, serial_no=?, status=?, remarks=?, updated_at=NOW()
        WHERE id=?
      ");
      $upd->execute([
        $part_code,$part_name,$part_number,$device_models,$category,
        $image_url,$serial_no,$status,$remarks,$id
      ]);
      $pdo->commit();
      redirect_used('msg=บันทึกการแก้ไขแล้ว');
    } else {
      // insert ชิ้นใหม่
      $ins = $pdo->prepare("
        INSERT INTO parts_used
        (part_code, part_name, part_number, device_models, category, image_url,
         serial_no, status, remarks, created_at)
        VALUES (?,?,?,?,?,?,?,?,?, NOW())
      ");
      $ins->execute([
        $part_code,$part_name,$part_number,$device_models,$category,
        $image_url,$serial_no, $status ?: 'in_stock', $remarks
      ]);

      // history: IN qty=1 เข้า location 'used'
      $pdo->prepare("
        INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
        VALUES ('IN', '', ?, ?, NOW())
      ")->execute([$remarks ?: null, $user_id]);
      $doc_id = $pdo->lastInsertId();

      $pdo->prepare("
        INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
        VALUES (?, ?, 1, NULL, 'used', NULL)
      ")->execute([$doc_id, $part_code]);

      $pdo->commit();
      redirect_used('saved=1');
    }

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['form_errors'] = [$e->getMessage()];
    $_SESSION['form_keep']   = $_POST;
    if ($id) {
      header("Location: form_used.php?id=".$id);
    } else {
      header("Location: form_used.php");
    }
    exit;
  }
}

// 2) เปลี่ยนสถานะ (เบิก/จ่าย, จอง, ชำรุด)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='status_update') {
  $id       = (int)($_POST['id'] ?? 0);
  $new_stat = val($_POST,'new_status');
  $remarks  = val($_POST,'status_remarks');

  if ($id<=0 || $new_stat==='') redirect_used('err=คำขอไม่ถูกต้อง');

  $st = $pdo->prepare("SELECT * FROM parts_used WHERE id=?");
  $st->execute([$id]);
  $cur = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cur) redirect_used('err=ไม่พบข้อมูล');

  try {
    $pdo->beginTransaction();

    // อัปเดตสถานะ
    $pdo->prepare("UPDATE parts_used SET status=?, remarks=?, updated_at=NOW() WHERE id=?")
        ->execute([$new_stat, $remarks, $id]);

    // บันทึกประวัติ
    if ($new_stat === 'consumed') {
      // ถือว่าเป็นการตัดจ่าย 1 ชิ้นจากสต็อกมือ 2
      $pdo->prepare("
        INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
        VALUES ('CONSUME', '', ?, ?, NOW())
      ")->execute([$remarks ?: null, $user_id]);
      $doc_id = $pdo->lastInsertId();

      $pdo->prepare("
        INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
        VALUES (?, ?, 1, 'used', NULL, NULL)
      ")->execute([$doc_id, $cur['part_code']]);
    } else {
      // reserved/defect แค่ Log เหตุการณ์
      $pdo->prepare("
        INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
        VALUES ('ADJUST', '', ?, ?, NOW())
      ")->execute([$remarks ?: ('สถานะใหม่: '.$new_stat), $user_id]);
      $doc_id = $pdo->lastInsertId();

      $pdo->prepare("
        INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
        VALUES (?, ?, 0, 'used', 'used', NULL)
      ")->execute([$doc_id, $cur['part_code']]);
    }

    $pdo->commit();
    redirect_used('msg=อัพเดตสถานะเรียบร้อย');

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect_used('err='.urlencode($e->getMessage()));
  }
}

// 3) ลบชิ้น (พร้อม log)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete_item') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id<=0) redirect_used('err=คำขอไม่ถูกต้อง');

  $st = $pdo->prepare("SELECT * FROM parts_used WHERE id=?");
  $st->execute([$id]);
  $cur = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cur) redirect_used('err=ไม่พบข้อมูล');

  try {
    $pdo->beginTransaction();

    // log การลบ เป็น ADJUST qty = -1 จาก used
    $pdo->prepare("
      INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
      VALUES ('ADJUST', '', 'ลบชิ้นมือ 2 ออกจากระบบ', ?, NOW())
    ")->execute([$user_id]);
    $doc_id = $pdo->lastInsertId();

    $pdo->prepare("
      INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
      VALUES (?, ?, -1, 'used', NULL, NULL)
    ")->execute([$doc_id, $cur['part_code']]);

    // ลบจริง
    $pdo->prepare("DELETE FROM parts_used WHERE id=?")->execute([$id]);

    $pdo->commit();
    redirect_used('msg=ลบเรียบร้อย');
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect_used('err='.urlencode($e->getMessage()));
  }
}

// =============== [RENDER FORM] ===============
// ฟอร์มจะโชว์ค่าจาก $_SESSION['form_keep'] หากมี error กลับมา
if (!empty($_SESSION['form_keep'])) {
  $item = array_merge($item, $_SESSION['form_keep']);
  unset($_SESSION['form_keep']);
}
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?> <?= $id ? '(แก้ไขชิ้น #' . (int)$id . ')' : '(เพิ่มชิ้นใหม่)' ?></span>
    <a href="index.php?tab=used" class="btn-secondary">← กลับรายการมือ 2</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?>
        <div><?= h($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- โซน 1: ฟอร์มข้อมูลหลัก -->
  <form method="post" class="card" style="padding:16px;border-radius:12px; margin-bottom:16px;">
    <input type="hidden" name="action" value="save_core">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="table-container">
      <table class="data-table">
        <tbody>
          <tr>
            <th style="width:220px;">Part Code *</th>
            <td><input type="text" class="filter-input" name="part_code" required value="<?= h($item['part_code']) ?>" placeholder="เช่น MB-BATT-A1819"></td>
          </tr>
          <tr>
            <th>ชื่ออะไหล่ *</th>
            <td><input type="text" class="filter-input" name="part_name" required value="<?= h($item['part_name']) ?>" placeholder="เช่น BATTERY A1819"></td>
          </tr>
          <tr>
            <th>เลขอะไหล่</th>
            <td><input type="text" class="filter-input" name="part_number" value="<?= h($item['part_number']) ?>" placeholder="A1819 หรือ 661-xxxx"></td>
          </tr>
          <tr>
            <th>ใช้กับรุ่น</th>
            <td><input type="text" class="filter-input" name="device_models" value="<?= h($item['device_models']) ?>" placeholder="A1706, A1708 ..."></td>
          </tr>
          <tr>
            <th>หมวด</th>
            <td><input type="text" class="filter-input" name="category" value="<?= h($item['category']) ?>" placeholder="MacBook / iPhone / ..."></td>
          </tr>
          <tr>
            <th>รูปภาพ (ชื่อไฟล์หรือ URL)</th>
            <td><input type="text" class="filter-input" name="image_url" value="<?= h($item['image_url']) ?>"></td>
          </tr>
          <tr>
            <th>Serial</th>
            <td><input type="text" class="filter-input" name="serial_no" value="<?= h($item['serial_no']) ?>"></td>
          </tr>
          <tr>
            <th>สถานะ</th>
            <td>
              <select name="status" class="filter-input">
                <?php $st = $item['status'] ?: 'in_stock'; ?>
                <option value="in_stock"  <?= $st==='in_stock'?'selected':'' ?>>คงอยู่</option>
                <option value="reserved"  <?= $st==='reserved'?'selected':'' ?>>จอง</option>
                <option value="consumed"  <?= $st==='consumed'?'selected':'' ?>>ตัดจ่าย</option>
                <option value="defect"    <?= $st==='defect'  ?'selected':'' ?>>ชำรุด</option>
              </select>
            </td>
          </tr>
          <tr>
            <th>หมายเหตุ</th>
            <td><textarea name="remarks" class="filter-input" rows="3" placeholder="รายละเอียดสภาพ อาการ ชิ้นส่วนที่หาย ฯลฯ"><?= h($item['remarks']) ?></textarea></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div style="display:flex;gap:10px;margin-top:14px;">
      <button class="btn-primary" type="submit"><?= $id ? 'บันทึกการแก้ไข' : 'เพิ่มชิ้น' ?></button>
      <a class="btn-secondary" href="index.php?tab=used">ยกเลิก</a>
    </div>
  </form>

  <?php if ($id): ?>
    <!-- โซน 2: เปลี่ยนสถานะอย่างเร็ว (ทำ history ให้อัตโนมัติ) -->
    <form method="post" class="card" style="padding:16px;border-radius:12px; margin-bottom:16px;">
      <input type="hidden" name="action" value="status_update">
      <input type="hidden" name="id" value="<?= (int)$id ?>">

      <h3 style="margin-top:0;">เปลี่ยนสถานะชิ้นนี้</h3>
      <div class="table-container">
        <table class="data-table">
          <tbody>
            <tr>
              <th style="width:220px;">สถานะใหม่</th>
              <td>
                <select name="new_status" class="filter-input" required>
                  <option value="">-- เลือก --</option>
                  <option value="consumed">ตัดจ่าย (ออกจากสต็อก)</option>
                  <option value="reserved">จอง</option>
                  <option value="defect">ชำรุด</option>
                </select>
              </td>
            </tr>
            <tr>
              <th>หมายเหตุ</th>
              <td><textarea name="status_remarks" class="filter-input" rows="3" placeholder="ใส่หมายเหตุประกอบรายการนี้ (ถ้ามี)"></textarea></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;">
        <button class="btn-secondary" type="submit">อัพเดตสถานะ</button>
      </div>
    </form>

    <!-- โซน 3: ลบชิ้นนี้ -->
    <form method="post" onsubmit="return confirm('ลบชิ้นนี้ถาวร ใช่ไหม? การกระทำนี้จะถูกบันทึกลงประวัติเป็น ADJUST (-1)');" class="card" style="padding:16px;border-radius:12px;">
      <input type="hidden" name="action" value="delete_item">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <h3 style="margin-top:0;color:#b00;">ลบชิ้นนี้</h3>
      <p class="muted">ระบบจะบันทึกลง History เป็น ADJUST จำนวน -1 จาก location 'used'</p>
      <button class="btn-danger" type="submit">ลบชิ้น</button>
    </form>
  <?php endif; ?>

</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
