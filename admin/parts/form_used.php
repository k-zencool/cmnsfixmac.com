<?php
/********************************************************************
 * admin/parts/form_used.php
 * ฟอร์ม "อะไหล่มือ 2"
 * Update: แก้ Logic Gen SKU ให้หาค่า MAX แทนหา ID ล่าสุด (แก้ปัญหารันเลขซ้ำ)
 ********************************************************************/

// ========== SETUP ==========
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin','manager']);

$pageTitle = "ฟอร์มอะไหล่มือ 2";
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function val($arr,$k,$d=''){ return isset($arr[$k]) ? trim((string)$arr[$k]) : $d; }

$user_id = $_SESSION['admin_id'] ?? ($_SESSION['user']['id'] ?? null);

// -------- Upload helpers --------
define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(PARTS_UPLOAD_DIR)) @mkdir(PARTS_UPLOAD_DIR, 0775, true);

function safeUploadName(string $orig): string {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  if ($base==='') $base='used';
  return $base.'_'.date('Ymd_His').'_'.substr(bin2hex(random_bytes(6)),0,12).'.'.$ext;
}
function img_src($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  if (preg_match('~^https?://~i',$v) || $v[0]==='/') return $v;
  return '../../uploads/parts/'.$v;
}

/** * ฟังก์ชันสร้างรหัส SKU (U-YYYYMM-Axxxx)
 * [UPDATED] ใช้ ORDER BY used_sku DESC เพื่อหาเลขที่สูงที่สุดจริงๆ
 */
