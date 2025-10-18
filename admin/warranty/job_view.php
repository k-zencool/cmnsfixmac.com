<?php
/********************************************************************
 * admin/warranty/job_view.php — ดูรายละเอียดงานประกัน + เคลมที่เกี่ยวข้อง
 ********************************************************************/
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();
require_perms(['warranty.jobs.view']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php?tab=jobs&err=" . rawurlencode("ไม่พบงานประกัน")); exit;
}

/* ---------- โหลดข้อมูลงานประกัน ---------- */
$st = $pdo->prepare("
  SELECT id, warranty_no, repair_no, customer_name, customer_phone,
         device_model, sn, base_date, warranty_days, warranty_until,
         warranty_status, terms_version, created_at, updated_at,
         DATEDIFF(warranty_until, CURDATE()) AS days_left
  FROM warranty_jobs
  WHERE id=:id
");
$st->execute([':id'=>$id]);
$job = $st->fetch(PDO::FETCH_ASSOC);
if (!$job) {
    header("Location: index.php?tab=jobs&err=" . rawurlencode("ไม่พบงานประกัน")); exit;
}
$pageTitle = "รายละเอียดงานประกัน";

/* ---------- ฟังก์ชันช่วยเลือกคอลัมน์ ---------- */
function pick(PDO $pdo, array $cands, string $alias): array {
    foreach ($cands as $c) {
        $chk = $pdo->prepare("SHOW COLUMNS FROM warranty_claims LIKE :c");
        try { $chk->execute([':c'=>$c]); } catch(Throwable $e) {}
        if ($chk && $chk->fetch(PDO::FETCH_ASSOC)) {
            return ['field'=>"c.`{$c}`",'select'=>"c.`{$c}` AS {$alias}"];
        }
    }
    return ['field'=>null,'select'=>"NULL AS {$alias}"];
}
function issue_sql(PDO $pdo): string {
    // ถ้ามี claim_reason ให้ใช้ก่อน
    $candFirst = pick($pdo, ['claim_reason'], 'issue_text');
    if ($candFirst['field']) return $candFirst['select'];
    $alts = [];
    foreach (['issue_text','issue_summary','issue','description','details','notes','remark','remarks'] as $c) {
        $chk = $pdo->prepare("SHOW COLUMNS FROM warranty_claims LIKE :c");
        try { $chk->execute([':c'=>$c]); } catch(Throwable $e){}
        if ($chk && $chk->fetch(PDO::FETCH_ASSOC)) $alts[] = "c.`{$c}`";
    }
    return $alts ? ('COALESCE('.implode(',', $alts).") AS issue_text") : "'' AS issue_text";
}

/* ---------- ตรวจคอลัมน์ของตาราง claims ---------- */
$C_NO      = pick($pdo, ['claim_no','claim_code','no','ref_no'], 'claim_no');
$C_STATUS  = pick($pdo, ['result','claim_status','status','state'], 'claim_status');
$C_DATE    = pick($pdo, ['claim_date','created_at','opened_at','created','date'], 'created_at');
$ISSUE_SEL = issue_sql($pdo);

/* ---------- โหลดเคลมที่เกี่ยวข้อง ---------- */
$sqlC = "
  SELECT c.id, c.job_id,
         {$C_NO['select']},
         {$C_STATUS['select']},
         {$C_DATE['select']},
         {$ISSUE_SEL}
  FROM warranty_claims c
  WHERE c.job_id=:id
  ORDER BY c.id DESC
";
$stc = $pdo->prepare($sqlC);
$stc->execute([':id'=>$id]);
$claims = $stc->fetchAll(PDO::FETCH_ASSOC);

/* ---------- สิทธิ์ปุ่ม ---------- */
$canCreateClaim = can('warranty.claims.create');
$canEditJob     = can('warranty.jobs.update');

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <span><?= w_h($pageTitle) ?></span>
    <a href="index.php?tab=jobs" class="view-site">← กลับรายการ</a>
  </div>

  <!-- หัวบัตร + ปุ่ม -->
  <div class="section-header">
    <h2>เลขประกัน: <?= w_h_nb($job['warranty_no']) ?></h2>
    <div>
      <?php if ($canCreateClaim): ?>
        <a href="claim_form.php?job_id=<?= (int)$job['id'] ?>" class="btn-primary">+ เพิ่มเคลม</a>
      <?php endif; ?>
      <?php if ($canEditJob): ?>
        <a href="job_form.php?id=<?= (int)$job['id'] ?>" class="btn-secondary">แก้ไข</a>
      <?php endif; ?>

      <?php if (can('warranty.jobs.delete') && in_array($job['warranty_status'], ['expired','void'])): ?>
        <form action="job_delete.php" method="post" style="display:inline"
              onsubmit="return confirm('ลบงานประกันนี้ถาวร?');">
          <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
          <button class="btn-danger">ลบงาน</button>
        </form>
      <?php endif; ?>
    </div>
  </div>


  <!-- การ์ดรายละเอียดงานประกัน -->
  <div class="table-container" style="padding:12px 14px;margin-bottom:12px">
    <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
      <div><div class="muted">เลขประกัน</div><div class="mono"><strong><?= w_h_nb($job['warranty_no']) ?></strong></div></div>
      <div><div class="muted">เลขงานซ่อม</div><div class="mono"><?= $job['repair_no']? w_h_nb($job['repair_no']) : '-' ?></div></div>
      <div><div class="muted">ลูกค้า</div><div><?= w_h($job['customer_name']) ?></div></div>
      <div><div class="muted">โทรศัพท์</div><div class="mono"><?= w_h($job['customer_phone'] ?: '-') ?></div></div>

      <div><div class="muted">อุปกรณ์/รุ่น</div><div><?= w_h($job['device_model']) ?></div></div>
      <div><div class="muted">S/N</div><div class="mono muted"><?= w_h($job['sn'] ?: '-') ?></div></div>
      <div><div class="muted">เริ่ม</div><div class="mono"><?= w_h($job['base_date'] ?: '-') ?></div></div>
      <div><div class="muted">วันหมดประกัน</div><div class="mono"><strong><?= w_h($job['warranty_until']) ?></strong></div></div>

      <div><div class="muted">วันคุ้มครอง</div><div class="mono"><?= (int)$job['warranty_days'] ?></div></div>
      <div><div class="muted">Terms</div><div class="mono"><?= w_h($job['terms_version'] ?: 'v1') ?></div></div>
      <div>
        <?php
          $d = (int)$job['days_left'];
          $lbl = w_status_label($job['warranty_status'], $d);
          $cls = w_badge_class($job['warranty_status'], $d);
          $title = $d>=0 ? "เหลืออีก {$d} วัน" : "เลยกำหนด ".abs($d)." วัน";
        ?>
        <div class="muted">สถานะ</div>
        <div><span class="badge <?= w_h($cls) ?>" title="<?= w_h($title) ?>"><?= w_h($lbl) ?></span></div>
      </div>
    </div>
  </div>

  <!-- ตารางเคลม -->
  <div class="table-container">
    <table class="data-table">
      <colgroup>
        <col class="w-col-idx">
        <col class="w-col-claim">
        <col class="w-col-code">
        <col class="w-col-repair">
        <col>
        <col>
        <col>
      </colgroup>
      <thead>
        <tr>
          <th class="center">#</th>
          <th>เลขเคลม</th>
          <th>เลขประกัน</th>
          <th>เลขงานซ่อม</th>
          <th>อาการ/รายละเอียด</th>
          <th>เปิดเมื่อ</th>
          <th class="center">สถานะ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($claims): foreach($claims as $i=>$c):
          $statusRaw = (string)($c['claim_status'] ?? '');
          $mapTH = ['pending'=>'เปิดใหม่','open'=>'เปิดใหม่','investigating'=>'กำลังตรวจสอบ','accepted'=>'รับเคลม','rejected'=>'ปฏิเสธ','closed'=>'ปิดเคส','void'=>'โมฆะ'];
          $mapClass = ['pending'=>'badge-amber','open'=>'badge-amber','investigating'=>'badge-blue','accepted'=>'badge-green','rejected'=>'badge-red','closed'=>'','void'=>'badge-amber'];
          $th = $mapTH[$statusRaw] ?? $statusRaw;
          $bc = $mapClass[$statusRaw] ?? '';
        ?>
          <tr data-goto="claim_view.php?id=<?= (int)$c['id'] ?>">
            <td class="center mono"><?= $i+1 ?></td>
            <td class="mono nowrap">
              <div class="cell-code">
                <strong><?= w_h_nb($c['claim_no']) ?></strong>
                <button type="button" class="copy-btn" data-copy="<?= w_h($c['claim_no']) ?>">⧉</button>
              </div>
            </td>
            <td class="mono nowrap"><?= w_h_nb($job['warranty_no']) ?></td>
            <td class="mono nowrap"><?= $job['repair_no'] ? w_h_nb($job['repair_no']) : '-' ?></td>
            <td><?= ($c['issue_text'] ?? '') !== '' ? w_h($c['issue_text']) : '<span class="muted">-</span>' ?></td>
            <td class="mono"><?= w_h($c['created_at'] ?? '-') ?></td>
            <td class="center"><span class="badge <?= w_h($bc) ?>"><?= w_h($th) ?></span></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="7" class="text-center">ยังไม่มีการเคลม</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<style>
  .table-container{background:#fff;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);overflow:hidden}
  .data-table{width:100%;border-collapse:collapse;background:#fff}
  .data-table th,.data-table td{padding:12px 14px;border-bottom:1px solid #eee;vertical-align:middle}
  .data-table th.center,.data-table td.center{text-align:center}
  .muted{color:#6b7280}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
  col.w-col-idx{width:56px}
  col.w-col-code{width:160px}
  col.w-col-repair{width:140px}
  col.w-col-claim{width:200px}
  .badge{display:inline-block;padding:2px 8px;border-radius:999px;background:#f3f4f6;color:#374151;font-size:12px}
  .badge-green{background:#e7f7ef;color:#0a7f42}
  .badge-amber{background:#fff6e6;color:#a05a00}
  .badge-red{background:#fde8e8;color:#a40000}
  .cell-code{display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
  .copy-btn{margin-left:6px;border:1px solid #e5e7eb;padding:2px 6px;border-radius:6px;background:#fff;cursor:pointer;font-size:12px}
  .copy-btn.copied{border-color:#34d399;box-shadow:0 0 0 2px rgba(52,211,153,.2) inset}
  tr[data-goto]{cursor:pointer}
</style>
<script>
document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.copy-btn');
  if (btn){
    const t = btn.getAttribute('data-copy') || '';
    if (t) navigator.clipboard.writeText(t).then(()=>{
      btn.classList.add('copied');
      setTimeout(()=>btn.classList.remove('copied'),800);
    });
    return;
  }
  const row = e.target.closest('tr[data-goto]');
  if (row && !e.target.closest('a,button')) location = row.getAttribute('data-goto');
});
</script>
