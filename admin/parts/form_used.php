<?php
/********************************************************************
 * admin/parts/form_used.php
 * ฟอร์ม "อะไหล่มือ 2" (Organized Layout)
 * Update: จัดระเบียบปุ่มเลือกชื่ออะไหล่ (Quick Tags) ให้เรียงสวยงามใต้ช่องกรอก
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin','manager']);

$pageTitle = "ฟอร์มอะไหล่มือ 2";
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function val($arr,$k,$d=''){ return isset($arr[$k]) ? trim((string)$arr[$k]) : $d; }

$user_id = $_SESSION['admin_id'] ?? ($_SESSION['user']['id'] ?? null);

// -------- CONFIG --------
define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts/');
if (!is_dir(PARTS_UPLOAD_DIR)) @mkdir(PARTS_UPLOAD_DIR, 0775, true);

// 1. หมวดหมู่
$categories = [
    'MacBook', 'iPhone', 'iPad', 'iMac', 
    'Apple Watch', 'AirPods', 'Apple Pencil', 
    'Mac mini', 'Mac Studio', 'Accessories', 'Other'
];

// 2. ชื่ออะไหล่ยอดฮิต (จัดกลุ่มให้กดง่ายๆ)
$commonParts = [
    'Screen (จอ)', 'Battery (แบต)', 'Logic Board', 'Top Case', 
    'Keyboard', 'Trackpad', 'Fan (พัดลม)', 'Speaker', 'Camera', 
    'Flex Cable', 'Housing', 'Charging Case', 'Left Side (L)', 'Right Side (R)'
];

// -------- SKU Logic --------
function detectPrefix($name, $model, $cat) {
    $text = strtolower($name . ' ' . $model . ' ' . $cat);
    if (strpos($text, 'iphone') !== false)     return 'IP';
    if (strpos($text, 'ipad') !== false)       return 'PD';
    if (strpos($text, 'imac') !== false)       return 'IM';
    if (strpos($text, 'watch') !== false)      return 'WA';
    if (strpos($text, 'airpods') !== false)    return 'AP';
    if (strpos($text, 'pencil') !== false)     return 'PE';
    if (strpos($text, 'mac mini') !== false)   return 'MM';
    if (strpos($text, 'adapter') !== false)    return 'AC';
    return 'MB'; // Default
}

function generateUsedSKU(PDO $pdo, $partName, $deviceModel, $cat) {
    $prefixType = detectPrefix($partName, $deviceModel, $cat);
    $ym = date('Ym');
    $sql = "SELECT CAST(SUBSTRING_INDEX(used_sku, '-A', -1) AS UNSIGNED) as max_seq 
            FROM parts_used WHERE used_sku LIKE '%-A%' ORDER BY max_seq DESC LIMIT 1";
    $stmt = $pdo->query($sql);
    $maxSeq = (int)$stmt->fetchColumn(); 
    return sprintf("U-%s-%s-A%04d", $prefixType, $ym, $maxSeq + 1);
}

$nextSeqStr = "0001";
$currentYM = date('Ym');
if (!isset($_GET['id'])) {
    $sqlMax = "SELECT CAST(SUBSTRING_INDEX(used_sku, '-A', -1) AS UNSIGNED) as max_seq 
               FROM parts_used WHERE used_sku LIKE '%-A%' ORDER BY max_seq DESC LIMIT 1";
    $stMax = $pdo->query($sqlMax);
    $nextSeqStr = str_pad(((int)$stMax->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
}

// -------- Helpers & Actions --------
function safeUploadName(string $orig): string {
  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION)); $base='used';
  return $base.'_'.date('Ymd_His').'_'.substr(bin2hex(random_bytes(6)),0,12).'.'.$ext;
}
function img_src($v){
  $v=trim((string)$v); if($v==='')return ''; if(preg_match('~^https?://~i',$v)||$v[0]==='/')return $v; return '../../uploads/parts/'.$v;
}
function used_doc(PDO $pdo, string $act, int $id, array $item, $uid): void {
  try {
    $ref = !empty($item['used_sku']) ? $item['used_sku'] : "USED:$id";
    if ($act==='CREATE') $rem="เพิ่มมือ 2 [$ref]: ".($item['part_name']??'');
    elseif ($act==='UPDATE') $rem="แก้ไขมือ 2 [$ref]";
    elseif ($act==='DELETE') $rem="ลบมือ 2 [$ref]";
    else $rem="$act #$id";
    $pdo->prepare("INSERT INTO parts_docs (doc_type,ref_no,remarks,user_id,created_at) VALUES ('USED',?,?,?,NOW())")->execute(["USED:$id",mb_strimwidth($rem,0,250),$uid]);
    $qty = ($act==='DELETE') ? -1 : 1;
    $pdo->prepare("INSERT INTO parts_doc_lines (doc_id,part_code,qty,location_to) VALUES (?,?,?,?)")->execute([$pdo->lastInsertId(), $item['part_code']??null, $qty, $item['location']??null]);
  } catch (Throwable $e) {}
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$donor_id = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : 0;
$currentPage = max(1, (int)($_REQUEST['page'] ?? 1));

$item = ['used_sku'=>'', 'donor_id'=>$donor_id?:null, 'part_code'=>'', 'part_name'=>'', 'part_number'=>'', 'device_models'=>'', 'category'=>'MacBook', 'image_url'=>null, 'location'=>'main', 'remarks'=>''];
$beforeRow = null;

if ($id) {
  $st=$pdo->prepare("SELECT * FROM parts_used WHERE id=?"); $st->execute([$id]);
  $row=$st->fetch(PDO::FETCH_ASSOC);
  if(!$row){ header("Location: index.php?tab=used"); exit; }
  $beforeRow=$row; $item=array_merge($item,$row);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = val($_POST,'action');
  if ($action==='delete_used' && $id) {
    used_doc($pdo, 'DELETE', $id, $item, $user_id);
    $pdo->prepare("DELETE FROM parts_used WHERE id=?")->execute([$id]);
    header("Location: index.php?tab=used&page={$currentPage}&msg=".urlencode("ลบเรียบร้อย")); exit;
  }
  if ($action==='save_used') {
    $item['donor_id'] = ($_POST['donor_id']??'')===''?null:(int)$_POST['donor_id'];
    foreach(['part_code','part_name','part_number','device_models','category','location','remarks'] as $k) $item[$k] = val($_POST, $k);
    if ($item['part_name']==='' && $item['part_code']==='') $errors[] = "กรุณากรอกชื่ออะไหล่";

    $newImage = null;
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error']===0) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $new = safeUploadName($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], PARTS_UPLOAD_DIR.$new)) $newImage = $new;
        }
    }
    
    if (!$errors) {
        if (empty($item['used_sku'])) $item['used_sku'] = generateUsedSKU($pdo, $item['part_name'], $item['device_models'], $item['category']);
        elseif (!$id) $item['used_sku'] = generateUsedSKU($pdo, $item['part_name'], $item['device_models'], $item['category']);

        if ($id) {
            $sql = "UPDATE parts_used SET donor_id=?, part_code=?, part_name=?, part_number=?, device_models=?, category=?, location=?, remarks=?, updated_at=NOW()";
            $p = [$item['donor_id'], $item['part_code'], $item['part_name'], $item['part_number'], $item['device_models'], $item['category'], $item['location'], $item['remarks']];
            if ($newImage) { $sql.=", image_url=?"; $p[]=$newImage; }
            elseif (($_POST['remove_image']??0)==1) { $sql.=", image_url=NULL"; }
            $sql.=" WHERE id=?"; $p[]=$id;
            $pdo->prepare($sql)->execute($p);
            used_doc($pdo, 'UPDATE', $id, $item, $user_id);
            header("Location: index.php?tab=used&page={$currentPage}&msg=".urlencode("แก้ไขแล้ว")); exit;
        } else {
            $r=3; $done=false;
            while($r>0){
                try{
                    $sql="INSERT INTO parts_used (used_sku,donor_id,part_code,part_name,part_number,device_models,category,image_url,location,remarks,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())";
                    $pdo->prepare($sql)->execute([$item['used_sku'], $item['donor_id'], $item['part_code'], $item['part_name'], $item['part_number'], $item['device_models'], $item['category'], $newImage, $item['location'], $item['remarks']]);
                    used_doc($pdo, 'CREATE', $pdo->lastInsertId(), $item, $user_id);
                    $done=true; break;
                }catch(PDOException $e){
                    if($e->errorInfo[1]==1062){ $item['used_sku']=generateUsedSKU($pdo,$item['part_name'],$item['device_models'],$item['category']); $r--; } else throw $e;
                }
            }
            if($done) { header("Location: index.php?tab=used&page={$currentPage}&msg=".urlencode("เพิ่มสำเร็จ SKU: {$item['used_sku']}")); exit; }
            else $errors[]="SKU ชนกัน กรุณาลองใหม่";
        }
    }
  }
}

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<style>
    .sku-input { background-color: #1f2937; color: #fbbf24; font-family: monospace; font-size: 1.1em; font-weight: bold; border-color: #374151; }
    
    /* Quick Tags Wrapper: จัดให้ปุ่มอยู่เป็นกลุ่มก้อน ไม่กระจัดกระจาย */
    .quick-tags-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px; /* เว้นระยะจากช่องกรอก */
        padding: 4px 0;
    }
    
    /* ตัวปุ่ม Tag */
    .quick-tag {
        font-size: 0.85rem;
        padding: 4px 10px;
        background-color: #f3f4f6;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
    }
    .quick-tag:hover {
        background-color: #dbeafe;
        color: #1d4ed8;
        border-color: #93c5fd;
        transform: translateY(-1px);
    }
    .quick-tag:active {
        transform: translateY(0);
        background-color: #2563eb;
        color: #fff;
    }
