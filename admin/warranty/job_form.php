<?php
/********************************************************************
 * admin/warranty/job_form.php
 * เพิ่ม/แก้ไขงานประกัน (สไตล์เดิม + ดึงข้อมูล Tracking + ปุ่มสุ่มไอคอน)
 ********************************************************************/
session_start();
// ตั้งเวลาไทย
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
// require_once __DIR__ . '/../../includes/warranty_lib.php'; 
require_login();

// --- Permissions ---
// $can_create = can('warranty.jobs.create');
// $can_update = can('warranty.jobs.update');
$can_create = true;
$can_update = true;

function jf_get($k, $d = null) { return isset($_REQUEST[$k]) ? trim($_REQUEST[$k]) : $d; }
function jf_h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function jf_next_warranty_no(PDO $pdo): string {
    try {
        $id = (int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM warranty_jobs")->fetchColumn();
    } catch (Throwable $e) {
        $id = rand(1000, 9999);
    }
    return sprintf("WJ-%s-%04d", date('Ym'), $id);
}

function jf_status_from_until(?string $until): string {
    if (!$until) return 'void';
    return (strtotime($until) >= strtotime(date('Y-m-d'))) ? 'in_warranty' : 'expired';
}

$id = (int)jf_get('id', 0);
$job_id = (int)jf_get('job_id', 0); // รับ job_id จากหน้า Tracking

$is_edit = $id > 0;
if ($is_edit && !$can_update) { http_response_code(403); exit('Forbidden'); }
if (!$is_edit && !$can_create) { http_response_code(403); exit('Forbidden'); }

$job = [
    'warranty_no'   => jf_next_warranty_no($pdo),
    'repair_no'     => '',
    'customer_name' => '',
    'customer_phone'=> '',
    'device_model'  => '',
    'sn'            => '',
    'issue_summary' => '',
    'base_date'     => date('Y-m-d'),
    'warranty_days' => 90,
    'warranty_until'=> date('Y-m-d', strtotime('+89 day')),
    'terms_version' => 'v1',
];

// 1. กรณีแก้ไข
if ($is_edit) {
    $st = $pdo->prepare("SELECT * FROM warranty_jobs WHERE id=:id");
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); exit('Not found'); }
    $job = array_merge($job, $row);
}
// 2. กรณีสร้างใหม่จากหน้า Tracking
else if ($job_id > 0) {
    $st = $pdo->prepare("SELECT * FROM tracking WHERE id=:id");
    $st->execute([':id' => $job_id]);
    $track = $st->fetch(PDO::FETCH_ASSOC);

    if ($track) {
        // รวมชื่อรุ่นให้สวยงาม
        $fullModel = trim($track['device_type'] . ' ' . ($track['device_series'] ?? '') . ' ' . $track['device_model']);
        $cleanIssue = trim(strip_tags(html_entity_decode($track['problem_details'])));

        $job['repair_no']      = $track['ticket_number'];
        $job['customer_name']  = $track['customer_name'];
        $job['customer_phone'] = $track['customer_phone'];
        $job['device_model']   = $fullModel;
        $job['sn']             = $track['serial_number'];
        $job['issue_summary']  = $cleanIssue;
    }
}

