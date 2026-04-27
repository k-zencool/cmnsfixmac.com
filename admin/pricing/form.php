<?php
/********************************************************************
 * admin/pricing/form.php
 * ฟอร์มเพิ่ม/แก้ไขราคา (Enterprise Edition)
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$id = isset($_GET['id']) ? $_GET['id'] : '';

// --- ค่าเริ่มต้น (Default Values) ---
$data = [
    'service_code' => '', 
    'device_type' => '', 
    'device_series' => '', 
    'device_model' => '', 
    'service_name' => '', 
    'price' => '', 
    'warranty_days' => 90, 
    'is_active' => 1,
    'show_on_web' => 0
];

// --- 1. บันทึกข้อมูล (Save) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_code = trim($_POST['service_code']);
    $device_type = trim($_POST['device_type']);
    $device_series = trim($_POST['device_series']);
    $device_model = trim($_POST['device_model']);
    $service_name = trim($_POST['service_name']);
    $price = floatval($_POST['price']);
    $warranty = intval($_POST['warranty_days']);
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;
    $show_on_web = isset($_POST['show_on_web']) ? intval($_POST['show_on_web']) : 0;

    if ($id) { // Update
        $sql = "UPDATE service_pricing SET service_code=?, device_type=?, device_series=?, device_model=?, service_name=?, price=?, warranty_days=?, is_active=?, show_on_web=?, updated_at=NOW() WHERE id=?";
        $pdo->prepare($sql)->execute([$service_code, $device_type, $device_series, $device_model, $service_name, $price, $warranty, $is_active, $show_on_web, $id]);
    } else { // Insert
        $sql = "INSERT INTO service_pricing (service_code, device_type, device_series, device_model, service_name, price, warranty_days, is_active, show_on_web) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$service_code, $device_type, $device_series, $device_model, $service_name, $price, $warranty, $is_active, $show_on_web]);
    }
    header("Location: index.php");
    exit();
}

// --- 2. ดึงข้อมูลมาแก้ไข (Edit Mode) ---
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM service_pricing WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if($res) $data = $res;
}

// --- 3. ดึงงานล่าสุด (Smart Suggestion) ---
// ✅ แก้ไข: ใช้ estimated_cost แทน price ตามตาราง tracking จริง
$recent_jobs = $pdo->query("
    SELECT * FROM tracking 
    WHERE status = 'DV' AND estimated_cost > 0 
    ORDER BY updated_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../templates/header_admin.php'; 
?>

<style>
    /* Layout */
    .layout-grid { display: grid; grid-template-columns: 2fr 1.3fr; gap: 25px; }
    .card { background: #fff; padding: 25px; border-radius: 16px; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    
    /* Inputs */
    .form-group { margin-bottom: 20px; position: relative; }
    .form-label { display: flex; align-items: center; gap: 6px; font-weight: 600; margin-bottom: 8px; color: #334155; font-size: 0.9rem; }
    .form-label .material-symbols-rounded { font-size: 18px; color: #64748b; }
    .form-control { width: 100%; padding: 12px 12px 12px 40px; border: 1px solid #cbd5e1; border-radius: 10px; transition: 0.2s; font-size: 1rem; }
    .form-control:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    
    /* Input Icons */
    .input-icon { position: absolute; left: 12px; top: 42px; color: #94a3b8; font-size: 20px; pointer-events: none; }
    
    /* Buttons */
    .btn-submit { background: #2563eb; color: #fff; padding: 14px; border: none; border-radius: 10px; width: 100%; cursor: pointer; font-size: 1rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; }
    .btn-submit:hover { background: #1d4ed8; transform: translateY(-1px); }

    /* Smart List Items */
    .job-item { padding: 15px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 10px; cursor: pointer; transition: 0.2s; background: #fff; position: relative; }
    .job-item:hover { border-color: #3b82f6; background: #eff6ff; transform: translateX(-2px); }
    .job-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .job-id { font-size: 0.8rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
    .job-price { font-weight: 700; color: #059669; font-size: 0.95rem; }
    .job-model { font-weight: 600; color: #1e293b; font-size: 0.9rem; display: flex; align-items: center; gap: 5px; }
    .job-problem { font-size: 0.85rem; color: #64748b; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
    .btn-add-mini { position: absolute; right: 10px; bottom: 10px; background: #dbeafe; color: #2563eb; border: none; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.2s; }
    .job-item:hover .btn-add-mini { opacity: 1; }

    /* Section Header */
    .section-head { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; color: #1e293b; font-size: 1.1rem; font-weight: 700; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
</style>

<main class="main">
    <div class="topbar">
        <span style="display: flex; align-items: center; gap: 10px;">
            <span class="material-symbols-rounded" style="color:#3b82f6;">price_check</span>
            <?= $id ? 'แก้ไขราคา' : 'เพิ่มราคาใหม่' ?>
        </span>
        <a href="index.php" style="color: #64748b; text-decoration: none; display: flex; align-items: center; gap: 5px;">
            <span class="material-symbols-rounded">arrow_back</span> กลับ
        </a>
    </div>

    <div class="layout-grid">
        
        <div class="card">
            <h3 class="section-head"><span class="material-symbols-rounded">edit_note</span> ข้อมูลราคามาตรฐาน</h3>
            <form method="POST">
                
                <div class="form-group">
                    <label class="form-label">รหัสบริการ (Service Code / SN)</label>
                    <span class="material-symbols-rounded input-icon">qr_code_2</span>
                    <input type="text" name="service_code" id="inpCode" class="form-control" placeholder="เช่น FIX-IP13-LCD (ไม่บังคับ)" value="<?= htmlspecialchars($data['service_code']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">ประเภทอุปกรณ์ (Type)</label>
                    <span class="material-symbols-rounded input-icon">devices</span>
                    <input type="text" name="device_type" id="inpType" class="form-control" placeholder="เช่น iPhone" value="<?= htmlspecialchars($data['device_type']) ?>" list="typeList" required>
                    <datalist id="typeList"><option value="iPhone"><option value="iPad"><option value="MacBook"></datalist>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">ชื่อรุ่น (Series)</label>
                        <span class="material-symbols-rounded input-icon">smartphone</span>
                        <input type="text" name="device_series" id="inpSeries" class="form-control" placeholder="เช่น 13 Pro" value="<?= htmlspecialchars($data['device_series']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">โมเดล (Axxxx)</label>
                        <span class="material-symbols-rounded input-icon">tag</span>
                        <input type="text" name="device_model" id="inpModel" class="form-control" placeholder="เช่น A2638" value="<?= htmlspecialchars($data['device_model']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">รายการซ่อม (Service)</label>
                    <span class="material-symbols-rounded input-icon">build_circle</span>
                    <input type="text" name="service_name" id="inpService" class="form-control" placeholder="เช่น เปลี่ยนจอแท้" value="<?= htmlspecialchars($data['service_name']) ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">ราคา (บาท)</label>
                        <span class="material-symbols-rounded input-icon">payments</span>
                        <input type="number" name="price" id="inpPrice" class="form-control" placeholder="0.00" value="<?= htmlspecialchars($data['price']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ประกัน (วัน)</label>
                        <span class="material-symbols-rounded input-icon">verified_user</span>
                        <input type="number" name="warranty_days" class="form-control" value="<?= htmlspecialchars($data['warranty_days']) ?>">
                    </div>
                </div>

                <div style="background: #f8fafc; padding: 15px; border-radius: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label class="form-label"><span class="material-symbols-rounded">store</span> สถานะหน้าร้าน</label>
                        <select name="is_active" class="form-control" style="padding-left: 12px;">
                            <option value="1" <?= $data['is_active'] == 1 ? 'selected' : '' ?>>เปิดใช้งาน</option>
                            <option value="0" <?= $data['is_active'] == 0 ? 'selected' : '' ?>>ปิดใช้งาน</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label"><span class="material-symbols-rounded">public</span> หน้าเว็บไซต์</label>
                        <select name="show_on_web" class="form-control" style="padding-left: 12px;">
                            <option value="1" <?= $data['show_on_web'] == 1 ? 'selected' : '' ?>>โชว์ราคา</option>
                            <option value="0" <?= $data['show_on_web'] == 0 ? 'selected' : '' ?>>ซ่อนราคา</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-submit" style="margin-top: 20px;">
                    <span class="material-symbols-rounded">save</span> บันทึกข้อมูล
                </button>
            </form>
        </div>

        <div class="card" style="background: #f8fafc; border: none;">
            <h3 class="section-head" style="font-size: 1rem; color:#475569;">
                <span class="material-symbols-rounded">history</span> งานที่เพิ่งส่งมอบ (DV)
            </h3>
            
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px;">คลิกที่รายการเพื่อดึงราคามาใช้ได้ทันที</p>

            <div style="max-height: 600px; overflow-y: auto; padding-right: 5px;">
                <?php if (empty($recent_jobs)): ?>
                    <div style="text-align: center; color: #94a3b8; padding: 30px;">
                        <span class="material-symbols-rounded" style="font-size: 40px; opacity: 0.5;">inbox</span>
                        <br>ยังไม่มีงานที่เสร็จและมีราคา
                    </div>
                <?php endif; ?>

                <?php foreach ($recent_jobs as $job): 
                    $clean_problem = htmlspecialchars(strip_tags($job['problem_details']));
                    $clean_model = htmlspecialchars($job['device_model']); // เช่น iPhone 13 Pro
                    $clean_type = htmlspecialchars($job['device_type']);
                    
                    // ✅ แก้ไข: ใช้ estimated_cost ตรงนี้ด้วย
                    $j_price = $job['estimated_cost'];
                ?>
                <div class="job-item" onclick="importData('<?= $clean_type ?>', '<?= $clean_model ?>', '<?= $clean_problem ?>', '<?= $j_price ?>')">
                    <div class="job-header">
                        <span class="job-id"><?= $job['ticket_number'] ?></span>
                        <span class="job-price">฿<?= number_format($j_price) ?></span>
                    </div>
                    <div class="job-model">
                        <span class="material-symbols-rounded" style="font-size: 16px; color:#64748b;">smartphone</span>
                        <?= $job['device_model'] ?>
                    </div>
                    <div class="job-problem">
                        <span class="material-symbols-rounded" style="font-size: 14px; vertical-align: middle;">build</span>
                        <?= $clean_problem ?>
                    </div>
                    <button class="btn-add-mini"><span class="material-symbols-rounded">add</span></button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</main>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
    function importData(type, series, service, price) {
        // เอฟเฟกต์เล็กน้อยให้รู้ว่ากดแล้ว
        document.body.style.cursor = 'wait';
        
        // เติมข้อมูล
        document.getElementById('inpType').value = type;
        document.getElementById('inpSeries').value = series;
        document.getElementById('inpService').value = service;
        document.getElementById('inpPrice').value = price;
        
        // Reset Model & Code เพราะต้องกรอกใหม่ให้ตรงกับมาตรฐาน
        document.getElementById('inpModel').value = ''; 
        document.getElementById('inpCode').value = ''; 

        setTimeout(() => {
            document.body.style.cursor = 'default';
            // Focus ที่ช่อง Service Code เผื่ออยากตั้งรหัสเลย
            document.getElementById('inpCode').focus();
        }, 200);
    }
</script>