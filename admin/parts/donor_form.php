<?php
/********************************************************************
 * admin/parts/donor_form.php
 * ฟอร์มจัดการเครื่อง (Parts Donor)
 * - [System] Prefix-Based Counting (แยกนับเลขตามชนิดอุปกรณ์ MB, PD, IP)
 * - [UX] Autofill Asset ID (ใส่เลขในช่องกรอกให้อัตโนมัติ)
 * - [Security] Duplicate Check + Loading Spinner
 ********************************************************************/

// ========== 1. SETUP & AUTH ==========
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); 

$pageTitle = "ฟอร์มเครื่อง";

if ($pdo instanceof PDO) {
  try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}
}

// Helpers
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function val($arr,$k,$d=''){ return isset($arr[$k]) ? trim((string)$arr[$k]) : $d; }
function short_remarks(string $s, int $max=255): string {
  $s = trim($s);
  return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
}

$user_id = $_SESSION['admin_id'] ?? ($_SESSION['user']['id'] ?? null);

// Upload Folder
define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(PARTS_UPLOAD_DIR)) @mkdir(PARTS_UPLOAD_DIR, 0775, true);

function safeUploadName(string $orig): string {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  return ($base?:'donor').'_'.date('Ymd_His').'_'.substr(bin2hex(random_bytes(6)),0,12).'.'.$ext;
}
function img_src($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  if (preg_match('~^https?://~i',$v) || $v[0]==='/') return $v;
  return '/uploads/parts/'.$v;
}

// ========== 2. LOGIC: PRE-CALCULATE NEXT ID FOR ALL PREFIXES ==========
// ฟังก์ชันนี้จะเตรียม "เลขถัดไป" ของทุก Prefix (MB, IP, PD...) เพื่อส่งให้ JS
function get_next_suffix_map($pdo) {
    // 1. ดึง Asset Tag ทั้งหมดในระบบ
    $sql = "SELECT internal_id FROM parts_donors 
            WHERE internal_id LIKE '%-%-%' AND internal_id IS NOT NULL";
    $stmt = $pdo->query($sql);
    $allTags = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // เก็บค่าสูงสุดของแต่ละ Prefix (เช่น MB => 112, PD => 0)
    $maxCounts = []; 

    foreach ($allTags as $tag) {
        $parts = explode('-', $tag);
        // ต้องมีอย่างน้อย 3 ส่วน: PREFIX-YM-SUFFIX
        if (count($parts) >= 3) {
            $prefix = $parts[0];   // MB, PD
            $suffix = end($parts); // A0112
            
            if (preg_match('/^([A-Z])(\d+)$/', $suffix, $matches)) {
                $char = $matches[1]; // A
                $num  = (int)$matches[2]; // 112
                
                $charIndex = ord($char) - 65; 
                if ($charIndex < 0) continue;
                
                $currentVal = ($charIndex * 9999) + $num;
                
                // อัปเดตค่าสูงสุดของ Prefix นั้นๆ
                if (!isset($maxCounts[$prefix]) || $currentVal > $maxCounts[$prefix]) {
                    $maxCounts[$prefix] = $currentVal;
                }
            }
        }
    }

    // สร้าง Map ของ Suffix ถัดไป (เช่น MB => A0113, PD => A0001)
    $nextSuffixes = [];
    // รายชื่อ Prefix ที่เป็นไปได้ทั้งหมด
    $allPrefixes = ['MB','IP','PD','IM','WA','PC','MN','OT']; 
    
    foreach ($allPrefixes as $p) {
        $max = $maxCounts[$p] ?? 0; // ถ้าไม่มีใน DB ให้เริ่มที่ 0
        $next = $max + 1;
        
        $setIndex = floor(($next - 1) / 9999); 
        $numIndex = ($next - 1) % 9999 + 1;
        $char = chr(65 + $setIndex);
        
        $nextSuffixes[$p] = $char . sprintf('%04d', $numIndex);
    }
    
    return $nextSuffixes;
}

// เตรียมข้อมูลส่งให้ JS
$nextSuffixMap = get_next_global_suffix_map($pdo); // ชื่อฟังก์ชันแก้เป็น get_next_suffix_map
function get_next_global_suffix_map($pdo){ return get_next_suffix_map($pdo); } // wrapper

