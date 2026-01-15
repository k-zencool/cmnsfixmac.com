<?php
/********************************************************************
 * admin/parts/form.php  (มือ 1)
 * Update: 
 * - Show SKU field (Readonly)
 * - Smart SKU Generation (P-{TYPE}-{YYYYMM}-Axxxx)
 * - Show Generated SKU in success message
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'manager']);

$pageTitle = "ฟอร์มอะไหล่ (มือ 1)";

/* ---------------- helpers ---------------- */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function normalize_part_number($v){
  $v = trim((string)($v ?? ''));
  if ($v === '' || $v === '-') return null;
  return $v;
}

/* ---------------- Upload constants ---------------- */
define('PUBLIC_ROOT', realpath(__DIR__ . '/../../'));
define('UPLOAD_DIR',  PUBLIC_ROOT . '/uploads/parts/');
define('UPLOAD_URL',  '/uploads/parts/');

if (!is_dir(UPLOAD_DIR)) {
  if (!@mkdir(UPLOAD_DIR, 0775, true) && !is_dir(UPLOAD_DIR)) {
    throw new RuntimeException('สร้างโฟลเดอร์อัปโหลดไม่สำเร็จ: ' . UPLOAD_DIR);
  }
}
if (!is_writable(UPLOAD_DIR)) { @chmod(UPLOAD_DIR, 0775); }

function safeUploadName(string $orig): string {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if ($ext === '') $ext = 'jpg';
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  if ($base==='') $base='part';
  return $base.'_'.date('Ymd_His').'_'.substr(bin2hex(random_bytes(6)),0,12).'.'.$ext;
}

function img_src($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  if (preg_match('~^https?://~i',$v) || $v[0]==='/') return $v;
  return UPLOAD_URL . $v;
}

function normalize_local_image($v): string {
  $v = trim((string)$v);
  if ($v==='') return '';
  if (!preg_match('~^https?://~i',$v) && $v[0]!=='/') return $v;
  if (strpos($v, '/uploads/parts/') === 0) return substr($v, strlen('/uploads/parts/'));
  $needle = '/uploads/parts/';
  $pos = strrpos($v, $needle);
  if ($pos !== false) return substr($v, $pos + strlen($needle));
  return '';
}
function is_local_image($v): bool { return normalize_local_image($v) !== ''; }

/* ---------------- SMART SKU LOGIC ---------------- */
function detectPrefix($name, $model) {
    $text = strtolower($name . ' ' . $model);
    if (strpos($text, 'iphone') !== false) return 'IP';
    if (strpos($text, 'ipad') !== false)   return 'PD';
    if (strpos($text, 'imac') !== false)   return 'IM';
    if (strpos($text, 'watch') !== false)  return 'WA';
    return 'MB'; // Default = MacBook/Other
}

function genSmartSKU(PDO $pdo, string $partName, string $deviceModel): string {
    $prefix = detectPrefix($partName, $deviceModel);
    $ym = date('Ym'); 

    // หาเลข Axxxx สูงสุดที่มีอยู่ในระบบ (Global Running)
    $stmt = $pdo->query("SELECT part_code FROM parts_new WHERE part_code LIKE 'P-%-A%'");
    $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $maxSeq = 0;
    foreach ($codes as $c) {
        if (preg_match('/-A(\d+)$/', $c, $m)) {
            $val = (int)$m[1];
            if ($val > $maxSeq) $maxSeq = $val;
        }
    }

    $nextSeq = $maxSeq + 1;

    // ลอง Gen และเช็คซ้ำ
    for ($i=0; $i<10; $i++) {
        $sku = sprintf("P-%s-%s-A%04d", $prefix, $ym, $nextSeq);
        
        $chk = $pdo->prepare("SELECT 1 FROM parts_new WHERE part_code = ? LIMIT 1");
        $chk->execute([$sku]);
        
        if (!$chk->fetch()) return $sku; // ไม่ซ้ำ ใช้ได้เลย
        
        $nextSeq++; // ถ้าซ้ำ ให้บวกเพิ่มแล้ววนใหม่
    }

    // Fallback กรณีซ้ำรัวๆ (ไม่ควรเกิด)
    return 'P-' . $prefix . '-' . date('YmdHis');
}

