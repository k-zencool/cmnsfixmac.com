<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$isModal = !empty($_GET['modal']);

$errors = [];
$user = ['id' => null, 'username' => '', 'role' => 'staff'];
$is_edit = false;

if (isset($_GET['id'])) {
    $is_edit = true;
    $stmt = $pdo->prepare("SELECT id, username, role FROM admin_users WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { header('Location: index.php'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username']         ?? '');
    $role             = trim($_POST['role']             ?? '');
    $password         = $_POST['password']         ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$username) $errors[] = 'กรุณากรอก Username';
    if (!$role)     $errors[] = 'กรุณาเลือก Role';

    $password_hash = null;
    if (!empty($password)) {
        if ($password !== $confirm_password) $errors[] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
        elseif (strlen($password) < 6)       $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัว';
        else $password_hash = password_hash($password, PASSWORD_BCRYPT);
    } elseif (!$is_edit) {
        $errors[] = 'กรุณากรอกรหัสผ่านสำหรับผู้ใช้ใหม่';
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                if ($password_hash)
                    $pdo->prepare("UPDATE admin_users SET username=?, role=?, password=? WHERE id=?")->execute([$username, $role, $password_hash, $user['id']]);
                else
                    $pdo->prepare("UPDATE admin_users SET username=?, role=? WHERE id=?")->execute([$username, $role, $user['id']]);
            } else {
                $pdo->prepare("INSERT INTO admin_users (username, role, password) VALUES (?,?,?)")->execute([$username, $role, $password_hash]);
            }

            if ($isModal) {
                echo '<script>window.parent.postMessage("user-saved","*")</script>';
                exit;
            }
            $_SESSION['flash'] = $is_edit ? 'อัปเดตผู้ใช้งานเรียบร้อยแล้ว' : 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว';
            header('Location: index.php'); exit;

        } catch (PDOException $e) {
            $errors[] = $e->errorInfo[1] == 1062
                ? "Username '" . h($username) . "' นี้มีผู้ใช้งานแล้ว"
                : 'Database Error: ' . $e->getMessage();
        }
    }
    $user['username'] = $username;
    $user['role']     = $role;
}

$ROLES = [
    'super_admin' => 'Super Admin',
    'manager'     => 'Manager',
    'admin'       => 'Admin',
    'staff'       => 'Staff',
    'viewer'      => 'Viewer',
];

if ($isModal):
?><!DOCTYPE html><html lang="th">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('admin_theme')||'dark');</script>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
<link rel="stylesheet" href="/admin/templates/assets/css/admin.css?v=9">
</head>
<body style="margin:0;padding:0;background:var(--bg-surface);display:flex;flex-direction:column;">

<!-- HEADER -->
<div style="flex-shrink:0;display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:1px solid var(--border,#e5e7eb);">
    <h2 style="margin:0;font-size:17px;font-weight:700;color:var(--text-main);display:flex;align-items:center;gap:8px;">
        <span class="material-symbols-rounded" style="font-size:20px;color:var(--primary);"><?= $is_edit ? 'manage_accounts' : 'person_add' ?></span>
        <?= $is_edit ? 'แก้ไขผู้ใช้งาน — ' . h($user['username']) : 'เพิ่มผู้ใช้งาน' ?>
    </h2>
    <button type="button" onclick="safeClose()"
            style="background:var(--bg-surface-alt);border:1px solid var(--border);width:34px;height:34px;border-radius:9px;cursor:pointer;color:var(--text-muted);display:flex;align-items:center;justify-content:center;transition:.2s;padding:0;"
            onmouseover="this.style.background='#ef4444';this.style.color='#fff'" onmouseout="this.style.background='var(--bg-surface-alt)';this.style.color='var(--text-muted)'">
        <span class="material-symbols-rounded" style="font-size:18px;">close</span>
    </button>
</div>

<!-- BODY -->
<div style="padding:20px 24px;">
<?php else:
    $pageTitle = $is_edit ? 'แก้ไขผู้ใช้งาน' : 'เพิ่มผู้ใช้งาน';
    include __DIR__ . '/../templates/header_admin.php';
endif; ?>

