<?php
// admin/templates/sidebar_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('can')) {
    require_once __DIR__ . '/../../includes/auth.php';
}

// ── Current admin (for the user card at the bottom) ──
global $pdo;
$sb_name   = 'Admin';
$sb_email  = '';
$sb_avatar = null;
if (!empty($_SESSION['admin_id']) && isset($pdo)) {
    try {
        $sb_stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = :id");
        $sb_stmt->execute([':id' => $_SESSION['admin_id']]);
        if ($u = $sb_stmt->fetch(PDO::FETCH_ASSOC)) {
            $sb_name   = !empty($u['full_name']) ? $u['full_name'] : ($u['username'] ?? 'Admin');
            $sb_email  = !empty($u['email']) ? $u['email'] : ($u['role'] ?? '');
            $sb_avatar = $u['avatar'] ?? null;
        }
    } catch (PDOException $e) { /* silent */ }
}
$sb_initial = mb_strtoupper(mb_substr($sb_name, 0, 1));
?>

<aside class="sidebar" id="sidebar">
    
    <div class="sidebar-header">
        <div class="logo-area">
            <a href="/admin/dashboard/" class="brand-link" title="CMNS FixMac Admin">
                <img class="brand-logo" src="/assets/img/Logo1.png" alt="CMNS FixMac">
            </a>
        </div>
        
        <button class="toggle-btn" onclick="toggleSidebarCollapse()" title="ย่อเมนู">
            <span class="material-symbols-rounded">menu_open</span>
        </button>

        <button class="close-btn" onclick="toggleSidebarMobile()" title="ปิดเมนู">
            <span class="material-symbols-rounded">close</span>
        </button>
    </div>

    <nav class="sidebar-nav" id="sidebarNav">

        <span class="nav-section">เมนูหลัก</span>

        <a href="/admin/dashboard/" title="Dashboard">
            <span class="material-symbols-rounded">space_dashboard</span>
            <span class="link-text">Dashboard</span>
        </a>

        <div class="has-sub">
            <div class="sub-head" onclick="toggleSubmenu(this)">
                <div class="sub-link">
                    <span class="material-symbols-rounded">build_circle</span>
                    <span class="link-text">ติดตามงานซ่อม</span>
                </div>
                <span class="material-symbols-rounded sub-toggle">keyboard_arrow_down</span>
            </div>
            <div class="submenu-wrapper">
                <div class="submenu-inner">
                    <a href="/admin/tracking/index.php">
                        <span class="material-symbols-rounded" style="font-size:18px;">monitoring</span> ภาพรวม
                    </a>
                    <?php if (can('jobs.write')): ?>
                    <a href="/admin/tracking/create.php">
                        <span class="material-symbols-rounded" style="font-size:18px;">add_task</span> เปิดงานใหม่
                    </a>
                    <?php endif; ?>
                    <a href="/admin/tracking/index.php?group=active">
                        <span class="material-symbols-rounded" style="font-size:18px;">engineering</span> กำลังซ่อม
                    </a>
                    <a href="/admin/tracking/index.php?group=done">
                        <span class="material-symbols-rounded" style="font-size:18px;">task_alt</span> รอรับ/เสร็จ
                    </a>
                    <a href="/admin/tracking/history.php">
                        <span class="material-symbols-rounded" style="font-size:18px;">history</span> ประวัติ
                    </a>
                </div>
            </div>
        </div>

        <?php if (can('content.write')): ?>
        <a href="/admin/shop/" title="จัดการหน้าร้าน">
            <span class="material-symbols-rounded">storefront</span>
            <span class="link-text">จัดการหน้าร้าน</span>
        </a>

        <a href="/admin/repairs/" title="ผลงานทั้งหมด">
            <span class="material-symbols-rounded">collections_bookmark</span>
            <span class="link-text">ผลงานทั้งหมด</span>
        </a>

        <a href="/admin/articles/" title="จัดการบทความ">
            <span class="material-symbols-rounded">article</span>
            <span class="link-text">จัดการบทความ</span>
        </a>
        <?php endif; ?>

        <span class="nav-section">จัดการ</span>

        <?php if (can('pricing.write')): ?>
        <a href="/admin/pricing/" title="จัดการราคาซ่อม">
            <span class="material-symbols-rounded">price_change</span>
            <span class="link-text">จัดการราคาซ่อม</span>
        </a>
        <?php endif; ?>

        <?php if (can('content.write')): ?>
        <a href="/admin/warranty/" title="ใบรับประกัน">
            <span class="material-symbols-rounded">verified_user</span>
            <span class="link-text">ใบรับประกัน</span>
        </a>
        <?php endif; ?>

        <div class="has-sub">
            <div class="sub-head" onclick="toggleSubmenu(this)">
                <div class="sub-link">
                    <span class="material-symbols-rounded">inventory_2</span>
                    <span class="link-text">คลังอะไหล่</span>
                </div>
                <span class="material-symbols-rounded sub-toggle">keyboard_arrow_down</span>
            </div>
            <div class="submenu-wrapper">
                <div class="submenu-inner">
                    <a href="/admin/inventory/index.php?type=all">
                        <span class="material-symbols-rounded" style="font-size:18px;">dashboard</span> ภาพรวมทั้งหมด
                    </a>
                    <a href="/admin/inventory/index.php?type=new">
                        <span class="material-symbols-rounded" style="font-size:18px;">new_releases</span> อะไหล่มือ 1
                    </a>
                    <a href="/admin/inventory/index.php?type=used">
                        <span class="material-symbols-rounded" style="font-size:18px;">recycling</span> อะไหล่มือ 2
                    </a>
                    <a href="/admin/inventory/index.php?type=machine">
                        <span class="material-symbols-rounded" style="font-size:18px;">devices_other</span> เครื่องอะไหล่
                    </a>
                    <a href="/admin/inventory/index.php?type=sale">
                        <span class="material-symbols-rounded" style="font-size:18px;">sell</span> เครื่องกำลังขาย
                    </a>

                    <div class="dropdown-divider" style="margin: 5px 0; border-top: 1px solid var(--border); opacity: 0.3;"></div>
                    
                    <?php if (can('parts.manage')): ?>
                    <a href="/admin/inventory/categories.php">
                        <span class="material-symbols-rounded" style="font-size:18px;">folder_managed</span> จัดการหมวดหมู่
                    </a>
                    <?php endif; ?>
                    <a href="/admin/inventory/logs.php">
                        <span class="material-symbols-rounded" style="font-size:18px;">history</span> ประวัติสต็อก
                    </a>
                </div>
            </div>
        </div>

        <?php if (function_exists('can') && can('manager.center')): ?>
        <span class="nav-section">ผู้จัดการ</span>

        <a href="/admin/manager/" title="งานค้าง — เครื่องที่ยังอยู่ในร้าน">
            <span class="material-symbols-rounded">pending_actions</span>
            <span class="link-text">งานค้าง</span>
        </a>
        <?php endif; ?>

        <span class="nav-section">ระบบ</span>

        <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="/admin/user/" title="จัดการผู้ใช้งาน">
                <span class="material-symbols-rounded">manage_accounts</span>
                <span class="link-text">จัดการผู้ใช้งาน</span>
            </a>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <a href="/admin/help/" title="ศูนย์ช่วยเหลือ">
            <span class="material-symbols-rounded">help</span>
            <span class="link-text">ศูนย์ช่วยเหลือ</span>
        </a>
        <a href="/admin/settings/" title="การตั้งค่า">
            <span class="material-symbols-rounded">settings</span>
            <span class="link-text">การตั้งค่า</span>
        </a>

        <div class="sidebar-user" id="sidebarUser">
            <div class="sidebar-user-main">
                <a class="sb-userlink" href="/admin/profile/" title="ไปที่โปรไฟล์">
                    <div class="sb-avatar">
                        <?php if (!empty($sb_avatar)): ?>
                            <img src="/uploads/avatars/<?= htmlspecialchars($sb_avatar) ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars($sb_initial) ?>
                        <?php endif; ?>
                    </div>
                    <div class="sb-user-info">
                        <span class="sb-name"><?= htmlspecialchars($sb_name) ?></span>
                        <?php if ($sb_email !== ''): ?><span class="sb-email"><?= htmlspecialchars($sb_email) ?></span><?php endif; ?>
                    </div>
                </a>
                <button type="button" class="sb-kebab" onclick="toggleSidebarUser(event)" title="ตัวเลือก" aria-label="ตัวเลือก">
                    <span class="material-symbols-rounded">more_vert</span>
                </button>
            </div>
            <div class="sidebar-user-menu" id="sidebarUserMenu">
                <a href="/admin/profile/"><span class="material-symbols-rounded">account_circle</span> โปรไฟล์</a>
                <a href="/admin/logout.php" class="logout-link"><span class="material-symbols-rounded">logout</span> ออกจากระบบ</a>
            </div>
        </div>
    </div>
</aside>

<div id="overlay" class="overlay" onclick="toggleSidebarMobile()"></div>