/* ---------------- LOG ADJUST ---------------- */
function log_adjust(PDO $pdo, int $user_id, string $location, string $part_code, int $delta): int {
  $remarks = "manual adjust (@{$location})";
  if (strlen($remarks) > 255) $remarks = substr($remarks, 0, 255);
  $pdo->prepare("INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at) VALUES ('ADJUST', NULL, ?, ?, NOW())")->execute([$remarks, $user_id]);
  $doc_id = (int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost) VALUES (?, ?, ?, ?, ?, NULL)")->execute([$doc_id, $part_code, $delta, $delta<0 ? $location : NULL, $delta>0 ? $location : NULL]);
  return $doc_id;
}

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

/* ---------------- UI base ---------------- */
$categories = ['MacBook','iMac','iPhone','iPad','Apple Watch','Other'];

/* ---------------- load ---------------- */
$pc = isset($_GET['part_code']) ? trim($_GET['part_code']) : '';

$meta = [
  'part_code'     => $pc,
  'part_name'     => '',
  'part_number'   => null,
  'device_models' => '',
  'category'      => 'MacBook',
  'image_url'     => null,
  'min_stock'     => 0,
];

if ($pc!=='') {
  $st = $pdo->prepare("SELECT part_code, part_name, part_number, device_models, category, image_url FROM parts_new WHERE part_code=? ORDER BY location LIMIT 1");
  $st->execute([$pc]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) $meta = array_merge($meta, $row);

  $st = $pdo->prepare("SELECT COALESCE(MAX(min_stock),0) FROM parts_new WHERE part_code=?");
  $st->execute([$pc]);
  $meta['min_stock'] = (int)$st->fetchColumn();
}

$locations=[];
if ($meta['part_code']!=='') {
  $st=$pdo->prepare("SELECT location, SUM(quantity) AS qty FROM parts_new WHERE part_code=? GROUP BY location ORDER BY location");
  $st->execute([$meta['part_code']]);
  $locations=$st->fetchAll(PDO::FETCH_ASSOC);
}

$knownLocs=[];
foreach($locations as $r){
  $L = isset($r['location'])? trim((string)$r['location']) : '';
  if ($L!=='' && !in_array($L,$knownLocs,true)) $knownLocs[]=$L;
}
if (!in_array('main',$knownLocs,true)) $knownLocs[]='main';

$curLoc='main'; $curQty=0;
if (!empty($locations)) {
  $curLoc = $locations[0]['location']; $curQty=(int)$locations[0]['qty'];
  foreach($locations as $r){
    if (($r['location']??'')==='main'){ $curLoc='main'; $curQty=(int)$r['qty']; break; }
  }
}

