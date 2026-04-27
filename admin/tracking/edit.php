<?php
/********************************************************************
 * admin/tracking/edit.php
 * แก้ไขงานซ่อม (Update Job) + บันทึกผู้แก้ไขล่าสุด (Modern UI)
 ********************************************************************/

session_start();
// ตั้งเวลาเป็นไทย
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// 1. รับ ID งานซ่อม
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errorMsg = '';

// 2. ดึงข้อมูลเดิมจาก Database
$stmt = $pdo->prepare("SELECT * FROM tracking WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die("ไม่พบข้อมูลงานซ่อม (Job Not Found)");
}

// --- Lists Data (ตัวเลือกต่างๆ) ---
$deviceList = ['iPhone', 'iPad', 'MacBook', 'iMac', 'Notebook', 'PC', 'Mac mini', 'Mac Studio', 'Mac Pro', 'Apple Watch', 'AirPods', 'Apple TV', 'Other'];
$accsList   = ['ตัวเครื่อง', 'Adapter', 'สายชาร์จ', 'กระเป๋า', 'Soft Case', 'กล่อง', 'Mouse', 'Keyboard'];
$stateList  = ['ปกติ/สวย', 'รอยขีดข่วน', 'รอยบุบ/ตก', 'น็อตหาย', 'เคยแกะซ่อม',  'แบตบวม', 'โดนน้ำ', 'เครื่องประกอบไม่สมบูรณ์'];
$sympsList  = ['ไฟเข้าเปิดไม่ติด', 'ไฟไม่เข้าเปิดไม่ติด', 'จอแตก/เสีย', 'แบตเสื่อม', 'คีย์บอร์ดเสีย', 'Trackpadเสีย', 'Wifi/BT เสีย', 'ลงโปรแกรม', 'ชาร์จไม่เข้า', 'windows/os'];
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
    'RT'  => 'รับคืนแล้ว'
];

// --- Helpers ---
function getCheckedItems($dbString) {
    if (!$dbString) return [];
    return array_map('trim', explode(',', $dbString));
}

function getOtherText($dbString, $standardList) {
    $items = getCheckedItems($dbString);
    $others = array_diff($items, $standardList);
    return implode(', ', $others);
}

// เตรียมข้อมูลสำหรับแสดงผล (Checkbox, CKEditor)
$savedAccs = getCheckedItems($job['accessories']);
$otherAccs = getOtherText($job['accessories'], $accsList);
$savedStates = []; // ปกติ technician_note เก็บรวม แต่ถ้าแยกเก็บ state ก็ดึงมาใช้ได้

// แยกหัวข้ออาการเสีย [xxx] ออกจากรายละเอียด text
$savedSymps = [];
$detailText = $job['problem_details'];
if (preg_match('/^\[(.*?)\](.*)/s', $job['problem_details'], $matches)) {
    $savedSymps = array_map('trim', explode(',', $matches[1]));
    $detailText = trim($matches[2]);
}