// --- Process Form Submit ---
$errMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'warranty_no'   => jf_get('warranty_no', $job['warranty_no']),
        'repair_no'     => jf_get('repair_no', ''),
        'customer_name' => jf_get('customer_name', ''),
        'customer_phone'=> jf_get('customer_phone', ''),
        'device_model'  => jf_get('device_model', ''),
        'sn'            => jf_get('sn', ''),
        'issue_summary' => jf_get('issue_summary', ''),
        'base_date'     => jf_get('base_date', date('Y-m-d')),
        'warranty_days' => max(0, (int)jf_get('warranty_days', 90)),
        'terms_version' => jf_get('terms_version', 'v1'),
    ];

    $manual_until = jf_get('warranty_until', '');
    if ($manual_until !== '') {
        $data['warranty_until'] = $manual_until;
    } else {
        $base_ts = strtotime($data['base_date'] ?: date('Y-m-d'));
        $data['warranty_until'] = $data['warranty_days'] > 0
            ? date('Y-m-d', strtotime(($data['warranty_days'] - 1) . ' day', $base_ts))
            : $data['base_date'];
    }

    $data['warranty_status'] = jf_status_from_until($data['warranty_until']);

    $errs = [];
    if ($data['warranty_no']   === '') $errs[] = 'กรุณากรอกเลขประกัน';
    if ($data['customer_name'] === '') $errs[] = 'กรุณากรอกชื่อลูกค้า';
    if ($data['device_model']  === '') $errs[] = 'กรุณากรอกรุ่นอุปกรณ์';

    if (!$errs) {
        if ($is_edit) {
            $sql = "UPDATE warranty_jobs SET
                warranty_no=:warranty_no, repair_no=:repair_no,
                customer_name=:customer_name, customer_phone=:customer_phone,
                device_model=:device_model, sn=:sn,
                issue_summary=:issue_summary,
                base_date=:base_date, warranty_days=:warranty_days, warranty_until=:warranty_until,
                warranty_status=:warranty_status, terms_version=:terms_version,
                updated_at=NOW()
              WHERE id=:id";
            $data['id'] = $id;
            $st = $pdo->prepare($sql);
            $st->execute($data);
            header("Location: index.php?tab=jobs&msg=" . rawurlencode("บันทึกการแก้ไขแล้ว"));
            exit;
        } else {
            $sql = "INSERT INTO warranty_jobs
                (warranty_no, repair_no, source_type, group_seq,
                 customer_name, customer_phone, device_model, sn, issue_summary,
                 warranty_days, base_date, warranty_until, warranty_status,
                 terms_version, created_at, updated_at)
              VALUES
                (:warranty_no, :repair_no, 'manual', 1,
                 :customer_name, :customer_phone, :device_model, :sn, :issue_summary,
                 :warranty_days, :base_date, :warranty_until, :warranty_status,
                 :terms_version, NOW(), NOW())";
            $st = $pdo->prepare($sql);
            $st->execute($data);

            header("Location: index.php?tab=jobs&msg=" . rawurlencode("บันทึกงานประกันเรียบร้อย"));
            exit;
        }
    } else {
        $job = array_merge($job, $data);
        $errMsg = implode(' • ', $errs);
    }
}

