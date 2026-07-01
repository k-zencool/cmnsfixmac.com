<?php
/********************************************************************
 * admin/tracking/edit.php  –  Edit Repair Job
 ********************************************************************/

session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_perms(['jobs.write']); // แก้งานซ่อม: ช่าง+ ขึ้นไป (ยกเว้นบัญชี)

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errorMsg = '';

$stmt = $pdo->prepare("SELECT * FROM tracking WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$job) die("ไม่พบข้อมูลงานซ่อม (Job Not Found)");

// ดึงใบประกันที่ผูกกับงานนี้
$wStmt = $pdo->prepare("SELECT id, warranty_no, end_date, status FROM warranties WHERE tracking_id = ? ORDER BY id DESC");
$wStmt->execute([$id]);
$linkedWarranties = $wStmt->fetchAll(PDO::FETCH_ASSOC);

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
<link rel="stylesheet" href="../templates/assets/css/modal.css?v=1">
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
        <div class="footer-actions" style="justify-content:space-between; gap:10px; flex-wrap:wrap;">

            <!-- ใบประกันที่ผูกกับงานนี้ -->
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <?php foreach ($linkedWarranties as $lw):
                    $wCls = $lw['status'] === 'active' ? 'color:#059669;background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);'
                          : ($lw['status'] === 'voided' ? 'color:#dc2626;background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.2);'
                          : 'color:var(--text-muted);background:var(--bg-surface-alt);border-color:var(--border);');
                    $wIcon = $lw['status'] === 'active' ? 'verified' : ($lw['status'] === 'voided' ? 'block' : 'schedule');
                ?>
                <a href="../warranty/view.php?id=<?= $lw['id'] ?>" target="_blank"
                   class="cmns-btn" style="<?= $wCls ?> border:1px solid; font-size:0.82rem; padding:6px 12px;">
                    <span class="material-symbols-rounded" style="font-size:15px;"><?= $wIcon ?></span>
                    <?= h($lw['warranty_no']) ?>
                    <span style="opacity:.65; font-size:0.75rem;">(หมด <?= date('d/m/y', strtotime($lw['end_date'])) ?>)</span>
                </a>
                <?php endforeach; ?>
            </div>

            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <a href="index.php" class="cmns-btn cmns-btn-secondary">
                <span class="material-symbols-rounded">close</span> ยกเลิก
            </a>
            <button type="button" onclick="openWarrantyModal()"
               class="cmns-btn" style="background:rgba(245,158,11,.12);color:#b45309;border:1px solid rgba(245,158,11,.4);">
                <span class="material-symbols-rounded">verified_user</span> ออกใบประกัน
            </button>
            <button type="submit" id="btnSaveFooter" class="cmns-btn cmns-btn-primary"
                    disabled style="opacity:.45; cursor:not-allowed;">
                <span class="material-symbols-rounded">save</span> บันทึกการแก้ไข
            </button>
            </div><!-- right actions -->
        </div><!-- footer-actions -->

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

<!-- ══════════════════════════════════════════════
     WARRANTY MODAL
════════════════════════════════════════════════ -->
<div id="modal-warranty" class="cmns-modal">
    <div class="modal-content" style="max-width:600px; padding:30px;">

        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:18px; margin-bottom:22px;">
            <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-weight:800; font-size:1.15rem;">
                <span class="material-symbols-rounded" style="color:#b45309; font-size:26px;">verified_user</span>
                ออกใบรับประกัน
            </h3>
            <button class="modal-close-btn" onclick="closeWarrantyModal()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>

        <!-- Success state (hidden until after submit) -->
        <div id="war-modal-success" style="display:none; text-align:center; padding:20px 0;">
            <span class="material-symbols-rounded" style="font-size:56px; color:#059669;">verified</span>
            <h4 id="war-modal-no" style="font-size:1.4rem; font-weight:900; font-family:monospace; color:var(--primary); margin:10px 0 6px;"></h4>
            <p style="color:var(--text-muted); margin-bottom:22px;">ออกใบประกันเรียบร้อยแล้ว</p>
            <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                <a id="war-btn-view" href="#" class="cmns-btn cmns-btn-primary">
                    <span class="material-symbols-rounded">visibility</span> ดูใบประกัน
                </a>
                <a id="war-btn-print" href="#" target="_blank" class="cmns-btn cmns-btn-secondary">
                    <span class="material-symbols-rounded">print</span> พิมพ์
                </a>
                <button onclick="closeWarrantyModal()" class="cmns-btn cmns-btn-secondary">ปิด</button>
            </div>
        </div>

        <!-- Form state -->
        <div id="war-modal-form">
            <div id="war-modal-err" style="display:none; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; color:#dc2626; font-size:0.88rem; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-rounded" style="font-size:18px;">error</span>
                <span id="war-modal-err-txt"></span>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label class="cmns-label">ชื่อลูกค้า <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="wm-cname" class="cmns-input" required>
                </div>
                <div>
                    <label class="cmns-label">เบอร์โทร</label>
                    <input type="text" id="wm-cphone" class="cmns-input">
                </div>
                <div>
                    <label class="cmns-label">รุ่นเครื่อง <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="wm-device" class="cmns-input" required>
                </div>
                <div>
                    <label class="cmns-label">Serial Number</label>
                    <input type="text" id="wm-serial" class="cmns-input">
                </div>
                <div style="grid-column:1/-1;">
                    <label class="cmns-label">สรุปงานที่ซ่อม</label>
                    <textarea id="wm-summary" class="cmns-input" rows="2" placeholder="เช่น เปลี่ยนแบตเตอรี่ / ซ่อมจอ..."></textarea>
                </div>
            </div>

            <!-- Warranty days -->
            <div style="margin-top:18px;">
                <label class="cmns-label" style="margin-bottom:10px; display:block;">ระยะเวลารับประกัน</label>
                <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:8px;">
                    <?php foreach ([30=>'1 เดือน',60=>'2 เดือน',90=>'3 เดือน',180=>'6 เดือน',365=>'1 ปี'] as $d=>$lbl): ?>
                    <div>
                        <input type="radio" name="wm_days" id="wmd_<?= $d ?>" value="<?= $d ?>" <?= $d===90?'checked':'' ?>
                               style="display:none;" onchange="wmRecalc()">
                        <label for="wmd_<?= $d ?>" class="wm-days-lbl">
                            <?= $d ?><span style="display:block;font-size:0.7rem;font-weight:400;opacity:.7;"><?= $lbl ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Dates -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:16px;">
                <div>
                    <label class="cmns-label">วันที่เริ่มประกัน</label>
                    <input type="date" id="wm-start" class="cmns-input" value="<?= date('Y-m-d') ?>" onchange="wmRecalc()">
                </div>
                <div>
                    <label class="cmns-label">วันหมดประกัน</label>
                    <input type="text" id="wm-end-disp" class="cmns-input" readonly style="background:var(--bg-surface-alt); color:var(--text-muted);">
                </div>
            </div>

            <!-- Submit -->
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:22px; border-top:1px solid var(--border); padding-top:18px;">
                <button type="button" onclick="closeWarrantyModal()" class="cmns-btn cmns-btn-secondary">ยกเลิก</button>
                <button type="button" onclick="submitWarranty()" id="wm-submit-btn" class="cmns-btn" style="background:rgba(245,158,11,.15);color:#b45309;border:1px solid rgba(245,158,11,.4);">
                    <span class="material-symbols-rounded">verified_user</span> ออกใบประกัน
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.cmns-label { font-size:11px; font-weight:800; color:var(--text-muted); margin-bottom:6px; display:block; text-transform:uppercase; letter-spacing:.5px; }
.cmns-input { width:100%; background:var(--bg-surface-alt); border:1px solid var(--border); color:var(--text-main); padding:11px 13px; border-radius:10px; font-size:13px; outline:none; transition:all .2s; font-family:inherit; }
.cmns-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); background:var(--bg-surface); }
textarea.cmns-input { resize:vertical; min-height:72px; }
.wm-days-lbl {
    display: block;
    padding: 9px 6px;
    border: 2px solid var(--border);
    border-radius: 10px;
    text-align: center;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 700;
    transition: .15s;
}
input[name="wm_days"]:checked + .wm-days-lbl {
    border-color: #f59e0b;
    background: rgba(245,158,11,.1);
    color: #b45309;
}
.wm-days-lbl:hover { border-color: #f59e0b; }
</style>

<script>
// ── Pre-fill data from current job ──
const wmJobData = {
    tracking_id:    <?= (int)$id ?>,
    customer_name:  <?= json_encode($job['customer_name'] ?? '') ?>,
    customer_phone: <?= json_encode($job['customer_phone'] ?? '') ?>,
    device_model:   <?= json_encode(trim(($job['device_type'] ?? '') . ' ' . ($job['device_model'] ?? ''))) ?>,
    serial_no:      <?= json_encode($job['serial_number'] ?? '') ?>,
};

function openWarrantyModal() {
    document.getElementById('wm-cname').value  = wmJobData.customer_name;
    document.getElementById('wm-cphone').value = wmJobData.customer_phone;
    document.getElementById('wm-device').value = wmJobData.device_model;
    document.getElementById('wm-serial').value = wmJobData.serial_no;
    document.getElementById('wm-summary').value = '';
    document.getElementById('war-modal-form').style.display = '';
    document.getElementById('war-modal-success').style.display = 'none';
    document.getElementById('war-modal-err').style.display = 'none';
    wmRecalc();
    document.getElementById('modal-warranty').classList.add('show');
}

function closeWarrantyModal() {
    document.getElementById('modal-warranty').classList.remove('show');
}

function wmRecalc() {
    const start = document.getElementById('wm-start').value;
    const days  = parseInt(document.querySelector('input[name="wm_days"]:checked')?.value || 90);
    if (!start) return;
    const d = new Date(start);
    d.setDate(d.getDate() + days);
    document.getElementById('wm-end-disp').value = d.toLocaleDateString('th-TH', {day:'2-digit',month:'2-digit',year:'numeric'});
}

async function submitWarranty() {
    const btn = document.getElementById('wm-submit-btn');
    const errBox = document.getElementById('war-modal-err');
    errBox.style.display = 'none';

    const cname  = document.getElementById('wm-cname').value.trim();
    const device = document.getElementById('wm-device').value.trim();
    if (!cname || !device) {
        document.getElementById('war-modal-err-txt').textContent = 'กรุณากรอกชื่อลูกค้าและรุ่นเครื่อง';
        errBox.style.display = 'flex';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="animation:spin 1s linear infinite;">sync</span> กำลังออกใบ...';

    const body = new URLSearchParams({
        action:         'create_warranty',
        tracking_id:    wmJobData.tracking_id,
        customer_name:  cname,
        customer_phone: document.getElementById('wm-cphone').value.trim(),
        device_model:   device,
        serial_no:      document.getElementById('wm-serial').value.trim(),
        repair_summary: document.getElementById('wm-summary').value.trim(),
        warranty_days:  document.querySelector('input[name="wm_days"]:checked')?.value || 90,
        start_date:     document.getElementById('wm-start').value,
    });

    try {
        const res  = await fetch('/admin/warranty/ajax.php', {method:'POST', body});
        const data = await res.json();
        if (data.ok) {
            document.getElementById('war-modal-form').style.display = 'none';
            document.getElementById('war-modal-no').textContent = data.warranty_no;
            document.getElementById('war-btn-view').href  = data.view_url;
            document.getElementById('war-btn-print').href = data.print_url;
            document.getElementById('war-modal-success').style.display = '';
        } else {
            document.getElementById('war-modal-err-txt').textContent = data.msg || 'เกิดข้อผิดพลาด';
            errBox.style.display = 'flex';
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">verified_user</span> ออกใบประกัน';
        }
    } catch(e) {
        document.getElementById('war-modal-err-txt').textContent = 'ไม่สามารถเชื่อมต่อได้';
        errBox.style.display = 'flex';
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded">verified_user</span> ออกใบประกัน';
    }
}

// Close on backdrop click
document.getElementById('modal-warranty').addEventListener('click', function(e) {
    if (e.target === this) closeWarrantyModal();
});

// Spin animation for loading state
const spinStyle = document.createElement('style');
spinStyle.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(spinStyle);

wmRecalc();
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
