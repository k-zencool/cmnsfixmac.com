<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

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
                if ($password_hash) {
                    $pdo->prepare("UPDATE admin_users SET username=?, role=?, password=? WHERE id=?")
                        ->execute([$username, $role, $password_hash, $user['id']]);
                } else {
                    $pdo->prepare("UPDATE admin_users SET username=?, role=? WHERE id=?")
                        ->execute([$username, $role, $user['id']]);
                }
            } else {
                $pdo->prepare("INSERT INTO admin_users (username, role, password) VALUES (?,?,?)")
                    ->execute([$username, $role, $password_hash]);
            }
            $_SESSION['flash'] = $is_edit ? 'อัปเดตผู้ใช้งานเรียบร้อยแล้ว' : 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว';
            header('Location: index.php'); exit;
        } catch (PDOException $e) {
            $errors[] = $e->errorInfo[1] == 1062
                ? "Username '" . h($username) . "' นี้มีผู้ใช้งานแล้ว"
                : 'Database Error: ' . $e->getMessage();
        }
    }
    // Preserve typed values on error
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

$pageTitle = $is_edit ? 'แก้ไขผู้ใช้งาน' : 'เพิ่มผู้ใช้งาน';
include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=9">
<style>
.usr-form-wrap{max-width:560px;margin:0 auto;padding:24px 0 60px;}
.usr-card{background:var(--bg-surface);border:1px solid var(--border);border-radius:14px;padding:28px;margin-bottom:20px;}
.usr-card h3{margin:0 0 20px;font-size:15px;font-weight:700;color:var(--text-main);display:flex;align-items:center;gap:8px;padding-bottom:14px;border-bottom:1px solid var(--border);}
.usr-label{font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:5px;display:block;text-transform:uppercase;letter-spacing:.4px;}
.usr-input,.usr-select{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;background:var(--bg-surface);color:var(--text-main);box-sizing:border-box;transition:border-color .15s;font-family:'Sarabun',sans-serif;}
.usr-input:focus,.usr-select:focus{outline:none;border-color:var(--primary);}
.usr-hint{font-size:11px;color:var(--text-muted);margin-top:4px;}
.usr-field{margin-bottom:16px;}
.usr-pw-wrap{position:relative;}
.usr-pw-wrap .usr-input{padding-right:44px;}
.usr-pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);padding:0;display:flex;align-items:center;transition:.15s;}
.usr-pw-toggle:hover{color:var(--text-main);}
.usr-error{background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:12px 16px;border-radius:9px;margin-bottom:20px;font-size:13px;}
.usr-error p{margin:0;line-height:1.7;}
.usr-actions{display:flex;align-items:center;justify-content:space-between;padding-top:4px;}
</style>

<div class="cmns-wrapper">

    <div class="cmns-header-bar" style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <a href="index.php" style="color:var(--text-muted);text-decoration:none;display:flex;align-items:center;gap:4px;font-size:13px;">
                <span class="material-symbols-rounded" style="font-size:18px;">arrow_back</span> กลับ
            </a>
            <h1 class="cmns-page-title" style="color:var(--primary);margin:0;">
                <span class="material-symbols-rounded" style="font-size:28px;"><?= $is_edit ? 'manage_accounts' : 'person_add' ?></span>
                <?= $is_edit ? 'แก้ไขผู้ใช้งาน — ' . h($user['username']) : 'เพิ่มผู้ใช้งาน' ?>
            </h1>
        </div>
    </div>

    <div class="usr-form-wrap">

        <?php if ($errors): ?>
        <div class="usr-error">
            <?php foreach ($errors as $e): ?><p>• <?= h($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="form.php<?= $is_edit ? '?id='.$user['id'] : '' ?>" id="usr-form">

            <!-- ข้อมูลบัญชี -->
            <div class="usr-card">
                <h3>
                    <span class="material-symbols-rounded" style="font-size:18px;color:var(--primary);">badge</span>
                    ข้อมูลบัญชี
                </h3>

                <div class="usr-field">
                    <label class="usr-label">Username <span style="color:#ef4444">*</span></label>
                    <input type="text" name="username" class="usr-input" value="<?= h($user['username']) ?>"
                           placeholder="เช่น john, natt_admin" autocomplete="off" required>
                </div>

                <div class="usr-field" style="margin-bottom:0;">
                    <label class="usr-label">สิทธิ์การใช้งาน (Role) <span style="color:#ef4444">*</span></label>
                    <?php if ($is_edit && (int)$user['id'] === (int)$_SESSION['admin_id']): ?>
                        <input type="text" class="usr-input" value="<?= h($user['role']) ?>" readonly
                               style="opacity:.6;cursor:not-allowed;">
                        <p class="usr-hint">ไม่สามารถเปลี่ยน Role ของตัวเองได้</p>
                    <?php else: ?>
                        <select name="role" class="usr-select">
                            <?php foreach ($ROLES as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($user['role'] ?? 'staff') === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>

            <!-- รหัสผ่าน -->
            <div class="usr-card">
                <h3>
                    <span class="material-symbols-rounded" style="font-size:18px;color:#f59e0b;">lock</span>
                    รหัสผ่าน
                    <?php if ($is_edit): ?>
                        <span style="font-size:11px;font-weight:500;color:var(--text-muted);margin-left:4px;">(ปล่อยว่างถ้าไม่เปลี่ยน)</span>
                    <?php endif; ?>
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
                               placeholder="กรอกรหัสผ่านอีกครั้ง"
                               <?= !$is_edit ? 'required' : '' ?>>
                        <button type="button" class="usr-pw-toggle" onclick="togglePw('pw2',this)">
                            <span class="material-symbols-rounded" style="font-size:20px;">visibility</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="usr-actions">
                <a href="index.php" style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border:1.5px solid var(--border);border-radius:9px;color:var(--text-main);text-decoration:none;font-size:14px;font-weight:600;background:var(--bg-surface);transition:.15s;"
                   onmouseover="this.style.background='var(--bg-surface-alt)'" onmouseout="this.style.background='var(--bg-surface)'">
                    <span class="material-symbols-rounded" style="font-size:16px;">close</span> ยกเลิก
                </a>
                <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:10px 24px;background:var(--primary);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Sarabun',sans-serif;transition:.15s;"
                        onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                    <span class="material-symbols-rounded" style="font-size:16px;">save</span>
                    <?= $is_edit ? 'บันทึกการเปลี่ยนแปลง' : 'สร้างบัญชี' ?>
                </button>
            </div>

        </form>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('.material-symbols-rounded');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.textContent = 'visibility_off';
    } else {
        inp.type = 'password';
        icon.textContent = 'visibility';
    }
}
</script>
