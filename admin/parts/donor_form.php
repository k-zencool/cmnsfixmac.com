<?php
/********************************************************************
 * admin/parts/donor_form.php
 * ฟอร์ม "เครื่องซาก" (Donor)
 * - อัปโหลดรูปแบบลาก-วาง + ปุ่มลบรูป
 * - ลบด้วย POST (กันลบถ้ามี parts_used ผูกอยู่)
 * - เก็บประวัติลง parts_docs (doc_type='DONOR', ref_no='DONOR:<id>')
 *   ใช้เฉพาะคอลัมน์เดิม: doc_type, ref_no, remarks, user_id, created_at
 ********************************************************************/

// ========== SETUP ==========
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin','manager']);

$pageTitle = "ฟอร์มเครื่องซาก";

// ให้ PDO โยน exception เผื่อโฮสต์ไม่ได้ตั้งค่า
if ($pdo instanceof PDO) {
  try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function val($arr,$k,$d=''){ return isset($arr[$k]) ? trim((string)$arr[$k]) : $d; }

// ย่อสตริงให้ไม่เกินความยาว remarks (255)
function short_remarks(string $s, int $max=255): string {
  $s = trim($s);
  if (mb_strlen($s) > $max) {
    $s = mb_substr($s, 0, $max - 1) . '…';
  }
  return $s;
}

$user_id = $_SESSION['admin_id'] ?? ($_SESSION['user']['id'] ?? null);

// -------- Upload helpers --------
define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(PARTS_UPLOAD_DIR)) @mkdir(PARTS_UPLOAD_DIR, 0775, true);

function safeUploadName(string $orig): string {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  if ($base==='') $base='donor';
  return $base.'_'.date('Ymd_His').'_'.substr(bin2hex(random_bytes(6)),0,12).'.'.$ext;
}
function img_src($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  if (preg_match('~^https?://~i',$v) || $v[0]==='/') return $v;
  // ไฟล์นี้อยู่ /admin/parts/ ดังนั้นรูปจริงอยู่ ../../uploads/parts/
  return '../../uploads/parts/'.$v;
}

/**
 * -------- บันทึกประวัติลง parts_docs (แบบสั้น ไม่ใช้ meta_json) --------
 * doc_type = 'DONOR'
 * ref_no   = 'DONOR:<id>'
 * remarks  = สรุปสั้น อ่านง่าย ไม่เกิน 255 ตัวอักษร
 */
function donor_doc(PDO $pdo, string $action, int $donor_id, array $item_or_diff, $user_id): void {
  try {
    $action = strtoupper($action);

    // สร้างข้อความสั้นๆ ให้พอดี remarks (กันคอลัมน์ 255)
    if ($action === 'CREATE') {
      $txt = "เพิ่มเครื่องซาก: ".($item_or_diff['device_models'] ?? '');
    } elseif ($action === 'UPDATE') {
      // ระบุคีย์ที่แก้ (อ่านง่ายและสั้น)
      $changed = array_keys($item_or_diff['changed'] ?? []);
      $txt = "แก้ไขเครื่องซาก (#{$donor_id}) ".($changed ? 'fields: '.implode(',', $changed) : '');
    } elseif ($action === 'DELETE') {
      $txt = "ลบเครื่องซาก (#{$donor_id})";
    } else {
      $txt = "{$action} donor #{$donor_id}";
    }

    $remarks = short_remarks($txt, 255);

    $sql = "INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
            VALUES ('DONOR', :ref, :remarks, :uid, NOW())";
    $pdo->prepare($sql)->execute([
      ':ref'     => 'DONOR:'.$donor_id,
      ':remarks' => $remarks,
      ':uid'     => $user_id,
    ]);
  } catch (Throwable $e) {
    // ไม่ให้ flow หลักพัง
    error_log("[donor_doc] ".$e->getMessage());
  }
}


// -------- Options --------
$deviceOptions = ['MacBook','iMac','iPhone','iPad','Apple Watch','อื่นๆ'];
$statusOptions = ['in_stock','reserved','stripped','sold'];

// -------- State --------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

$beforeRow = null;
if ($id) {
  $st = $pdo->prepare("SELECT * FROM parts_donors WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    header("Location: index.php?tab=donor&err=".urlencode("ไม่พบเครื่องซาก"));
    exit;
  }
  $beforeRow = $row;
  $item = array_merge($item, $row);
}

$errors = [];

