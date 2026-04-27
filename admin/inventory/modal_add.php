<?php
// ดึงข้อมูลพื้นฐาน (เหมือนเดิม)
$stmt_cats = $pdo->query("SELECT id, name, parent_id FROM parts_categories ORDER BY name ASC");
$all_categories_raw = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
$main_cats = []; $sub_cats = [];
foreach($all_categories_raw as $c) {
    if(empty($c['parent_id'])) $main_cats[] = $c;
    else $sub_cats[] = $c;
}

$stmt_items = $pdo->query("SELECT id, name, sku FROM inventory ORDER BY name ASC");
$all_inventory_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$stmt_mach = $pdo->query("SELECT id, name, asset_tag FROM inventory WHERE type = 'machine' ORDER BY id DESC");
$machine_list = $stmt_mach->fetchAll(PDO::FETCH_ASSOC);

// 🛑 รับค่า $current_type จากหน้า view.php (ถ้าไม่มีให้ default เป็น new)
$auto_type = isset($current_type) && $current_type !== 'all' ? $current_type : 'new';
?>

<div id="modal-add" class="cmns-modal">
    <div class="modal-content" style="max-width: 850px; padding: 30px;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 25px;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px; color: var(--text-main); font-weight: 800; font-size: 20px;">
                <span class="material-symbols-rounded" style="color: var(--primary); font-size: 28px;">add_circle</span>
                นำเข้าสินค้าเข้าคลัง
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeAddModal()"><span class="material-symbols-rounded">close</span></button>
        </div>

        <form id="form-add-item" action="process_add.php" method="POST" enctype="multipart/form-data">
            
            <div style="display: flex; gap: 10px; margin-bottom: 30px;">
                <label class="mode-btn active" id="btn-mode-new" style="flex: 1; text-align: center; padding: 12px; border-radius: 12px; cursor: pointer; font-weight: 700; transition: 0.2s; border: 1px solid transparent;">
                    <input type="radio" name="add_mode" value="new" checked hidden onchange="toggleAddMode('new')">
                    <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 20px; margin-right: 6px;">fiber_new</span> สร้างโปรไฟล์สินค้าใหม่
                </label>
                <label class="mode-btn" id="btn-mode-exist" style="flex: 1; text-align: center; padding: 12px; border-radius: 12px; cursor: pointer; font-weight: 700; transition: 0.2s; border: 1px solid transparent;">
                    <input type="radio" name="add_mode" value="existing" hidden onchange="toggleAddMode('existing')">
                    <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 20px; margin-right: 6px;">move_to_inbox</span> เติมสต็อกสินค้าเดิม
                </label>
            </div>

            <div id="section-existing-item" style="display: none; min-height: 420px; margin-bottom: 10px; background: var(--bg-surface-alt); border: 1px dashed var(--border); border-radius: 16px; padding: 40px; flex-direction: column; justify-content: center; align-items: center;">
                <span class="material-symbols-rounded" style="font-size: 70px; color: var(--primary); margin-bottom: 15px; opacity: 0.3;">inventory_2</span>
                <h3 style="margin: 0 0 10px 0; color: var(--text-main);">ดึงข้อมูลจากสินค้าเดิมในระบบ</h3>
                <div style="width: 100%; max-width: 500px; text-align: left; margin-top: 20px;">
                    <label class="cmns-label" style="color: var(--primary);">พิมพ์ค้นหาหรือเลือกสินค้า <span style="color:red">*</span></label>
                    <select name="existing_item_id" class="cmns-input" id="select-existing" style="width: 100%; font-size: 15px; padding: 14px; border-color: var(--primary);">
                        <option value="">-- พิมพ์ค้นหาหรือเลือกสินค้า --</option>
                        <?php foreach($all_inventory_items as $itm): ?>
                            <option value="<?= $itm['id'] ?>">[<?= $itm['sku'] ?: 'NO-SKU' ?>] <?= htmlspecialchars($itm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="section-new-item" style="display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 10px; align-items: stretch; min-height: 420px;">
                <div style="flex: 1; min-width: 200px; max-width: 220px;">
                    <label class="cmns-label">PRODUCT IMAGE</label>
                    <div style="width: 100%; aspect-ratio: 1; border: 2px dashed var(--border); border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: transparent; position: relative; overflow: hidden; cursor: pointer; transition: 0.2s;" onclick="document.getElementById('add-image').click()">
                        <div id="add-img-placeholder" style="display: flex; flex-direction: column; align-items: center; color: var(--text-muted); opacity: 0.5;">
                            <span class="material-symbols-rounded" style="font-size: 40px; margin-bottom: 8px;">add_photo_alternate</span>
                            <span style="font-size: 11px; font-weight: 700;">คลิกอัปโหลด</span>
                        </div>
                        <img id="add-img-preview" src="" style="width: 100%; height: 100%; object-fit: contain; display: none; position: absolute; top:0; left:0; background: var(--bg-surface-alt);">
                    </div>
                    <input type="file" name="image" id="add-image" accept="image/*" hidden onchange="previewAddImage(this)">
                </div>

                <div style="flex: 2; min-width: 300px;">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="cmns-label">ชื่อสินค้า <span style="color:red">*</span></label>
                            <input type="text" name="name" id="input-name" class="cmns-input" placeholder="กรอกชื่อสินค้า..." required>
                        </div>
                        <div>
                            <label class="cmns-label">รหัส SKU</label>
                            <input type="text" name="sku" class="cmns-input" placeholder="เว้นว่างเพื่อออโต้">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label class="cmns-label">1. อุปกรณ์ (Device) <span style="color:red">*</span></label>
                            <select id="main_cat_select" class="cmns-input" required onchange="updateSubCategory()">
                                <option value="">-- เลือกอุปกรณ์ --</option>
                                <?php foreach($main_cats as $mc): ?>
                                    <option value="<?= $mc['id'] ?>"><?= htmlspecialchars($mc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="cmns-label">2. ประเภทอะไหล่ (Part) <span style="color:red">*</span></label>
                            <select name="category_id" id="sub_cat_select" class="cmns-input" required disabled>
                                <option value="">-- รอเลือกอุปกรณ์ --</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label class="cmns-label" style="color: var(--primary);">ประเภทสินค้า (Item Type) <span style="color:red">*</span></label>
                        <select name="type" id="add-type-select" class="cmns-input" onchange="toggleTypeFields()" style="border-color: var(--primary); font-weight: 700; color: var(--primary);" required>
                            <option value="new" <?= ($auto_type == 'new') ? 'selected' : '' ?>>NEW (อะไหล่มือ 1)</option>
                            <option value="used" <?= ($auto_type == 'used') ? 'selected' : '' ?>>USED (อะไหล่ถอด / มือ 2)</option>
                            <option value="machine" <?= ($auto_type == 'machine') ? 'selected' : '' ?>>MACHINE (ซาก / เครื่องรอแกะ)</option>
                            <option value="sale" <?= ($auto_type == 'sale') ? 'selected' : '' ?>>SALE (เครื่องพร้อมขาย)</option>
                        </select>
                    </div>

                    <div id="dynamic-type-fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        </div>
                </div>
            </div>

            <hr style="border: none; border-top: 1px dashed var(--border); margin: 30px 0;">

            <div>
                <h4 style="margin: 0 0 20px 0; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-rounded" style="color: #10b981; font-size: 22px;">inventory</span> ข้อมูลสต็อกล็อตนี้
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div><label class="cmns-label">จำนวนรับ (Qty) <span style="color:red">*</span></label><input type="number" name="qty_received" class="cmns-input" value="1" min="1" required style="font-weight: 800; color: #10b981; border-color: #10b981;"></div>
                    <div><label class="cmns-label">ทุน/ชิ้น</label><input type="number" name="cost_price" class="cmns-input" placeholder="0.00" step="0.01"></div>
                    <div><label class="cmns-label">ขาย/ชิ้น</label><input type="number" name="sell_price" class="cmns-input" placeholder="0.00" step="0.01"></div>
                </div>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                    <div><label class="cmns-label">ซัพพลายเออร์</label><input type="text" name="supplier_name" class="cmns-input" placeholder="ร้านค้า..."></div>
                    <div><label class="cmns-label" style="color: #ef4444;">วันหมดประกัน</label><input type="date" name="warranty_end" class="cmns-input"></div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 35px;">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeAddModal()">ยกเลิก</button>
                <button type="submit" class="cmns-btn cmns-btn-primary" id="btn-submit-modal" style="padding: 12px 30px;">
                    <span class="material-symbols-rounded">save</span> บันทึกเข้าสต็อก
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.cmns-label { font-size: 11px; font-weight: 800; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
.cmns-input { width: 100%; background: var(--bg-surface-alt); border: 1px solid var(--border); color: var(--text-main); padding: 12px 14px; border-radius: 12px; font-size: 13px; outline: none; transition: all 0.2s ease; }
.cmns-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); background: var(--bg-surface); }
.cmns-input:disabled { opacity: 0.5; cursor: not-allowed; }
.mode-btn { background: transparent; color: var(--text-muted); }
.mode-btn:hover { background: var(--bg-surface-alt); }
.mode-btn.active { background: rgba(37, 99, 235, 0.1); color: var(--primary); border-color: var(--primary) !important; }
</style>

<script>
const allSubCats = <?= json_encode($sub_cats) ?>;
const machinesList = <?= json_encode($machine_list) ?>;

function toggleTypeFields() {
    const type = document.getElementById('add-type-select').value;
    const container = document.getElementById('dynamic-type-fields');
    let html = '';

    if (type === 'new') {
        html = `<div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" placeholder="เช่น 661-123"></div><div><label class="cmns-label">รุ่นรองรับ (Model)</label><input type="text" name="compatible_models" class="cmns-input" placeholder="เช่น A2337"></div><div><label class="cmns-label">ตำแหน่งเก็บ (Location)</label><input type="text" name="location" class="cmns-input" placeholder="ตู้ A ชั้น 2"></div><div><label class="cmns-label" style="color:#f59e0b;">เตือนของหมด (Min)</label><input type="number" name="min_qty" class="cmns-input" value="1" min="0"></div>`;
    } 
    else if (type === 'used') {
        let machineOpts = '<option value="">-- ไม่ทราบที่มา / ไม่ผูกเครื่องซาก --</option>';
        machinesList.forEach(m => { machineOpts += `<option value="${m.id}">[${m.asset_tag || 'NO-TAG'}] ${m.name}</option>`; });
        html = `<div style="grid-column: span 2;"><label class="cmns-label" style="color:#ef4444;"><span class="material-symbols-rounded" style="font-size:16px; vertical-align:middle;">link</span> แกะมาจากเครื่องซากตัวไหน?</label><select name="source_machine_id" class="cmns-input" style="border-color:#ef4444;">${machineOpts}</select></div><div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" placeholder="เช่น 661-123"></div><div><label class="cmns-label">ตำแหน่งเก็บ (Location)</label><input type="text" name="location" class="cmns-input" placeholder="ตู้ A ชั้น 2"></div>`;
    }
    else if (type === 'machine') {
        html = `<div><label class="cmns-label">รหัสเครื่อง (Asset Tag)</label><input type="text" name="asset_tag" class="cmns-input" placeholder="เช่น MC-001"></div><div><label class="cmns-label">Serial Number</label><input type="text" name="serial_number" class="cmns-input" placeholder="S/N..."></div><div style="grid-column: span 2;"><label class="cmns-label" style="color:#10b981;">สถานะการแยกอะไหล่</label><select name="disassembly_status" class="cmns-input" style="border-color:#10b981;"><option value="intact">ยังไม่แกะ</option><option value="partially_stripped">แกะไปบางส่วน</option><option value="stripped">แกะหมดแล้ว</option></select></div>`;
    }
    else if (type === 'sale') {
        html = `<div><label class="cmns-label">รหัสเครื่อง (Asset Tag)</label><input type="text" name="asset_tag" class="cmns-input"></div><div><label class="cmns-label">Serial Number</label><input type="text" name="serial_number" class="cmns-input"></div><div style="grid-column: span 2;"><label class="cmns-label">สภาพเครื่อง (Condition)</label><input type="text" name="condition_note" class="cmns-input" placeholder="ตำหนิต่างๆ..."></div>`;
    }
    container.innerHTML = html;
}

// 🛑 ฟังก์ชันเปิด Modal (เพิ่มคำสั่งให้สั่งเปลี่ยนฟอร์มออโต้)
function openAddModal() {
    const modal = document.getElementById('modal-add');
    if (modal) { 
        modal.classList.add('show'); 
        document.body.style.overflow = 'hidden'; 
        // สั่งให้ JS ตรวจสอบค่า Type ปัจจุบันแล้วพ่นฟิลด์ออกมาทันทีที่เปิดหน้าต่าง
        toggleTypeFields(); 
    }
}

function previewAddImage(input) {
    const placeholder = document.getElementById('add-img-placeholder');
    const preview = document.getElementById('add-img-preview');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result; preview.style.display = 'block'; placeholder.style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    } else { preview.style.display = 'none'; placeholder.style.display = 'flex'; }
}

