<?php
/********************************************************************
 * admin/tracking/create.php  –  Create New Repair Job (v3 redesign)
 ********************************************************************/

session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_perms(['jobs.write']); // เปิดงานซ่อม: ช่าง+ ขึ้นไป (ยกเว้นบัญชี)

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$pageTitle = "เปิดงานซ่อมใหม่";
$errorMsg  = '';

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

/* ── Suggest next ticket number (last V#### + 1, editable) ── */
$suggestTicket = '';
try {
    $lastTicket = $pdo->query("
        SELECT ticket_number FROM tracking
        WHERE ticket_number REGEXP '^V[0-9]+$'
        ORDER BY CAST(SUBSTRING(ticket_number, 2) AS UNSIGNED) DESC
        LIMIT 1
    ")->fetchColumn();
    if ($lastTicket) $suggestTicket = 'V' . ((int)substr($lastTicket, 1) + 1);
} catch (PDOException $e) { /* suggestion only — ignore */ }

/* ── Recent jobs + today count (aside panel, desktop only) ── */
$recentJobs = [];
$todayCount = 0;
try {
    $recentJobs = $pdo->query("
        SELECT id, ticket_number, customer_name, status, created_at
        FROM tracking ORDER BY id DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    $todayCount = (int)$pdo->query("SELECT COUNT(*) FROM tracking WHERE DATE(created_at) = CURDATE()")->fetchColumn();
} catch (PDOException $e) { /* aside only — ignore */ }

/* ── Sticky old input on error ── */
function old($key, $default = '') { return h($_POST[$key] ?? $default); }
function old_in($key, $val) { return in_array($val, (array)($_POST[$key] ?? []), true) ? 'checked' : ''; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket = trim($_POST['ticket_number'] ?? '');

    if ($ticket === '') {
        $errorMsg = "กรุณาระบุเลขที่ซ่อม";
    } else {
        $chk = $pdo->prepare("SELECT id FROM tracking WHERE ticket_number = ?");
        $chk->execute([$ticket]);

        if ($chk->fetch()) {
            $errorMsg = "เลขที่ซ่อม ($ticket) มีในระบบแล้ว";
        } else {
            $cust_name  = trim($_POST['customer_name']  ?? '');
            $cust_phone = trim($_POST['customer_phone'] ?? '');
            $type       = trim($_POST['device_type']    ?? '');
            $series     = trim($_POST['device_series']  ?? '');
            $model_code = trim($_POST['device_model']   ?? '');
            $serial     = trim($_POST['serial_number']  ?? '');
            $pass       = trim($_POST['device_password']?? '');
            $job_date   = !empty($_POST['job_date']) ? $_POST['job_date'] : date('Y-m-d H:i:s');

            $accs_arr = $_POST['items'] ?? [];
            if (!empty($_POST['items_other'])) $accs_arr[] = trim($_POST['items_other']);
            $accs_db = implode(', ', array_filter($accs_arr));

            $state_arr = $_POST['state'] ?? [];
            if (!empty($_POST['state_other'])) $state_arr[] = trim($_POST['state_other']);
            $state_str = $state_arr ? "สภาพ: " . implode(', ', array_filter($state_arr)) : "";

            $note_input = trim($_POST['technician_note'] ?? '');
            $note_db    = $state_str . ($note_input ? " | Note: " . $note_input : "");

            $symp_arr    = $_POST['symptoms'] ?? [];
            $prob_detail = trim($_POST['problem_details'] ?? '');
            $prob_header = $symp_arr ? "[ " . implode(', ', array_filter($symp_arr)) . " ] " : "";
            $prob_db     = $prob_header . $prob_detail;

            $cost        = (float)($_POST['estimated_cost'] ?? 0);
            $status      = array_key_exists($_POST['status'] ?? '', $statusList) ? $_POST['status'] : 'QS';
            $app_date    = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
            $pickup_date = !empty($_POST['pickup_date'])      ? $_POST['pickup_date']      : null;
            $admin_id    = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;

            if ($cust_name && $cust_phone && $model_code) {
                try {
                    $pdo->prepare("
                        INSERT INTO tracking
                        (ticket_number, customer_name, customer_phone, device_type, device_model, device_series,
                         serial_number, device_password, problem_details, technician_note, accessories,
                         estimated_cost, appointment_date, pickup_date, status, created_at, updated_at, updated_by)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)
                    ")->execute([
                        $ticket, $cust_name, $cust_phone, $type, $model_code, $series,
                        $serial, $pass, $prob_db, $note_db, $accs_db,
                        $cost, $app_date, $pickup_date, $status, $job_date, $admin_id
                    ]);
                    $newId = (int)$pdo->lastInsertId();

                    // ── LINE alert: การ์ดเปิดงานใหม่ (fail-safe — พังเงียบ ไม่ block การบันทึก) ──
                    try {
                        require_once __DIR__ . '/../../includes/line_helper.php';
                        $stMeta = line_tracking_status($status);
                        $rows = [
                            ['label' => 'ลูกค้า',  'value' => $cust_name],
                            ['label' => 'โทร',     'value' => $cust_phone],
                            ['label' => 'เครื่อง', 'value' => trim("$type $series $model_code")],
                        ];
                        if ($prob_db !== '') $rows[] = ['label' => 'อาการ', 'value' => mb_strimwidth(trim(strip_tags($prob_db)), 0, 80, '…')];
                        if ($cost > 0)       $rows[] = ['label' => 'ราคา',  'value' => '฿' . number_format($cost)];
                        if ($app_date)       $rows[] = ['label' => 'นัด',   'value' => date('d/m/Y', strtotime($app_date))];
                        $rows[] = ['label' => 'สถานะ', 'value' => $stMeta['label'], 'color' => $stMeta['color'], 'bold' => true];

                        $editUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'cmnsfixmac.com') . '/admin/tracking/edit.php?id=' . $newId;
                        line_job_notify($pdo, line_job_flex(
                            'เปิดงานซ่อมใหม่', $ticket, date('d/m/Y H:i') . ' น.', $rows, $editUrl, '#10b981'
                        ));
                        // แจ้งเตือนผ่านแอป (Web Push — ฟรี ไม่มีโควต้า) คู่กับ LINE
                        require_once __DIR__ . '/../../includes/push_helper.php';
                        push_job_notify($pdo,
                            "เปิดงานใหม่ $ticket",
                            trim("$cust_name · $type $series $model_code") . ' · ' . $stMeta['label'],
                            '/admin/tracking/edit.php?id=' . $newId);
                    } catch (Throwable $e) { /* ignore */ }

                    $_SESSION['success'] = "เปิดงาน $ticket เรียบร้อย";
                    header("Location: index.php");
                    exit;
                } catch (PDOException $e) {
                    $errorMsg = "DB Error: " . $e->getMessage();
                }
            } else {
                $errorMsg = "กรุณากรอกข้อมูลให้ครบ (ชื่อ, เบอร์, รุ่น)";
            }
        }
    }
}

