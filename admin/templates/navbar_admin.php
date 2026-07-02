<?php
// เช็ค Session กันเหนียว
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// auth helpers (is_super_admin / is_viewing_as / view_as_roles ...) — guarded require_once
require_once __DIR__ . '/../../includes/auth.php';

// ตั้งค่า Default เผื่อ Database พัง
$admin_id  = $_SESSION['admin_id'] ?? null;
$adminName = 'Admin';
$avatar_file = null;

// =========================================================
// ✅ ดึงรูป + ชื่อ + ตำแหน่ง จากฐานข้อมูลแบบ Real-time เลยสัส!
// =========================================================
global $pdo;

if ($admin_id && isset($pdo)) {
    try {
        // SELECT * กัน query พังถ้าตารางยังไม่มีคอลัมน์ full_name/avatar (เหมือนหน้า profile)
        $stmt_user = $pdo->prepare("SELECT * FROM admin_users WHERE id = :id");
        $stmt_user->execute([':id' => $admin_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $avatar_file = $user_data['avatar'] ?? null;
            // ถ้ามี full_name ให้ใช้ full_name ถ้าไม่มีให้ใช้ username แทน
            $adminName = !empty($user_data['full_name']) ? $user_data['full_name'] : ($user_data['username'] ?? 'Admin');
        }
    } catch (PDOException $e) {
        // เงียบไว้
    }
}

// เอาตัวอักษรแรกของชื่อมาทำรูปรองรับเผื่อยังไม่อัปโหลดรูป
$firstChar = strtoupper(mb_substr($adminName, 0, 1)); 
?>

<nav class="topbar">
    <div class="topbar-left">
        <button class="mobile-toggle-btn" onclick="toggleSidebarMobile()">
            <span class="material-symbols-rounded">menu</span>
        </button>
        <h2 class="page-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?></h2>
    </div>

    <div class="topbar-right">
        <button class="toggle-btn theme-btn" onclick="toggleTheme()" title="เปลี่ยนธีม">
            <span class="material-symbols-rounded" id="themeIcon">dark_mode</span>
        </button>

        <div class="user-dropdown-wrapper">
            <div class="user-profile" onclick="toggleUserMenu(event)">
                
                <div class="user-avatar" style="padding: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; background: var(--bg-surface-alt);">
                    <?php if (!empty($avatar_file)): ?>
                        <img src="/uploads/avatars/<?= htmlspecialchars($avatar_file) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?= $firstChar ?>
                    <?php endif; ?>
                </div>

                <div class="user-info">
                    <span class="name"><?= htmlspecialchars($adminName) ?></span>
                </div>
                <span class="material-symbols-rounded chevron">expand_more</span>
            </div>

            <div class="dropdown-menu" id="userMenu">
                <a href="/admin/profile/" class="dropdown-item">
                    <span class="material-symbols-rounded">account_circle</span> โปรไฟล์
                </a>

                <?php if (is_super_admin()): ?>
                    <div class="dropdown-divider"></div>
                    <div style="padding:6px 14px 4px; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px;">ดูในมุมมองยศ</div>
                    <?php foreach (view_as_roles() as $r): $active = (viewing_as_role() === $r); ?>
                        <a href="/admin/view_as.php?role=<?= $r ?>" class="dropdown-item" style="display:flex; align-items:center; <?= $active ? 'background:rgba(37,99,235,.08); font-weight:700;' : '' ?>">
                            <span class="material-symbols-rounded">visibility</span> <?= htmlspecialchars(role_label($r)) ?>
                            <?php if ($active): ?><span class="material-symbols-rounded" style="margin-left:auto; color:#10b981; font-size:18px;">check</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (is_viewing_as()): ?>
                        <a href="/admin/view_as.php?role=exit" class="dropdown-item" style="color:#10b981; font-weight:700;">
                            <span class="material-symbols-rounded">undo</span> กลับมุมมอง Super Admin
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="dropdown-divider"></div>
                <a href="/admin/logout.php" class="dropdown-item text-danger">
                    <span class="material-symbols-rounded">logout</span> ออกจากระบบ
                </a>
            </div>
        </div>
    </div>
</nav>

<?php if (is_viewing_as()): ?>
<div class="viewas-banner" style="display:flex; align-items:center; justify-content:center; gap:10px; padding:8px 16px; background:linear-gradient(90deg,#f59e0b,#f97316); color:#fff; font-size:13px; font-weight:600; box-shadow:0 2px 6px rgba(0,0,0,.12);">
    <span class="material-symbols-rounded" style="font-size:18px;">visibility</span>
    กำลังดูในมุมมอง: <strong><?= htmlspecialchars(role_label(viewing_as_role())) ?></strong>
    <a href="/admin/view_as.php?role=exit" style="margin-left:6px; background:rgba(255,255,255,.22); color:#fff; padding:3px 14px; border-radius:20px; text-decoration:none; font-weight:700;">ออกจากมุมมอง</a>
</div>
<?php endif; ?>