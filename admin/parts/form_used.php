<?php
/********************************************************************
 * admin/parts/form_used.php  (FULL)
 *
 * หน้านี้ทำทุกอย่างของ "อะไหล่มือ 2" ในไฟล์เดียว:
 *  - เพิ่มชิ้น (ไม่มี ?id)
 *  - แก้ไขชิ้น (?id=...)
 *  - เปลี่ยนสถานะ (consumed / reserved / defect) พร้อมบันทึก History
 *  - ลบชิ้น (รองรับทั้ง GET: ?op=delete&id=... และ POST: action=delete_item)
 *
 * ตารางที่ใช้:
 *   parts_used(id, part_code, part_name, part_number, device_models, category,
 *              image_url, serial_no, status, remarks, created_at, updated_at)
 *   parts_new (หัวชนิด, auto-create ถ้าไม่มี)
 *   parts_docs(doc_id, doc_type, ref_no, remarks, user_id, created_at)
 *   parts_doc_lines(line_id, doc_id, part_code, qty, location_from, location_to, unit_cost)
 *
 * หมายเหตุ History:
 *   - เพิ่มชิ้นใหม่:        IN,        qty=+1, location_to='used'
 *   - เปลี่ยนสถานะ consumed: CONSUME,   qty=+1 จาก 'used'
 *   - reserved/defect:       ADJUST,    qty=0  (log เหตุการณ์)
 *   - ลบชิ้น:                ADJUST,    qty=-1, location_from='used'
 ********************************************************************/

// =============== [SETUP & AUTH] ===============
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'manager']);

$pageTitle = "ชิ้นอะไหล่มือ 2";
$user_id   = $_SESSION['user']['id'] ?? null; // ใช้แปะชื่อใน History

// =============== [UPLOAD CONFIG] ===============
define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts'); // พาธจริง
define('PARTS_UPLOAD_URL', '../../uploads/parts');            // พาธสำหรับ <img src>

if (!is_dir(PARTS_UPLOAD_DIR)) {
  @mkdir(PARTS_UPLOAD_DIR, 0775, true);
}
$allowExt  = ['jpg','jpeg','png','webp'];
$allowMime = ['image/jpeg','image/png','image/webp'];
function genSafeName($orig) {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  $base = trim($base,'-');
  if ($base==='') $base = 'part';
  return $base . '-' . date('Ymd-His') . '-' . substr(sha1(random_bytes(8)),0,6) . '.' . $ext;
}

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

// ค่าเริ่มต้นของฟิลด์
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

// ถ้ามี id แปลว่าโหมดแก้ไข ดึงข้อมูลก่อน
if ($id) {
  $st = $pdo->prepare("SELECT * FROM parts_used WHERE id=?");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) redirect_used('err=ไม่พบข้อมูล');
  $item = array_merge($item, $row);
}

/* ===================================================================
 * 3) ลบชิ้น (รองรับทั้ง GET และ POST)
 *    - GET:  form_used.php?op=delete&id=123  (ใช้กับลิงก์ <a>)
 *    - POST: action=delete_item (ใช้กับปุ่มฟอร์มดั้งเดิม)
 *    หมายเหตุ: ลบผ่าน GET เสี่ยงโดนบอทกด ให้คง confirm() ฝั่ง UI ไว้
 * =================================================================== */
$wantDeleteByGet  = ($_SERVER['REQUEST_METHOD']==='GET'  && val($_GET,'op')==='delete');
$wantDeleteByPost = ($_SERVER['REQUEST_METHOD']==='POST' && val($_POST,'action')==='delete_item');

if ($wantDeleteByGet || $wantDeleteByPost) {
  $delete_id = (int)($wantDeleteByGet ? ($_GET['id'] ?? 0) : ($_POST['id'] ?? 0));
  if ($delete_id <= 0) redirect_used('err=คำขอไม่ถูกต้อง');

  $st = $pdo->prepare("SELECT * FROM parts_used WHERE id=?");
  $st->execute([$delete_id]);
  $cur = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cur) redirect_used('err=ไม่พบข้อมูล');

  try {
    $pdo->beginTransaction();

    // เขียนประวัติ ADJUST qty=-1 ออกจาก 'used'
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
    $pdo->prepare("DELETE FROM parts_used WHERE id=?")->execute([$delete_id]);

    $pdo->commit();
    redirect_used('msg=ลบเรียบร้อย');
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect_used('err='.urlencode($e->getMessage()));
  }
}

