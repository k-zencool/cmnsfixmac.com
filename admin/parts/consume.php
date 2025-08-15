<?php
// admin/parts/consume.php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin','manager']);

$pageTitle = "ตัดจ่าย/เบิก (มือ 1)";
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$code = trim($_GET['part_code'] ?? '');
$errors = [];

// โหลดสรุปอะไหล่
$part = null;
if ($code !== '') {
  $st = $pdo->prepare("
    SELECT part_code,
           MAX(part_name) part_name,
           MAX(part_number) part_number,
           MAX(device_models) device_models,
           MAX(category) category,
           MAX(image_url) image_url,
           SUM(quantity) qty
    FROM parts_new
    WHERE part_code=?
    GROUP BY part_code
    LIMIT 1
  ");
  $st->execute([$code]);
  $part = $st->fetch(PDO::FETCH_ASSOC);
}

// บันทึก
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $code     = trim($_POST['part_code'] ?? '');
  $location = trim($_POST['location'] ?? 'main');
  $qty      = max(0, (int)($_POST['qty'] ?? 0));
  $ref_no   = trim($_POST['ref_no'] ?? '');
  $remarks  = trim($_POST['remarks'] ?? '');
  $user_id  = $_SESSION['admin_id'] ?? 1;

  if ($code==='') $errors[] = "กรอกรหัสอะไหล่";
  if ($qty<=0)    $errors[] = "จำนวนต้องมากกว่า 0";

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // ล็อกแถวสต็อกที่จะตัด
      $st = $pdo->prepare("SELECT quantity FROM parts_new WHERE part_code=? AND location=? FOR UPDATE");
      $st->execute([$code,$location]);
      $have = (int)($st->fetchColumn() ?? 0);
      if ($have < $qty) throw new Exception("คงเหลือไม่พอในที่เก็บนี้ (เหลือ $have)");

      // header
      $pdo->prepare("INSERT INTO parts_docs(doc_type, ref_no, remarks, user_id)
                     VALUES('CONSUME', ?, ?, ?)")
          ->execute([$ref_no ?: null, $remarks !== '' ? $remarks : "consume from {$location}", $user_id]);
      $doc_id = (int)$pdo->lastInsertId();

      // line
      $pdo->prepare("INSERT INTO parts_doc_lines(doc_id, part_code, qty, location_from)
                     VALUES(?,?,?,?)")
          ->execute([$doc_id, $code, $qty, $location]);

      // หักสต็อก
      $pdo->prepare("UPDATE parts_new SET quantity=quantity-? WHERE part_code=? AND location=?")
          ->execute([$qty, $code, $location]);

      $pdo->commit();
      header("Location: index.php?tab=new&restocked=1"); // ใช้ alert กล่องเดียวกันไปก่อน
      exit;
    } catch(Throwable $e){
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = $e->getMessage();
    }
  }
}

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar"><span><?= h($pageTitle) ?></span></div>

  <div class="section-header">
    <h2>ตัดจ่าย/เบิก</h2>
    <a href="index.php?tab=new" class="btn-secondary">← กลับรายการ</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <?php if ($part): ?>
    <div class="card" style="padding:12px;border-radius:10px;margin-bottom:12px;">
      <div style="display:flex;gap:12px;align-items:center;">
        <?php if ($part['image_url']): ?>
          <img src="../../uploads/parts/<?= h($part['image_url']) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #eee;">
        <?php else: ?>
          <div style="width:56px;height:56px;border:1px dashed #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">ไม่มีรูป</div>
        <?php endif; ?>
        <div>
          <strong><?= h($part['part_name'] ?: $code) ?></strong>
          <div class="muted" style="font-size:12px;">รหัส: <?= h($code) ?> | เลข: <?= h($part['part_number']) ?></div>
          <div class="muted" style="font-size:12px;">รุ่น: <?= h($part['device_models']) ?> | คงเหลือรวม: <?= (int)$part['qty'] ?></div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" class="card" style="padding:16px;border-radius:12px;max-width:720px;">
    <div class="table-container">
      <table class="data-table"><tbody>
        <tr>
          <th style="width:220px;">รหัสอะไหล่ *</th>
          <td><input class="filter-input" name="part_code" required value="<?= h($code) ?>"></td>
        </tr>
        <tr>
          <th>ที่เก็บ *</th>
          <td><input class="filter-input" name="location" required value="main"></td>
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
</main>
<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>
