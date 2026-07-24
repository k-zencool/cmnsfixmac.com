<?php
/********************************************************************
 * admin/tracking/edit.php  –  Edit Repair Job (v3 redesign)
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

/* value => material icon (values unchanged — saved to DB as-is) */
$deviceList = [
    'iPhone'      => 'phone_iphone',
    'iPad'        => 'tablet_mac',
    'MacBook'     => 'laptop_mac',
    'iMac'        => 'desktop_mac',
    'Notebook'    => 'laptop_windows',
    'PC'          => 'computer',
    'Mac mini'    => 'dns',
    'Mac Studio'  => 'device_hub',
    'Mac Pro'     => 'memory',
    'Apple Watch' => 'watch',
    'AirPods'     => 'headphones',
    'Apple TV'    => 'tv',
    'Other'       => 'more_horiz',
];
$accsList = [
    'ตัวเครื่อง' => 'devices',
    'Adapter'    => 'power',
    'สายชาร์จ'   => 'cable',
    'กระเป๋า'    => 'business_center',
    'Soft Case'  => 'cases',
    'กล่อง'      => 'inventory_2',
    'Mouse'      => 'mouse',
    'Keyboard'   => 'keyboard',
];
$stateList = [
    'ปกติ/สวย'                => 'verified',
    'รอยขีดข่วน'              => 'draw',
    'รอยบุบ/ตก'               => 'warning',
    'น็อตหาย'                 => 'construction',
    'เคยแกะซ่อม'              => 'home_repair_service',
    'แบตบวม'                  => 'battery_alert',
    'โดนน้ำ'                  => 'water_drop',
    'เครื่องประกอบไม่สมบูรณ์' => 'rule',
];
$sympsList = [
    'ไฟเข้าเปิดไม่ติด'   => 'power',
    'ไฟไม่เข้าเปิดไม่ติด' => 'power_off',
    'จอแตก/เสีย'         => 'broken_image',
    'แบตเสื่อม'          => 'battery_alert',
    'คีย์บอร์ดเสีย'      => 'keyboard',
    'Trackpadเสีย'       => 'touch_app',
    'Wifi/BT เสีย'       => 'wifi_off',
    'ลงโปรแกรม'          => 'install_desktop',
    'ชาร์จไม่เข้า'        => 'bolt',
    'windows/os'         => 'desktop_windows',
];
$statusList = [
    'QS'  => ['รอเช็คราคา',          '#f59e0b'],
    'WC'  => ['รอคอนเฟิร์ม',         '#3b82f6'],
    'OK'  => ['กำลังซ่อม',           '#8b5cf6'],
    'RW'  => ['งานแก้/เคลม',         '#ef4444'],
    'FN'  => ['ซ่อมเสร็จ (รอรับ)',   '#10b981'],
    'DV'  => ['ส่งมอบแล้ว',          '#6b7280'],
    'NCF' => ['ติดต่อไม่ได้ (เสร็จ)', '#6b7280'],
    'NCS' => ['ติดต่อไม่ได้ (เสนอ)',  '#6b7280'],
    'XX'  => ['ยกเลิก',              '#ef4444'],
    'RT'  => ['รับคืนแล้ว',          '#6b7280'],
];

function getCheckedItems($s) {
    if (!$s) return [];
    return array_map('trim', explode(',', $s));
}
function getOtherText($s, $list) {
    return implode(', ', array_diff(getCheckedItems($s), $list));
}

$savedAccs  = getCheckedItems($job['accessories'] ?? '');
$otherAccs  = getOtherText($job['accessories'] ?? '', array_keys($accsList));

$savedSymps = [];
$detailText = $job['problem_details'];
if (preg_match('/^\[(.*?)\](.*)/s', $job['problem_details'], $m)) {
    $savedSymps = array_map('trim', explode(',', $m[1]));
    $detailText = trim($m[2]);
}
// legacy rows were written by CKEditor — flatten the HTML into plain text
$detailText = trim(strip_tags(preg_replace('/<\/(p|div|li)>|<br\s*\/?>/i', "\n", $detailText)));

