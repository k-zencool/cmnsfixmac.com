<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = "คลังอะไหล่";
include '../templates/header_admin.php';

// 1. Get Type from URL
$current_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Setup Header UI
$header_map = [
    'all'     => ['title' => 'INVENTORY / ALL ITEMS',   'icon' => 'inventory_2',  'color' => 'var(--primary)'],
    'new'     => ['title' => 'INVENTORY / NEW STOCK',   'icon' => 'new_releases', 'color' => '#10b981'],
    'used'    => ['title' => 'INVENTORY / USED PARTS',  'icon' => 'build',        'color' => '#f59e0b'],
    'machine' => ['title' => 'INVENTORY / MACHINES',    'icon' => 'memory',       'color' => '#8b5cf6'],
    'sale'    => ['title' => 'INVENTORY / FOR SALE',    'icon' => 'sell',         'color' => '#ef4444']
];

$header = $header_map[$current_type] ?? $header_map['all'];
$header_title = $header['title'];
$header_icon  = $header['icon'];
$header_color = $header['color'];

// 2. Fetch Root Categories
$stmt = $pdo->query("SELECT * FROM parts_categories WHERE parent_id IS NULL ORDER BY name ASC");
$root_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Stat cards (ตาม type tab ที่เลือก)
$tw = $current_type !== 'all' ? "i.type = ?" : "1";
$tp = $current_type !== 'all' ? [$current_type] : [];