function generateUsedSKU(PDO $pdo) {
    // 1. Prefix: U-202512-A
    $prefix = "U-" . date('Ym') . "-A";
    
    // 2. หาเลขที่ "มากที่สุด" ในเดือนนี้ (เปลี่ยนจาก id DESC เป็น used_sku DESC)
    $stmt = $pdo->prepare("SELECT used_sku FROM parts_used WHERE used_sku LIKE :p ORDER BY used_sku DESC LIMIT 1");
    $stmt->execute([':p' => $prefix . '%']);
    $maxSku = $stmt->fetchColumn();

    $nextNum = 1;
    if ($maxSku) {
        // ดึง 4 ตัวท้ายมาบวก 1
        $lastNum = (int)substr($maxSku, -4);
        $nextNum = $lastNum + 1;
    }

    // 3. คืนค่า U-202512-A0001
    return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/** -------- บันทึกประวัติลง parts_docs -------- */
function used_doc(PDO $pdo, string $action, int $used_id, array $item, $user_id): void {
  try {
    $refInfo = !empty($item['used_sku']) ? $item['used_sku'] : "USED:{$used_id}";
    $ref = 'USED:' . $used_id;

    if ($action === 'CREATE') {
      $remarks = "เพิ่มมือ 2 [{$refInfo}]: " . (($item['part_name'] ?? '') ?: ($item['part_code'] ?? ''));
    } elseif ($action === 'UPDATE') {
      $remarks = "แก้ไขมือ 2 [{$refInfo}]";
    } elseif ($action === 'DELETE') {
      $remarks = "ลบมือ 2 [{$refInfo}]";
    } else {
      $remarks = strtoupper($action)." USED #{$used_id}";
    }
    $remarks = function_exists('mb_strimwidth') ? mb_strimwidth($remarks, 0, 250, '', 'UTF-8') : substr($remarks, 0, 250);

    $pdo->prepare("INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at) VALUES ('USED', :ref_no, :remarks, :uid, NOW())")
        ->execute([':ref_no' => $ref, ':remarks' => $remarks, ':uid' => $user_id]);
    $doc_id = (int)$pdo->lastInsertId();

    $loc = trim((string)($item['location'] ?? ''));
    $pc  = $item['part_code'] ?? null;

    if ($action === 'CREATE') {
      $pdo->prepare("INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to) VALUES (?, ?, 1, NULL, ?)")->execute([$doc_id, ($pc ?: null), ($loc ?: null)]);
    } elseif ($action === 'DELETE') {
      $pdo->prepare("INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to) VALUES (?, ?, -1, ?, NULL)")->execute([$doc_id, ($pc ?: null), ($loc ?: null)]);
    }
  } catch (Throwable $e) {}
}


// -------- State --------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$donor_id = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : 0;

$currentPage = 1;
if (isset($_POST['page']) && is_numeric($_POST['page'])) {
    $currentPage = (int)$_POST['page'];
} elseif (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $currentPage = (int)$_GET['page'];
} elseif (isset($_SERVER['HTTP_REFERER'])) {
    $parts = parse_url($_SERVER['HTTP_REFERER']);
    if (isset($parts['query'])) {
        parse_str($parts['query'], $qs);
        if (isset($qs['page']) && is_numeric($qs['page'])) {
            $currentPage = (int)$qs['page'];
        }
    }
}
$currentPage = max(1, $currentPage);


// ค่าเริ่มต้น
$item = [
  'used_sku'     => '', 
  'donor_id'     => $donor_id ?: null,
  'part_code'    => '',
  'part_name'    => '',
  'part_number'  => '',
  'device_models'=> '',
  'category'     => '',
  'image_url'    => null,
  'location'     => 'main',
  'remarks'      => '',
];

$beforeRow = null;

if ($id) {
  // --- กรณีแก้ไข: ดึงข้อมูลเดิม ---
  $st = $pdo->prepare("SELECT * FROM parts_used WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    header("Location: index.php?tab=used&page={$currentPage}&err=".urlencode("ไม่พบอะไหล่มือ 2"));
    exit;
  }
  $beforeRow = $row;
  $item = array_merge($item, $row);

  // [UPDATED] ถ้ายังไม่มี SKU ให้ Gen ใหม่
  if (empty($item['used_sku'])) {
      $item['used_sku'] = generateUsedSKU($pdo);
  }

} else {
  // --- กรณีเพิ่มใหม่ ---
  $item['used_sku'] = generateUsedSKU($pdo);
}

$errors = [];

// -------- Actions (POST) --------
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = val($_POST,'action');
  $redirectPage = isset($_POST['page']) ? (int)$_POST['page'] : 1;

  if ($action==='delete_used' && $id) {
    try {
      used_doc($pdo, 'DELETE', $id, $item, $user_id);
      $pdo->prepare("DELETE FROM parts_used WHERE id=? LIMIT 1")->execute([$id]);
      header("Location: index.php?tab=used&page={$redirectPage}&msg=".urlencode("ลบชิ้นมือ 2 เรียบร้อย"));
      exit;
    } catch(Throwable $e){
      $errors[] = $e->getMessage();
    }
  }

  if ($action==='save_used') {
    $form_id                = (int)($_POST['id'] ?? 0);
    $item['used_sku']       = val($_POST, 'used_sku'); // รับค่าจากฟอร์ม
    $item['donor_id']       = ($_POST['donor_id'] ?? '') === '' ? null : (int)$_POST['donor_id'];
    $item['part_code']      = val($_POST,'part_code');
    $item['part_name']      = val($_POST,'part_name');
    $item['part_number']    = val($_POST,'part_number');
    $item['device_models']  = val($_POST,'device_models');
    $item['category']       = val($_POST,'category');
    $item['location']       = val($_POST,'location','main');
    $item['remarks']        = val($_POST,'remarks');
    $remove_image_flag      = (int)($_POST['remove_image'] ?? 0);

    if ($item['part_name']==='' && $item['part_code']==='') $errors[] = "กรุณากรอกชื่ออะไหล่หรือรหัสอะไหล่อย่างน้อยอย่างใดอย่างหนึ่ง";
    if (!$user_id) $errors[] = "ไม่พบผู้ใช้งาน (session)";

    // อัปโหลดรูป
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
        // [ANTI-COLLISION] ถ้า SKU ซ้ำกับคนอื่น (ที่ไม่ใช่ตัวเอง) ให้ Gen ใหม่ทันที
        if (!empty($item['used_sku'])) {
            $chk = $pdo->prepare("SELECT id FROM parts_used WHERE used_sku = ? AND id != ?");
            $chk->execute([$item['used_sku'], $form_id]);
            if ($chk->fetch()) {
                // ซ้ำ! Gen ใหม่เลย
                $item['used_sku'] = generateUsedSKU($pdo);
            }
        } else {
             $item['used_sku'] = generateUsedSKU($pdo);
        }

        if ($form_id) {
          // ============== UPDATE ==============
          if (!$beforeRow) {
            $stB = $pdo->prepare("SELECT * FROM parts_used WHERE id=? LIMIT 1");
            $stB->execute([$form_id]);
            $beforeRow = $stB->fetch(PDO::FETCH_ASSOC) ?: [];
          }

          if ($newImage !== null) {
            $sql = "UPDATE parts_used SET used_sku=?, donor_id=?, part_code=?, part_name=?, part_number=?, device_models=?, category=?, image_url=?, location=?, remarks=?, updated_at=NOW() WHERE id=?";
            $params = [$item['used_sku'], $item['donor_id'], $item['part_code'], $item['part_name'], $item['part_number'], $item['device_models'], $item['category'], $newImage, $item['location'], $item['remarks'], $form_id];
          } elseif ($remove_image_flag===1) {
            $sql = "UPDATE parts_used SET used_sku=?, donor_id=?, part_code=?, part_name=?, part_number=?, device_models=?, category=?, image_url=NULL, location=?, remarks=?, updated_at=NOW() WHERE id=?";
            $params = [$item['used_sku'], $item['donor_id'], $item['part_code'], $item['part_name'], $item['part_number'], $item['device_models'], $item['category'], $item['location'], $item['remarks'], $form_id];
          } else {
            $sql = "UPDATE parts_used SET used_sku=?, donor_id=?, part_code=?, part_name=?, part_number=?, device_models=?, category=?, location=?, remarks=?, updated_at=NOW() WHERE id=?";
            $params = [$item['used_sku'], $item['donor_id'], $item['part_code'], $item['part_name'], $item['part_number'], $item['device_models'], $item['category'], $item['location'], $item['remarks'], $form_id];
          }
          $pdo->prepare($sql)->execute($params);
          used_doc($pdo, 'UPDATE', $form_id, $item, $user_id);

          header("Location: index.php?tab=used&page={$redirectPage}&msg=".urlencode("บันทึกการแก้ไขแล้ว (SKU: {$item['used_sku']})"));
          exit;

        } else {
          // ============== INSERT ==============
          // พยายามบันทึก ถ้าซ้ำให้ Gen ใหม่แล้วลองอีกที (Max 3 รอบ)
          $maxRetries = 3;
          $success = false;
          
          while($maxRetries > 0) {
              try {
                  $sql = "INSERT INTO parts_used (used_sku, donor_id, part_code, part_name, part_number, device_models, category, image_url, location, remarks, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?, ?, ?, NOW(), NOW())";
                  $pdo->prepare($sql)->execute([
                    $item['used_sku'],
                    $item['donor_id'], $item['part_code'], $item['part_name'], $item['part_number'], $item['device_models'],
                    $item['category'], $newImage, $item['location'], $item['remarks']
                  ]);
                  $new_id = (int)$pdo->lastInsertId();
                  used_doc($pdo, 'CREATE', $new_id, $item, $user_id);
                  $success = true;
                  break;

              } catch (PDOException $ex) {
                  // Code 1062 = Duplicate Entry
                  if ($ex->errorInfo[1] == 1062) {
                      $item['used_sku'] = generateUsedSKU($pdo); // Gen ใหม่
                      $maxRetries--;
                  } else {
                      throw $ex; 
                  }
              }
          }

          if ($success) {
            header("Location: index.php?tab=used&page={$redirectPage}&msg=".urlencode("เพิ่มชิ้นมือ 2 แล้ว รหัส: {$item['used_sku']}"));
            exit;
          } else {
            $errors[] = "ระบบไม่สามารถสร้างรหัส SKU ได้ (มีการชนกันของข้อมูล) กรุณาลองใหม่อีกครั้ง";
          }
        }
      } catch(Throwable $e){
        $errors[] = "Error: " . $e->getMessage();
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
    <a href="index.php?tab=used&page=<?= $currentPage ?>" class="view-site">← กลับรายการมือ 2</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger" style="margin-top:12px;">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <style>
    .restock-form .form-hint{grid-column:2/3;margin-top:6px;color:#6B7280;font-size:12px;line-height:1.35}
    .sku-input {
        background-color: #1f2937; 
        color: #fbbf24; 
        font-family: monospace; 
        font-size: 1.1em; 
        letter-spacing: 1px;
        font-weight: bold;
        border-color: #374151;
    }
  </style>

  <form id="usedForm" method="post" enctype="multipart/form-data" class="card restock-form" style="margin-top:16px;" novalidate>
    <input type="hidden" name="action" id="usedAction" value="save_used">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="remove_image" id="remove_image" value="0">
    <input type="hidden" name="page" value="<?= $currentPage ?>">

    <div class="form-grid">
      
      <div class="form-item">
        <label class="form-label">SKU / รหัสทรัพย์สิน</label>
        <input type="text" name="used_sku" class="input sku-input" value="<?= h($item['used_sku']) ?>" readonly>
        <small class="form-hint">ระบบสร้างให้อัตโนมัติ (U-ปีเดือน-Axxxx)</small>
      </div>

      <div class="form-item">
        <label class="form-label">รูป</label>
        <div class="image-upload-ui" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <div id="uImgWrap" style="position:relative;width:100px;height:100px;border:1px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fafafa;cursor:pointer;" title="คลิกหรือลากไฟล์มาวาง">
            <?php if (!empty($item['image_url'])): ?>
              <img id="uImg" src="<?= h(img_src($item['image_url'])) ?>" alt="preview" style="width:100%;height:100%;object-fit:cover;">
              <button type="button" id="uRemoveBtn" style="position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);">×</button>
            <?php else: ?>
              <span id="uImgText" class="muted small">ลากรูปมาวาง</span>
              <button type="button" id="uRemoveBtn" style="display:none;position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);">×</button>
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
        <label class="form-label" for="donor_id">เชื่อมกับเครื่อง (ถ้ามี)</label>
        <input id="donor_id" name="donor_id" class="input filter-input" type="number" min="0" value="<?= h($item['donor_id']) ?>" placeholder="ID เครื่อง">
        <small class="form-hint">ปล่อยว่างได้ หากชิ้นนี้ไม่ได้มาจากเครื่อง</small>
      </div>
      <div class="form-item">
        <label class="form-label" for="part_name">ชื่ออะไหล่</label>
        <input id="part_name" name="part_name" class="input filter-input" value="<?= h($item['part_name']) ?>" placeholder="เช่น Top Case, Screen, Battery">
      </div>
      <div class="form-item">
        <label class="form-label" for="part_code">รหัสอะไหล่ (ภายใน)</label>
        <input id="part_code" name="part_code" class="input filter-input" value="<?= h($item['part_code']) ?>" placeholder="ถ้ามี ใช้ช่วยค้นหา">
      </div>
      <div class="form-item">
        <label class="form-label" for="part_number">เลขอะไหล่</label>
        <input id="part_number" name="part_number" class="input filter-input" value="<?= h($item['part_number']) ?>" placeholder="เช่น 661-xxxx, Axxxx">
      </div>
      <div class="form-item">
        <label class="form-label" for="device_models">รุ่นอุปกรณ์</label>
        <input id="device_models" name="device_models" class="input filter-input" value="<?= h($item['device_models']) ?>" placeholder="เช่น A1706, A2159">
      </div>
      <div class="form-item">
        <label class="form-label" for="category">หมวด</label>
        <input id="category" name="category" class="input filter-input" value="<?= h($item['category']) ?>" placeholder="screen/battery/board/...">
      </div>
      <div class="form-item">
        <label class="form-label" for="location">ที่เก็บ</label>
        <input id="location" name="location" class="input filter-input" value="<?= h($item['location']) ?>" placeholder="เช่น main, shelf-A3">
      </div>
      <div class="form-item" style="grid-column:1 / -1">
        <label class="form-label" for="remarks">หมายเหตุ</label>
        <textarea id="remarks" name="remarks" class="input filter-input" rows="3" placeholder="รายละเอียด/สภาพ/ที่มา ฯลฯ"><?= h($item['remarks']) ?></textarea>
      </div>

      <div class="form-actions" style="grid-column:1 / -1">
        <button class="btn-primary" type="submit"><?= $id ? 'บันทึกการแก้ไข' : 'เพิ่มชิ้นมือ 2' ?></button>
        <a class="btn-secondary" href="index.php?tab=used&page=<?= $currentPage ?>">ยกเลิก</a>
        <?php if ($id): ?>
          <button type="button" class="btn-secondary" onclick="return deleteUsed();">ลบรายการ</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
  (function() {
    var input = document.getElementById('image');
    var wrap = document.getElementById('uImgWrap');
    var remove = document.getElementById('uRemoveBtn');
    var img = document.getElementById('uImg');
    var rmField = document.getElementById('remove_image');
    var existed = <?= json_encode(!empty($item['image_url'])) ?>;

    function showPreview(file) {
      if (!file) return;
      if (!/image\/(png|jpe?g|webp)/i.test(file.type)) { alert('ไฟล์ไม่ใช่รูปภาพที่รองรับ'); return; }
      var reader = new FileReader();
      reader.onload = function(e) {
        if (!img) {
          img = document.createElement('img');
          img.id = 'uImg'; img.alt = 'preview';
          img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
        }
        wrap.innerHTML = ''; wrap.appendChild(img); img.src = e.target.result;
        if (!remove) {
          remove = document.createElement('button');
          remove.id = 'uRemoveBtn'; remove.type = 'button'; remove.textContent = '×';
          remove.style.cssText = 'position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);';
          remove.addEventListener('click', clearImage);
        }
        wrap.appendChild(remove); remove.style.display = '';
        if (rmField) rmField.value = 0;
      };
      reader.readAsDataURL(file);
    }
    function clearImage(e) {
      if (e) e.stopPropagation();
      if (input) input.value = '';
      wrap.innerHTML = '<span id="uImgText" class="muted small">ลากรูปมาวาง</span>';
      if (remove) { wrap.appendChild(remove); remove.style.display = 'none'; }
      img = null;
      if (rmField && existed) rmField.value = 1;
    }
    wrap.addEventListener('click', function(){ if (input) input.click(); });
    wrap.addEventListener('dragover', function(e){ e.preventDefault(); wrap.style.borderColor = '#3b82f6'; });
    wrap.addEventListener('dragleave', function(){ wrap.style.borderColor = '#cbd5e1'; });
    wrap.addEventListener('drop', function(e){
      e.preventDefault(); wrap.style.borderColor = '#cbd5e1';
      var f = e.dataTransfer.files && e.dataTransfer.files[0];
      if (f){ input.files = e.dataTransfer.files; showPreview(f); }
    });
    if (input) input.addEventListener('change', function(){ var f = input.files && input.files[0]; if (f) showPreview(f); });
    if (remove) remove.addEventListener('click', clearImage);
  })();

  function deleteUsed(){
    if(!confirm('ยืนยันลบรายการมือ 2 นี้ถาวร?')) return false;
    document.getElementById('usedAction').value = 'delete_used';
    document.getElementById('usedForm').submit();
    return false;
  }
</script>