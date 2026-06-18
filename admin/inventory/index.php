<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

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

// 5. PARTS SEARCH (server-side) — แสดงผลเป็นตารางเฉพาะตอนที่ค้นหาเท่านั้น
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_results = [];
if ($search !== '') {
    $sw = [];
    $sp = [];
    if ($current_type !== 'all') { $sw[] = "i.type = ?"; $sp[] = $current_type; }
    $sw[] = "(i.name LIKE ? OR i.sku LIKE ? OR i.serial_number LIKE ? OR i.part_number LIKE ? OR i.asset_tag LIKE ?)";
    $kw = "%$search%";
    array_push($sp, $kw, $kw, $kw, $kw, $kw);
    $sw_sql = implode(" AND ", $sw);

    $sql_search = "SELECT i.*, c.name AS cat_name,
            COALESCE(SUM(l.qty_remaining), 0) AS total_qty,
            MIN(CASE WHEN l.qty_remaining > 0 THEN l.warranty_end END) AS nearest_warranty
        FROM inventory i
        LEFT JOIN inventory_lots l ON i.id = l.inventory_id
        LEFT JOIN parts_categories c ON i.category_id = c.id
        WHERE $sw_sql
        GROUP BY i.id
        ORDER BY FIELD(i.type,'new','used','machine','sale'), i.name ASC
        LIMIT 100";
    $stmt_search = $pdo->prepare($sql_search);
    $stmt_search->execute($sp);
    $search_results = $stmt_search->fetchAll(PDO::FETCH_ASSOC);
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
        <form action="index.php" method="GET" class="cmns-search-input-wrap" style="margin:0;">
            <input type="hidden" name="type" value="<?= htmlspecialchars($current_type) ?>">
            <span class="material-symbols-rounded search-icon">search</span>
            <input type="text" id="cmns-search" name="q" value="<?= htmlspecialchars($search) ?>" class="cmns-search-input" placeholder="ค้นหาอะไหล่ (ชื่อ, SKU, S/N, Part No., Asset Tag)..." autocomplete="off">
            <?php if ($search !== ''): ?>
            <a href="index.php?type=<?= htmlspecialchars($current_type) ?>" class="search-clear-x" title="ล้างการค้นหา" style="position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--text-muted); text-decoration:none; font-size:18px; line-height:1;">✕</a>
            <?php endif; ?>
        </form>

        <?php if ($current_type == 'all' && $search === ''): ?>
        <div class="cmns-filter-group">
            <button class="cmns-filter-btn" data-filter="new" onclick="toggleFilter(this)">NEW</button>
            <button class="cmns-filter-btn" data-filter="used" onclick="toggleFilter(this)">USED</button>
            <button class="cmns-filter-btn" data-filter="machine" onclick="toggleFilter(this)">MACHINE</button>
            <button class="cmns-filter-btn" data-filter="sale" onclick="toggleFilter(this)">SALE</button>
            <button class="cmns-filter-btn" data-filter="empty" onclick="toggleFilter(this)">EMPTY</button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($search !== ''): ?>
    <!-- ============ PARTS SEARCH RESULTS (ตารางแบบ view.php) ============ -->
    <div class="cmns-view-card" style="margin-top:6px;">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px; font-size:13px; color:var(--text-muted);">
            <span class="material-symbols-rounded" style="font-size:18px; color:var(--primary);">search</span>
            ผลการค้นหา "<b style="color:var(--text-main);"><?= htmlspecialchars($search) ?></b>" — พบ <b style="color:var(--text-main);"><?= count($search_results) ?></b> รายการ
            <?php if (count($search_results) >= 100): ?><span style="opacity:.6;">(แสดงสูงสุด 100)</span><?php endif; ?>
        </div>

        <div class="cmns-table-responsive">
            <table class="cmns-table">
                <thead>
                    <tr>
                        <th width="40" style="text-align:center;">#</th>
                        <th width="60">IMG</th>
                        <th>NAME / SKU</th>
                        <th>TYPE</th>
                        <th>CATEGORY</th>
                        <th width="60" style="text-align:center;">QTY</th>
                        <th width="130" style="text-align:center;">STATUS / WARRANTY</th>
                        <th width="100" style="text-align:right;">PRICE</th>
                        <th width="90" style="text-align:center;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $type_meta = [
                        'new'     => ['label'=>'NEW',     'color'=>'#10b981', 'icon'=>'fiber_new'],
                        'used'    => ['label'=>'USED',    'color'=>'#f59e0b', 'icon'=>'build'],
                        'machine' => ['label'=>'MACHINE', 'color'=>'#8b5cf6', 'icon'=>'computer'],
                        'sale'    => ['label'=>'SALE',    'color'=>'#ef4444', 'icon'=>'sell'],
                    ];
                    if (empty($search_results)): ?>
                        <tr><td colspan="9" style="padding:70px 20px; text-align:center; color:var(--text-muted);">
                            <span class="material-symbols-rounded" style="font-size:56px; opacity:.15; display:block; margin-bottom:12px;">search_off</span>
                            ไม่พบอะไหล่ที่ตรงกับ "<?= htmlspecialchars($search) ?>"
                        </td></tr>
                    <?php else: foreach($search_results as $idx => $item):
                        $it  = $item['type'];
                        $qty = (int)($item['total_qty'] ?: 0);
                        $st  = strtoupper(trim($item['status']));
                        $st_class = ['STOCK'=>'status-green','GOOD'=>'status-green','READY'=>'status-green','TEST'=>'status-orange','PENDING'=>'status-orange','SOLD'=>'status-red'][$st] ?? 'status-red';
                        $isMachine = ($it === 'machine' || $it === 'sale');
                        $isOos  = !$isMachine && ($qty === 0 || $st === 'OOS');
                        $isDead = ($st === 'DEAD');
                        $rowClass = $isOos ? 'row-oos' : ($isDead ? 'row-dead' : '');
                        $tm = $type_meta[$it] ?? ['label'=>strtoupper($it),'color'=>'#888','icon'=>'inventory_2'];
                    ?>
                        <tr class="inventory-row <?= $rowClass ?>" id="row-<?= $item['id'] ?>" onclick="toggleLotDetails(<?= $item['id'] ?>)">
                            <td style="text-align:center; opacity:.45; font-size:11px;"><?= $idx + 1 ?></td>
                            <td>
                                <?php if (!empty($item['image'])): ?>
                                    <img src="../../uploads/inventory/<?= htmlspecialchars($item['image']) ?>"
                                         style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--border);"
                                         onerror="this.outerHTML='<div class=\'no-img-box\'><span class=\'material-symbols-rounded\'>image_not_supported</span></div>'">
                                <?php else: ?>
                                    <div class="no-img-box"><span class="material-symbols-rounded">image</span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:700; color:var(--text-main); font-size:14px; line-height:1.3;"><?= htmlspecialchars($item['name']) ?></div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:3px; display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                    <code style="background:var(--bg-surface-alt); padding:1px 5px; border-radius:4px;"><?= htmlspecialchars($item['sku'] ?: '—') ?></code>
                                    <?php if($item['asset_tag']): ?><span style="opacity:.6; font-family:monospace;"><?= htmlspecialchars($item['asset_tag']) ?></span><?php endif; ?>
                                    <?php if($item['serial_number']): ?><span style="opacity:.5; font-family:monospace;"><?= htmlspecialchars($item['serial_number']) ?></span><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:10px; font-weight:800; padding:2px 8px; border-radius:6px; background:<?= $tm['color'] ?>22; color:<?= $tm['color'] ?>; border:1px solid <?= $tm['color'] ?>44; white-space:nowrap;"><?= $tm['label'] ?></span>
                            </td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= htmlspecialchars($item['cat_name'] ?: '—') ?></td>
                            <td style="text-align:center;">
                                <?php if($isMachine): ?>
                                    <span style="color:var(--text-muted); opacity:.3; font-size:13px;">—</span>
                                <?php else: ?>
                                    <span style="font-size:20px; font-weight:800; color:<?= $isOos ? '#ef4444' : ($qty <= ($item['min_qty'] ?? 1) ? '#f59e0b' : 'var(--text-main)') ?>;"><?= $qty ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="status-indicator <?= $st_class ?>"><?= $st ?: '—' ?></span>
                                <?php if($item['nearest_warranty']):
                                    $wDays = (strtotime($item['nearest_warranty']) - time()) / 86400;
                                    $wColor = $wDays < 30 ? '#ef4444' : ($wDays < 90 ? '#f59e0b' : 'var(--text-muted)');
                                ?>
                                    <div style="font-size:10px; color:<?= $wColor ?>; margin-top:5px; font-weight:600;">Exp: <?= date('d/m/y', strtotime($item['nearest_warranty'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; font-weight:700; color:var(--primary); font-size:14px;">฿<?= number_format($item['sell_price']) ?></td>
                            <td style="text-align:center;" onclick="event.stopPropagation()">
                                <a href="view.php?id=<?= (int)$item['category_id'] ?>&type=<?= htmlspecialchars($it) ?>&q=<?= urlencode($item['name']) ?>"
                                   class="inv-btn inv-btn-edit" title="เปิดในหมวดหมู่ (จัดการ/เบิก/แก้ไข)" style="text-decoration:none;">
                                    <span class="material-symbols-rounded">open_in_new</span>
                                </a>
                            </td>
                        </tr>
                        <tr id="lot-detail-<?= $item['id'] ?>" class="lot-detail-row" style="display:none;">
                            <td colspan="9"><div id="lot-content-<?= $item['id'] ?>"></div></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
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
    <?php endif; ?>

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

const searchInput = document.getElementById('cmns-search');
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

// ── Parts search: auto-submit form (debounce 0.5s / Enter) ──
if (searchInput) {
    let typingTimer;
    const form = searchInput.closest('form');
    searchInput.addEventListener('input', () => {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => { if (form) form.submit(); }, 500);
    });
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); clearTimeout(typingTimer); if (form) form.submit(); }
    });
    // ย้ายเคอร์เซอร์ไปท้ายข้อความเดิม
    if (searchInput.value) { const v = searchInput.value; searchInput.focus(); searchInput.value = ''; searchInput.value = v; }
}