$stmt_stat = $pdo->prepare("
    SELECT COUNT(DISTINCT i.id) AS item_count,
           COALESCE(SUM(CASE WHEN i.type IN ('new','used') THEN l.qty_remaining ELSE 0 END), 0) AS on_hand
    FROM inventory i
    LEFT JOIN inventory_lots l ON i.id = l.inventory_id
    WHERE $tw");
$stmt_stat->execute($tp);
$stat = $stmt_stat->fetch(PDO::FETCH_ASSOC);
$total_items = $stat['item_count'];

$stmt_low = $pdo->prepare("
    SELECT COUNT(*) FROM (
        SELECT i.id, COALESCE(SUM(l.qty_remaining), 0) AS q, MAX(i.min_qty) AS mq
        FROM inventory i
        LEFT JOIN inventory_lots l ON i.id = l.inventory_id
        WHERE $tw AND i.type IN ('new','used')
        GROUP BY i.id
        HAVING q <= mq
    ) t");
$stmt_low->execute($tp);
$stat_low = (int)$stmt_low->fetchColumn();

$stat_value = null;
if (can('shop.finance')) {
    $stmt_val = $pdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN i.type IN ('new','used') THEN l.qty_remaining * i.sell_price ELSE 0 END), 0)
        FROM inventory i
        LEFT JOIN inventory_lots l ON i.id = l.inventory_id
        WHERE $tw");
    $stmt_val->execute($tp);
    $stat_value = (float)$stmt_val->fetchColumn();

    $stmt_val2 = $pdo->prepare("
        SELECT COALESCE(SUM(i.sell_price), 0)
        FROM inventory i
        WHERE $tw AND i.type IN ('machine','sale') AND i.status != 'SOLD'");
    $stmt_val2->execute($tp);
    $stat_value += (float)$stmt_val2->fetchColumn();
}

// 4. Fetch Stats for each Folder (ดึงจากตาราง Lots แทน)
// หมวดมี 2 ชั้น (root = parent_id NULL) เลย map ทุกหมวดขึ้น root ด้วย COALESCE(parent_id, id)
// แล้วรวมยอดทีเดียวจบ — เดิมยิง 2 query ต่อ 1 หมวด (N+1) ตอนนี้เหลือ query เดียวทั้งหน้า
// machine/sale = individual units (no lots) → COUNT items; new/used = lot-based → SUM qty_remaining
$stmt_stats = $pdo->query("
    SELECT COALESCE(c.parent_id, c.id) AS root_id,
           i.type,
           CASE
               WHEN i.type IN ('machine','sale') THEN COUNT(DISTINCT i.id)
               ELSE COALESCE(SUM(l.qty_remaining), 0)
           END AS total_qty
    FROM inventory i
    JOIN parts_categories c ON c.id = i.category_id
    LEFT JOIN inventory_lots l ON l.inventory_id = i.id
    WHERE NOT (i.type = 'sale' AND i.status = 'SOLD')
    GROUP BY root_id, i.type
");

$stats_by_root = [];
foreach ($stmt_stats->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $stats_by_root[(int)$row['root_id']][$row['type']] = (int)$row['total_qty'];
}

foreach ($root_categories as $key => $cat) {
    $stats = $stats_by_root[(int)$cat['id']] ?? [];
    $root_categories[$key]['stats'] = [
        'new'     => $stats['new']     ?? 0,
        'used'    => $stats['used']    ?? 0,
        'machine' => $stats['machine'] ?? 0,
        'sale'    => $stats['sale']    ?? 0
    ];
}

?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="assets/css/inventory-v2.css?v=<?= asset_ver('/admin/inventory/assets/css/inventory-v2.css') ?>">
<link rel="stylesheet" href="assets/css/inventory-index.css?v=<?= asset_ver('/admin/inventory/assets/css/inventory-index.css') ?>">
<link rel="stylesheet" href="../templates/assets/css/modal.css?v=<?= asset_ver('/admin/templates/assets/css/modal.css') ?>">

<div class="cmns-wrapper" style="--active-theme-color: <?= $header_color ?>;">
    
    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color: <?= $header_color ?>;">
                <span class="material-symbols-rounded" style="font-size: 32px;"><?= $header_icon ?></span>
                <?= $header_title ?>
            </h1>
            <p style="color: var(--text-muted); margin-top: 5px; font-size: 14px;">
                ทั้งหมด <b><?= number_format($total_items) ?></b> รายการในหมวดนี้
            </p>
        </div>
        <div class="cmns-action-buttons">
            <a href="logs.php" class="cmns-btn cmns-btn-secondary">
                <span class="material-symbols-rounded">receipt_long</span> ประวัติสต็อก
            </a>
            <a href="categories.php" class="cmns-btn cmns-btn-secondary">
                <span class="material-symbols-rounded">account_tree</span> หมวดหมู่
            </a>
            <?php if (can('parts.manage')): ?>
            <button onclick="openAddModal()" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">add_circle</span> เพิ่มสินค้า
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Stat cards ── -->
    <div class="inv-stats">
        <div class="inv-stat-card inv-stat-blue">
            <div class="inv-stat-icon"><span class="material-symbols-rounded">inventory_2</span></div>
            <div>
                <div class="inv-stat-val"><?= number_format($stat['item_count']) ?></div>
                <div class="inv-stat-lbl">รายการ</div>
            </div>
        </div>
        <div class="inv-stat-card inv-stat-green">
            <div class="inv-stat-icon"><span class="material-symbols-rounded">deployed_code</span></div>
            <div>
                <div class="inv-stat-val"><?= number_format($stat['on_hand']) ?></div>
                <div class="inv-stat-lbl">ชิ้นในสต็อก</div>
            </div>
        </div>
        <div class="inv-stat-card inv-stat-amber">
            <div class="inv-stat-icon"><span class="material-symbols-rounded">production_quantity_limits</span></div>
            <div>
                <div class="inv-stat-val"><?= number_format($stat_low) ?></div>
                <div class="inv-stat-lbl">ใกล้หมด / หมด</div>
            </div>
        </div>
        <?php if ($stat_value !== null): ?>
        <div class="inv-stat-card inv-stat-red">
            <div class="inv-stat-icon"><span class="material-symbols-rounded">payments</span></div>
            <div>
                <div class="inv-stat-val">฿<?= number_format($stat_value) ?></div>
                <div class="inv-stat-lbl">มูลค่าสต็อก (ราคาขาย)</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="cmns-controls-bar">
        <div class="cmns-tabs">
            <a href="index.php?type=all"     class="cmns-tab <?= $current_type == 'all'     ? 'active-all'     : '' ?>"><span class="material-symbols-rounded">apps</span> ทั้งหมด</a>
            <a href="index.php?type=new"     class="cmns-tab <?= $current_type == 'new'     ? 'active-new'     : '' ?>"><span class="material-symbols-rounded">new_releases</span> ของใหม่</a>
            <a href="index.php?type=used"    class="cmns-tab <?= $current_type == 'used'    ? 'active-used'    : '' ?>"><span class="material-symbols-rounded">build</span> มือสอง</a>
            <a href="index.php?type=machine" class="cmns-tab <?= $current_type == 'machine' ? 'active-machine' : '' ?>"><span class="material-symbols-rounded">memory</span> เครื่อง/ซาก</a>
            <a href="index.php?type=sale"    class="cmns-tab <?= $current_type == 'sale'    ? 'active-sale'    : '' ?>"><span class="material-symbols-rounded">sell</span> ขาย</a>
        </div>
        <div class="cmns-view-toggle">
            <button onclick="setViewMode('grid')" id="btn-view-grid" class="cmns-view-btn" title="มุมมองตาราง">
                <span class="material-symbols-rounded">grid_view</span>
            </button>
            <button onclick="setViewMode('list')" id="btn-view-list" class="cmns-view-btn" title="มุมมองรายการ">
                <span class="material-symbols-rounded">view_list</span>
            </button>
        </div>
    </div>

    <div class="cmns-search-bar">
        <!-- search ทุกหมวด — ส่งไป view.php (all-items mode); ไม่ auto-submit ระหว่างพิมพ์ พิมพ์จบแล้วกดปุ่ม/Enter -->
        <form action="view.php" method="GET" class="cmns-search-form-row">
            <input type="hidden" name="type" value="<?= htmlspecialchars($current_type) ?>">
            <div class="cmns-search-input-wrap" style="margin:0;">
                <span class="material-symbols-rounded search-icon">search</span>
                <input type="text" id="cmns-search" name="q" class="cmns-search-input" placeholder="ค้นหาอะไหล่ทุกหมวด (ชื่อ, SKU, S/N, Part No., Asset Tag)..." autocomplete="off">
            </div>
            <button type="submit" class="inv-icon-btn inv-icon-search" aria-label="ค้นหา" data-tip="ค้นหา">
                <span class="material-symbols-rounded">search</span>
            </button>
        </form>

        <?php if ($current_type == 'all'): ?>
        <div class="cmns-filter-group">
            <button class="cmns-filter-btn" data-filter="new" onclick="toggleFilter(this)">ของใหม่</button>
            <button class="cmns-filter-btn" data-filter="used" onclick="toggleFilter(this)">มือสอง</button>
            <button class="cmns-filter-btn" data-filter="machine" onclick="toggleFilter(this)">เครื่อง/ซาก</button>
            <button class="cmns-filter-btn" data-filter="sale" onclick="toggleFilter(this)">ขาย</button>
            <button class="cmns-filter-btn" data-filter="empty" onclick="toggleFilter(this)">ว่าง</button>
        </div>
        <?php endif; ?>
    </div>

    <div id="folder-container" class="cmns-container view-grid">
        <?php foreach($root_categories as $cat):
            $s = $cat['stats'];
            $total_q = $s['new'] + $s['used'] + $s['machine'] + $s['sale'];
        ?>
            <a href="view.php?id=<?= $cat['id'] ?>&type=<?= $current_type ?>" 
               class="cmns-folder-card"
               data-name="<?= strtolower(htmlspecialchars($cat['name'])) ?>"
               data-has-new="<?= $s['new'] > 0 ? 'true' : 'false' ?>"
               data-has-used="<?= $s['used'] > 0 ? 'true' : 'false' ?>"
               data-has-machine="<?= $s['machine'] > 0 ? 'true' : 'false' ?>"
               data-has-sale="<?= $s['sale'] > 0 ? 'true' : 'false' ?>"
               data-is-empty="<?= ($total_q == 0) ? 'true' : 'false' ?>">

                <span class="material-symbols-rounded cmns-folder-icon"><?= htmlspecialchars($cat['icon'] ?: 'folder') ?></span>
                <div class="cmns-folder-name"><?= htmlspecialchars($cat['name']) ?></div>
                
                <div class="cmns-stats-container">
                    <?php 
                        if(($current_type == 'all' || $current_type == 'new') && $s['new'] > 0) echo '<span class="cmns-stat-badge stat-new">ใหม่ '.$s['new'].'</span>';
                        if(($current_type == 'all' || $current_type == 'used') && $s['used'] > 0) echo '<span class="cmns-stat-badge stat-used">มือสอง '.$s['used'].'</span>';
                        if(($current_type == 'all' || $current_type == 'machine') && $s['machine'] > 0) echo '<span class="cmns-stat-badge stat-machine">เครื่อง '.$s['machine'].'</span>';
                        if(($current_type == 'all' || $current_type == 'sale') && $s['sale'] > 0) echo '<span class="cmns-stat-badge stat-sale">ขาย '.$s['sale'].'</span>';
                        if($total_q == 0) echo '<span class="cmns-stat-badge stat-empty">ว่าง</span>';
                    ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

</div>

<script>
// JS Logic (setViewMode, toggleFilter, applySearch) ยังคงเดิมตามที่มึงมี
function setViewMode(mode) {
    const container = document.getElementById('folder-container');
    const btnGrid   = document.getElementById('btn-view-grid');
    const btnList   = document.getElementById('btn-view-list');
    if(!container) return;
    if (mode === 'list') {
        container.classList.replace('view-grid', 'view-list');
        btnGrid.classList.remove('active'); btnList.classList.add('active');
    } else {
        container.classList.replace('view-list', 'view-grid');
        btnList.classList.remove('active'); btnGrid.classList.add('active');
    }
    localStorage.setItem('inventoryViewMode', mode);
}
document.addEventListener("DOMContentLoaded", () => setViewMode(localStorage.getItem('inventoryViewMode') || 'grid'));

let activeFilter = null;

// ── Folder stock filter (โหมดโฟลเดอร์เท่านั้น) ──
function toggleFilter(btn) {
    const filter = btn.dataset.filter;
    if (activeFilter === filter) {
        activeFilter = null; btn.classList.remove('active');
    } else {
        document.querySelectorAll('.cmns-filter-btn').forEach(b => b.classList.remove('active'));
        activeFilter = filter; btn.classList.add('active');
    }
    document.querySelectorAll('.cmns-folder-card').forEach(card => {
        let matchF = true;
        if (activeFilter === 'new') matchF = card.dataset.hasNew === 'true';
        if (activeFilter === 'used') matchF = card.dataset.hasUsed === 'true';
        if (activeFilter === 'machine') matchF = card.dataset.hasMachine === 'true';
        if (activeFilter === 'sale') matchF = card.dataset.hasSale === 'true';
        if (activeFilter === 'empty') matchF = card.dataset.isEmpty === 'true';
        card.classList.toggle('search-hidden', !matchF);
    });
}


</script>

<?php if (can('parts.manage')) include 'modal_add.php'; ?>
<?php include '../templates/footer_admin.php'; ?>