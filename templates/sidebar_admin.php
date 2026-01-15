<?php
// admin/sidebar_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

function has($needle)
{
    global $uri;
    return strpos($uri, $needle) !== false;
}

$partsOpen = has('/admin/parts/');
$trackingOpen = has('/admin/tracking/');
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-area">
            <span class="logo-icon material-symbols-rounded">settings</span>
            <h2>Admin Panel</h2>
        </div>
        <button class="close-btn" onclick="toggleSidebar()" aria-label="ปิดเมนู">✖</button>
    </div>

    <nav class="sidebar-nav">
        <a class="<?php echo has('/admin/dashboard/') ? 'active' : ''; ?>" href="/admin/dashboard/">
            <span class="material-symbols-rounded">space_dashboard</span> Dashboard
        </a>

        <div class="has-sub <?php echo $trackingOpen ? 'open' : ''; ?>">
            <div class="sub-head">
                <a href="/admin/tracking/index.php" class="sub-link <?php echo $trackingOpen ? 'active-parent' : ''; ?>">
                    <span class="material-symbols-rounded">build_circle</span>
                    <span class="link-text">ติดตามงานซ่อม</span>
                </a>

                <button type="button" class="sub-toggle" onclick="toggleSubmenu(event, 'tracking-sub')" aria-expanded="<?php echo $trackingOpen ? 'true' : 'false'; ?>">
                    <span class="material-symbols-rounded">keyboard_arrow_down</span>
                </button>
            </div>

            <div id="tracking-sub" class="submenu-wrapper <?php echo $trackingOpen ? 'open' : ''; ?>">
                <div class="submenu-inner">
                    <a href="/admin/tracking/create.php" class="<?php echo has('/tracking/create.php') ? 'sub-active' : ''; ?>">
                        <span class="material-symbols-rounded">add_circle</span> เปิดงานซ่อมใหม่
                    </a>

                    <a href="/admin/tracking/index.php?group=active" class="<?php echo has('group=active') ? 'sub-active' : ''; ?>">
                        <span class="material-symbols-rounded">handyman</span> กำลังซ่อม
                    </a>

                    <a href="/admin/tracking/index.php?group=done" class="<?php echo has('group=done') ? 'sub-active' : ''; ?>">
                        <span class="material-symbols-rounded">verified</span> รอรับ/เสร็จสิ้น
                    </a>

                    <a href="/admin/tracking/history.php" class="<?php echo has('/tracking/history.php') ? 'sub-active' : ''; ?>">
                        <span class="material-symbols-rounded">manage_search</span> ประวัติทั้งหมด
                    </a>
                </div>
            </div>
        </div>

        <a class="<?php echo has('/admin/shop/') ? 'active' : ''; ?>" href="/admin/shop/">
            <span class="material-symbols-rounded">storefront</span> จัดการหน้าร้าน
        </a>

        <a class="<?php echo has('/admin/repairs/') ? 'active' : ''; ?>" href="/admin/repairs/">
            <span class="material-symbols-rounded">collections_bookmark</span> ผลงานทั้งหมด
        </a>

        <a class="<?php echo has('/admin/before_after/') ? 'active' : ''; ?>" href="/admin/before_after/">
            <span class="material-symbols-rounded">compare</span> รูป Before-After
        </a>

        <a class="<?php echo has('/admin/articles/') ? 'active' : ''; ?>" href="/admin/articles/">
            <span class="material-symbols-rounded">article</span> จัดการบทความ
        </a>

        <a class="<?php echo has('/admin/youtube/') ? 'active' : ''; ?>" href="/admin/youtube/">
            <span class="material-symbols-rounded">play_circle</span> จัดการวิดีโอ
        </a>

        <a class="<?php echo has('/admin/pricing/') ? 'active' : ''; ?>" href="/admin/pricing/">
            <span class="material-symbols-rounded">price_change</span> จัดการราคาซ่อม
        </a>

        <a class="<?php echo has('/admin/warranty/') ? 'active' : ''; ?>" href="/admin/warranty/">
            <span class="material-symbols-rounded">security</span> รับประกัน
        </a>

        <div class="has-sub <?php echo $partsOpen ? 'open' : ''; ?>">
            <div class="sub-head">
                <a href="/admin/parts/index.php" class="sub-link <?php echo $partsOpen ? 'active-parent' : ''; ?>">
                    <span class="material-symbols-rounded">inventory_2</span>
                    <span class="link-text">คลังอะไหล่</span>
                </a>

                <button type="button" class="sub-toggle" onclick="toggleSubmenu(event, 'parts-sub')" aria-expanded="<?php echo $partsOpen ? 'true' : 'false'; ?>">
                    <span class="material-symbols-rounded">keyboard_arrow_down</span>
                </button>
            </div>

            <div id="parts-sub" class="submenu-wrapper <?php echo $partsOpen ? 'open' : ''; ?>">
                <div class="submenu-inner">
                    <a href="/admin/parts/index.php?tab=new" class="<?php echo has('tab=new') ? 'sub-active' : ''; ?>">
                        <span class="material-symbols-rounded">new_releases</span> อะไหล่มือ 1
                    </a>

                    <a href="/admin/parts/index.php?tab=used" class="<?php echo has('tab=used') ? 'sub-active' : ''; ?>">
                        <span class="material-symbols-rounded">recycling</span> อะไหล่มือ 2
                    </a>

                    <a href="/admin/parts/index.php?tab=donor" class="<?php echo has('tab=donor') ? 'sub-active' : ''; ?>">
                        <span class="material-symbols-rounded">extension</span> เครื่อง
                    </a>

                    <a href="/admin/parts/index.php?tab=history" class="<?php echo has('tab=history') ? 'sub-active' : ''; ?>">
                        <span class="material-symbols-rounded">history</span> ประวัติสต็อก
                    </a>
                </div>
            </div>
        </div>

        <hr style="margin: 15px 15px; border: 0; border-top: 1px solid #e5e7eb;">

        <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
            <a class="<?php echo has('/admin/user/') ? 'active' : ''; ?>" href="/admin/user/">
                <span class="material-symbols-rounded">manage_accounts</span> จัดการผู้ใช้งาน
            </a>
        <?php endif; ?>

        <a href="/admin/logout.php" class="logout-link">
            <span class="material-symbols-rounded">logout</span> ออกจากระบบ
        </a>
    </nav>