function updateSubCategory() {
    const mainCatId = document.getElementById('main_cat_select').value;
    const subCatSelect = document.getElementById('sub_cat_select');
    subCatSelect.innerHTML = '<option value="">-- เลือกประเภทอะไหล่ --</option>';
    if (!mainCatId) { subCatSelect.disabled = true; subCatSelect.innerHTML = '<option value="">-- รอเลือกอุปกรณ์ --</option>'; return; }
    subCatSelect.disabled = false;
    const filtered = allSubCats.filter(c => c.parent_id == mainCatId);
    if (filtered.length > 0) {
        filtered.forEach(c => { subCatSelect.innerHTML += `<option value="${c.id}">${c.name}</option>`; });
        subCatSelect.innerHTML += `<option value="${mainCatId}" style="color:var(--primary); font-weight:bold;">📍 วางไว้ในตู้หลัก</option>`;
    } else { subCatSelect.innerHTML = `<option value="${mainCatId}" selected>📍 วางไว้ในตู้หลัก</option>`; }
}

function toggleAddMode(mode) {
    const secNew = document.getElementById('section-new-item');
    const secExist = document.getElementById('section-existing-item');
    const btnNew = document.getElementById('btn-mode-new');
    const btnExist = document.getElementById('btn-mode-exist');
    const inputName = document.getElementById('input-name');
    const selectExist = document.getElementById('select-existing');
    
    if (mode === 'new') {
        secNew.style.display = 'flex'; secExist.style.display = 'none';
        btnNew.classList.add('active'); btnExist.classList.remove('active');
        inputName.setAttribute('required', 'true'); selectExist.removeAttribute('required');
    } else {
        secNew.style.display = 'none'; secExist.style.display = 'flex';
        btnExist.classList.add('active'); btnNew.classList.remove('active');
        inputName.removeAttribute('required'); selectExist.setAttribute('required', 'true');
    }
}

function closeAddModal() {
    const modal = document.getElementById('modal-add');
    if (modal) {
        modal.classList.remove('show'); document.body.style.overflow = 'auto';
        document.getElementById('form-add-item').reset();
        document.getElementById('add-img-preview').style.display = 'none';
        document.getElementById('add-img-placeholder').style.display = 'flex';
        document.getElementById('sub_cat_select').disabled = true;
        toggleAddMode('new');
        // รีเซ็ตค่า Type กลับไปตามเดิมเวลาปิด
        document.getElementById('add-type-select').value = "<?= $auto_type ?>";
        toggleTypeFields();
    }
}
</script>