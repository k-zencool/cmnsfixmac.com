<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function time_ago($dt) {
    if (!$dt) return '—';
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'เมื่อสักครู่';
    if ($diff < 3600) return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return floor($diff / 3600) . ' ชม.ที่แล้ว';
    return floor($diff / 86400) . ' วันที่แล้ว';
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$form_errors = [];

// ── Handle POST (inline form save) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {
    $action  = $_POST['_action']; // 'add' | 'edit'
    $edit_id = (int)($_POST['edit_id'] ?? 0);

    if (!hash_equals($CSRF, $_POST['csrf_token'] ?? '')) {
        $form_errors[] = 'CSRF token ไม่ถูกต้อง';
    } else {
        $username         = trim($_POST['username'] ?? '');
        $role             = trim($_POST['role']     ?? '');
        $full_name        = trim($_POST['full_name'] ?? '');
        $phone            = trim($_POST['phone']     ?? '');
        $email            = trim($_POST['email']     ?? '');
        $password         = $_POST['password']         ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!$username) $form_errors[] = 'กรุณากรอก Username';
        if (!$role)     $form_errors[] = 'กรุณาเลือก Role';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $form_errors[] = 'อีเมลไม่ถูกต้อง';

        $pw_hash = null;
        if (!empty($password)) {
            if ($password !== $confirm_password) $form_errors[] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
            elseif (strlen($password) < 6)        $form_errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัว';
            else $pw_hash = password_hash($password, PASSWORD_BCRYPT);
        } elseif ($action === 'add') {
            $form_errors[] = 'กรุณากรอกรหัสผ่านสำหรับผู้ใช้ใหม่';
        }

        // กันแก้ role ของตัวเอง
        if ($action === 'edit' && $edit_id === (int)$_SESSION['admin_id']) {
            $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
            $stmt->execute([$edit_id]);
            $role = $stmt->fetchColumn() ?: $role; // บังคับใช้ role เดิมเสมอ ไม่สนใจค่าที่ส่งมา
        }

        if (empty($form_errors)) {
            try {
                if ($action === 'edit' && $edit_id) {
                    if ($pw_hash)
                        $pdo->prepare("UPDATE admin_users SET username=?, role=?, full_name=?, phone=?, email=?, password=? WHERE id=?")
                            ->execute([$username, $role, $full_name ?: null, $phone ?: null, $email ?: null, $pw_hash, $edit_id]);
                    else
                        $pdo->prepare("UPDATE admin_users SET username=?, role=?, full_name=?, phone=?, email=? WHERE id=?")
                            ->execute([$username, $role, $full_name ?: null, $phone ?: null, $email ?: null, $edit_id]);
                } else {
                    $pdo->prepare("INSERT INTO admin_users (username, role, full_name, phone, email, password) VALUES (?,?,?,?,?,?)")
                        ->execute([$username, $role, $full_name ?: null, $phone ?: null, $email ?: null, $pw_hash]);
                }
                $_SESSION['flash'] = $action === 'edit' ? 'อัปเดตผู้ใช้งานเรียบร้อยแล้ว' : 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว';
                header('Location: index.php'); exit;
            } catch (PDOException $e) {
                $form_errors[] = $e->errorInfo[1] == 1062
                    ? "Username '" . h($username) . "' นี้มีผู้ใช้งานแล้ว"
                    : 'Database Error: ' . $e->getMessage();
            }
        }
    }
}

// ── Filters (GET, explicit apply — ไม่ auto-submit ระหว่างพิมพ์) ──
$q            = trim($_GET['q'] ?? '');
$roleFilter   = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($roleFilter !== '') { $where[] = "role = ?"; $params[] = $roleFilter; }
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("SELECT * FROM admin_users $where_sql ORDER BY id ASC");
$stmt->execute($params);
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Active sessions ต่อ admin_id (dataset เล็ก ทำ map ใน PHP ง่ายกว่า SQL ซับซ้อน) ──
$sessions_by_admin = [];
try {
    $sess_rows = $pdo->query("SELECT * FROM admin_sessions WHERE revoked_at IS NULL ORDER BY last_seen_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sess_rows as $s) {
        $sessions_by_admin[$s['admin_id']][] = $s;
    }
} catch (Throwable $e) {
    // ยังไม่ได้รัน migration_admin_sessions.sql — แสดงหน้าได้ปกติ แค่ไม่มีข้อมูลออนไลน์
    $sessions_by_admin = [];
}

