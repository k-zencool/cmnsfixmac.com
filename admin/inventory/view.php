<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
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

$per_page = 20; 
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

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
$order_sql = $current_type === 'all'
    ? "ORDER BY $type_order, " . ($order_map[$sort] ?? 'i.created_at DESC')
    : "ORDER BY " . ($order_map[$sort] ?? 'i.created_at DESC');

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

// categories สำหรับ strip modal
$_all_cats = $pdo->query("SELECT id, name, parent_id FROM parts_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$main_cats = array_filter($_all_cats, fn($c) => empty($c['parent_id']));
$sub_cats  = array_filter($_all_cats, fn($c) => !empty($c['parent_id']));

$pageTitle = $category ? "Category: " . htmlspecialchars($category['name']) : "All Inventory";
include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time(); ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-view.css?v=<?= time(); ?>">

<div class="cmns-wrapper">
    
    <div class="cmns-top-nav" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <?php $back_link = ($category && $category['parent_id']) ? "view.php?id={$category['parent_id']}" : "index.php"; ?>
        <a href="<?= $back_link ?>" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> BACK
        </a>
        <button onclick="openAddModal()" class="cmns-btn cmns-btn-primary" style="border-radius: 10px; padding: 8px 16px;">
            <span class="material-symbols-rounded" style="font-size: 20px;">add</span> ADD ITEM
        </button>
    </div>

    <div class="cmns-view-card">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="material-symbols-rounded" style="font-size: 36px; color: var(--primary);"><?= htmlspecialchars($category ? ($category['icon'] ?: 'folder_open') : 'inventory_2') ?></span>
                <div>
                    <h1 style="margin:0; font-size: 22px; font-weight: 700;"><?= $category ? htmlspecialchars($category['name']) : 'ALL ITEMS' ?></h1>
                    <?php if($category && $category['description']): ?>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($category['description']) ?></div>
                    <?php elseif(!$category): ?>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">อะไหล่ทุกหมวดหมู่รวมกัน</div>
                    <?php endif; ?>
                </div>
            </div>

            <form action="view.php" method="GET" class="search-form-pro">
                <input type="hidden" name="id" value="<?= $category_id ?>">
                <input type="hidden" name="type" value="<?= $current_type ?>">
                <input type="hidden" name="status" value="<?= $current_status ?>">
                <input type="hidden" name="sort" value="<?= $sort ?>">
                
                <span class="material-symbols-rounded search-icon">search</span>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search items..." class="view-search-input">
                
                <button type="button" class="search-filter-btn" title="Advanced Filters" onclick="alert('เดี๋ยวทำ Popup ')">
                    <span class="material-symbols-rounded">tune</span>
                </button>
            </form>
        </div>

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
                <a href="<?= $bt ?>&type=all"     class="cmns-tab <?= $current_type == 'all' ? 'active-all' : '' ?>">ALL</a>
                <a href="<?= $bt ?>&type=new"     class="cmns-tab <?= $current_type == 'new' ? 'active-new' : '' ?>">NEW</a>
                <a href="<?= $bt ?>&type=used"    class="cmns-tab <?= $current_type == 'used' ? 'active-used' : '' ?>">USED</a>
                <a href="<?= $bt ?>&type=machine" class="cmns-tab <?= $current_type == 'machine' ? 'active-machine' : '' ?>">MACHINE</a>
                <a href="<?= $bt ?>&type=sale"    class="cmns-tab <?= $current_type == 'sale' ? 'active-sale' : '' ?>">SALE</a>
            </div>

            <div style="display: flex; gap: 10px;">
                <select class="filter-select" onchange="location.href='view.php?id=<?= $category_id ?>&q=<?= $search ?>&type=<?= $current_type ?>&sort=<?= $sort ?>&status='+this.value">
                    <option value="">Status: All</option>
                    <?php 
                        $status_opts = ($current_type == 'new') ? ['STOCK', 'OOS'] : (($current_type == 'used') ? ['GOOD', 'TEST', 'DEAD'] : ($current_type == 'sale' ? ['READY', 'SOLD', 'PENDING'] : ['READY', 'PARTIAL', 'DISCOUNT']));
                        foreach($status_opts as $opt) echo "<option value='$opt' ".($current_status==$opt?'selected':'').">$opt</option>";
                    ?>
                </select>
                <select class="filter-select" onchange="location.href='view.php?id=<?= $category_id ?>&q=<?= $search ?>&type=<?= $current_type ?>&status=<?= $current_status ?>&sort='+this.value">
                    <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="price_low" <?= $sort == 'price_low' ? 'selected' : '' ?>>Price: Low-High</option>
                    <option value="price_high" <?= $sort == 'price_high' ? 'selected' : '' ?>>Price: High-Low</option>
                </select>
            </div>
        </div>

        <div class="cmns-table-responsive">
            <table class="cmns-table">
                <thead<?= $current_type === 'all' ? ' style="display:none;"' : '' ?>>
                    <tr>
                        <th width="40" style="text-align:center;">#</th>
                        <th width="60">IMG</th>
                        <?php if($current_type === 'new'): ?>
                            <th>PART NAME / SKU</th>
                            <th>PART NO.</th>
                            <th>COMPATIBLE</th>
                        <?php elseif($current_type === 'used'): ?>
                            <th>ITEM NAME / SKU</th>
                            <th>SERIAL NO.</th>
                            <th>CONDITION</th>
                        <?php elseif($current_type == 'machine'): ?>
                            <th>MACHINE / ASSET</th>
                            <th>SPECS</th>
                            <th>GRADE / COLOR</th>
                        <?php elseif($current_type == 'sale'): ?>
                            <th>DEVICE / ASSET</th>
                            <th>SPECS</th>
                            <th>GRADE / WARRANTY / BATTERY</th>
                        <?php else: ?>
                            <th>PRODUCT NAME / SKU</th>
                            <th colspan="2">TYPE / LOCATION</th>
                        <?php endif; ?>
                        <?php if(!in_array($current_type, ['machine','sale'])): ?>
                        <th width="60" style="text-align:center;">QTY</th>
                        <?php endif; ?>
                        <th width="130" style="text-align:center;">STATUS / WARRANTY</th>
                        <th width="100" style="text-align:right;">PRICE</th>
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
                        $rowClass = $isOos ? 'row-oos' : ($isDead ? 'row-dead' : '');

                        // Group header สำหรับ ALL tab
                        if ($current_type === 'all' && $it !== $prev_type):
                            $tm = $type_meta[$it] ?? ['label'=>strtoupper($it),'color'=>'#888','icon'=>'inventory_2','cols'=>['NAME','','']];
                            $prev_type = $it;
                    ?>
                        <tr style="background:<?= $tm['color'] ?>12; border-top:2px solid <?= $tm['color'] ?>33; pointer-events:none;">
                            <td colspan="2" style="padding:8px 14px;">
                                <div style="display:flex; align-items:center; gap:7px;">
                                    <span class="material-symbols-rounded" style="font-size:16px; color:<?= $tm['color'] ?>;"><?= $tm['icon'] ?></span>
                                    <span style="font-size:11px; font-weight:900; letter-spacing:1.5px; color:<?= $tm['color'] ?>; text-transform:uppercase;"><?= $tm['label'] ?></span>
                                </div>
                            </td>
                            <?php $th_s = 'font-size:10px; font-weight:800; color:' . $tm['color'] . '88; text-transform:uppercase; letter-spacing:.8px; padding:8px 6px;'; ?>
                            <td style="<?= $th_s ?>"><?= $tm['cols'][0] ?? '' ?></td>
                            <td style="<?= $th_s ?>"><?= $tm['cols'][1] ?? '' ?></td>
                            <td style="<?= $th_s ?>"><?= $tm['cols'][2] ?? '' ?></td>
                            <td style="<?= $th_s ?> text-align:center;"><?= $it !== 'machine' ? 'QTY' : '' ?></td>
                            <td style="<?= $th_s ?> text-align:center;">STATUS</td>
                            <td style="<?= $th_s ?> text-align:right;">PRICE</td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="inventory-row <?= $rowClass ?>"
                            id="row-<?= $item['id'] ?>"
                            onclick="toggleLotDetails(<?= $item['id'] ?>)">

                            <td style="text-align:center; opacity:.45; font-size:11px;"><?= $offset + $idx + 1 ?></td>

                            <td>
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
                                </td>
                                <td style="font-size:12px; font-family:monospace; color:var(--text-muted);">
                                    <?= htmlspecialchars($item['part_number'] ?: '—') ?>
                                </td>
                                <td>
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
                                </td>
                                <td style="font-size:11px; color:var(--text-muted); line-height:2;">
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
                                <td style="font-size:12px;">
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
                                </td>
                                <td style="font-size:11px; color:var(--text-muted); line-height:2;">
                                    <?php if($item['cpu_spec']): ?><div style="display:flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:13px;">memory</span><?= htmlspecialchars($item['cpu_spec']) ?></div><?php endif; ?>
                                    <?php if($item['ram_spec']): ?><div style="display:flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:13px;">storage</span><?= htmlspecialchars($item['ram_spec']) ?></div><?php endif; ?>
                                    <?php if($item['storage_spec']): ?><div style="display:flex;align-items:center;gap:4px;"><span class="material-symbols-rounded" style="font-size:13px;">hard_drive</span><?= htmlspecialchars($item['storage_spec']) ?></div><?php endif; ?>
                                    <?php if(!$item['cpu_spec'] && !$item['ram_spec'] && !$item['storage_spec']): ?><span style="opacity:.4;">—</span><?php endif; ?>
                                </td>
                                <td style="font-size:11px; line-height:1.9;">
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
                                </td>
                                <td style="font-size:12px; font-family:monospace; color:var(--text-muted);">
                                    <?php if($item['serial_number']): ?>
                                        <code style="background:var(--bg-surface-alt); padding:2px 6px; border-radius:5px; font-size:11px;"><?= htmlspecialchars($item['serial_number']) ?></code>
                                    <?php else: ?><span style="opacity:.4;">—</span><?php endif; ?>
                                </td>
                                <td style="font-size:12px; color:var(--text-muted);">
                                    <?= htmlspecialchars($item['condition_note'] ?: '—') ?>
                                    <?php if($item['location']): ?>
                                        <div style="font-size:10px; margin-top:2px; opacity:.55;"><span class="material-symbols-rounded" style="font-size:11px;vertical-align:-2px;">location_on</span> <?= htmlspecialchars($item['location']) ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <td>
                                    <div style="font-weight:700; font-size:14px;"><?= htmlspecialchars($item['name']) ?></div>
                                    <div style="font-size:11px; color:var(--text-muted);">SKU: <code><?= htmlspecialchars($item['sku'] ?: '-') ?></code></div>
                                </td>
                                <td colspan="2" style="font-size:12px; color:var(--text-muted);">
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
                            <td style="text-align:right; font-weight:700; color:var(--primary); font-size:14px;">
                                ฿<?= number_format($item['sell_price']) ?>
                            </td>

                            <!-- ACTIONS -->
                            <td style="text-align:center;" onclick="event.stopPropagation()">
                                <div style="display:flex; justify-content:center; gap:4px;">
                                    <?php if($it === 'new'): ?>
                                        <button class="inv-btn inv-btn-requisition <?= $isOos ? 'disabled' : '' ?>"
                                                title="เบิกอะไหล่ NEW"
                                                onclick="<?= $isOos ? '' : "openRequisitionModal({$item['id']},'new')" ?>"
                                                <?= $isOos ? 'disabled' : '' ?>>
                                            <span class="material-symbols-rounded">output</span>
                                        </button>
                                        <button class="inv-btn inv-btn-to-sale <?= $isOos ? 'disabled' : '' ?>"
                                                title="ย้ายไป SALE"
                                                onclick="<?= $isOos ? '' : "openToSaleModal({$item['id']},'new')" ?>"
                                                <?= $isOos ? 'disabled' : '' ?>>
                                            <span class="material-symbols-rounded">sell</span>
                                        </button>
                                    <?php elseif($it === 'used'): ?>
                                        <button class="inv-btn <?= $isOos ? 'disabled' : '' ?>"
                                                title="ใช้อะไหล่ USED"
                                                style="background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.35); color:#f59e0b;"
                                                onclick="<?= $isOos ? '' : "openRequisitionModal({$item['id']},'used')" ?>"
                                                <?= $isOos ? 'disabled' : '' ?>>
                                            <span class="material-symbols-rounded">build</span>
                                        </button>
                                        <button class="inv-btn inv-btn-to-sale <?= $isOos ? 'disabled' : '' ?>"
                                                title="ย้ายไป SALE"
                                                onclick="<?= $isOos ? '' : "openToSaleModal({$item['id']},'used')" ?>"
                                                <?= $isOos ? 'disabled' : '' ?>>
                                            <span class="material-symbols-rounded">sell</span>
                                        </button>
                                    <?php elseif($it === 'machine'): ?>
                                        <button class="inv-btn"
                                                title="แยกอะไหล่ → USED"
                                                style="background:rgba(139,92,246,.12); border:1px solid rgba(139,92,246,.35); color:#8b5cf6;"
                                                onclick="openStripModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', '<?= htmlspecialchars(addslashes($item['asset_tag'] ?? '')) ?>')">
                                            <span class="material-symbols-rounded">content_cut</span>
                                        </button>
                                        <button class="inv-btn inv-btn-to-sale"
                                                title="ย้ายไป SALE"
                                                onclick="openToSaleModal(<?= $item['id'] ?>,'machine')">
                                            <span class="material-symbols-rounded">sell</span>
                                        </button>
                                    <?php elseif($it === 'sale'): ?>
                                        <?php if($st === 'PENDING'): ?>
                                            <button class="inv-btn"
                                                    title="Mark READY"
                                                    style="background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.35); color:#10b981;"
                                                    onclick="updateSaleStatus(<?= $item['id'] ?>,'mark_ready')">
                                                <span class="material-symbols-rounded">check_circle</span>
                                            </button>
                                        <?php elseif($st === 'READY'): ?>
                                            <button class="inv-btn"
                                                    title="ยืนยันขาย SOLD"
                                                    style="background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.35); color:#ef4444;"
                                                    onclick="openMarkSoldModal(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode($item['name'])) ?>, <?= (float)$item['sell_price'] ?>)">
                                                <span class="material-symbols-rounded">payments</span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if($st !== 'SOLD'): ?>
                                            <button class="inv-btn"
                                                    title="คืนของที่เดิม"
                                                    style="background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.35); color:#f59e0b;"
                                                    onclick="confirmRevertSale(<?= $item['id'] ?>, <?= htmlspecialchars(json_encode($item['name'])) ?>)">
                                                <span class="material-symbols-rounded">undo</span>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button class="inv-btn inv-btn-edit" title="แก้ไข"
                                            onclick="openEditModal(<?= $item['id'] ?>)">
                                        <span class="material-symbols-rounded">edit</span>
                                    </button>
                                    <?php if ($can_hard_delete): ?>
                                        <button class="inv-btn inv-btn-delete" title="ลบทั้งก้อน (super admin)"
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
    </div>


    <?php include 'partials/_modal_requisition.php'; ?>

    <?php include 'partials/_modal_strip.php'; ?>

    <div class="cmns-footer-pagination">
        <div class="pagination-info">Showing <b><?= min($total_items, $offset + 1) ?>-<?= min($total_items, $offset + $per_page) ?></b> of <b><?= number_format($total_items) ?></b> items</div>
        <div class="pagination-controls">
            <a href="?id=<?= $category_id ?>&q=<?= $search ?>&type=<?= $current_type ?>&status=<?= $current_status ?>&sort=<?= $sort ?>&page=<?= max(1, $page-1) ?>" class="page-nav-btn <?= $page <= 1 ? 'disabled' : '' ?>"><span class="material-symbols-rounded">chevron_left</span></a>
            <a href="?id=<?= $category_id ?>&q=<?= $search ?>&type=<?= $current_type ?>&status=<?= $current_status ?>&sort=<?= $sort ?>&page=<?= min($total_pages, $page+1) ?>" class="page-nav-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><span class="material-symbols-rounded">chevron_right</span></a>
        </div>
    </div>
</div>

<style>
/* ── Warranty / restock button ── */
.cmns-btn-warranty {
    background: rgba(16,185,129,.1);
    color: #059669;
    border: 1px solid rgba(16,185,129,.35) !important;
}
.cmns-btn-warranty:hover {
    background: #10b981;
    color: #fff;
    border-color: #10b981 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16,185,129,.35);
}
[data-theme="dark"] .cmns-btn-warranty { background: rgba(16,185,129,.1); color: #34d399; }

/* ── Lot option cards ── */
.lot-option {
    display:flex; align-items:center; gap:10px;
    border:1.5px solid var(--border); border-radius:10px;
    padding:10px 14px; cursor:pointer; transition:border-color .15s, background .15s;
}
.lot-option:has(input:checked) { border-color:#10b981; background:rgba(16,185,129,.06); }
.lot-option:hover:not(.lot-expired) { border-color:#10b981; }
.lot-option input[type="radio"] { accent-color:#10b981; width:16px; height:16px; flex-shrink:0; cursor:pointer; }
.lot-opt-body { display:flex; align-items:center; justify-content:space-between; gap:12px; flex:1; min-width:0; }
.lot-expired { opacity:.45; cursor:not-allowed; }
.lot-expired input { cursor:not-allowed; }

/* ── NEW parts action buttons ── */
.inv-btn {
    width:32px; height:32px; border-radius:7px; border:1px solid var(--border);
    background:var(--bg-surface-alt); color:var(--text-muted);
    display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all .18s; padding:0;
}
.inv-btn .material-symbols-rounded { font-size:16px; }
.inv-btn:hover { transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.1); }
.inv-btn-edit:hover { color:var(--primary); background:rgba(37,99,235,.07); border-color:var(--primary); }
.inv-btn-requisition:hover { color:#10b981; background:rgba(16,185,129,.07); border-color:#10b981; }
.inv-btn-requisition.disabled { opacity:.35; cursor:not-allowed; }
.inv-btn-requisition.disabled:hover { transform:none; box-shadow:none; color:var(--text-muted); background:var(--bg-surface-alt); border-color:var(--border); }
.inv-btn-to-sale:hover { color:#ef4444; background:rgba(239,68,68,.08); border-color:#ef4444; }
.inv-btn-to-sale.disabled { opacity:.35; cursor:not-allowed; }
.inv-btn-to-sale.disabled:hover { transform:none; box-shadow:none; color:var(--text-muted); background:var(--bg-surface-alt); border-color:var(--border); }
.inv-btn-delete { color:#ef4444; background:rgba(239,68,68,.08); border-color:rgba(239,68,68,.3); }
.inv-btn-delete:hover { color:#fff; background:#ef4444; border-color:#ef4444; }
#hd-btn:disabled { opacity:.45; cursor:not-allowed; }
#hd-input:focus { outline:none; border-color:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.12); }

/* ── OOS row ── */
.row-oos td { opacity:.55; }
.row-oos:hover td { opacity:.75; }
.row-dead td { opacity:.5; background: rgba(239,68,68,.04); }
.row-dead:hover td { opacity:.7; }
</style>
<link rel="stylesheet" href="../templates/assets/css/modal.css?v=<?= time(); ?>">

<?php include 'partials/_modal_to_sale.php'; ?>
<?php include 'partials/_modal_mark_sold.php'; ?>
<?php if ($can_hard_delete) include 'partials/_modal_hard_delete.php'; ?>
<?php include 'modal_add.php'; ?>
<?php include 'partials/_modal_edit.php'; ?>

<script>
// page data for strip-modal JS (sub-category picker)
const _stripSubCats = <?= json_encode(array_values($sub_cats), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/inventory-table.js?v=<?= time(); ?>"></script>
<script src="assets/js/inventory-requisition.js?v=<?= time(); ?>"></script>
<script src="assets/js/inventory-sale.js?v=<?= time(); ?>"></script>
<script src="assets/js/inventory-edit.js?v=<?= time(); ?>"></script>
<script src="assets/js/inventory-strip.js?v=<?= time(); ?>"></script>
<?php if ($can_hard_delete): ?><script src="assets/js/inventory-danger.js?v=<?= time(); ?>"></script><?php endif; ?>

<?php include '../templates/footer_admin.php'; ?>