// Helper Log
function donor_doc(PDO $pdo, string $action, int $donor_id, array $item_or_diff, $user_id): void {
  try {
    $action = strtoupper($action);
    $txt = "$action donor #$donor_id";
    if ($action === 'CREATE') {
      $modelStr = trim(($item_or_diff['device_type']??'') . ' ' . ($item_or_diff['device_series']??'') . ' ' . ($item_or_diff['model_code']??''));
      $txt = "เพิ่มเครื่อง: {$modelStr} [Tag: ".($item_or_diff['internal_id']??'')."]";
    } elseif ($action === 'UPDATE') {
      $changed = array_keys($item_or_diff['changed'] ?? []);
      $txt = "แก้ไขเครื่อง (#{$donor_id}) ".($changed ? 'fields: '.implode(',', $changed) : '');
    } elseif ($action === 'DELETE') {
      $txt = "ลบเครื่อง (#{$donor_id})";
    }
    $remarks = short_remarks($txt, 255);
    $sql = "INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
            VALUES ('DONOR', :ref, :remarks, :uid, NOW())";
    $pdo->prepare($sql)->execute([':ref'=>'DONOR:'.$donor_id, ':remarks'=>$remarks, ':uid'=>$user_id]);
  } catch (Throwable $e) {}
}

