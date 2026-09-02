<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_perms(['pricing.write']); // จัดการหมวดบริการ/ราคา: ผู้จัดการ+ เท่านั้น

$pageTitle = "หมวดบริการ";
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $order = intval($_POST['sort_order'] ?? 0);
        if ($name) {
            $pdo->prepare("INSERT INTO pricing_categories (name, sort_order) VALUES (?, ?)")->execute([$name, $order]);
            $msg = "เพิ่มหมวด \"$name\" แล้ว";
            $msgType = 'success';
        }
    }

    if ($action === 'edit') {
        $eid   = intval($_POST['id']);
        $name  = trim($_POST['name'] ?? '');
        $order = intval($_POST['sort_order'] ?? 0);
        if ($eid && $name) {
            $pdo->prepare("UPDATE pricing_categories SET name = ?, sort_order = ? WHERE id = ?")->execute([$name, $order, $eid]);
            $msg = 'แก้ไขเรียบร้อย';
            $msgType = 'success';
        }
    }

    if ($action === 'delete') {
        $did  = intval($_POST['id']);
        $used = $pdo->prepare("SELECT COUNT(*) FROM service_pricing WHERE category_id = ?");
        $used->execute([$did]);
        if ($used->fetchColumn() > 0) {
            $msg = 'ไม่สามารถลบได้ — มีรายการราคาใช้หมวดนี้อยู่';
            $msgType = 'error';
        } else {
            $pdo->prepare("DELETE FROM pricing_categories WHERE id = ?")->execute([$did]);
            $msg = 'ลบแล้ว';
            $msgType = 'success';
        }
    }
}

$categories = $pdo->query("SELECT pc.*, COUNT(sp.id) AS cnt
    FROM pricing_categories pc
    LEFT JOIN service_pricing sp ON sp.category_id = pc.id
    GROUP BY pc.id ORDER BY pc.sort_order, pc.id")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../templates/header_admin.php';
?>

<link rel="stylesheet" href="<?= $assets_base ?>css/inventory-dashboard.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-dashboard.css') ?>">

<style>
.cat-layout { display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start; }
.cat-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: 14px; padding: 24px; }
.cat-card-title { font-size: 0.9rem; font-weight: 700; color: var(--text-main); margin: 0 0 16px; display: flex; align-items: center; gap: 8px; }

.cat-row { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 8px; transition: 0.15s; background: var(--bg-surface); }
.cat-row:hover { border-color: var(--primary); background: var(--primary-light); }
.cat-order { font-size: 0.78rem; color: var(--text-muted); min-width: 26px; text-align: right; }
.cat-name  { flex: 1; font-weight: 600; color: var(--text-main); font-size: 0.92rem; }
.cat-cnt   { font-size: 0.75rem; background: var(--bg-surface-alt); color: var(--text-muted); padding: 2px 9px; border-radius: 12px; border: 1px solid var(--border); }