// ── กางรายละเอียด lot/อะไหล่ (ใช้ AJAX ของ view.php) ──
function toggleLotDetails(id) {
    const detailRow  = document.getElementById(`lot-detail-${id}`);
    const contentDiv = document.getElementById(`lot-content-${id}`);
    const mainRow    = document.getElementById(`row-${id}`);
    if (!detailRow || !contentDiv || !mainRow) return;

    if (detailRow.style.display === 'table-row') {
        detailRow.style.display = 'none';
        mainRow.classList.remove('active');
        return;
    }
    document.querySelectorAll('.lot-detail-row').forEach(r => r.style.display = 'none');
    document.querySelectorAll('.inventory-row').forEach(r => r.classList.remove('active'));

    detailRow.style.display = 'table-row';
    mainRow.classList.add('active');
    contentDiv.innerHTML = '<div style="padding:40px; text-align:center; color:var(--text-muted);"><span class="material-symbols-rounded" style="animation:spin 1s linear infinite; font-size:24px;">sync</span></div>';

    fetch(`view.php?action=get_lots_inline&item_id=${id}`)
        .then(res => res.text())
        .then(data => { contentDiv.innerHTML = data; })
        .catch(() => { contentDiv.innerHTML = '<div style="padding:20px; text-align:center; color:#ef4444;">โหลดข้อมูลไม่สำเร็จ</div>'; });
}
const _spinStyle = document.createElement('style');
_spinStyle.innerHTML = `@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`;
document.head.appendChild(_spinStyle);
</script>

<?php include 'modal_add.php'; ?>
<?php include '../templates/footer_admin.php'; ?>