<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$users = $pdo->query("SELECT id, username, role, created_at FROM admin_users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$role_count = [];
foreach ($users as $u) $role_count[$u['role']] = ($role_count[$u['role']] ?? 0) + 1;

$ROLE_META = [
    'super_admin' => ['label' => 'Super Admin', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,.12)', 'border' => 'rgba(139,92,246,.3)'],
    'manager'     => ['label' => 'Manager',     'color' => '#3b82f6', 'bg' => 'rgba(37,99,235,.1)',   'border' => 'rgba(37,99,235,.25)'],
    'admin'       => ['label' => 'Admin',        'color' => '#0ea5e9', 'bg' => 'rgba(14,165,233,.1)', 'border' => 'rgba(14,165,233,.25)'],
    'staff'       => ['label' => 'Staff',        'color' => '#10b981', 'bg' => 'rgba(16,185,129,.1)', 'border' => 'rgba(16,185,129,.25)'],
    'viewer'      => ['label' => 'Viewer',       'color' => '#9ca3af', 'bg' => 'rgba(156,163,175,.1)','border' => 'rgba(156,163,175,.25)'],
];

$pageTitle = 'จัดการผู้ใช้งาน';
include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=9">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=9">
<link rel="stylesheet" href="../templates/assets/css/modal.css?v=9">
<style>
.t-btn{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-muted);cursor:pointer;transition:all .18s;text-decoration:none;padding:0;}
.t-btn .material-symbols-rounded{font-size:16px;line-height:1;}
.t-btn:hover{transform:translateY(-1px);box-shadow:0 3px 8px rgba(0,0,0,.1);}
.t-edit:hover{color:var(--primary);background:rgba(37,99,235,.07);border-color:var(--primary);}
.t-del:hover{color:#ef4444;background:rgba(239,68,68,.07);border-color:#ef4444;}
.user-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0;text-transform:uppercase;}
.role-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.3px;border:1px solid transparent;white-space:nowrap;}
/* Delete confirm */
.usr-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1100;display:none;align-items:center;justify-content:center;opacity:0;transition:opacity .15s;}
.usr-modal-overlay.show{display:flex;opacity:1;}
.usr-modal{background:var(--bg-surface);width:90%;max-width:400px;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.25);overflow:hidden;border:1px solid var(--border);transform:scale(.95);transition:transform .2s;}
.usr-modal-overlay.show .usr-modal{transform:scale(1);}
.usr-modal-header{padding:22px 20px 14px;text-align:center;background:rgba(239,68,68,.06);border-bottom:1px solid var(--border);}
.usr-modal-icon{width:46px;height:46px;border-radius:50%;background:rgba(239,68,68,.12);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;}
.usr-modal-header h3{margin:0;font-size:16px;font-weight:700;color:#dc2626;}
.usr-modal-body{padding:18px 20px;text-align:center;color:var(--text-main);font-size:14px;line-height:1.65;}
.usr-modal-footer{padding:14px 20px;border-top:1px solid var(--border);background:var(--bg-surface-alt);display:flex;gap:10px;justify-content:center;}
.usr-btn-cancel{padding:9px 20px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface);color:var(--text-main);font-weight:600;cursor:pointer;transition:.2s;font-family:'Sarabun',sans-serif;font-size:14px;}
.usr-btn-cancel:hover{background:var(--bg-surface-alt);}
.usr-btn-confirm{padding:9px 20px;border-radius:8px;border:none;background:#ef4444;color:#fff;font-weight:700;cursor:pointer;transition:.2s;font-family:'Sarabun',sans-serif;font-size:14px;box-shadow:0 3px 10px rgba(239,68,68,.3);}
.usr-btn-confirm:hover{background:#dc2626;transform:translateY(-1px);}
</style>

<div class="cmns-wrapper">

    <!-- Header -->
    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color:var(--primary);">
                <span class="material-symbols-rounded" style="font-size:32px;">manage_accounts</span>
                จัดการผู้ใช้งาน
            </h1>
            <p style="color:var(--text-muted);margin-top:5px;font-size:13px;">
                ระบบสิทธิ์ Admin · ทั้งหมด <b><?= count($users) ?></b> บัญชี
            </p>
        </div>
        <div class="cmns-action-buttons">
            <button type="button" onclick="openUserModal('form.php?modal=1')" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">person_add</span> เพิ่มผู้ใช้งาน
            </button>
        </div>
    </div>

    <?php if ($flash): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({ icon:'success', title: <?= json_encode($flash) ?>, toast:true, position:'top-end',
            showConfirmButton:false, timer:3000, timerProgressBar:true });
    });
    </script>
    <?php endif; ?>

    <!-- Stats -->
    <div class="log-stats" style="margin-bottom:24px;">
        <div class="log-stat-card stat-accent-blue">
            <span class="stat-label">ทั้งหมด</span>
            <span class="stat-value"><?= count($users) ?></span>
            <span class="stat-sub">บัญชีในระบบ</span>
        </div>
        <div class="log-stat-card stat-accent-purple">
            <span class="stat-label">Super Admin</span>
            <span class="stat-value"><?= $role_count['super_admin'] ?? 0 ?></span>
            <span class="stat-sub">สิทธิ์สูงสุด</span>
        </div>
        <div class="log-stat-card stat-accent-blue">
            <span class="stat-label">Manager</span>
            <span class="stat-value"><?= $role_count['manager'] ?? 0 ?></span>
            <span class="stat-sub">ผู้จัดการ</span>
        </div>
        <div class="log-stat-card stat-accent-green">
            <span class="stat-label">Staff</span>
            <span class="stat-value"><?= ($role_count['staff'] ?? 0) + ($role_count['admin'] ?? 0) + ($role_count['viewer'] ?? 0) ?></span>
            <span class="stat-sub">พนักงาน / ทั่วไป</span>
        </div>
    </div>

    <!-- Table -->
    <div class="log-card">
        <div style="overflow-x:auto;">
        <table class="log-table">
            <thead>
                <tr>
                    <th style="width:50px;text-align:center;">#</th>
                    <th>ผู้ใช้งาน</th>
                    <th style="width:140px;text-align:center;">สิทธิ์</th>
                    <th style="width:130px;text-align:center;">วันที่สร้าง</th>
                    <th style="width:80px;text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($users): foreach ($users as $u):
                $meta = $ROLE_META[$u['role']] ?? $ROLE_META['viewer'];
                $initial = mb_strtoupper(mb_substr($u['username'], 0, 1));
                $isSelf = ((int)$u['id'] === (int)$_SESSION['admin_id']);
            ?>
            <tr id="user-row-<?= $u['id'] ?>">
                <td style="text-align:center;color:var(--text-muted);font-size:12px;"><?= $u['id'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="user-avatar" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>;border:1px solid <?= $meta['border'] ?>;">
                            <?= h($initial) ?>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:14px;color:var(--text-main);">
                                <?= h($u['username']) ?>
                                <?php if ($isSelf): ?>
                                    <span style="font-size:10px;font-weight:600;color:var(--primary);background:rgba(37,99,235,.1);padding:1px 7px;border-radius:10px;margin-left:4px;">คุณ</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:1px;">ID: <?= $u['id'] ?></div>
                        </div>
                    </div>
                </td>
                <td style="text-align:center;">
                    <span class="role-badge" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>;border-color:<?= $meta['border'] ?>;">
                        <?= $meta['label'] ?>
                    </span>
                </td>
                <td style="text-align:center;color:var(--text-muted);font-size:12px;">
                    <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                </td>
                <td style="text-align:center;">
                    <div style="display:flex;align-items:center;justify-content:center;gap:5px;">
                        <button type="button" class="t-btn t-edit" title="แก้ไข"
                                onclick="openUserModal('form.php?id=<?= $u['id'] ?>&modal=1')">
                            <span class="material-symbols-rounded">edit</span>
                        </button>
                        <?php if (!$isSelf): ?>
                        <button type="button" class="t-btn t-del" title="ลบ"
                                onclick="openDeleteConfirm(<?= $u['id'] ?>, '<?= h($u['username']) ?>')">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                        <?php else: ?>
                        <button type="button" class="t-btn" title="ลบตัวเองไม่ได้" disabled
                                style="opacity:.3;cursor:not-allowed;">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-muted);">ไม่มีข้อมูล</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</div><!-- .cmns-wrapper -->