.ic-btn { display: inline-flex; align-items: center; padding: 4px 6px; border-radius: 6px; border: none; background: none; cursor: pointer; transition: 0.15s; }
.ic-edit { color: #f59e0b; } .ic-edit:hover { background: rgba(245,158,11,0.1); }
.ic-del  { color: #ef4444; } .ic-del:hover  { background: rgba(239,68,68,0.1); }
.ic-dis  { color: var(--border); cursor: not-allowed; }

.edit-box { display: none; background: var(--bg-surface-alt); border-top: 1px solid var(--border); padding: 10px 14px; border-radius: 0 0 10px 10px; margin-top: 6px; }
.edit-box.open { display: flex; gap: 8px; align-items: center; }

.frm-lbl { display: block; font-size: 0.83rem; font-weight: 600; color: var(--text-main); margin-bottom: 6px; }
.frm-ctrl { width: 100%; padding: 9px 13px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.92rem; background: var(--bg-surface); color: var(--text-main); box-sizing: border-box; }
.frm-ctrl:focus { outline: none; border-color: var(--primary); }

.flash { padding: 12px 16px; border-radius: 9px; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 0.9rem; }
.flash-ok  { background: rgba(22,163,74,0.08); color: #15803d; border: 1px solid rgba(22,163,74,0.2); }
.flash-err { background: rgba(239,68,68,0.08); color: #dc2626; border: 1px solid rgba(239,68,68,0.2); }
</style>

<div class="cmns-wrapper">

    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color:var(--primary);">
                <span class="material-symbols-rounded" style="font-size:28px;">category</span>
                หมวดบริการ
            </h1>
            <p style="color:var(--text-muted);margin-top:4px;font-size:13px;">จัดการประเภทการซ่อม</p>
        </div>
        <a href="index.php" class="cmns-btn cmns-btn-secondary" style="font-size:14px;">
            <span class="material-symbols-rounded" style="font-size:16px;">arrow_back</span> กลับรายการราคา
        </a>
    </div>

    <?php if ($msg): ?>
    <div class="flash <?= $msgType === 'success' ? 'flash-ok' : 'flash-err' ?>">
        <span class="material-symbols-rounded" style="font-size:18px;"><?= $msgType === 'success' ? 'check_circle' : 'error' ?></span>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="cat-layout">

        <!-- List -->
        <div class="cat-card">
            <h3 class="cat-card-title">
                <span class="material-symbols-rounded" style="font-size:18px;color:var(--primary);">list</span>
                หมวดทั้งหมด (<?= count($categories) ?>)
            </h3>

            <?php if (empty($categories)): ?>
            <p style="color:var(--text-muted);text-align:center;padding:30px;">ยังไม่มีหมวดบริการ</p>
            <?php endif; ?>

            <?php foreach ($categories as $cat): ?>
            <div>
                <div class="cat-row" id="cat-row-<?= $cat['id'] ?>">
                    <span class="cat-order"><?= $cat['sort_order'] ?></span>
                    <span class="cat-name"><?= htmlspecialchars($cat['name']) ?></span>
                    <span class="cat-cnt"><?= $cat['cnt'] ?> รายการ</span>
                    <button class="ic-btn ic-edit" onclick="openEdit(<?= $cat['id'] ?>, <?= htmlspecialchars(json_encode($cat['name'])) ?>, <?= $cat['sort_order'] ?>)" title="แก้ไข">
                        <span class="material-symbols-rounded" style="font-size:17px;">edit</span>
                    </button>
                    <?php if ($cat['cnt'] == 0): ?>
                    <form method="POST" onsubmit="return confirm('ลบหมวด \'<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>\' ?')" style="display:inline;margin:0;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <button type="submit" class="ic-btn ic-del" title="ลบ">
                            <span class="material-symbols-rounded" style="font-size:17px;">delete</span>
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="ic-btn ic-dis" title="ไม่สามารถลบได้ (มีรายการใช้งาน)" disabled>
                        <span class="material-symbols-rounded" style="font-size:17px;">delete</span>
                    </button>
                    <?php endif; ?>
                </div>
                <div id="edit-<?= $cat['id'] ?>" class="edit-box">
                    <form method="POST" style="display:flex;gap:8px;align-items:center;flex:1;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <input type="text" name="name" class="frm-ctrl" style="flex:1;min-width:120px;" placeholder="ชื่อหมวด" required>
                        <input type="number" name="sort_order" class="frm-ctrl" style="width:75px;" placeholder="ลำดับ" min="0">
                        <button type="submit" class="cmns-btn cmns-btn-primary" style="font-size:13px;padding:8px 16px;">บันทึก</button>
                        <button type="button" onclick="closeEdit(<?= $cat['id'] ?>)" class="cmns-btn cmns-btn-secondary" style="font-size:13px;padding:8px 16px;">ยกเลิก</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Add form -->
        <div class="cat-card">
            <h3 class="cat-card-title">
                <span class="material-symbols-rounded" style="font-size:18px;color:#8b5cf6;">add_circle</span>
                เพิ่มหมวดใหม่
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div style="margin-bottom:14px;">
                    <label class="frm-lbl">ชื่อหมวดบริการ *</label>
                    <input type="text" name="name" class="frm-ctrl" placeholder="เช่น เปลี่ยนจอ" required>
                </div>
                <div style="margin-bottom:18px;">
                    <label class="frm-lbl">ลำดับ (sort)</label>
                    <input type="number" name="sort_order" class="frm-ctrl" value="0" min="0">
                </div>
                <button type="submit" class="cmns-btn cmns-btn-primary" style="width:100%;justify-content:center;">
                    <span class="material-symbols-rounded" style="font-size:16px;">add</span> เพิ่มหมวด
                </button>
            </form>

            <div style="margin-top:20px;padding:14px;background:var(--bg-surface-alt);border-radius:10px;font-size:0.8rem;color:var(--text-muted);line-height:1.7;border:1px solid var(--border);">
                <strong style="color:var(--text-main);">ตัวอย่างหมวด:</strong><br>
                เปลี่ยนจอ · เปลี่ยนแบตเตอรี่ · เปลี่ยนกระจกหลัง · ซ่อม Logic Board · ซ่อมกล้อง · เปลี่ยนพอร์ตชาร์จ · ซ่อมลำโพง · อัปเกรด RAM/SSD · ล้างเครื่อง
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
function openEdit(id, name, order) {
    document.querySelectorAll('.edit-box').forEach(b => b.classList.remove('open'));
    const box = document.getElementById('edit-' + id);
    box.querySelector('[name="name"]').value   = name;
    box.querySelector('[name="sort_order"]').value = order;
    box.classList.add('open');
    box.querySelector('[name="name"]').focus();
}
function closeEdit(id) {
    document.getElementById('edit-' + id).classList.remove('open');
}
</script>