// ========== 3. INIT STATE ==========
$typeOptions = ['MacBook','iMac','iPhone','iPad','Apple Watch','Surface/PC','Other'];
$seriesOptions = ['Air','Pro','12','Mini','Pro Max','Plus']; 
$statusOptions = ['in_stock'=>'พร้อมแยก (in_stock)', 'reserved'=>'จอง (reserved)', 'for_sale'=>'กำลังขาย (for_sale)', 'stripped'=>'แยกแล้ว (stripped)', 'sold'=>'ขายแล้ว (sold)'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = [
  'internal_id'=>'', 'device_type'=>'MacBook', 'device_series'=>'', 'model_code'=>'', 
  'serial_no'=>'', 'status'=>'in_stock', 'image_url'=>null, 'remarks'=>'', 'location_index'=>''
];

$beforeRow = null;
if ($id) {
    $st = $pdo->prepare("SELECT * FROM parts_donors WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $beforeRow = $st->fetch(PDO::FETCH_ASSOC);
    if(!$beforeRow) { header("Location: index.php?tab=donor&err=".urlencode("ไม่พบเครื่อง")); exit; }
    $item = array_merge($item, $beforeRow);
}

$errors = [];

// ========== 4. ACTION HANDLER (POST) ==========
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = val($_POST,'action');

    // --- DELETE ---
    if ($action==='delete_donor' && $id) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM parts_used WHERE donor_id=?");
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) $errors[] = "ลบไม่ได้: มีอะไหล่มือ 2 ผูกกับเครื่องนี้";
        else {
            donor_doc($pdo, 'DELETE', $id, ['before'=>$beforeRow], $user_id);
            $pdo->prepare("DELETE FROM parts_donors WHERE id=? LIMIT 1")->execute([$id]);
            header("Location: index.php?tab=donor&msg=".urlencode("ลบเครื่องเรียบร้อย")); exit;
        }
    }

    // --- SAVE (INSERT / UPDATE) ---
    if ($action==='save_donor') {
        $form_id = (int)($_POST['id'] ?? 0);
        
        $rawTag = trim(val($_POST,'internal_id'));
        $item['internal_id']    = strtoupper($rawTag); 
        $item['device_type']    = val($_POST,'device_type','MacBook');
        $item['device_series']  = val($_POST,'device_series');
        $item['model_code']     = strtoupper(val($_POST,'model_code')); 
        $item['serial_no']      = val($_POST,'serial_no');
        $item['status']         = val($_POST,'status','in_stock');
        $item['remarks']        = val($_POST,'remarks');
        $item['location_index'] = mb_substr(val($_POST,'location_index'), 0, 60);
        $remove_image_flag      = (int)($_POST['remove_image'] ?? 0);

        if ($item['device_type']==='') $errors[] = "กรุณาระบุประเภทอุปกรณ์";
        if (!isset($statusOptions[$item['status']])) $errors[] = "สถานะไม่ถูกต้อง";
        if ($item['internal_id']==='') $errors[] = "กรุณาระบุ Asset Tag (หรือรอให้ระบบ Autofill)";

        // Check Duplicate (Prefix+Suffix Only)
        if ($item['internal_id'] !== '') {
            $parts = explode('-', $item['internal_id']);
            if (count($parts) >= 3) {
                $prefix = $parts[0]; $suffix = end($parts);
                // หาคนที่มี Prefix นี้ และ Suffix นี้ (ไม่สนตรงกลาง)
                $sqlCheck = "SELECT internal_id FROM parts_donors 
                             WHERE internal_id LIKE ? AND internal_id LIKE ? AND id != ? LIMIT 1";
                $stmtCheck = $pdo->prepare($sqlCheck);
                $stmtCheck->execute([$prefix . '-%', '%-' . $suffix, $form_id]);
                $duplicate = $stmtCheck->fetchColumn();
                if ($duplicate) $errors[] = "⛔ บันทึกไม่ได้! เลขรัน '{$suffix}' ซ้ำกับหมวด {$prefix} (ชนกับ {$duplicate})";
            } else {
                // Fallback check
                $stmtFull = $pdo->prepare("SELECT id FROM parts_donors WHERE internal_id = ? AND id != ?");
                $stmtFull->execute([$item['internal_id'], $form_id]);
                if ($stmtFull->fetch()) $errors[] = "⛔ Asset Tag '{$item['internal_id']}' มีอยู่แล้ว";
            }
        }

        // Upload Image
        $newImage = null;
        if (!empty($_FILES['image']['name'])) {
            $f = $_FILES['image'];
            if ($f['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp']) && $f['size'] <= 5*1024*1024) {
                    $new = safeUploadName($f['name']);
                    if (move_uploaded_file($f['tmp_name'], PARTS_UPLOAD_DIR.$new)) {
                        $newImage = $new; $remove_image_flag = 0;
                    } else $errors[] = "อัปโหลดรูปไม่สำเร็จ";
                } else $errors[] = "ไฟล์รูปต้องเป็น jpg, png, webp ขนาดไม่เกิน 5MB";
            } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
                $errors[] = "อัปโหลดรูปผิดพลาด (code {$f['error']})";
            }
        }

        if (!$errors) {
            try {
                if ($form_id) { // UPDATE
                    if (!$beforeRow) {
                        $stB = $pdo->prepare("SELECT * FROM parts_donors WHERE id=? LIMIT 1");
                        $stB->execute([$form_id]);
                        $beforeRow = $stB->fetch(PDO::FETCH_ASSOC) ?: [];
                    }
                    $sql = "UPDATE parts_donors SET 
                            internal_id=?, device_type=?, device_series=?, model_code=?, serial_no=?, status=?,
                            remarks=?, location_index=?, updated_at=NOW()";
                    $params = [$item['internal_id'], $item['device_type'], $item['device_series'], $item['model_code'], $item['serial_no'], $item['status'], $item['remarks'], $item['location_index']];
                    
                    if ($newImage !== null) { $sql .= ", image_url=?"; $params[] = $newImage; } 
                    elseif ($remove_image_flag===1) { $sql .= ", image_url=NULL"; }
                    
                    $sql .= " WHERE id=?"; $params[] = $form_id;
                    $pdo->prepare($sql)->execute($params);

                    // Log
                    $changed = [];
                    foreach (['internal_id','device_type','device_series','model_code','serial_no','status','image_url','remarks','location_index'] as $k) {
                        $afterVal = ($k==='image_url') ? ($newImage!==null?$newImage:(($remove_image_flag===1)?null:($beforeRow[$k]??null))) : $item[$k];
                        if (($beforeRow[$k]??null) !== $afterVal) $changed[$k] = true;
                    }
                    donor_doc($pdo, 'UPDATE', $form_id, ['changed'=>$changed], $user_id);
                    header("Location: index.php?tab=donor&msg=".urlencode("บันทึกการแก้ไขแล้ว ({$item['internal_id']})")); exit;

                } else { // INSERT
                    $sql = "INSERT INTO parts_donors
                            (internal_id, device_type, device_series, model_code, serial_no, status,
                             image_url, remarks, location_index, created_at, updated_at)
                            VALUES (?,?,?,?,?,?, ?,?,?, NOW(), NOW())";
                    $pdo->prepare($sql)->execute([
                        $item['internal_id'], $item['device_type'], $item['device_series'], $item['model_code'], 
                        $item['serial_no'], $item['status'], $newImage, $item['remarks'], $item['location_index']
                    ]);
                    $new_id = (int)$pdo->lastInsertId();
                    donor_doc($pdo, 'CREATE', $new_id, $item, $user_id);
                    header("Location: index.php?tab=donor&msg=".urlencode("เพิ่มเครื่องแล้ว ({$item['internal_id']})")); exit;
                }
            } catch(Throwable $e){
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) $errors[] = "⛔ Database Error: Asset Tag ซ้ำ!";
                else $errors[] = $e->getMessage();
            }
        }
        if ($newImage !== null) $item['image_url'] = $newImage;
    }
}