<style>
.usr-card{background:var(--bg-surface,#fff);border:1px solid var(--border,#e5e7eb);border-radius:14px;padding:24px;margin-bottom:16px;}
.usr-card h3{margin:0 0 18px;font-size:14px;font-weight:700;color:var(--text-main,#111);display:flex;align-items:center;gap:8px;padding-bottom:12px;border-bottom:1px solid var(--border,#e5e7eb);}
.usr-label{font-size:12px;font-weight:600;color:var(--text-muted,#6b7280);margin-bottom:5px;display:block;text-transform:uppercase;letter-spacing:.4px;}
.usr-input,.usr-select{width:100%;padding:10px 13px;border:1.5px solid var(--border,#e5e7eb);border-radius:9px;font-size:14px;background:var(--bg-surface,#fff);color:var(--text-main,#111);box-sizing:border-box;transition:border-color .15s;font-family:'Sarabun',sans-serif;}
.usr-input:focus,.usr-select:focus{outline:none;border-color:var(--primary,#3b82f6);}
.usr-hint{font-size:11px;color:var(--text-muted,#9ca3af);margin-top:4px;}
.usr-field{margin-bottom:14px;}
.usr-pw-wrap{position:relative;}
.usr-pw-wrap .usr-input{padding-right:44px;}
.usr-pw-toggle{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted,#9ca3af);padding:0;display:flex;align-items:center;transition:.15s;}
.usr-pw-toggle:hover{color:var(--text-main,#111);}
.usr-error{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:10px 14px;border-radius:9px;margin-bottom:16px;font-size:13px;}
.usr-error p{margin:0;line-height:1.7;}
</style>

<?php if (!$isModal): ?>
<div style="max-width:540px;margin:0 auto;padding:24px 0 60px;">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <a href="index.php" style="color:var(--text-muted);text-decoration:none;font-size:13px;display:flex;align-items:center;gap:4px;">
        <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> กลับ
    </a>
    <h2 style="margin:0;font-size:20px;"><?= $is_edit ? 'แก้ไขผู้ใช้งาน — ' . h($user['username']) : 'เพิ่มผู้ใช้งาน' ?></h2>
</div>
<?php endif; ?>

<?php if ($errors): ?>
<div class="usr-error">
    <?php foreach ($errors as $e): ?><p>• <?= h($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" action="form.php?<?= $is_edit ? 'id='.$user['id'].'&' : '' ?><?= $isModal ? 'modal=1' : '' ?>" id="usr-form">

    <div class="usr-card">
        <h3><span class="material-symbols-rounded" style="font-size:18px;color:var(--primary,#3b82f6);">badge</span> ข้อมูลบัญชี</h3>
        <div class="usr-field">
            <label class="usr-label">Username <span style="color:#ef4444">*</span></label>
            <input type="text" name="username" class="usr-input" value="<?= h($user['username']) ?>"
                   placeholder="เช่น john, natt_admin" autocomplete="off" required>
        </div>
        <div class="usr-field" style="margin-bottom:0;">
            <label class="usr-label">Role <span style="color:#ef4444">*</span></label>
            <?php if ($is_edit && (int)$user['id'] === (int)$_SESSION['admin_id']): ?>
                <input type="text" class="usr-input" value="<?= h($user['role']) ?>" readonly style="opacity:.6;cursor:not-allowed;">
                <p class="usr-hint">ไม่สามารถเปลี่ยน Role ของตัวเองได้</p>
            <?php else: ?>
                <select name="role" class="usr-select">
                    <?php foreach ($ROLES as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($user['role'] ?? 'staff') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </div>

    <div class="usr-card">
        <h3>
            <span class="material-symbols-rounded" style="font-size:18px;color:#f59e0b;">lock</span>
            รหัสผ่าน
            <?php if ($is_edit): ?><span style="font-size:11px;font-weight:500;color:var(--text-muted,#9ca3af);">(ปล่อยว่างถ้าไม่เปลี่ยน)</span><?php endif; ?>
        </h3>
        <div class="usr-field">
            <label class="usr-label">Password <?= !$is_edit ? '<span style="color:#ef4444">*</span>' : '' ?></label>
            <div class="usr-pw-wrap">
                <input type="password" id="pw1" name="password" class="usr-input"
                       placeholder="<?= $is_edit ? 'กรอกถ้าต้องการเปลี่ยน' : 'อย่างน้อย 6 ตัว' ?>"
                       <?= !$is_edit ? 'required' : '' ?>>
                <button type="button" class="usr-pw-toggle" onclick="togglePw('pw1',this)">
                    <span class="material-symbols-rounded" style="font-size:20px;">visibility</span>
                </button>
            </div>
        </div>
        <div class="usr-field" style="margin-bottom:0;">
            <label class="usr-label">Confirm Password <?= !$is_edit ? '<span style="color:#ef4444">*</span>' : '' ?></label>
            <div class="usr-pw-wrap">
                <input type="password" id="pw2" name="confirm_password" class="usr-input"
                       placeholder="กรอกรหัสผ่านอีกครั้ง" <?= !$is_edit ? 'required' : '' ?>>
                <button type="button" class="usr-pw-toggle" onclick="togglePw('pw2',this)">
                    <span class="material-symbols-rounded" style="font-size:20px;">visibility</span>
                </button>
            </div>
        </div>
    </div>

</form>

<?php if (!$isModal): ?>
<div style="display:flex;align-items:center;justify-content:space-between;padding-top:4px;">
    <a href="index.php" style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border:1.5px solid var(--border);border-radius:9px;color:var(--text-main);text-decoration:none;font-size:14px;font-weight:600;">
        <span class="material-symbols-rounded" style="font-size:16px;">close</span> ยกเลิก
    </a>
    <button type="submit" form="usr-form" style="display:inline-flex;align-items:center;gap:6px;padding:10px 24px;background:var(--primary,#3b82f6);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Sarabun',sans-serif;">
        <span class="material-symbols-rounded" style="font-size:16px;">save</span>
        <?= $is_edit ? 'บันทึกการเปลี่ยนแปลง' : 'สร้างบัญชี' ?>
    </button>
</div>
</div><!-- /max-width wrap -->
<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
<?php else: ?>
</div><!-- /body -->

<!-- FOOTER -->
<div style="flex-shrink:0;display:flex;align-items:center;justify-content:space-between;padding:14px 24px;border-top:1px solid var(--border,#e5e7eb);background:var(--bg-surface);">
    <button type="button" onclick="safeClose()"
            style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border:1.5px solid var(--border);border-radius:9px;color:var(--text-main);background:none;font-size:14px;font-weight:600;cursor:pointer;font-family:'Sarabun',sans-serif;">
        <span class="material-symbols-rounded" style="font-size:15px;">close</span> ยกเลิก
    </button>
    <button type="submit" form="usr-form"
            style="display:inline-flex;align-items:center;gap:6px;padding:10px 24px;background:var(--primary,#3b82f6);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Sarabun',sans-serif;box-shadow:0 3px 10px rgba(37,99,235,.25);">
        <span class="material-symbols-rounded" style="font-size:16px;">save</span>
        <?= $is_edit ? 'บันทึกการเปลี่ยนแปลง' : 'สร้างบัญชี' ?>
    </button>
</div>

<!-- Confirm close dialog -->
<div id="usr-confirm" style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:none;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;">
    <div style="background:var(--bg-surface,#fff);width:90%;max-width:340px;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.25);overflow:hidden;border:1px solid var(--border,#e5e7eb);">
        <div style="padding:20px 20px 12px;text-align:center;background:rgba(239,68,68,.06);border-bottom:1px solid var(--border,#e5e7eb);">
            <div style="width:44px;height:44px;border-radius:50%;background:rgba(239,68,68,.12);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                <span class="material-symbols-rounded" style="font-size:22px;">warning</span>
            </div>
            <h3 style="margin:0;font-size:15px;font-weight:700;color:#dc2626;">ออกโดยไม่บันทึก?</h3>
        </div>
        <div style="padding:16px 20px;text-align:center;color:var(--text-main,#111);font-size:13px;line-height:1.65;">
            ข้อมูลที่กรอกไว้จะหายทั้งหมด
        </div>
        <div style="padding:12px 20px;border-top:1px solid var(--border,#e5e7eb);background:var(--bg-surface-alt,#f8fafc);display:flex;gap:8px;justify-content:center;">
            <button onclick="closeConfirm()" style="padding:8px 18px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface);color:var(--text-main);font-weight:600;cursor:pointer;font-family:'Sarabun',sans-serif;font-size:13px;">อยู่ต่อ</button>
            <button onclick="confirmClose()" style="padding:8px 18px;border-radius:8px;border:none;background:#ef4444;color:#fff;font-weight:700;cursor:pointer;font-family:'Sarabun',sans-serif;font-size:13px;">ออกเลย</button>
        </div>
    </div>
</div>

</body></html>
<?php endif; ?>

<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('.material-symbols-rounded');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.textContent = inp.type === 'password' ? 'visibility' : 'visibility_off';
}
<?php if ($isModal): ?>
function reportHeight() {
    window.parent.postMessage({ type:'usr-resize', h: document.body.scrollHeight }, '*');
}
window.addEventListener('load', reportHeight);
new ResizeObserver(reportHeight).observe(document.body);

let formDirty = false;
const _form = document.getElementById('usr-form');
_form.addEventListener('input',  () => { formDirty = true; });
_form.addEventListener('change', () => { formDirty = true; });
_form.addEventListener('submit', () => { formDirty = false; });

function safeClose() {
    if (!formDirty) { window.parent.closeUserModal(); return; }
    const d = document.getElementById('usr-confirm');
    d.style.display = 'flex';
    requestAnimationFrame(() => d.style.opacity = '1');
}
function closeConfirm() {
    const d = document.getElementById('usr-confirm');
    d.style.opacity = '0';
    setTimeout(() => d.style.display = 'none', 200);
}
function confirmClose() { formDirty = false; window.parent.closeUserModal(); }
<?php endif; ?>
</script>
