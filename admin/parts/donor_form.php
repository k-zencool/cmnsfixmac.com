<?php
/********************************************************************
 * admin/parts/donor_form.php
 * ฟอร์ม "เครื่องซาก" โฉมเดียวกับ form_used.php
 * ใช้ตาราง parts_donors (status='stripped' = แยกแล้ว)
 ********************************************************************/

// ========== SETUP ==========
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin','manager']);

$pageTitle = "ฟอร์มเครื่องซาก";
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function val($arr,$k,$d=''){ return isset($arr[$k]) ? trim((string)$arr[$k]) : $d; }

// อัปโหลดรูป
define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(PARTS_UPLOAD_DIR)) @mkdir(PARTS_UPLOAD_DIR,0775,true);
function safeUploadName(string $orig): string {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  if ($base==='') $base='donor';
  return $base.'-'.date('Ymd_His').'-'.substr(bin2hex(random_bytes(4)),0,8).'.'.$ext;
}

// ค่าพื้นฐาน
$deviceOptions = ['MacBook','iMac','iPhone','iPad','Apple Watch','อื่นๆ'];
$statusOptions = ['in_stock','reserved','stripped','sold'];

// รับ id ถ้ามา = โหมดแก้ไข
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ค่าเริ่มต้นฟอร์ม
$item = [
  'device_name'   => 'MacBook',
  'device_models' => '',
  'category'      => '',
  'serial_no'     => '',
  'status'        => 'in_stock',
  'purchase_cost' => null,
  'reserved_ref'  => '',
  'image_url'     => null,
  'remarks'       => ''
];

// โหลดของเดิมถ้ามี id
if ($id) {
  $st = $pdo->prepare("SELECT * FROM parts_donors WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    header("Location: index.php?tab=donor&err=".urlencode("ไม่พบเครื่องซาก"));
    exit;
  }
  $item = array_merge($item, $row);
}

// ลบ (GET: ?op=delete&id=..)
if ($_SERVER['REQUEST_METHOD']==='GET' && val($_GET,'op')==='delete' && $id) {
  try {
    // กันลบถ้ามี parts_used ผูกอยู่
    $chk = $pdo->prepare("SELECT COUNT(*) FROM parts_used WHERE donor_id=?");
    $chk->execute([$id]);
    if ((int)$chk->fetchColumn() > 0) {
      header("Location: index.php?tab=donor&err=".urlencode("ลบไม่ได้: มีอะไหล่มือ 2 ผูกกับเครื่องซากนี้"));
      exit;
    }
    $pdo->prepare("DELETE FROM parts_donors WHERE id=? LIMIT 1")->execute([$id]);
    header("Location: index.php?tab=donor&msg=".urlencode("ลบเครื่องซากเรียบร้อย"));
    exit;
  } catch(Throwable $e){
    header("Location: index.php?tab=donor&err=".urlencode($e->getMessage()));
    exit;
  }
}