$pageTitle = $is_edit ? "แก้ไขงานประกัน" : "เพิ่มงานประกัน";
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <div class="topbar">
    <span><?= jf_h($pageTitle) ?></span>
    <a href="index.php?tab=jobs" class="view-site">← กลับรายการ</a>
  </div>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-success"><?= jf_h($_GET['msg']) ?></div>
  <?php endif; ?>
  <?php if (!empty($errMsg)): ?>
    <div class="alert alert-danger"><?= jf_h($errMsg) ?></div>
  <?php endif; ?>

  <form action="" method="post" class="card-form">
    
    <?php if($job_id > 0): ?>
        <div style="background:#eff6ff; color:#1e40af; padding:10px 12px; border-radius:8px; margin-bottom:15px; border:1px solid #dbeafe; font-size:14px;">
            <span class="material-symbols-rounded" style="vertical-align:bottom; font-size:18px;">info</span> 
            ดึงข้อมูลจาก Job No. <strong><?= jf_h($job['repair_no']) ?></strong> เรียบร้อย
        </div>
    <?php endif; ?>

    <div class="form-grid">
      <div class="form-group">
        <label>เลขประกัน</label>
        <div class="inline-input">
          <input name="warranty_no" value="<?= jf_h($job['warranty_no']) ?>" class="input" required style="font-family:monospace; color:#2563eb; font-weight:600;">
          <button type="button" class="btn-secondary icon-btn" id="genNoBtn" title="สุ่มเลขใหม่">
            <span class="material-symbols-rounded">refresh</span>
          </button>
        </div>
        <small class="muted">WJ-YYYYMM-XXXX (แก้ไขได้)</small>
      </div>

      <div class="form-group">
        <label>เลขงานซ่อม</label>
        <input name="repair_no" value="<?= jf_h($job['repair_no']) ?>" class="input" placeholder="Vxxxx หรืออื่นๆ">
      </div>

      <div class="form-group">
        <label>ชื่อลูกค้า</label>
        <input name="customer_name" value="<?= jf_h($job['customer_name']) ?>" class="input" required>
      </div>

      <div class="form-group">
        <label>โทรศัพท์ลูกค้า</label>
        <input name="customer_phone" value="<?= jf_h($job['customer_phone']) ?>" class="input" placeholder="08x-xxx-xxxx">
      </div>

      <div class="form-group">
        <label>อุปกรณ์/รุ่น</label>
        <input name="device_model" value="<?= jf_h($job['device_model']) ?>" class="input" placeholder="เช่น MacBook A1708" required>
      </div>

      <div class="form-group">
        <label>S/N</label>
        <input name="sn" value="<?= jf_h($job['sn']) ?>" class="input" placeholder="-">
      </div>

      <div class="form-group form-span-2">
        <label>อาการเสีย (ย่อ)</label>
        <textarea name="issue_summary" class="textarea" rows="3"
          placeholder="เช่น ติดแล้วดับ, ชาร์จไม่เข้า, จอเป็นเส้น ฯลฯ"><?= jf_h($job['issue_summary']) ?></textarea>
      </div>

      <div class="form-group">
        <label>วันเริ่มนับ</label>
        <input type="date" name="base_date" id="base_date" value="<?= jf_h($job['base_date']) ?>" class="input" required>
      </div>

      <div class="form-group">
        <label>จำนวนวันประกัน</label>
        <div class="inline-input">
          <input type="number" name="warranty_days" id="warranty_days" value="<?= (int)$job['warranty_days'] ?>" class="input" min="0">
          <div class="chip-row">
            <button type="button" class="chip" data-days="30">30</button>
            <button type="button" class="chip" data-days="90">90</button>
            <button type="button" class="chip" data-days="180">180</button>
            <button type="button" class="chip" data-days="365">365</button>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>วันหมดประกัน (แก้ไขได้เอง หรือปล่อยว่างให้ระบบคำนวณ)</label>
        <input type="date" name="warranty_until" id="warranty_until"
               value="<?= jf_h($job['warranty_until']) ?>" class="input">
      </div>

      <div class="form-group">
        <label>เวอร์ชันเงื่อนไข (Terms)</label>
        <input name="terms_version" value="<?= jf_h($job['terms_version']) ?>" class="input" placeholder="v1">
      </div>
    </div>

    <div class="form-actions">
      <?php if ($is_edit): ?>
        <button class="btn-primary" type="submit">บันทึกการแก้ไข</button>
      <?php else: ?>
        <button class="btn-primary" type="submit" name="save">บันทึก</button>
      <?php endif; ?>
      
      <a class="btn-light" href="<?= ($job_id > 0) ? '../tracking/edit.php?id='.$job_id : 'index.php?tab=jobs' ?>">ยกเลิก</a>
    </div>
  </form>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<style>
  .card-form{background:#fff;border:1px solid #eee;border-radius:12px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.04);}
  .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}
  .form-group{display:flex;flex-direction:column;gap:6px;}
  .form-span-2{grid-column:span 2 / span 2;}
  .input,.textarea{width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;font-size:14px;}
  .inline-input{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
  .muted{color:#6b7280;}
  .form-actions{display:flex;gap:8px;margin-top:14px;}
  .btn-primary,.btn-secondary,.btn-light{display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:10px;font-weight:600;border:1px solid transparent;cursor:pointer;}
  .btn-primary{background:#2563eb;color:#fff;}
  .btn-secondary{background:#fff;color:#111827;border-color:#e5e7eb;}
  .btn-light{background:#f3f4f6;color:#111827;}
  .chip-row{display:flex;gap:6px;flex-wrap:wrap;}
  .chip{padding:6px 10px;border-radius:999px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;font-size:12px;}
  
  /* เพิ่ม Style สำหรับปุ่มไอคอนให้ขนาดพอดี */
  .icon-btn { padding: 8px; width: 40px; }
  .icon-btn .material-symbols-rounded { font-size: 20px; line-height: 1; }
  
  @media (max-width:880px){.form-grid{grid-template-columns:1fr;}.form-span-2{grid-column:auto;}}
</style>

<script>
  // สุ่มเลข
  document.getElementById('genNoBtn')?.addEventListener('click', () => {
    const ym = new Date().toISOString().slice(0,7).replace('-','');
    const r  = Math.floor(Math.random()*9000)+1000;
    document.querySelector('input[name="warranty_no"]').value = `WJ-${ym}-${r}`;
  });

  const dBase  = document.getElementById('base_date');
  const dDays  = document.getElementById('warranty_days');
  const dUntil = document.getElementById('warranty_until');

  function pad(n){ return n < 10 ? '0'+n : ''+n; }
  function toLocalDateString(dt){
    // คืนค่า YYYY-MM-DD ตามเวลาเครื่อง ไม่อิง UTC
    return dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate());
  }

  function calcUntil(){
    const base = dBase.value;
    const days = parseInt(dDays.value||'0',10);
    if(!base || isNaN(days)) return;

    // new Date('YYYY-MM-DDT00:00:00') เป็น Local time อยู่แล้ว
    const dt = new Date(base + 'T00:00:00');
    dt.setDate(dt.getDate() + Math.max(0, days-1)); // รวมวันแรก

    // คำนวณอัตโนมัติ เฉพาะถ้า user ยังไม่แก้เอง
    if (!dUntil.dataset.userEdited) {
      dUntil.value = toLocalDateString(dt);
    }
  }

  dUntil.addEventListener('input',()=>{ dUntil.dataset.userEdited = '1'; });
  dBase.addEventListener('change', calcUntil);
  dDays.addEventListener('input', calcUntil);
  calcUntil();

  document.querySelectorAll('.chip[data-days]')?.forEach(ch=>{
    ch.addEventListener('click', ()=>{
      dDays.value = ch.getAttribute('data-days');
      calcUntil();
    });
  });
</script>