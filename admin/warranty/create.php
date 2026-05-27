<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$pageTitle = "ออกใบรับประกัน";
$error     = '';

// Pre-fill from tracking_id if given
$tracking_id  = (int)($_GET['tracking_id'] ?? 0);
$prefill      = [];
if ($tracking_id > 0) {
    $t = $pdo->prepare("SELECT * FROM tracking WHERE id = ?");
    $t->execute([$tracking_id]);
    $trk = $t->fetch(PDO::FETCH_ASSOC);
    if ($trk) {
        $prefill = [
            'tracking_id'    => $trk['id'],
            'customer_name'  => $trk['customer_name'],
            'customer_phone' => $trk['customer_phone'],
            'device_model'   => trim(($trk['device_type'] ?? '') . ' ' . ($trk['device_model'] ?? '')),
            'serial_no'      => $trk['serial_number'] ?? '',
            'repair_summary' => '',
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid       = (int)($_POST['tracking_id'] ?? 0) ?: null;
    $cname     = trim($_POST['customer_name'] ?? '');
    $cphone    = trim($_POST['customer_phone'] ?? '');
    $device    = trim($_POST['device_model'] ?? '');
    $serial    = trim($_POST['serial_no'] ?? '');
    $summary   = trim($_POST['repair_summary'] ?? '');
    $w_days    = (int)($_POST['warranty_days'] ?? 90);
    $start     = $_POST['start_date'] ?? date('Y-m-d');
    $end       = date('Y-m-d', strtotime($start . " +$w_days days"));

    if (!$cname || !$device) {
        $error = "กรุณากรอกชื่อลูกค้าและรุ่นเครื่อง";
    } else {
        $wno = w_next_warranty_no($pdo);
        $pdo->prepare("INSERT INTO warranties
                        (warranty_no, tracking_id, customer_name, customer_phone,
                         device_model, serial_no, repair_summary, warranty_days, start_date, end_date, created_by)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$wno, $tid, $cname, $cphone, $device, $serial, $summary, $w_days, $start, $end,
                       $_SESSION['user_id'] ?? null]);
        $new_id = $pdo->lastInsertId();
        $_SESSION['success'] = "ออกใบประกัน $wno เรียบร้อย";
        header("Location: view.php?id=$new_id");
        exit;
    }

    // Re-populate after error
    $prefill = compact('tid','cname','cphone','device','serial','summary','w_days','start');
}

include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="<?= $assets_base ?>css/inventory-dashboard.css?v=3">
<style>
.war-form-wrap { max-width:720px; margin:0 auto; }
.war-card { background:var(--bg-surface); border:1px solid var(--border); border-radius:14px; padding:28px; margin-bottom:20px; }
.war-card-title { font-size:0.82rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); margin-bottom:18px; display:flex; align-items:center; gap:8px; }
.war-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.war-field { display:flex; flex-direction:column; gap:6px; }
.war-field.full { grid-column:1/-1; }
.war-label { font-size:0.82rem; font-weight:600; color:var(--text-muted); }
.war-input,.war-select,.war-textarea { width:100%; padding:10px 12px; border:1.5px solid var(--border); border-radius:8px; font-size:0.93rem; background:var(--bg-surface); color:var(--text-main); transition:.15s; }
.war-input:focus,.war-select:focus,.war-textarea:focus { outline:none; border-color:var(--primary); }
.war-textarea { resize:vertical; min-height:90px; }
.war-days-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; }
.war-days-opt { display:none; }
.war-days-opt + label { display:block; padding:10px 8px; border:2px solid var(--border); border-radius:10px; text-align:center; cursor:pointer; font-size:0.88rem; font-weight:700; transition:.15s; }
.war-days-opt:checked + label { border-color:var(--primary); background:var(--primary-light); color:var(--primary); }
.war-days-opt + label small { display:block; font-size:0.72rem; font-weight:400; color:var(--text-muted); margin-top:2px; }
.trk-lookup { display:flex; gap:8px; }
.trk-lookup input { flex:1; }
.trk-found { margin-top:10px; background:rgba(37,99,235,.06); border:1px solid rgba(37,99,235,.2); border-radius:8px; padding:10px 14px; font-size:0.85rem; display:none; }
@media(max-width:600px){ .war-grid { grid-template-columns:1fr; } .war-days-grid { grid-template-columns:repeat(3,1fr); } }
</style>

