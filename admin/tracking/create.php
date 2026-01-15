<?php
/********************************************************************
 * admin/tracking/create.php
 * เปิดงานซ่อมใหม่ (Create Job) + บันทึกคนทำรายการ (Modern UI)
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

$pageTitle = "เปิดงานซ่อม";
$errorMsg  = '';

// --- Lists Data ---
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket = trim($_POST['ticket_number'] ?? '');

    if ($ticket === '') {
        $errorMsg = "⚠️ กรุณาระบุเลขที่ซ่อม";
    } else {
        // เช็คเลขซ้ำ
        $chk = $pdo->prepare("SELECT id FROM tracking WHERE ticket_number = ?");
        $chk->execute([$ticket]);

        if ($chk->fetch()) {
            $errorMsg = "❌ เลขที่ซ่อมนี้ ($ticket) มีในระบบแล้ว";
        } else {
            // รับค่าจากฟอร์ม
            $cust_name   = trim($_POST['customer_name'] ?? '');
            $cust_phone  = trim($_POST['customer_phone'] ?? '');
            $type        = trim($_POST['device_type'] ?? '');
            $series      = trim($_POST['device_series'] ?? '');
            $model_code  = trim($_POST['device_model'] ?? '');
            $serial      = trim($_POST['serial_number'] ?? '');
            $pass        = trim($_POST['device_password'] ?? '');
            
            // วันที่รับงาน (Default = Now)
            $job_date = !empty($_POST['job_date']) ? $_POST['job_date'] : date('Y-m-d H:i:s');

            // อุปกรณ์ที่นำมา
            $accs_arr = $_POST['items'] ?? [];
            if (!empty($_POST['items_other'])) $accs_arr[] = trim($_POST['items_other']);
            $accs_db = implode(', ', array_filter($accs_arr));

            // สภาพเครื่อง
            $state_arr = $_POST['state'] ?? [];
            if (!empty($_POST['state_other'])) $state_arr[] = trim($_POST['state_other']);
            $state_str = $state_arr ? "สภาพ: " . implode(', ', array_filter($state_arr)) : "";

            // หมายเหตุช่าง
            $note_input = trim($_POST['technician_note'] ?? '');
            $note_db = $state_str . ($note_input ? " | Note: " . $note_input : "");

            // อาการเสีย
            $symp_arr    = $_POST['symptoms'] ?? [];
            $prob_detail = trim($_POST['problem_details'] ?? '');
            $prob_header = $symp_arr ? "[ " . implode(', ', array_filter($symp_arr)) . " ] " : "";
            $prob_db     = $prob_header . $prob_detail;

            $cost     = (float)($_POST['estimated_cost'] ?? 0);
            $status   = $_POST['status'] ?? 'QS';
            $app_date = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;

            // ** หา ID คนทำรายการ (จาก Session) **
            // ดักไว้หลายชื่อเผื่อระบบ Auth มึงใช้ตัวแปรอื่น
            $admin_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['id'] ?? null;

            if ($cust_name && $cust_phone && $model_code) {
                try {
                    // SQL Insert (เพิ่ม updated_by และ updated_at)
                    $sql = "INSERT INTO tracking
                    (ticket_number, customer_name, customer_phone, device_type, device_model, device_series, serial_number, device_password,
                     problem_details, technician_note, accessories, estimated_cost, appointment_date, status, created_at, updated_at, updated_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW(), ?)";

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
                        $admin_id // บันทึกคนสร้าง
                    ]);

                    header("Location: index.php?msg=" . urlencode("เปิดงาน $ticket เรียบร้อย"));
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

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<link rel="stylesheet" href="/admin/tracking/assets/css/create-style.css">

<style>
/* Footer Actions Bar */
.footer-actions {
    position: sticky; bottom: 0; z-index: 100;
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 30px; margin-top: 30px;
    background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(8px);
    border-top: 1px solid #e2e8f0; box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.05);
}
.btn-action {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    height: 46px; padding: 0 32px; border-radius: 10px; border: 1px solid transparent;
    font-family: 'Sarabun', sans-serif; font-weight: 600; font-size: 1rem;
    cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-action .material-symbols-rounded { font-size: 22px; }

/* Buttons */
.btn-save { background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%); color: #fff; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2); }
.btn-save:hover { background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%); box-shadow: 0 6px 12px rgba(37, 99, 235, 0.3); transform: translateY(-1px); }
.btn-cancel { color: #64748b; text-decoration: none; font-weight: 500; font-size: 0.95rem; padding: 8px 16px; border-radius: 8px; transition: all 0.2s; }
.btn-cancel:hover { color: #ef4444; background-color: #fef2f2; }

@media (max-width: 640px) {
    .footer-actions { flex-direction: column-reverse; gap: 15px; padding: 15px; }
    .btn-action { width: 100%; } .btn-cancel { width: 100%; text-align: center; }
}

/* CKEditor Style */
#editorSymptoms+.ck-editor .ck-editor__editable { min-height: 220px !important; border-radius: 0 0 12px 12px !important; }
#editorSymptoms+.ck-editor .ck-toolbar { border-radius: 12px 12px 0 0 !important; }
</style>

<main class="main" id="main-content">
    <div class="topbar">
        <span><?= h($pageTitle) ?></span>
        <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
    </div>

    <?php if ($errorMsg): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #fecaca;">
            <?= h($errorMsg) ?>
        </div>
    <?php endif; ?>

    <form method="post" id="createForm">
        <div class="form-wrapper">

            <div class="header-scroll-wrapper">
                <div class="paper-header">
                    <div class="ph-logo"><img src="/assets/img/Logo1.png" alt="CMNS Logo"></div>
                    <div class="ph-center">
                        <h1 class="ph-title">ซ่อม Mac เชียงใหม่ By CMNS</h1>
                        <div class="ph-subtitle">Apple Product Repair Center</div>
                        <div class="ph-address">482 ม.8 วรุณนิเวศน์ ต.แม่เหียะ อ.เมือง จ.เชียงใหม่ 50100</div>
                        <div class="ph-contact">
                            <span><span class="material-symbols-rounded">call</span> 084-151-1684</span>
                        </div>
                    </div>
                    <div class="ph-box">
                        <div class="ph-box-title">เลขที่ซ่อม | Job No.</div>
                        <div class="ph-box-row">
                            <label>No.</label>
                            <input type="text" name="ticket_number" class="input-line-dashed" required placeholder="VXXXX" autofocus>
                        </div>
                        <div class="ph-box-row">
                            <label>Date.</label>
                            <input type="datetime-local" name="job_date" value="<?= date('Y-m-d\TH:i') ?>" class="input-line-dashed" required>
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
                                    <input type="text" name="customer_name" class="v2-input" required>
                                </div>
                                <div class="v2-field">
                                    <label class="v2-label">เบอร์โทรศัพท์ *</label>
                                    <input type="tel" name="customer_phone" class="v2-input" placeholder="08x-xxx-xxxx" required>
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
                                                <option value="" disabled selected>-- เลือก --</option>
                                                <?php foreach ($deviceList as $prod): ?>
                                                    <option value="<?= h($prod) ?>"><?= h($prod) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="v2-field">
                                            <label class="v2-label">Model Code (รุ่น) *</label>
                                            <input type="text" name="device_model" class="v2-input" required placeholder="เช่น A2338">
                                        </div>
                                    </div>
                                    <div class="v2-row v2-row--two">
                                        <div class="v2-field">
                                            <label class="v2-label">Series / Year</label>
                                            <input type="text" name="device_series" class="v2-input" placeholder="เช่น Pro M1 2020">
                                        </div>
                                        <div class="v2-field">
                                            <label class="v2-label">Serial No.</label>
                                            <input type="text" name="serial_number" class="v2-input" placeholder="S/N">
                                        </div>
                                    </div>
                                    <div class="v2-row v2-row--two">
                                        <div class="v2-field">
                                            <label class="v2-label v2-label--danger">Password (รหัสผ่าน)</label>
                                            <input type="text" name="device_password" class="v2-input v2-input--danger" placeholder="จำเป็นต้องขอเพื่อเทสเครื่อง">
                                        </div>
                                        <div class="v2-field">
                                            <label class="v2-label">หมายเหตุราคา</label>
                                            <input type="text" name="price_note" class="v2-input" placeholder="เช่น รวมค่าอะไหล่">
                                        </div>
                                    </div>
                                </div>

                                <div class="v2-block v2-block--price">
                                    <div class="v2-block__hd">ราคา</div>
                                    <div class="v2-price-grid">
                                        <div class="v2-field">
                                            <label class="v2-label">ราคาประเมิน (บาท)</label>
                                            <input type="number" name="estimated_cost" class="v2-input v2-input--price" value="0">
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
                                            <input type="datetime-local" name="appointment_date" id="appDateInput" class="v2-input" oninput="calcDaysFromDate()">
                                        </div>
                                    </div>
                                </div>

                                <div class="v2-divider"></div>

                                <div class="v2-block">
                                    <div class="v2-block__hd"><span class="material-symbols-rounded" style="font-size:18px; vertical-align:-3px; margin-right:4px;">flag</span> สถานะเริ่มต้น</div>
                                    <div class="v2-checkgrid" style="grid-template-columns: repeat(5, 1fr); gap: 10px;">
                                        <?php foreach ($statusList as $code => $label): ?>
                                            <label class="v2-check">
                                                <input type="radio" name="status" value="<?= $code ?>" <?= $code === 'QS' ? 'checked' : '' ?>>
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
                                        <?php foreach ($accsList as $i): $ii = h($i); ?>
                                            <label class="v2-check"><input type="checkbox" name="items[]" value="<?= $ii ?>"> <span><?= $ii ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="text" name="items_other" class="v2-input v2-input--sm" placeholder="อื่นๆ...">
                                </div>

                                <div class="v2-divider"></div>

                                <div class="v2-block">
                                    <div class="v2-block__hd">สภาพเครื่อง / หมายเหตุช่าง</div>
                                    <div class="v2-checkgrid v2-checkgrid--tight" style="margin-bottom:10px;">
                                        <?php foreach ($stateList as $s): $ss = h($s); ?>
                                            <label class="v2-check"><input type="checkbox" name="state[]" value="<?= $ss ?>"> <span><?= $ss ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <textarea name="technician_note" class="v2-input" rows="3" placeholder="ระบุสภาพเครื่อง หรือ หมายเหตุเพิ่มเติม..."></textarea>
                                </div>

                                <div class="v2-divider"></div>

                                <div class="v2-block v2-block--warn">
                                    <div class="v2-block__hd">อาการเสีย (Symptoms) <span class="v2-req">*</span></div>
                                    <div class="v2-checkgrid v2-checkgrid--wide v2-checkgrid--tight">
                                        <?php foreach ($sympsList as $sy): $syy = h($sy); ?>
                                            <label class="v2-check"><input type="checkbox" name="symptoms[]" value="<?= $syy ?>"> <span><?= $syy ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mb-0 d-flex flex-column flex-grow-1" style="margin-top:10px;">
                                        <label class="v2-label fw-bold"><span class="material-symbols-rounded" style="font-size:18px; color:#6b7280; vertical-align:-4px; margin-right:6px;">edit_note</span> รายละเอียดอาการเสีย</label>
                                        <textarea name="problem_details" id="editorSymptoms"></textarea>
                                    </div>
                                </div>

                            </div>
                        </section>

                    </div>
                </div>
            </div>

            <div class="footer-actions">
                <a href="index.php" class="btn-cancel">ยกเลิก</a>
                <button type="submit" class="btn-action btn-save">
                    <span class="material-symbols-rounded">save</span> เปิดงานซ่อม
                </button>
            </div>

        </div>
    </form>
</main>

<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<script>
    // คำนวณวันนัดรับ
    function calcWorkDate() {
        const daysInput = document.getElementById('daysToFinish');
        const targetInput = document.getElementById('appDateInput');
        const daysToAdd = parseInt(daysInput?.value ?? '', 10);
        if (isNaN(daysToAdd) || daysToAdd < 0 || !targetInput) return;

        const currentDate = new Date();
        let addedCount = 0;
        while (addedCount < daysToAdd) {
            currentDate.setDate(currentDate.getDate() + 1);
            if (currentDate.getDay() !== 0) addedCount++;
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

    // เปิด CKEditor
    window.addEventListener('load', function() {
        const el = document.querySelector('#editorSymptoms');
        if (el && typeof ClassicEditor !== 'undefined') {
            ClassicEditor.create(el, {
                toolbar: ['undo', 'redo', '|', 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'removeFormat'],
                shouldNotGroupWhenFull: true
            }).catch(console.error);
        }
    });
</script>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>