// parse technician_note back into state chips + clean note
// formats in DB: "สภาพ: a, b | Note: xxx" (create) or "สภาพ: a, b | xxx" (edit)
$savedStates = [];
$noteText    = $job['technician_note'] ?? '';
if (preg_match('/^สภาพ:\s*([^|]*)(?:\|\s*(?:Note:\s*)?(.*))?$/su', $noteText, $m)) {
    $savedStates = array_map('trim', explode(',', $m[1]));
    $noteText    = trim($m[2] ?? '');
}
$otherState = implode(', ', array_diff(array_filter($savedStates), array_keys($stateList)));

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
        if (!empty($_POST['state_other'])) $state_arr[] = trim($_POST['state_other']);
        $state_str  = $state_arr ? "สภาพ: " . implode(', ', array_filter($state_arr)) : "";
        $note_input = trim($_POST['technician_note'] ?? '');
        $note_db    = $state_str ? $state_str . " | " . $note_input : $note_input;

        $symp_arr    = $_POST['symptoms'] ?? [];
        $prob_detail = trim($_POST['problem_details'] ?? '');
        $prob_header = $symp_arr ? "[ " . implode(', ', array_filter($symp_arr)) . " ] " : "";
        $prob_db     = $prob_header . $prob_detail;

        $cost        = (float)($_POST['estimated_cost']  ?? 0);
        $status      = array_key_exists($_POST['status'] ?? '', $statusList) ? $_POST['status'] : 'QS';
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

            // ── LINE alert: การ์ดอัปเดตงาน (เฉพาะมีการเปลี่ยนแปลงจริง; fail-safe) ──
            if (!empty($diff)) {
                try {
                    require_once __DIR__ . '/../../includes/line_helper.php';

                    $fmtVal = function ($key, $v) {
                        if ($v === null || $v === '') return '—';
                        if ($key === 'status')          return line_tracking_status($v)['label'];
                        if ($key === 'estimated_cost')  return '฿' . number_format((float)$v);
                        if (in_array($key, ['appointment_date', 'pickup_date'], true)) return date('d/m/y', strtotime($v));
                        return mb_strimwidth(trim(strip_tags((string)$v)), 0, 30, '…');
                    };

                    $rows = [
                        ['label' => 'ลูกค้า',  'value' => $cust_name],
                        ['label' => 'เครื่อง', 'value' => trim("$type $model_code")],
                    ];
                    // สถานะขึ้นก่อนถ้าเปลี่ยน — ตัวหนา + สีของสถานะใหม่
                    if (isset($diff['status'])) {
                        $stMeta = line_tracking_status($status);
                        $rows[] = ['label' => 'สถานะ',
                                   'value' => $fmtVal('status', $job['status']) . ' → ' . $stMeta['label'],
                                   'color' => $stMeta['color'], 'bold' => true];
                    }
                    // ที่เหลือ (จำกัด 5 รายการ กันการ์ดยาว)
                    $n = 0;
                    foreach ($diff as $key => $d) {
                        if ($key === 'status') continue;
                        if (++$n > 5) break;
                        $rows[] = ['label' => $d['label'],
                                   'value' => $fmtVal($key, $d['from']) . ' → ' . $fmtVal($key, $d['to'])];
                    }

                    $editUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'cmnsfixmac.com') . '/admin/tracking/edit.php?id=' . $id;
                    line_job_notify($pdo, line_job_flex(
                        'อัปเดตงานซ่อม', $ticket,
                        'โดย ' . ($admin_name ?: '—') . ' · ' . date('d/m/Y H:i') . ' น.',
                        $rows, $editUrl, '#0ea5e9'
                    ));
                    // แจ้งเตือนผ่านแอป (Web Push — ฟรี ไม่มีโควต้า) คู่กับ LINE
                    require_once __DIR__ . '/../../includes/push_helper.php';
                    $pushBody = isset($diff['status'])
                        ? 'สถานะ: ' . $fmtVal('status', $job['status']) . ' → ' . line_tracking_status($status)['label']
                        : 'แก้ไข ' . count($diff) . ' รายการ';
                    push_job_notify($pdo,
                        "✏️ อัปเดตงาน $ticket",
                        $pushBody . ' · โดย ' . ($admin_name ?: '—'),
                        '/admin/tracking/edit.php?id=' . $id);
                } catch (Throwable $e) { /* ignore */ }
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
$appVal    = $job['appointment_date'] ? date('Y-m-d', strtotime($job['appointment_date'])) : '';
$pickupVal = !empty($job['pickup_date']) ? date('Y-m-d\TH:i', strtotime($job['pickup_date'])) : '';