$ONLINE_THRESHOLD = 5 * 60; // วินาที
$now_ts = time();

// ── รวม sessions + คำนวณสถานะออนไลน์ + apply status filter (ทำใน PHP เพราะ dataset เล็ก) ──
$users = [];
$online_count = 0;
foreach ($all_users as $u) {
    $my_sessions = $sessions_by_admin[$u['id']] ?? [];
    $is_online = false;
    foreach ($my_sessions as $s) {
        if ((strtotime($s['last_seen_at']) + $ONLINE_THRESHOLD) >= $now_ts) { $is_online = true; break; }
    }
    $u['_sessions']  = $my_sessions;
    $u['_is_online'] = $is_online;
    $is_active = (int)($u['is_active'] ?? 1) === 1;

    if ($statusFilter === 'online'   && !$is_online) continue;
    if ($statusFilter === 'offline'  && ($is_online || !$is_active)) continue;
    if ($statusFilter === 'inactive' && $is_active) continue;

    if ($is_online) $online_count++;
    $users[] = $u;
}

$role_count = [];
foreach ($all_users as $u) $role_count[$u['role']] = ($role_count[$u['role']] ?? 0) + 1;
$active_count   = count(array_filter($all_users, fn($u) => (int)($u['is_active'] ?? 1) === 1));
$inactive_count = count($all_users) - $active_count;

$ROLE_META = [
    'super_admin' => ['color'=>'#8b5cf6','bg'=>'rgba(139,92,246,.12)','border'=>'rgba(139,92,246,.3)'],
    'manager'     => ['color'=>'#3b82f6','bg'=>'rgba(37,99,235,.1)',  'border'=>'rgba(37,99,235,.25)'],
    'admin'       => ['color'=>'#0ea5e9','bg'=>'rgba(14,165,233,.1)', 'border'=>'rgba(14,165,233,.25)'],
    'staff'       => ['color'=>'#10b981','bg'=>'rgba(16,185,129,.1)', 'border'=>'rgba(16,185,129,.25)'],
    'viewer'      => ['color'=>'#9ca3af','bg'=>'rgba(156,163,175,.1)','border'=>'rgba(156,163,175,.25)'],
];
$ROLES = ['super_admin'=>'Super Admin','manager'=>'Manager','admin'=>'Admin','staff'=>'Staff','viewer'=>'Viewer'];