require_once __DIR__ . '/../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/create-v3.css?v=<?= time() ?>">

<div class="cr3-wrap">

    <!-- ── Page header ── -->
    <div class="cr3-topbar">
        <a href="index.php" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> TRACKING
        </a>
        <h1 class="cr3-title">
            <span class="material-symbols-rounded">add_circle</span> เปิดงานซ่อมใหม่
        </h1>
    </div>

    <?php if ($errorMsg): ?>
    <div class="cr3-alert">
        <span class="material-symbols-rounded">error</span>
        <?= h($errorMsg) ?>
    </div>
    <?php endif; ?>

    <form method="post" id="createForm" class="cr3-form">
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
                           value="<?= old('ticket_number', $suggestTicket) ?>" required placeholder="VXXXX">
                    <?php if ($suggestTicket): ?>
                    <span class="cr3-hint">เลขถัดไปอัตโนมัติ — แก้ไขได้</span>
                    <?php endif; ?>
                </div>
                <div class="cr3-field">
                    <label class="cr3-label" for="jobDate">วันที่รับเครื่อง</label>
                    <input type="datetime-local" name="job_date" id="jobDate" class="cr3-input"
                           value="<?= old('job_date', date('Y-m-d\TH:i')) ?>" required>
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
                                <input type="text" name="customer_name" class="cr3-input" value="<?= old('customer_name') ?>" required>
                            </div>
                            <div class="cr3-field">
                                <label class="cr3-label">เบอร์โทรศัพท์ <b class="cr3-req">*</b></label>
                                <input type="tel" name="customer_phone" inputmode="tel" class="cr3-input"
                                       value="<?= old('customer_phone') ?>" placeholder="08x-xxx-xxxx" required>
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
                                           <?= ($_POST['device_type'] ?? '') === $prod ? 'checked' : '' ?> <?= $first ? 'required' : '' ?>>
                                    <span><i class="material-symbols-rounded cr3-chip-ico"><?= $ico ?></i><?= $pp ?></span>
                                </label>
                                <?php $first = false; endforeach; ?>
                            </div>
                        </div>

                        <div class="cr3-grid2">
                            <div class="cr3-field">
                                <label class="cr3-label">Model Code (รุ่น) <b class="cr3-req">*</b></label>
                                <input type="text" name="device_model" class="cr3-input" value="<?= old('device_model') ?>" required placeholder="เช่น A2338">
                            </div>
                            <div class="cr3-field">
                                <label class="cr3-label">Series / Year</label>
                                <input type="text" name="device_series" class="cr3-input" value="<?= old('device_series') ?>" placeholder="เช่น Pro M1 2020">
                            </div>
                        </div>
                        <div class="cr3-grid2">
                            <div class="cr3-field">
                                <label class="cr3-label">Serial No.</label>
                                <input type="text" name="serial_number" class="cr3-input" value="<?= old('serial_number') ?>" placeholder="S/N">
                            </div>
                            <div class="cr3-field">
                                <label class="cr3-label cr3-label-danger">Password (รหัสเครื่อง)</label>
                                <input type="text" name="device_password" class="cr3-input cr3-input-danger"
                                       value="<?= old('device_password') ?>" placeholder="จำเป็นต้องขอเพื่อเทสเครื่อง">
                            </div>
                        </div>

                    </div>
                </section>

                <!-- ── Symptoms ── -->
                <section class="cr3-card cr3-card-warn">
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
                                <input type="checkbox" name="symptoms[]" value="<?= $syy ?>" <?= old_in('symptoms', $sy) ?>>
                                <span><i class="material-symbols-rounded cr3-chip-ico"><?= $ico ?></i><?= $syy ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="cr3-field" style="margin-top:12px;">
                            <label class="cr3-label">รายละเอียดอาการเสีย</label>
                            <textarea name="problem_details" class="cr3-input cr3-textarea" rows="3"
                                      placeholder="อธิบายอาการเพิ่มเติม เช่น เปิดติดแต่ไม่ขึ้นภาพ, ศูนย์แจ้งว่า..."><?= old('problem_details') ?></textarea>
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
                                    <input type="checkbox" name="items[]" value="<?= $ii ?>" <?= old_in('items', $it) ?>>
                                    <span><i class="material-symbols-rounded cr3-chip-ico"><?= $ico ?></i><?= $ii ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="items_other" class="cr3-input cr3-input-sm" value="<?= old('items_other') ?>" placeholder="อื่นๆ...">
                        </div>

                        <div class="cr3-field">
                            <label class="cr3-label">สภาพเครื่อง</label>
                            <div class="cr3-chips">
                                <?php foreach ($stateList as $s => $ico): $ss = h($s); ?>
                                <label class="cr3-chip cr3-chip-amber">
                                    <input type="checkbox" name="state[]" value="<?= $ss ?>" <?= old_in('state', $s) ?>>
                                    <span><i class="material-symbols-rounded cr3-chip-ico"><?= $ico ?></i><?= $ss ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" name="state_other" class="cr3-input cr3-input-sm" value="<?= old('state_other') ?>" placeholder="สภาพอื่นๆ...">
                        </div>

                        <div class="cr3-field">
                            <label class="cr3-label">หมายเหตุช่าง</label>
                            <textarea name="technician_note" class="cr3-input cr3-textarea" rows="2"
                                      placeholder="หมายเหตุเพิ่มเติม..."><?= old('technician_note') ?></textarea>
                        </div>

                    </div>
                </section>

                <!-- ── Price / appointment / status ── -->
                <section class="cr3-card">
                    <header class="cr3-hd cr3-hd-amber">
                        <span class="cr3-hd-ico material-symbols-rounded">payments</span>
                        <div class="cr3-hd-txt">
                            <div class="cr3-hd-title">ราคา · นัดหมาย · สถานะ</div>
                            <div class="cr3-hd-sub">ประเมินราคา วันนัด และสถานะเริ่มต้น</div>
                        </div>
                        <span class="cr3-step">5</span>
                    </header>
                    <div class="cr3-body">

                        <div class="cr3-grid2">
                            <div class="cr3-field">
                                <label class="cr3-label">ราคาประเมิน (บาท)</label>
                                <div class="cr3-money">
                                    <span class="cr3-money-sign">฿</span>
                                    <input type="number" name="estimated_cost" class="cr3-input" value="<?= old('estimated_cost', '0') ?>" min="0" step="any" inputmode="numeric">
                                </div>
                            </div>
                            <div class="cr3-field">
                                <label class="cr3-label">วันที่นัดหมาย (แจ้งผล)</label>
                                <input type="date" name="appointment_date" id="appDateInput" class="cr3-input"
                                       value="<?= old('appointment_date') ?>" oninput="calcDaysFromDate()">
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
                            <input type="datetime-local" name="pickup_date" class="cr3-input" value="<?= old('pickup_date') ?>">
                        </div>

                        <div class="cr3-field">
                            <label class="cr3-label">สถานะเริ่มต้น</label>
                            <div class="cr3-chips">
                                <?php $curStatus = $_POST['status'] ?? 'QS'; ?>
                                <?php foreach ($statusList as $code => [$label, $color]): ?>
                                <label class="cr3-chip cr3-chip-status">
                                    <input type="radio" name="status" value="<?= $code ?>" <?= $curStatus === $code ? 'checked' : '' ?>>
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
        <div class="cr3-actionbar">
            <a href="index.php" class="cr3-btn cr3-btn-ghost">
                <span class="material-symbols-rounded">close</span> ยกเลิก
            </a>
            <button type="submit" class="cr3-btn cr3-btn-save" onclick="if(document.getElementById('createForm').checkValidity()) showLoader()">
                <span class="material-symbols-rounded">save</span> บันทึกงานซ่อม
            </button>
        </div>

      </div><!-- /.cr3-main -->

      <!-- ── Quick guide (wide desktop only) ── -->
      <aside class="cr3-aside">
        <div class="cr3-card cr3-guide">
            <header class="cr3-hd">
                <span class="cr3-hd-ico material-symbols-rounded" style="background:rgba(37,99,235,.10); color:var(--primary);">menu_book</span>
                <div class="cr3-hd-txt">
                    <div class="cr3-hd-title">คู่มือย่อ</div>
                    <div class="cr3-hd-sub">ขั้นตอนเปิดงานซ่อม</div>
                </div>
            </header>
            <div class="cr3-body">
                <ol class="cr3-guide-steps">
                    <li><i style="background:#3b82f6;"></i><div><b>ลูกค้า</b><span>ชื่อ + เบอร์ติดต่อกลับ (จำเป็น)</span></div></li>
                    <li><i style="background:#8b5cf6;"></i><div><b>อุปกรณ์</b><span>Model Code จำเป็น — อย่าลืมขอรหัสเครื่องเพื่อเทส</span></div></li>
                    <li><i style="background:#ef4444;"></i><div><b>อาการเสีย</b><span>จิ้มได้หลายอาการ + พิมพ์รายละเอียดเพิ่ม</span></div></li>
                    <li><i style="background:#14b8a6;"></i><div><b>ตรวจรับ</b><span>เช็คของที่นำมา + ตำหนิ กันปัญหาตอนคืนเครื่อง</span></div></li>
                    <li><i style="background:#f59e0b;"></i><div><b>ราคา · นัด</b><span>ปุ่ม +วัน ข้ามวันอาทิตย์ให้อัตโนมัติ</span></div></li>
                </ol>

                <div class="cr3-guide-div"></div>

                <div class="cr3-guide-tips">
                    <div class="cr3-tip"><span class="material-symbols-rounded">tag</span> เลขที่ซ่อมรันต่อจากงานล่าสุดให้เอง แก้ไขได้</div>
                    <div class="cr3-tip"><span class="material-symbols-rounded">key</span> ช่องกรอบแดง = รหัสเครื่อง สำคัญมากตอนเทส</div>
                    <div class="cr3-tip"><span class="material-symbols-rounded">flag</span> งานใหม่ปกติใช้สถานะ "รอเช็คราคา"</div>
                    <div class="cr3-tip"><span class="material-symbols-rounded">save</span> บันทึกแล้วระบบพากลับหน้ารายการทันที</div>
                </div>
            </div>
        </div>

        <!-- ── Recent jobs ── -->
        <?php if ($recentJobs): ?>
        <div class="cr3-card cr3-recent">
            <header class="cr3-hd">
                <span class="cr3-hd-ico material-symbols-rounded" style="background:rgba(20,184,166,.10); color:#14b8a6;">history</span>
                <div class="cr3-hd-txt">
                    <div class="cr3-hd-title">งานซ่อมล่าสุด</div>
                    <div class="cr3-hd-sub">เปิดวันนี้ <?= number_format($todayCount) ?> งาน</div>
                </div>
            </header>
            <div class="cr3-recent-list">
                <?php foreach ($recentJobs as $rj):
                    [$rjLabel, $rjColor] = $statusList[$rj['status']] ?? [$rj['status'], '#6b7280'];
                ?>
                <a href="edit.php?id=<?= (int)$rj['id'] ?>" class="cr3-recent-row" onclick="showLoader()">
                    <i class="cr3-dot" style="background:<?= $rjColor ?>;" title="<?= h($rjLabel) ?>"></i>
                    <span class="cr3-recent-no"><?= h($rj['ticket_number']) ?></span>
                    <span class="cr3-recent-name"><?= h($rj['customer_name']) ?></span>
                    <span class="cr3-recent-date"><?= date('d/m', strtotime($rj['created_at'])) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <a href="index.php" class="cr3-recent-all" onclick="showLoader()">
                ดูทั้งหมด <span class="material-symbols-rounded">arrow_forward</span>
            </a>
        </div>
        <?php endif; ?>
      </aside>

    </form>
</div>

<script>
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
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