// -------- Actions (POST) --------
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = val($_POST,'action');

  // ลบ
  if ($action==='delete_donor' && $id) {
    try {
      // กันลบถ้ามี parts_used ผูกอยู่
      $chk = $pdo->prepare("SELECT COUNT(*) FROM parts_used WHERE donor_id=?");
      $chk->execute([$id]);
      if ((int)$chk->fetchColumn() > 0) {
        $errors[] = "ลบไม่ได้: มีอะไหล่มือ 2 ผูกกับเครื่องซากนี้";
      } else {
        // log ก่อนลบ
        donor_doc($pdo, 'DELETE', $id, ['before'=>$beforeRow], $user_id);

        $pdo->prepare("DELETE FROM parts_donors WHERE id=? LIMIT 1")->execute([$id]);
        header("Location: index.php?tab=donor&msg=".urlencode("ลบเครื่องซากเรียบร้อย"));
        exit;
      }
    } catch(Throwable $e){
      $errors[] = $e->getMessage();
    }
  }

  // บันทึก (สร้าง/แก้ไข)
  if ($action==='save_donor') {
    $form_id               = (int)($_POST['id'] ?? 0);
    $item['device_name']   = val($_POST,'device_name','MacBook');
    $item['device_models'] = val($_POST,'device_models');
    $item['category']      = val($_POST,'category');
    $item['serial_no']     = val($_POST,'serial_no');
    $item['status']        = val($_POST,'status','in_stock');
    $item['purchase_cost'] = ($_POST['purchase_cost'] ?? '') === '' ? null : (float)$_POST['purchase_cost'];
    $item['reserved_ref']  = val($_POST,'reserved_ref');
    $item['remarks']       = val($_POST,'remarks');
    $remove_image_flag     = (int)($_POST['remove_image'] ?? 0);

    // validate
    if ($item['device_models']==='') $errors[] = "กรุณากรอกชื่ออะไหล่/รุ่น";
    if (!in_array($item['device_name'], $deviceOptions, true)) $errors[] = "อุปกรณ์ไม่ถูกต้อง";
    if (!in_array($item['status'], $statusOptions, true))   $errors[] = "สถานะไม่ถูกต้อง";

    // อัปโหลดรูป (ถ้ามี)
    $newImage = null;
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
            $newImage = $new;
            $remove_image_flag = 0;
          }
        }
      } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "อัปโหลดรูปผิดพลาด (code {$f['error']})";
      }
    }

    if (!$errors) {
      try {
        if ($form_id) {
          // ============== UPDATE ==============
          if (!$beforeRow) {
            $stB = $pdo->prepare("SELECT * FROM parts_donors WHERE id=? LIMIT 1");
            $stB->execute([$form_id]);
            $beforeRow = $stB->fetch(PDO::FETCH_ASSOC) ?: [];
          }

          if ($newImage !== null) {
            $sql = "UPDATE parts_donors
                       SET device_name=?, device_models=?, category=?, serial_no=?, status=?,
                           purchase_cost=?, reserved_ref=?, image_url=?, remarks=?, updated_at=NOW()
                     WHERE id=?";
            $params = [
              $item['device_name'], $item['device_models'], $item['category'], $item['serial_no'], $item['status'],
              $item['purchase_cost'], $item['reserved_ref'], $newImage, $item['remarks'], $form_id
            ];
          } elseif ($remove_image_flag===1) {
            $sql = "UPDATE parts_donors
                       SET device_name=?, device_models=?, category=?, serial_no=?, status=?,
                           purchase_cost=?, reserved_ref=?, image_url=NULL, remarks=?, updated_at=NOW()
                     WHERE id=?";
            $params = [
              $item['device_name'], $item['device_models'], $item['category'], $item['serial_no'], $item['status'],
              $item['purchase_cost'], $item['reserved_ref'], $item['remarks'], $form_id
            ];
          } else {
            $sql = "UPDATE parts_donors
                       SET device_name=?, device_models=?, category=?, serial_no=?, status=?,
                           purchase_cost=?, reserved_ref=?, remarks=?, updated_at=NOW()
                     WHERE id=?";
            $params = [
              $item['device_name'], $item['device_models'], $item['category'], $item['serial_no'], $item['status'],
              $item['purchase_cost'], $item['reserved_ref'], $item['remarks'], $form_id
            ];
          }
          $pdo->prepare($sql)->execute($params);

          // หา field ที่เปลี่ยนเพื่อลง log แบบสั้น
          $changed = [];
          foreach (['device_name','device_models','category','serial_no','status','purchase_cost','reserved_ref','image_url','remarks'] as $k) {
            $afterVal = ($k==='image_url')
              ? ($newImage !== null ? $newImage : (($remove_image_flag===1) ? null : ($beforeRow[$k] ?? null)))
              : $item[$k];
            if (($beforeRow[$k] ?? null) !== $afterVal) $changed[$k] = true;
          }
          donor_doc($pdo, 'UPDATE', $form_id, ['changed'=>$changed], $user_id);

          header("Location: index.php?tab=donor&msg=".urlencode("บันทึกการแก้ไขแล้ว"));
          exit;

        } else {
          // ============== INSERT ==============
          $sql = "INSERT INTO parts_donors
                    (device_name, device_models, category, serial_no, status,
                     purchase_cost, reserved_ref, image_url, remarks, created_at, updated_at)
                  VALUES (?,?,?,?,?,?, ?, ?, ?, NOW(), NOW())";
          $pdo->prepare($sql)->execute([
            $item['device_name'], $item['device_models'], $item['category'], $item['serial_no'], $item['status'],
            $item['purchase_cost'], $item['reserved_ref'], $newImage, $item['remarks']
          ]);
          $new_id = (int)$pdo->lastInsertId();

          donor_doc($pdo, 'CREATE', $new_id, [
            'device_models' => $item['device_models'],
          ], $user_id);

          header("Location: index.php?tab=donor&msg=".urlencode("เพิ่มเครื่องซากแล้ว"));
          exit;
        }
      } catch(Throwable $e){
        $errors[] = $e->getMessage();
      }
    }

    if ($newImage !== null) $item['image_url'] = $newImage;
  }
}

