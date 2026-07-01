<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/manager_lib.php';
require_login();
require_perms(['pricing.write']); // ตั้ง/แก้ราคาซ่อม: ผู้จัดการ+ เท่านั้น

$id      = intval($_GET['id'] ?? 0);
$isModal = !empty($_GET['modal']);
$pageTitle = $id ? 'แก้ไขราคา' : 'เพิ่มราคาใหม่';

$data = [
    'device_type'  => 'iPhone',
    'device_name'  => '',
    'category_id'  => '',
    'price'        => '',
    'price_note'   => '',
    'warranty_days'=> 90,
    'is_active'    => 1,
    'show_on_web'  => 1,
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['device_type']   = trim($_POST['device_type'] ?? '');
    $data['device_name']   = trim($_POST['device_name'] ?? '');
    $data['category_id']   = intval($_POST['category_id'] ?? 0);
    $data['price']         = intval($_POST['price'] ?? 0);
    $data['price_note']    = trim($_POST['price_note'] ?? '');
    $data['warranty_days'] = intval($_POST['warranty_days'] ?? 90);
    $data['is_active']     = isset($_POST['is_active'])   ? 1 : 0;
    $data['show_on_web']   = isset($_POST['show_on_web']) ? 1 : 0;

    if (!$data['device_type'])  $errors[] = 'กรุณาเลือกประเภทอุปกรณ์';
    if (!$data['device_name'])  $errors[] = 'กรุณากรอกชื่อรุ่น';
    if (!$data['category_id'])  $errors[] = 'กรุณาเลือกหมวดบริการ';
    if ($data['price'] < 0)     $errors[] = 'ราคาต้องไม่ติดลบ';

    if (empty($errors)) {
        $cols = ['device_type','device_name','category_id','price','price_note','warranty_days','is_active','show_on_web'];
        $vals = array_map(fn($c) => $data[$c], $cols);
        if ($id) {
            // เก็บค่าเดิมไว้ให้ manager ย้อนได้
            $old_stmt = $pdo->prepare("SELECT " . implode(',', array_map(fn($c) => "`$c`", $cols)) . ", price AS _old_price FROM service_pricing WHERE id = ?");
            $old_stmt->execute([$id]);
            $old_row = $old_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $old_price = $old_row['_old_price'] ?? null;
            unset($old_row['_old_price']);

            $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
            $pdo->prepare("UPDATE service_pricing SET $set, updated_at = NOW() WHERE id = ?")->execute([...$vals, $id]);

            if ($old_price != $data['price'] || $old_row) {
                mgr_log($pdo, [
                    'action_type' => 'price_set',
                    'ref_table'   => 'service_pricing',
                    'ref_id'      => $id,
                    'summary'     => "แก้ราคา {$data['device_name']}: ฿" . number_format((float)$old_price) . " → ฿" . number_format((float)$data['price']),
                    'amount'      => (float)$data['price'],
                    'reversible'  => 1,
                    'payload'     => ['pricing_id' => (int)$id, 'old' => $old_row],
                ]);
            }
        } else {
            $ph  = implode(', ', array_fill(0, count($cols), '?'));
            $col = implode(', ', array_map(fn($c) => "`$c`", $cols));
            $pdo->prepare("INSERT INTO service_pricing ($col) VALUES ($ph)")->execute($vals);
            $new_pid = (int)$pdo->lastInsertId();
            mgr_log($pdo, [
                'action_type' => 'price_set',
                'ref_table'   => 'service_pricing',
                'ref_id'      => $new_pid,
                'summary'     => "เพิ่มราคาใหม่ {$data['device_name']}: ฿" . number_format((float)$data['price']),
                'amount'      => (float)$data['price'],
                'reversible'  => 1,
                'payload'     => ['pricing_id' => $new_pid, 'was_insert' => true],
            ]);
        }
        if ($isModal) {
            echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body><script>window.parent.postMessage('pricing-saved','*');</script></body></html>";
            exit;
        }
        header("Location: index.php?type=" . urlencode($data['device_type']));
        exit;
    }
}

if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM service_pricing WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $data = $row;
}

