<?php
// เช็ค Session กันเหนียว
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        // ดึงแม่งมาให้หมดในรอบเดียว
        $stmt_user = $pdo->prepare("SELECT avatar, full_name, username, role FROM admin_users WHERE id = :id");
        $stmt_user->execute([':id' => $admin_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $avatar_file = $user_data['avatar'];
            // ถ้ามี full_name ให้ใช้ full_name ถ้าไม่มีให้ใช้ username แทน
            $adminName = !empty($user_data['full_name']) ? $user_data['full_name'] : $user_data['username'];
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

                <div class="dropdown-divider"></div>
                <a href="/admin/logout.php" class="dropdown-item text-danger">
                    <span class="material-symbols-rounded">logout</span> ออกจากระบบ
                </a>
            </div>
        </div>
    </div>
</nav>