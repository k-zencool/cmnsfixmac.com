<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$form_errors = [];
$form_result = null; // 'ok' | 'error'

// ── Handle POST (inline form save) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {
    $action  = $_POST['_action']; // 'add' | 'edit'
    $edit_id = (int)($_POST['edit_id'] ?? 0);

    if (!hash_equals($CSRF, $_POST['csrf_token'] ?? '')) {
        $form_errors[] = 'CSRF token ไม่ถูกต้อง';
    } else {
        $username         = trim($_POST['username'] ?? '');
        $role             = trim($_POST['role']     ?? '');
        $password         = $_POST['password']         ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!$username) $form_errors[] = 'กรุณากรอก Username';
        if (!$role)     $form_errors[] = 'กรุณาเลือก Role';

        $pw_hash = null;
        if (!empty($password)) {
            if ($password !== $confirm_password) $form_errors[] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
            elseif (strlen($password) < 6)        $form_errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัว';
            else $pw_hash = password_hash($password, PASSWORD_BCRYPT);
        } elseif ($action === 'add') {
            $form_errors[] = 'กรุณากรอกรหัสผ่านสำหรับผู้ใช้ใหม่';
        }

        if (empty($form_errors)) {
            try {
                if ($action === 'edit' && $edit_id) {
                    if ($pw_hash)
                        $pdo->prepare("UPDATE admin_users SET username=?, role=?, password=? WHERE id=?")->execute([$username, $role, $pw_hash, $edit_id]);
                    else
                        $pdo->prepare("UPDATE admin_users SET username=?, role=? WHERE id=?")->execute([$username, $role, $edit_id]);
                } else {
                    $pdo->prepare("INSERT INTO admin_users (username, role, password) VALUES (?,?,?)")->execute([$username, $role, $pw_hash]);
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

$users = $pdo->query("SELECT id, username, role, created_at FROM admin_users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$role_count = [];
foreach ($users as $u) $role_count[$u['role']] = ($role_count[$u['role']] ?? 0) + 1;

$ROLE_META = [
    'super_admin' => ['label'=>'Super Admin','color'=>'#8b5cf6','bg'=>'rgba(139,92,246,.12)','border'=>'rgba(139,92,246,.3)'],
    'manager'     => ['label'=>'Manager',    'color'=>'#3b82f6','bg'=>'rgba(37,99,235,.1)',  'border'=>'rgba(37,99,235,.25)'],
    'admin'       => ['label'=>'Admin',      'color'=>'#0ea5e9','bg'=>'rgba(14,165,233,.1)', 'border'=>'rgba(14,165,233,.25)'],
    'staff'       => ['label'=>'Staff',      'color'=>'#10b981','bg'=>'rgba(16,185,129,.1)', 'border'=>'rgba(16,185,129,.25)'],
    'viewer'      => ['label'=>'Viewer',     'color'=>'#9ca3af','bg'=>'rgba(156,163,175,.1)','border'=>'rgba(156,163,175,.25)'],
];
$ROLES = ['super_admin'=>'Super Admin','manager'=>'Manager','admin'=>'Admin','staff'=>'Staff','viewer'=>'Viewer'];

$pageTitle = 'จัดการผู้ใช้งาน';
include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=9">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=9">
<style>
.t-btn{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-muted);cursor:pointer;transition:all .18s;text-decoration:none;padding:0;}
.t-btn .material-symbols-rounded{font-size:16px;line-height:1;}
.t-btn:hover{transform:translateY(-1px);box-shadow:0 3px 8px rgba(0,0,0,.1);}
.t-edit:hover{color:var(--primary);background:rgba(37,99,235,.07);border-color:var(--primary);}
.t-del:hover{color:#ef4444;background:rgba(239,68,68,.07);border-color:#ef4444;}
.user-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0;text-transform:uppercase;}
.role-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.3px;border:1px solid transparent;white-space:nowrap;}
/* Shared modal overlay */
.usr-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;display:none;align-items:center;justify-content:center;opacity:0;transition:opacity .18s;}
.usr-overlay.show{display:flex;opacity:1;}
.usr-box{background:var(--bg-surface);width:90%;max-width:480px;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.3);border:1px solid var(--border);overflow:hidden;transform:scale(.96);transition:transform .18s;}
.usr-overlay.show .usr-box{transform:scale(1);}
/* Form modal */
.usr-form-header{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border);}
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
/* Delete modal */
.del-modal-header{padding:20px 20px 12px;text-align:center;background:rgba(239,68,68,.06);border-bottom:1px solid var(--border);}
.del-modal-icon{width:44px;height:44px;border-radius:50%;background:rgba(239,68,68,.12);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;}
.del-modal-body{padding:16px 20px;text-align:center;color:var(--text-main);font-size:14px;line-height:1.65;}
.del-modal-footer{padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-surface-alt);display:flex;gap:8px;justify-content:center;}
.usr-btn-cancel{padding:8px 18px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface);color:var(--text-main);font-weight:600;cursor:pointer;font-family:'Sarabun',sans-serif;font-size:13px;}
.usr-btn-confirm{padding:8px 18px;border-radius:8px;border:none;background:#ef4444;color:#fff;font-weight:700;cursor:pointer;font-family:'Sarabun',sans-serif;font-size:13px;}
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
                ระบบสิทธิ์ Admin · ทั้งหมด <b><?= count($users) ?></b> บัญชี
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
                $meta    = $ROLE_META[$u['role']] ?? $ROLE_META['viewer'];
                $initial = mb_strtoupper(mb_substr($u['username'], 0, 1));
                $isSelf  = ((int)$u['id'] === (int)$_SESSION['admin_id']);
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
                                onclick="openEditModal(<?= $u['id'] ?>, '<?= h($u['username']) ?>', '<?= h($u['role']) ?>', <?= $isSelf ? 'true' : 'false' ?>)">
                            <span class="material-symbols-rounded">edit</span>
                        </button>
                        <?php if (!$isSelf): ?>
                        <button type="button" class="t-btn t-del" title="ลบ"
                                onclick="openDeleteConfirm(<?= $u['id'] ?>, '<?= h($u['username']) ?>')">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                        <?php else: ?>
                        <button type="button" class="t-btn" disabled style="opacity:.3;cursor:not-allowed;">
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

<!-- ── Delete Confirm Modal ── -->
<div id="usr-del-overlay" class="usr-overlay">
  <div class="usr-box">
    <div class="del-modal-header">
      <div class="del-modal-icon"><span class="material-symbols-rounded" style="font-size:22px;">warning</span></div>
      <h3 style="margin:0;font-size:15px;font-weight:700;color:#dc2626;">ลบผู้ใช้งาน?</h3>
    </div>
    <div class="del-modal-body">
      ลบบัญชี <strong id="del-username">...</strong> ออกจากระบบ?<br>
      <span style="font-size:12px;color:#ef4444;">ไม่สามารถย้อนกลับได้</span>
    </div>
    <div class="del-modal-footer">
      <button type="button" class="usr-btn-cancel" onclick="closeDeleteConfirm()">ยกเลิก</button>
      <button id="del-confirm-btn" type="button" class="usr-btn-confirm" onclick="doDelete()">ลบเลย</button>
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
    document.getElementById('f-pw').value              = '';
    document.getElementById('f-cpw').value             = '';
    document.getElementById('pw-required').style.display  = 'inline';
    document.getElementById('cpw-required').style.display = 'inline';
    document.getElementById('pw-hint').style.display       = 'none';
    document.getElementById('role-locked-hint').style.display = 'none';
    document.getElementById('save-label').textContent  = 'สร้างบัญชี';
    document.getElementById('form-errors').style.display = 'none';
    showOverlay('usr-form-overlay');
    setTimeout(() => document.getElementById('f-username').focus(), 80);
}

