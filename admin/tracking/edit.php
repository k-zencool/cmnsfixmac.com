<?php
/********************************************************************
 * admin/tracking/edit.php  –  Edit Repair Job
 ********************************************************************/

session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errorMsg = '';

$stmt = $pdo->prepare("SELECT * FROM tracking WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$job) die("ไม่พบข้อมูลงานซ่อม (Job Not Found)");

$deviceList = ['iPhone','iPad','MacBook','iMac','Notebook','PC','Mac mini','Mac Studio','Mac Pro','Apple Watch','AirPods','Apple TV','Other'];
$accsList   = ['ตัวเครื่อง','Adapter','สายชาร์จ','กระเป๋า','Soft Case','กล่อง','Mouse','Keyboard'];
$stateList  = ['ปกติ/สวย','รอยขีดข่วน','รอยบุบ/ตก','น็อตหาย','เคยแกะซ่อม','แบตบวม','โดนน้ำ','เครื่องประกอบไม่สมบูรณ์'];
$sympsList  = ['ไฟเข้าเปิดไม่ติด','ไฟไม่เข้าเปิดไม่ติด','จอแตก/เสีย','แบตเสื่อม','คีย์บอร์ดเสีย','Trackpadเสีย','Wifi/BT เสีย','ลงโปรแกรม','ชาร์จไม่เข้า','windows/os'];
$statusList = [
    'QS'  => 'รอเช็คราคา',
    'WC'  => 'รอคอนเฟิร์ม',
    'OK'  => 'กำลังซ่อม',
    'RW'  => 'งานแก้/เคลม',
    'FN'  => 'ซ่อมเสร็จ (รอรับ)',
    'DV'  => 'ส่งมอบแล้ว',
    'NCF' => 'ติดต่อไม่ได้ (เสร็จ)',
    'NCS' => 'ติดต่อไม่ได้ (เสนอ)',
    'XX'  => 'ยกเลิก',
    'RT'  => 'รับคืนแล้ว',
];

function getCheckedItems($s) {
    if (!$s) return [];
    return array_map('trim', explode(',', $s));
}
function getOtherText($s, $list) {
    return implode(', ', array_diff(getCheckedItems($s), $list));
}

$savedAccs  = getCheckedItems($job['accessories'] ?? '');
$otherAccs  = getOtherText($job['accessories'] ?? '', $accsList);
$savedSymps = [];
$detailText = $job['problem_details'];
if (preg_match('/^\[(.*?)\](.*)/s', $job['problem_details'], $m)) {
    $savedSymps = array_map('trim', explode(',', $m[1]));
    $detailText = trim($m[2]);
}

/* ── UPDATE ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket = trim($_POST['ticket_number'] ?? '');

    if ($ticket !== $job['ticket_number']) {
        $chk = $pdo->prepare("SELECT id FROM tracking WHERE ticket_number = ? AND id != ?");
        $chk->execute([$ticket, $id]);
        if ($chk->fetch()) $errorMsg = "เลขที่ซ่อม $ticket ซ้ำกับงานอื่น";
    }

    if (!$errorMsg) {
        $cust_name  = trim($_POST['customer_name']  ?? '');
        $cust_phone = trim($_POST['customer_phone'] ?? '');
        $type       = trim($_POST['device_type']    ?? '');
        $series     = trim($_POST['device_series']  ?? '');
        $model_code = trim($_POST['device_model']   ?? '');
        $serial     = trim($_POST['serial_number']  ?? '');
        $pass       = trim($_POST['device_password']?? '');
        $job_date   = !empty($_POST['job_date']) ? $_POST['job_date'] : $job['created_at'];

        $accs_arr = $_POST['items'] ?? [];
        if (!empty($_POST['items_other'])) $accs_arr[] = trim($_POST['items_other']);
        $accs_db = implode(', ', array_filter($accs_arr));

        $state_arr  = $_POST['state'] ?? [];
        $state_str  = $state_arr ? "สภาพ: " . implode(', ', array_filter($state_arr)) : "";
        $note_input = trim($_POST['technician_note'] ?? '');
        $note_db    = $state_str ? $state_str . " | " . $note_input : $note_input;

        $symp_arr    = $_POST['symptoms'] ?? [];
        $prob_detail = trim($_POST['problem_details'] ?? '');
        $prob_header = $symp_arr ? "[ " . implode(', ', array_filter($symp_arr)) . " ] " : "";
        $prob_db     = $prob_header . $prob_detail;

        $cost        = (float)($_POST['estimated_cost']  ?? 0);
        $status      = $_POST['status']           ?? 'QS';
        $app_date    = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
        $pickup_date = !empty($_POST['pickup_date'])      ? $_POST['pickup_date']      : null;
        $updated_by  = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;
        $admin_name  = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? $_SESSION['name'] ?? null;

        try {
            // ── Build diff before overwriting ──
            $watch = [
                'status'           => ['label' => 'สถานะ',      'old' => $job['status'],           'new' => $status],
                'customer_name'    => ['label' => 'ชื่อลูกค้า', 'old' => $job['customer_name'],    'new' => $cust_name],
                'customer_phone'   => ['label' => 'เบอร์',       'old' => $job['customer_phone'],   'new' => $cust_phone],
                'device_type'      => ['label' => 'ประเภท',      'old' => $job['device_type'],      'new' => $type],
                'device_model'     => ['label' => 'รุ่น',        'old' => $job['device_model'],     'new' => $model_code],
                'device_series'    => ['label' => 'Series',      'old' => $job['device_series'] ?? '', 'new' => $series],
                'serial_number'    => ['label' => 'S/N',         'old' => $job['serial_number'],    'new' => $serial],
                'device_password'  => ['label' => 'Password',    'old' => $job['device_password'],  'new' => $pass],
                'estimated_cost'   => ['label' => 'ราคา',        'old' => $job['estimated_cost'],   'new' => $cost],
                'appointment_date' => ['label' => 'นัดหมาย',     'old' => $job['appointment_date'], 'new' => $app_date],
                'pickup_date'      => ['label' => 'รับเครื่องคืน','old'=> $job['pickup_date'],      'new' => $pickup_date],
                'technician_note'  => ['label' => 'หมายเหตุช่าง','old'=> $job['technician_note'],  'new' => $note_db],
                'problem_details'  => ['label' => 'อาการเสีย',  'old' => $job['problem_details'],  'new' => $prob_db],
                'accessories'      => ['label' => 'อุปกรณ์',    'old' => $job['accessories'] ?? '', 'new' => $accs_db],
            ];
            $diff = [];
            foreach ($watch as $key => $v) {
                if ((string)($v['old'] ?? '') !== (string)($v['new'] ?? '')) {
                    $diff[$key] = ['label' => $v['label'], 'from' => $v['old'], 'to' => $v['new']];
                }
            }

            $pdo->prepare("
                UPDATE tracking SET
                    ticket_number = ?, customer_name = ?, customer_phone = ?,
                    device_type = ?, device_model = ?, device_series = ?,
                    serial_number = ?, device_password = ?,
                    problem_details = ?, technician_note = ?, accessories = ?,
                    estimated_cost = ?, appointment_date = ?, pickup_date = ?,
                    status = ?, created_at = ?,
                    updated_at = NOW(), updated_by = ?
                WHERE id = ?
            ")->execute([
                $ticket, $cust_name, $cust_phone,
                $type, $model_code, $series,
                $serial, $pass,
                $prob_db, $note_db, $accs_db,
                $cost, $app_date, $pickup_date,
                $status, $job_date,
                $updated_by, $id
            ]);

            // ── Insert history log ──
            if (!empty($diff)) {
                $pdo->prepare("
                    INSERT INTO tracking_history
                        (tracking_id, changed_by, admin_name,
                         status_old, status_new, cost_old, cost_new,
                         appt_old, appt_new, pickup_old, pickup_new, diff_json)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute([
                    $id, $updated_by, $admin_name,
                    $job['status'],  $status,
                    $job['estimated_cost'], $cost,
                    $job['appointment_date'], $app_date,
                    $job['pickup_date'],      $pickup_date,
                    json_encode($diff, JSON_UNESCAPED_UNICODE)
                ]);
            }

            $_SESSION['success'] = "บันทึกแก้ไขงาน $ticket เรียบร้อย";
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $errorMsg = "Update Error: " . $e->getMessage();
        }
    }
}

/* ── Prepare date values ── */
$appVal    = $job['appointment_date'] ? date('Y-m-d\TH:i', strtotime($job['appointment_date'])) : '';
$pickupVal = !empty($job['pickup_date'])      ? date('Y-m-d\TH:i', strtotime($job['pickup_date']))      : '';

require_once __DIR__ . '/../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/create-style.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/tracking-index.css?v=<?= time() ?>">

<!-- ── Page Header ── -->
<div style="margin-bottom:16px;">
    <a href="index.php" class="cmns-back-link">
        <span class="material-symbols-rounded">arrow_back</span> TRACKING
    </a>
</div>
<div class="cmns-header-bar" style="margin-bottom:20px;">
    <div>
        <h1 class="cmns-page-title" style="color:var(--primary);">
            <span class="material-symbols-rounded" style="font-size:28px;">edit_document</span>
            แก้ไขงานซ่อม
        </h1>
        <p style="color:var(--text-muted); margin-top:5px; font-size:13px;">
            <code style="background:var(--bg-surface-alt,#f1f5f9); padding:2px 8px; border-radius:6px; font-weight:700;"><?= h($job['ticket_number']) ?></code>
            &nbsp;·&nbsp; <?= h($job['customer_name']) ?>
            &nbsp;·&nbsp; <?= h($job['customer_phone']) ?>
        </p>
    </div>
    <div class="cmns-action-buttons">
        <button type="button" id="btnToggleLock" onclick="toggleFormLock()"
                class="cmns-btn cmns-btn-secondary">
            <span class="material-symbols-rounded" id="lockIcon">lock</span>
            <span id="lockLabel">ปลดล็อกแก้ไข</span>
        </button>
        <button type="submit" form="editForm" id="btnSave"
                class="cmns-btn cmns-btn-primary" disabled style="opacity:.45; cursor:not-allowed;">
            <span class="material-symbols-rounded">save</span> บันทึกการแก้ไข
        </button>
    </div>
</div>

<?php if ($errorMsg): ?>
<div style="background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); color:#dc2626; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-weight:600; display:flex; align-items:center; gap:8px; font-size:14px;">
    <span class="material-symbols-rounded" style="font-size:18px;">error</span>
    <?= h($errorMsg) ?>
</div>
<?php endif; ?>

<form method="post" id="editForm">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="form-wrapper">

        <div class="header-scroll-wrapper">
            <div class="paper-header">
                <div class="ph-logo"><img src="/assets/img/Logo1.png" alt="CMNS Logo"></div>
                <div class="ph-center">
                    <h1 class="ph-title">ซ่อม Mac เชียงใหม่ By CMNS</h1>
                    <div class="ph-subtitle">Apple Product Repair Center</div>
                    <div class="ph-address">482 ม.8 วรุณนิเวศน์ ต.แม่เหียะ อ.เมือง จ.เชียงใหม่ 50100</div>
                    <div class="ph-contact"><span><span class="material-symbols-rounded">call</span> 084-151-1684</span></div>
                </div>
                <div class="ph-box">
                    <div class="ph-box-title">เลขที่ซ่อม | Job No.</div>
                    <div class="ph-box-row">
                        <label>No.</label>
                        <input type="text" name="ticket_number" class="input-line-dashed" required value="<?= h($job['ticket_number']) ?>">
                    </div>
                    <div class="ph-box-row">
                        <label>Date.</label>
                        <input type="datetime-local" name="job_date" class="input-line-dashed" required
                               value="<?= date('Y-m-d\TH:i', strtotime($job['created_at'])) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-body form-body-v2">
            <div class="v2-layout-clean">

                <!-- ── Customer ── -->
                <section class="v2-card v2-card--main">
                    <header class="v2-card__hd"><span class="material-symbols-rounded">person</span> ข้อมูลลูกค้า (Customer)</header>
                    <div class="v2-pad">
                        <div class="v2-row v2-row--two">
                            <div class="v2-field">
                                <label class="v2-label">ชื่อลูกค้า *</label>
                                <input type="text" name="customer_name" class="v2-input" required value="<?= h($job['customer_name']) ?>">
                            </div>
                            <div class="v2-field">
                                <label class="v2-label">เบอร์โทรศัพท์ *</label>
                                <input type="tel" name="customer_phone" class="v2-input" required value="<?= h($job['customer_phone']) ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <div class="v2-row2-clean">

                    <!-- ── Device + Price + Status ── -->
                    <section class="v2-card v2-card--main">
                        <header class="v2-card__hd"><span class="material-symbols-rounded">devices</span> ข้อมูลอุปกรณ์ + ราคา + สถานะ</header>
                        <div class="v2-pad">

                            <div class="v2-block">
                                <div class="v2-block__hd">อุปกรณ์</div>
                                <div class="v2-row v2-row--two">
                                    <div class="v2-field">
                                        <label class="v2-label">ประเภทเครื่อง</label>
                                        <select name="device_type" class="v2-input" required>
                                            <option value="" disabled>-- เลือก --</option>
                                            <?php foreach ($deviceList as $prod): ?>
                                                <option value="<?= h($prod) ?>" <?= $job['device_type'] == $prod ? 'selected' : '' ?>><?= h($prod) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="v2-field">
                                        <label class="v2-label">Model Code (รุ่น) *</label>
                                        <input type="text" name="device_model" class="v2-input" required value="<?= h($job['device_model']) ?>">
                                    </div>
                                </div>
                                <div class="v2-row v2-row--two">
                                    <div class="v2-field">
                                        <label class="v2-label">Series / Year</label>
                                        <input type="text" name="device_series" class="v2-input" value="<?= h($job['device_series'] ?? '') ?>">
                                    </div>
                                    <div class="v2-field">
                                        <label class="v2-label">Serial No.</label>
                                        <input type="text" name="serial_number" class="v2-input" value="<?= h($job['serial_number']) ?>">
                                    </div>
                                </div>
                                <div class="v2-row v2-row--two">
                                    <div class="v2-field">
                                        <label class="v2-label v2-label--danger">Password</label>
                                        <input type="text" name="device_password" class="v2-input v2-input--danger" value="<?= h($job['device_password']) ?>">
                                    </div>
                                    <div class="v2-field">
                                        <label class="v2-label">หมายเหตุราคา</label>
                                        <input type="text" name="price_note" class="v2-input" placeholder="Note เพิ่มเติม">
                                    </div>
                                </div>
                            </div>

                            <div class="v2-block v2-block--price">
                                <div class="v2-block__hd">ราคา</div>
                                <div class="v2-price-grid">
                                    <div class="v2-field">
                                        <label class="v2-label">ราคาประเมิน (บาท)</label>
                                        <input type="number" name="estimated_cost" class="v2-input v2-input--price" value="<?= (float)$job['estimated_cost'] ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="v2-divider"></div>

                            <!-- ── Dates ── -->
                            <div class="v2-block">
                                <div class="v2-block__hd">
                                    <span class="material-symbols-rounded" style="font-size:18px; vertical-align:-3px; margin-right:4px;">event</span>
                                    นัดรับ / แจ้งผล
                                </div>
                                <div class="v2-row v2-row--two">
                                    <div class="v2-field">
                                        <label class="v2-label">อีก (วัน)</label>
                                        <input type="number" id="daysToFinish" class="v2-input" placeholder="0" min="0" oninput="calcWorkDate()">
                                    </div>
                                    <div class="v2-field">
                                        <label class="v2-label">วันที่นัดหมาย (แจ้งผล)</label>
                                        <input type="datetime-local" name="appointment_date" id="appDateInput"
                                               class="v2-input" value="<?= $appVal ?>" oninput="calcDaysFromDate()">
                                    </div>
                                </div>
                                <div class="v2-row" style="grid-template-columns:1fr; margin-top:4px;">
                                    <div class="v2-field">
                                        <label class="v2-label" style="display:flex; align-items:center; gap:5px;">
                                            <span class="material-symbols-rounded" style="font-size:15px; color:#10b981;">check_circle</span>
                                            วันที่ลูกค้ารับเครื่องคืน
                                        </label>
                                        <input type="datetime-local" name="pickup_date" class="v2-input"
                                               value="<?= $pickupVal ?>" style="border-color:#a7f3d0;">
                                    </div>
                                </div>
                            </div>

                            <div class="v2-divider"></div>

                            <!-- ── Status ── -->
                            <div class="v2-block">
                                <div class="v2-block__hd">
                                    <span class="material-symbols-rounded" style="font-size:18px; vertical-align:-3px; margin-right:4px;">flag</span>
                                    สถานะงาน (Job Status)
                                </div>
                                <div class="v2-checkgrid" style="grid-template-columns:repeat(5,1fr); gap:10px;">
                                    <?php foreach ($statusList as $code => $label): ?>
                                        <label class="v2-check">
                                            <input type="radio" name="status" value="<?= $code ?>" <?= $job['status'] == $code ? 'checked' : '' ?>>
                                            <span style="font-size:.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= h($label) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>
                    </section>

                    <!-- ── Checklist ── -->
                    <section class="v2-card v2-card--main">
                        <header class="v2-card__hd"><span class="material-symbols-rounded">fact_check</span> ตรวจรับเครื่อง (Checklist)</header>
                        <div class="v2-pad">

                            <div class="v2-block">
                                <div class="v2-block__hd">สิ่งที่นำมา (Accessories)</div>
                                <div class="v2-checkgrid v2-checkgrid--tight">
                                    <?php foreach ($accsList as $i): $ii = h($i); $chk = in_array($i, $savedAccs) ? 'checked' : ''; ?>
                                        <label class="v2-check"><input type="checkbox" name="items[]" value="<?= $ii ?>" <?= $chk ?>> <span><?= $ii ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                                <input type="text" name="items_other" class="v2-input v2-input--sm" placeholder="อื่นๆ..." value="<?= h($otherAccs) ?>">
                            </div>

                            <div class="v2-divider"></div>

                            <div class="v2-block">
                                <div class="v2-block__hd">สภาพเครื่อง / หมายเหตุช่าง</div>
                                <div class="v2-checkgrid v2-checkgrid--tight" style="margin-bottom:10px;">
                                    <?php foreach ($stateList as $s): $ss = h($s); ?>
                                        <label class="v2-check"><input type="checkbox" name="state[]" value="<?= $ss ?>"> <span><?= $ss ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                                <textarea name="technician_note" class="v2-input" rows="3"
                                          placeholder="ระบุสภาพเครื่อง หรือ หมายเหตุเพิ่มเติม..."><?= h($job['technician_note']) ?></textarea>
                            </div>

                            <div class="v2-divider"></div>

                            <div class="v2-block v2-block--warn">
                                <div class="v2-block__hd">อาการเสีย (Symptoms) <span class="v2-req">*</span></div>
                                <div class="v2-checkgrid v2-checkgrid--wide v2-checkgrid--tight">
                                    <?php foreach ($sympsList as $sy): $syy = h($sy); $chk = in_array($sy, $savedSymps) ? 'checked' : ''; ?>
                                        <label class="v2-check"><input type="checkbox" name="symptoms[]" value="<?= $syy ?>" <?= $chk ?>> <span><?= $syy ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mb-0 d-flex flex-column flex-grow-1" style="margin-top:10px;">
                                    <label class="v2-label fw-bold">
                                        <span class="material-symbols-rounded" style="font-size:18px; color:#6b7280; vertical-align:-4px; margin-right:6px;">edit_note</span>
                                        รายละเอียดอาการเสีย
                                    </label>
                                    <textarea name="problem_details" id="editorSymptoms"><?= h($detailText) ?></textarea>
                                </div>
                            </div>

                        </div>
                    </section>

                </div><!-- .v2-row2-clean -->
            </div><!-- .v2-layout-clean -->
        </div><!-- .form-body -->

        <!-- ── Footer Actions ── -->
        <div class="footer-actions" style="justify-content:flex-end; gap:10px;">
            <a href="index.php" class="cmns-btn cmns-btn-secondary">
                <span class="material-symbols-rounded">close</span> ยกเลิก
            </a>
            <a href="../warranty/job_form.php?job_id=<?= $id ?>" target="_blank"
               class="cmns-btn cmns-btn-warranty">
                <span class="material-symbols-rounded">verified_user</span> เพิ่มประกัน
            </a>
            <button type="submit" id="btnSaveFooter" class="cmns-btn cmns-btn-primary"
                    disabled style="opacity:.45; cursor:not-allowed;">
                <span class="material-symbols-rounded">save</span> บันทึกการแก้ไข
            </button>
        </div>

    </div><!-- .form-wrapper -->
</form>

<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script>
let myEditor;
let isLocked = true;

function setFormState(locked) {
    isLocked = locked;

    // ── Header button ──
    const btn   = document.getElementById('btnToggleLock');
    const icon  = document.getElementById('lockIcon');
    const label = document.getElementById('lockLabel');

    if (locked) {
        icon.textContent  = 'lock';
        label.textContent = 'ปลดล็อกแก้ไข';
        btn.style.borderColor = '';
        btn.style.color       = '';
    } else {
        icon.textContent  = 'lock_open';
        label.textContent = 'ล็อกการแก้ไข';
        btn.style.borderColor = '#ef4444';
        btn.style.color       = '#ef4444';
    }

    // ── Save buttons ──
    ['btnSave','btnSaveFooter'].forEach(bid => {
        const el = document.getElementById(bid);
        if (!el) return;
        el.disabled          = locked;
        el.style.opacity     = locked ? '.45' : '1';
        el.style.cursor      = locked ? 'not-allowed' : 'pointer';
    });

    // ── Form inputs ──
    document.querySelectorAll('#editForm input:not([type="hidden"]), #editForm select, #editForm textarea')
        .forEach(el => { if (el.id !== 'editorSymptoms') el.disabled = locked; });

    // ── CKEditor ──
    if (myEditor) {
        locked ? myEditor.enableReadOnlyMode('lock') : myEditor.disableReadOnlyMode('lock');
    }
}

function toggleFormLock() { setFormState(!isLocked); }

function calcWorkDate() {
    const dInput = document.getElementById('daysToFinish');
    const tInput = document.getElementById('appDateInput');
    const days   = parseInt(dInput?.value ?? '', 10);
    if (isNaN(days) || days < 0 || !tInput) return;
    const d = new Date(); let added = 0;
    while (added < days) { d.setDate(d.getDate() + 1); if (d.getDay() !== 0) added++; }
    updateDateInput(tInput, d);
}
function calcDaysFromDate() {
    const dInput = document.getElementById('daysToFinish');
    const tInput = document.getElementById('appDateInput');
    if (!tInput?.value || !dInput) return;
    const target = new Date(tInput.value);
    const today  = new Date();
    target.setHours(0,0,0,0); today.setHours(0,0,0,0);
    if (target < today) { dInput.value = 0; return; }
    let count = 0; const tmp = new Date(today);
    while (tmp < target) { tmp.setDate(tmp.getDate() + 1); if (tmp.getDay() !== 0) count++; }
    dInput.value = count;
}
function updateDateInput(input, date) {
    const pad = n => String(n).padStart(2,'0');
    input.value = `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

window.addEventListener('load', function() {
    calcDaysFromDate();
    const el = document.querySelector('#editorSymptoms');
    if (el && typeof ClassicEditor !== 'undefined') {
        ClassicEditor.create(el, {
            toolbar: ['undo','redo','|','heading','|','bold','italic','link','bulletedList','numberedList','|','removeFormat'],
            shouldNotGroupWhenFull: true
        }).then(editor => {
            myEditor = editor;
            setFormState(true);
        }).catch(console.error);
    } else {
        setFormState(true);
    }
});
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