/* ---------------- actions ---------------- */
$errors=[];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
  $redirectPage = isset($_POST['page']) ? (int)$_POST['page'] : 1;
  $del_code = trim($_POST['del_code'] ?? '');
  if ($del_code==='') $errors[]="ไม่พบรหัสที่จะลบ";
  else {
    try{
      $pdo->beginTransaction();
      $st=$pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM parts_new WHERE part_code=? FOR UPDATE");
      $st->execute([$del_code]);
      $sum=(int)$st->fetchColumn();
      if ($sum!==0) throw new Exception("ลบไม่ได้: ยอดรวมทุกที่เก็บต้องเป็น 0 (ตอนนี้ {$sum})");
      $pdo->prepare("DELETE FROM parts_new WHERE part_code=?")->execute([$del_code]);
      $pdo->commit();
      
      header("Location: index.php?tab=new&page={$redirectPage}&msg=".urlencode("ลบ {$del_code} แล้ว"));
      exit;
    }catch(Throwable $e){
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[]=$e->getMessage();
    }
  }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_all') {
  $redirectPage = isset($_POST['page']) ? (int)$_POST['page'] : 1;

  $old_code      = trim($_POST['existing_code'] ?? '');
  $part_code     = $old_code ?: '';
  $part_name     = trim($_POST['part_name'] ?? '');
  $part_number   = normalize_part_number($_POST['part_number'] ?? null);
  $device_models = trim($_POST['device_models'] ?? '');
  $category      = trim($_POST['category'] ?? 'MacBook');
  $location      = trim($_POST['location'] ?? $curLoc);
  if ($location==='') $location='main';
  $desired_qty   = max(0, (int)($_POST['desired_qty'] ?? $curQty));
  $min_stock     = max(0, (int)($_POST['min_stock'] ?? (int)$meta['min_stock']));

  $user_id = (int)($_SESSION['admin_id'] ?? ($_SESSION['user']['id'] ?? 0));

  if ($part_name==='') $errors[]="กรอกชื่ออะไหล่";
  if (!in_array($category,$categories,true)) $category='Other';
  if (!$user_id) $errors[]="ไม่พบผู้ใช้งาน";

  if ($part_number !== null && mb_strtolower($part_name)===mb_strtolower($part_number)) {
    $errors[]="ชื่ออะไหล่กับเลขอะไหล่ห้ามเหมือนกัน";
  }
  if ($part_number !== null) {
    if ($old_code!=='') {
      $st=$pdo->prepare("SELECT part_code FROM parts_new WHERE part_number IS NOT NULL AND LOWER(part_number)=LOWER(?) AND part_code<>? LIMIT 1");
      $st->execute([$part_number,$old_code]);
    } else {
      $st=$pdo->prepare("SELECT part_code FROM parts_new WHERE part_number IS NOT NULL AND LOWER(part_number)=LOWER(?) LIMIT 1");
      $st->execute([$part_number]);
    }
    if ($st->fetch(PDO::FETCH_ASSOC)) $errors[]="เลขอะไหล่นี้ถูกใช้แล้วโดยรายการอื่น";
  }

  $old_image   = $meta['image_url'] ?? null;
  $want_remove = isset($_POST['remove_image']) && $_POST['remove_image']=='1';
  $new_image   = null;

  if (!empty($_FILES['image']['name'])) {
    $f = $_FILES['image'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        if ($f['error'] !== UPLOAD_ERR_NO_FILE) $errors[] = 'อัปโหลดรูปผิดพลาด Code: '.$f['error'];
    } else {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp'])) $errors[] = "รองรับเฉพาะ jpg, png, webp";
      elseif ($f['size'] > 5*1024*1024) $errors[] = "ไฟล์ใหญ่เกิน 5MB";
      elseif (!is_uploaded_file($f['tmp_name'])) $errors[] = "ไม่พบไฟล์";
      else {
        $new = safeUploadName($f['name']);
        if (@move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $new)) {
          @chmod(UPLOAD_DIR . $new, 0644);
          $new_image = $new;
        } else {
          $errors[] = "เขียนไฟล์ไม่สำเร็จ";
        }
      }
    }
  }

  $imgMode = 'keep';
  $imgValue = null;
  if ($new_image) { $imgMode = 'set'; $imgValue = $new_image; }
  elseif ($want_remove) { $imgMode = 'clear'; }

  if (!$errors){
    try{
      $pdo->beginTransaction();

      // [Generate SKU] ถ้าเป็นของใหม่ ให้เจนรหัส
      $isNew = ($part_code === '');
      if ($isNew) {
        $part_code = genSmartSKU($pdo, $part_name, $device_models);
      }

      if ($imgMode === 'set') $imgForInsert = $imgValue;
      elseif ($imgMode === 'clear') $imgForInsert = null;
      else $imgForInsert = $old_image ?? null;

      $stmt = $pdo->prepare("INSERT INTO parts_new (part_code, part_name, part_number, device_models, category, image_url, min_stock, location, quantity) VALUES (:code, :name, :pnum, :models, :cat, :img, :min, :loc, 0) ON DUPLICATE KEY UPDATE part_name=VALUES(part_name), part_number=VALUES(part_number), device_models=VALUES(device_models), category=VALUES(category), image_url=COALESCE(VALUES(image_url), image_url), min_stock=VALUES(min_stock)");
      $stmt->bindValue(':code',   $part_code);
      $stmt->bindValue(':name',   $part_name);
      if ($part_number === null) $stmt->bindValue(':pnum', null, PDO::PARAM_NULL); else $stmt->bindValue(':pnum', $part_number, PDO::PARAM_STR);
      $stmt->bindValue(':models', $device_models);
      $stmt->bindValue(':cat',    $category);
      $stmt->bindValue(':img',    $imgForInsert);
      $stmt->bindValue(':min',    $min_stock, PDO::PARAM_INT);
      $stmt->bindValue(':loc',    $location);
      $stmt->execute();

      if ($imgMode === 'set') {
        $stmt = $pdo->prepare("UPDATE parts_new SET part_name=:name, part_number=:pnum, device_models=:models, category=:cat, image_url=:img, min_stock=:min WHERE part_code=:code");
        $stmt->bindValue(':img', $imgValue);
      } elseif ($imgMode === 'clear') {
        $stmt = $pdo->prepare("UPDATE parts_new SET part_name=:name, part_number=:pnum, device_models=:models, category=:cat, image_url=NULL, min_stock=:min WHERE part_code=:code");
      } else {
        $stmt = $pdo->prepare("UPDATE parts_new SET part_name=:name, part_number=:pnum, device_models=:models, category=:cat, min_stock=:min WHERE part_code=:code");
      }
      $stmt->bindValue(':name',   $part_name);
      if ($part_number === null) $stmt->bindValue(':pnum', null, PDO::PARAM_NULL); else $stmt->bindValue(':pnum', $part_number, PDO::PARAM_STR);
      $stmt->bindValue(':models', $device_models);
      $stmt->bindValue(':cat',    $category);
      $stmt->bindValue(':min',    $min_stock, PDO::PARAM_INT);
      $stmt->bindValue(':code',   $part_code);
      $stmt->execute();

      if (($imgMode==='set' || $imgMode==='clear') && $old_image) {
        $oldLocal = normalize_local_image($old_image);
        $newLocal = normalize_local_image($new_image ?? '');
        if ($oldLocal && $oldLocal !== $newLocal) { @unlink(UPLOAD_DIR . $oldLocal); }
      }

      $st=$pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM parts_new WHERE part_code=? AND location=? FOR UPDATE");
      $st->execute([$part_code,$location]);
      $currentQty=(int)$st->fetchColumn();
      $delta = (int)$desired_qty - $currentQty;

      if ($delta!==0){
        log_adjust($pdo, $user_id, $location, $part_code, $delta);
        $pdo->prepare("UPDATE parts_new SET quantity=quantity+? WHERE part_code=? AND location=?")->execute([$delta,$part_code,$location]);
      }

      $pdo->commit();
      
      // [Success Message] บอกเลข SKU ในข้อความแจ้งเตือน
      $msgText = $isNew ? "เพิ่มสำเร็จ! รหัสสินค้าคือ {$part_code}" : "บันทึกเรียบร้อย ({$part_code})";
      header("Location: index.php?tab=new&page={$redirectPage}&msg=".urlencode($msgText));
      exit;
    }catch(Throwable $e){
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[]=$e->getMessage();
    }
  }

  $meta['part_code']=$part_code;
  $meta['part_name']=$part_name;
  $meta['part_number']=$part_number;
  $meta['device_models']=$device_models;
  $meta['category']=$category;
  $meta['min_stock']=$min_stock;
  if ($new_image) { $meta['image_url']=$new_image; } elseif ($want_remove) { $meta['image_url']=null; }
  $curLoc=$location; $curQty=$desired_qty;
}

