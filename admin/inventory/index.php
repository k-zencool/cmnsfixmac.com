<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "Inventory Dashboard";
include '../templates/header_admin.php';

// 1. Get Type from URL
$current_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Setup Header UI
$header_map = [
    'all'     => ['title' => 'ALL INVENTORY',            'icon' => 'inventory_2',  'color' => 'var(--primary)'],
    'new'     => ['title' => 'NEW PARTS (STOCK)',        'icon' => 'new_releases', 'color' => '#10b981'],
    'used'    => ['title' => 'USED PARTS (GOOD/TEST)',   'icon' => 'build',        'color' => '#f59e0b'],
    'machine' => ['title' => 'MACHINE / SALVAGE',        'icon' => 'memory',       'color' => '#8b5cf6'],
    'sale'    => ['title' => 'SALE ITEMS (OFFER)',       'icon' => 'sell',         'color' => '#ef4444']
];

$header = $header_map[$current_type] ?? $header_map['all'];
$header_title = $header['title'];
$header_icon  = $header['icon'];
$header_color = $header['color'];

// 2. Fetch Root Categories
$stmt = $pdo->query("SELECT * FROM parts_categories WHERE parent_id IS NULL ORDER BY name ASC");
$root_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Get Total Items Count
if ($current_type == 'all') {
    $total_items = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
} else {
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE type = ?");
    $stmt_total->execute([$current_type]);
    $total_items = $stmt_total->fetchColumn();
}

// 4. Fetch Stats for each Folder (ดึงจากตาราง Lots แทน)
foreach ($root_categories as $key => $cat) {
    $cat_id = $cat['id'];
    $stmt_sub_ids = $pdo->prepare("SELECT id FROM parts_categories WHERE parent_id = ? OR id = ?");
    $stmt_sub_ids->execute([$cat_id, $cat_id]);
    $all_ids = $stmt_sub_ids->fetchAll(PDO::FETCH_COLUMN);
    $ids_in = implode(',', $all_ids);

    // machine/sale = individual units (no lots) → COUNT items; new/used = lot-based → SUM qty_remaining
    $stmt_stats = $pdo->prepare("
        SELECT i.type,
            CASE
                WHEN i.type IN ('machine','sale') THEN COUNT(DISTINCT i.id)
                ELSE COALESCE(SUM(l.qty_remaining), 0)
            END as total_qty
        FROM inventory i
        LEFT JOIN inventory_lots l ON i.id = l.inventory_id
        WHERE i.category_id IN ($ids_in)
        GROUP BY i.type
    ");
    $stmt_stats->execute();
    $stats = $stmt_stats->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $root_categories[$key]['stats'] = [
        'new'     => $stats['new']     ?? 0,
        'used'    => $stats['used']    ?? 0,
        'machine' => $stats['machine'] ?? 0,
        'sale'    => $stats['sale']    ?? 0
    ];
}

?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time(); ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-view.css?v=<?= time(); ?>">
<link rel="stylesheet" href="../templates/assets/css/modal.css?v=<?= time(); ?>">

<div class="cmns-wrapper" style="--active-theme-color: <?= $header_color ?>;">
    
    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color: <?= $header_color ?>;">
                <span class="material-symbols-rounded" style="font-size: 32px;"><?= $header_icon ?></span>
                <?= $header_title ?>
            </h1>
            <p style="color: var(--text-muted); margin-top: 5px; font-size: 14px;">
                Total <b><?= number_format($total_items) ?></b> items in this section
            </p>
        </div>
        <div class="cmns-action-buttons">
            <a href="logs.php" class="cmns-btn cmns-btn-secondary">
                <span class="material-symbols-rounded">receipt_long</span> LOGS
            </a>
            <a href="categories.php" class="cmns-btn cmns-btn-secondary">
                <span class="material-symbols-rounded">account_tree</span> CATEGORIES
            </a>
            <button onclick="openAddModal()" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">add_circle</span> ADD ITEM
            </button>
        </div>
    </div>

    <div class="cmns-controls-bar">
        <div class="cmns-tabs">
            <a href="index.php?type=all"     class="cmns-tab <?= $current_type == 'all'     ? 'active-all'     : '' ?>"><span class="material-symbols-rounded">apps</span> ALL</a>
            <a href="index.php?type=new"     class="cmns-tab <?= $current_type == 'new'     ? 'active-new'     : '' ?>"><span class="material-symbols-rounded">new_releases</span> NEW</a>
            <a href="index.php?type=used"    class="cmns-tab <?= $current_type == 'used'    ? 'active-used'    : '' ?>"><span class="material-symbols-rounded">build</span> USED</a>
            <a href="index.php?type=machine" class="cmns-tab <?= $current_type == 'machine' ? 'active-machine' : '' ?>"><span class="material-symbols-rounded">memory</span> MACHINE</a>
            <a href="index.php?type=sale"    class="cmns-tab <?= $current_type == 'sale'    ? 'active-sale'    : '' ?>"><span class="material-symbols-rounded">sell</span> SALE</a>
        </div>
        <div class="cmns-view-toggle">
            <button onclick="setViewMode('grid')" id="btn-view-grid" class="cmns-view-btn" title="Grid View">
                <span class="material-symbols-rounded">grid_view</span>
            </button>
            <button onclick="setViewMode('list')" id="btn-view-list" class="cmns-view-btn" title="List View">
                <span class="material-symbols-rounded">view_list</span>
            </button>
        </div>
    </div>

    <div class="cmns-search-bar">
        <!-- search ทุกหมวด — ส่งไป view.php (all-items mode) -->
        <form action="view.php" method="GET" class="cmns-search-input-wrap" style="margin:0;">
            <input type="hidden" name="type" value="<?= htmlspecialchars($current_type) ?>">
            <span class="material-symbols-rounded search-icon">search</span>
            <input type="text" id="cmns-search" name="q" class="cmns-search-input" placeholder="ค้นหาอะไหล่ทุกหมวด (ชื่อ, SKU, S/N, Part No., Asset Tag) แล้วกด Enter..." autocomplete="off">
        </form>

        <?php if ($current_type == 'all'): ?>
        <div class="cmns-filter-group">
            <button class="cmns-filter-btn" data-filter="new" onclick="toggleFilter(this)">NEW</button>
            <button class="cmns-filter-btn" data-filter="used" onclick="toggleFilter(this)">USED</button>
            <button class="cmns-filter-btn" data-filter="machine" onclick="toggleFilter(this)">MACHINE</button>
            <button class="cmns-filter-btn" data-filter="sale" onclick="toggleFilter(this)">SALE</button>
            <button class="cmns-filter-btn" data-filter="empty" onclick="toggleFilter(this)">EMPTY</button>
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
                        if(($current_type == 'all' || $current_type == 'new') && $s['new'] > 0) echo '<span class="cmns-stat-badge stat-new">NEW: '.$s['new'].'</span>';
                        if(($current_type == 'all' || $current_type == 'used') && $s['used'] > 0) echo '<span class="cmns-stat-badge stat-used">USED: '.$s['used'].'</span>';
                        if(($current_type == 'all' || $current_type == 'machine') && $s['machine'] > 0) echo '<span class="cmns-stat-badge stat-machine">MACH: '.$s['machine'].'</span>';
                        if(($current_type == 'all' || $current_type == 'sale') && $s['sale'] > 0) echo '<span class="cmns-stat-badge stat-sale">SALE: '.$s['sale'].'</span>';
                        if($total_q == 0) echo '<span class="cmns-stat-badge stat-empty">EMPTY</span>';
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

<?php include 'modal_add.php'; ?>
<?php include '../templates/footer_admin.php'; ?>