$categories   = $pdo->query("SELECT * FROM pricing_categories ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
$device_types = ['iPhone','iPad','MacBook','iMac','AirPods','Apple Watch','Software','Other'];

$suggestions = [
    'iPhone'      => ['iPhone 16 Pro Max','iPhone 16 Pro','iPhone 16 Plus','iPhone 16','iPhone 15 Pro Max','iPhone 15 Pro','iPhone 15 Plus','iPhone 15','iPhone 14 Pro Max','iPhone 14 Pro','iPhone 14 Plus','iPhone 14','iPhone 13 Pro Max','iPhone 13 Pro','iPhone 13','iPhone 12 Pro Max','iPhone 12 Pro','iPhone 12','iPhone 11 Pro Max','iPhone 11 Pro','iPhone 11','iPhone XS Max','iPhone XS','iPhone XR','iPhone X','iPhone SE (3rd gen)','iPhone SE (2nd gen)'],
    'iPad'        => ['iPad Pro M4 11"','iPad Pro M4 13"','iPad Pro M2 11"','iPad Pro M2 12.9"','iPad Air M2 11"','iPad Air M2 13"','iPad Air M1','iPad mini 7','iPad mini 6','iPad 10th gen','iPad 9th gen'],
    'MacBook'     => ['MacBook Air M3 13"','MacBook Air M3 15"','MacBook Air M2 13"','MacBook Air M2 15"','MacBook Air M1','MacBook Pro M3 Pro 14"','MacBook Pro M3 Pro 16"','MacBook Pro M3 Max 14"','MacBook Pro M3 Max 16"','MacBook Pro M2 Pro 14"','MacBook Pro M2 Pro 16"','MacBook Pro M1 Pro 14"','MacBook Pro M1 Pro 16"','MacBook Pro Intel 13"','MacBook Pro Intel 15"','MacBook Pro Intel 16"'],
    'iMac'        => ['iMac 24" M3','iMac 24" M1','iMac 27" 2020','iMac 21.5" 2019'],
    'AirPods'     => ['AirPods Pro 2nd gen','AirPods Pro 1st gen','AirPods 4th gen','AirPods 3rd gen','AirPods Max'],
    'Apple Watch' => ['Apple Watch Series 10','Apple Watch Series 9','Apple Watch Series 8','Apple Watch Ultra 2','Apple Watch SE 2nd gen'],
    'Software'    => ['macOS Reinstall','iOS Restore','Data Recovery','Virus Removal','Data Transfer'],
    'Other'       => [],
];
?>
<?php if ($isModal): ?>
<!DOCTYPE html><html lang="th">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('admin_theme')||'dark');</script>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
<link rel="stylesheet" href="/admin/templates/assets/css/admin.css?v=3">
<link rel="stylesheet" href="/admin/templates/assets/css/inventory-dashboard.css?v=3">
<?php else: ?>
<?php include __DIR__ . '/../templates/header_admin.php'; ?>
<link rel="stylesheet" href="<?= $assets_base ?>css/inventory-dashboard.css?v=3">
<?php endif; ?>