/* ===================================================================
 * 1) บันทึกข้อมูลหลัก (เพิ่มใหม่ หรือ อัปเดต)
 *    รวมอัปโหลดรูป + ลบรูปเดิม และเลือกหมวดแบบ select/พิมพ์เอง
 * =================================================================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && val($_POST,'action')==='save_core') {
  $form_id       = (int)($_POST['id'] ?? 0);
  $part_code     = val($_POST,'part_code');
  $part_name     = val($_POST,'part_name');
  $part_number   = val($_POST,'part_number');
  $device_models = val($_POST,'device_models');

  // หมวด: เลือกจาก select หรือพิมพ์เองถ้าเลือก other
  $category_sel  = val($_POST,'category_select');
  $category_cus  = val($_POST,'category_custom');
  $category      = $category_sel === 'other' ? $category_cus : $category_sel;

  // รูป: รองรับทั้งอัปโหลดไฟล์ และวาง URL
  $image_url     = val($_POST,'image_url'); // เก็บชื่อไฟล์หรือ URL
  $remove_image  = isset($_POST['remove_image']);

  $serial_no     = val($_POST,'serial_no');
  $status        = val($_POST,'status','in_stock');
  $remarks       = val($_POST,'remarks');

  $errors = [];
  if ($part_code==='') $errors[] = "กรุณากรอก Part Code";
  if ($part_name==='') $errors[] = "กรุณากรอกชื่ออะไหล่";
  if ($category==='')  $errors[] = "กรุณาเลือกหมวด";

  // ข้อมูลรูปเดิมกรณีแก้ไข
  $old = null;
  if ($form_id) {
    $q = $pdo->prepare("SELECT image_url FROM parts_used WHERE id=?");
    $q->execute([$form_id]);
    $old = $q->fetch(PDO::FETCH_ASSOC);
  }

  // ถ้ามีอัปโหลดไฟล์
  if (!empty($_FILES['image_file']) && is_uploaded_file($_FILES['image_file']['tmp_name'])) {
    $f    = $_FILES['image_file'];
    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($f['tmp_name']) ?: '';
    if (!in_array($ext, $allowExt, true) || !in_array($mime, $allowMime, true)) {
      $errors[] = "ไฟล์รูปต้องเป็น JPG/PNG/WEBP เท่านั้น";
    } else {
      $newName = genSafeName($f['name']);
      if (!move_uploaded_file($f['tmp_name'], PARTS_UPLOAD_DIR . '/' . $newName)) {
        $errors[] = "อัปโหลดรูปไม่สำเร็จ";
      } else {
        $image_url = $newName; // เก็บชื่อไฟล์เท่านั้น
      }
    }
  }

  // ลบรูปเดิมถ้าติ๊ก และรูปเดิมเป็นไฟล์ในระบบ (ไม่ใช่ URL)
  if ($remove_image && !empty($old['image_url']) && strpos($old['image_url'], '://') === false) {
    @unlink(PARTS_UPLOAD_DIR . '/' . $old['image_url']);
    if (empty($_FILES['image_file']['tmp_name'])) {
      $image_url = ''; // ถ้าไม่ได้อัปใหม่ทับ ให้เคลียร์ค่ารูป
    }
  }

  if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_keep']   = $_POST;
    header("Location: form_used.php".($form_id ? "?id=".$form_id : ""));
    exit;
  }

  try {
    $pdo->beginTransaction();

    // parts_new: auto-create ถ้าไม่มีหัว part_code
    $chk = $pdo->prepare("SELECT 1 FROM parts_new WHERE part_code=? LIMIT 1");
    $chk->execute([$part_code]);
    if (!$chk->fetchColumn()) {
      $pdo->prepare("
        INSERT INTO parts_new
          (part_code, part_name, part_number, device_models, category, image_url,
           min_stock, is_active, location, quantity)
        VALUES (?,?,?,?,?,?, 0, 1, 'used', 0)
      ")->execute([$part_code,$part_name,$part_number,$device_models,$category,$image_url]);
    }

    if ($form_id) {
      // อัปเดตของเดิม
      $pdo->prepare("
        UPDATE parts_used
        SET part_code=?, part_name=?, part_number=?, device_models=?, category=?,
            image_url=?, serial_no=?, status=?, remarks=?, updated_at=NOW()
        WHERE id=?
      ")->execute([
        $part_code,$part_name,$part_number,$device_models,$category,
        $image_url,$serial_no,$status,$remarks,$form_id
      ]);

      $pdo->commit();
      redirect_used('msg=บันทึกการแก้ไขแล้ว');

    } else {
      // เพิ่มใหม่
      $pdo->prepare("
        INSERT INTO parts_used
          (part_code, part_name, part_number, device_models, category, image_url,
           serial_no, status, remarks, created_at)
        VALUES (?,?,?,?,?,?,?,?,?, NOW())
      ")->execute([
        $part_code,$part_name,$part_number,$device_models,$category,
        $image_url,$serial_no, ($status ?: 'in_stock'), $remarks
      ]);

      // ประวัติ IN +1 เข้า 'used'
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
    header("Location: form_used.php".($form_id ? "?id=".$form_id : ""));
    exit;
  }
}

/* ===================================================================
 * 2) เปลี่ยนสถานะ (พร้อม History)
 * =================================================================== */