// 3. บันทึกข้อมูล (UPDATE Process)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket = trim($_POST['ticket_number'] ?? '');

    // เช็คเลขซ้ำ (เฉพาะถ้าเลขเปลี่ยน)
    if ($ticket !== $job['ticket_number']) {
        $chk = $pdo->prepare("SELECT id FROM tracking WHERE ticket_number = ? AND id != ?");
        $chk->execute([$ticket, $id]);
        if ($chk->fetch()) $errorMsg = "เลขที่ซ่อม $ticket ซ้ำกับงานอื่น";
    }

    if (!$errorMsg) {
        $cust_name   = trim($_POST['customer_name'] ?? '');
        $cust_phone  = trim($_POST['customer_phone'] ?? '');
        $type        = trim($_POST['device_type'] ?? '');
        $series      = trim($_POST['device_series'] ?? '');
        $model_code  = trim($_POST['device_model'] ?? '');

        $serial = trim($_POST['serial_number'] ?? '');
        $pass   = trim($_POST['device_password'] ?? '');
        $job_date = !empty($_POST['job_date']) ? $_POST['job_date'] : $job['created_at'];

        // อุปกรณ์ที่นำมา
        $accs_arr = $_POST['items'] ?? [];
        if (!empty($_POST['items_other'])) $accs_arr[] = trim($_POST['items_other']);
        $accs_db = implode(', ', array_filter($accs_arr));

        // สภาพเครื่อง & หมายเหตุ
        $state_arr = $_POST['state'] ?? [];
        $state_str = $state_arr ? "สภาพ: " . implode(', ', array_filter($state_arr)) : "";
        $note_input = trim($_POST['technician_note'] ?? '');
        $note_db = $note_input;
        if ($state_str) {
            $note_db = $state_str . " | " . $note_db;
        }

        // อาการเสีย
        $symp_arr    = $_POST['symptoms'] ?? [];
        $prob_detail = trim($_POST['problem_details'] ?? '');
        $prob_header = $symp_arr ? "[ " . implode(', ', array_filter($symp_arr)) . " ] " : "";
        $prob_db = $prob_header . $prob_detail;

        $cost     = (float)($_POST['estimated_cost'] ?? 0);
        $status   = $_POST['status'] ?? 'QS';
        $app_date = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
        
        // ** ดึง ID คนแก้ไข (Admin ID) **
        // ใช้ $_SESSION['user_id'] หรือตัวแปร session อื่นตามระบบของคุณ
        $updated_by = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;

        try {
            // อัปเดตข้อมูล + updated_by + updated_at
            $sql = "UPDATE tracking SET 
                ticket_number = ?, customer_name = ?, customer_phone = ?,
                device_type = ?, device_model = ?, device_series = ?, serial_number = ?, device_password = ?,
                problem_details = ?, technician_note = ?, accessories = ?,
                estimated_cost = ?, appointment_date = ?, status = ?, created_at = ?,
                updated_at = NOW(), updated_by = ? 
                WHERE id = ?";

            $pdo->prepare($sql)->execute([
                $ticket,
                $cust_name,
                $cust_phone,
                $type,
                $model_code,
                $series,
                $serial,
                $pass,
                $prob_db,
                $note_db,
                $accs_db,
                $cost,
                $app_date,
                $status,
                $job_date,
                $updated_by, // บันทึก ID คนแก้ไข
                $id
            ]);

            header("Location: index.php?msg=" . urlencode("บันทึกแก้ไขงาน $ticket เรียบร้อย"));
            exit;
        } catch (PDOException $e) {
            $errorMsg = "Update Error: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../templates/header_admin.php'; 

?>

<link rel="stylesheet" href="/admin/tracking/assets/css/create-style.css">

<style>
    /* Footer Action Bar */
    .footer-actions {
        position: sticky; bottom: 0; z-index: 100;
        display: flex; justify-content: space-between; align-items: center;
        padding: 16px 30px; margin-top: 30px;
        background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(8px);
        border-top: 1px solid #e2e8f0; box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.05);
    }
    .action-group { display: flex; gap: 12px; }
    
    .btn-action {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        height: 46px; padding: 0 24px; border-radius: 10px; border: 1px solid transparent;
        font-family: 'Sarabun', sans-serif; font-weight: 600; font-size: 1rem;
        cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); text-decoration:none;
    }
    .btn-action .material-symbols-rounded { font-size: 22px; }

    /* Buttons */
    .btn-save { background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%); color: #fff; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2); }
    .btn-save:hover { background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%); box-shadow: 0 6px 12px rgba(37, 99, 235, 0.3); transform: translateY(-1px); }
    .btn-save:disabled { background: #cbd5e1; border-color: #cbd5e1; cursor: not-allowed; box-shadow: none; transform: none; }

    .btn-warranty { background-color: #fff; color: #d97706; border: 1px solid #f59e0b; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); }
    .btn-warranty:hover { background-color: #fffbeb; border-color: #d97706; color: #b45309; transform: translateY(-1px); }

    .btn-cancel { color: #64748b; text-decoration: none; font-weight: 500; font-size: 0.95rem; padding: 8px 16px; border-radius: 8px; transition: all 0.2s; }
    .btn-cancel:hover { color: #ef4444; background-color: #fef2f2; }

    /* Unlock Button */
    .btn-unlock {
        background: #f97316; color: #fff; border: 1px solid #ea580c;
        padding: 6px 14px; border-radius: 6px; font-weight: 700; cursor: pointer;
        display: inline-flex; align-items: center; gap: 5px; font-size: 0.9rem;
        box-shadow: 0 2px 4px rgba(249, 115, 22, 0.2);
    }
    .btn-unlock:hover { background: #ea580c; }
    .btn-unlock.is-unlocked { background: #ef4444; border-color: #dc2626; }

    /* CKEditor Fix */
    #editorSymptoms+.ck-editor .ck-editor__editable { min-height: 220px !important; border-radius: 0 0 12px 12px !important; }
    #editorSymptoms+.ck-editor .ck-toolbar { border-radius: 12px 12px 0 0 !important; }

    @media (max-width: 640px) {
        .footer-actions { flex-direction: column-reverse; gap: 15px; padding: 15px; }
        .action-group, .btn-action, .btn-cancel { width: 100%; text-align: center; }
    }
</style>

<main class="main" id="main-content">
    <div class="topbar" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span>แก้ไขงานซ่อม (Edit Job)</span>
            <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
        </div>
        <button type="button" class="btn-unlock" id="btnToggleLock" onclick="toggleFormLock()">
            <span class="material-symbols-rounded">lock</span> ปลดล็อกแก้ไข
        </button>
    </div>

    <?php if ($errorMsg): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #fecaca;">
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
                            <input type="datetime-local" name="job_date" value="<?= date('Y-m-d\TH:i', strtotime($job['created_at'])) ?>" class="input-line-dashed" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-body form-body-v2">
                <div class="v2-layout-clean">

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
                                            <label class="v2-label">Model Code (รุ่น)</label>
                                            <input type="text" name="device_model" class="v2-input" required value="<?= h($job['device_model']) ?>">
                                        </div>
                                    </div>
                                    <div class="v2-row v2-row--two">
                                        <div class="v2-field">
                                            <label class="v2-label">Series / Year</label>
                                            <input type="text" name="device_series" class="v2-input" placeholder="เช่น Pro M1" value="<?= h($job['device_series'] ?? '') ?>">
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

                                <div class="v2-block">
                                    <div class="v2-block__hd"><span class="material-symbols-rounded" style="font-size:18px; vertical-align:-3px; margin-right:4px;">event</span> นัดรับ / แจ้งผล</div>
                                    <div class="v2-row v2-row--two">
                                        <div class="v2-field">
                                            <label class="v2-label">อีก (วัน)</label>
                                            <input type="number" id="daysToFinish" class="v2-input" placeholder="0" min="0" oninput="calcWorkDate()">
                                        </div>
                                        <div class="v2-field">
                                            <label class="v2-label">วันที่นัดหมาย</label>
                                            <?php $appVal = $job['appointment_date'] ? date('Y-m-d\TH:i', strtotime($job['appointment_date'])) : ''; ?>
                                            <input type="datetime-local" name="appointment_date" id="appDateInput" class="v2-input" value="<?= $appVal ?>" oninput="calcDaysFromDate()">
                                        </div>
                                    </div>
                                </div>

                                <div class="v2-divider"></div>

                                <div class="v2-block">
                                    <div class="v2-block__hd"><span class="material-symbols-rounded" style="font-size:18px; vertical-align:-3px; margin-right:4px;">flag</span> สถานะงาน (Job Status)</div>
                                    <div class="v2-checkgrid" style="grid-template-columns: repeat(5, 1fr); gap: 10px;">
                                        <?php foreach ($statusList as $code => $label): ?>
                                            <label class="v2-check">
                                                <input type="radio" name="status" value="<?= $code ?>" <?= $job['status'] == $code ? 'checked' : '' ?>>
                                                <span style="font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= h($label) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </section>

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
                                    <textarea name="technician_note" class="v2-input" rows="3" placeholder="ระบุสภาพเครื่อง หรือ หมายเหตุเพิ่มเติม..."><?= h($job['technician_note']) ?></textarea>
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
                                        <label class="v2-label fw-bold"><span class="material-symbols-rounded" style="font-size:18px; color:#6b7280; vertical-align:-4px; margin-right:6px;">edit_note</span> รายละเอียดอาการเสีย</label>
                                        <textarea name="problem_details" id="editorSymptoms"><?= h($detailText) ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <div class="footer-actions">
                <a href="index.php" class="btn-cancel">ยกเลิก</a>
                <div class="action-group">
                    <a href="../warranty/job_form.php?job_id=<?= $id ?>" target="_blank" class="btn-action btn-warranty" style="text-decoration:none;">
                        <span class="material-symbols-rounded">verified_user</span> เพิ่มประกัน
                    </a>
                    <button type="submit" id="btnSave" class="btn-action btn-save">
                        <span class="material-symbols-rounded">save</span> บันทึกการแก้ไข
                    </button>
                </div>
            </div>

        </div>
    </form>
</main>

<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script>
    let myEditor;
    let isFormLocked = true; // เริ่มต้นล็อกฟอร์มไว้

    function setFormState(locked) {
        isFormLocked = locked;
        const btn = document.getElementById('btnToggleLock');
        const saveBtn = document.getElementById('btnSave');
        const form = document.getElementById('editForm');

        if (locked) {
            btn.innerHTML = '<span class="material-symbols-rounded">lock</span> ปลดล็อกแก้ไข';
            btn.classList.remove('is-unlocked');
            saveBtn.disabled = true; // ปิดปุ่ม Save
        } else {
            btn.innerHTML = '<span class="material-symbols-rounded">lock_open</span> ล็อกการแก้ไข';
            btn.classList.add('is-unlocked');
            saveBtn.disabled = false; // เปิดปุ่ม Save
        }

        // ล็อก Input ทั้งหมด ยกเว้น Editor
        const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
        inputs.forEach(el => { if (el.id !== 'editorSymptoms') el.disabled = locked; });

        // ล็อก CKEditor
        if (myEditor) {
            locked ? myEditor.enableReadOnlyMode('lock') : myEditor.disableReadOnlyMode('lock');
        }
    }

    function toggleFormLock() { setFormState(!isFormLocked); }

    // ฟังก์ชันคำนวณวันนัดรับ
    function calcWorkDate() {
        const daysInput = document.getElementById('daysToFinish');
        const targetInput = document.getElementById('appDateInput');
        const daysToAdd = parseInt(daysInput?.value ?? '', 10);
        if (isNaN(daysToAdd) || daysToAdd < 0 || !targetInput) return;

        const currentDate = new Date();
        let addedCount = 0;
        while (addedCount < daysToAdd) {
            currentDate.setDate(currentDate.getDate() + 1);
            if (currentDate.getDay() !== 0) addedCount++; // ข้ามวันอาทิตย์ (ถ้าต้องการ)
        }
        updateDateInput(targetInput, currentDate);
    }

    function calcDaysFromDate() {
        const daysInput = document.getElementById('daysToFinish');
        const targetInput = document.getElementById('appDateInput');
        if (!targetInput?.value || !daysInput) return;

        const targetDate = new Date(targetInput.value);
        const today = new Date();
        targetDate.setHours(0, 0, 0, 0); today.setHours(0, 0, 0, 0);

        if (targetDate < today) { daysInput.value = 0; return; }

        let count = 0;
        const tempDate = new Date(today);
        while (tempDate < targetDate) {
            tempDate.setDate(tempDate.getDate() + 1);
            if (tempDate.getDay() !== 0) count++;
        }
        daysInput.value = count;
    }

    function updateDateInput(input, date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        const hh = String(date.getHours()).padStart(2, '0');
        const mm = String(date.getMinutes()).padStart(2, '0');
        input.value = `${y}-${m}-${d}T${hh}:${mm}`;
    }

    // Init Logic
    window.addEventListener('load', function() {
        calcDaysFromDate();
        const el = document.querySelector('#editorSymptoms');
        if (el && typeof ClassicEditor !== 'undefined') {
            ClassicEditor.create(el, {
                toolbar: ['undo', 'redo', '|', 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'removeFormat'],
                shouldNotGroupWhenFull: true
            }).then(editor => {
                myEditor = editor;
                setFormState(true); // ล็อกฟอร์มเมื่อโหลดเสร็จ
            }).catch(console.error);
        } else {
            setFormState(true);
        }
    });
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>