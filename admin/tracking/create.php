<?php
/********************************************************************
 * admin/tracking/create.php
 *
 * ฉบับ "Two-Way Date Sync":
 * - กรอกจำนวนวัน -> คำนวณวันที่นัด (ข้ามอาทิตย์)
 * - เลือกวันที่นัด -> คำนวณย้อนกลับว่ากี่วัน (ข้ามอาทิตย์)
 * - Dropdown Select + Clean Code
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$pageTitle = "เปิดงานซ่อม";
$errorMsg = '';

// Dropdown Data
$deviceList = [
    'iPhone', 'iPad', 'MacBook', 'iMac', 
    'Notebook', 'PC', 
    'Mac mini', 'Mac Studio', 'Mac Pro', 
    'Apple Watch', 'AirPods', 'Apple TV', 'Other'
];

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

// =========================[ 1) HANDLE FORM SUBMIT ]========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket = trim($_POST['ticket_number']);
    
    if (empty($ticket)) { 
        $errorMsg = "⚠️ กรุณาระบุเลขที่ซ่อม";
    } else {
        $chk = $pdo->prepare("SELECT id FROM tracking WHERE ticket_number = ?");
        $chk->execute([$ticket]);
        
        if ($chk->fetch()) {
            $errorMsg = "❌ เลขที่ซ่อมนี้ ($ticket) มีในระบบแล้ว";
        } else {
            // Basic Info
            $cust_name  = trim($_POST['customer_name']);
            $cust_phone = trim($_POST['customer_phone']);
            
            // Device Info
            $type       = trim($_POST['device_type']);
            $series     = trim($_POST['device_series']); 
            $model_code = trim($_POST['device_model']);  
            $final_model = trim($series . ' ' . $model_code);
            $serial     = trim($_POST['serial_number']);
            $pass       = trim($_POST['device_password']);
            
            // Checklist
            $accs_arr = isset($_POST['items']) ? $_POST['items'] : [];
            if (!empty($_POST['items_other'])) { $accs_arr[] = trim($_POST['items_other']); }
            $accs_db = implode(', ', $accs_arr);

            $state_arr = isset($_POST['state']) ? $_POST['state'] : [];
            if (!empty($_POST['state_other'])) { $state_arr[] = trim($_POST['state_other']); }
            $state_str = !empty($state_arr) ? "สภาพ: " . implode(', ', $state_arr) : "";
            
            $note_input = trim($_POST['technician_note']);
            $note_db = $state_str . ($note_input ? " | Note: " . $note_input : "");

            $symp_arr = isset($_POST['symptoms']) ? $_POST['symptoms'] : [];
            $prob_detail = trim($_POST['problem_details']);
            $prob_header = !empty($symp_arr) ? "[ " . implode(', ', $symp_arr) . " ] " : "";
            $prob_db = $prob_header . $prob_detail;

            // Cost & Date
            $cost    = (float)$_POST['estimated_cost'];
            $status  = $_POST['status'] ?? 'QS'; 
            $app_date = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;

            if ($cust_name && $cust_phone && $final_model) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO tracking 
                        (ticket_number, customer_name, customer_phone, device_type, device_model, 
                         serial_number, device_password, problem_details, technician_note, 
                         accessories, estimated_cost, appointment_date, status, created_at)
                        VALUES 
                        (:ticket, :cname, :cphone, :dtype, :dmodel, 
                         :sn, :pass, :prob, :note, 
                         :accs, :cost, :app_date, :status, NOW())
                    ");

                    $stmt->execute([
                        ':ticket' => $ticket, ':cname' => $cust_name, ':cphone' => $cust_phone,
                        ':dtype' => $type, ':dmodel' => $final_model, ':sn' => $serial,
                        ':pass' => $pass, ':prob' => $prob_db, ':note' => $note_db,
                        ':accs' => $accs_db, 
                        ':cost' => $cost, 
                        ':app_date' => $app_date,
                        ':status' => $status
                    ]);

                    header("Location: index.php"); 
                    exit;
                } catch (PDOException $e) {
                    $errorMsg = "Error: " . $e->getMessage();
                }
            } else {
                $errorMsg = "กรุณากรอกข้อมูลให้ครบ";
            }
        }
    }
}

// =========================[ 2) TEMPLATE ]=============================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">

<main class="main" id="main-content">
    
    <div class="topbar">
        <span><?= h($pageTitle) ?></span>
        <a href="index.php" class="view-site">
            <span class="material-symbols-rounded icon-back">arrow_back</span> กลับหน้ารายการ
        </a>
    </div>

    <div class="section-header">
        <h2>บันทึกข้อมูล (Two-Way Date Sync)</h2>
        <button type="submit" form="createForm" class="btn-primary">
            <span class="material-symbols-rounded">save</span> บันทึกข้อมูล
        </button>
    </div>

    <div class="table-container form-container">
        
        <?php if($errorMsg): ?>
            <div class="alert-box error">
                <span class="material-symbols-rounded">error</span> <?= h($errorMsg) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" id="createForm">
            
            <div class="form-section-group">
                <div class="job-id-box">
                    <label class="job-label">เลขที่ซ่อม (Job No.) <span class="req">*</span></label>
                    <input type="text" name="ticket_number" class="input-job" placeholder="Vxxxx" required autofocus autocomplete="off">
                </div>
                <div class="customer-box">
                    <div class="section-title"><span class="material-symbols-rounded icon">person</span> ข้อมูลลูกค้า</div>
                    <div class="form-row-inline">
                        <div class="form-col">
                            <label>ชื่อ-นามสกุล <span class="req">*</span></label>
                            <input type="text" name="customer_name" class="input-std" required>
                        </div>
                        <div class="form-col">
                            <label>เบอร์โทรศัพท์ <span class="req">*</span></label>
                            <input type="tel" name="customer_phone" class="input-std" required>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="form-divider">

            <div class="form-split">
                
                <div class="form-left">
                    <div class="section-title"><span class="material-symbols-rounded icon">devices</span> ข้อมูลอุปกรณ์</div>
                    
                    <div class="form-group-row">
                        <label>ประเภทเครื่อง <span class="req">*</span></label>
                        <div class="select-wrapper">
                            <select name="device_type" class="modern-select" required>
                                <option value="" disabled selected>-- เลือกประเภท --</option>
                                <?php foreach ($deviceList as $prod): ?>
                                    <option value="<?= $prod ?>"><?= $prod ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="material-symbols-rounded select-arrow">expand_more</span>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <label>รุ่นย่อย (Series)</label>
                        <input type="text" name="device_series" class="input-line" placeholder="Ex: Pro, Air, Mini">
                    </div>

                    <div class="form-group-row">
                        <label>Model (Axxxx) <span class="req">*</span></label>
                        <input type="text" name="device_model" class="input-line" required placeholder="Ex: A1708, A2338">
                    </div>

                    <div class="form-group-row">
                        <label>Serial No.</label>
                        <input type="text" name="serial_number" class="input-line">
                    </div>
                    
                    <div class="password-box">
                        <div class="form-group-row mb-0">
                            <label class="text-danger"><span class="material-symbols-rounded icon-sm">lock</span> Password</label>
                            <input type="text" name="device_password" class="input-line border-danger" placeholder="สำคัญมาก!">
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
                        
                        <div class="price-box">
                            <label>ราคาประเมิน</label>
                            <input type="number" name="estimated_cost" value="0">
                        </div>

                        <div class="form-group-row mt-3" style="align-items: center;">
                            <label class="label-auto" style="min-width:120px;">นัดรับ/แจ้งผล (อีก):</label>
                            <div class="date-calc-wrapper">
                                <input type="number" id="daysToFinish" class="input-days" placeholder="0" min="0" oninput="calcWorkDate()">
                                <span class="unit-text">วัน (รวมเช็ค+ซ่อม)</span>
                            </div>
                        </div>

                        <div class="form-group-row">
                            <label class="label-auto" style="min-width:120px;">วันที่นัดหมาย:</label>
                            <input type="datetime-local" name="appointment_date" id="appDateInput" class="input-line" 
                                   style="font-weight:bold; color:var(--primary);" onchange="calcDaysFromDate()">
                        </div>

                        <div class="form-group-row">
                            <label class="label-auto" style="min-width:120px;">สถานะเริ่มต้น:</label>
                            <div class="select-wrapper">
                                <select name="status" class="modern-select">
                                    <?php foreach ($statusList as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $key === 'QS' ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="material-symbols-rounded select-arrow">expand_more</span>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="form-right">
                    
                    <div class="chk-block">
                        <label class="chk-label">1. สิ่งที่นำมา (Items Brought)</label>
                        <div class="chk-grid-3">
                            <label><input type="checkbox" name="items[]" value="ตัวเครื่อง"> ตัวเครื่อง</label>
                            <label><input type="checkbox" name="items[]" value="Adapter"> Adapter</label>
                            <label><input type="checkbox" name="items[]" value="สายชาร์จ"> สายชาร์จ</label>
                            <label><input type="checkbox" name="items[]" value="Bag"> กระเป๋า</label>
                            <label><input type="checkbox" name="items[]" value="Soft Case"> ซอง (Case)</label>
                            <label><input type="checkbox" name="items[]" value="กล่อง"> กล่อง</label>
                            <label><input type="checkbox" name="items[]" value="Mouse"> เมาส์</label>
                            <label><input type="checkbox" name="items[]" value="Keyboard"> คีย์บอร์ด</label>
                            <label><input type="checkbox" name="items[]" value="Sim Tray"> ถาดซิม</label>
                        </div>
                        <input type="text" name="items_other" class="input-sm" placeholder="อื่นๆ ระบุ...">
                    </div>

                    <div class="chk-block">
                        <label class="chk-label">2. สภาพเครื่อง (State)</label>
                        <div class="chk-grid-3">
                            <label><input type="checkbox" name="state[]" value="ปกติ"> ปกติ/สวย</label>
                            <label><input type="checkbox" name="state[]" value="มีรอยขีดข่วน"> รอยขีดข่วน</label>
                            <label><input type="checkbox" name="state[]" value="มีรอยบุบ/ตก"> รอยบุบ/ตก</label>
                            <label><input type="checkbox" name="state[]" value="จอลอก"> จอลอก</label>
                            <label><input type="checkbox" name="state[]" value="ยางขอบจอเสื่อม"> ยางจอเสื่อม</label>
                            <label><input type="checkbox" name="state[]" value="ยางรองหลุด"> ยางรองหลุด</label>
                            <label><input type="checkbox" name="state[]" value="น็อตหาย"> น็อตหาย</label>
                            <label><input type="checkbox" name="state[]" value="เคยแกะซ่อม"> เคยแกะซ่อม</label>
                            <label><input type="checkbox" name="state[]" value="สกปรกมาก"> สกปรกมาก</label>
                            <label><input type="checkbox" name="state[]" value="แบตบวม"> แบตบวม</label>
                            <label><input type="checkbox" name="state[]" value="เครื่องงอ"> เครื่องงอ</label>
                            <label><input type="checkbox" name="state[]" value="โดนน้ำ"> คราบน้ำ</label>
                        </div>
                        <input type="text" name="state_other" class="input-sm" placeholder="อื่นๆ ระบุ...">
                    </div>

                    <div class="chk-block chk-block-warning">
                        <label class="chk-label text-warning-dark">3. อาการเสีย (Symptoms)</label>
                        <div class="chk-grid-3">
                            <label class="chk-important"><input type="checkbox" name="symptoms[]" value="ไฟไม่เข้าเปิดไม่ติด"> ไฟไม่เข้า เปิดไม่ติด</label>
                            <label class="chk-important"><input type="checkbox" name="symptoms[]" value="ไฟเข้าเปิดไม่ติด"> ไฟเข้า เปิดไม่ติด</label>
                            <label><input type="checkbox" name="symptoms[]" value="OS/Software"> ลง Windows/OS</label>
                            <label><input type="checkbox" name="symptoms[]" value="จอแตก/เสีย"> จอแตก/เสีย</label>
                            <label><input type="checkbox" name="symptoms[]" value="แบตเสื่อม"> แบตเสื่อม/หมดไว</label>
                            <label><input type="checkbox" name="symptoms[]" value="คีย์บอร์ดเสีย"> คีย์บอร์ดเสีย</label>
                            <label><input type="checkbox" name="symptoms[]" value="Trackpadเสีย"> Trackpadเสีย</label>
                            <label><input type="checkbox" name="symptoms[]" value="Wifi/BT เสีย"> Wifi/BT เสีย</label>
                            <label><input type="checkbox" name="symptoms[]" value="ลำโพงแตก"> ลำโพงแตก</label>
                        </div>
                        <textarea name="problem_details" class="input-area" placeholder="รายละเอียดอาการเพิ่มเติม..."></textarea>
                    </div>

                    <div class="form-group-row mt-2">
                        <label class="label-auto text-muted">Note (ภายใน):</label>
                        <input type="text" name="technician_note" class="input-line">
                    </div>

                </div>
            </div>

        </form>
    </div>
</main>

<script>
// 1. กรอกเลขวัน -> คำนวณวันที่
function calcWorkDate() {
    const daysInput = document.getElementById('daysToFinish');
    const targetInput = document.getElementById('appDateInput');
    
    let daysToAdd = parseInt(daysInput.value);
    if (isNaN(daysToAdd) || daysToAdd < 0) {
        // ถ้าลบเลขวันออก อาจจะไม่ต้องเคลียร์วันที่ก็ได้ แล้วแต่ชอบ
        return;
    }

    let currentDate = new Date();
    let addedCount = 0;

    // Loop บวกวัน (ข้ามอาทิตย์)
    while (addedCount < daysToAdd) {
        currentDate.setDate(currentDate.getDate() + 1);
        if (currentDate.getDay() !== 0) { // 0 = Sunday
            addedCount++;
        }
    }

    // Format YYYY-MM-DDTHH:mm
    const year = currentDate.getFullYear();
    const month = String(currentDate.getMonth() + 1).padStart(2, '0');
    const day = String(currentDate.getDate()).padStart(2, '0');
    const hours = String(currentDate.getHours()).padStart(2, '0');
    const minutes = String(currentDate.getMinutes()).padStart(2, '0');

    targetInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
}

// 2. เลือกวันที่ -> คำนวณย้อนกลับเป็นจำนวนวัน
function calcDaysFromDate() {
    const daysInput = document.getElementById('daysToFinish');
    const targetInput = document.getElementById('appDateInput');

    if (!targetInput.value) return;

    const targetDate = new Date(targetInput.value);
    const today = new Date();
    
    // Set time to 00:00:00 for accurate day counting
    targetDate.setHours(0,0,0,0);
    today.setHours(0,0,0,0);

    if (targetDate < today) {
        daysInput.value = 0; // เลือกวันย้อนหลัง ให้เป็น 0
        return;
    }

    let count = 0;
    let tempDate = new Date(today);

    // Loop จากวันนี้ จนถึงวันที่เลือก
    while (tempDate < targetDate) {
        tempDate.setDate(tempDate.getDate() + 1);
        if (tempDate.getDay() !== 0) { // ถ้าไม่ใช่วันอาทิตย์ ให้นับ
            count++;
        }
    }
    
    daysInput.value = count;
}
</script>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>