if ($_SERVER['REQUEST_METHOD']==='POST' && val($_POST,'action')==='status_update') {
  $sid      = (int)($_POST['id'] ?? 0);
  $new_stat = val($_POST,'new_status');
  $s_remark = val($_POST,'status_remarks');

  if ($sid<=0 || $new_stat==='') redirect_used('err=คำขอไม่ถูกต้อง');

  $st = $pdo->prepare("SELECT * FROM parts_used WHERE id=?");
  $st->execute([$sid]);
  $cur = $st->fetch(PDO::FETCH_ASSOC);
  if (!$cur) redirect_used('err=ไม่พบข้อมูล');

  try {
    $pdo->beginTransaction();

    // อัปเดตสถานะในตารางหลัก
    $pdo->prepare("UPDATE parts_used SET status=?, remarks=?, updated_at=NOW() WHERE id=?")
        ->execute([$new_stat, $s_remark, $sid]);

    // เขียนประวัติตามสถานะ
    if ($new_stat === 'consumed') {
      $pdo->prepare("
        INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
        VALUES ('CONSUME', '', ?, ?, NOW())
      ")->execute([$s_remark ?: null, $user_id]);
      $doc_id = $pdo->lastInsertId();

      $pdo->prepare("
        INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
        VALUES (?, ?, 1, 'used', NULL, NULL)
      ")->execute([$doc_id, $cur['part_code']]);

    } else {
      // reserved/defect: log เหตุการณ์ qty=0
      $pdo->prepare("
        INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
        VALUES ('ADJUST', '', ?, ?, NOW())
      ")->execute([$s_remark ?: ('สถานะใหม่: '.$new_stat), $user_id]);
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

// ===================================================================
// [RENDER FORM]
// ===================================================================
if (!empty($_SESSION['form_keep'])) {
  $item = array_merge($item, $_SESSION['form_keep']); // sticky form
  unset($_SESSION['form_keep']);
}
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);

// เทมเพลตส่วนหัว/ข้าง
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
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- โซน 1: ฟอร์มข้อมูลหลัก -->
  <form method="post" enctype="multipart/form-data" class="card" style="padding:16px;border-radius:12px; margin-bottom:16px;">
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

          <!-- หมวด: select + กรอกเองได้ -->
          <tr>
            <th>หมวด *</th>
            <td>
              <?php
                $catOptions = ['macbook' => 'MacBook', 'iphone' => 'iPhone', 'ipad' => 'iPad', 'imac' => 'iMac'];
                $catCurrent = $item['category'] ?: 'macbook';
                $catExists  = array_key_exists($catCurrent, $catOptions);
              ?>
              <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <select name="category_select" class="filter-input" style="min-width:180px;">
                  <?php foreach ($catOptions as $val => $label): ?>
                    <option value="<?= h($val) ?>" <?= $catCurrent===$val?'selected':'' ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                  <option value="other" <?= !$catExists?'selected':'' ?>>อื่นๆ (พิมพ์เอง)</option>
                </select>
                <input type="text"
                       name="category_custom"
                       class="filter-input"
                       placeholder="เช่น logic board / top case ฯลฯ"
                       value="<?= !$catExists ? h($catCurrent) : '' ?>"
                       style="flex:1; min-width:220px;">
              </div>
              <div class="muted" style="margin-top:4px;">ถ้าเลือก “อื่นๆ” ระบบจะใช้ค่าที่พิมพ์เอง</div>
            </td>
          </tr>

          <!-- รูปภาพ: อัปโหลดไฟล์ + พรีวิว + วาง URL ได้ -->
          <tr>
            <th>รูปภาพ</th>
            <td>
              <?php
                $hasImg = trim((string)$item['image_url']) !== '';
                $imgSrc = $hasImg
                  ? (strpos($item['image_url'],'://')!==false ? $item['image_url'] : PARTS_UPLOAD_URL . '/' . h($item['image_url']))
                  : '';
              ?>
              <?php if ($hasImg): ?>
                <div style="display:flex; gap:12px; align-items:center; margin-bottom:8px;">
                  <img src="<?= $imgSrc ?>" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #eee;">
                  <label class="checkline"><input type="checkbox" name="remove_image"> ลบรูปนี้</label>
                </div>
              <?php endif; ?>

              <input type="file" name="image_file" accept="image/*" class="filter-input" style="max-width:320px;">
              <div class="muted" style="margin-top:4px;">หรือวาง URL เอง (ถ้าจำเป็น):</div>
              <input type="text" name="image_url" value="<?= h($item['image_url']) ?>" class="filter-input" placeholder="วาง URL รูป หรือเว้นว่างถ้าอัปโหลดไฟล์">
            </td>
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
    <!-- โซน 2: เปลี่ยนสถานะเร็ว พร้อม History -->
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

    <!-- โซน 3: ลบชิ้นนี้ด้วย POST (เผื่ออยากใช้วิธีปลอดภัยกว่า GET) -->
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
