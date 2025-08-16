<?php
/********************************************************************
 * admin/parts/form_used.php  (no is_active, no min_stock)
 * ฟอร์ม "อะไหล่มือ 2" โฉมเดียวกับ form.php (ชิ้นต่อแถว)
 * ใช้ตาราง parts_used (ไม่มี quantity / ไม่ใช้ is_active/min_stock)
 ********************************************************************/

// ========== SETUP ==========
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin','manager']);

$pageTitle = "ฟอร์มชิ้นอะไหล่มือ 2";
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function val($arr,$k,$d=''){ return isset($arr[$k]) ? trim((string)$arr[$k]) : $d; }

// อัปโหลดรูป
define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(PARTS_UPLOAD_DIR)) @mkdir(PARTS_UPLOAD_DIR,0775,true);
function safeUploadName(string $orig): string {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  if ($base==='') $base='part';
  return $base.'-'.date('Ymd_His').'-'.substr(bin2hex(random_bytes(4)),0,8).'.'.$ext;
}

// ค่าพื้นฐาน
$categories = ['MacBook','iMac','iPhone','iPad','Apple Watch','Other'];

// รับ id ถ้ามา = โหมดแก้ไข
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ค่าเริ่มต้นฟอร์ม (ไม่มี is_active แล้ว)
$item = [
  'part_code'     => '',
  'part_name'     => '',
  'part_number'   => '',
  'device_models' => '',
  'category'      => 'MacBook',
  'image_url'     => null,
  'location'      => 'used',
  'remarks'       => ''
];

// โหลดของเดิมถ้ามี id
if ($id) {
  $st = $pdo->prepare("SELECT * FROM parts_used WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    header("Location: index.php?tab=used&err=".urlencode("ไม่พบข้อมูลชิ้นมือ 2"));
    exit;
  }
  $item = array_merge($item, $row);
}

// ลบ (GET: ?op=delete&id=..)
if ($_SERVER['REQUEST_METHOD']==='GET' && val($_GET,'op')==='delete' && $id) {
  try {
    $pdo->prepare("DELETE FROM parts_used WHERE id=? LIMIT 1")->execute([$id]);
    header("Location: index.php?tab=used&msg=".urlencode("ลบชิ้นเรียบร้อย"));
    exit;
  } catch(Throwable $e){
    header("Location: index.php?tab=used&err=".urlencode($e->getMessage()));
    exit;
  }
}

