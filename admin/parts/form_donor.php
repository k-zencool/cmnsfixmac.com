<?php
/********************************************************************
 * admin/parts/form_donor.php (FULL)
 * - เพิ่ม/แก้ไข/ลบ "เครื่องซาก" + อัปโหลดรูป + ลบรูปเดิม
 ********************************************************************/
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin','manager']);

$pageTitle = "เครื่องซาก";
$user_id   = $_SESSION['user']['id'] ?? null;

// Upload config (ใช้โฟลเดอร์เดียวกับชิ้นส่วน)
define('PARTS_UPLOAD_DIR', __DIR__ . '/../../uploads/parts');
define('PARTS_UPLOAD_URL', '/uploads/parts'); // ใช้ absolute path กันงงชั้นไดเรกทอรี
if (!is_dir(PARTS_UPLOAD_DIR)) { @mkdir(PARTS_UPLOAD_DIR, 0775, true); }
$allowExt  = ['jpg','jpeg','png','webp'];
$allowMime = ['image/jpeg','image/png','image/webp'];

function genSafeName($orig){
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $base = preg_replace('/[^a-z0-9\-_]+/i','-', pathinfo($orig, PATHINFO_FILENAME));
  $base = trim($base,'-'); if ($base==='') $base='donor';
  return $base.'-'.date('Ymd-His').'-'.substr(sha1(random_bytes(8)),0,6).'.'.$ext;
}
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES,'UTF-8'); }
function val($a,$k,$d=''){ return isset($a[$k]) ? trim((string)$a[$k]) : $d; }
function back_to_list($qs=''){ $u='index.php?tab=donor'; if($qs) $u.='&'.$qs; header("Location: $u"); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// defaults
$item = [
  'device_name'=>'', 'device_models'=>'', 'category'=>'macbook',
  'serial_no'=>'', 'status'=>'in_stock', 'image_url'=>'', 'remarks'=>''
];

// load if edit
if ($id){
  $st = $pdo->prepare("SELECT * FROM parts_donors WHERE id=?");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) back_to_list('err=ไม่พบข้อมูล');
  $item = array_merge($item,$row);
}

/* Delete (GET or POST) */
$wantDeleteByGet  = ($_SERVER['REQUEST_METHOD']==='GET'  && val($_GET,'op')==='delete');
$wantDeleteByPost = ($_SERVER['REQUEST_METHOD']==='POST' && val($_POST,'action')==='delete_item');
if ($wantDeleteByGet || $wantDeleteByPost) {
  $del_id = (int)($wantDeleteByGet ? ($_GET['id'] ?? 0) : ($_POST['id'] ?? 0));
  if ($del_id<=0) back_to_list('err=คำขอไม่ถูกต้อง');
  try {
    // ลบไฟล์ภาพเก่าถ้าเป็นไฟล์ภายใน
    $r = $pdo->prepare("SELECT image_url FROM parts_donors WHERE id=?");
    $r->execute([$del_id]);
    $old = $r->fetch(PDO::FETCH_ASSOC);
    if ($old && $old['image_url'] && strpos($old['image_url'],'://')===false) {
      @unlink(PARTS_UPLOAD_DIR . '/' . $old['image_url']);
    }
    $pdo->prepare("DELETE FROM parts_donors WHERE id=?")->execute([$del_id]);
    back_to_list('msg=ลบเครื่องซากเรียบร้อย');
  } catch(Throwable $e){
    back_to_list('err='.urlencode($e->getMessage()));
  }
}

