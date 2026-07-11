<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "จัดการโครงสร้างคลังอะไหล่";
include '../templates/header_admin.php';

$can_manage = can('parts.manage');

// ดึงหมวดหมู่ทั้งหมดทำ Tree View
$stmt = $pdo->query("SELECT * FROM parts_categories ORDER BY parent_id ASC, name ASC");
$all_cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// จำนวน item ต่อหมวด (นับตรงหมวด ไม่รวมลูก)
$cat_counts = $pdo->query("SELECT category_id, COUNT(*) FROM inventory GROUP BY category_id")->fetchAll(PDO::FETCH_KEY_PAIR);

$tree = [];
foreach ($all_cats as $cat) {
    if (!$cat['parent_id']) {
        $tree[$cat['id']] = $cat;
        $tree[$cat['id']]['children'] = [];
    } else {
        $tree[$cat['parent_id']]['children'][] = $cat;
    }
}
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time(); ?>">
<link rel="stylesheet" href="assets/css/inventory-categories.css?v=<?= time(); ?>">

<div class="cmns-wrapper">
    <div style="margin-bottom: 20px;">
        <a href="index.php" class="cmns-back-link">
            <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> BACK TO INVENTORY
        </a>
    </div>

    <div class="cmns-layout">
        
        <div class="cmns-card cmns-left">
            <div class="cmns-tree-header">
                <h3>
                    <span class="material-symbols-rounded" style="color:var(--primary);">account_tree</span> โครงสร้างคลัง
                </h3>
                <?php if ($can_manage): ?>
                <button onclick="resetToAddNew()" class="cmns-btn cmns-btn-primary" style="padding: 6px 12px; font-size:12px; border-radius:8px;">
                    <span class="material-symbols-rounded" style="font-size: 16px;">add</span> เพิ่มใหม่
                </button>
                <?php endif; ?>
            </div>

            <div class="cmns-tree-list">
                <?php foreach ($tree as $parent): ?>
                    <div>
                        <div class="cmns-tree-item" id="cat-item-<?= $parent['id'] ?>" onclick='selectCat(<?= htmlspecialchars(json_encode($parent), ENT_QUOTES, 'UTF-8') ?>)'>
                            <?php if (!empty($parent['children'])): ?>
                                <span class="material-symbols-rounded cmns-toggle-icon" onclick="toggleFolder(event, 'child-list-<?= $parent['id'] ?>', this)">chevron_right</span>
                            <?php else: ?>
                                <div class="cmns-spacer"></div>
                            <?php endif; ?>

                            <span class="material-symbols-rounded" style="color: var(--primary);"><?= htmlspecialchars($parent['icon'] ?: 'folder') ?></span>
                            <span style="flex-grow: 1;"><?= htmlspecialchars($parent['name']) ?></span>
                            <?php if (!empty($cat_counts[$parent['id']])): ?><span class="cat-count"><?= number_format($cat_counts[$parent['id']]) ?></span><?php endif; ?>
                        </div>

                        <?php if (!empty($parent['children'])): ?>
                            <div id="child-list-<?= $parent['id'] ?>" style="display: none; margin-left: 36px; border-left: 1px dashed var(--border); padding-left: 8px;">
                                <?php foreach ($parent['children'] as $child): ?>
                                    <div class="cmns-tree-item" id="cat-item-<?= $child['id'] ?>" onclick='selectCat(<?= htmlspecialchars(json_encode($child), ENT_QUOTES, 'UTF-8') ?>)'>
                                        <span class="material-symbols-rounded" style="font-size: 18px; color: var(--text-muted);">subdirectory_arrow_right</span>
                                        <span class="material-symbols-rounded" style="font-size: 18px; color: var(--primary);"><?= htmlspecialchars($child['icon'] ?: 'folder') ?></span>
                                        <span style="flex-grow: 1;"><?= htmlspecialchars($child['name']) ?></span>
                                        <?php if (!empty($cat_counts[$child['id']])): ?><span class="cat-count"><?= number_format($cat_counts[$child['id']]) ?></span><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="cmns-card cmns-right" style="display:flex; justify-content:center; align-items:center;">
            <div id="editor-panel" style="width: 100%; max-width: 500px;">
                <div class="cmns-editor-placeholder" style="text-align: center; color: var(--text-muted);">
                    <span class="material-symbols-rounded" style="font-size: 80px; color:var(--primary); opacity: 0.2; margin-bottom:10px;">category</span>
                    <h2 style="margin:0 0 10px 0; color:var(--text-main);">จัดการโครงสร้างตู้</h2>
                    <?php if ($can_manage): ?>
                    <p style="margin:0; font-size:14px;">คลิกเลือกโฟลเดอร์ทางซ้ายมือ เพื่อดูข้อมูล แก้ไข หรือลบทิ้ง<br>หรือกดปุ่ม <b>"เพิ่มใหม่"</b> เพื่อสร้างหมวดหมู่หลัก</p>
                    <?php else: ?>
                    <p style="margin:0; font-size:14px;">คลิกเลือกโฟลเดอร์ทางซ้ายมือ เพื่อดูรายละเอียดหมวดหมู่</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const CAN_MANAGE = <?= $can_manage ? 'true' : 'false' ?>;

