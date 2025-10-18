<?php
/********************************************************************
 * admin/warranty/claim_form.php — เพิ่ม/แก้ไข การเคลม (create + update)
 * รองรับตาราง warranty_claims ที่มีคอลัมน์ตามสกรีน: 
 * id, claim_no, job_id, claim_date, claim_reason, result, resolution_note, handled_by, updated_at
 * - ถ้าไม่มี created_at โค้ดจะไม่แตะต้อง
 ********************************************************************/
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;       // แก้ไขถ้ามี
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

if ($id) {
  require_perms(['warranty.claims.update']);
} else {
  require_perms(['warranty.claims.create']);
  if ($job_id <= 0) { header("Location: index.php?tab=claims&err=ต้องระบุ job_id"); exit; }
}

/* ---------- โหลดเคลมเดิมกรณีแก้ไข ---------- */
$claim = null; $job = null; $pageTitle = $id ? "แก้ไขเคลม" : "เพิ่มเคลม";
if ($id) {
  $st = $pdo->prepare("SELECT * FROM warranty_claims WHERE id=:id");
  $st->execute([':id'=>$id]);
  $claim = $st->fetch(PDO::FETCH_ASSOC);
  if (!$claim) { header("Location: index.php?tab=claims&err=ไม่พบเคลม"); exit; }
  $job_id = (int)$claim['job_id'];
}

/* ---------- โหลดข้อมูลงานประกัน เพื่อโชว์หัวเรื่อง ---------- */
$st = $pdo->prepare("SELECT id,warranty_no,repair_no,customer_name,device_model FROM warranty_jobs WHERE id=:id");
$st->execute([':id'=>$job_id]);
$job = $st->fetch(PDO::FETCH_ASSOC);
if (!$job) { header("Location: index.php?tab=jobs&err=ไม่พบน้ำหนักงานประกัน"); exit; }