</aside>

<button class="menu-btn" onclick="toggleSidebar()" aria-label="เปิดเมนู">
    <span class="material-symbols-rounded">menu</span>
</button>
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>

<style>
    /* CSS Variables */
    :root {
        --sidebar-bg: #ffffff;
        --sidebar-width: 260px;
        --sidebar-text: #4b5563;
        --sidebar-hover-bg: #f3f4f6;
        --primary-color: #2563eb;
        --primary-text: #ffffff;
        --border-color: #e5e7eb;
    }

    * {
        box-sizing: border-box;
    }

    .sidebar {
        width: var(--sidebar-width);
        background: var(--sidebar-bg);
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1000;
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease;
        overflow-y: auto;
    }

    .sidebar-header {
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--border-color);
        flex-shrink: 0;
    }

    .sidebar-header .logo-area {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--primary-color);
    }

    .sidebar-header h2 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: #111827;
    }

    .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        display: none;
        color: #6b7280;
        outline: none;
    }

    .sidebar-nav {
        padding: 15px 10px;
        flex: 1;
    }

    .sidebar-nav>a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 15px;
        color: var(--sidebar-text);
        text-decoration: none;
        border-radius: 8px;
        margin-bottom: 4px;
        transition: background-color 0.2s;
        font-size: 0.95rem;
        white-space: nowrap;
    }

    .sidebar-nav>a:hover {
        background-color: var(--sidebar-hover-bg);
        color: #111827;
    }

    .sidebar-nav>a.active {
        background-color: var(--primary-color);
        color: var(--primary-text);
        font-weight: 500;
    }

    .sidebar-nav a .material-symbols-rounded {
        font-size: 20px;
        flex-shrink: 0;
    }

    /* Dropdown Styles */
    .has-sub {
        margin-bottom: 4px;
    }

    .sub-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-radius: 8px;
        transition: background-color 0.2s;
        width: 100%;
        overflow: hidden;
    }

    .sub-head:hover {
        background-color: var(--sidebar-hover-bg);
    }

    .sub-link {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 0 12px 15px;
        color: var(--sidebar-text);
        text-decoration: none;
        font-size: 0.95rem;
        white-space: nowrap;
        min-width: 0;
        outline: none;
    }

    .link-text {
        white-space: nowrap;
    }

    .sub-toggle {
        width: 40px;
        height: 44px;
        background: transparent;
        border: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        flex-shrink: 0;
        outline: none;
    }

    .sub-head:hover .sub-link {
        color: #111827;
    }

    .sub-head:hover .sub-toggle {
        color: #4b5563;
    }

    .sub-link.active-parent {
        color: var(--primary-color);
        font-weight: 600;
    }

    .sub-link.active-parent .material-symbols-rounded {
        color: var(--primary-color);
    }

    /* Animation */
    .has-sub.open .sub-toggle .material-symbols-rounded {
        transform: rotate(180deg);
        transition: transform 0.3s;
    }

    .submenu-wrapper {
        display: grid;
        grid-template-rows: 0fr;
        transition: grid-template-rows 0.3s ease-out;
    }

    .submenu-wrapper.open {
        grid-template-rows: 1fr;
    }

    @media (min-width: 769px) {
        .has-sub:hover .submenu-wrapper {
            grid-template-rows: 1fr;
        }

        .has-sub:hover .sub-toggle .material-symbols-rounded {
            transform: rotate(180deg);
        }

        .has-sub:hover .sub-head {
            background-color: var(--sidebar-hover-bg);
            color: #111827;
        }
    }

    .submenu-inner {
        overflow: hidden;
        padding-left: 12px;
    }

    .submenu-inner a {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 15px;
        font-size: 0.9rem;
        color: #6b7280;
        text-decoration: none;
        border-radius: 8px;
        margin-bottom: 2px;
        white-space: nowrap;
    }

    .submenu-inner a:hover {
        color: var(--primary-color);
        background: rgba(37, 99, 235, 0.05);
    }

    .submenu-inner a.sub-active {
        color: var(--primary-color);
        background: rgba(37, 99, 235, 0.1);
        font-weight: 600;
    }

    /* Mobile */
    .menu-btn {
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 999;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 8px;
        cursor: pointer;
        display: none;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 998;
        display: none;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .overlay.show {
        display: block;
        opacity: 1;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .menu-btn,
        .close-btn {
            display: block;
        }
    }
</style>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
        document.getElementById('overlay').classList.toggle('show');
    }

    function toggleSubmenu(e, id) {
        e.stopPropagation();
        var wrapper = e.currentTarget.closest('.has-sub');
        var submenu = wrapper.querySelector('.submenu-wrapper');
        var btn = e.currentTarget;
        var isExpanded = btn.getAttribute('aria-expanded') === 'true';

        if (submenu) submenu.classList.toggle('open');
        if (wrapper) wrapper.classList.toggle('open');
        btn.setAttribute('aria-expanded', !isExpanded);
    }
</script>