<div class="main-content">
<div class="war-form-wrap">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:22px;">
        <a href="index.php" class="cmns-btn cmns-btn-secondary" style="padding:8px 12px;">
            <span class="material-symbols-rounded">arrow_back</span>
        </a>
        <h1 class="page-title" style="margin:0;">ออกใบรับประกัน</h1>
    </div>

    <?php if ($error): ?>
        <div class="cmns-alert cmns-alert-danger" style="margin-bottom:16px;">
            <span class="material-symbols-rounded">error</span> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="post">

    <!-- ── Link to Tracking (optional) ── -->
    <div class="war-card">
        <div class="war-card-title">
            <span class="material-symbols-rounded">link</span>
            ผูกกับงานซ่อม (ไม่บังคับ)
        </div>
        <div class="war-field">
            <label class="war-label">เลขที่งานซ่อม (Ticket No.)</label>
            <div class="trk-lookup">
                <input type="text" id="trk_input" placeholder="เช่น TRK-2026-0001" class="war-input" value="<?= h($prefill['ticket_number'] ?? ($trk['ticket_number'] ?? '')) ?>">
                <input type="hidden" name="tracking_id" id="trk_id" value="<?= h($prefill['tracking_id'] ?? $prefill['tid'] ?? '') ?>">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="lookupTracking()">ดึงข้อมูล</button>
            </div>
            <div id="trk_found" class="trk-found"></div>
        </div>
    </div>

    <!-- ── Customer + Device ── -->
    <div class="war-card">
        <div class="war-card-title">
            <span class="material-symbols-rounded">person</span>
            ข้อมูลลูกค้าและเครื่อง
        </div>
        <div class="war-grid">
            <div class="war-field">
                <label class="war-label">ชื่อลูกค้า <span style="color:#ef4444;">*</span></label>
                <input type="text" name="customer_name" class="war-input" required value="<?= h($prefill['customer_name'] ?? $prefill['cname'] ?? '') ?>">
            </div>
            <div class="war-field">
                <label class="war-label">เบอร์โทร</label>
                <input type="text" name="customer_phone" class="war-input" value="<?= h($prefill['customer_phone'] ?? $prefill['cphone'] ?? '') ?>">
            </div>
            <div class="war-field">
                <label class="war-label">รุ่นเครื่อง <span style="color:#ef4444;">*</span></label>
                <input type="text" name="device_model" id="inp_device" class="war-input" required value="<?= h($prefill['device_model'] ?? $prefill['device'] ?? '') ?>">
            </div>
            <div class="war-field">
                <label class="war-label">Serial Number</label>
                <input type="text" name="serial_no" id="inp_serial" class="war-input" value="<?= h($prefill['serial_no'] ?? $prefill['serial'] ?? '') ?>">
            </div>
            <div class="war-field full">
                <label class="war-label">สรุปงานที่ซ่อม</label>
                <textarea name="repair_summary" class="war-textarea" placeholder="เช่น เปลี่ยนแบตเตอรี่ MacBook Pro 14&quot; M1, ซ่อมจอ iPhone 15 Pro..."><?= h($prefill['repair_summary'] ?? $prefill['summary'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- ── Warranty Period ── -->
    <div class="war-card">
        <div class="war-card-title">
            <span class="material-symbols-rounded">calendar_month</span>
            ระยะเวลารับประกัน
        </div>
        <div class="war-days-grid" style="margin-bottom:18px;">
            <?php
            $opts = [30=>'1 เดือน', 60=>'2 เดือน', 90=>'3 เดือน', 180=>'6 เดือน', 365=>'1 ปี'];
            $sel  = (int)($prefill['warranty_days'] ?? $prefill['w_days'] ?? 90);
            foreach ($opts as $d => $lbl):
            ?>
            <div>
                <input type="radio" name="warranty_days" id="wd_<?= $d ?>" class="war-days-opt" value="<?= $d ?>" <?= $sel===$d?'checked':'' ?> onchange="recalcEnd()">
                <label for="wd_<?= $d ?>"><?= $d ?> วัน<small><?= $lbl ?></small></label>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="war-grid">
            <div class="war-field">
                <label class="war-label">วันที่เริ่มประกัน</label>
                <input type="date" name="start_date" id="inp_start" class="war-input" value="<?= h($prefill['start'] ?? date('Y-m-d')) ?>" onchange="recalcEnd()">
            </div>
            <div class="war-field">
                <label class="war-label">วันหมดประกัน (คำนวณอัตโนมัติ)</label>
                <input type="text" id="disp_end" class="war-input" readonly style="background:var(--bg-surface-alt); color:var(--text-muted);">
            </div>
        </div>
    </div>

    <!-- ── Submit ── -->
    <div style="display:flex; gap:12px; justify-content:flex-end;">
        <a href="index.php" class="cmns-btn cmns-btn-secondary">ยกเลิก</a>
        <button type="submit" class="cmns-btn cmns-btn-primary">
            <span class="material-symbols-rounded">verified_user</span> ออกใบประกัน
        </button>
    </div>
    </form>
</div>
</div>

<script>
function addDays(dateStr, days) {
    const d = new Date(dateStr);
    d.setDate(d.getDate() + days);
    return d.toLocaleDateString('th-TH', {day:'2-digit', month:'2-digit', year:'numeric'});
}
function recalcEnd() {
    const start  = document.getElementById('inp_start').value;
    const days   = parseInt(document.querySelector('input[name="warranty_days"]:checked')?.value || 90);
    document.getElementById('disp_end').value = start ? addDays(start, days) : '-';
}

async function lookupTracking() {
    const ticket = document.getElementById('trk_input').value.trim();
    if (!ticket) return;
    const res  = await fetch('ajax.php?action=lookup_tracking&q=' + encodeURIComponent(ticket));
    const data = await res.json();
    const box  = document.getElementById('trk_found');
    if (data.ok) {
        document.getElementById('trk_id').value          = data.id;
        document.querySelector('[name="customer_name"]').value  = data.customer_name;
        document.querySelector('[name="customer_phone"]').value = data.customer_phone;
        document.getElementById('inp_device').value      = data.device_model;
        document.getElementById('inp_serial').value      = data.serial_no;
        box.style.display = 'block';
        box.innerHTML = `<span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;color:#059669;">check_circle</span>
                         ดึงข้อมูลจาก <strong>${data.ticket_number}</strong> — ${data.customer_name} / ${data.device_model}`;
    } else {
        box.style.display = 'block';
        box.innerHTML = `<span style="color:#dc2626;">ไม่พบงานซ่อม "${ticket}"</span>`;
        box.style.background = 'rgba(239,68,68,.06)';
        box.style.borderColor = 'rgba(239,68,68,.2)';
    }
}

document.addEventListener('DOMContentLoaded', recalcEnd);
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
