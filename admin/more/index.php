<?php
/* =========================================================
   admin/more/index.php — "เพิ่มเติม" (mobile only)

   The 5th tab. Below 992px the sidebar drawer is gone, so this page is
   the ONLY way to reach anything that is not one of the 4 tabs. Its link
   list must therefore stay in sync with sidebar_admin.php — same hrefs,
   same permission gates. If you add a destination to the sidebar, add it
   here too or it becomes unreachable on a phone.

   Desktop users can still land here; the sidebar is right there, so it
   just renders as a plain index page.
   ========================================================= */
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
require_login();

$pageTitle = "เพิ่มเติม";
include '../templates/header_admin.php';

// Current admin, for the account card at the bottom.
$mr_name = 'Admin'; $mr_sub = ''; $mr_avatar = null;
try {
    $mr_st = $pdo->prepare("SELECT * FROM admin_users WHERE id = :id");
    $mr_st->execute([':id' => $_SESSION['admin_id']]);
    if ($mr_u = $mr_st->fetch(PDO::FETCH_ASSOC)) {
        $mr_name   = !empty($mr_u['full_name']) ? $mr_u['full_name'] : ($mr_u['username'] ?? 'Admin');
        $mr_sub    = !empty($mr_u['email']) ? $mr_u['email'] : '';
        $mr_avatar = $mr_u['avatar'] ?? null;
    }
} catch (PDOException $e) { /* silent — the card degrades to initials */ }
$mr_initial = mb_strtoupper(mb_substr($mr_name, 0, 1));
$mr_role    = $_SESSION['admin_role'] ?? '';

/* Groups mirror sidebar_admin.php. 'show' false = hidden for this role.
   Entries already covered by a tab (dashboard/tracking/inventory) are
   deliberately left out — except the sub-destinations a tab cannot reach. */
$mr_groups = [
    [
        'label' => 'งานซ่อม',
        'items' => [
            ['icon' => 'add_task',    'label' => 'เปิดงานใหม่',   'desc' => 'รับเครื่องเข้าร้าน',        'href' => '/admin/tracking/create.php',            'show' => can('jobs.write')],
            ['icon' => 'engineering', 'label' => 'กำลังซ่อม',     'desc' => 'งานที่อยู่ระหว่างดำเนินการ', 'href' => '/admin/tracking/index.php?group=active', 'show' => true],
            ['icon' => 'task_alt',    'label' => 'รอรับ / เสร็จ',  'desc' => 'ซ่อมจบ รอลูกค้ามารับ',     'href' => '/admin/tracking/index.php?group=done',   'show' => true],
            ['icon' => 'history',     'label' => 'ประวัติงานซ่อม', 'desc' => 'งานที่ปิดไปแล้ว',           'href' => '/admin/tracking/history.php',           'show' => true],
            ['icon' => 'qr_code_2',   'label' => 'สติ๊กเกอร์ QR',  'desc' => 'พิมพ์เลขที่ซ่อมล่วงหน้า',    'href' => '/admin/tracking/stickers.php',          'show' => true],
        ],
    ],
    [
        'label' => 'คลังอะไหล่',
        'items' => [
            ['icon' => 'new_releases',   'label' => 'อะไหล่มือ 1',   'desc' => 'ของใหม่',              'href' => '/admin/inventory/index.php?type=new',     'show' => true],
            ['icon' => 'recycling',      'label' => 'อะไหล่มือ 2',   'desc' => 'ของถอด / มือสอง',      'href' => '/admin/inventory/index.php?type=used',    'show' => true],
            ['icon' => 'devices_other',  'label' => 'เครื่องอะไหล่',  'desc' => 'เครื่องซากไว้ถอดอะไหล่', 'href' => '/admin/inventory/index.php?type=machine', 'show' => true],
            ['icon' => 'sell',           'label' => 'เครื่องกำลังขาย', 'desc' => 'ตั้งขายหน้าร้าน',      'href' => '/admin/inventory/index.php?type=sale',    'show' => true],
            ['icon' => 'folder_managed', 'label' => 'จัดการหมวดหมู่', 'desc' => 'โฟลเดอร์อะไหล่',       'href' => '/admin/inventory/categories.php',         'show' => can('parts.manage')],
            ['icon' => 'qr_code_2',      'label' => 'ฉลาก QR',       'desc' => 'อะไหล่ / เครื่องซาก',   'href' => '/admin/inventory/labels.php',             'show' => can('parts.manage')],
            ['icon' => 'shelves',        'label' => 'ชั้นเก็บของ',    'desc' => 'ช่อง A-01… + ฉลาก QR',   'href' => '/admin/inventory/bins.php',               'show' => can('parts.manage')],
            ['icon' => 'history',        'label' => 'ประวัติสต็อก',  'desc' => 'เข้า-ออกอะไหล่',        'href' => '/admin/inventory/logs.php',               'show' => true],
        ],
    ],
    [
        'label' => 'หน้าร้าน & เนื้อหา',
        'items' => [
            ['icon' => 'storefront',           'label' => 'จัดการหน้าร้าน', 'desc' => 'สินค้าที่ลงขาย',     'href' => '/admin/shop/',     'show' => can('content.write')],
            ['icon' => 'collections_bookmark', 'label' => 'ผลงานทั้งหมด',  'desc' => 'ผลงานซ่อมหน้าเว็บ',   'href' => '/admin/repairs/',  'show' => can('content.write')],
            ['icon' => 'article',              'label' => 'จัดการบทความ',  'desc' => 'บทความ / บล็อก',     'href' => '/admin/articles/', 'show' => can('content.write')],
        ],
    ],
    [
        'label' => 'จัดการ',
        'items' => [
            ['icon' => 'price_change',    'label' => 'จัดการราคาซ่อม', 'desc' => 'ตารางราคาหน้าเว็บ',       'href' => '/admin/pricing/',  'show' => can('pricing.write')],
            ['icon' => 'verified_user',   'label' => 'ใบรับประกัน',   'desc' => 'ออกใบ / เคลมประกัน',      'href' => '/admin/warranty/', 'show' => can('content.write')],
            ['icon' => 'pending_actions', 'label' => 'งานค้าง',       'desc' => 'เครื่องที่ยังอยู่ในร้าน',   'href' => '/admin/manager/',  'show' => can('manager.center')],
        ],
    ],
    [
        'label' => 'ระบบ',
        'items' => [
            ['icon' => 'manage_accounts', 'label' => 'จัดการผู้ใช้งาน', 'desc' => 'บัญชีและยศ',        'href' => '/admin/user/',     'show' => ($mr_role === 'super_admin')],
            ['icon' => 'settings',        'label' => 'การตั้งค่า',     'desc' => 'ระบบและการเชื่อมต่อ', 'href' => '/admin/settings/', 'show' => true],
            ['icon' => 'help',            'label' => 'ศูนย์ช่วยเหลือ', 'desc' => 'วิธีใช้ / ติดต่อผู้ดูแล', 'href' => '/admin/help/',     'show' => true],
        ],
    ],
];
?>