// ========== 5. TEMPLATE & LOADING CSS ==========
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<style>
  #loading-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(255, 255, 255, 0.85);
    z-index: 9999;
    display: none;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    backdrop-filter: blur(2px);
  }
  .spinner {
    width: 50px; height: 50px;
    border: 5px solid #e2e8f0;
    border-top: 5px solid #2563eb;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
  }
  .loading-text { font-size: 1.1rem; color: #1e293b; font-weight: 600; animation: pulse 1.5s infinite; }
  @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
  @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }
  .restock-form .form-hint{grid-column:2/3;margin-top:6px;color:#6B7280;font-size:12px;line-height:1.35}
</style>

<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?> <?= $id ? '(แก้ไข #' . (int)$id . ')' : '(เพิ่มรายการใหม่)' ?></span>
    <a href="index.php?tab=donor" class="view-site">← กลับรายการเครื่อง</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger" style="margin-top:12px;">
      <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form id="donorForm" method="post" enctype="multipart/form-data" class="card restock-form" style="margin-top:16px;" novalidate>
    <input type="hidden" name="action" id="donorAction" value="save_donor">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="remove_image" id="remove_image" value="0">

    <div class="form-grid">
      <div class="form-item">
        <label class="form-label" for="internal_id">Asset Tag (รหัสร้าน)</label>
        <input id="internal_id" name="internal_id" class="input filter-input" 
               value="<?= h($item['internal_id']) ?>" 
               placeholder="เช่น MB-202512-A0113"
               style="font-family: monospace; font-weight: bold; letter-spacing: 1px; color: #2563eb;">
        <small class="form-hint">ระบบจะใส่เลขถัดไปให้อัตโนมัติ (แยกตามชนิดอุปกรณ์)</small>
      </div>

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
        <label class="form-label" for="device_type">ประเภท *</label>
        <select id="device_type" name="device_type" class="input filter-input">
          <?php foreach ($typeOptions as $opt): ?>
            <option value="<?= h($opt) ?>" <?= $item['device_type']===$opt ? 'selected' : '' ?>><?= h($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-item">
        <label class="form-label" for="device_series">ซีรีส์/รุ่นย่อย</label>
        <input id="device_series" name="device_series" class="input filter-input" value="<?= h($item['device_series']) ?>" placeholder="เช่น Air, Pro, 12, Pro Max" list="seriesList">
        <datalist id="seriesList"><?php foreach($seriesOptions as $s): ?><option value="<?= h($s) ?>"><?php endforeach; ?></datalist>
      </div>

      <div class="form-item">
        <label class="form-label" for="model_code">รหัสโมเดล (Axxxx)</label>
        <input id="model_code" name="model_code" class="input filter-input" value="<?= h($item['model_code']) ?>" placeholder="เช่น A1708, A2338">
      </div>

      <div class="form-item">
        <label class="form-label" for="serial_no">Serial</label>
        <input id="serial_no" name="serial_no" class="input filter-input" value="<?= h($item['serial_no']) ?>">
      </div>

      <div class="form-item">
        <label class="form-label" for="status">สถานะ</label>
        <select id="status" name="status" class="input filter-input">
          <?php foreach($statusOptions as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= $item['status']===$key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small class="form-hint">ตั้งเป็น <code>stripped</code> เมื่อแยกอะไหล่แล้ว</small>
      </div>

      <div class="form-item">
        <label class="form-label" for="location_index">ที่เก็บ</label>
        <input id="location_index" name="location_index" class="input filter-input" maxlength="60"
               value="<?= h($item['location_index']) ?>" placeholder="เช่น ชั้นB-กล่อง3 หรือ C1-R2">
        <small class="form-hint">ไม่เกิน 60 ตัวอักษร</small>
      </div>

      <div class="form-item">
        <label class="form-label" for="remarks">หมายเหตุ</label>
        <textarea id="remarks" name="remarks" class="input filter-input" rows="3" placeholder="รายละเอียดเพิ่มเติม เช่น อาการ/สภาพ/แหล่งที่มา"><?= h($item['remarks']) ?></textarea>
      </div>

      <div class="form-actions">
        <button class="btn-primary" type="submit"><?= $id ? 'บันทึกการแก้ไข' : 'เพิ่มเครื่อง' ?></button>
        <a class="btn-secondary" href="index.php?tab=donor">ยกเลิก</a>
        <?php if ($id): ?>
          <button type="button" class="btn-secondary" onclick="return deleteDonor();">ลบเครื่อง</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
</main>

<div id="loading-overlay">
  <div class="spinner"></div>
  <div class="loading-text">กำลังบันทึกข้อมูล...</div>
</div>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
  function showLoading() {
      document.getElementById('loading-overlay').style.display = 'flex';
  }

  // ---[ JS: AUTOFILL ID (Client-Side) ]---
  
  // 1. รับค่า Next Suffix Map จาก PHP (แปลงเป็น JSON)
  const suffixMap = <?= json_encode($nextSuffixMap) ?>; 
  // ตัวอย่างผลลัพธ์: { 'MB': 'A0113', 'PD': 'A0001', 'IP': 'A0050', ... }

  const currentYM = '<?= date('Ym') ?>';
  
  // Mapping ชื่อประเภท -> Prefix
  const typeToPrefix = {
      'MacBook': 'MB', 'iPhone': 'IP', 'iPad': 'PD', 'iMac': 'IM',
      'Apple Watch': 'WA', 'Surface/PC': 'PC', 'Monitor': 'MN', 'Other': 'OT'
  };

  const typeSelect = document.getElementById('device_type');
  const inputId = document.getElementById('internal_id');
  
  // ฟังก์ชันคำนวณและใส่ค่าลงใน input
  function autofillID() {
      // ถ้าเป็นโหมดแก้ไข (มี ID เดิมอยู่แล้ว) ไม่ต้องทำอะไร
      // หรือถ้า User พิมพ์เองไปแล้วก็ไม่ทับ (ยกเว้นว่าง)
      if (inputId.value.trim() !== '' && <?= $id ? 'true' : 'false' ?>) return;

      const selectedType = typeSelect.value;
      const prefix = typeToPrefix[selectedType] || 'OT';
      
      // หา Suffix ถัดไปของ Prefix นี้ (ถ้าไม่มีให้เริ่ม A0001)
      const nextSuffix = suffixMap[prefix] || 'A0001';
      
      // ใส่ค่าลงใน input
      inputId.value = `${prefix}-${currentYM}-${nextSuffix}`;
  }

  // เรียกใช้เมื่อเปลี่ยนประเภท
  typeSelect.addEventListener('change', () => {
      // เมื่อเปลี่ยนประเภท บังคับเปลี่ยนเลขใหม่เสมอ (ถ้ายังไม่ได้ Save)
      // แต่เช็คหน่อยว่าถ้าเป็นหน้า Edit อาจจะไม่ควรเปลี่ยนมั่วซั่ว
      if (<?= $id ? 'false' : 'true' ?>) {
          inputId.value = ''; // เคลียร์ของเก่าก่อน
          autofillID();       // เจนฯ ของใหม่
      }
  });

  // เรียกใช้ครั้งแรกตอนโหลดหน้า (เฉพาะหน้าเพิ่มใหม่)
  if (<?= $id ? 'false' : 'true' ?>) {
      autofillID();
  }

  // ---[ IMAGE PREVIEW ]---
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

  document.getElementById('donorForm').addEventListener('submit', function() {
      showLoading();
  });

  function deleteDonor(){
    if(!confirm('ยืนยันลบเครื่องนี้ถาวร?')) return false;
    document.querySelector('#loading-overlay .loading-text').textContent = 'กำลังลบข้อมูล...';
    showLoading();
    document.getElementById('donorAction').value = 'delete_donor';
    document.getElementById('donorForm').submit();
    return false;
  }
</script>