// ฟังก์ชัน ย่อ/ขยาย โฟลเดอร์
function toggleFolder(event, childListId, iconElement) {
    event.stopPropagation(); 
    const childList = document.getElementById(childListId);
    if (childList.style.display === "none") {
        childList.style.display = "block";
        iconElement.classList.add("open");
    } else {
        childList.style.display = "none";
        iconElement.classList.remove("open");
    }
}

// ฟังก์ชันแปลงตัวอักษรพิเศษ ป้องกันพัง
function escapeHtml(unsafe) {
    if(!unsafe) return '';
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// 🛑 ฟังก์ชันคลิกเพื่อแก้ไขหมวดหมู่
function selectCat(data) {
    document.querySelectorAll('.cmns-tree-item').forEach(el => el.classList.remove('active'));
    document.getElementById('cat-item-' + data.id).classList.add('active');

    const panel = document.getElementById('editor-panel');

    // role ที่ไม่มี parts.manage — ดูได้อย่างเดียว
    if (!CAN_MANAGE) {
        panel.innerHTML = `
            <div style="text-align: center;">
                <span class="material-symbols-rounded" style="font-size:50px; color:var(--primary);">${escapeHtml(data.icon) || 'folder'}</span>
                <h2 style="color:var(--text-main); margin: 10px 0 5px 0;">${escapeHtml(data.name)}</h2>
                <div style="background:var(--bg-surface-alt); display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800; color:var(--text-muted); margin-bottom:12px;">
                    CAT-ID: #${data.id}
                </div>
                <p style="margin:0; font-size:14px; color:var(--text-muted);">${escapeHtml(data.description) || 'ไม่มีคำอธิบาย'}</p>
                <p style="margin-top:18px; font-size:12px; color:var(--text-muted); opacity:.6;">ต้องมีสิทธิ์ parts.manage จึงจะแก้ไขได้</p>
            </div>`;
        return;
    }
    panel.innerHTML = `
        <div style="text-align: center; margin-bottom:25px;">
            <span class="material-symbols-rounded" style="font-size:50px; color:var(--primary);">${escapeHtml(data.icon) || 'folder'}</span>
            <h2 style="color:var(--text-main); margin: 10px 0 5px 0;">แก้ไขหมวดหมู่</h2>
            <div style="background:var(--bg-surface-alt); display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800; color:var(--text-muted);">
                CAT-ID: #${data.id}
            </div>
        </div>
        
        <form action="category_action.php" method="POST">
            <input type="hidden" name="id" value="${data.id}">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div class="cmns-input-group" style="grid-column: span 2;">
                    <label>ชื่อโฟลเดอร์ / หมวดหมู่ <span style="color:red">*</span></label>
                    <input type="text" name="name" class="cmns-input" value="${escapeHtml(data.name)}" required>
                </div>

                <div class="cmns-input-group">
                    <label>ไอคอน (Material) <a href="https://fonts.google.com/icons" target="_blank" style="color:var(--primary); font-size:10px; float:right;">ดูไอคอน</a></label>
                    <input type="text" name="icon" class="cmns-input" value="${escapeHtml(data.icon)}" placeholder="เช่น memory, build">
                </div>

                <div class="cmns-input-group">
                    <label>ย้ายไปอยู่ในโฟลเดอร์ (Parent)</label>
                    <select name="parent_id" class="cmns-input" id="parent-select">
                        <option value="">-- ไม่สังกัดใคร (หมวดหมู่หลัก) --</option>
                        <?php foreach($all_cats as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="cmns-input-group" style="margin-top: 15px;">
                <label>คำอธิบายสั้นๆ (Description)</label>
                <textarea name="description" class="cmns-input" placeholder="เช่น อะไหล่สำหรับ MacBook ทุกรุ่น...">${escapeHtml(data.description)}</textarea>
            </div>

            <div style="display:flex; gap:10px; margin-top:25px;">
                <button type="submit" name="update_category" class="cmns-btn cmns-btn-primary" style="flex:2; padding:12px;">บันทึกการแก้ไข</button>
                <button type="button" onclick="confirmDelete(${data.id}, '${escapeHtml(data.name)}')" class="cmns-btn" style="flex:1; background:transparent; border:1px solid #ef4444; color:#ef4444; padding:12px;">ลบทิ้ง</button>
            </div>
        </form>
    `;
    
    const select = document.getElementById('parent-select');
    select.value = data.parent_id || "";
    Array.from(select.options).forEach(opt => { 
        if(opt.value == data.id) opt.disabled = true; 
    });
}

// 🛑 ฟังก์ชันฟอร์มสร้างใหม่
function resetToAddNew() {
    document.querySelectorAll('.cmns-tree-item').forEach(el => el.classList.remove('active'));
    
    const panel = document.getElementById('editor-panel');
    panel.innerHTML = `
        <div style="text-align: center; margin-bottom:25px;">
            <span class="material-symbols-rounded" style="font-size:50px; color:#10b981;">create_new_folder</span>
            <h2 style="color:var(--text-main); margin: 10px 0 5px 0;">เพิ่มหมวดหมู่ใหม่</h2>
        </div>
        
        <form action="category_action.php" method="POST">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                <div class="cmns-input-group" style="grid-column: span 2;">
                    <label>ตั้งชื่อโฟลเดอร์ใหม่ <span style="color:red">*</span></label>
                    <input type="text" name="name" class="cmns-input" placeholder="เช่น จอภาพ, แบตเตอรี่..." required>
                </div>

                <div class="cmns-input-group">
                    <label>ไอคอน <a href="https://fonts.google.com/icons" target="_blank" style="color:var(--primary); font-size:10px; float:right;">ดูไอคอน</a></label>
                    <input type="text" name="icon" class="cmns-input" placeholder="เช่น folder">
                </div>

                <div class="cmns-input-group">
                    <label>จัดให้อยู่ในโฟลเดอร์ (ถ้ามี)</label>
                    <select name="parent_id" class="cmns-input">
                        <option value="">-- สร้างเป็นหมวดหมู่หลัก --</option>
                        <?php foreach($all_cats as $c): ?>
                            <?php if(!$c['parent_id']): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="cmns-input-group" style="margin-top: 15px;">
                <label>คำอธิบายสั้นๆ (Description)</label>
                <textarea name="description" class="cmns-input" placeholder="อธิบายว่าหมวดหมู่นี้เก็บอะไร..."></textarea>
            </div>

            <button type="submit" name="add_category" class="cmns-btn cmns-btn-primary" style="width:100%; margin-top:15px; padding:12px; background:#10b981; border-color:#10b981;">+ สร้างโฟลเดอร์ใหม่เลย!</button>
        </form>
    `;
}

// ฟังก์ชันลบ
function confirmDelete(id, name) {
    if (typeof Swal === 'undefined') {
        if (confirm(`มึงแน่ใจนะว่าจะลบโฟลเดอร์ "${name}"? \nถ้าลบแล้วของข้างในแม่งปลิวหมดนะสัส! เอาจริงดิ?`)) {
            window.location.href = `category_action.php?delete=${id}`;
        }
        return;
    }
    Swal.fire({
        icon: 'warning',
        title: `ลบโฟลเดอร์ "${name}"?`,
        text: 'ของข้างในจะหลุดจากหมวดหมู่ — กู้คืนไม่ได้',
        showCancelButton: true,
        confirmButtonText: 'ลบเลย',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#ef4444'
    }).then(r => {
        if (r.isConfirmed) window.location.href = `category_action.php?delete=${id}`;
    });
}
</script>

<?php include '../templates/footer_admin.php'; ?>