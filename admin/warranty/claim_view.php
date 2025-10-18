<?php
/********************************************************************
 * admin/warranty/claim_view.php — ดูรายละเอียดการเคลม
 ********************************************************************/
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: index.php?tab=claims&err=ไม่พบเคลม"); exit; }

require_perms(['warranty.claims.view']);

/* ---------- โหลดข้อมูลเคลม ---------- */
$st = $pdo->prepare("
  SELECT c.*,
         j.warranty_no, j.repair_no, j.customer_name, j.device_model
  FROM warranty_claims c
  LEFT JOIN warranty_jobs j ON j.id = c.job_id
  WHERE c.id = :id
");
$st->execute([':id'=>$id]);
$claim = $st->fetch(PDO::FETCH_ASSOC);
if (!$claim) { header("Location: index.php?tab=claims&err=ไม่พบเคลม"); exit; }

/* ---------- สิทธิ์ปุ่ม ---------- */
$canUpdate = can('warranty.claims.update');
$canDelete = can('warranty.claims.delete');

$pageTitle = "รายละเอียดเคลม";

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
<!-- Topbar / Breadcrumb -->
<div class="topbar" style="display:flex;align-items:center;justify-content:space-between;">
  <div class="bread" style="display:flex;align-items:center;gap:10px;">
    <a href="index.php?tab=claims" class="view-site">← รายการเคลม</a>
    <span class="muted">›</span>
    <a href="job_view.php?id=<?= (int)$claim['job_id'] ?>" class="view-site">งานประกัน #<?= (int)$claim['job_id'] ?></a>
    <span class="muted">›</span>
    <span>เลขเคลม: <strong class="mono"><?= w_h_nb($claim['claim_no'] ?? ('#'.$claim['id'])) ?></strong></span>
  </div>

</div>


  <div class="section-header"
       style="display:flex;align-items:center;justify-content:space-between;margin:8px 0 12px;">
    <h2>เลขเคลม: <?= w_h_nb($claim['claim_no']) ?></h2>
    <div>
      <?php if ($canUpdate): ?>
        <a class="btn-secondary" href="claim_form.php?id=<?= (int)$claim['id'] ?>">แก้ไข</a>
      <?php endif; ?>
      <?php if ($canDelete): ?>
        <form action="claim_delete.php" method="post" style="display:inline"
              onsubmit="return confirm('ยืนยันลบเคลมนี้?');">
          <input type="hidden" name="id" value="<?= (int)$claim['id'] ?>">
          <input type="hidden" name="back" value="job">
          <button class="btn-danger" type="submit">ลบ</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="table-container" style="padding:12px 14px;">
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
      <div>
        <div class="muted">เลขเคลม</div>
        <div class="mono"><strong><?= w_h_nb($claim['claim_no']) ?></strong></div>
      </div>
      <div>
        <div class="muted">เลขประกัน</div>
        <div class="mono"><?= w_h_nb($claim['warranty_no']) ?></div>
      </div>
      <div>
        <div class="muted">เลขงานซ่อม</div>
        <div class="mono"><?= $claim['repair_no'] ? w_h_nb($claim['repair_no']) : '-' ?></div>
      </div>
      <div>
        <div class="muted">ลูกค้า</div>
        <div><?= w_h($claim['customer_name']) ?></div>
      </div>
      <div>
        <div class="muted">อุปกรณ์</div>
        <div><?= w_h($claim['device_model']) ?></div>
      </div>
      <div>
        <div class="muted">วันที่เปิดเคลม</div>
        <div class="mono"><?= w_h($claim['claim_date']) ?></div>
      </div>
      <div>
        <div class="muted">สถานะ</div>
        <?php
          $cs = (string)$claim['result'];
          $cls = [
            'pending'=>'badge-amber','investigating'=>'badge-blue','accepted'=>'badge-green',
            'rejected'=>'badge-red','closed'=>''
          ][$cs] ?? '';
          $cs_th = [
            'pending'=>'รอตรวจ','investigating'=>'กำลังตรวจสอบ','accepted'=>'รับเคลม',
            'rejected'=>'ปฏิเสธ','closed'=>'ปิดเคส'
          ][$cs] ?? $cs;
        ?>
        <div><span class="badge <?= w_h($cls) ?>"><?= w_h($cs_th) ?></span></div>
      </div>
      <div>
        <div class="muted">ผู้รับผิดชอบ</div>
        <div class="mono"><?= $claim['handled_by'] !== null ? (int)$claim['handled_by'] : '-' ?></div>
      </div>
      <div style="grid-column:1/-1">
        <div class="muted">อาการ / เหตุผลการเคลม</div>
        <div><?= nl2br(w_h($claim['claim_reason'] ?? '-')) ?></div>
      </div>
      <div style="grid-column:1/-1">
        <div class="muted">หมายเหตุ/การแก้ไข (Resolution)</div>
        <div><?= nl2br(w_h($claim['resolution_note'] ?? '-')) ?></div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<style>
  .table-container{background:#fff;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);overflow:hidden}
  .muted{color:#6b7280}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
  .badge{display:inline-block;padding:2px 8px;border-radius:999px;background:#f3f4f6;color:#374151;font-size:12px}
  .badge-green{background:#e7f7ef;color:#0a7f42}
  .badge-amber{background:#fff6e6;color:#a05a00}
  .badge-red{background:#fde8e8;color:#a40000}
  .btn-secondary{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px 14px;color:#111;margin-right:6px;text-decoration:none}
  .btn-danger{background:#ef4444;color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer}
</style>
