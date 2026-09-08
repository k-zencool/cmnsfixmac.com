<?php
/* =========================================================
   admin/templates/tabbar_admin.php
   Bottom tab bar — mobile only (hidden ≥992px by admin-mobile.css).

   Replaces the off-canvas drawer below 992px. The drawer is gone on
   mobile entirely; anything not in the 4 tabs lives on /admin/more/.

   Layout is 2 tabs · raised scan button · 2 tabs. All four destinations
   are permission-free, so every role gets the same bar and the same
   muscle memory — the role-specific pages (งานค้าง / ประกัน / ราคา /
   ผู้ใช้) are all reachable from เพิ่มเติม, which gates them with can().
   ========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Path only — query strings never decide which tab is lit.
$tb_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/* match = URL prefix that lights the tab (a section, not a single page) */
$tb_tabs = [
    ['key' => 'dashboard', 'label' => 'หน้าแรก',  'icon' => 'space_dashboard', 'href' => '/admin/dashboard/',                    'match' => '/admin/dashboard'],
    ['key' => 'tracking',  'label' => 'งานซ่อม',  'icon' => 'build_circle',    'href' => '/admin/tracking/index.php',            'match' => '/admin/tracking'],
    ['key' => 'scan',      'label' => 'สแกน',     'icon' => 'qr_code_scanner', 'href' => '/admin/scan/',                         'match' => '/admin/scan',      'scan' => true],
    ['key' => 'inventory', 'label' => 'สต็อก',    'icon' => 'inventory_2',     'href' => '/admin/inventory/index.php?type=all',  'match' => '/admin/inventory'],
    ['key' => 'more',      'label' => 'เพิ่มเติม', 'icon' => 'apps',            'href' => '/admin/more/',                         'match' => '/admin/more'],
];

/* Longest prefix wins, so a deeper path can never light a shallower tab. */
$tb_active = null;
$tb_best   = 0;
foreach ($tb_tabs as $tb_t) {
    $tb_m = $tb_t['match'];
    if (strpos($tb_path, $tb_m) === 0 && strlen($tb_m) > $tb_best) {
        $tb_active = $tb_t['key'];
        $tb_best   = strlen($tb_m);
    }
}
// On a page no tab owns (settings, profile, บทความ…) the user got there
// through เพิ่มเติม, so light that.
if ($tb_active === null) $tb_active = 'more';
?>

<!-- Floating pill bar. The scan chip is a sibling of .tabbar, not a child,
     so it can sit proud of the pill's rounded edge without being clipped by
     it; the <nav> wraps both so all five links stay in one landmark. -->
<nav class="tabbar-wrap" id="tabbar" aria-label="เมนูหลัก">
    <?php /* The bar's silhouette. Two paths: one filled body, one stroked top
             edge, so only the top carries the hairline. admin-mobile.js writes
             both `d` values from the real viewport width — a single stretched
             path would squash the curve on rotation. The inline `d` here is the
             390px default so the bar is never blank before JS runs. */ ?>
    <div class="tabbar">
        <?php foreach ($tb_tabs as $tb_t):
            $tb_on = ($tb_t['key'] === $tb_active);
            if (!empty($tb_t['scan'])):
        ?>
            <?php /* keeps the centre column's width so the other four stay even */ ?>
            <span class="tabbar-item tabbar-slot" aria-hidden="true"></span>
        <?php else: ?>
            <a href="<?= htmlspecialchars($tb_t['href']) ?>"
               class="tabbar-item<?= $tb_on ? ' is-active' : '' ?>"
               <?= $tb_on ? 'aria-current="page"' : '' ?>>
                <span class="tabbar-icon material-symbols-rounded"><?= htmlspecialchars($tb_t['icon']) ?></span>
                <span class="tabbar-label"><?= htmlspecialchars($tb_t['label']) ?></span>
            </a>
        <?php endif; endforeach; ?>
    </div>

    <?php $tb_scan = null; foreach ($tb_tabs as $tb_t) { if (!empty($tb_t['scan'])) $tb_scan = $tb_t; } ?>
    <?php if ($tb_scan): $tb_on = ($tb_scan['key'] === $tb_active); ?>
    <a href="<?= htmlspecialchars($tb_scan['href']) ?>"
       class="tabbar-scan<?= $tb_on ? ' is-active' : '' ?>"
       <?= $tb_on ? 'aria-current="page"' : '' ?>>
        <span class="tabbar-scan-btn">
            <span class="material-symbols-rounded"><?= htmlspecialchars($tb_scan['icon']) ?></span>
        </span>
        <span class="tabbar-label"><?= htmlspecialchars($tb_scan['label']) ?></span>
    </a>
    <?php endif; ?>
</nav>
