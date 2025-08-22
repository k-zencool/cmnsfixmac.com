<?php
/********************************************************************
 * admin/parts/donor_split.php
 * โหมดแยกอะไหล่ "ทีละชิ้น" แล้วส่งเข้ามือ 2 ทันที
 * - ฟอร์มบน: ใส่ข้อมูลชิ้นเดียว -> INSERT parts_used
 * - [ถ้าติ๊ก] บันทึกแล้วเบิกทันที -> เด้งไป consume.php
 * - อัปเดตสถานะ donor เป็น 'stripped' หลังมีอย่างน้อย 1 ชิ้น
 * - ตารางล่าง: แสดง "รายการที่แยกแล้ว" = (ของที่ยังอยู่ใน parts_used)
 *                UNION (ของที่เคยเบิกไปแล้วจาก parts_used_log)
 *   - แถวที่มาจาก log จะขึ้นป้าย "เบิกแล้ว" และกดเบิกไม่ได้
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();                       // ต้องล็อกอินก่อน
require_perms(['parts.donor.view']);   // อย่างน้อยต้องดู donor ได้

$pageTitle = "แยกอะไหล่จากเครื่องซาก (ทีละชิ้น)";

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function val($arr,$k,$d=''){ return isset($arr[$k]) ? trim((string)$arr[$k]) : $d; }

define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(PARTS_UPLOAD_DIR)) @mkdir(PARTS_UPLOAD_DIR, 0775, true);

function safeUploadName(string $orig): string {
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  if ($base==='') $base='used';
  return $base.'-'.date('Ymd_His').'-'.substr(bin2hex(random_bytes(4)),0,6).'.'.$ext;
}

function img_src_any($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  if (preg_match('~^https?://~i',$v) || $v[0]==='/') return $v;
  return '../../uploads/parts/'.$v;
}

/* --------------------- STATE --------------------- */
$donor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$donor_id) {
  header("Location: index.php?tab=donor&err=".urlencode("ไม่ได้ระบุ id"));
  exit;
}

/* --------------------- LOAD DONOR --------------------- */
$st = $pdo->prepare("SELECT * FROM parts_donors WHERE id=? LIMIT 1");
$st->execute([$donor_id]);
$donor = $st->fetch(PDO::FETCH_ASSOC);
if (!$donor) {
  header("Location: index.php?tab=donor&err=".urlencode("ไม่พบเครื่องซาก"));
  exit;
}

$errors = [];
$msg = val($_GET,'msg');

