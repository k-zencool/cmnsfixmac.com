<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_helpers.php';
require_login();

// Hard-delete privilege: ONLY super_admin with id = 1. ห้ามผูกกับ role string — ล็อกที่ id ตรงๆ
// ซ่อนตอนสวมมุมมองยศอื่น (view-as) เพื่อให้ preview เหมือนยศนั้นจริงๆ
$can_hard_delete = ((int)($_SESSION['admin_id'] ?? 0) === 1) && empty($_SESSION['view_as']);

// =========================================================
// 1. DATA FETCHING & FILTER LOGIC (AJAX กางแถวอยู่ที่ ajax.php)
// =========================================================

$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$current_status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

list($per_page, $page, $offset) = inv_get_pager();

// ── All-items mode: ไม่ส่ง id มา = ดูรวมทุกหมวด (search จาก index.php ใช้โหมดนี้) ──
$category = null;
$sub_categories = [];
$where = [];
$params = [];

if ($category_id > 0) {
    $stmt_cat = $pdo->prepare("SELECT * FROM parts_categories WHERE id = ?");
    $stmt_cat->execute([$category_id]);
    $category = $stmt_cat->fetch(PDO::FETCH_ASSOC);
    if (!$category) { die("Category not found!"); }

    $stmt_ids = $pdo->prepare("SELECT id FROM parts_categories WHERE parent_id = ? OR id = ?");
    $stmt_ids->execute([$category_id, $category_id]);
    $all_cat_ids = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);
    $ids_string = implode(',', array_map('intval', $all_cat_ids));
    $where[] = "i.category_id IN ($ids_string)";

    $stmt_sub = $pdo->prepare("SELECT * FROM parts_categories WHERE parent_id = ? ORDER BY name ASC");
    $stmt_sub->execute([$category_id]);
    $sub_categories = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);
}
if ($current_type !== 'all') { $where[] = "i.type = ?"; $params[] = $current_type; }
if ($current_status !== '') { $where[] = "i.status = ?"; $params[] = $current_status; }

// USED tab — ซ่อน OOS (qty=0) เป็น default
if ($current_type === 'used') {
    $where[] = "COALESCE((SELECT SUM(l.qty_remaining) FROM inventory_lots l WHERE l.inventory_id = i.id), 0) > 0";
}