function openEditModal(id, username, role, isSelf) {
    document.getElementById('form-title').textContent  = 'แก้ไข — ' + username;
    document.getElementById('form-icon').textContent   = 'manage_accounts';
    document.getElementById('form-action').value       = 'edit';
    document.getElementById('form-edit-id').value      = id;
    document.getElementById('f-username').value        = username;
    document.getElementById('f-role').value            = role;
    document.getElementById('f-role').disabled         = isSelf;
    document.getElementById('f-pw').value              = '';
    document.getElementById('f-cpw').value             = '';
    document.getElementById('pw-required').style.display  = 'none';
    document.getElementById('cpw-required').style.display = 'none';
    document.getElementById('pw-hint').style.display       = 'block';
    document.getElementById('role-locked-hint').style.display = isSelf ? 'block' : 'none';
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

// ── Delete Modal ────────────────────────────────────────────────
let _delId = null;
function openDeleteConfirm(id, name) {
    _delId = id;
    document.getElementById('del-username').textContent = name;
    showOverlay('usr-del-overlay');
}
function closeDeleteConfirm() { hideOverlay('usr-del-overlay'); }
function doDelete() {
    if (!_delId) return;
    const btn = document.getElementById('del-confirm-btn');
    btn.disabled = true; btn.textContent = 'กำลังลบ...';
    fetch('delete.php?id=' + _delId + '&ajax=1').then(r => r.json()).then(d => {
        closeDeleteConfirm();
        if (d.ok) {
            const row = document.getElementById('user-row-' + _delId);
            if (row) { row.style.transition='opacity .25s,transform .25s'; row.style.opacity='0'; row.style.transform='translateX(30px)'; setTimeout(()=>row.remove(),260); }
            Swal.fire({ icon:'success', title:'ลบผู้ใช้งานเรียบร้อยแล้ว', toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
        }
        btn.disabled = false; btn.textContent = 'ลบเลย';
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
[document.getElementById('usr-form-overlay'), document.getElementById('usr-del-overlay')].forEach(el => {
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