// ========== TEMPLATE ==========
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?> <?= $id ? '(แก้ไข #' . (int)$id . ')' : '(เพิ่มรายการใหม่)' ?></span>
    <a href="index.php?tab=donor" class="view-site">← กลับรายการเครื่องซาก</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger" style="margin-top:12px;">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <style>.restock-form .form-hint{grid-column:2/3;margin-top:6px;color:#6B7280;font-size:12px;line-height:1.35}</style>

  <form id="donorForm" method="post" enctype="multipart/form-data" class="card restock-form" style="margin-top:16px;" novalidate>
    <input type="hidden" name="action" id="donorAction" value="save_donor">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="remove_image" id="remove_image" value="0">

    <div class="form-grid">
      <!-- รูป -->
      <div class="form-item">
        <label class="form-label">รูป</label>
        <div class="image-upload-ui" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <div id="dImgWrap" style="position:relative;width:100px;height:100px;border:1px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fafafa;cursor:pointer;" title="คลิกหรือลากไฟล์มาวาง">
            <?php if (!empty($item['image_url'])): ?>
              <img id="dImg" src="<?= h(img_src($item['image_url'])) ?>" alt="preview" style="width:100%;height:100%;object-fit:cover;">
              <button type="button" id="dRemoveBtn" style="position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);">×</button>
            <?php else: ?>
              <span id="dImgText" class="muted small">ลากรูปมาวาง</span>
              <button type="button" id="dRemoveBtn" style="display:none;position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);">×</button>
            <?php endif; ?>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;min-width:220px;">
            <label for="image" class="btn-secondary" style="cursor:pointer;display:inline-flex;align-items:center;gap:8px;">เลือกรูปจากเครื่อง</label>
            <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp" style="display:none">
            <div class="muted small">รองรับ jpg, jpeg, png, webp ≤ 5MB</div>
          </div>
        </div>
      </div>

      <div class="form-item">
        <label class="form-label" for="device_name">อุปกรณ์</label>
        <select id="device_name" name="device_name" class="input filter-input">
          <?php foreach ($deviceOptions as $opt): ?>
            <option value="<?= h($opt) ?>" <?= $item['device_name']===$opt ? 'selected' : '' ?>><?= h($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-item">
        <label class="form-label" for="device_models">ชื่ออะไหล่/รุ่น *</label>
        <input id="device_models" name="device_models" class="input filter-input" required value="<?= h($item['device_models']) ?>" placeholder='เช่น "MacBook Pro 13 2019 A2159"'>
      </div>

      <div class="form-item">
        <label class="form-label" for="category">หมวด</label>
        <input id="category" name="category" class="input filter-input" value="<?= h($item['category']) ?>" placeholder="screen/battery/board/...">
      </div>

      <div class="form-item">
        <label class="form-label" for="serial_no">Serial</label>
        <input id="serial_no" name="serial_no" class="input filter-input" value="<?= h($item['serial_no']) ?>">
      </div>

      <div class="form-item">
        <label class="form-label" for="status">สถานะ</label>
        <select id="status" name="status" class="input filter-input">
          <?php foreach($statusOptions as $s): ?>
            <option value="<?= h($s) ?>" <?= $item['status']===$s ? 'selected' : '' ?>><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="form-hint">ตั้งเป็น <code>stripped</code> เมื่อแยกอะไหล่แล้ว</small>
      </div>

      <div class="form-item">
        <label class="form-label" for="purchase_cost">ทุน (บาท)</label>
        <input id="purchase_cost" name="purchase_cost" class="input filter-input" type="number" step="0.01" value="<?= h($item['purchase_cost']) ?>" placeholder="เช่น 1500.00" inputmode="decimal">
      </div>

      <div class="form-item">
        <label class="form-label" for="reserved_ref">อ้างอิง/ผู้ขาย</label>
        <input id="reserved_ref" name="reserved_ref" class="input filter-input" value="<?= h($item['reserved_ref']) ?>" placeholder="PO/ชื่อร้าน/เลขบิล">
      </div>

      <div class="form-item">
        <label class="form-label" for="remarks">หมายเหตุ</label>
        <textarea id="remarks" name="remarks" class="input filter-input" rows="3" placeholder="รายละเอียดเพิ่มเติม เช่น อาการ/สภาพ/แหล่งที่มา"><?= h($item['remarks']) ?></textarea>
      </div>

      <div class="form-actions">
        <button class="btn-primary" type="submit"><?= $id ? 'บันทึกการแก้ไข' : 'เพิ่มเครื่องซาก' ?></button>
        <a class="btn-secondary" href="index.php?tab=donor">ยกเลิก</a>
        <?php if ($id): ?>
          <button type="button" class="btn-secondary" onclick="return deleteDonor();">ลบเครื่องซาก</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
  // Image UI
  (function() {
    var input   = document.getElementById('image');
    var wrap    = document.getElementById('dImgWrap');
    var remove  = document.getElementById('dRemoveBtn');
    var img     = document.getElementById('dImg');
    var rmField = document.getElementById('remove_image');
    var existed = <?= json_encode(!empty($item['image_url'])) ?>;

    function showPreview(file) {
      if (!file) return;
      if (!/image\/(png|jpe?g|webp)/i.test(file.type)) { alert('ไฟล์ไม่ใช่รูปภาพที่รองรับ'); return; }
      var reader = new FileReader();
      reader.onload = function(e) {
        if (!img) {
          img = document.createElement('img');
          img.id = 'dImg';
          img.alt = 'preview';
          img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
        }
        wrap.innerHTML = '';
        wrap.appendChild(img);
        img.src = e.target.result;

        if (!remove) {
          remove = document.createElement('button');
          remove.id = 'dRemoveBtn';
          remove.type = 'button';
          remove.textContent = '×';
          remove.style.cssText =
            'position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;' +
            'background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;' +
            'line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);';
          remove.addEventListener('click', clearImage);
        }
        wrap.appendChild(remove);
        remove.style.display = '';
        if (rmField) rmField.value = 0;
      };
      reader.readAsDataURL(file);
    }

    function clearImage(e) {
      if (e) e.stopPropagation();
      if (input) input.value = '';
      wrap.innerHTML = '<span id="dImgText" class="muted small">ลากรูปมาวาง</span>';
      if (remove) { wrap.appendChild(remove); remove.style.display = 'none'; }
      img = null;
      if (rmField && existed) rmField.value = 1;
    }

    wrap.addEventListener('click', function(){ if (input) input.click(); });
    function setBorder(c){ wrap.style.borderColor = c; }
    wrap.addEventListener('dragover', function(e){ e.preventDefault(); setBorder('#3b82f6'); });
    wrap.addEventListener('dragleave', function(){ setBorder('#cbd5e1'); });
    wrap.addEventListener('drop', function(e){
      e.preventDefault(); setBorder('#cbd5e1');
      var f = e.dataTransfer.files && e.dataTransfer.files[0];
      if (f){ input.files = e.dataTransfer.files; showPreview(f); }
    });
    if (input) input.addEventListener('change', function(){
      var f = input.files && input.files[0];
      if (f) showPreview(f);
    });
    if (remove) remove.addEventListener('click', clearImage);
  })();

  function deleteDonor(){
    if(!confirm('ยืนยันลบเครื่องซากนี้ถาวร?')) return false;
    document.getElementById('donorAction').value = 'delete_donor';
    document.getElementById('donorForm').submit();
    return false;
  }
</script>