</style>

<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
    <a href="index.php?tab=used&page=<?= $currentPage ?>" class="view-site">← กลับ</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger" style="margin-top:12px;"><?= implode('<br>', $errors) ?></div>
  <?php endif; ?>

  <form id="usedForm" method="post" enctype="multipart/form-data" class="card restock-form" style="margin-top:16px;" novalidate>
    <input type="hidden" name="action" id="usedAction" value="save_used">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="remove_image" id="remove_image" value="0">
    <input type="hidden" name="page" value="<?= $currentPage ?>">

    <div class="form-grid">
      
      <div class="form-item">
        <label class="form-label">SKU / รหัสทรัพย์สิน</label>
        <input type="text" id="used_sku_display" name="used_sku" class="input sku-input" 
               value="<?= $id ? h($item['used_sku']) : "U-MB-{$currentYM}-A{$nextSeqStr}" ?>" readonly>
        <small class="muted" style="font-size:0.8em; margin-top:4px;">ระบบสร้างให้อัตโนมัติ (รหัสเปลี่ยนตามหมวดหมู่)</small>
      </div>

      <div class="form-item">
        <label class="form-label">รูปภาพ</label>
        <div class="image-upload-ui" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <div id="uImgWrap" style="position:relative;width:100px;height:100px;border:1px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fafafa;cursor:pointer;" title="คลิกหรือลากไฟล์มาวาง">
            <?php if (!empty($item['image_url'])): ?>
              <img id="uImg" src="<?= h(img_src($item['image_url'])) ?>" style="width:100%;height:100%;object-fit:cover;">
              <button type="button" id="uRemoveBtn" onclick="clearImage()" style="position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;font-weight:700;">×</button>
            <?php else: ?>
              <span id="uImgText" class="muted small">ลากรูปมาวาง</span>
              <button type="button" id="uRemoveBtn" onclick="clearImage()" style="display:none;position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;cursor:pointer;">×</button>
            <?php endif; ?>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;min-width:220px;">
            <label for="image" class="btn-secondary" style="cursor:pointer;display:inline-flex;align-items:center;gap:8px;">เลือกรูปจากเครื่อง</label>
            <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp" style="display:none">
          </div>
        </div>
      </div>

      <div class="form-item">
        <label class="form-label">ชื่ออะไหล่ *</label>
        <div style="display:flex; flex-direction:column;">
            <input type="text" id="part_name" name="part_name" class="input" value="<?= h($item['part_name']) ?>" required placeholder="พิมพ์เอง หรือกดเลือก..." oninput="updateSkuPreview()">
            
            <div class="quick-tags-wrapper">
                <?php foreach($commonParts as $cp): ?>
                    <span class="quick-tag" onclick="fillPart('<?= h($cp) ?>')"><?= h($cp) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
      </div>

      <div class="form-item">
        <label class="form-label">รุ่นอุปกรณ์</label>
        <input type="text" id="device_models" name="device_models" class="input" value="<?= h($item['device_models']) ?>" placeholder="เช่น iPhone 13, A1708" oninput="updateSkuPreview()">
      </div>

      <div class="form-item">
        <label class="form-label">หมวดหมู่</label>
        <select name="category" id="category" class="input" onchange="updateSkuPreview()">
            <?php foreach($categories as $cat): ?>
                <option value="<?= h($cat) ?>" <?= $item['category']===$cat ? 'selected' : '' ?>><?= h($cat) ?></option>
            <?php endforeach; ?>
        </select>
      </div>

      <div class="form-item"><label class="form-label">เลขอะไหล่ (Part No.)</label><input name="part_number" class="input" value="<?= h($item['part_number']) ?>" placeholder="เช่น 661-xxxx"></div>
      <div class="form-item"><label class="form-label">ที่เก็บ</label><input name="location" class="input" value="<?= h($item['location']) ?>" placeholder="เช่น ตู้กระจก"></div>
      <div class="form-item"><label class="form-label">Donor ID (ถ้ามี)</label><input type="number" name="donor_id" class="input" value="<?= h($item['donor_id']) ?>" placeholder="ID เครื่อง"></div>
      
      <div class="form-item" style="grid-column:1 / -1">
        <label class="form-label">หมายเหตุ</label>
        <textarea name="remarks" class="input" rows="3"><?= h($item['remarks']) ?></textarea>
      </div>

      <div class="form-actions" style="grid-column:1 / -1">
        <button class="btn-primary" type="submit">บันทึก</button>
        <a class="btn-secondary" href="index.php?tab=used&page=<?= $currentPage ?>">ยกเลิก</a>
        <?php if ($id): ?>
          <button type="button" class="btn-secondary" onclick="deleteUsed()">ลบรายการ</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