/* Save (insert/update) */
if ($_SERVER['REQUEST_METHOD']==='POST' && val($_POST,'action')==='save') {
  $form_id       = (int)($_POST['id'] ?? 0);
  $device_name   = val($_POST,'device_name');
  $device_models = val($_POST,'device_models');
  $category_sel  = val($_POST,'category_select');
  $category_cus  = val($_POST,'category_custom');
  $category      = $category_sel==='other' ? $category_cus : $category_sel;
  $serial_no     = val($_POST,'serial_no');
  $status        = val($_POST,'status','in_stock');
  $remarks       = val($_POST,'remarks');
  $image_url     = val($_POST,'image_url');  // อนุญาต URL เต็ม
  $remove_image  = isset($_POST['remove_image']);

  $errors = [];
  if ($device_name==='') $errors[] = 'กรุณากรอกชื่อเครื่อง';
  if ($category==='')    $errors[] = 'กรุณาเลือกหมวด';

  $old = null;
  if ($form_id){
    $q = $pdo->prepare("SELECT image_url FROM parts_donors WHERE id=?");
    $q->execute([$form_id]);
    $old = $q->fetch(PDO::FETCH_ASSOC);
  }

  // อัปโหลดไฟล์ถ้ามี
  if (!empty($_FILES['image_file']) && is_uploaded_file($_FILES['image_file']['tmp_name'])) {
    $f=$_FILES['image_file']; $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    $mime = mime_content_type($f['tmp_name']) ?: '';
    if (!in_array($ext,$allowExt,true) || !in_array($mime,$allowMime,true)) {
      $errors[] = 'ไฟล์รูปต้องเป็น JPG/PNG/WEBP เท่านั้น';
    } else {
      $newName = genSafeName($f['name']);
      if (!move_uploaded_file($f['tmp_name'], PARTS_UPLOAD_DIR.'/'.$newName)) {
        $errors[] = 'อัปโหลดรูปไม่สำเร็จ';
      } else {
        $image_url = $newName;
      }
    }
  }

  // ลบรูปเดิมถ้าติ๊ก และรูปเดิมเป็นไฟล์ในระบบ
  if ($remove_image && $old && $old['image_url'] && strpos($old['image_url'],'://')===false) {
    @unlink(PARTS_UPLOAD_DIR . '/' . $old['image_url']);
    if (empty($_FILES['image_file']['tmp_name'])) $image_url = '';
  }

  if ($errors){
    $_SESSION['form_errors']=$errors;
    $_SESSION['form_keep']=$_POST;
    header("Location: form_donor.php".($form_id?"?id=".$form_id:""));
    exit;
  }

  try{
    if ($form_id){
      $pdo->prepare("
        UPDATE parts_donors
        SET device_name=?, device_models=?, category=?, serial_no=?,
            status=?, image_url=?, remarks=?, updated_at=NOW()
        WHERE id=?
      ")->execute([$device_name,$device_models,$category,$serial_no,$status,$image_url,$remarks,$form_id]);
      back_to_list('msg=บันทึกการแก้ไขแล้ว');
    } else {
      $pdo->prepare("
        INSERT INTO parts_donors
          (device_name, device_models, category, serial_no, status, image_url, remarks, created_at)
        VALUES (?,?,?,?,?,?,?, NOW())
      ")->execute([$device_name,$device_models,$category,$serial_no,$status,$image_url,$remarks]);
      back_to_list('msg=เพิ่มเครื่องซากแล้ว');
    }
  } catch(Throwable $e){
    $_SESSION['form_errors'] = [$e->getMessage()];
    $_SESSION['form_keep']   = $_POST;
    header("Location: form_donor.php".($form_id?"?id=".$form_id:""));
    exit;
  }
}

// sticky form
if (!empty($_SESSION['form_keep'])){ $item = array_merge($item,$_SESSION['form_keep']); unset($_SESSION['form_keep']); }
$errors = $_SESSION['form_errors'] ?? []; unset($_SESSION['form_errors']);

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?> <?= $id ? '(แก้ไข #' . (int)$id . ')' : '(เพิ่มเครื่องใหม่)' ?></span>
    <a href="index.php?tab=donor" class="btn-secondary">← กลับรายการเครื่องซาก</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach($errors as $e):?><div><?= h($e) ?></div><?php endforeach;?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="card" style="padding:16px;border-radius:12px;">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="table-container">
      <table class="data-table">
        <tbody>
          <tr>
            <th style="width:220px;">ชื่อเครื่อง *</th>
            <td><input class="filter-input" name="device_name" required value="<?= h($item['device_name']) ?>" placeholder="เช่น MacBook Pro 13 2017"></td>
          </tr>
          <tr>
            <th>ใช้กับรุ่น / Model</th>
            <td><input class="filter-input" name="device_models" value="<?= h($item['device_models']) ?>" placeholder="A1706, A1708 ..."></td>
          </tr>
          <tr>
            <th>หมวด *</th>
            <td>
              <?php
                $catOptions=['macbook'=>'MacBook','iphone'=>'iPhone','ipad'=>'iPad','imac'=>'iMac'];
                $catCurrent=$item['category'] ?: 'macbook';
                $catExists=array_key_exists($catCurrent,$catOptions);
              ?>
              <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <select name="category_select" class="filter-input" style="min-width:180px;">
                  <?php foreach($catOptions as $v=>$lab): ?>
                    <option value="<?= h($v) ?>" <?= $catCurrent===$v?'selected':'' ?>><?= h($lab) ?></option>
                  <?php endforeach; ?>
                  <option value="other" <?= !$catExists?'selected':'' ?>>อื่นๆ (พิมพ์เอง)</option>
                </select>
                <input class="filter-input" name="category_custom" style="flex:1;min-width:220px;"
                       value="<?= !$catExists? h($catCurrent):'' ?>" placeholder="เช่น iPhone parts box">
              </div>
            </td>
          </tr>
          <tr>
            <th>Serial</th>
            <td><input class="filter-input" name="serial_no" value="<?= h($item['serial_no']) ?>"></td>
          </tr>
          <tr>
            <th>สถานะ</th>
            <td>
              <?php $st=$item['status']?:'in_stock'; ?>
              <select name="status" class="filter-input">
                <option value="in_stock"  <?= $st==='in_stock'?'selected':'' ?>>คงอยู่</option>
                <option value="reserved"  <?= $st==='reserved'?'selected':'' ?>>จอง</option>
                <option value="stripped"  <?= $st==='stripped'?'selected':'' ?>>ถอดอะไหล่แล้ว</option>
                <option value="disposed"  <?= $st==='disposed'?'selected':'' ?>>จำหน่ายทิ้ง/ขายซาก</option>
                <option value="sold"      <?= $st==='sold'?'selected':'' ?>>ขายออก</option>
                <option value="scrap"     <?= $st==='scrap'?'selected':'' ?>>ซาก</option>
              </select>
            </td>
          </tr>

          <tr>
            <th>รูปภาพ</th>
            <td>
              <?php
                $hasImg = trim((string)$item['image_url'])!=='';
                $imgSrc = $hasImg ? (strpos($item['image_url'],'://')!==false ? $item['image_url'] : PARTS_UPLOAD_URL.'/'.h($item['image_url'])) : '';
              ?>
              <?php if($hasImg): ?>
                <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px;">
                  <img src="<?= $imgSrc ?>" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #eee;" alt="">
                  <label class="checkline"><input type="checkbox" name="remove_image"> ลบรูปนี้</label>
                </div>
              <?php endif; ?>
              <input type="file" name="image_file" accept="image/*" class="filter-input" style="max-width:320px;">
              <div class="muted" style="margin-top:4px;">หรือวาง URL เอง:</div>
              <input class="filter-input" name="image_url" value="<?= h($item['image_url']) ?>" placeholder="URL รูป หรือเว้นว่างถ้าอัปโหลดไฟล์">
            </td>
          </tr>

          <tr>
            <th>หมายเหตุ</th>
            <td><textarea name="remarks" rows="3" class="filter-input" placeholder="สภาพ, อาการ, ราคาซื้อซาก ฯลฯ"><?= h($item['remarks']) ?></textarea></td>
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
    <form method="post" onsubmit="return confirm('ลบเครื่องนี้ถาวร ใช่ไหม?');" class="card" style="padding:16px;border-radius:12px;margin-top:16px;">
      <input type="hidden" name="action" value="delete_item">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <h3 style="margin:0;color:#b00;">ลบเครื่องนี้</h3>
      <button class="btn-danger" type="submit">ลบเครื่องซาก</button>
    </form>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