/* ---------- POST: บันทึก ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // รับค่าจากฟอร์ม
  $claim_date     = trim($_POST['claim_date'] ?? '');
  $claim_reason   = trim($_POST['claim_reason'] ?? '');
  $result         = trim($_POST['result'] ?? 'pending');
  $resolution_note= trim($_POST['resolution_note'] ?? '');
  $handled_by_raw = trim($_POST['handled_by'] ?? '');
  $handled_by     = ($handled_by_raw === '' ? null : (int)$handled_by_raw);

  // กันค่าว่างที่ DB รับไม่ได้
  if ($claim_date === '') $claim_date = date('Y-m-d 00:00:00');

  if ($id) {
    // UPDATE
    $sql = "
      UPDATE warranty_claims
      SET claim_date=:cd, claim_reason=:cr, result=:rs, resolution_note=:rn, handled_by=:hb
      WHERE id=:id
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':cd', $claim_date);
    $st->bindValue(':cr', $claim_reason);
    $st->bindValue(':rs', $result);
    $st->bindValue(':rn', $resolution_note);
    if ($handled_by === null) $st->bindValue(':hb', null, PDO::PARAM_NULL);
    else                      $st->bindValue(':hb', $handled_by, PDO::PARAM_INT);
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();

    header("Location: claim_view.php?id={$id}&msg=อัปเดตเรียบร้อย");
    exit;
  } else {
    // CREATE: gen running claim_no (ง่ายๆรูปแบบ CYYYYMMDD-### ต่อวัน)
    $today = date('Ymd');
    $prefix = 'C' . $today . '-';
    $running = 1;
    $st = $pdo->prepare("SELECT claim_no FROM warranty_claims WHERE claim_no LIKE :pfx ORDER BY claim_no DESC LIMIT 1");
    $st->execute([':pfx' => $prefix . '%']);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $last = (int)substr($row['claim_no'], -3);
      $running = $last + 1;
    }
    $claim_no = $prefix . str_pad((string)$running, 3, '0', STR_PAD_LEFT);

    $sql = "
      INSERT INTO warranty_claims
        (claim_no, job_id, claim_date, claim_reason, result, resolution_note, handled_by)
      VALUES
        (:cn, :jid, :cd, :cr, :rs, :rn, :hb)
    ";
    $st = $pdo->prepare($sql);
    $st->bindValue(':cn',  $claim_no);
    $st->bindValue(':jid', $job_id, PDO::PARAM_INT);
    $st->bindValue(':cd',  $claim_date);
    $st->bindValue(':cr',  $claim_reason);
    $st->bindValue(':rs',  $result);
    $st->bindValue(':rn',  $resolution_note);
    if ($handled_by === null) $st->bindValue(':hb', null, PDO::PARAM_NULL);
    else                      $st->bindValue(':hb', $handled_by, PDO::PARAM_INT);
    $st->execute();

    $newId = (int)$pdo->lastInsertId();
    header("Location: claim_view.php?id={$newId}&msg=เพิ่มเคลมเรียบร้อย");
    exit;
  }
}

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <a href="job_view.php?id=<?= (int)$job['id'] ?>" class="view-site">← กลับงานประกัน</a>
    <span><?= w_h($pageTitle) ?></span>
    <div></div>
  </div>

  <div class="section-header" style="display:flex;align-items:center;justify-content:space-between;margin:8px 0 12px;">
    <h2>
      <?= $id ? 'แก้ไขเคลม' : 'เพิ่มเคลมใหม่' ?>
      <small class="muted" style="font-weight:400">/ งาน: <?= w_h_nb($job['warranty_no']) ?> (<?= w_h_nb($job['repair_no']) ?>)</small>
    </h2>
  </div>

  <form action="" method="post" class="form-card" style="background:#fff;border-radius:12px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
    <div class="grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
      <?php if ($id): ?>
        <div>
          <label class="form-label">เลขเคลม</label>
          <input class="filter-input mono" value="<?= w_h_nb($claim['claim_no']) ?>" disabled>
        </div>
      <?php endif; ?>

      <div>
        <label class="form-label">วันที่เปิดเคลม</label>
        <input type="datetime-local" name="claim_date" class="filter-input"
               value="<?= w_h(dt_to_local($claim['claim_date'] ?? '')) ?>">
      </div>

      <div>
        <label class="form-label">สถานะ</label>
        <select name="result" class="filter-input">
          <?php
            // เซ็ต default ให้ครอบคลุม enum/ข้อความอะไรก็ได้ในตาราง
            $cur = $claim['result'] ?? 'pending';
            $opts = ['pending'=>'รอตรวจ','investigating'=>'กำลังตรวจสอบ','accepted'=>'รับเคลม','rejected'=>'ปฏิเสธ','closed'=>'ปิดเคส'];
            foreach ($opts as $v=>$lb): ?>
              <option value="<?= w_h($v) ?>" <?= $cur===$v?'selected':'' ?>><?= w_h($lb) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="form-label">ผู้รับผิดชอบ (รหัสพนักงาน)</label>
        <input type="number" name="handled_by" class="filter-input mono" placeholder="ตัวเลขหรือเว้นว่าง"
               value="<?= w_h($claim['handled_by'] ?? '') ?>">
      </div>

      <div style="grid-column:1/-1">
        <label class="form-label">อาการ / เหตุผลการเคลม</label>
        <textarea name="claim_reason" class="filter-input" rows="3" placeholder="อธิบายอาการเคลม..."><?= w_h($claim['claim_reason'] ?? '') ?></textarea>
      </div>

      <div style="grid-column:1/-1">
        <label class="form-label">หมายเหตุ/การแก้ไข (Resolution)</label>
        <textarea name="resolution_note" class="filter-input" rows="3" placeholder="บันทึกผลตรวจ, อะไหล่ที่เปลี่ยน, ส่งต่อ ฯลฯ"><?= w_h($claim['resolution_note'] ?? '') ?></textarea>
      </div>
    </div>

    <div style="margin-top:14px;display:flex;gap:8px;">
      <button class="btn-primary"><?= $id ? 'บันทึกการแก้ไข' : 'บันทึกเคลม' ?></button>
      <a href="<?= $id ? 'claim_view.php?id='.(int)$id : 'job_view.php?id='.(int)$job['id'] ?>" class="btn-secondary">ยกเลิก</a>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<style>
  .form-label{display:block;margin:4px 0 6px;color:#374151;font-size:14px}
  .filter-input{width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px}
  .btn-primary{background:#2563eb;color:#fff;border:none;border-radius:8px;padding:10px 14px;cursor:pointer}
  .btn-secondary{display:inline-block;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;color:#111}
</style>
<?php
/* ===== helpers เฉพาะฟอร์มนี้ ===== */
function dt_to_local($dt){
  if(!$dt || $dt==='0000-00-00 00:00:00') return '';
  // แปลง 'Y-m-d H:i:s' -> 'Y-m-d\TH:i' สำหรับ input[type=datetime-local]
  $t = strtotime($dt); if(!$t) return '';
  return date('Y-m-d\TH:i', $t);
}