// Logic: จิ้มแล้วเติม
function fillPart(val) {
    const el = document.getElementById('part_name');
    el.value = val;
    updateSkuPreview();
}

// Logic: Preview SKU
const isEditMode = <?= $id ? 'true' : 'false' ?>;
const nextSeq = "<?= $nextSeqStr ?>";
const currentYM = "<?= $currentYM ?>";

function updateSkuPreview() {
    if (isEditMode) return; 

    const name = document.getElementById('part_name').value.toLowerCase();
    const model = document.getElementById('device_models').value.toLowerCase();
    const cat = document.getElementById('category').value;
    const text = name + ' ' + model + ' ' + cat.toLowerCase();

    let prefix = 'MB'; 
    if (text.includes('iphone')) prefix = 'IP';
    else if (text.includes('ipad')) prefix = 'PD';
    else if (text.includes('imac')) prefix = 'IM';
    else if (text.includes('watch')) prefix = 'WA';
    else if (text.includes('airpods')) prefix = 'AP';
    else if (text.includes('pencil')) prefix = 'PE';
    else if (text.includes('mac mini')) prefix = 'MM';
    else if (text.includes('adapter') || text.includes('accessories')) prefix = 'AC';
    
    if (prefix === 'MB') { 
        if(cat === 'iPhone') prefix = 'IP';
        if(cat === 'iPad') prefix = 'PD';
        if(cat === 'AirPods') prefix = 'AP';
        if(cat === 'Apple Pencil') prefix = 'PE';
        if(cat === 'Apple Watch') prefix = 'WA';
        if(cat === 'Mac mini') prefix = 'MM';
    }

    document.getElementById('used_sku_display').value = `U-${prefix}-${currentYM}-A${nextSeq}`;
}

// Upload Logic
var input = document.getElementById('image');
var wrap = document.getElementById('uImgWrap');
var rmField = document.getElementById('remove_image');

function preview(f) {
    if(!f) return;
    var r = new FileReader();
    r.onload = function(e) {
        wrap.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">` +
                         `<button type="button" onclick="clearImage()" style="position:absolute;top:6px;right:6px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:0;font-weight:700;">×</button>`;
        rmField.value = 0;
    };
    r.readAsDataURL(f);
}
input.addEventListener('change', function(){ if(input.files[0]) preview(input.files[0]); });
wrap.addEventListener('click', function(e){ if(e.target.tagName!=='BUTTON') input.click(); });

function clearImage() {
    event.stopPropagation();
    input.value = '';
    wrap.innerHTML = '<span id="uImgText" class="muted small">ลากรูปมาวาง</span>';
    rmField.value = 1;
}

function deleteUsed(){
    if(confirm('ยืนยันลบ?')) {
        const f = document.getElementById('usedForm');
        const i = document.createElement('input'); i.type='hidden'; i.name='action'; i.value='delete_used';
        f.appendChild(i); f.submit();
    }
}
</script>