/* ── Edit history for this job (aside panel, desktop only) ── */
$histRows = [];
try {
    $hStmt = $pdo->prepare("
        SELECT admin_name, status_old, status_new, diff_json, changed_at
        FROM tracking_history WHERE tracking_id = ?
        ORDER BY id DESC LIMIT 12
    ");
    $hStmt->execute([$id]);
    $histRows = $hStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* aside only — ignore */ }

require_once __DIR__ . '/../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="../templates/assets/css/modal.css?v=1">
<link rel="stylesheet" href="assets/css/create-v3.css?v=<?= time() ?>">

<div class="cr3-wrap">

    <!-- ── Page header ── -->
    <div class="cr3-topbar">
        <a href="index.php" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> TRACKING
        </a>
        <div class="cr3e-titlebar">
            <h1 class="cr3-title">
                <span class="material-symbols-rounded">edit_document</span> แก้ไขงานซ่อม
                <code class="cr3e-jobcode"><?= h($job['ticket_number']) ?></code>
            </h1>
            <p class="cr3e-subline"><?= h($job['customer_name']) ?> · <?= h($job['customer_phone']) ?></p>
        </div>
    </div>

    <?php if ($errorMsg): ?>
    <div class="cr3-alert">
        <span class="material-symbols-rounded">error</span>
        <?= h($errorMsg) ?>
    </div>
    <?php endif; ?>

    <form method="post" id="editForm" class="cr3-form">
      <div class="cr3-main">

        <!-- ── Job slip: no. + received date ── -->
        <section class="cr3-card cr3-job">
            <div class="cr3-job-badge">
                <span class="material-symbols-rounded">receipt_long</span> ใบรับซ่อม · JOB SLIP
            </div>
            <div class="cr3-job-grid">
                <div class="cr3-job-logo">
                    <img src="/assets/img/Logo1.png" alt="CMNS FixMac">
                </div>
                <div class="cr3-field">
                    <label class="cr3-label" for="ticketInput">เลขที่ซ่อม (Job No.) <b class="cr3-req">*</b></label>
                    <input type="text" name="ticket_number" id="ticketInput" class="cr3-input cr3-input-ticket"
                           value="<?= h($job['ticket_number']) ?>" required>
                </div>
                <div class="cr3-field">
                    <label class="cr3-label" for="jobDate">วันที่รับเครื่อง</label>
                    <input type="datetime-local" name="job_date" id="jobDate" class="cr3-input"
                           value="<?= date('Y-m-d\TH:i', strtotime($job['created_at'])) ?>" required>
                </div>
            </div>
        </section>

        <div class="cr3-cols">
            <div class="cr3-col">

                <!-- ── Customer ── -->
                <section class="cr3-card">
                    <header class="cr3-hd cr3-hd-blue">
                        <span class="cr3-hd-ico material-symbols-rounded">person</span>
                        <div class="cr3-hd-txt">
                            <div class="cr3-hd-title">ข้อมูลลูกค้า</div>
                            <div class="cr3-hd-sub">ชื่อและเบอร์ติดต่อกลับ</div>
                        </div>
                        <span class="cr3-step">1</span>
                    </header>
                    <div class="cr3-body">
                        <div class="cr3-grid2">
                            <div class="cr3-field">
                                <label class="cr3-label">ชื่อลูกค้า <b class="cr3-req">*</b></label>
                                <input type="text" name="customer_name" class="cr3-input" value="<?= h($job['customer_name']) ?>" required>
                            </div>
                            <div class="cr3-field">
                                <label class="cr3-label">เบอร์โทรศัพท์ <b class="cr3-req">*</b></label>
                                <input type="tel" name="customer_phone" inputmode="tel" class="cr3-input"
                                       value="<?= h($job['customer_phone']) ?>" required>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ── Device ── -->
                <section class="cr3-card">
                    <header class="cr3-hd cr3-hd-violet">
                        <span class="cr3-hd-ico material-symbols-rounded">devices</span>
                        <div class="cr3-hd-txt">
                            <div class="cr3-hd-title">ข้อมูลอุปกรณ์</div>
                            <div class="cr3-hd-sub">ประเภท รุ่น Serial และรหัสเครื่อง</div>
                        </div>
                        <span class="cr3-step">2</span>
                    </header>
                    <div class="cr3-body">

                        <div class="cr3-field">
                            <label class="cr3-label">ประเภทเครื่อง</label>
                            <div class="cr3-chips">
                                <?php $first = true; foreach ($deviceList as $prod => $ico): $pp = h($prod); ?>
                                <label class="cr3-chip">
                                    <input type="radio" name="device_type" value="<?= $pp ?>"
                                           <?= $job['device_type'] === $prod ? 'checked' : '' ?> <?= $first ? 'required' : '' ?>>
                                    <span><i class="material-symbols-rounded cr3-chip-ico"><?= $ico ?></i><?= $pp ?></span>
                                </label>
                                <?php $first = false; endforeach; ?>
                            </div>
                        </div>

                        <div class="cr3-grid2">
                            <div class="cr3-field">
                                <label class="cr3-label">Model Code (รุ่น) <b class="cr3-req">*</b></label>
                                <input type="text" name="device_model" class="cr3-input" value="<?= h($job['device_model']) ?>" required>
                            </div>
                            <div class="cr3-field">
                                <label class="cr3-label">Series / Year</label>
                                <input type="text" name="device_series" class="cr3-input" value="<?= h($job['device_series'] ?? '') ?>" placeholder="เช่น Pro M1 2020">
                            </div>
                        </div>
                        <div class="cr3-grid2">
                            <div class="cr3-field">
                                <label class="cr3-label">Serial No.</label>
                                <input type="text" name="serial_number" class="cr3-input" value="<?= h($job['serial_number']) ?>" placeholder="S/N">
                            </div>
                            <div class="cr3-field">
                                <label class="cr3-label cr3-label-danger">Password (รหัสเครื่อง)</label>
                                <input type="text" name="device_password" class="cr3-input cr3-input-danger"
                                       value="<?= h($job['device_password']) ?>" placeholder="จำเป็นต้องขอเพื่อเทสเครื่อง">
                            </div>
                        </div>

                    </div>
                </section>

                <!-- ── Symptoms ── -->
                <section class="cr3-card">
                    <header class="cr3-hd cr3-hd-red">
                        <span class="cr3-hd-ico material-symbols-rounded">report</span>
                        <div class="cr3-hd-txt">
                            <div class="cr3-hd-title">อาการเสีย</div>
                            <div class="cr3-hd-sub">เลือกอาการ + รายละเอียดเพิ่มเติม</div>
                        </div>
                        <span class="cr3-step">3</span>
                    </header>
                    <div class="cr3-body">
                        <div class="cr3-chips">
                            <?php foreach ($sympsList as $sy => $ico): $syy = h($sy); ?>
                            <label class="cr3-chip cr3-chip-red">
                                <input type="checkbox" name="symptoms[]" value="<?= $syy ?>" <?= in_array($sy, $savedSymps, true) ? 'checked' : '' ?>>
                                <span><i class="material-symbols-rounded cr3-chip-ico"><?= $ico ?></i><?= $syy ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="cr3-field" style="margin-top:12px;">
                            <label class="cr3-label">รายละเอียดอาการเสีย</label>
                            <textarea name="problem_details" class="cr3-input cr3-textarea" rows="3"
                                      placeholder="อธิบายอาการเพิ่มเติม..."><?= h($detailText) ?></textarea>
                        </div>
                    </div>
                </section>

            </div>
            <div class="cr3-col">

                <!-- ── Check-in checklist ── -->
                <section class="cr3-card">
                    <header class="cr3-hd cr3-hd-teal">
                        <span class="cr3-hd-ico material-symbols-rounded">fact_check</span>
                        <div class="cr3-hd-txt">
                            <div class="cr3-hd-title">ตรวจรับเครื่อง</div>
                            <div class="cr3-hd-sub">ของที่นำมา สภาพเครื่อง หมายเหตุ</div>
                        </div>
                        <span class="cr3-step">4</span>
                    </header>
                    <div class="cr3-body">

                        <div class="cr3-field">
                            <label class="cr3-label">สิ่งที่นำมา (Accessories)</label>
                            <div class="cr3-chips">
                                <?php foreach ($accsList as $it => $ico): $ii = h($it); ?>
                                <label class="cr3-chip">
                                    <input type="checkbox" name="items[]" value="<?= $ii ?>" <?= in_array($it, $savedAccs, true) ? 'checked' : '' ?>>
                                    <span><i class="material-symbols-rounded cr3-chip-ico"><?= $ico ?></i><?= $ii ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="items_other" class="cr3-input cr3-input-sm" value="<?= h($otherAccs) ?>" placeholder="อื่นๆ...">
                        </div>

                        <div class="cr3-field">
                            <label class="cr3-label">สภาพเครื่อง</label>
                            <div class="cr3-chips">
                                <?php foreach ($stateList as $s => $ico): $ss = h($s); ?>
                                <label class="cr3-chip cr3-chip-amber">
                                    <input type="checkbox" name="state[]" value="<?= $ss ?>" <?= in_array($s, $savedStates, true) ? 'checked' : '' ?>>
                                    <span><i class="material-symbols-rounded cr3-chip-ico"><?= $ico ?></i><?= $ss ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="state_other" class="cr3-input cr3-input-sm" value="<?= h($otherState) ?>" placeholder="สภาพอื่นๆ...">
                        </div>

                        <div class="cr3-field">
                            <label class="cr3-label">หมายเหตุช่าง</label>
                            <textarea name="technician_note" class="cr3-input cr3-textarea" rows="2"
                                      placeholder="หมายเหตุเพิ่มเติม..."><?= h($noteText) ?></textarea>
                        </div>

                    </div>
                </section>

                <!-- ── Price / appointment / status ── -->
                <section class="cr3-card">
                    <header class="cr3-hd cr3-hd-amber">
                        <span class="cr3-hd-ico material-symbols-rounded">payments</span>
                        <div class="cr3-hd-txt">
                            <div class="cr3-hd-title">ราคา · นัดหมาย · สถานะ</div>
                            <div class="cr3-hd-sub">ประเมินราคา วันนัด และสถานะงาน</div>
                        </div>
                        <span class="cr3-step">5</span>
                    </header>
                    <div class="cr3-body">

                        <div class="cr3-grid2">
                            <div class="cr3-field">
                                <label class="cr3-label">ราคาประเมิน (บาท)</label>
                                <div class="cr3-money">
                                    <span class="cr3-money-sign">฿</span>
                                    <input type="number" name="estimated_cost" class="cr3-input" value="<?= (float)$job['estimated_cost'] ?>" min="0" step="any" inputmode="numeric">
                                </div>
                            </div>
                            <div class="cr3-field">
                                <label class="cr3-label">วันที่นัดหมาย (แจ้งผล)</label>
                                <input type="date" name="appointment_date" id="appDateInput" class="cr3-input"
                                       value="<?= $appVal ?>" oninput="calcDaysFromDate()">
                            </div>
                        </div>

                        <div class="cr3-field">
                            <label class="cr3-label">นัดอีกกี่วัน (ข้ามวันอาทิตย์)</label>
                            <div class="cr3-quickdays">
                                <?php foreach ([1, 2, 3, 5, 7] as $d): ?>
                                <button type="button" class="cr3-qd" onclick="setDays(<?= $d ?>)">+<?= $d ?> วัน</button>
                                <?php endforeach; ?>
                                <input type="number" id="daysToFinish" class="cr3-input cr3-qd-input" placeholder="วัน" min="0" oninput="calcWorkDate()">
                            </div>
                        </div>

                        <div class="cr3-field">
                            <label class="cr3-label" style="color:#059669;">วันที่ลูกค้ารับเครื่องคืน (ถ้ารับแล้ว)</label>
                            <input type="datetime-local" name="pickup_date" class="cr3-input" value="<?= $pickupVal ?>">
                        </div>

                        <div class="cr3-field">
                            <label class="cr3-label">สถานะงาน</label>
                            <div class="cr3-chips">
                                <?php foreach ($statusList as $code => [$label, $color]): ?>
                                <label class="cr3-chip cr3-chip-status">
                                    <input type="radio" name="status" value="<?= $code ?>" <?= $job['status'] === $code ? 'checked' : '' ?>>
                                    <span><i class="cr3-dot" style="background:<?= $color ?>;"></i><?= h($label) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                </section>

            </div>
        </div>

        <!-- ── Sticky action bar ── -->
        <div class="cr3-actionbar cr3e-actionbar">
            <div class="cr3e-bar-left">
                <?php foreach ($linkedWarranties as $lw):
                    $wCls  = $lw['status'] === 'active' ? 'cr3e-wty-active' : ($lw['status'] === 'voided' ? 'cr3e-wty-voided' : 'cr3e-wty-other');
                    $wIcon = $lw['status'] === 'active' ? 'verified' : ($lw['status'] === 'voided' ? 'block' : 'schedule');
                ?>
                <a href="../warranty/view.php?id=<?= $lw['id'] ?>" target="_blank" class="cr3e-wty <?= $wCls ?>">
                    <span class="material-symbols-rounded"><?= $wIcon ?></span>
                    <?= h($lw['warranty_no']) ?>
                    <small>(หมด <?= date('d/m/y', strtotime($lw['end_date'])) ?>)</small>
                </a>
                <?php endforeach; ?>
                <button type="button" onclick="openWarrantyModal()" class="cr3-btn cr3e-btn-warranty">
                    <span class="material-symbols-rounded">verified_user</span> ออกใบประกัน
                </button>
            </div>
            <div class="cr3e-bar-right">
                <a href="index.php" class="cr3-btn cr3-btn-ghost">
                    <span class="material-symbols-rounded">close</span> ยกเลิก
                </a>
                <button type="button" id="btnToggleLock" onclick="toggleFormLock()" class="cr3-btn cr3-btn-ghost">
                    <span class="material-symbols-rounded" id="lockIcon">lock</span>
                    <span id="lockLabel">ปลดล็อกแก้ไข</span>
                </button>
                <button type="submit" id="btnSave" class="cr3-btn cr3-btn-save" disabled
                        onclick="if(document.getElementById('editForm').checkValidity()) showLoader()">
                    <span class="material-symbols-rounded">save</span> บันทึกการแก้ไข
                </button>
            </div>
        </div>

      </div><!-- /.cr3-main -->

      <!-- ── Edit history (wide desktop only) ── -->
      <aside class="cr3-aside">
        <div class="cr3-card">
            <header class="cr3-hd">
                <span class="cr3-hd-ico material-symbols-rounded" style="background:rgba(37,99,235,.10); color:var(--primary);">info</span>
                <div class="cr3-hd-txt">
                    <div class="cr3-hd-title">ข้อมูลงาน</div>
                    <div class="cr3-hd-sub"><?= h($job['ticket_number']) ?></div>
                </div>
            </header>
            <div class="cr3-body">
                <div class="cr3-tip"><span class="material-symbols-rounded">event</span> เปิดงาน <?= date('d/m/Y H:i', strtotime($job['created_at'])) ?></div>
                <?php if (!empty($job['updated_at'])): ?>
                <div class="cr3-tip"><span class="material-symbols-rounded">update</span> แก้ไขล่าสุด <?= date('d/m/Y H:i', strtotime($job['updated_at'])) ?></div>
                <?php endif; ?>
                <div class="cr3-tip"><span class="material-symbols-rounded">lock</span> ฟอร์มถูกล็อกไว้ — กด "ปลดล็อกแก้ไข" ก่อนแก้ข้อมูล</div>
            </div>
        </div>

        <div class="cr3-card cr3-recent">
            <header class="cr3-hd">
                <span class="cr3-hd-ico material-symbols-rounded" style="background:rgba(20,184,166,.10); color:#14b8a6;">history</span>
                <div class="cr3-hd-txt">
                    <div class="cr3-hd-title">ประวัติการแก้ไข</div>
                    <div class="cr3-hd-sub">งานนี้ถูกแก้ <?= count($histRows) ?><?= count($histRows) >= 12 ? '+' : '' ?> ครั้ง</div>
                </div>
            </header>
            <div class="cr3-recent-list">
                <?php if (!$histRows): ?>
                    <div class="cr3e-hist-empty">ยังไม่มีการแก้ไข</div>
                <?php endif; ?>
                <?php foreach ($histRows as $hr):
                    $stChanged = ($hr['status_old'] ?? '') !== ($hr['status_new'] ?? '');
                    $nDiff = count(json_decode($hr['diff_json'] ?? '[]', true) ?: []);
                ?>
                <div class="cr3-recent-row">
                    <span class="cr3-recent-no"><?= date('d/m H:i', strtotime($hr['changed_at'])) ?></span>
                    <span class="cr3-recent-name">
                        <?= h($hr['admin_name'] ?: '—') ?>
                        <?php if ($stChanged): ?>
                            · <?= h($hr['status_old']) ?> → <b style="color:<?= $statusList[$hr['status_new']][1] ?? 'inherit' ?>;"><?= h($hr['status_new']) ?></b>
                        <?php endif; ?>
                    </span>
                    <span class="cr3-recent-date"><?= $nDiff ?> จุด</span>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="history.php" class="cr3-recent-all" onclick="showLoader()">
                ประวัติทั้งหมด <span class="material-symbols-rounded">arrow_forward</span>
            </a>
        </div>
      </aside>

    </form>
</div>

<script>
/* ── Lock / unlock editing ── */
let isLocked = true;

function setFormState(locked) {
    isLocked = locked;

    const icon  = document.getElementById('lockIcon');
    const label = document.getElementById('lockLabel');
    const btn   = document.getElementById('btnToggleLock');
    icon.textContent  = locked ? 'lock' : 'lock_open';
    label.textContent = locked ? 'ปลดล็อกแก้ไข' : 'ล็อกการแก้ไข';
    btn.classList.toggle('cr3e-unlocked', !locked);

    const save = document.getElementById('btnSave');
    save.disabled = locked;

    document.querySelectorAll('#editForm input:not([type="hidden"]), #editForm textarea, #editForm .cr3-qd')
        .forEach(el => { el.disabled = locked; });

    document.getElementById('editForm').classList.toggle('cr3e-locked', locked);
}
function toggleFormLock() { setFormState(!isLocked); }

/* ── Appointment day calculator (skips Sundays — shop closed) ── */
function setDays(n) {
    const el = document.getElementById('daysToFinish');
    el.value = n;
    calcWorkDate();
}
function calcWorkDate() {
    const daysInput   = document.getElementById('daysToFinish');
    const targetInput = document.getElementById('appDateInput');
    const daysToAdd   = parseInt(daysInput?.value ?? '', 10);
    if (isNaN(daysToAdd) || daysToAdd < 0 || !targetInput) return;
    const d = new Date(); let added = 0;
    while (added < daysToAdd) { d.setDate(d.getDate() + 1); if (d.getDay() !== 0) added++; }
    const pad = n => String(n).padStart(2, '0');
    targetInput.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}
function calcDaysFromDate() {
    const daysInput   = document.getElementById('daysToFinish');
    const targetInput = document.getElementById('appDateInput');
    if (!targetInput?.value || !daysInput) return;
    const target = new Date(targetInput.value);
    const today  = new Date(); target.setHours(0,0,0,0); today.setHours(0,0,0,0);
    if (target < today) { daysInput.value = 0; return; }
    let count = 0; const tmp = new Date(today);
    while (tmp < target) { tmp.setDate(tmp.getDate() + 1); if (tmp.getDay() !== 0) count++; }
    daysInput.value = count;
}

/* ── Auto-grow textareas ── */
document.querySelectorAll('.cr3-textarea').forEach(t => {
    const grow = () => { t.style.height = 'auto'; t.style.height = (t.scrollHeight + 2) + 'px'; };
    t.addEventListener('input', grow); grow();
});

window.addEventListener('load', function() {
    calcDaysFromDate();
    setFormState(true);
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