$pageTitle = 'จัดการผู้ใช้งาน';
include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-logs.css') ?>">
<style>
.t-btn{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-muted);cursor:pointer;transition:all .18s;text-decoration:none;padding:0;}
.t-btn .material-symbols-rounded{font-size:16px;line-height:1;}
.t-btn:hover{transform:translateY(-1px);box-shadow:0 3px 8px rgba(0,0,0,.1);}
.t-edit:hover{color:var(--primary);background:rgba(37,99,235,.07);border-color:var(--primary);}
.t-del:hover{color:#ef4444;background:rgba(239,68,68,.07);border-color:#ef4444;}
.t-act:hover{color:#10b981;background:rgba(16,185,129,.07);border-color:#10b981;}
.user-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0;text-transform:uppercase;overflow:hidden;}
.user-avatar img{width:100%;height:100%;object-fit:cover;}
.role-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.3px;border:1px solid transparent;white-space:nowrap;}
.online-dot{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:800;}
.online-dot::before{content:"";width:7px;height:7px;border-radius:50%;}
.online-dot.on::before{background:#10b981;box-shadow:0 0 5px rgba(16,185,129,.6);}
.online-dot.on{color:#10b981;}
.online-dot.off::before{background:var(--text-muted);}
.online-dot.off{color:var(--text-muted);}
.online-dot.inactive::before{background:#ef4444;}
.online-dot.inactive{color:#ef4444;}
.sess-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-main);font-size:12px;font-weight:700;cursor:pointer;}
.sess-btn:hover{border-color:var(--primary);color:var(--primary);}
.sess-btn .material-symbols-rounded{font-size:14px;}
.sess-drawer{display:none;background:var(--bg-surface-alt);}
.sess-drawer.open{display:table-row;}
.sess-drawer td{padding:0 !important;}
.sess-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 20px;border-bottom:1px dashed var(--border);}
.sess-item:last-child{border-bottom:none;}
.sess-item .dev{font-weight:700;font-size:13px;color:var(--text-main);}
.sess-item .meta{font-size:11px;color:var(--text-muted);margin-top:2px;}
.kill-btn{padding:6px 12px;border-radius:8px;border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.08);color:#ef4444;font-weight:700;font-size:11px;cursor:pointer;white-space:nowrap;}
.kill-btn:hover{background:#ef4444;color:#fff;}
.kill-btn:disabled{opacity:.4;cursor:not-allowed;}
/* Shared modal overlay */
.usr-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:none;align-items:center;justify-content:center;opacity:0;transition:opacity .18s;}
.usr-overlay.show{display:flex;opacity:1;}
.usr-box{background:var(--bg-surface);width:90%;max-width:520px;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.3);border:1px solid var(--border);overflow:hidden;transform:scale(.96);transition:transform .18s;max-height:90vh;overflow-y:auto;}
.usr-overlay.show .usr-box{transform:scale(1);}
.usr-form-header{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--bg-surface);}
.usr-form-header h3{margin:0;font-size:16px;font-weight:700;color:var(--text-main);display:flex;align-items:center;gap:8px;}
.usr-form-body{padding:16px 20px;}
.usr-form-footer{padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-surface-alt);display:flex;justify-content:space-between;align-items:center;}
.usr-label{font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:block;text-transform:uppercase;letter-spacing:.4px;}
.usr-input,.usr-select{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background:var(--bg-surface);color:var(--text-main);box-sizing:border-box;transition:border-color .15s;font-family:'Sarabun',sans-serif;}
.usr-input:focus,.usr-select:focus{outline:none;border-color:var(--primary);}
.usr-field{margin-bottom:12px;}
.usr-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.usr-hint{font-size:11px;color:var(--text-muted);margin-top:3px;}
.usr-pw-wrap{position:relative;}
.usr-pw-wrap .usr-input{padding-right:40px;}
.usr-pw-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);padding:0;display:flex;align-items:center;}
.usr-pw-toggle .material-symbols-rounded{font-size:18px;}
.usr-pw-toggle:hover{color:var(--text-main);}
.usr-error{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:8px 12px;border-radius:8px;margin-bottom:12px;font-size:12px;line-height:1.6;}
/* Confirm modal (deactivate / reactivate / hard-delete) */
.del-modal-header{padding:20px 20px 12px;text-align:center;background:rgba(239,68,68,.06);border-bottom:1px solid var(--border);}
.del-modal-header.warn{background:rgba(245,158,11,.08);}
.del-modal-icon{width:44px;height:44px;border-radius:50%;background:rgba(239,68,68,.12);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;}
.del-modal-icon.warn{background:rgba(245,158,11,.15);color:#f59e0b;}
.del-modal-body{padding:16px 20px;text-align:center;color:var(--text-main);font-size:14px;line-height:1.65;}
.del-modal-footer{padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-surface-alt);display:flex;gap:8px;justify-content:center;}
.usr-btn-cancel{padding:8px 18px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface);color:var(--text-main);font-weight:600;cursor:pointer;font-family:'Sarabun',sans-serif;font-size:13px;}
.usr-btn-confirm{padding:8px 18px;border-radius:8px;border:none;background:#ef4444;color:#fff;font-weight:700;cursor:pointer;font-family:'Sarabun',sans-serif;font-size:13px;}
.usr-btn-confirm.warn{background:#f59e0b;}
.usr-btn-save{padding:9px 22px;border-radius:8px;border:none;background:var(--primary);color:#fff;font-weight:700;cursor:pointer;font-family:'Sarabun',sans-serif;font-size:14px;display:inline-flex;align-items:center;gap:6px;}
</style>

<div class="cmns-wrapper">

    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color:var(--primary);">
                <span class="material-symbols-rounded" style="font-size:32px;">manage_accounts</span>
                จัดการผู้ใช้งาน
            </h1>
            <p style="color:var(--text-muted);margin-top:5px;font-size:13px;">
                ระบบสิทธิ์ Admin · ทั้งหมด <b><?= count($all_users) ?></b> บัญชี
            </p>
        </div>
        <div class="cmns-action-buttons">
            <button type="button" onclick="openAddModal()" class="cmns-btn cmns-btn-primary">
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
            <span class="stat-value"><?= count($all_users) ?></span>
            <span class="stat-sub">บัญชีในระบบ</span>
        </div>
        <div class="log-stat-card stat-accent-green">
            <span class="stat-label">ออนไลน์ตอนนี้</span>
            <span class="stat-value"><?= $online_count ?></span>
            <span class="stat-sub">ใช้งานภายใน 5 นาที</span>
        </div>
        <div class="log-stat-card stat-accent-purple">
            <span class="stat-label">Super Admin</span>
            <span class="stat-value"><?= $role_count['super_admin'] ?? 0 ?></span>
            <span class="stat-sub">สิทธิ์สูงสุด</span>
        </div>
        <div class="log-stat-card stat-accent-red">
            <span class="stat-label">ปิดใช้งาน</span>
            <span class="stat-value"><?= $inactive_count ?></span>
            <span class="stat-sub">บัญชีถูกระงับ</span>
        </div>
    </div>

    <!-- Filter bar -->
    <form method="GET" action="index.php">
        <div class="log-filter-bar">
            <div class="log-filter-group" style="flex:1; min-width:220px;">
                <label>ค้นหา</label>
                <div class="log-search-wrap">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Username / ชื่อ / อีเมล / เบอร์">
                </div>
            </div>
            <div class="log-filter-group">
                <label>สิทธิ์</label>
                <select name="role">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($ROLES as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $roleFilter === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="log-filter-group">
                <label>สถานะ</label>
                <select name="status">
                    <option value="">ทั้งหมด</option>
                    <option value="online"   <?= $statusFilter === 'online'   ? 'selected' : '' ?>>ออนไลน์</option>
                    <option value="offline"  <?= $statusFilter === 'offline'  ? 'selected' : '' ?>>ออฟไลน์</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option>
                </select>
            </div>
            <button type="submit" class="btn-filter" title="ค้นหา">
                <span class="material-symbols-rounded" style="font-size:16px;">search</span> ค้นหา
            </button>
            <?php if ($q !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
            <a href="index.php" class="btn-reset" title="ล้างค่าทั้งหมด">
                <span class="material-symbols-rounded" style="font-size:16px;">close</span>
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <div class="log-card">
        <div style="overflow-x:auto;">
        <table class="log-table">
            <thead>
                <tr>
                    <th style="width:50px;text-align:center;">#</th>
                    <th>ผู้ใช้งาน</th>
                    <th style="width:120px;text-align:center;">สิทธิ์</th>
                    <th style="width:110px;text-align:center;">สถานะ</th>
                    <th style="width:120px;text-align:center;">อุปกรณ์</th>
                    <th style="width:120px;text-align:center;">วันที่สร้าง</th>
                    <th style="width:100px;text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($users): foreach ($users as $u):
                $meta      = $ROLE_META[$u['role']] ?? $ROLE_META['viewer'];
                $initial   = mb_strtoupper(mb_substr($u['full_name'] ?: $u['username'], 0, 1));
                $isSelf    = ((int)$u['id'] === (int)$_SESSION['admin_id']);
                $isActive  = (int)($u['is_active'] ?? 1) === 1;
                $sessCount = count($u['_sessions']);
            ?>
            <tr id="user-row-<?= $u['id'] ?>">
                <td style="text-align:center;color:var(--text-muted);font-size:12px;"><?= $u['id'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="user-avatar" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>;border:1px solid <?= $meta['border'] ?>;">
                            <?php if (!empty($u['avatar']) && file_exists(__DIR__ . '/../../uploads/avatars/' . $u['avatar'])): ?>
                                <img src="/uploads/avatars/<?= h($u['avatar']) ?>" alt="">
                            <?php else: ?>
                                <?= h($initial) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:14px;color:var(--text-main);">
                                <?= h($u['full_name'] ?: $u['username']) ?>
                                <?php if ($isSelf): ?>
                                    <span style="font-size:10px;font-weight:600;color:var(--primary);background:rgba(37,99,235,.1);padding:1px 7px;border-radius:10px;margin-left:4px;">คุณ</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:1px;">@<?= h($u['username']) ?><?= $u['email'] ? ' · ' . h($u['email']) : '' ?></div>
                        </div>
                    </div>
                </td>
                <td style="text-align:center;">
                    <span class="role-badge" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>;border-color:<?= $meta['border'] ?>;">
                        <?= h(role_label($u['role'])) ?>
                    </span>
                </td>
                <td style="text-align:center;">
                    <?php if (!$isActive): ?>
                        <span class="online-dot inactive">ปิดใช้งาน</span>
                    <?php elseif ($u['_is_online']): ?>
                        <span class="online-dot on">ออนไลน์</span>
                    <?php else: ?>
                        <span class="online-dot off">ออฟไลน์</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if ($sessCount > 0): ?>
                        <button type="button" class="sess-btn" onclick="toggleSessDrawer(<?= $u['id'] ?>)">
                            <span class="material-symbols-rounded">devices</span> <?= $sessCount ?> เครื่อง
                        </button>
                    <?php else: ?>
                        <span style="color:var(--text-muted);font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;color:var(--text-muted);font-size:12px;">
                    <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                </td>
                <td style="text-align:center;">
                    <div style="display:flex;align-items:center;justify-content:center;gap:5px;">
                        <button type="button" class="t-btn t-edit" title="แก้ไข"
                                onclick='openEditModal(<?= json_encode(["id"=>$u['id'],"username"=>$u['username'],"role"=>$u['role'],"full_name"=>$u['full_name'],"phone"=>$u['phone'],"email"=>$u['email'],"isSelf"=>$isSelf], JSON_UNESCAPED_UNICODE) ?>)'>
                            <span class="material-symbols-rounded">edit</span>
                        </button>
                        <?php if (!$isSelf && $isActive): ?>
                        <button type="button" class="t-btn t-del" title="ปิดใช้งาน"
                                onclick="openConfirm('deactivate', <?= $u['id'] ?>, '<?= h($u['username']) ?>')">
                            <span class="material-symbols-rounded">person_off</span>
                        </button>
                        <?php elseif (!$isSelf && !$isActive): ?>
                        <button type="button" class="t-btn t-act" title="เปิดใช้งาน"
                                onclick="openConfirm('activate', <?= $u['id'] ?>, '<?= h($u['username']) ?>')">
                            <span class="material-symbols-rounded">person_check</span>
                        </button>
                        <button type="button" class="t-btn t-del" title="ลบถาวร"
                                onclick="openConfirm('hard_delete', <?= $u['id'] ?>, '<?= h($u['username']) ?>')">
                            <span class="material-symbols-rounded">delete_forever</span>
                        </button>
                        <?php else: ?>
                        <button type="button" class="t-btn" disabled style="opacity:.3;cursor:not-allowed;">
                            <span class="material-symbols-rounded">person_off</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php if ($sessCount > 0): ?>
            <tr class="sess-drawer" id="sess-drawer-<?= $u['id'] ?>">
                <td colspan="7">
                    <?php foreach ($u['_sessions'] as $s):
                        $mySess = (hash_equals(hash('sha256', session_id()), $s['session_hash']));
                    ?>
                    <div class="sess-item">
                        <div>
                            <div class="dev">
                                <span class="material-symbols-rounded" style="font-size:14px;vertical-align:-2px;">devices</span>
                                <?= h($s['device_label'] ?: 'ไม่ทราบอุปกรณ์') ?>
                                <?php if ($mySess): ?><span style="color:var(--primary);font-weight:600;">(เครื่องนี้)</span><?php endif; ?>
                            </div>
                            <div class="meta">
                                IP <?= h($s['ip'] ?: '—') ?> ·
                                เข้าสู่ระบบ <?= date('d/m/y H:i', strtotime($s['created_at'])) ?> ·
                                ใช้งานล่าสุด <?= time_ago($s['last_seen_at']) ?>
                            </div>
                        </div>
                        <button type="button" class="kill-btn" <?= $mySess ? 'disabled title="ใช้ปุ่มออกจากระบบแทน"' : '' ?>
                                onclick="killSession(<?= $s['id'] ?>, this)">
                            บังคับออกจากระบบ
                        </button>
                    </div>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">ไม่พบข้อมูล</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</div><!-- .cmns-wrapper -->

<!-- ── User Form Modal (inline, no iframe) ── -->
<div id="usr-form-overlay" class="usr-overlay">
  <div class="usr-box">
    <div class="usr-form-header">
      <h3>
        <span id="form-icon" class="material-symbols-rounded" style="font-size:18px;color:var(--primary);">person_add</span>
        <span id="form-title">เพิ่มผู้ใช้งาน</span>
      </h3>
      <button type="button" onclick="closeFormModal()"
              style="background:var(--bg-surface-alt);border:1px solid var(--border);width:32px;height:32px;border-radius:8px;cursor:pointer;color:var(--text-muted);display:flex;align-items:center;justify-content:center;padding:0;transition:.15s;"
              onmouseover="this.style.background='#ef4444';this.style.color='#fff'" onmouseout="this.style.background='var(--bg-surface-alt)';this.style.color='var(--text-muted)'">
        <span class="material-symbols-rounded" style="font-size:17px;">close</span>
      </button>
    </div>
    <form id="usr-form" method="POST" action="index.php" onsubmit="return validateForm()">
      <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
      <input type="hidden" name="_action" id="form-action" value="add">
      <input type="hidden" name="edit_id" id="form-edit-id" value="">
      <div class="usr-form-body">
        <div id="form-errors" class="usr-error" style="display:none;"></div>
        <div class="usr-grid">
          <div class="usr-field">
            <label class="usr-label">Username <span style="color:#ef4444">*</span></label>
            <input type="text" name="username" id="f-username" class="usr-input" placeholder="เช่น john, natt_admin" autocomplete="off" required>
          </div>
          <div class="usr-field">
            <label class="usr-label">Role <span style="color:#ef4444">*</span></label>
            <select name="role" id="f-role" class="usr-select">
              <?php foreach ($ROLES as $val => $label): ?>
              <option value="<?= $val ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            <p id="role-locked-hint" class="usr-hint" style="display:none;">ไม่สามารถเปลี่ยน Role ของตัวเองได้</p>
          </div>
        </div>
        <div class="usr-field">
          <label class="usr-label">ชื่อที่แสดง</label>
          <input type="text" id="f-full-name" name="full_name" class="usr-input" placeholder="ชื่อ-นามสกุล">
        </div>
        <div class="usr-grid">
          <div class="usr-field">
            <label class="usr-label">เบอร์โทร</label>
            <input type="text" id="f-phone" name="phone" class="usr-input" placeholder="08x-xxx-xxxx">
          </div>
          <div class="usr-field">
            <label class="usr-label">อีเมล</label>
            <input type="email" id="f-email" name="email" class="usr-input" placeholder="name@example.com">
          </div>
        </div>
        <div class="usr-grid">
          <div class="usr-field" style="margin-bottom:0;">
            <label class="usr-label">Password <span id="pw-required" style="color:#ef4444">*</span></label>
            <div class="usr-pw-wrap">
              <input type="password" id="f-pw" name="password" class="usr-input" placeholder="อย่างน้อย 6 ตัว">
              <button type="button" class="usr-pw-toggle" onclick="togglePw('f-pw',this)">
                <span class="material-symbols-rounded">visibility</span>
              </button>
            </div>
          </div>
          <div class="usr-field" style="margin-bottom:0;">
            <label class="usr-label">Confirm Password <span id="cpw-required" style="color:#ef4444">*</span></label>
            <div class="usr-pw-wrap">
              <input type="password" id="f-cpw" name="confirm_password" class="usr-input" placeholder="กรอกซ้ำ">
              <button type="button" class="usr-pw-toggle" onclick="togglePw('f-cpw',this)">
                <span class="material-symbols-rounded">visibility</span>
              </button>
            </div>
          </div>
        </div>
        <p id="pw-hint" class="usr-hint" style="margin-top:6px;display:none;">ปล่อยว่างถ้าไม่ต้องการเปลี่ยนรหัสผ่าน</p>
      </div>
      <div class="usr-form-footer">
        <button type="button" class="usr-btn-cancel" onclick="closeFormModal()">ยกเลิก</button>
        <button type="submit" class="usr-btn-save">
          <span class="material-symbols-rounded" style="font-size:16px;">save</span>
          <span id="save-label">สร้างบัญชี</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Confirm Modal (deactivate / activate / hard-delete, shared) ── -->
<div id="usr-confirm-overlay" class="usr-overlay">
  <div class="usr-box">
    <div class="del-modal-header" id="confirm-header">
      <div class="del-modal-icon" id="confirm-icon"><span class="material-symbols-rounded" style="font-size:22px;">warning</span></div>
      <h3 id="confirm-title" style="margin:0;font-size:15px;font-weight:700;color:#dc2626;">ยืนยัน?</h3>
    </div>
    <div class="del-modal-body" id="confirm-body">...</div>
    <div class="del-modal-footer">
      <button type="button" class="usr-btn-cancel" onclick="closeConfirm()">ยกเลิก</button>
      <button id="confirm-btn" type="button" class="usr-btn-confirm" onclick="doConfirm()">ยืนยัน</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
// ── Form Modal ──────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('form-title').textContent  = 'เพิ่มผู้ใช้งาน';
    document.getElementById('form-icon').textContent   = 'person_add';
    document.getElementById('form-action').value       = 'add';
    document.getElementById('form-edit-id').value      = '';
    document.getElementById('f-username').value        = '';
    document.getElementById('f-role').value            = 'staff';
    document.getElementById('f-role').disabled         = false;
    document.getElementById('f-full-name').value       = '';
    document.getElementById('f-phone').value           = '';
    document.getElementById('f-email').value           = '';
    document.getElementById('f-pw').value               = '';
    document.getElementById('f-cpw').value              = '';
    document.getElementById('pw-required').style.display  = 'inline';
    document.getElementById('cpw-required').style.display = 'inline';
    document.getElementById('pw-hint').style.display       = 'none';
    document.getElementById('role-locked-hint').style.display = 'none';
    document.getElementById('save-label').textContent  = 'สร้างบัญชี';
    document.getElementById('form-errors').style.display = 'none';
    showOverlay('usr-form-overlay');
    setTimeout(() => document.getElementById('f-username').focus(), 80);
}

function openEditModal(u) {
    document.getElementById('form-title').textContent  = 'แก้ไข — ' + u.username;
    document.getElementById('form-icon').textContent   = 'manage_accounts';
    document.getElementById('form-action').value       = 'edit';
    document.getElementById('form-edit-id').value      = u.id;
    document.getElementById('f-username').value        = u.username;
    document.getElementById('f-role').value            = u.role;
    document.getElementById('f-role').disabled         = u.isSelf;
    document.getElementById('f-full-name').value       = u.full_name || '';
    document.getElementById('f-phone').value           = u.phone || '';
    document.getElementById('f-email').value           = u.email || '';
    document.getElementById('f-pw').value               = '';
    document.getElementById('f-cpw').value              = '';
    document.getElementById('pw-required').style.display  = 'none';
    document.getElementById('cpw-required').style.display = 'none';
    document.getElementById('pw-hint').style.display       = 'block';
    document.getElementById('role-locked-hint').style.display = u.isSelf ? 'block' : 'none';
    document.getElementById('save-label').textContent  = 'บันทึกการเปลี่ยนแปลง';
    document.getElementById('form-errors').style.display = 'none';
    showOverlay('usr-form-overlay');
    setTimeout(() => document.getElementById('f-username').focus(), 80);
}

function closeFormModal() { hideOverlay('usr-form-overlay'); }

function validateForm() {
    const pw  = document.getElementById('f-pw').value;
    const cpw = document.getElementById('f-cpw').value;
    const isAdd = document.getElementById('form-action').value === 'add';
    const errBox = document.getElementById('form-errors');

    if (isAdd && !pw) { showErr(errBox, 'กรุณากรอกรหัสผ่านสำหรับผู้ใช้ใหม่'); return false; }
    if (pw && pw !== cpw) { showErr(errBox, 'รหัสผ่านทั้งสองช่องไม่ตรงกัน'); return false; }
    if (pw && pw.length < 6) { showErr(errBox, 'รหัสผ่านต้องมีอย่างน้อย 6 ตัว'); return false; }
    return true;
}
function showErr(box, msg) { box.textContent = msg; box.style.display = 'block'; }

// ── Confirm modal: deactivate / activate / hard_delete ────────────
const CONFIRM_CFG = {
    deactivate: { warn:false, title:'ปิดใช้งานบัญชี?', body:n=>`ปิดใช้งานบัญชี <strong>${n}</strong> — ผู้ใช้นี้จะเข้าสู่ระบบไม่ได้ และทุก session ที่ออนไลน์อยู่จะถูกตัดทันที<br><span style="font-size:12px;color:var(--text-muted);">เปิดใช้งานกลับได้ภายหลัง</span>`, btn:'ปิดใช้งาน', cls:'' },
    activate:   { warn:false, title:'เปิดใช้งานบัญชี?', body:n=>`เปิดใช้งานบัญชี <strong>${n}</strong> อีกครั้ง ผู้ใช้จะกลับมาเข้าสู่ระบบได้ตามปกติ`, btn:'เปิดใช้งาน', cls:'warn' },
    hard_delete:{ warn:true,  title:'ลบถาวร?', body:n=>`ลบบัญชี <strong>${n}</strong> ออกจากระบบถาวร — ข้อมูลที่ผูกกับบัญชีนี้ (ประวัติ, log) จะไม่มีเจ้าของอีกต่อไป<br><span style="font-size:12px;color:#ef4444;">การกระทำนี้ย้อนกลับไม่ได้</span>`, btn:'ลบถาวร', cls:'' },
};
let _confirmAction = null, _confirmId = null;
function openConfirm(action, id, name) {
    _confirmAction = action; _confirmId = id;
    const cfg = CONFIRM_CFG[action];
    document.getElementById('confirm-title').textContent = cfg.title;
    document.getElementById('confirm-body').innerHTML = cfg.body(name);
    const btn = document.getElementById('confirm-btn');
    btn.textContent = cfg.btn;
    btn.className = 'usr-btn-confirm' + (cfg.cls ? ' ' + cfg.cls : '');
    const hdr = document.getElementById('confirm-header'), icon = document.getElementById('confirm-icon');
    hdr.className = 'del-modal-header' + (action === 'activate' ? ' warn' : '');
    icon.className = 'del-modal-icon' + (action === 'activate' ? ' warn' : '');
    icon.querySelector('.material-symbols-rounded').textContent = action === 'activate' ? 'person_check' : 'warning';
    showOverlay('usr-confirm-overlay');
}
function closeConfirm() { hideOverlay('usr-confirm-overlay'); }
function doConfirm() {
    if (!_confirmId || !_confirmAction) return;
    const btn = document.getElementById('confirm-btn');
    btn.disabled = true; btn.textContent = 'กำลังดำเนินการ...';
    const fd = new FormData();
    fd.append('action', _confirmAction);
    fd.append('id', _confirmId);
    fd.append('csrf_token', <?= json_encode($CSRF) ?>);
    fetch('delete.php', { method:'POST', body: fd }).then(r => r.json()).then(d => {
        closeConfirm();
        if (d.ok) {
            Swal.fire({ icon:'success', title: d.msg || 'สำเร็จ', toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
            setTimeout(() => location.reload(), 500);
        } else {
            Swal.fire({ icon:'error', title: d.msg || 'ทำรายการไม่สำเร็จ', toast:true, position:'top-end', showConfirmButton:false, timer:3500 });
        }
        btn.disabled = false;
    });
}

// ── Sessions drawer ─────────────────────────────────────────────
function toggleSessDrawer(id) {
    document.querySelectorAll('.sess-drawer.open').forEach(el => { if (el.id !== 'sess-drawer-' + id) el.classList.remove('open'); });
    document.getElementById('sess-drawer-' + id).classList.toggle('open');
}
function killSession(sessionId, btn) {
    btn.disabled = true; btn.textContent = 'กำลังตัด...';
    const fd = new FormData();
    fd.append('session_id', sessionId);
    fd.append('csrf_token', <?= json_encode($CSRF) ?>);
    fetch('kill_session.php', { method:'POST', body: fd }).then(r => r.json()).then(d => {
        if (d.ok) {
            Swal.fire({ icon:'success', title:'บังคับออกจากระบบแล้ว', toast:true, position:'top-end', showConfirmButton:false, timer:2500 });
            setTimeout(() => location.reload(), 400);
        } else {
            Swal.fire({ icon:'error', title: d.msg || 'ทำรายการไม่สำเร็จ', toast:true, position:'top-end', showConfirmButton:false, timer:3500 });
            btn.disabled = false; btn.textContent = 'บังคับออกจากระบบ';
        }
    });
}

// ── Overlay helpers ─────────────────────────────────────────────
function showOverlay(id) {
    const el = document.getElementById(id);
    el.style.display = 'flex';
    requestAnimationFrame(() => el.classList.add('show'));
}
function hideOverlay(id) {
    const el = document.getElementById(id);
    el.classList.remove('show');
    setTimeout(() => el.style.display = 'none', 180);
}
[document.getElementById('usr-form-overlay'), document.getElementById('usr-confirm-overlay')].forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) hideOverlay(el.id); });
});

// ── Password toggle ─────────────────────────────────────────────
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('.material-symbols-rounded');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.textContent = inp.type === 'password' ? 'visibility' : 'visibility_off';
}
</script>
