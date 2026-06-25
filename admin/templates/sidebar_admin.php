<?php
// admin/templates/sidebar_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<aside class="sidebar" id="sidebar">
    
    <div class="sidebar-header">
        <div class="logo-area">
            <span class="material-symbols-rounded">settings_suggest</span>
            <span class="logo-text" style="margin-left: 11px;">Admin Panel</span>
        </div>
        
        <button class="toggle-btn" onclick="toggleSidebarCollapse()" title="ย่อเมนู">
            <span class="material-symbols-rounded">menu_open</span>
        </button>

        <button class="close-btn" onclick="toggleSidebarMobile()" title="ปิดเมนู">
            <span class="material-symbols-rounded">close</span>
        </button>
    </div>

    <nav class="sidebar-nav" id="sidebarNav">
        
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
                    <a href="/admin/tracking/create.php">
                        <span class="material-symbols-rounded" style="font-size:18px;">add_task</span> เปิดงานใหม่
                    </a>
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

        <a href="/admin/pricing/" title="จัดการราคาซ่อม">
            <span class="material-symbols-rounded">price_change</span>
            <span class="link-text">จัดการราคาซ่อม</span>
        </a>

        <a href="/admin/warranty/" title="ใบรับประกัน">
            <span class="material-symbols-rounded">verified_user</span>
            <span class="link-text">ใบรับประกัน</span>
        </a>

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
                    
                    <a href="/admin/inventory/categories.php">
                        <span class="material-symbols-rounded" style="font-size:18px;">folder_managed</span> จัดการหมวดหมู่
                    </a>
                    <a href="/admin/inventory/logs.php">
                        <span class="material-symbols-rounded" style="font-size:18px;">history</span> ประวัติสต็อก
                    </a>
                </div>
            </div>
        </div>

        <div class="has-sub">
            <div class="sub-head" onclick="toggleSubmenu(this)">
                <div class="sub-link">
                    <span class="material-symbols-rounded">chat</span>
                    <span class="link-text">Chat Inbox</span>
                </div>
                <span class="material-symbols-rounded sub-toggle">keyboard_arrow_down</span>
            </div>
            <div class="submenu-wrapper">
                <div class="submenu-inner">
                    <a href="/admin/chat/">
                        <span class="material-symbols-rounded" style="font-size:18px">inbox</span> Inbox
                    </a>
                    <a href="/admin/chat/settings.php">
                        <span class="material-symbols-rounded" style="font-size:18px">link</span> Connections
                    </a>
                </div>
            </div>
        </div>

        <hr style="margin: 14px 12px; border: 0; border-top: 1px solid var(--border);">

        <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a href="/admin/user/" title="จัดการผู้ใช้งาน">
                <span class="material-symbols-rounded">manage_accounts</span>
                <span class="link-text">จัดการผู้ใช้งาน</span>
            </a>
        <?php endif; ?>

        <a href="/admin/logout.php" class="logout-link" title="ออกจากระบบ">
            <span class="material-symbols-rounded">logout</span>
            <span class="link-text">ออกจากระบบ</span>
        </a>

    </nav>
</aside>

<div id="overlay" class="overlay" onclick="toggleSidebarMobile()"></div>