<link rel="stylesheet" href="<?= $assets_base ?>css/more.css?v=<?= asset_ver('/admin/templates/assets/css/more.css') ?>">

<div class="more-page">

    <?php foreach ($mr_groups as $mr_g):
        // Drop the whole group when this role can see none of its items.
        $mr_visible = array_values(array_filter($mr_g['items'], fn($i) => $i['show']));
        if (!$mr_visible) continue;
    ?>
    <section class="more-group">
        <h2 class="more-group-label"><?= htmlspecialchars($mr_g['label']) ?></h2>
        <div class="more-list">
            <?php foreach ($mr_visible as $mr_i): ?>
            <a class="more-row" href="<?= htmlspecialchars($mr_i['href']) ?>">
                <span class="more-ico material-symbols-rounded"><?= htmlspecialchars($mr_i['icon']) ?></span>
                <span class="more-text">
                    <span class="more-label"><?= htmlspecialchars($mr_i['label']) ?></span>
                    <span class="more-desc"><?= htmlspecialchars($mr_i['desc']) ?></span>
                </span>
                <span class="more-chev material-symbols-rounded">chevron_right</span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <?php if (is_super_admin()): ?>
    <?php /* The topbar avatar dropdown is hidden on mobile, and this was the
             only way in — without this block "ดูในมุมมองยศ" is unreachable on
             a phone. Keep it in step with navbar_admin.php. */ ?>
    <section class="more-group">
        <h2 class="more-group-label">ดูในมุมมองยศ</h2>
        <div class="more-list">
            <?php foreach (view_as_roles() as $mr_r): $mr_on = (viewing_as_role() === $mr_r); ?>
            <a class="more-row" href="/admin/view_as.php?role=<?= urlencode($mr_r) ?>">
                <span class="more-ico material-symbols-rounded">visibility</span>
                <span class="more-text">
                    <span class="more-label"><?= htmlspecialchars(role_label($mr_r)) ?></span>
                </span>
                <?php if ($mr_on): ?>
                    <span class="more-chev material-symbols-rounded more-check">check</span>
                <?php else: ?>
                    <span class="more-chev material-symbols-rounded">chevron_right</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>

            <?php if (is_viewing_as()): ?>
            <a class="more-row more-exitview" href="/admin/view_as.php?role=exit">
                <span class="more-ico material-symbols-rounded">undo</span>
                <span class="more-text">
                    <span class="more-label">กลับมุมมอง Super Admin</span>
                </span>
            </a>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="more-group">
        <h2 class="more-group-label">บัญชี</h2>
        <div class="more-list">
            <a class="more-row more-account" href="/admin/profile/">
                <span class="more-avatar">
                    <?php if (!empty($mr_avatar)): ?>
                        <img src="/uploads/avatars/<?= htmlspecialchars($mr_avatar) ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars($mr_initial) ?>
                    <?php endif; ?>
                </span>
                <span class="more-text">
                    <span class="more-label"><?= htmlspecialchars($mr_name) ?></span>
                    <span class="more-desc"><?= htmlspecialchars($mr_sub !== '' ? $mr_sub : role_label($mr_role)) ?></span>
                </span>
                <span class="more-chev material-symbols-rounded">chevron_right</span>
            </a>

            <button type="button" class="more-row more-theme" onclick="toggleTheme()">
                <span class="more-ico material-symbols-rounded js-theme-icon">dark_mode</span>
                <span class="more-text">
                    <span class="more-label">เปลี่ยนธีม</span>
                    <span class="more-desc">สลับสว่าง / มืด</span>
                </span>
            </button>

            <a class="more-row more-logout" href="/admin/logout.php">
                <span class="more-ico material-symbols-rounded">logout</span>
                <span class="more-text">
                    <span class="more-label">ออกจากระบบ</span>
                </span>
            </a>
        </div>
    </section>

    <p class="more-version">CMNS Fix Mac Admin</p>
</div>

<?php include '../templates/footer_admin.php'; ?>
