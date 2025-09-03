<?php
/********************************************************************
 * admin/parts/form.php  (มือ 1)
 * - เพิ่ม/แก้ไขเมตาอะไหล่ + ปรับยอดเฉพาะโลเคชันที่ระบุ
 * - ออกเอกสาร ADJUST อัตโนมัติเมื่อยอดเปลี่ยน (แสดงในหน้า "ประวัติ")
 * - ใช้สคีมาเดิม: parts_docs.doc_type ∈ {IN, CONSUME, MOVE, ADJUST}
 *                 parts_docs.remarks เป็น VARCHAR(255)
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin', 'manager']);

$pageTitle = "ฟอร์มอะไหล่ (มือ 1)";

// ---------- helpers ----------
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

define('UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

function safeUploadName(string $orig): string {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  if ($base==='') $base='part';
  return $base.'_'.date('Ymd_His').'_'.substr(bin2hex(random_bytes(6)),0,12).'.'.$ext;
}
function genPartCode(PDO $pdo): string {
  for ($i=0;$i<5;$i++){
    $c = 'AUTO-'.date('Ymd-His').'-'.substr(bin2hex(random_bytes(3)),0,6);
    $st=$pdo->prepare("SELECT 1 FROM parts_new WHERE part_code=? LIMIT 1");
    $st->execute([$c]);
    if (!$st->fetch()) return $c;
    usleep(120000);
  }
  return 'AUTO-'.date('Ymd-His').'-'.substr(bin2hex(random_bytes(6)),0,12);
}
function img_src($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  if (preg_match('~^https?://~i',$v) || $v[0]==='/') return $v;
  return '../../uploads/parts/'.$v;
}
/** บันทึกเอกสาร ADJUST ให้ย่อความยาว remarks <= 255 */
function log_adjust(PDO $pdo, int $user_id, string $location, string $part_code, int $delta): int {
  $remarks = "manual adjust (@{$location})";
  if (strlen($remarks) > 255) $remarks = substr($remarks, 0, 255);

  $pdo->prepare("
    INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
    VALUES ('ADJUST', NULL, ?, ?, NOW())
  ")->execute([$remarks, $user_id]);
  $doc_id = (int)$pdo->lastInsertId();

  $pdo->prepare("
    INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
    VALUES (?, ?, ?, ?, ?, NULL)
  ")->execute([$doc_id, $part_code, $delta, $delta<0 ? $location : NULL, $delta>0 ? $location : NULL]);

  return $doc_id;
}

// ---------- UI base ----------
$categories = ['MacBook','iMac','iPhone','iPad','Apple Watch','Other'];

// ---------- load (edit) ----------
$pc = isset($_GET['part_code']) ? trim($_GET['part_code']) : '';

$meta = [
  'part_code'     => $pc,
  'part_name'     => '',
  'part_number'   => '',
  'device_models' => '',
  'category'      => 'MacBook',
  'image_url'     => null,
];

if ($pc!=='') {
  $st = $pdo->prepare("
    SELECT part_code, part_name, part_number, device_models, category, image_url
    FROM parts_new
    WHERE part_code=?
    ORDER BY location
    LIMIT 1
  ");
  $st->execute([$pc]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) $meta = array_merge($meta, $row);
}

// โหลดยอดตามโลเคชัน
$locations=[];
if ($meta['part_code']!=='') {
  $st=$pdo->prepare("
    SELECT location, SUM(quantity) AS qty
    FROM parts_new
    WHERE part_code=?
    GROUP BY location
    ORDER BY location
  ");
  $st->execute([$meta['part_code']]);
  $locations=$st->fetchAll(PDO::FETCH_ASSOC);
}

// datalist โลเคชัน
$knownLocs=[];
foreach($locations as $r){
  $L = isset($r['location'])? trim((string)$r['location']) : '';
  if ($L!=='' && !in_array($L,$knownLocs,true)) $knownLocs[]=$L;
}
if (!in_array('main',$knownLocs,true)) $knownLocs[]='main';

// เดาโลเคชัน/ยอดเริ่ม
$curLoc='main'; $curQty=0;
if (!empty($locations)) {
  $curLoc = $locations[0]['location']; $curQty=(int)$locations[0]['qty'];
  foreach($locations as $r){
    if (($r['location']??'')==='main'){ $curLoc='main'; $curQty=(int)$r['qty']; break; }
  }
}

// ---------- actions ----------
$errors=[];

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
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
      header("Location: index.php?tab=new&msg=".urlencode("ลบ {$del_code} แล้ว"));
      exit;
    }catch(Throwable $e){
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[]=$e->getMessage();
    }
  }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_all') {
  $old_code      = trim($_POST['existing_code'] ?? '');
  $part_code     = $old_code ?: '';
  $part_name     = trim($_POST['part_name'] ?? '');
  $part_number   = trim($_POST['part_number'] ?? '');
  $device_models = trim($_POST['device_models'] ?? '');
  $category      = trim($_POST['category'] ?? 'MacBook');
  $location      = trim($_POST['location'] ?? $curLoc);
  if ($location==='') $location='main';
  $desired_qty   = max(0, (int)($_POST['desired_qty'] ?? $curQty));

  $user_id = (int)($_SESSION['admin_id'] ?? ($_SESSION['user']['id'] ?? 0));

  if ($part_name==='')   $errors[]="กรอกชื่ออะไหล่";
  if ($part_number==='') $errors[]="กรอกเลขอะไหล่";
  if (!in_array($category,$categories,true)) $category='Other';
  if (!$user_id) $errors[]="ไม่พบผู้ใช้งาน";

  // ชื่อ = เลข (ไม่ให้)
  if (mb_strtolower($part_name)===mb_strtolower($part_number)) {
    $errors[]="ชื่ออะไหล่กับเลขอะไหล่ห้ามเหมือนกัน";
  }
  // เลขอะไหล่ห้ามซ้ำกับของคนอื่น
  if ($part_number!=='') {
    if ($old_code!=='') {
      $st=$pdo->prepare("SELECT part_code FROM parts_new WHERE LOWER(part_number)=LOWER(?) AND part_code<>? LIMIT 1");
      $st->execute([$part_number,$old_code]);
    } else {
      $st=$pdo->prepare("SELECT part_code FROM parts_new WHERE LOWER(part_number)=LOWER(?) LIMIT 1");
      $st->execute([$part_number]);
    }
    if ($st->fetch(PDO::FETCH_ASSOC)) $errors[]="เลขอะไหล่นี้ถูกใช้แล้วโดยรายการอื่น";
  }

  // อัปโหลดรูป (ออปชัน)
  $image_url=null;
  if (!empty($_FILES['image']['name'])) {
    $f=$_FILES['image'];
    if ($f['error']===UPLOAD_ERR_OK){
      $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
      if (!in_array($ext,['jpg','jpeg','png','webp'],true)) $errors[]="ไฟล์รูปต้องเป็น jpg, jpeg, png หรือ webp";
      elseif ($f['size']>5*1024*1024) $errors[]="ไฟล์รูปใหญ่เกิน 5MB";
      else{
        $new=safeUploadName($f['name']);
        if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR.$new)) $errors[]="อัปโหลดรูปไม่สำเร็จ";
        else $image_url=$new;
      }
    } elseif ($f['error']!==UPLOAD_ERR_NO_FILE) {
      $errors[]="อัปโหลดรูปผิดพลาด (code {$f['error']})";
    }
  }

  if (!$errors){
    try{
      $pdo->beginTransaction();

      if ($part_code==='') $part_code = genPartCode($pdo);

      // ให้มีแถว (part_code, location) เสมอ
      $pdo->prepare("
        INSERT INTO parts_new
          (part_code, part_name, part_number, device_models, category, image_url, min_stock, location, quantity)
        VALUES (?, ?, ?, ?, ?, ?, 0, ?, 0)
        ON DUPLICATE KEY UPDATE
          part_name=VALUES(part_name),
          part_number=VALUES(part_number),
          device_models=VALUES(device_models),
          category=VALUES(category),
          image_url=COALESCE(VALUES(image_url), image_url)
      ")->execute([$part_code,$part_name,$part_number,$device_models,$category,$image_url,$location]);

      // sync เมตาทุกโลเคชันของ part_code
      $pdo->prepare("
        UPDATE parts_new
        SET part_name=?, part_number=?, device_models=?, category=?, image_url=COALESCE(?, image_url)
        WHERE part_code=?
      ")->execute([$part_name,$part_number,$device_models,$category,$image_url,$part_code]);

      // อ่านยอดปัจจุบันของโลเคชันนี้
      $st=$pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM parts_new WHERE part_code=? AND location=? FOR UPDATE");
      $st->execute([$part_code,$location]);
      $currentQty=(int)$st->fetchColumn();
      $delta = (int)$desired_qty - $currentQty;

      if ($delta!==0){
        // เอกสาร ADJUST + line + ปรับยอดจริง
        log_adjust($pdo, $user_id, $location, $part_code, $delta);

        $pdo->prepare("UPDATE parts_new SET quantity=quantity+? WHERE part_code=? AND location=?")
            ->execute([$delta,$part_code,$location]);
      }

      $pdo->commit();
      header("Location: index.php?tab=new&msg=".urlencode("บันทึกเรียบร้อย"));
      exit;
    }catch(Throwable $e){
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[]=$e->getMessage();
    }
  }

  // คืนค่าเข้าแบบฟอร์มกรณี error
  $meta['part_code']=$part_code;
  $meta['part_name']=$part_name;
  $meta['part_number']=$part_number;
  $meta['device_models']=$device_models;
  $meta['category']=$category;
  if ($image_url) $meta['image_url']=$image_url;
  $curLoc=$location; $curQty=$desired_qty;
}

// ---------- template ----------
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?> <?= $meta['part_code'] ? '· แก้ไข: '.h($meta['part_code']) : '' ?></span>
    <a href="index.php?tab=new" class="view-site">← กลับรายการ</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger" style="margin-top:12px;">
      <?php foreach($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($meta['part_code']): ?>
  <section class="card part-summary" style="margin-top:16px;">
    <?php $img=img_src($meta['image_url']??''); ?>
    <div class="part-summary__media">
      <?php if ($img): ?>
        <img src="<?= h($img) ?>" class="part-summary__img" alt="">
      <?php else: ?>
        <div class="part-summary__placeholder">ไม่มีรูป</div>
      <?php endif; ?>
    </div>
    <div class="part-summary__body">
      <strong class="part-summary__title"><?= h($meta['part_name'] ?: $meta['part_code']) ?></strong>
      <div class="muted small">เลข: <?= h($meta['part_number']) ?> | หมวด: <?= h($meta['category']) ?></div>
      <div class="muted small">รุ่น: <?= h($meta['device_models']) ?></div>
      <?php
        $locRows=[];
        if ($meta['part_code']!==''){
          $st=$pdo->prepare("SELECT location,SUM(quantity) qty FROM parts_new WHERE part_code=? GROUP BY location ORDER BY location");
          $st->execute([$meta['part_code']]);
          $locRows=$st->fetchAll(PDO::FETCH_ASSOC);
        }
      ?>
      <?php if (!empty($locRows)): ?>
        <div class="chips" style="margin-top:6px">
          <?php foreach($locRows as $l): ?>
            <span class="badge"><?= h($l['location']) ?>: <?= (int)$l['qty'] ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <form id="mainForm" method="post" enctype="multipart/form-data" class="card restock-form" style="margin-top:16px;" novalidate>
    <input type="hidden" name="action" id="actionField" value="save_all">
    <input type="hidden" name="existing_code" value="<?= h($meta['part_code']) ?>">
    <input type="hidden" name="del_code" id="del_codeField" value="">

    <div class="form-grid">
      <!-- รูป -->
      <div class="form-item">
        <label class="form-label">รูป</label>
        <div class="image-upload-ui" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <div id="imgPreviewWrap" style="position:relative;width:100px;height:100px;border:1px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fafafa;cursor:pointer;" title="คลิกหรือลากไฟล์มาวาง">
            <?php if (!empty($meta['image_url'])): ?>
              <img id="imgPreview" src="<?= h(img_src($meta['image_url'])) ?>" alt="preview" style="width:100%;height:100%;object-fit:cover;">
              <button type="button" id="imageRemoveBtn" style="position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);">×</button>
            <?php else: ?>
              <span id="imgPreviewText" class="muted small">ลากรูปมาวาง</span>
              <button type="button" id="imageRemoveBtn" style="display:none;position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);">×</button>
            <?php endif; ?>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;min-width:220px;">
            <label for="image" class="btn-secondary" style="cursor:pointer;display:inline-flex;align-items:center;gap:8px;">เลือกรูปจากเครื่อง</label>
            <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp" style="display:none">
            <div class="muted small">รองรับ jpg, jpeg, png, webp ≤ 5MB</div>
          </div>
        </div>
      </div>

      <script>
      (function(){
        var input = document.getElementById('image');
        var wrap  = document.getElementById('imgPreviewWrap');
        var remove= document.getElementById('imageRemoveBtn');
        var img   = document.getElementById('imgPreview');

        function showPreview(file){
          if(!file) return;
          if(!/image\/(png|jpe?g|webp)/i.test(file.type)){ alert('ไฟล์ไม่รองรับ'); return; }
          var reader=new FileReader();
          reader.onload=function(e){
            if(!img){
              img=document.createElement('img');
              img.id='imgPreview'; img.alt='preview';
              img.style.cssText='width:100%;height:100%;object-fit:cover;';
            }
            wrap.innerHTML=''; wrap.appendChild(img); img.src=e.target.result;
            if(!remove){
              remove=document.createElement('button'); remove.id='imageRemoveBtn'; remove.type='button';
              remove.textContent='×';
              remove.style.cssText='position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;line-height:22px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.15);';
              remove.addEventListener('click', clearImage);
            }
            wrap.appendChild(remove); remove.style.display='';
          };
          reader.readAsDataURL(file);
        }
        function clearImage(e){
          if(e) e.stopPropagation();
          if(input) input.value='';
          wrap.innerHTML='<span id="imgPreviewText" class="muted small">ลากรูปมาวาง</span>';
          if(remove){ wrap.appendChild(remove); remove.style.display='none'; }
          img=null;
        }
        wrap.addEventListener('click', function(){ if(input) input.click(); });
        function setBorder(c){ wrap.style.borderColor=c; }
        wrap.addEventListener('dragover', function(e){ e.preventDefault(); setBorder('#3b82f6'); });
        wrap.addEventListener('dragleave', function(){ setBorder('#cbd5e1'); });
        wrap.addEventListener('drop', function(e){ e.preventDefault(); setBorder('#cbd5e1'); var f=e.dataTransfer.files && e.dataTransfer.files[0]; if(f){ input.files=e.dataTransfer.files; showPreview(f); }});
        if(input) input.addEventListener('change', function(){ var f=input.files && input.files[0]; if(f) showPreview(f); });
        if(remove) remove.addEventListener('click', clearImage);
      })();
      </script>

      <div class="form-item">
        <label class="form-label" for="part_name">ชื่ออะไหล่ *</label>
        <input id="part_name" name="part_name" class="input filter-input" required value="<?= h($meta['part_name']) ?>" placeholder="เช่น BATTERY A1819">
      </div>

      <div class="form-item">
        <label class="form-label" for="part_number">เลขอะไหล่ *</label>
        <input id="part_number" name="part_number" class="input filter-input" required value="<?= h($meta['part_number']) ?>" placeholder="เช่น A1819 หรือ 661-xxxx">
      </div>

      <div class="form-item">
        <label class="form-label" for="device_models">รุ่น</label>
        <input id="device_models" name="device_models" class="input filter-input" value="<?= h($meta['device_models']) ?>" placeholder="เช่น A1706, A1708">
      </div>

      <div class="form-item">
        <label class="form-label" for="category">หมวด</label>
        <select id="category" name="category" class="input filter-input">
          <?php foreach($categories as $c): ?>
            <option value="<?= h($c) ?>" <?= $meta['category']===$c ? 'selected' : '' ?>><?= h($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-item">
        <label class="form-label" for="location">ที่เก็บ (โลเคชัน) *</label>
        <input id="location" name="location" class="input filter-input" list="locs" required value="<?= h($curLoc) ?>" placeholder="เช่น main, shelf-A3">
        <datalist id="locs">
          <?php foreach($knownLocs as $L): ?><option value="<?= h($L) ?>"></option><?php endforeach; ?>
        </datalist>
      </div>

      <div class="form-item">
        <label class="form-label">คงเหลือ (ที่โลเคชันนี้) *</label>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <span class="muted small">ปัจจุบัน</span>
          <input id="curQty" class="input filter-input" value="<?= (int)$curQty ?>" readonly style="max-width:120px;">
          <span class="muted small">ปรับเป็น</span>
          <input type="number" name="desired_qty" id="desired_qty" class="input filter-input" min="0" required value="<?= (int)$curQty ?>" style="max-width:160px;">
        </div>
      </div>

      <div class="form-actions">
        <button class="btn-primary" type="submit">บันทึก</button>
        <a class="btn-secondary" href="index.php?tab=new">ยกเลิก</a>
        <?php if ($meta['part_code']): ?>
          <button type="button" class="btn-secondary" onclick="return confirmDelete('<?= h($meta['part_code']) ?>');">ลบอะไหล่นี้</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
  // เปลี่ยนโลเคชันแล้วอัปเดตยอด
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

  // ป้องกันชื่อ=เลข
  (function(){
    var nameEl=document.getElementById('part_name');
    var numEl=document.getElementById('part_number');
    if(!nameEl||!numEl) return;
    function validate(){
      var same=(nameEl.value.trim().toLowerCase()===numEl.value.trim().toLowerCase());
      if(same){ var msg='ชื่ออะไหล่กับเลขอะไหล่ห้ามเหมือนกัน'; nameEl.setCustomValidity(msg); numEl.setCustomValidity(msg); }
      else { nameEl.setCustomValidity(''); numEl.setCustomValidity(''); }
    }
    nameEl.addEventListener('input', validate);
    numEl.addEventListener('input', validate);
    validate();
  })();
</script>