if ($search !== '') {
    $where[] = "(i.name LIKE ? OR i.asset_tag LIKE ? OR i.serial_number LIKE ? OR i.sku LIKE ? OR i.part_number LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}
$where_sql = $where ? implode(" AND ", $where) : "1";

$order_map = ['newest'=>'i.created_at DESC','oldest'=>'i.created_at ASC','price_low'=>'i.sell_price ASC','price_high'=>'i.sell_price DESC'];
$type_order = "FIELD(i.type,'new','used','machine','sale')";
// ดันของ "หมดแล้ว" ไปท้ายสุดเสมอ ไม่ว่าจะเรียงลำดับแบบไหน:
//   - SALE ที่ขายแล้ว (status='SOLD')
//   - new/used ที่สต็อกหมด (qty รวมจาก lot = 0 หรือ status='OOS')
$depleted_last = "((i.status = 'SOLD') OR (i.type IN ('new','used') AND (COALESCE(SUM(l.qty_remaining), 0) = 0 OR i.status = 'OOS')))";
$order_sql = $current_type === 'all'
    ? "ORDER BY $type_order, $depleted_last, " . ($order_map[$sort] ?? 'i.created_at DESC')
    : "ORDER BY $depleted_last, " . ($order_map[$sort] ?? 'i.created_at DESC');

$stmt_count = $pdo->prepare("SELECT COUNT(DISTINCT i.id) FROM inventory i WHERE $where_sql");
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = ceil($total_items / $per_page);

$sql = "SELECT i.*, COALESCE(SUM(l.qty_remaining), 0) as total_qty,
        MIN(CASE WHEN l.qty_remaining > 0 THEN l.warranty_end END) as nearest_warranty 
        FROM inventory i 
        LEFT JOIN inventory_lots l ON i.id = l.inventory_id 
        WHERE $where_sql 
        GROUP BY i.id 
        $order_sql 
        LIMIT $per_page OFFSET $offset";
$stmt_items = $pdo->prepare($sql);
$stmt_items->execute($params);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// ── Stat cards (ตาม scope filter ปัจจุบัน) ──
$stmt_stat = $pdo->prepare("
    SELECT COUNT(DISTINCT i.id) AS item_count,
           COALESCE(SUM(CASE WHEN i.type IN ('new','used') THEN l.qty_remaining ELSE 0 END), 0) AS on_hand
    FROM inventory i
    LEFT JOIN inventory_lots l ON i.id = l.inventory_id
    WHERE $where_sql");
$stmt_stat->execute($params);
$stat = $stmt_stat->fetch(PDO::FETCH_ASSOC);

$stmt_low = $pdo->prepare("
    SELECT COUNT(*) FROM (
        SELECT i.id, COALESCE(SUM(l.qty_remaining), 0) AS q, MAX(i.min_qty) AS mq
        FROM inventory i
        LEFT JOIN inventory_lots l ON i.id = l.inventory_id
        WHERE $where_sql AND i.type IN ('new','used')
        GROUP BY i.id
        HAVING q <= mq
    ) t");
$stmt_low->execute($params);
$stat_low = (int)$stmt_low->fetchColumn();

$stat_value = null;
if (can('shop.finance')) {
    // มูลค่า on-hand ที่ราคาขาย: new/used = qty คงเหลือ × ราคา, machine/sale = ราคาเครื่อง (ยังไม่ SOLD)
    $stmt_val = $pdo->prepare("
        SELECT COALESCE(SUM(CASE WHEN i.type IN ('new','used') THEN l.qty_remaining * i.sell_price ELSE 0 END), 0)
        FROM inventory i
        LEFT JOIN inventory_lots l ON i.id = l.inventory_id
        WHERE $where_sql");
    $stmt_val->execute($params);
    $stat_value = (float)$stmt_val->fetchColumn();

    $stmt_val2 = $pdo->prepare("
        SELECT COALESCE(SUM(i.sell_price), 0)
        FROM inventory i
        WHERE $where_sql AND i.type IN ('machine','sale') AND i.status != 'SOLD'");
    $stmt_val2->execute($params);
    $stat_value += (float)$stmt_val2->fetchColumn();
}

// categories สำหรับ strip modal
$_all_cats = $pdo->query("SELECT id, name, parent_id FROM parts_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$main_cats = array_filter($_all_cats, fn($c) => empty($c['parent_id']));
$sub_cats  = array_filter($_all_cats, fn($c) => !empty($c['parent_id']));

$pageTitle = $category ? "Category: " . htmlspecialchars($category['name']) : "All Inventory";
include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-logs.css') ?>">
<link rel="stylesheet" href="assets/css/inventory-v2.css?v=<?= asset_ver('/admin/inventory/assets/css/inventory-v2.css') ?>">

<div class="cmns-wrapper">
    
    <?php $back_link = ($category && $category['parent_id']) ? "view.php?id={$category['parent_id']}" : "index.php"; ?>
    <div style="margin-bottom:16px;">
        <a href="<?= $back_link ?>" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> BACK
        </a>
    </div>

    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color: var(--primary);">
                <span class="material-symbols-rounded" style="font-size: 32px;"><?= htmlspecialchars($category ? ($category['icon'] ?: 'folder_open') : 'inventory_2') ?></span>
                <?= $category ? htmlspecialchars($category['name']) : 'ALL ITEMS' ?>
            </h1>
            <p style="color: var(--text-muted); margin-top: 5px; font-size: 14px;">
                <?php if ($category && $category['description']): ?>
                    <?= htmlspecialchars($category['description']) ?>
                <?php elseif (!$category): ?>
                    อะไหล่ทุกหมวดหมู่รวมกัน
                <?php else: ?>
                    ทั้งหมด <b><?= number_format($stat['item_count']) ?></b> รายการในหมวดนี้
                <?php endif; ?>
            </p>
        </div>
        <div class="cmns-action-buttons">
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

    <?php $status_opts = ($current_type == 'new') ? ['STOCK', 'OOS'] : (($current_type == 'used') ? ['GOOD', 'TEST', 'DEAD'] : ($current_type == 'sale' ? ['READY', 'SOLD', 'PENDING'] : ['READY', 'PARTIAL', 'DISCOUNT'])); ?>
    <?php $sort_opts = ['newest' => 'Newest First', 'oldest' => 'Oldest First', 'price_low' => 'Price: Low-High', 'price_high' => 'Price: High-Low']; ?>
    <form action="view.php" method="GET">
            <input type="hidden" name="id" value="<?= $category_id ?>">
            <input type="hidden" name="type" value="<?= $current_type ?>">

            <div class="log-filter-bar" style="margin-bottom:20px;">
                <div class="log-filter-group" style="flex:1; min-width:220px;">
                    <label>ค้นหา</label>
                    <div class="log-search-wrap">
                        <span class="material-symbols-rounded search-icon">search</span>
                        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ชื่อ, SKU, S/N, Part No., Asset Tag" class="view-search-input">
                    </div>
                </div>

                <div class="inv-filter-wrap" style="align-self:flex-end;">
                    <button type="button" class="inv-filter-btn <?= ($current_status !== '' || $sort !== 'newest') ? 'has-filter' : '' ?>"
                            id="statusFilterBtn" onclick="toggleStatusFilterMenu(event)"
                            aria-label="ตัวกรอง / เรียงลำดับ" data-tip="ตัวกรอง / เรียงลำดับ">
                        <span class="material-symbols-rounded">filter_list</span>
                        <?php if ($current_status !== '' || $sort !== 'newest'): ?><span class="inv-filter-badge">&bull;</span><?php endif; ?>
                    </button>
                    <div class="inv-filter-menu" id="statusFilterMenu">
                        <div class="inv-filter-title">สถานะ</div>
                        <label class="inv-filter-item">
                            <input type="radio" name="status" value="" <?= $current_status === '' ? 'checked' : '' ?>>
                            <span>ทั้งหมด</span>
                        </label>
                        <?php foreach ($status_opts as $opt): ?>
                        <label class="inv-filter-item">
                            <input type="radio" name="status" value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>" <?= $current_status === $opt ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($opt) ?></span>
                        </label>
                        <?php endforeach; ?>

                        <div class="inv-filter-title" style="margin-top:10px;">เรียงลำดับ</div>
                        <?php foreach ($sort_opts as $sv => $sl): ?>
                        <label class="inv-filter-item">
                            <input type="radio" name="sort" value="<?= $sv ?>" <?= $sort === $sv ? 'checked' : '' ?>>
                            <span><?= $sl ?></span>
                        </label>
                        <?php endforeach; ?>

                        <div class="inv-filter-actions">
                            <button type="button" onclick="clearStatusFilter()">ล้าง</button>
                            <button type="submit">ค้นหา</button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="inv-icon-btn inv-icon-search" aria-label="ค้นหา" data-tip="ค้นหา" style="align-self:flex-end;">
                    <span class="material-symbols-rounded">search</span>
                </button>
                <?php if ($search !== '' || $current_status !== '' || $sort !== 'newest'): ?>
                <a href="view.php?id=<?= $category_id ?>&type=<?= htmlspecialchars($current_type) ?>"
                   class="inv-icon-btn inv-icon-reset" aria-label="ล้างค่าทั้งหมด" data-tip="ล้างค่าทั้งหมด" style="align-self:flex-end;">
                    <span class="material-symbols-rounded">close</span>
                </a>
                <?php endif; ?>
            </div>
    </form>

    <div class="cmns-view-card">

        <?php if(!empty($sub_categories) && $search === ''): ?>
            <div style="margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px dashed var(--border);">
                <div style="font-size: 11px; font-weight: 800; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">SUB-FOLDERS</div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 15px;">
                    <?php foreach($sub_categories as $sub): ?>
                        <a href="view.php?id=<?= $sub['id'] ?>&type=all" class="sub-folder-item"
                           style="display: flex; align-items: center; gap: 15px; padding: 16px; border: 1px solid var(--border); border-radius: 12px; background: var(--bg-surface-alt); text-decoration: none; transition: all 0.2s ease; overflow: hidden;"
                           onmouseover="this.style.borderColor='var(--primary)'; this.style.transform='translateY(-3px)';"
                           onmouseout="this.style.borderColor='var(--border)'; this.style.transform='none';">
                            <span class="material-symbols-rounded" style="font-size: 32px; color: var(--primary); flex-shrink: 0; width: 32px; display: flex; justify-content: center;">
                                <?= $sub['icon'] ?: 'folder' ?>
                            </span>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 700; color: var(--text-main); font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($sub['name']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">View Inside &rarr;</div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 15px; flex-wrap: wrap; gap: 15px;">
            <div class="cmns-tabs">
                <?php $bt = "view.php?id=$category_id&q=$search&status=$current_status&sort=$sort"; ?>
                <a href="<?= $bt ?>&type=all"     class="cmns-tab <?= $current_type == 'all' ? 'active-all' : '' ?>"><span class="material-symbols-rounded">apps</span> ทั้งหมด</a>
                <a href="<?= $bt ?>&type=new"     class="cmns-tab <?= $current_type == 'new' ? 'active-new' : '' ?>"><span class="material-symbols-rounded">new_releases</span> ของใหม่</a>
                <a href="<?= $bt ?>&type=used"    class="cmns-tab <?= $current_type == 'used' ? 'active-used' : '' ?>"><span class="material-symbols-rounded">build</span> มือสอง</a>
                <a href="<?= $bt ?>&type=machine" class="cmns-tab <?= $current_type == 'machine' ? 'active-machine' : '' ?>"><span class="material-symbols-rounded">memory</span> เครื่อง/ซาก</a>
                <a href="<?= $bt ?>&type=sale"    class="cmns-tab <?= $current_type == 'sale' ? 'active-sale' : '' ?>"><span class="material-symbols-rounded">sell</span> ขาย</a>
            </div>
        </div>

        <div class="cmns-table-responsive">
            <table class="cmns-table">
                <thead<?= $current_type === 'all' ? ' style="display:none;"' : '' ?>>
                    <tr>
                        <th width="40" class="col-num" style="text-align:center;">#</th>
                        <th width="60" class="col-img">IMG</th>
                        <?php if($current_type === 'new'): ?>
                            <th>PART NAME / SKU</th>
                            <th class="col-d1">PART NO.</th>
                            <th class="col-d2">COMPATIBLE</th>
                        <?php elseif($current_type === 'used'): ?>
                            <th>ITEM NAME / SKU</th>
                            <th class="col-d1">SERIAL NO.</th>
                            <th class="col-d2">CONDITION</th>
                        <?php elseif($current_type == 'machine'): ?>
                            <th>MACHINE / ASSET</th>
                            <th class="col-d1">SPECS</th>
                            <th class="col-d2">GRADE / COLOR</th>
                        <?php elseif($current_type == 'sale'): ?>
                            <th>DEVICE / ASSET</th>
                            <th class="col-d1">SPECS</th>
                            <th class="col-d2">GRADE / WARRANTY / BATTERY</th>
                        <?php else: ?>
                            <th>PRODUCT NAME / SKU</th>
                            <th colspan="2" class="col-d1">TYPE / LOCATION</th>
                        <?php endif; ?>
                        <?php if(!in_array($current_type, ['machine','sale'])): ?>
                        <th width="60" style="text-align:center;">QTY</th>
                        <?php endif; ?>
                        <th width="130" style="text-align:center;">STATUS / WARRANTY</th>
                        <th width="100" class="col-price" style="text-align:right;">PRICE</th>
                        <th width="<?= $current_type === 'sale' ? '80' : '130' ?>" style="text-align:center;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $type_meta = [
                        'new'     => ['label'=>'NEW',     'color'=>'#10b981', 'icon'=>'fiber_new', 'cols'=>['PART NAME / SKU','PART NO.','COMPATIBLE']],
                        'used'    => ['label'=>'USED',    'color'=>'#f59e0b', 'icon'=>'build',     'cols'=>['ITEM NAME / SKU','SERIAL NO.','CONDITION']],
                        'machine' => ['label'=>'MACHINE', 'color'=>'#8b5cf6', 'icon'=>'computer',  'cols'=>['MACHINE / ASSET','SPECS','GRADE / COLOR']],
                        'sale'    => ['label'=>'SALE',    'color'=>'#ef4444', 'icon'=>'sell',      'cols'=>['DEVICE / ASSET','SPECS','GRADE / WARRANTY / BATTERY']],
                    ];
                    $prev_type = null;
                    if(empty($items)): ?>
                        <tr><td colspan="9" style="padding:80px 20px; text-align:center; color:var(--text-muted);">
                            <div class="empty-state" style="padding:0;">
                                <span class="material-symbols-rounded" style="font-size:56px; opacity:.15; display:block; margin-bottom:12px;">inventory_2</span>
                                ไม่พบรายการในหมวดนี้
                            </div>
                        </td></tr>
                    <?php else: foreach($items as $idx => $item):
                        $it  = $item['type'];
                        $qty = (int)($item['total_qty'] ?: 0);
                        $st  = strtoupper(trim($item['status']));
                        $st_class = ['STOCK'=>'status-green','GOOD'=>'status-green','READY'=>'status-green','TEST'=>'status-orange','PENDING'=>'status-orange','SOLD'=>'status-red'][$st] ?? 'status-red';
                        $isMachine = ($it === 'machine' || $it === 'sale');
                        $isOos  = !$isMachine && ($qty === 0 || $st === 'OOS');
                        $isDead = ($st === 'DEAD');
                        $isSold = ($st === 'SOLD');
                        $rowClass = $isOos ? 'row-oos' : ($isDead ? 'row-dead' : ($isSold ? 'row-sold' : ''));

                        // Group header สำหรับ ALL tab
                        if ($current_type === 'all' && $it !== $prev_type):
                            $tm = $type_meta[$it] ?? ['label'=>strtoupper($it),'color'=>'#888','icon'=>'inventory_2','cols'=>['NAME','','']];
                            $prev_type = $it;
                    ?>
                        <tr style="background:<?= $tm['color'] ?>12; border-top:2px solid <?= $tm['color'] ?>33; pointer-events:none;">
                            <!-- cell เดียว colspan เต็มแถว — ไม่งั้น column folding บนมือถือจะทำให้แถวเบี้ยว -->
                            <td colspan="9" style="padding:8px 14px;">
                                <div style="display:flex; align-items:center; gap:7px;">
                                    <span class="material-symbols-rounded" style="font-size:16px; color:<?= $tm['color'] ?>;"><?= $tm['icon'] ?></span>
                                    <span style="font-size:11px; font-weight:900; letter-spacing:1.5px; color:<?= $tm['color'] ?>; text-transform:uppercase;"><?= $tm['label'] ?></span>
                                    <span style="font-size:10px; font-weight:700; color:<?= $tm['color'] ?>88; margin-left:6px;"><?= implode(' · ', array_filter($tm['cols'])) ?></span>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr class="inventory-row <?= $rowClass ?>"
                            id="row-<?= $item['id'] ?>"
                            onclick="toggleLotDetails(<?= $item['id'] ?>)">

                            <td class="col-num" style="text-align:center; opacity:.45; font-size:11px;"><?= $offset + $idx + 1 ?></td>

                            <td class="col-img">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="../../uploads/inventory/<?= htmlspecialchars($item['image']) ?>"
                                         style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--border);"
                                         onerror="this.outerHTML='<div class=\'no-img-box\'><span class=\'material-symbols-rounded\'>image_not_supported</span></div>'">
                                <?php else: ?>
                                    <div class="no-img-box"><span class="material-symbols-rounded">image</span></div>
                                <?php endif; ?>
                            </td>

                            <?php if($it === 'new'): ?>
                                <td>
                                    <div style="font-weight:700; color:var(--text-main); font-size:14px; line-height:1.3;"><?= htmlspecialchars($item['name']) ?></div>
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:3px;">
                                        <code style="background:var(--bg-surface-alt); padding:1px 5px; border-radius:4px;"><?= htmlspecialchars($item['sku'] ?: '—') ?></code>
                                    </div>
                                    <?php if($item['part_number']): ?><div class="fold fold-d">P/N: <?= htmlspecialchars($item['part_number']) ?></div><?php endif; ?>
                                    <div class="fold fold-price">฿<?= number_format($item['sell_price']) ?></div>
                                </td>
                                <td class="col-d1" style="font-size:12px; font-family:monospace; color:var(--text-muted);">
                                    <?= htmlspecialchars($item['part_number'] ?: '—') ?>
                                </td>
                                <td class="col-d2">
                                    <?php foreach(array_filter(array_map('trim', explode(',', $item['compatible_models'] ?? ''))) as $model): ?>
                                        <span style="display:inline-block; background:rgba(37,99,235,.08); color:var(--primary); border:1px solid rgba(37,99,235,.2); border-radius:6px; padding:1px 7px; font-size:11px; font-weight:700; margin:1px;"><?= htmlspecialchars($model) ?></span>
                                    <?php endforeach; ?>
                                </td>
                            <?php elseif($it === 'machine'):
                                $grade_color = ['A' => '#10b981', 'B' => '#3b82f6', 'C' => '#f59e0b', 'D' => '#ef4444'][$item['condition_grade'] ?? ''] ?? '#888';
                                $dis_label = [
                                    'intact'             => ['lock','#10b981','Intact'],
                                    'partially_stripped' => ['build','#f59e0b','Partial'],
                                    'stripped'           => ['check_circle','#6b7280','Stripped'],
                                ][$item['disassembly_status'] ?? ''] ?? ['','#888','—'];
                            ?>
                                <td>
                                    <div style="font-weight:700; font-size:14px; line-height:1.3;"><?= htmlspecialchars($item['name']) ?></div>
                                    <div style="font-size:11px; margin-top:3px; display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                                        <?php if($item['asset_tag']): ?>
                                            <code style="background:rgba(139,92,246,.1); color:#8b5cf6; border:1px solid rgba(139,92,246,.3); padding:1px 5px; border-radius:4px;"><?= htmlspecialchars($item['asset_tag']) ?></code>
                                        <?php endif; ?>
                                        <?php if($item['serial_number']): ?>
                                            <span style="color:var(--text-muted); font-family:monospace;"><?= htmlspecialchars($item['serial_number']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:10px; margin-top:4px; display:flex; align-items:center; gap:3px; color:<?= $dis_label[1] ?>; font-weight:700;">
                                        <?php if($dis_label[0]): ?><span class="material-symbols-rounded" style="font-size:12px;"><?= $dis_label[0] ?></span><?php endif; ?>
                                        <?= $dis_label[2] ?>
                                    </div>
                                    <div class="fold fold-d"><?= htmlspecialchars(implode(' · ', array_filter([$item['cpu_spec'], $item['ram_spec'], $item['storage_spec'], $item['condition_grade'] ? 'Grade '.$item['condition_grade'] : null]))) ?></div>
                                    <div class="fold fold-price">฿<?= number_format($item['sell_price']) ?></div>
                                </td>
                                <td class="col-d1" style="font-size:11px; color:var(--text-muted); line-height:2;">
                                    <?php if($item['cpu_spec']): ?>
                                        <div style="display:flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:13px;">memory</span><?= htmlspecialchars($item['cpu_spec']) ?></div>
                                    <?php endif; ?>
                                    <?php if($item['ram_spec']): ?>
                                        <div style="display:flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:13px;">storage</span><?= htmlspecialchars($item['ram_spec']) ?></div>
                                    <?php endif; ?>
                                    <?php if($item['storage_spec']): ?>
                                        <div style="display:flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:13px;">hard_drive</span><?= htmlspecialchars($item['storage_spec']) ?></div>
                                    <?php endif; ?>
                                    <?php if($item['gpu_spec']): ?>
                                        <div style="display:flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:13px;">display_settings</span><?= htmlspecialchars($item['gpu_spec']) ?></div>
                                    <?php endif; ?>
                                    <?php if(!$item['cpu_spec'] && !$item['ram_spec'] && !$item['storage_spec'] && !$item['gpu_spec']): ?><span style="opacity:.4;">—</span><?php endif; ?>
                                </td>
                                <td class="col-d2" style="font-size:12px;">
                                    <?php if($item['condition_grade']): ?>
                                        <span style="font-size:18px; font-weight:900; color:<?= $grade_color ?>;"><?= htmlspecialchars($item['condition_grade']) ?></span>
                                        <span style="font-size:10px; color:var(--text-muted); display:block;">Grade</span>
                                    <?php endif; ?>
                                    <?php if($item['color']): ?>
                                        <div style="font-size:11px; color:var(--text-muted); margin-top:4px; display:flex; align-items:center; gap:3px;">
                                            <span class="material-symbols-rounded" style="font-size:12px;">palette</span><?= htmlspecialchars($item['color']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php elseif($it === 'sale'):
                                $sale_grade_color = ['A' => '#10b981', 'B' => '#3b82f6', 'C' => '#f59e0b'][$item['condition_grade'] ?? ''] ?? '#888';
                                $apple_days   = null;
                                $store_w_days = $item['store_warranty_days'] ?? null;
                                if (!empty($item['apple_warranty_date'])) {
                                    $apple_days = (int)((strtotime($item['apple_warranty_date']) - time()) / 86400);
                                }
                            ?>
                                <td>
                                    <div style="font-weight:700; color:var(--text-main); font-size:14px; line-height:1.3;"><?= htmlspecialchars($item['name']) ?></div>
                                    <div style="font-size:11px; margin-top:3px; display:flex; gap:6px; align-items:center;">
                                        <?php if($item['asset_tag']): ?>
                                            <code style="background:rgba(239,68,68,.1); color:#ef4444; border:1px solid rgba(239,68,68,.3); padding:1px 5px; border-radius:4px;"><?= htmlspecialchars($item['asset_tag']) ?></code>
                                        <?php endif; ?>
                                        <?php if($item['serial_number']): ?>
                                            <span style="color:var(--text-muted); font-family:monospace; font-size:10px;"><?= htmlspecialchars($item['serial_number']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($item['color']): ?>
                                        <div style="font-size:10px; color:var(--text-muted); margin-top:3px; display:flex; align-items:center; gap:3px;">
                                            <span class="material-symbols-rounded" style="font-size:11px;">palette</span><?= htmlspecialchars($item['color']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="fold fold-d"><?= htmlspecialchars(implode(' · ', array_filter([
                                        $item['condition_grade'] ? 'Grade '.$item['condition_grade'] : null,
                                        $item['cpu_spec'],
                                        $item['battery_health'] !== null ? 'แบต '.(int)$item['battery_health'].'%' : null,
                                    ]))) ?></div>
                                    <div class="fold fold-price">฿<?= number_format($item['sell_price']) ?></div>
                                </td>
                                <td class="col-d1" style="font-size:11px; color:var(--text-muted); line-height:2;">
                                    <?php if($item['cpu_spec']): ?><div style="display:flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:13px;">memory</span><?= htmlspecialchars($item['cpu_spec']) ?></div><?php endif; ?>
                                    <?php if($item['ram_spec']): ?><div style="display:flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:13px;">storage</span><?= htmlspecialchars($item['ram_spec']) ?></div><?php endif; ?>
                                    <?php if($item['storage_spec']): ?><div style="display:flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:13px;">hard_drive</span><?= htmlspecialchars($item['storage_spec']) ?></div><?php endif; ?>
                                    <?php if(!$item['cpu_spec'] && !$item['ram_spec'] && !$item['storage_spec']): ?><span style="opacity:.4;">—</span><?php endif; ?>
                                </td>
                                <td class="col-d2" style="font-size:11px; line-height:1.9;">
                                    <?php if($item['condition_grade']): ?>
                                        <div style="display:flex;align-items:center;gap:5px;margin-bottom:2px;">
                                            <span style="font-size:16px;font-weight:900;color:<?= $sale_grade_color ?>;"><?= htmlspecialchars($item['condition_grade']) ?></span>
                                            <span style="font-size:10px;color:var(--text-muted);">Grade</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if($apple_days !== null): ?>
                                        <?php $ac = $apple_days < 0 ? '#ef4444' : ($apple_days < 90 ? '#f59e0b' : '#10b981'); ?>
                                        <div style="display:flex;align-items:center;gap:4px;color:<?= $ac ?>;">
                                            <span class="material-symbols-rounded" style="font-size:12px;">verified</span>
                                            <span style="font-size:10px;font-weight:700;"><?= $apple_days < 0 ? 'หมดประกัน' : "Apple {$apple_days}ว." ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if($store_w_days !== null): ?>
                                        <div style="display:flex;align-items:center;gap:4px;color:#3b82f6;">
                                            <span class="material-symbols-rounded" style="font-size:12px;">store</span>
                                            <span style="font-size:10px;font-weight:700;">ประกันร้าน <?= $store_w_days ?> วัน</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if($item['battery_health'] !== null): ?>
                                        <?php $bh = (int)$item['battery_health']; $bc_color = $bh >= 85 ? '#10b981' : ($bh >= 70 ? '#f59e0b' : '#ef4444'); ?>
                                        <div style="display:flex;align-items:center;gap:4px;color:<?= $bc_color ?>;">
                                            <span class="material-symbols-rounded" style="font-size:12px;">battery_charging_full</span>
                                            <span style="font-size:10px;font-weight:700;"><?= $bh ?>%<?= $item['battery_cycles'] ? " · {$item['battery_cycles']}รอบ" : '' ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php elseif($it === 'used'): ?>
                                <td>
                                    <div style="font-weight:700; color:var(--text-main); font-size:14px; line-height:1.3;"><?= htmlspecialchars($item['name']) ?></div>
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:3px; display:flex; gap:6px; align-items:center;">
                                        <code style="background:rgba(245,158,11,.1); color:#f59e0b; border:1px solid rgba(245,158,11,.3); padding:1px 5px; border-radius:4px;"><?= htmlspecialchars($item['sku'] ?: '—') ?></code>
                                        <?php if($item['part_number']): ?><span style="opacity:.5;"><?= htmlspecialchars($item['part_number']) ?></span><?php endif; ?>
                                    </div>
                                    <div class="fold fold-d"><?= htmlspecialchars(implode(' · ', array_filter([$item['serial_number'], $item['condition_note']]))) ?></div>
                                    <div class="fold fold-price">฿<?= number_format($item['sell_price']) ?></div>
                                </td>
                                <td class="col-d1" style="font-size:12px; font-family:monospace; color:var(--text-muted);">
                                    <?php if($item['serial_number']): ?>
                                        <code style="background:var(--bg-surface-alt); padding:2px 6px; border-radius:5px; font-size:11px;"><?= htmlspecialchars($item['serial_number']) ?></code>
                                    <?php else: ?><span style="opacity:.4;">—</span><?php endif; ?>
                                </td>
                                <td class="col-d2" style="font-size:12px; color:var(--text-muted);">
                                    <?= htmlspecialchars($item['condition_note'] ?: '—') ?>
                                    <?php if($item['location']): ?>
                                        <div style="font-size:10px; margin-top:2px; opacity:.55;"><span class="material-symbols-rounded" style="font-size:11px;vertical-align:-2px;">location_on</span> <?= htmlspecialchars($item['location']) ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <td>
                                    <div style="font-weight:700; font-size:14px;"><?= htmlspecialchars($item['name']) ?></div>
                                    <div style="font-size:11px; color:var(--text-muted);">SKU: <code><?= htmlspecialchars($item['sku'] ?: '-') ?></code></div>
                                    <div class="fold fold-d"><?= htmlspecialchars(implode(' · ', array_filter([strtoupper($it), $item['location']]))) ?></div>
                                    <div class="fold fold-price">฿<?= number_format($item['sell_price']) ?></div>
                                </td>
                                <td colspan="2" class="col-d1" style="font-size:12px; color:var(--text-muted);">
                                    <span style="font-size:10px;font-weight:800;padding:2px 7px;border-radius:5px;background:rgba(245,158,11,.1);color:#f59e0b;border:1px solid rgba(245,158,11,.3);"><?= strtoupper($it) ?></span>
                                    <span style="margin-left:8px;"><?= htmlspecialchars($item['location'] ?: '—') ?></span>
                                </td>
                            <?php endif; ?>

                            <!-- QTY: ซ่อนใน machine tab / แสดง — ใน all tab -->
                            <?php if(!$isMachine || $current_type === 'all'): ?>
                            <td style="text-align:center;">
                                <?php if($isMachine): ?>
                                    <span style="color:var(--text-muted); opacity:.3; font-size:13px;">—</span>
                                <?php else: ?>
                                    <span style="font-size:20px; font-weight:800; color:<?= $isOos ? '#ef4444' : ($qty <= ($item['min_qty'] ?? 1) ? '#f59e0b' : 'var(--text-main)') ?>;"><?= $qty ?></span>
                                    <?php if($qty > 0 && $qty <= ($item['min_qty'] ?? 1)): ?>
                                        <div style="font-size:9px; color:#f59e0b; font-weight:700; margin-top:2px;">LOW</div>
                                    <?php elseif($isOos): ?>
                                        <div style="font-size:9px; color:#ef4444; font-weight:700; margin-top:2px;">OOS</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>

                            <!-- STATUS -->
                            <td style="text-align:center;">
                                <span class="status-indicator <?= $st_class ?>"><?= $st ?: '—' ?></span>
                                <?php if($item['nearest_warranty']):
                                    $wDays = (strtotime($item['nearest_warranty']) - time()) / 86400;
                                    $wColor = $wDays < 30 ? '#ef4444' : ($wDays < 90 ? '#f59e0b' : 'var(--text-muted)');
                                ?>
                                    <div style="font-size:10px; color:<?= $wColor ?>; margin-top:5px; font-weight:600;">
                                        Exp: <?= date('d/m/y', strtotime($item['nearest_warranty'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- PRICE -->
                            <td class="col-price" style="text-align:right; font-weight:700; color:var(--primary); font-size:14px;">
                                ฿<?= number_format($item['sell_price']) ?>
                            </td>

                            <!-- ACTIONS -->
                            <td style="text-align:center;" onclick="event.stopPropagation()">
                                <div style="display:flex; justify-content:center; gap:4px;">
                                    <?php if($it === 'new'): ?>
                                        <?php if (can('parts.consume')): ?>
                                        <button class="inv-btn inv-btn-requisition <?= $isOos ? 'disabled' : '' ?>"
                                                aria-label="เบิกอะไหล่ NEW" data-tip="<?= $isOos ? 'หมดสต็อก เบิกไม่ได้' : 'เบิกอะไหล่ NEW' ?>"
                                                onclick="<?= $isOos ? '' : "openRequisitionModal({$item['id']},'new')" ?>"
                                                <?= $isOos ? 'disabled' : '' ?>>
                                            <span class="material-symbols-rounded">output</span>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (can('shop.finance')): ?>
                                        <button class="inv-btn inv-btn-to-sale <?= $isOos ? 'disabled' : '' ?>"
                                                aria-label="ย้ายไป SALE" data-tip="<?= $isOos ? 'หมดสต็อก ย้ายไม่ได้' : 'ย้ายไปขายในแท็บ SALE' ?>"
                                                onclick="<?= $isOos ? '' : "openToSaleModal({$item['id']},'new')" ?>"
                                                <?= $isOos ? 'disabled' : '' ?>>
                                            <span class="material-symbols-rounded">sell</span>
                                        </button>
                                        <?php endif; ?>
                                    <?php elseif($it === 'used'): ?>
                                        <?php if (can('parts.consume')): ?>
                                        <button class="inv-btn <?= $isOos ? 'disabled' : '' ?>"
                                                aria-label="ใช้อะไหล่ USED" data-tip="<?= $isOos ? 'หมดสต็อก เบิกไม่ได้' : 'เบิกใช้อะไหล่ USED' ?>"
                                                style="background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.35); color:#f59e0b;"
                                                onclick="<?= $isOos ? '' : "openRequisitionModal({$item['id']},'used')" ?>"
                                                <?= $isOos ? 'disabled' : '' ?>>
                                            <span class="material-symbols-rounded">build</span>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (can('shop.finance')): ?>
                                        <button class="inv-btn inv-btn-to-sale <?= $isOos ? 'disabled' : '' ?>"
                                                aria-label="ย้ายไป SALE" data-tip="<?= $isOos ? 'หมดสต็อก ย้ายไม่ได้' : 'ย้ายไปขายในแท็บ SALE' ?>"
                                                onclick="<?= $isOos ? '' : "openToSaleModal({$item['id']},'used')" ?>"
                                                <?= $isOos ? 'disabled' : '' ?>>
                                            <span class="material-symbols-rounded">sell</span>
                                        </button>
                                        <?php endif; ?>
                                    <?php elseif($it === 'machine'): ?>
                                        <?php if (can('parts.manage')): ?>
                                        <button class="inv-btn"
                                                aria-label="แยกอะไหล่ → USED" data-tip="แยกอะไหล่จากเครื่องนี้ → เก็บเป็น USED"
                                                style="background:rgba(139,92,246,.12); border:1px solid rgba(139,92,246,.35); color:#8b5cf6;"
                                                onclick="openStripModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', '<?= htmlspecialchars(addslashes($item['asset_tag'] ?? '')) ?>')">
                                            <span class="material-symbols-rounded">content_cut</span>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (can('shop.finance')): ?>
                                        <button class="inv-btn inv-btn-to-sale"
                                                aria-label="ย้ายไป SALE" data-tip="ย้ายไปขายในแท็บ SALE"
                                                onclick="openToSaleModal(<?= $item['id'] ?>,'machine')">
                                            <span class="material-symbols-rounded">sell</span>
                                        </button>
                                        <?php endif; ?>
                                    <?php elseif($it === 'sale'): ?>
                                        <?php if (can('shop.finance')): ?>
                                        <?php if($st === 'PENDING'): ?>
                                            <button class="inv-btn"
                                                    aria-label="Mark READY" data-tip="เช็คเสร็จแล้ว → พร้อมขาย (READY)"
                                                    style="background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.35); color:#10b981;"
                                                    onclick="updateSaleStatus(<?= $item['id'] ?>,'mark_ready')">
                                                <span class="material-symbols-rounded">check_circle</span>
                                            </button>
                                        <?php elseif($st === 'READY'): ?>
                                            <button class="inv-btn"
                                                    aria-label="ยืนยันขาย SOLD" data-tip="ยืนยันการขาย → เปลี่ยนเป็น SOLD"
                                                    style="background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.35); color:#ef4444;"
                                                    onclick="openMarkSoldModal(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode($item['name'])) ?>, <?= (float)$item['sell_price'] ?>)">
                                                <span class="material-symbols-rounded">payments</span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if($st !== 'SOLD'): ?>
                                            <button class="inv-btn"
                                                    aria-label="คืนของที่เดิม" data-tip="ยกเลิก/คืนของกลับที่เดิม"
                                                    style="background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.35); color:#f59e0b;"
                                                    onclick="confirmRevertSale(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode($item['name'])) ?>)">
                                                <span class="material-symbols-rounded">undo</span>
                                            </button>
                                        <?php endif; ?>
                                        <?php endif; // shop.finance ?>
                                    <?php endif; ?>
                                    <?php if (can('parts.manage')): ?>
                                    <button class="inv-btn inv-btn-edit" aria-label="แก้ไข" data-tip="แก้ไขข้อมูลสินค้า"
                                            onclick="openEditModal(<?= $item['id'] ?>)">
                                        <span class="material-symbols-rounded">edit</span>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($can_hard_delete): ?>
                                        <button class="inv-btn inv-btn-delete" aria-label="ลบทั้งก้อน" data-tip="ลบทั้งก้อน (super admin only, ลบถาวร)"
                                                onclick="confirmHardDelete(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode($item['name']), ENT_QUOTES) ?>)">
                                            <span class="material-symbols-rounded">delete_forever</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <tr id="lot-detail-<?= $item['id'] ?>" class="lot-detail-row" style="display:none;">
                            <td colspan="9">
                                <div id="lot-content-<?= $item['id'] ?>"></div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php $total_pages = max(1, (int)$total_pages); ?>
        <div class="log-pagination">
            <div>
                แสดง <b><?= number_format(min($total_items, $offset + 1)) ?>–<?= number_format(min($total_items, $offset + $per_page)) ?></b>
                จาก <b><?= number_format($total_items) ?></b> รายการ
                &nbsp;·&nbsp; หน้า <?= $page ?> / <?= $total_pages ?>
            </div>
            <div class="page-btns">
                <a href="<?= inv_page_url($page - 1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><span class="material-symbols-rounded" style="font-size:16px;">chevron_left</span></a>
                <?php
                $w_start = max(1, $page - 2);
                $w_end   = min($total_pages, $w_start + 4);
                $w_start = max(1, $w_end - 4);
                for ($i = $w_start; $i <= $w_end; $i++):
                ?>
                <a href="<?= inv_page_url($i) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="<?= inv_page_url($page + 1) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><span class="material-symbols-rounded" style="font-size:16px;">chevron_right</span></a>
                <select onchange="location.href=this.value"
                        style="padding:6px 10px; border-radius:8px; border:1px solid var(--border); background:var(--bg-surface); color:var(--text-main); font-size:13px; outline:none; cursor:pointer; font-family:'Sarabun',sans-serif;">
                    <?php foreach ([20, 50, 100] as $pp):
                        $q = $_GET; $q['per'] = $pp; $q['page'] = 1;
                    ?>
                    <option value="?<?= h(http_build_query($q)) ?>" <?= $per_page == $pp ? 'selected' : '' ?>><?= $pp ?>/หน้า</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <?php if (can('parts.consume')) include 'partials/_modal_requisition.php'; ?>
    <?php if (can('parts.manage'))  include 'partials/_modal_strip.php'; ?>
</div>

<link rel="stylesheet" href="../templates/assets/css/modal.css?v=<?= asset_ver('/admin/templates/assets/css/modal.css') ?>">

<?php if (can('shop.finance')): ?>
    <?php include 'partials/_modal_to_sale.php'; ?>
    <?php include 'partials/_modal_mark_sold.php'; ?>
<?php endif; ?>
<?php if ($can_hard_delete) include 'partials/_modal_hard_delete.php'; ?>
<?php if (can('parts.manage')): ?>
    <?php include 'modal_add.php'; ?>
    <?php include 'partials/_modal_edit.php'; ?>
<?php endif; ?>

<script>
// ── Filter/Sort panel: กดปุ่มเดียวเปิด panel, เลือกแล้วกด "ค้นหา" ถึง submit form จริง (native submit — ไม่ auto-apply) ──
function toggleStatusFilterMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById('statusFilterMenu');
    const btn  = document.getElementById('statusFilterBtn');
    const open = menu.classList.toggle('open');
    btn.classList.toggle('active', open);
}
function clearStatusFilter() {
    const statusAll = document.querySelector('#statusFilterMenu input[name="status"][value=""]');
    const sortDefault = document.querySelector('#statusFilterMenu input[name="sort"][value="newest"]');
    if (statusAll) statusAll.checked = true;
    if (sortDefault) sortDefault.checked = true;
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.inv-filter-wrap')) {
        const menu = document.getElementById('statusFilterMenu');
        const btn  = document.getElementById('statusFilterBtn');
        if (menu) menu.classList.remove('open');
        if (btn)  btn.classList.remove('active');
    }
});
</script>
<script src="assets/js/inventory-table.js?v=<?= asset_ver('/admin/inventory/assets/js/inventory-table.js') ?>"></script>
<?php if (can('parts.consume')): ?><script src="assets/js/inventory-requisition.js?v=<?= asset_ver('/admin/inventory/assets/js/inventory-requisition.js') ?>"></script><?php endif; ?>
<?php if (can('shop.finance')): ?><script src="assets/js/inventory-sale.js?v=<?= asset_ver('/admin/inventory/assets/js/inventory-sale.js') ?>"></script><?php endif; ?>
<?php if (can('parts.manage')): ?>
<script>
// page data for strip-modal JS (sub-category picker)
const _stripSubCats = <?= json_encode(array_values($sub_cats), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/inventory-edit.js?v=<?= asset_ver('/admin/inventory/assets/js/inventory-edit.js') ?>"></script>
<script src="assets/js/inventory-strip.js?v=<?= asset_ver('/admin/inventory/assets/js/inventory-strip.js') ?>"></script>
<?php endif; ?>
<?php if ($can_hard_delete): ?><script src="assets/js/inventory-danger.js?v=<?= asset_ver('/admin/inventory/assets/js/inventory-danger.js') ?>"></script><?php endif; ?>

<?php include '../templates/footer_admin.php'; ?>
