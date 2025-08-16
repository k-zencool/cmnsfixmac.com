<?php
/********************************************************************
 * admin/parts/consume.php
 * เบิก/ตัดจ่ายอะไหล่:
 *   - มือ 1 (parts_new) : หักจำนวนจาก location ที่เลือก + เอกสาร CONSUME
 *   - มือ 2 (parts_used): สร้าง CONSUME (qty=1, from 'used') แล้วลบแถวออกเลย
 *
 * ใช้งาน (รองรับพารามฯ แบบเก่า/ใหม่):
 *   มือ 1: ?type=new&part_code=...   หรือ  ?mode=new&part_code=...
 *   มือ 2: ?type=used&used_id=...    หรือ  ?mode=used&id=...
 ********************************************************************/

// ========================== [SETUP & GUARD] ==========================
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin','manager']);

$pageTitle = "ตัดจ่าย/เบิก";
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function img_src($v){
  $v = trim((string)$v);
  if ($v==='') return '';
  if (preg_match('~^https?://~i',$v) || $v[0]==='/') return $v;
  return '../../uploads/parts/'.$v;
}
function back_to($tab, $qs=''){
  $u = "index.php?tab=".urlencode($tab);
  if ($qs) $u .= "&$qs";
  header("Location: $u"); exit;
}

// ========================== [STATE] ==================================
// รับทั้ง type และ mode (เพื่อเข้ากับลิงก์เดิมได้)
$rawType = $_GET['type'] ?? $_GET['mode'] ?? 'new';
$type    = ($rawType === 'used') ? 'used' : 'new';

// id มือ 2 รองรับทั้ง used_id และ id
$used_id = isset($_GET['used_id']) ? (int)$_GET['used_id'] : (int)($_GET['id'] ?? 0);
// มือ 1
$code    = trim($_GET['part_code'] ?? '');

$errors  = [];
$msg     = '';
$user_id = $_SESSION['user']['id'] ?? ($_SESSION['admin_id'] ?? null);

// ========================== [LOAD: มือ 1 SUMMARY + LOCATIONS] ========
$part_new = null;  // meta + qty รวม
$locs     = [];    // คงเหลือต่อ location