// บันทึก (INSERT/UPDATE)
$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && val($_POST,'action')==='save_used') {
  $form_id       = (int)($_POST['id'] ?? 0);
  $item['part_code']     = val($_POST,'part_code');
  $item['part_name']     = val($_POST,'part_name');
  $item['part_number']   = val($_POST,'part_number');
  $item['device_models'] = val($_POST,'device_models');
  $item['category']      = val($_POST,'category','MacBook');
  $item['location']      = val($_POST,'location','used');
  $item['remarks']       = val($_POST,'remarks');

  if ($item['part_code']==='')   $errors[] = "กรุณากรอก Part Code";
  if ($item['part_name']==='')   $errors[] = "กรุณากรอกชื่ออะไหล่";
  if (!in_array($item['category'],$categories,true)) $errors[] = "หมวดหมู่ไม่ถูกต้อง";

  // อัปโหลดรูป (ถ้ามี)
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
        if (!move_uploaded_file($f['tmp_name'], PARTS_UPLOAD_DIR.$new)) {
          $errors[] = "อัปโหลดรูปไม่สำเร็จ";
        } else {
          $item['image_url'] = $new; // เก็บชื่อไฟล์
        }
      }
    } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
      $errors[] = "อัปโหลดรูปผิดพลาด (code {$f['error']})";
    }
  }

  if (!$errors) {
    try {
      if ($form_id) {
        // UPDATE (ไม่มี is_active แล้ว)
        $sql = "UPDATE parts_used
                   SET part_code=?, part_name=?, part_number=?, device_models=?, category=?,
                       image_url=?, location=?, remarks=?, updated_at=NOW()
                 WHERE id=?";
        $pdo->prepare($sql)->execute([
          $item['part_code'], $item['part_name'], $item['part_number'], $item['device_models'],
          $item['category'], $item['image_url'], $item['location'], $item['remarks'], $form_id
        ]);
        header("Location: index.php?tab=used&msg=".urlencode("บันทึกการแก้ไขแล้ว"));
        exit;
      } else {
        // INSERT (ไม่มี is_active แล้ว)
        $sql = "INSERT INTO parts_used
                  (part_code, part_name, part_number, device_models, category, image_url,
                   location, remarks, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?, NOW(), NOW())";
        $pdo->prepare($sql)->execute([
          $item['part_code'], $item['part_name'], $item['part_number'], $item['device_models'],
          $item['category'], $item['image_url'], $item['location'], $item['remarks']
        ]);
        header("Location: index.php?tab=used&msg=".urlencode("เพิ่มชิ้นมือ 2 เรียบร้อย"));
        exit;
      }
    } catch(Throwable $e){
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
    <span><?= h($pageTitle) ?> <?= $id ? '(แก้ไข #' . (int)$id . ')' : '(เพิ่มชิ้นใหม่)' ?></span>
    <a href="index.php?tab=used" class="btn-secondary">← กลับรายการมือ 2</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ============ ฟอร์มข้อมูลชิ้นมือ 2 ============ -->
  <form method="post" enctype="multipart/form-data" class="card" style="padding:16px;border-radius:12px;margin-bottom:16px;">
    <input type="hidden" name="action" value="save_used">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="table-container">
      <table class="data-table">
        <tbody>
          <tr>
            <th style="width:220px;">Part Code *</th>
            <td><input type="text" name="part_code" class="filter-input" required value="<?= h($item['part_code']) ?>" placeholder="เช่น MB-BATT-A1819"></td>
          </tr>
          <tr>
            <th>ชื่ออะไหล่ *</th>
            <td><input type="text" name="part_name" class="filter-input" required value="<?= h($item['part_name']) ?>" placeholder="เช่น BATTERY A1819"></td>
          </tr>
          <tr>
            <th>เลขอะไหล่</th>
            <td><input type="text" name="part_number" class="filter-input" value="<?= h($item['part_number']) ?>" placeholder="A1819 หรือ 661-xxxx"></td>
          </tr>
          <tr>
            <th>ใช้กับรุ่น (Model)</th>
            <td><input type="text" name="device_models" class="filter-input" value="<?= h($item['device_models']) ?>" placeholder="A1706, A1708 ..."></td>
          </tr>
          <tr>
            <th>หมวดหมู่</th>
            <td>
              <select name="category" class="filter-input">
                <?php foreach($categories as $c): ?>
                  <option value="<?= h($c) ?>" <?= $item['category']===$c ? 'selected' : '' ?>><?= h($c) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th>ที่เก็บ (Location)</th>
            <td><input type="text" name="location" class="filter-input" value="<?= h($item['location']) ?>" placeholder="เช่น used, main, shelf-A"></td>
          </tr>
          <tr>
            <th>รูปภาพ</th>
            <td>
              <?php if (!empty($item['image_url'])): ?>
                <div style="display:flex;align-items:center;gap:12px;">
                  <img src="../../uploads/parts/<?= h($item['image_url']) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:10px;border:1px solid #eee;">
                  <span class="muted"><?= h($item['image_url']) ?></span>
                </div>
                <div style="height:8px;"></div>
              <?php endif; ?>
              <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="filter-input">
              <div class="muted" style="font-size:12px;">รองรับ jpg, jpeg, png, webp ขนาดไม่เกิน 5MB</div>
            </td>
          </tr>
          <tr>
            <th>หมายเหตุ</th>
            <td><textarea name="remarks" class="filter-input" rows="3" placeholder="รายละเอียดสภาพ อาการ ร่องรอย ฯลฯ"><?= h($item['remarks']) ?></textarea></td>
          </tr>
          <!-- ตัดฟิลด์สถานะใช้งาน (is_active) ออกแล้ว -->
        </tbody>
      </table>
    </div>

    <div style="display:flex;gap:10px;margin-top:14px;">
      <button class="btn-primary" type="submit"><?= $id ? 'บันทึกการแก้ไข' : 'เพิ่มชิ้น' ?></button>
      <a class="btn-secondary" href="index.php?tab=used">ยกเลิก</a>
    </div>
  </form>

  <?php if ($id): ?>
    <!-- ลบชิ้นนี้ -->
    <form method="get" class="card" style="padding:16px;border-radius:12px;" onsubmit="return confirm('ลบชิ้นนี้ถาวร ใช่ไหม?');">
      <input type="hidden" name="op" value="delete">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <h3 style="margin-top:0;color:#b00;">ลบชิ้นนี้</h3>
      <button class="btn-danger" type="submit">ลบชิ้น</button>
    </form>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