/* --------------------- POST: MOVE LOCATION --------------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && val($_POST,'action')==='move_location') {
  require_perms(['parts.donor.split']); // ต้องมีสิทธิ์แยก/จัดการ donor

  $used_id  = (int)($_POST['used_id'] ?? 0);
  $location = val($_POST,'location','used');

  try {
    $pdo->prepare("UPDATE parts_used SET location=?, updated_at=NOW() WHERE id=? AND donor_id=?")
        ->execute([$location,$used_id,$donor_id]);
    header("Location: donor_split.php?id=".$donor_id."&msg=".urlencode("อัปเดตที่เก็บเรียบร้อย"));
    exit;
  } catch (Throwable $e) {
    $errors[] = $e->getMessage();
  }
}

/* --------------------- POST: SAVE ONE (แยก 1 ชิ้น) --------------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && val($_POST,'action')==='save_one') {
  require_perms(['parts.donor.split']); // ต้องมีสิทธิ์แยก

  $part_name     = val($_POST,'part_name');
  $part_code     = strtoupper(val($_POST,'part_code'));
  $part_number   = val($_POST,'part_number');
  $device_models = val($_POST,'device_models', $donor['device_models']);
  $category      = val($_POST,'category','Other');
  $remarks       = val($_POST,'remarks','จาก donor #'.$donor_id);
  $location      = val($_POST,'location','used');

  // รูป: URL หรือ Upload (อัปโหลดชนะ URL)
  $image_url = val($_POST,'image_url');
  if (!empty($_FILES['image']['name'])) {
    $f = $_FILES['image'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      if (!in_array($ext,['jpg','jpeg','png','webp'],true)) {
        $errors[]="ไฟล์รูปต้องเป็น jpg/jpeg/png/webp";
      } elseif ($f['size'] > 6*1024*1024) {
        $errors[]="ไฟล์รูปใหญ่เกิน 6MB";
      } else {
        $new = safeUploadName($f['name']);
        if (!move_uploaded_file($f['tmp_name'], PARTS_UPLOAD_DIR.$new)) $errors[]="อัปโหลดรูปไม่สำเร็จ";
        else $image_url = $new;
      }
    } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
      $errors[] = "อัปโหลดรูปผิดพลาด (code {$f['error']})";
    }
  }

  if ($part_name==='') $errors[] = "กรุณากรอกชื่ออะไหล่";

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // insert ชิ้นมือ 2
      $pdo->prepare("
        INSERT INTO parts_used
          (part_code, part_name, part_number, device_models, category,
           image_url, location, remarks, donor_id, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?, NOW(), NOW())
      ")->execute([
        $part_code, $part_name, $part_number, $device_models, $category,
        $image_url, $location, $remarks, $donor_id
      ]);
      $used_id = (int)$pdo->lastInsertId();

      // ถ้ายังไม่ stripped ให้ตั้งสถานะ
      if ($donor['status'] !== 'stripped') {
        $pdo->prepare("UPDATE parts_donors SET status='stripped', updated_at=NOW() WHERE id=?")
            ->execute([$donor_id]);
        $donor['status'] = 'stripped';
      }

      $pdo->commit();

      if (!empty($_POST['consume_now'])) {
        header("Location: consume.php?type=used&used_id=".$used_id);
      } else {
        header("Location: donor_split.php?id=".$donor_id."&msg=".urlencode("เพิ่มชิ้นเข้ามือ 2 แล้ว"));
      }
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = $e->getMessage();
    }
  }
}

/* --------------------- LOAD: รายการที่แยกแล้ว (สด + เคยเบิก) --------------------- */
/* ต้องมีตาราง parts_used_log ด้วย (ดูสคีมาด้านล่างไฟล์นี้) */
$st2 = $pdo->prepare("
  SELECT 
    id,
    donor_id,
    part_code,
    part_name,
    device_models,
    category,
    image_url,
    location,
    0 AS is_log,
    NULL AS consumed_at
  FROM parts_used
  WHERE donor_id=?
  UNION ALL
  SELECT
    NULL AS id,
    donor_id,
    part_code,
    part_name,
    device_models,
    category,
    image_url,
    location,
    1 AS is_log,
    consumed_at
  FROM parts_used_log
  WHERE donor_id=?
  ORDER BY is_log ASC, COALESCE(consumed_at, '1970-01-01') DESC
");
$st2->execute([$donor_id, $donor_id]);
$used_parts = $st2->fetchAll(PDO::FETCH_ASSOC);

/* --------------------- TEMPLATE --------------------- */
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?> #<?= (int)$donor_id ?> (<?= h($donor['device_models']) ?>)</span>
    <a href="index.php?tab=donor" class="btn-secondary">← กลับรายการเครื่องซาก</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <!-- การ์ดหัว: ข้อมูลเครื่องซาก + รูป -->
  <div class="card" style="padding:16px;border-radius:12px;margin-bottom:16px;display:flex;gap:16px;align-items:center;">
    <?php $dsrc = img_src_any($donor['image_url'] ?? ''); ?>
    <div style="width:100px;">
      <?php if ($dsrc): ?>
        <img src="<?= h($dsrc) ?>" style="width:100px;height:100px;object-fit:cover;border-radius:10px;border:1px solid #eee;">
      <?php else: ?>
        <div style="width:100px;height:100px;border:1px dashed #ddd;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#aaa;">ไม่มีรูป</div>
      <?php endif; ?>
    </div>
    <div style="flex:1;">
      <div><strong>รุ่น:</strong> <?= h($donor['device_models']) ?></div>
      <div><strong>Serial:</strong> <?= h($donor['serial_no']) ?></div>
      <div><strong>ทุน:</strong> <?= $donor['purchase_cost']!==null ? number_format($donor['purchase_cost'],2) : '-' ?></div>
      <div><strong>สถานะ:</strong> <?= h($donor['status']) ?> <span class="muted">(จะตั้งเป็น stripped อัตโนมัติหลังแยกชิ้นแรก)</span></div>
    </div>
  </div>

  <!-- ฟอร์มแยก "หนึ่งชิ้น" -->
  <form method="post" enctype="multipart/form-data" class="card" style="padding:16px;border-radius:12px;margin-bottom:16px;">
    <input type="hidden" name="action" value="save_one">
    <h3 style="margin-top:0;">เพิ่มชิ้นเข้ามือ 2 (ครั้งละชิ้นเดียว)</h3>
    <div class="table-container">
      <table class="data-table"><tbody>
        <tr>
          <th style="width:220px;">ชื่ออะไหล่ *</th>
          <td><input name="part_name" class="filter-input" required placeholder="เช่น จอ/บอร์ด/ลำโพง"></td>
        </tr>
        <tr>
          <th>Part Code</th>
          <td style="display:flex;gap:8px;align-items:center;">
            <input name="part_code" id="part_code" class="filter-input" placeholder="เว้นว่างให้ระบบช่วยก็ได้">
            <button type="button" class="btn-secondary" onclick="autogenCode()">สร้างให้</button>
          </td>
        </tr>
        <tr>
          <th>เลขอะไหล่</th>
          <td><input name="part_number" class="filter-input" placeholder="Axxx / 661-xxxx"></td>
        </tr>
        <tr>
          <th>รุ่น</th>
          <td><input name="device_models" id="device_models" class="filter-input" value="<?= h($donor['device_models']) ?>"></td>
        </tr>
        <tr>
          <th>หมวด</th>
          <td><input name="category" id="category" class="filter-input" placeholder="screen/battery/..." list="catlist"></td>
        </tr>
        <tr>
          <th>รูปภาพ</th>
          <td>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
              <label class="btn-secondary" style="cursor:pointer;">อัปโหลด
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" style="display:none" onchange="previewFile(this)">
              </label>
              <input name="image_url" id="image_url" class="filter-input" style="min-width:260px;" placeholder="หรือวาง URL รูป" oninput="previewUrl(this)">
              <div id="imgPreview" style="width:64px;height:64px;border:1px dashed #ddd;border-radius:8px;overflow:hidden;"></div>
            </div>
            <div class="muted" style="font-size:12px;margin-top:6px;">ใส่ได้ทั้ง URL หรืออัปโหลดไฟล์ ถ้าใส่ทั้งคู่จะใช้ไฟล์อัปโหลด</div>
          </td>
        </tr>
        <tr>
          <th>ที่เก็บ (location)</th>
          <td><input name="location" class="filter-input" value="used" placeholder="เช่น used, shelf-A"></td>
        </tr>
        <tr>
          <th>หมายเหตุ</th>
          <td><input name="remarks" class="filter-input" value="จาก donor #<?= (int)$donor_id ?>"></td>
        </tr>
      </tbody></table>
    </div>
    <div style="display:flex;gap:12px;align-items:center;margin-top:12px;">
      <button class="btn-primary" type="submit">เพิ่มชิ้นเข้ามือ 2</button>
      <label style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" name="consume_now" value="1">
        <span>บันทึกแล้วเบิกทันที</span>
      </label>
    </div>
  </form>

  <!-- รายการที่แยกแล้ว (สด + เคยเบิก) -->
  <div class="card" style="padding:16px;border-radius:12px;">
    <h3 style="margin-top:0;">รายการที่แยกแล้ว</h3>
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th style="width:56px;">รูป</th>
          <th>Part Code</th>
          <th>ชื่ออะไหล่</th>
          <th>รุ่น</th>
          <th>หมวด</th>
          <th>ที่เก็บ</th>
          <th style="min-width:200px;">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($used_parts): foreach($used_parts as $i=>$p): ?>
          <?php
            $psrc  = img_src_any($p['image_url'] ?? '');
            $isLog = (int)($p['is_log'] ?? 0) === 1;
          ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td>
              <?php if ($psrc): ?>
                <img src="<?= h($psrc) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #eee;">
              <?php else: ?>
                <div style="width:48px;height:48px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;">-</div>
              <?php endif; ?>
            </td>
            <td><?= h($p['part_code']) ?></td>
            <td>
              <?= h($p['part_name']) ?>
              <?php if ($isLog && !empty($p['consumed_at'])): ?>
                <div class="muted" style="font-size:12px;">(เบิกแล้ว: <?= h($p['consumed_at']) ?>)</div>
              <?php endif; ?>
            </td>
            <td><?= h($p['device_models']) ?></td>
            <td><span class="badge"><?= h($p['category'] ?: 'Other') ?></span></td>

            <td>
              <?php if (!$isLog && can('parts.donor.split')): ?>
                <form method="post" style="display:flex;gap:6px;align-items:center;">
                  <input type="hidden" name="action" value="move_location">
                  <input type="hidden" name="used_id" value="<?= (int)($p['id'] ?? 0) ?>">
                  <input name="location" class="filter-input" value="<?= h($p['location']) ?>" style="max-width:140px;">
                  <button class="btn-secondary" type="submit">บันทึก</button>
                </form>
              <?php else: ?>
                <span class="muted"><?= h($p['location']) ?></span>
              <?php endif; ?>
            </td>

            <td class="no-wrap" style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php if (!$isLog && can('parts.used.consume')): ?>
                <a class="btn-primary" href="consume.php?type=used&used_id=<?= (int)($p['id'] ?? 0) ?>">เบิก</a>
              <?php endif; ?>
              <?php if (!$isLog && can('parts.used.update')): ?>
                <a class="btn-secondary" href="form_used.php?id=<?= (int)($p['id'] ?? 0) ?>">เปิดในมือ 2</a>
              <?php endif; ?>
              <?php if ($isLog): ?>
                <span class="badge" style="background:#eee;color:#555;">เบิกแล้ว</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="8" style="text-align:center;">ยังไม่มีรายการที่แยก</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<!-- helpers -->
<datalist id="catlist">
  <option value="screen">
  <option value="battery">
  <option value="keyboard">
  <option value="trackpad">
  <option value="speaker">
  <option value="camera">
  <option value="board">
  <option value="cable">
  <option value="fan">
  <option value="hinge">
  <option value="case">
  <option value="Other">
</datalist>

<script>
  function pad2(n){return (n<10?'0':'')+n;}
  function genCode(cat, model){
    const map={screen:'SCR',battery:'BATT',keyboard:'KB',trackpad:'TP',speaker:'SPK',camera:'CAM',board:'BRD',cable:'CBL',fan:'FAN',hinge:'HNG',case:'CASE',Other:'OTH'};
    const cc = map[(cat||'').toLowerCase()]||'OTH';
    const m = (model||'').replace(/[^A-Za-z0-9]+/g,'').slice(0,6).toUpperCase()||'MDL';
    const d=new Date(); const t=d.getHours()+''+pad2(d.getMinutes())+pad2(d.getSeconds());
    return `${cc}-${m}-${t}`;
  }
  function autogenCode(){
    const cat = document.getElementById('category').value || 'Other';
    const mdl = document.getElementById('device_models').value || '';
    const out = genCode(cat, mdl);
    const pc = document.getElementById('part_code');
    if(pc && !pc.value) pc.value = out;
  }
  function previewFile(input){
    const file = input.files && input.files[0];
    const holder = document.getElementById('imgPreview');
    holder.innerHTML='';
    if(file){
      const img = document.createElement('img');
      img.style.width='64px'; img.style.height='64px'; img.style.objectFit='cover'; img.style.borderRadius='8px';
      img.src = URL.createObjectURL(file);
      holder.appendChild(img);
    }
  }
  function previewUrl(inp){
    const url = inp.value.trim();
    const holder = document.getElementById('imgPreview');
    holder.innerHTML='';
    if(url){
      const img = document.createElement('img');
      img.style.width='64px'; img.style.height='64px'; img.style.objectFit='cover'; img.style.borderRadius='8px';
      img.src = url;
      img.onerror = ()=>{ holder.innerHTML='<div style="width:64px;height:64px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;">ผิด</div>'; };
      holder.appendChild(img);
    }
  }
</script>