if ($type === 'new' && $code !== '') {
  $st = $pdo->prepare("
    SELECT
      part_code,
      MAX(part_name)     AS part_name,
      MAX(part_number)   AS part_number,
      MAX(device_models) AS device_models,
      MAX(category)      AS category,
      MAX(image_url)     AS image_url,
      SUM(quantity)      AS qty
    FROM parts_new
    WHERE part_code=?
    GROUP BY part_code
    LIMIT 1
  ");
  $st->execute([$code]);
  $part_new = $st->fetch(PDO::FETCH_ASSOC);

  $st2 = $pdo->prepare("
    SELECT location, SUM(quantity) AS qty
    FROM parts_new
    WHERE part_code=?
    GROUP BY location
    ORDER BY location
  ");
  $st2->execute([$code]);
  $locs = $st2->fetchAll(PDO::FETCH_ASSOC);
}

// ========================== [LOAD: มือ 2 ITEM] =======================
$used = null;
if ($type === 'used' && $used_id > 0) {
  // *** ตัด is_active ออก (ไม่มีคอลัมน์นี้แล้ว) ***
  $st = $pdo->prepare("
    SELECT id, part_code, part_name, part_number, device_models, category,
           image_url, location, remarks, created_at, updated_at
    FROM parts_used
    WHERE id=? LIMIT 1
  ");
  $st->execute([$used_id]);
  $used = $st->fetch(PDO::FETCH_ASSOC);
}

// ========================== [POST: ACTION] ===========================
if ($_SERVER['REQUEST_METHOD']==='POST') {
  // โหมดมาจาก hidden input
  $mode = ($_POST['mode'] ?? 'new') === 'used' ? 'used' : 'new';

  // ---------- มือ 1: CONSUME จาก parts_new ----------
  if ($mode === 'new') {
    $code     = trim($_POST['part_code'] ?? '');
    $location = trim($_POST['location'] ?? 'main');
    $qty      = max(0, (int)($_POST['qty'] ?? 0));
    $ref_no   = trim($_POST['ref_no'] ?? '');
    $remarks  = trim($_POST['remarks'] ?? '');

    if ($code==='')     $errors[] = "กรอกรหัสอะไหล่";
    if ($location==='') $errors[] = "กรอก/เลือกที่เก็บ";
    if ($qty<=0)        $errors[] = "จำนวนต้องมากกว่า 0";
    if (!$user_id)      $errors[] = "ไม่พบผู้ใช้งานในระบบ";

    if (!$errors) {
      try {
        $pdo->beginTransaction();

        // ล็อกยอดที่ location
        $st = $pdo->prepare("SELECT quantity FROM parts_new WHERE part_code=? AND location=? FOR UPDATE");
        $st->execute([$code,$location]);
        $have = (int)($st->fetchColumn() ?? 0);
        if ($have < $qty) throw new Exception("คงเหลือไม่พอในที่เก็บนี้ (เหลือ {$have})");

        // Header
        $pdo->prepare("
          INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
          VALUES ('CONSUME', ?, ?, ?, NOW())
        ")->execute([$ref_no ?: null, $remarks !== '' ? $remarks : "consume from {$location}", $user_id]);
        $doc_id = (int)$pdo->lastInsertId();

        // Line
        $pdo->prepare("
          INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
          VALUES (?, ?, ?, ?, NULL, NULL)
        ")->execute([$doc_id, $code, $qty, $location]);

        // หักสต็อก
        $pdo->prepare("
          UPDATE parts_new SET quantity = quantity - ?
          WHERE part_code=? AND location=?
        ")->execute([$qty, $code, $location]);

        $pdo->commit();
        back_to('new','msg=ตัดจ่ายเรียบร้อย');
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = $e->getMessage();
      }
    }
  }

  // ---------- มือ 2: CONSUME แล้วลบออกเลย ----------
  if ($mode === 'used') {
    $used_id = (int)($_POST['used_id'] ?? 0);
    $ref_no  = trim($_POST['ref_no'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($used_id <= 0) $errors[] = "ไม่พบรายการมือ 2 ที่จะตัดจ่าย";
    if (!$user_id)     $errors[] = "ไม่พบผู้ใช้งานในระบบ";

    if (!$errors) {
      try {
        $pdo->beginTransaction();

        // ล็อกแถวชิ้นมือ 2
        $st = $pdo->prepare("SELECT * FROM parts_used WHERE id=? FOR UPDATE");
        $st->execute([$used_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception("ไม่พบข้อมูลชิ้นมือ 2");

        // Header
        $pdo->prepare("
          INSERT INTO parts_docs (doc_type, ref_no, remarks, user_id, created_at)
          VALUES ('CONSUME', ?, ?, ?, NOW())
        ")->execute([$ref_no ?: null, $remarks !== '' ? $remarks : "consume (used item #{$used_id})", $user_id]);
        $doc_id = (int)$pdo->lastInsertId();

        // Line (qty=1 จาก location 'used')
        $pdo->prepare("
          INSERT INTO parts_doc_lines (doc_id, part_code, qty, location_from, location_to, unit_cost)
          VALUES (?, ?, 1, 'used', NULL, NULL)
        ")->execute([$doc_id, $row['part_code']]);

        // ลบแถวออกจาก parts_used (ชิ้นต่อแถว => เบิกแล้วหายไป)
        $pdo->prepare("DELETE FROM parts_used WHERE id=?")->execute([$used_id]);

        $pdo->commit();
        back_to('used','msg=ตัดจ่ายชิ้นมือ2แล้ว');
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = $e->getMessage();
      }
    }
  }
}

// ========================== [TEMPLATE] ===============================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?> <?= $type==='used' ? '(มือ 2)' : '(มือ 1)' ?></span>
  </div>

  <div class="section-header">
    <h2>ตัดจ่าย/เบิก</h2>
    <div style="display:flex; gap:8px; align-items:center;">
      <a class="btn-secondary" href="index.php?tab=<?= $type==='used' ? 'used':'new' ?>">← กลับรายการ</a>
      <?php if ($type==='new'): ?>
        <a class="btn-secondary" href="consume.php?type=used<?= $used_id ? '&used_id='.((int)$used_id) : '' ?>">โหมดมือ 2</a>
      <?php else: ?>
        <a class="btn-secondary" href="consume.php?type=new<?= $code ? '&part_code='.urlencode($code) : '' ?>">โหมดมือ 1</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($type==='new'): ?>
    <!-- ===================== [VIEW: มือ 1] ===================== -->
    <?php if ($part_new): ?>
      <div class="card" style="padding:12px;border-radius:10px;margin-bottom:12px;">
        <div style="display:flex;gap:12px;align-items:center;">
          <?php $img = img_src($part_new['image_url'] ?? ''); ?>
          <?php if ($img): ?>
            <img src="<?= h($img) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #eee;" alt="">
          <?php else: ?>
            <div style="width:56px;height:56px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">ไม่มีรูป</div>
          <?php endif; ?>
          <div>
            <strong><?= h($part_new['part_name'] ?: $code) ?></strong>
            <div class="muted" style="font-size:12px;">รหัส: <?= h($code) ?> | เลข: <?= h($part_new['part_number']) ?></div>
            <div class="muted" style="font-size:12px;">รุ่น: <?= h($part_new['device_models']) ?> | คงเหลือรวม: <?= (int)$part_new['qty'] ?></div>
            <?php if ($locs): ?>
              <div class="muted" style="font-size:12px;margin-top:4px;">
                <?php foreach ($locs as $l): ?>
                  <span class="badge" style="margin-right:6px"><?= h($l['location']) ?>: <?= (int)$l['qty'] ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" class="card" style="padding:16px;border-radius:12px;max-width:720px;">
      <input type="hidden" name="mode" value="new">
      <div class="table-container">
        <table class="data-table"><tbody>
          <tr>
            <th style="width:220px;">รหัสอะไหล่ *</th>
            <td><input class="filter-input" name="part_code" required value="<?= h($code) ?>"></td>
          </tr>
          <tr>
            <th>ที่เก็บ *</th>
            <td>
              <?php if (!empty($locs)): ?>
                <?php $hasMain=false; foreach($locs as $l){ if(($l['location'] ?? '')==='main') $hasMain=true; } ?>
                <select name="location" class="filter-input" required>
                  <?php foreach ($locs as $l): ?>
                    <option value="<?= h($l['location']) ?>" <?= ($hasMain && $l['location']==='main')?'selected':'' ?>>
                      <?= h($l['location']) ?> (คงเหลือ <?= (int)$l['qty'] ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input class="filter-input" name="location" required value="main">
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th>จำนวนที่เบิก *</th>
            <td><input class="filter-input" type="number" min="1" name="qty" required value="1"></td>
          </tr>
          <tr>
            <th>เลขอ้างอิง</th>
            <td><input class="filter-input" name="ref_no" placeholder="ใบงาน, ชื่อช่าง ฯลฯ"></td>
          </tr>
          <tr>
            <th>หมายเหตุ</th>
            <td><input class="filter-input" name="remarks" placeholder="โน้ตสั้นๆ"></td>
          </tr>
        </tbody></table>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;">
        <button class="btn-primary" type="submit">บันทึกการเบิก</button>
        <a class="btn-secondary" href="index.php?tab=new">ยกเลิก</a>
      </div>
    </form>

  <?php else: ?>
    <!-- ===================== [VIEW: มือ 2] ===================== -->
    <?php if ($used): ?>
      <div class="card" style="padding:12px;border-radius:10px;margin-bottom:12px;">
        <div style="display:flex;gap:12px;align-items:center;">
          <?php $img = img_src($used['image_url'] ?? ''); ?>
          <?php if ($img): ?>
            <img src="<?= h($img) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #eee;" alt="">
          <?php else: ?>
            <div style="width:56px;height:56px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">ไม่มีรูป</div>
          <?php endif; ?>
          <div>
            <strong><?= h($used['part_name'] ?: $used['part_code']) ?></strong>
            <div class="muted" style="font-size:12px;">รหัส: <?= h($used['part_code']) ?> | เลข: <?= h($used['part_number']) ?></div>
            <div class="muted" style="font-size:12px;">รุ่น: <?= h($used['device_models']) ?> | ที่เก็บ: <?= h($used['location']) ?></div>
            <?php if (trim((string)$used['remarks'])!==''): ?>
              <div class="muted" style="font-size:12px;">หมายเหตุ: <?= h($used['remarks']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-info">เลือกชิ้นมือ 2 จากรายการก่อน (กด “เบิก” ที่หน้า “มือ 2”)</div>
    <?php endif; ?>

    <form method="post" class="card" style="padding:16px;border-radius:12px;max-width:720px;">
      <input type="hidden" name="mode" value="used">
      <input type="hidden" name="used_id" value="<?= (int)$used_id ?>">
      <div class="table-container">
        <table class="data-table"><tbody>
          <tr>
            <th style="width:220px;">รายการ *</th>
            <td><input class="filter-input" value="<?= $used ? h($used['part_code'].' — '.$used['part_name']) : '' ?>" disabled></td>
          </tr>
          <tr>
            <th>จำนวน</th>
            <td><input class="filter-input" value="1" disabled></td>
          </tr>
          <tr>
            <th>เลขอ้างอิง</th>
            <td><input class="filter-input" name="ref_no" placeholder="ใบงาน, ชื่อช่าง ฯลฯ"></td>
          </tr>
          <tr>
            <th>หมายเหตุ</th>
            <td><input class="filter-input" name="remarks" placeholder="โน้ตสั้นๆ"></td>
          </tr>
        </tbody></table>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;">
        <button class="btn-primary" type="submit" <?= $used ? '' : 'disabled' ?>>บันทึกการเบิก</button>
        <a class="btn-secondary" href="index.php?tab=used">ยกเลิก</a>
      </div>
    </form>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