/* ---------------- template ---------------- */
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?> <?= $meta['part_code'] ? '· แก้ไข: '.h($meta['part_code']) : '' ?></span>
    <a href="index.php?tab=new&page=<?= $currentPage ?>" class="view-site">← กลับรายการ</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger" style="margin-top:12px;">
      <?php foreach($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form id="mainForm" method="post" enctype="multipart/form-data" class="card restock-form" style="margin-top:16px;" novalidate>
    <input type="hidden" name="action" id="actionField" value="save_all">
    <input type="hidden" name="existing_code" value="<?= h($meta['part_code']) ?>">
    <input type="hidden" name="del_code" id="del_codeField" value="">
    <input type="hidden" name="remove_image" id="remove_image" value="0">
    <input type="hidden" name="page" value="<?= $currentPage ?>">

    <div class="form-grid">
      <div class="form-item">
        <label class="form-label" style="font-weight:bold; color:#374151;">รหัสสินค้า (SKU)</label>
        <input class="input filter-input" value="<?= $meta['part_code'] ? h($meta['part_code']) : '(ระบบจะสร้างอัตโนมัติ: P-XX-YYYYMM-Axxxx)' ?>" readonly style="background-color:#f3f4f6; color:#6b7280; cursor:not-allowed; border-color:#e5e7eb; font-family:monospace; font-weight:600;">
      </div>

      <div class="form-item">
        <label class="form-label">รูปภาพ</label>
        <div class="image-upload-ui" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <div id="imgPreviewWrap" style="position:relative;width:120px;height:120px;border:1px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fafafa;cursor:pointer;" title="คลิกหรือลากไฟล์มาวาง">
            <?php if (!empty($meta['image_url'])): ?>
              <img id="imgPreview" src="<?= h(img_src($meta['image_url'])) ?>" alt="preview" style="width:100%;height:100%;object-fit:cover;">
              <button type="button" id="imageRemoveBtn" style="position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);" title="ลบรูป">×</button>
            <?php else: ?>
              <span id="imgPreviewText" class="muted small">ลากรูปมาวาง</span>
              <button type="button" id="imageRemoveBtn" style="display:none;position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);" title="ลบรูป">×</button>
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
        <label class="form-label" for="part_name">ชื่ออะไหล่ *</label>
        <input id="part_name" name="part_name" class="input filter-input" required value="<?= h($meta['part_name']) ?>" placeholder="เช่น BATTERY A1819">
      </div>

      <div class="form-item">
        <label class="form-label" for="part_number">เลขอะไหล่ (ถ้ามี)</label>
        <input id="part_number" name="part_number" class="input filter-input" value="<?= h($meta['part_number']) ?>" placeholder="เช่น A1819 หรือ 661-xxxx (เว้นว่างหรือใส่ - ได้)">
      </div>

      <div class="form-item">
        <label class="form-label" for="device_models">รุ่นที่รองรับ</label>
        <input id="device_models" name="device_models" class="input filter-input" value="<?= h($meta['device_models']) ?>" placeholder="เช่น A1706, A1708 (มีผลต่อรหัส SKU)">
      </div>

      <div class="form-item">
        <label class="form-label" for="category">หมวดหมู่</label>
        <select id="category" name="category" class="input filter-input">
          <?php foreach($categories as $c): ?>
            <option value="<?= h($c) ?>" <?= $meta['category']===$c ? 'selected' : '' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-item">
        <label class="form-label" for="min_stock">ขั้นต่ำ (แจ้งเตือน)</label>
        <input type="number" id="min_stock" name="min_stock" class="input filter-input" min="0" value="<?= (int)$meta['min_stock'] ?>" placeholder="0" style="max-width:160px;">
      </div>

      <div class="form-item">
        <label class="form-label" for="location">ที่เก็บหลัก *</label>
        <input id="location" name="location" class="input filter-input" list="locs" required value="<?= h($curLoc) ?>" placeholder="เช่น main, shelf-A3">
        <datalist id="locs">
          <?php foreach($knownLocs as $L): ?><option value="<?= h($L) ?>"></option><?php endforeach; ?>
        </datalist>
      </div>

      <div class="form-item">
        <label class="form-label">จำนวนตั้งต้น *</label>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <span class="muted small">ปัจจุบัน</span>
          <input id="curQty" class="input filter-input" value="<?= (int)$curQty ?>" readonly style="max-width:120px; background:#f9fafb;">
          <span class="muted small">ปรับเป็น</span>
          <input type="number" name="desired_qty" id="desired_qty" class="input filter-input" min="0" required value="<?= (int)$curQty ?>" style="max-width:160px;">
        </div>
      </div>

      <div class="form-actions">
        <button class="btn-primary" type="submit">บันทึกข้อมูล</button>
        <a class="btn-secondary" href="index.php?tab=new&page=<?= $currentPage ?>">ยกเลิก</a>
        <?php if ($meta['part_code']): ?>
          <button type="button" class="btn-secondary" onclick="return confirmDelete('<?= h($meta['part_code']) ?>');" style="color:#ef4444; border-color:#ef4444;">ลบรายการนี้</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
  (function(){
    var locInput=document.getElementById('location');
    var curQtyEl=document.getElementById('curQty');
    var desEl=document.getElementById('desired_qty');
    var locMap={};
    <?php foreach($locations as $r): ?>
      locMap[<?= json_encode((string)$r['location']) ?>]=<?= (int)$r['qty'] ?>;
    <?php endforeach; ?>
    if(typeof locMap['main']==='undefined') locMap['main']=0;

    function refreshQty(){
      var L=(locInput.value||'main').trim();
      var q=(typeof locMap[L]!=='undefined') ? locMap[L] : 0;
      curQtyEl.value=q;
      if(desEl && (desEl.value==='' || +desEl.value<0)) desEl.value=q;
    }
    if(locInput) locInput.addEventListener('input', refreshQty);
    refreshQty();
  })();

  function confirmDelete(code){
    if(!code) return false;
    if(!confirm('ยืนยันลบอะไหล่ '+code+' ? ต้องมียอดรวมทุกที่เก็บ = 0')) return false;
    var f=document.getElementById('mainForm');
    document.getElementById('actionField').value='delete';
    document.getElementById('del_codeField').value=code;
    f.submit(); return false;
  }

  (function(){
    var nameEl=document.getElementById('part_name');
    var numEl=document.getElementById('part_number');
    if(!nameEl||!numEl) return;
    function norm(v){ v=(v||'').trim(); return (v===''||v==='-')? '' : v.toLowerCase(); }
    function validate(){
      var same=(nameEl.value.trim().toLowerCase()===norm(numEl.value));
      if(same){ var msg='ชื่ออะไหล่กับเลขอะไหล่ห้ามเหมือนกัน'; nameEl.setCustomValidity(msg); numEl.setCustomValidity(msg); }
      else { nameEl.setCustomValidity(''); numEl.setCustomValidity(''); }
    }
    nameEl.addEventListener('input', validate);
    numEl.addEventListener('input', validate);
    validate();
  })();

  (function(){
    var box = document.getElementById('imgPreviewWrap');
    var file = document.getElementById('image');
    var removeBtn = document.getElementById('imageRemoveBtn');
    var txt = document.getElementById('imgPreviewText');
    var rmField = document.getElementById('remove_image');
    if (!box || !file || !rmField) return;

    box.addEventListener('click', function(e){ if (e.target && e.target.id === 'imageRemoveBtn') return; file.click(); });

    file.addEventListener('change', function(){
      if (!file.files || !file.files[0]) return;
      rmField.value = '0';
      var f = file.files[0];
      if (!/\.(jpe?g|png|webp)$/i.test(f.name)) { alert('รองรับเฉพาะ JPG/PNG/WebP'); file.value=''; return; }
      if (f.size > 5*1024*1024) { alert('ไฟล์ใหญ่เกิน 5MB'); file.value=''; return; }
      var url = URL.createObjectURL(f);
      var imgEl = document.getElementById('imgPreview');
      if (!imgEl){
        imgEl = document.createElement('img'); imgEl.id = 'imgPreview';
        imgEl.style.width='100%'; imgEl.style.height='100%'; imgEl.style.objectFit='cover';
        box.appendChild(imgEl);
      }
      imgEl.src = url;
      if (txt) txt.style.display='none';
      if (removeBtn) removeBtn.style.display='block';
    });

    if (removeBtn){
      removeBtn.addEventListener('click', function(e){
        e.stopPropagation(); rmField.value = '1';
        var imgEl = document.getElementById('imgPreview');
        if (imgEl) imgEl.remove();
        if (txt) txt.style.display='';
        file.value=''; removeBtn.style.display='none';
      });
    }
  })();
</script>