// บันทึก (INSERT/UPDATE)
$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && val($_POST,'action')==='save_donor') {
  $form_id               = (int)($_POST['id'] ?? 0);
  $item['device_name']   = val($_POST,'device_name','MacBook');
  $item['device_models'] = val($_POST,'device_models');
  $item['category']      = val($_POST,'category');
  $item['serial_no']     = val($_POST,'serial_no');
  $item['status']        = val($_POST,'status','in_stock');
  $item['purchase_cost'] = ($_POST['purchase_cost'] ?? '') === '' ? null : (float)$_POST['purchase_cost'];
  $item['reserved_ref']  = val($_POST,'reserved_ref');
  $item['remarks']       = val($_POST,'remarks');

  // validate ง่ายๆ
  if ($item['device_models']==='') $errors[] = "กรุณากรอกชื่ออะไหล่/รุ่น";
  if (!in_array($item['device_name'], $deviceOptions, true)) $errors[] = "อุปกรณ์ไม่ถูกต้อง";
  if (!in_array($item['status'], $statusOptions, true)) $errors[] = "สถานะไม่ถูกต้อง";

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
        $sql = "UPDATE parts_donors
                   SET device_name=?, device_models=?, category=?, serial_no=?, status=?,
                       purchase_cost=?, reserved_ref=?, image_url=?, remarks=?, updated_at=NOW()
                 WHERE id=?";
        $pdo->prepare($sql)->execute([
          $item['device_name'], $item['device_models'], $item['category'], $item['serial_no'], $item['status'],
          $item['purchase_cost'], $item['reserved_ref'], $item['image_url'], $item['remarks'], $form_id
        ]);
        header("Location: index.php?tab=donor&msg=".urlencode("บันทึกการแก้ไขแล้ว"));
        exit;
      } else {
        $sql = "INSERT INTO parts_donors
                  (device_name, device_models, category, serial_no, status,
                   purchase_cost, reserved_ref, image_url, remarks, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?, NOW(), NOW())";
        $pdo->prepare($sql)->execute([
          $item['device_name'], $item['device_models'], $item['category'], $item['serial_no'], $item['status'],
          $item['purchase_cost'], $item['reserved_ref'], $item['image_url'], $item['remarks']
        ]);
        header("Location: index.php?tab=donor&msg=".urlencode("เพิ่มเครื่องซากแล้ว"));
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
    <span><?= h($pageTitle) ?> <?= $id ? '(แก้ไข #' . (int)$id . ')' : '(เพิ่มรายการใหม่)' ?></span>
    <a href="index.php?tab=donor" class="btn-secondary">← กลับรายการเครื่องซาก</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ============ ฟอร์มข้อมูลเครื่องซาก ============ -->
  <form method="post" enctype="multipart/form-data" class="card" style="padding:16px;border-radius:12px;margin-bottom:16px;">
    <input type="hidden" name="action" value="save_donor">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="table-container">
      <table class="data-table">
        <tbody>
          <tr>
            <th style="width:220px;">อุปกรณ์</th>
            <td>
              <select name="device_name" class="filter-input">
                <?php foreach($deviceOptions as $opt): ?>
                  <option value="<?= h($opt) ?>" <?= $item['device_name']===$opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>

          <tr>
            <th>ชื่ออะไหล่/รุ่น *</th>
            <td><input type="text" name="device_models" class="filter-input" required value="<?= h($item['device_models']) ?>" placeholder='เช่น "MacBook Pro 13 2019 A2159"'></td>
          </tr>

          <tr>
            <th>หมวด</th>
            <td><input type="text" name="category" class="filter-input" value="<?= h($item['category']) ?>" placeholder="screen/battery/board/..."></td>
          </tr>

          <tr>
            <th>Serial</th>
            <td><input type="text" name="serial_no" class="filter-input" value="<?= h($item['serial_no']) ?>"></td>
          </tr>

          <tr>
            <th>Status</th>
            <td>
              <select name="status" class="filter-input">
                <?php foreach($statusOptions as $s): ?>
                  <option value="<?= h($s) ?>" <?= $item['status']===$s ? 'selected' : '' ?>><?= h($s) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="muted" style="font-size:12px;margin-top:4px;">ตั้งเป็น <code>stripped</code> เมื่อแยกอะไหล่แล้ว</div>
            </td>
          </tr>

          <tr>
            <th>ทุน (บาท)</th>
            <td><input type="number" step="0.01" name="purchase_cost" class="filter-input" value="<?= h($item['purchase_cost']) ?>" placeholder="เช่น 1500.00"></td>
          </tr>

          <tr>
            <th>อ้างอิง/ผู้ขาย</th>
            <td><input type="text" name="reserved_ref" class="filter-input" value="<?= h($item['reserved_ref']) ?>" placeholder="PO/ชื่อร้าน/เลขบิล"></td>
          </tr>

          <tr>
            <th>รูปภาพ</th>
            <td>
              <?php if (!empty($item['image_url'])): ?>
                <div style="display:flex;align-items:center;gap:12px;">
                  <?php
                    // ภาพอาจเก็บเป็นชื่อไฟล์ใน uploads หรือเป็น URL ตรง
                    $img = $item['image_url'];
                    $src = (preg_match('~^https?://~i', $img) || substr($img,0,1)==='/') ? $img : ('../../uploads/parts/'. $img);
                  ?>
                  <img src="<?= h($src) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:10px;border:1px solid #eee;">
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
            <td><textarea name="remarks" class="filter-input" rows="3" placeholder="รายละเอียดเพิ่มเติม เช่น อาการ/สภาพ/แหล่งที่มา"><?= h($item['remarks']) ?></textarea></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div style="display:flex;gap:10px;margin-top:14px;">
      <button class="btn-primary" type="submit"><?= $id ? 'บันทึกการแก้ไข' : 'เพิ่มเครื่องซาก' ?></button>
      <a class="btn-secondary" href="index.php?tab=donor">ยกเลิก</a>
    </div>
  </form>

  <?php if ($id): ?>
    <!-- ลบเครื่องซาก -->
    <form method="get" class="card" style="padding:16px;border-radius:12px;" onsubmit="return confirm('ลบเครื่องซากนี้ถาวร ใช่ไหม?');">
      <input type="hidden" name="op" value="delete">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <h3 style="margin-top:0;color:#b00;">ลบเครื่องซากนี้</h3>
      <div class="muted" style="margin-bottom:8px;">ถ้ามีอะไหล่มือ 2 ผูกกับเครื่องนี้ จะลบไม่ได้</div>
      <button class="btn-danger" type="submit">ลบเครื่องซาก</button>
    </form>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