<!-- User Form Modal -->
<div id="modal-user" class="cmns-modal">
    <div class="modal-content" style="width:min(540px,calc(100vw - 40px));max-width:none;max-height:none;padding:0;overflow:hidden;border-radius:16px;flex-shrink:0;">
        <iframe id="user-iframe" src="" style="width:100%;height:auto;min-height:480px;border:none;display:block;background:var(--bg-surface);"></iframe>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="usr-del-modal" class="usr-modal-overlay">
    <div class="usr-modal">
        <div class="usr-modal-header">
            <div class="usr-modal-icon"><span class="material-symbols-rounded" style="font-size:24px;">warning</span></div>
            <h3>ลบผู้ใช้งาน?</h3>
        </div>
        <div class="usr-modal-body">
            ลบบัญชี <strong id="del-username">...</strong> ออกจากระบบ?<br>
            <span style="font-size:13px;color:#ef4444;">ไม่สามารถย้อนกลับได้</span>
        </div>
        <div class="usr-modal-footer">
            <button type="button" class="usr-btn-cancel" onclick="closeDeleteConfirm()">ยกเลิก</button>
            <button id="del-confirm-btn" type="button" class="usr-btn-confirm" onclick="doDelete()">ลบเลย</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
let _delId = null;
function openDeleteConfirm(id, name) {
    _delId = id;
    document.getElementById('del-username').textContent = name;
    const m = document.getElementById('usr-del-modal');
    m.style.display = 'flex';
    requestAnimationFrame(() => m.classList.add('show'));
}
function closeDeleteConfirm() {
    const m = document.getElementById('usr-del-modal');
    m.classList.remove('show');
    setTimeout(() => { m.style.display = 'none'; }, 150);
}
function doDelete() {
    if (!_delId) return;
    const btn = document.getElementById('del-confirm-btn');
    btn.disabled = true; btn.textContent = 'กำลังลบ...';
    fetch('delete.php?id=' + _delId + '&ajax=1')
        .then(r => r.json())
        .then(d => {
            closeDeleteConfirm();
            if (d.ok) {
                const row = document.getElementById('user-row-' + _delId);
                if (row) {
                    row.style.transition = 'opacity .25s,transform .25s';
                    row.style.opacity = '0'; row.style.transform = 'translateX(30px)';
                    setTimeout(() => row.remove(), 260);
                }
                Swal.fire({ icon:'success', title:'ลบผู้ใช้งานเรียบร้อยแล้ว', toast:true, position:'top-end',
                    showConfirmButton:false, timer:3000, timerProgressBar:true });
            }
            btn.disabled = false; btn.textContent = 'ลบเลย';
        });
}
document.getElementById('usr-del-modal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteConfirm();
});

function openUserModal(url) {
    document.getElementById('user-iframe').src = url;
    document.getElementById('modal-user').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeUserModal() {
    document.getElementById('modal-user').classList.remove('show');
    document.body.style.overflow = '';
    setTimeout(() => document.getElementById('user-iframe').src = '', 300);
}
document.getElementById('modal-user').addEventListener('click', function(e) {
    if (e.target === this) closeUserModal();
});
window.addEventListener('message', function(e) {
    if (e.data === 'user-saved') {
        closeUserModal();
        location.reload();
    }
});
</script>