<style>
html,body{margin:0;padding:0;}
.modal-mode{height:100vh;overflow:hidden;display:flex;flex-direction:column;background:var(--bg-surface);}
#ifrm-header{display:flex;align-items:center;justify-content:space-between;padding:14px 22px;border-bottom:1px solid var(--border);background:var(--bg-surface);flex-shrink:0;}
#ifrm-header h2{margin:0;font-size:15px;font-weight:700;color:var(--text-main);display:flex;align-items:center;gap:8px;}
#ifrm-header h2 .material-symbols-rounded{font-size:18px;color:var(--primary);}
#ifrm-body{flex:1;overflow-y:auto;padding:20px 24px;}
.frm-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 16px; padding: 30px 32px; max-width: 660px; }
.frm-section { margin-bottom: 28px; }
.frm-section-title { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
.frm-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.frm-lbl { display: flex; align-items: center; gap: 5px; font-size: 0.87rem; font-weight: 600; color: var(--text-main); margin-bottom: 7px; }
.frm-lbl .material-symbols-rounded { font-size: 16px; color: var(--text-muted); }
.frm-ctrl { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 9px; font-size: 0.95rem; background: var(--bg-surface); color: var(--text-main); transition: 0.15s; box-sizing: border-box; }
.frm-ctrl:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
.frm-ctrl.err { border-color: #ef4444; }

.chk-group { display: flex; gap: 24px; }
.chk-item { display: flex; align-items: center; gap: 8px; }
.chk-item input[type="checkbox"] { width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer; }
.chk-item label { font-size: 0.9rem; font-weight: 600; color: var(--text-main); cursor: pointer; display: flex; align-items: center; gap: 5px; }

.err-list { background: rgba(239,68,68,0.08); color: #dc2626; padding: 12px 18px; border-radius: 9px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.2); }
.err-list li { font-size: 0.9rem; margin: 4px 0; }

.frm-actions { display: flex; gap: 12px; padding-top: 4px; }
</style>

<?php if ($isModal): ?>
<body class="modal-mode">
<div id="ifrm-header">
    <h2>
        <span class="material-symbols-rounded"><?= $id ? 'edit_square' : 'add_circle' ?></span>
        <?= $id ? 'แก้ไขราคา' : 'เพิ่มราคาใหม่' ?>
    </h2>
    <button type="button" onclick="window.parent.postMessage('pricing-close','*')"
        style="background:var(--bg-surface-alt);border:1px solid var(--border);width:34px;height:34px;border-radius:9px;cursor:pointer;color:var(--text-muted);display:flex;align-items:center;justify-content:center;transition:.2s;padding:0;"
        onmouseover="this.style.background='#ef4444';this.style.color='#fff'"
        onmouseout="this.style.background='var(--bg-surface-alt)';this.style.color='var(--text-muted)'">
        <span class="material-symbols-rounded" style="font-size:18px;">close</span>
    </button>
</div>
<div id="ifrm-body">
<?php else: ?>
<div class="cmns-wrapper">
    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color:var(--primary);">
                <span class="material-symbols-rounded" style="font-size:28px;"><?= $id ? 'edit_square' : 'add_circle' ?></span>
                <?= $id ? 'แก้ไขราคา' : 'เพิ่มราคาใหม่' ?>
            </h1>
        </div>
        <a href="index.php" class="cmns-btn cmns-btn-secondary" style="font-size:14px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span> กลับ
        </a>
    </div>
<?php endif; ?>

    <?php if ($errors): ?>
    <ul class="err-list" style="max-width:660px;list-style:none;margin:0 0 20px;padding:12px 18px;">
        <?php foreach ($errors as $e): ?>
        <li><span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">error</span> <?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <div class="frm-card" style="<?= $isModal ? 'max-width:100%;border:none;border-radius:0;padding:0;' : '' ?>">
        <form method="POST" id="pricing-form">

            <!-- Device info -->
            <div class="frm-section">
                <div class="frm-section-title">ข้อมูลอุปกรณ์</div>
                <div class="frm-row2">
                    <div>
                        <label class="frm-lbl">
                            <span class="material-symbols-rounded">devices</span>
                            ประเภทอุปกรณ์ *
                        </label>
                        <select name="device_type" id="inp-type" class="frm-ctrl" onchange="updateSuggestions(this.value)" required>
                            <?php foreach ($device_types as $dt): ?>
                            <option value="<?= $dt ?>" <?= $data['device_type'] === $dt ? 'selected' : '' ?>><?= $dt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="frm-lbl">
                            <span class="material-symbols-rounded">smartphone</span>
                            ชื่อรุ่น *
                        </label>
                        <input type="text" name="device_name" id="inp-name"
                            class="frm-ctrl <?= in_array('กรุณากรอกชื่อรุ่น', $errors) ? 'err' : '' ?>"
                            placeholder="เช่น iPhone 16 Pro"
                            value="<?= htmlspecialchars($data['device_name']) ?>"
                            list="device-suggestions" autocomplete="off" required>
                        <datalist id="device-suggestions"></datalist>
                    </div>
                </div>
            </div>

            <!-- Service & Price -->
            <div class="frm-section">
                <div class="frm-section-title">บริการและราคา</div>

                <div style="margin-bottom:16px;">
                    <label class="frm-lbl">
                        <span class="material-symbols-rounded">build_circle</span>
                        หมวดบริการ *
                    </label>
                    <select name="category_id" class="frm-ctrl <?= in_array('กรุณาเลือกหมวดบริการ', $errors) ? 'err' : '' ?>" required>
                        <option value="">— เลือกหมวดบริการ —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (int)$data['category_id'] === $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($categories)): ?>
                    <p style="font-size:0.82rem;color:#ef4444;margin:6px 0 0;">
                        ยังไม่มีหมวดบริการ —
                        <a href="categories.php" style="color:var(--primary);">สร้างหมวดก่อน</a>
                    </p>
                    <?php endif; ?>
                </div>

                <div class="frm-row2" style="margin-bottom:16px;">
                    <div>
                        <label class="frm-lbl">
                            <span class="material-symbols-rounded">payments</span>
                            ราคา (บาท) *
                        </label>
                        <input type="number" name="price" class="frm-ctrl" placeholder="0" min="0" value="<?= htmlspecialchars($data['price']) ?>" required>
                    </div>
                    <div>
                        <label class="frm-lbl">
                            <span class="material-symbols-rounded">verified_user</span>
                            ประกัน (วัน)
                        </label>
                        <input type="number" name="warranty_days" class="frm-ctrl" min="0" value="<?= htmlspecialchars($data['warranty_days']) ?>">
                    </div>
                </div>

                <div>
                    <label class="frm-lbl">
                        <span class="material-symbols-rounded">sticky_note_2</span>
                        หมายเหตุ
                    </label>
                    <input type="text" name="price_note" class="frm-ctrl"
                        placeholder="เช่น ราคาไม่รวมอะไหล่, ขึ้นอยู่กับความเสียหาย"
                        value="<?= htmlspecialchars($data['price_note'] ?? '') ?>">
                </div>
            </div>

            <!-- Visibility -->
            <div class="frm-section">
                <div class="frm-section-title">การแสดงผล</div>
                <div class="chk-group">
                    <div class="chk-item">
                        <input type="checkbox" name="show_on_web" id="chk-web" <?= $data['show_on_web'] ? 'checked' : '' ?>>
                        <label for="chk-web">
                            <span class="material-symbols-rounded" style="font-size:16px;color:#2563eb;">public</span>
                            แสดงบนเว็บไซต์
                        </label>
                    </div>
                    <div class="chk-item">
                        <input type="checkbox" name="is_active" id="chk-active" <?= $data['is_active'] ? 'checked' : '' ?>>
                        <label for="chk-active">
                            <span class="material-symbols-rounded" style="font-size:16px;color:#16a34a;">check_circle</span>
                            เปิดใช้งาน
                        </label>
                    </div>
                </div>
            </div>

            <div class="frm-actions">
                <button type="submit" class="cmns-btn cmns-btn-primary">
                    <span class="material-symbols-rounded" style="font-size:16px;">save</span>
                    <?= $id ? 'บันทึกการแก้ไข' : 'เพิ่มรายการ' ?>
                </button>
                <?php if (!$isModal): ?>
                <a href="index.php" class="cmns-btn cmns-btn-secondary">
                    <span class="material-symbols-rounded" style="font-size:16px;">close</span> ยกเลิก
                </a>
                <?php endif; ?>
            </div>

        </form>
    </div>

<?php if ($isModal): ?>
</div><!-- #ifrm-body -->
</body></html>
<?php else: ?>
</div><!-- .cmns-wrapper -->
<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
<?php endif; ?>

<script>
const suggestions = <?= json_encode($suggestions, JSON_UNESCAPED_UNICODE) ?>;
const editName = <?= json_encode($id ? $data['device_name'] : '') ?>;

function updateSuggestions(type) {
    const dl = document.getElementById('device-suggestions');
    dl.innerHTML = '';
    (suggestions[type] || []).forEach(name => {
        const opt = document.createElement('option');
        opt.value = name;
        dl.appendChild(opt);
    });
    if (!editName) {
        document.getElementById('inp-name').value = '';
        document.getElementById('inp-name').focus();
    }
}

// Init
updateSuggestions(document.getElementById('inp-type').value);
if (editName) document.getElementById('inp-name').value = editName;

<?php if ($isModal): ?>
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') window.parent.postMessage('pricing-close', '*');
});
<?php endif; ?>
</script>
