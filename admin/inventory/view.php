<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Hard-delete privilege: ONLY super_admin with id = 1. ห้ามผูกกับ role string — ล็อกที่ id ตรงๆ
// ซ่อนตอนสวมมุมมองยศอื่น (view-as) เพื่อให้ preview เหมือนยศนั้นจริงๆ
$can_hard_delete = ((int)($_SESSION['admin_id'] ?? 0) === 1) && empty($_SESSION['view_as']);

// =========================================================
// 🛑 1. SELF-AJAX LOGIC (กางรายละเอียดล็อตแบบ Seamless)
// =========================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_lots_inline') {
    $item_id = (int)$_GET['item_id'];

    // USED type — แสดง item details แทน lot table
    $item_row = $pdo->prepare("SELECT i.*, inv2.name as src_name, inv2.asset_tag as src_tag FROM inventory i LEFT JOIN inventory AS inv2 ON i.source_machine_id = inv2.id WHERE i.id = ?");
    $item_row->execute([$item_id]);
    $item_row = $item_row->fetch(PDO::FETCH_ASSOC);

    if ($item_row && $item_row['type'] === 'used') {
        $st_color = $item_row['status'] === 'GOOD' ? '#10b981' : '#f59e0b';
        $source   = $item_row['src_name'] ? "[{$item_row['src_tag']}] {$item_row['src_name']}" : null;
        ?>
        <div style="padding:16px 20px 16px 80px;">
            <div style="font-size:11px;font-weight:800;color:var(--text-muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:1px;display:flex;align-items:center;gap:6px;">
                <span class="material-symbols-rounded" style="font-size:16px;color:#f59e0b;">build</span> USED PART DETAILS
            </div>
            <div style="display:flex;gap:28px;flex-wrap:wrap;">
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">SERIAL NO.</div>
                    <code style="font-size:13px;background:var(--bg-surface-alt);padding:2px 8px;border-radius:6px;"><?= htmlspecialchars($item_row['serial_number'] ?: '—') ?></code>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">STATUS</div>
                    <span style="font-weight:800;color:<?= $st_color ?>;font-size:13px;"><?= htmlspecialchars($item_row['status']) ?></span>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">CONDITION NOTE</div>
                    <span style="font-size:13px;color:var(--text-main);"><?= htmlspecialchars($item_row['condition_note'] ?: '—') ?></span>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">LOCATION</div>
                    <span style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($item_row['location'] ?: '—') ?></span>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">PART NO.</div>
                    <span style="font-size:13px;color:var(--text-muted);font-family:monospace;"><?= htmlspecialchars($item_row['part_number'] ?: '—') ?></span>
                </div>
                <?php if($source): ?>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">SOURCE MACHINE</div>
                    <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($source) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        exit;
    }

    // MACHINE type — แสดง specs + ตำหนิ
    if ($item_row && $item_row['type'] === 'machine') {
        $grade_color = ['A' => '#10b981', 'B' => '#3b82f6', 'C' => '#f59e0b', 'D' => '#ef4444'][$item_row['condition_grade'] ?? ''] ?? '#888';
        $dis_map = [
            'intact'             => ['lock',         '#10b981', 'Intact — ยังไม่แกะ'],
            'partially_stripped' => ['build',         '#f59e0b', 'Partially Stripped — แกะบางส่วน'],
            'stripped'           => ['check_circle',  '#6b7280', 'Fully Stripped — แกะหมดแล้ว'],
        ];
        $dis = $dis_map[$item_row['disassembly_status'] ?? ''] ?? ['info', '#888', '—'];

        // ดึงอะไหล่ที่แกะออกจากเครื่องนี้
        $stripped_parts = $pdo->prepare("SELECT name, sku, status, serial_number FROM inventory WHERE source_machine_id = ? AND type = 'used' ORDER BY created_at DESC");
        $stripped_parts->execute([$item_id]);
        $parts = $stripped_parts->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div style="padding:14px 20px 14px 80px; border-top:1px solid var(--border);">
            <?php if($item_row['condition_note']): ?>
            <div style="<?= $parts ? 'margin-bottom:12px;' : '' ?>">
                <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">ตำหนิ / หมายเหตุ</div>
                <div style="font-size:13px;color:var(--text-main);line-height:1.6;padding:10px 14px;background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.15);border-radius:8px;"><?= htmlspecialchars($item_row['condition_note']) ?></div>
            </div>
            <?php endif; ?>

            <?php if($parts): ?>
            <div>
                <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">อะไหล่ที่แยกออกมาแล้ว (<?= count($parts) ?>)</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    <?php foreach($parts as $p):
                        $pc = ['GOOD'=>'#10b981','TEST'=>'#f59e0b'][$p['status']] ?? '#ef4444';
                    ?>
                    <span style="font-size:11px;padding:3px 10px;border-radius:20px;background:<?= $pc ?>14;border:1px solid <?= $pc ?>33;color:<?= $pc ?>;font-weight:700;">
                        <?= htmlspecialchars($p['name']) ?> · <?= $p['status'] ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if(!$item_row['condition_note'] && !$parts): ?>
            <div style="font-size:13px;color:var(--text-muted);font-style:italic;">ไม่มีหมายเหตุและยังไม่ได้แยกอะไหล่</div>
            <?php endif; ?>
        </div>
        <?php
        exit;
    }

    // SALE type — แสดง warranty + battery + condition note
    if ($item_row && $item_row['type'] === 'sale') {
        $apple_days   = null;
        $store_w_days = $item_row['store_warranty_days'] ?? null;
        if (!empty($item_row['apple_warranty_date'])) $apple_days = (int)((strtotime($item_row['apple_warranty_date']) - time()) / 86400);
        $grade_color = ['A'=>'#10b981','B'=>'#3b82f6','C'=>'#f59e0b'][$item_row['condition_grade'] ?? ''] ?? '#888';
        ?>
        <div style="padding:14px 20px 14px 80px; border-top:1px solid var(--border);">
            <div style="display:flex;gap:28px;flex-wrap:wrap;margin-bottom:<?= $item_row['condition_note'] ? '14px' : '0' ?>;">
                <?php if($item_row['condition_grade']): ?>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">Grade</div>
                    <span style="font-size:22px;font-weight:900;color:<?= $grade_color ?>;"><?= htmlspecialchars($item_row['condition_grade']) ?></span>
                </div>
                <?php endif; ?>

                <?php if($apple_days !== null): $ac = $apple_days < 0 ? '#ef4444' : ($apple_days < 90 ? '#f59e0b' : '#10b981'); ?>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">ประกัน Apple ศูนย์</div>
                    <div style="font-size:13px;font-weight:700;color:<?= $ac ?>;display:flex;align-items:center;gap:5px;">
                        <span class="material-symbols-rounded" style="font-size:16px;">verified</span>
                        <?php if($apple_days < 0): ?>หมดแล้ว (<?= date('d/m/Y', strtotime($item_row['apple_warranty_date'])) ?>)
                        <?php else: ?>เหลือ <?= $apple_days ?> วัน (หมด <?= date('d/m/Y', strtotime($item_row['apple_warranty_date'])) ?>)<?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($store_w_days !== null): ?>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">ประกันร้าน</div>
                    <div style="font-size:13px;font-weight:700;color:#3b82f6;display:flex;align-items:center;gap:5px;">
                        <span class="material-symbols-rounded" style="font-size:16px;">store</span>
                        <?= $store_w_days ?> วัน (นับจากวันที่ขาย)
                    </div>
                </div>
                <?php endif; ?>

                <?php if($item_row['battery_health'] !== null): $bh=(int)$item_row['battery_health']; $bc=$bh>=85?'#10b981':($bh>=70?'#f59e0b':'#ef4444'); ?>
                <div>
                    <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">สุขภาพแบต</div>
                    <div style="font-size:18px;font-weight:900;color:<?= $bc ?>;display:flex;align-items:center;gap:6px;">
                        <span class="material-symbols-rounded" style="font-size:18px;">battery_charging_full</span>
                        <?= $bh ?>%
                        <?php if($item_row['battery_cycles']): ?>
                        <span style="font-size:11px;color:var(--text-muted);font-weight:500;"><?= $item_row['battery_cycles'] ?> รอบชาร์จ</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if($item_row['condition_note']): ?>
            <div>
                <div style="font-size:10px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">ตำหนิ / สภาพ</div>
                <div style="font-size:13px;color:var(--text-main);line-height:1.6;padding:10px 14px;background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.12);border-radius:8px;"><?= htmlspecialchars($item_row['condition_note']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        exit;
    }

    // NEW — แสดง lot table เดิม
    $stmt_lots = $pdo->prepare("SELECT * FROM inventory_lots WHERE inventory_id = ? AND qty_remaining > 0 ORDER BY warranty_end ASC");
    $stmt_lots->execute([$item_id]);
    $lots = $stmt_lots->fetchAll(PDO::FETCH_ASSOC);

    if (!$lots) {
        echo '<div style="padding:20px 20px 20px 80px; color:var(--text-muted); font-size:13px; font-style:italic;">🚫 ไม่มีข้อมูลสต็อกในระบบล็อต</div>';
    } else {
        ?>
        <div class="lot-seamless-wrapper">
            <div style="font-size: 11px; font-weight: 800; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-rounded" style="font-size: 16px; color: var(--primary);">account_tree</span> 
                WARRANTY TRACKING PER LOT
            </div>

            <table class="seamless-inner-table">
                <thead>
                    <tr>
                        <th align="left">LOT NUMBER</th>
                        <th align="center">QTY (LEFT/TOTAL)</th>
                        <th align="center">COST (ทุน)</th>
                        <th align="center">WARRANTY EXP.</th>
                        <th align="left">SUPPLIER</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lots as $l):
                        $wEnd   = $l['warranty_end'] ?? null;
                        $urgent = $wEnd && (strtotime($wEnd) - time() < 2592000);
                    ?>
                    <tr>
                        <td><code><?= htmlspecialchars($l['lot_number'] ?? '—') ?></code></td>
                        <td align="center" style="color: var(--text-main);">
                            <b style="font-size: 14px;"><?= $l['qty_remaining'] ?></b>
                            <span style="color: var(--text-muted);">/ <?= $l['qty_received'] ?></span>
                        </td>
                        <td align="center" style="color: var(--text-muted);">฿<?= number_format($l['cost_price'] ?? 0) ?></td>
                        <td align="center" style="color: <?= $urgent ? '#ef4444' : 'var(--text-main)' ?>; font-weight: <?= $urgent ? '800' : '500' ?>;">
                            <?= $wEnd ? date('d/m/Y', strtotime($wEnd)) : '—' ?>
                        </td>
                        <td style="color: var(--text-muted);"><?= htmlspecialchars($l['supplier_name'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    exit;
}

// =========================================================
// 2. MAIN DATA FETCHING & FILTER LOGIC
// =========================================================
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$current_status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$per_page = 20; 
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

$stmt_cat = $pdo->prepare("SELECT * FROM parts_categories WHERE id = ?");
$stmt_cat->execute([$category_id]);
$category = $stmt_cat->fetch(PDO::FETCH_ASSOC);
if (!$category) { die("Category not found!"); }

$stmt_ids = $pdo->prepare("SELECT id FROM parts_categories WHERE parent_id = ? OR id = ?");
$stmt_ids->execute([$category_id, $category_id]);
$all_cat_ids = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);
$ids_string = implode(',', $all_cat_ids);

$stmt_sub = $pdo->prepare("SELECT * FROM parts_categories WHERE parent_id = ? ORDER BY name ASC");
$stmt_sub->execute([$category_id]);
$sub_categories = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

$where = ["i.category_id IN ($ids_string)"];
$params = [];
if ($current_type !== 'all') { $where[] = "i.type = ?"; $params[] = $current_type; }
if ($current_status !== '') { $where[] = "i.status = ?"; $params[] = $current_status; }

// USED tab — ซ่อน OOS (qty=0) เป็น default
if ($current_type === 'used') {
    $where[] = "COALESCE((SELECT SUM(l.qty_remaining) FROM inventory_lots l WHERE l.inventory_id = i.id), 0) > 0";
}

if ($search !== '') {
    $where[] = "(i.name LIKE ? OR i.asset_tag LIKE ? OR i.serial_number LIKE ? OR i.sku LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
$where_sql = implode(" AND ", $where);

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

$pageTitle = "Category: " . htmlspecialchars($category['name']);
include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time(); ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-view.css?v=<?= time(); ?>">

<div class="cmns-wrapper">
    
    <div class="cmns-top-nav" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <?php $back_link = $category['parent_id'] ? "view.php?id={$category['parent_id']}" : "index.php"; ?>
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
                <span class="material-symbols-rounded" style="font-size: 36px; color: var(--primary);"><?= htmlspecialchars($category['icon'] ?: 'folder_open') ?></span>
                <div>
                    <h1 style="margin:0; font-size: 22px; font-weight: 700;"><?= htmlspecialchars($category['name']) ?></h1>
                    <?php if($category['description']): ?>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($category['description']) ?></div>
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

    <!-- ── เบิกอะไหล่ Modal ── -->
    <div id="modal-requisition" class="cmns-modal">
        <div class="modal-content" style="max-width:520px; padding:30px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid var(--border);">
                <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-size:18px; color:var(--text-main);">
                    <span class="material-symbols-rounded" style="color:#10b981; font-size:26px;">output</span>
                    เบิกอะไหล่ใหม่
                </h3>
                <button class="modal-close-btn" onclick="closeRequisitionModal()"><span class="material-symbols-rounded">close</span></button>
            </div>

            <!-- Item info -->
            <div id="req-item-info" style="background:var(--bg-surface-alt); border:1px solid var(--border); border-radius:10px; padding:14px 16px; margin-bottom:16px;">
                <div id="req-item-name" style="font-weight:700; font-size:14px; color:var(--text-main);"></div>
                <div style="display:flex; gap:16px; margin-top:6px;">
                    <span style="font-size:12px; color:var(--text-muted);">SKU: <code id="req-item-sku"></code></span>
                    <span style="font-size:12px; color:var(--text-muted);">คงเหลือรวม: <b id="req-item-qty" style="color:var(--text-main);"></b> ชิ้น</span>
                </div>
            </div>

            <!-- Lot Selector -->
            <div style="margin-bottom:16px;">
                <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:8px;">เลือก Lot</label>
                <div id="req-lots-wrap" style="display:flex; flex-direction:column; gap:6px;">
                    <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:13px;">
                        <span class="material-symbols-rounded" style="animation:spin 1s linear infinite; font-size:20px; display:block; margin-bottom:6px;">sync</span>
                        กำลังโหลด lots...
                    </div>
                </div>
                <input type="hidden" id="req-lot-id" value="">
            </div>

            <div style="display:grid; gap:16px;">

                <!-- Qty -->
                <div>
                    <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:6px;">จำนวนที่เบิก</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <button type="button" onclick="adjustQty(-1)" style="width:36px; height:36px; border-radius:8px; border:1px solid var(--border); background:var(--bg-surface-alt); font-size:20px; cursor:pointer; color:var(--text-main); display:flex; align-items:center; justify-content:center;">−</button>
                        <input type="number" id="req-qty" value="1" min="1" max="99"
                               style="width:72px; text-align:center; padding:8px; border:1.5px solid var(--border); border-radius:8px; background:var(--bg-surface-alt); color:var(--text-main); font-size:16px; font-weight:800; outline:none;">
                        <button type="button" onclick="adjustQty(1)" style="width:36px; height:36px; border-radius:8px; border:1px solid var(--border); background:var(--bg-surface-alt); font-size:20px; cursor:pointer; color:var(--text-main); display:flex; align-items:center; justify-content:center;">+</button>
                        <span id="req-qty-max-label" style="font-size:12px; color:var(--text-muted);"></span>
                    </div>
                </div>

                <!-- Link to job -->
                <div>
                    <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:6px;">ผูกกับงานซ่อม (ไม่บังคับ)</label>
                    <div style="position:relative;">
                        <span class="material-symbols-rounded" style="position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:18px; color:var(--text-muted); pointer-events:none;">build_circle</span>
                        <input type="text" id="req-job-search" placeholder="พิมพ์ Job No. / ชื่อลูกค้า..."
                               autocomplete="off"
                               style="width:100%; padding:10px 12px 10px 38px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-surface-alt); color:var(--text-main); font-size:13px; font-family:inherit; outline:none;"
                               oninput="searchJobs(this.value)">
                        <div id="req-job-results" style="display:none; position:absolute; top:calc(100%+4px); left:0; right:0; background:var(--bg-surface); border:1.5px solid var(--border); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); z-index:200; max-height:220px; overflow-y:auto;"></div>
                    </div>
                    <div id="req-job-selected" style="display:none; margin-top:8px; background:rgba(16,185,129,.08); border:1px solid rgba(16,185,129,.25); border-radius:8px; padding:8px 12px; font-size:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="font-weight:700; color:#059669;" id="req-job-label"></span>
                            </div>
                            <button type="button" onclick="clearJobSelection()" style="background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:16px; padding:0 4px;">✕</button>
                        </div>
                    </div>
                    <input type="hidden" id="req-tracking-id" value="">
                    <input type="hidden" id="req-ticket-number" value="">
                </div>

                <!-- Remarks -->
                <div>
                    <label style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; display:block; margin-bottom:6px;">หมายเหตุ</label>
                    <input type="text" id="req-remarks" placeholder="เช่น เปลี่ยนหน้าจอแตก..."
                           style="width:100%; padding:10px 12px; border:1.5px solid var(--border); border-radius:10px; background:var(--bg-surface-alt); color:var(--text-main); font-size:13px; font-family:inherit; outline:none;">
                </div>
            </div>

            <div id="req-error" style="display:none; margin-top:14px; padding:10px 14px; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); border-radius:8px; color:#dc2626; font-size:13px; font-weight:600;"></div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:24px; padding-top:16px; border-top:1px solid var(--border);">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeRequisitionModal()">ยกเลิก</button>
                <button type="button" id="req-submit-btn" class="cmns-btn cmns-btn-primary" style="background:#10b981; border-color:#10b981;" onclick="submitRequisition()">
                    <span class="material-symbols-rounded">output</span> ยืนยันเบิก
                </button>
            </div>
        </div>
    </div>

    <!-- ── Strip Parts Modal ── -->
    <div id="modal-strip" class="cmns-modal">
        <div class="modal-content" style="max-width:860px; padding:30px;">

            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:20px; margin-bottom:20px;">
                <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-weight:800; font-size:20px; color:var(--text-main);">
                    <span class="material-symbols-rounded" style="color:#8b5cf6; font-size:28px;">content_cut</span>
                    แยกอะไหล่ → USED
                </h3>
                <button class="modal-close-btn" onclick="closeStripModal()"><span class="material-symbols-rounded">close</span></button>
            </div>

            <!-- machine source badge -->
            <div id="strip-machine-info" style="display:flex; align-items:center; gap:10px; background:rgba(139,92,246,.06); border:1px solid rgba(139,92,246,.2); border-radius:10px; padding:10px 16px; margin-bottom:22px;">
                <span class="material-symbols-rounded" style="color:#8b5cf6; font-size:20px;">computer</span>
                <div>
                    <div id="strip-machine-name" style="font-weight:700; font-size:13px; color:#8b5cf6;"></div>
                    <div id="strip-machine-tag" style="font-size:11px; color:var(--text-muted);"></div>
                </div>
            </div>

            <form id="form-strip" action="process_strip.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="source_machine_id" id="strip-machine-id">

                <div style="display:flex; gap:28px; flex-wrap:wrap; align-items:flex-start;">

                    <!-- รูป -->
                    <div style="flex-shrink:0; width:160px;">
                        <label class="cmns-label">PART IMAGE</label>
                        <div style="width:160px; height:160px; border:2px dashed var(--border); border-radius:16px; display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; overflow:hidden; cursor:pointer; transition:.2s; background:transparent;"
                             onclick="document.getElementById('strip-image').click()"
                             onmouseover="this.style.borderColor='#8b5cf6'" onmouseout="this.style.borderColor='var(--border)'">
                            <div id="strip-img-placeholder" style="display:flex; flex-direction:column; align-items:center; color:var(--text-muted); opacity:.5;">
                                <span class="material-symbols-rounded" style="font-size:40px; margin-bottom:8px;">add_photo_alternate</span>
                                <span style="font-size:11px; font-weight:700;">คลิกอัปโหลด</span>
                            </div>
                            <img id="strip-img-preview" src="" style="width:100%; height:100%; object-fit:contain; display:none; position:absolute; top:0; left:0; background:var(--bg-surface-alt);">
                        </div>
                        <input type="file" name="image" id="strip-image" accept="image/*" hidden onchange="previewStripImage(this)">
                    </div>

                    <!-- fields -->
                    <div style="flex:1; min-width:300px;">
                        <div style="display:grid; grid-template-columns:2fr 1fr; gap:14px; margin-bottom:14px;">
                            <div>
                                <label class="cmns-label">ชื่ออะไหล่ <span style="color:red">*</span></label>
                                <input type="text" name="name" id="strip-name" class="cmns-input" placeholder="เช่น LCD MacBook Pro 13 A2338" required>
                            </div>
                            <div>
                                <label class="cmns-label">รหัส SKU</label>
                                <input type="text" name="sku" class="cmns-input" placeholder="เว้นว่างออโต้">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                            <div>
                                <label class="cmns-label">1. อุปกรณ์ <span style="color:red">*</span></label>
                                <select id="strip-main-cat" class="cmns-input" required onchange="updateStripSubCat()">
                                    <option value="">-- เลือกอุปกรณ์ --</option>
                                    <?php foreach(array_values($main_cats) as $mc): ?>
                                        <option value="<?= $mc['id'] ?>"><?= htmlspecialchars($mc['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="cmns-label">2. ประเภทอะไหล่ <span style="color:red">*</span></label>
                                <select name="category_id" id="strip-sub-cat" class="cmns-input" required disabled>
                                    <option value="">-- รอเลือกอุปกรณ์ --</option>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                            <div>
                                <label class="cmns-label">Part No.</label>
                                <input type="text" name="part_number" class="cmns-input" placeholder="เช่น 661-18505">
                            </div>
                            <div>
                                <label class="cmns-label">Serial No.</label>
                                <input type="text" name="serial_number" class="cmns-input" placeholder="S/N ของชิ้นส่วน">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:14px;">
                            <div>
                                <label class="cmns-label" style="color:#f59e0b;">สภาพ <span style="color:red">*</span></label>
                                <select name="status" class="cmns-input" style="border-color:#f59e0b; font-weight:700; color:#f59e0b;" required>
                                    <option value="GOOD">GOOD</option>
                                    <option value="TEST">TEST</option>
                                    <option value="DEAD">DEAD</option>
                                </select>
                            </div>
                            <div>
                                <label class="cmns-label">ราคาขาย (฿)</label>
                                <input type="number" name="sell_price" class="cmns-input" value="0" step="0.01">
                            </div>
                            <div>
                                <label class="cmns-label">ทุน (฿)</label>
                                <input type="number" name="cost_price" class="cmns-input" value="0" step="0.01">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                            <div>
                                <label class="cmns-label">ตำแหน่งเก็บ</label>
                                <input type="text" name="location" class="cmns-input" placeholder="ตู้ A ชั้น 2">
                            </div>
                            <div>
                                <label class="cmns-label">หมายเหตุสภาพ</label>
                                <input type="text" name="condition_note" class="cmns-input" placeholder="มีรอยขีดข่วนเล็กน้อย...">
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:28px; padding-top:20px; border-top:1px solid var(--border);">
                    <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeStripModal()">ยกเลิก</button>
                    <button type="submit" class="cmns-btn cmns-btn-primary" style="background:#8b5cf6; border-color:#8b5cf6; padding:12px 28px;">
                        <span class="material-symbols-rounded">add_circle</span> บันทึกเข้า USED
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="cmns-footer-pagination">
        <div class="pagination-info">Showing <b><?= min($total_items, $offset + 1) ?>-<?= min($total_items, $offset + $per_page) ?></b> of <b><?= number_format($total_items) ?></b> items</div>
        <div class="pagination-controls">
            <a href="?id=<?= $category_id ?>&q=<?= $search ?>&type=<?= $current_type ?>&status=<?= $current_status ?>&sort=<?= $sort ?>&page=<?= max(1, $page-1) ?>" class="page-nav-btn <?= $page <= 1 ? 'disabled' : '' ?>"><span class="material-symbols-rounded">chevron_left</span></a>
            <a href="?id=<?= $category_id ?>&q=<?= $search ?>&type=<?= $current_type ?>&status=<?= $current_status ?>&sort=<?= $sort ?>&page=<?= min($total_pages, $page+1) ?>" class="page-nav-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><span class="material-symbols-rounded">chevron_right</span></a>
        </div>
    </div>
</div>

<script>
// 1. ระบบกางแถว
function toggleLotDetails(id) {
    const detailRow = document.getElementById(`lot-detail-${id}`);
    const contentDiv = document.getElementById(`lot-content-${id}`);
    const mainRow = document.getElementById(`row-${id}`);

    if (!detailRow || !contentDiv || !mainRow) return;

    if (detailRow.style.display === 'table-row') {
        detailRow.style.display = 'none';
        mainRow.classList.remove('active');
        return;
    }

    document.querySelectorAll('.lot-detail-row').forEach(row => row.style.display = 'none');
    document.querySelectorAll('.inventory-row').forEach(row => row.classList.remove('active'));

    detailRow.style.display = 'table-row';
    mainRow.classList.add('active');
    
    contentDiv.innerHTML = '<div style="padding:40px; text-align:center; color:var(--text-muted);"><span class="material-symbols-rounded spin-icon" style="font-size: 24px;">sync</span></div>';

    fetch(`view.php?action=get_lots_inline&item_id=${id}`)
        .then(res => res.text())
        .then(data => { contentDiv.innerHTML = data; })
        .catch(err => { contentDiv.innerHTML = '<div style="padding:20px; text-align:center; color:#ef4444;">โหลดข้อมูลไม่สำเร็จ</div>'; });
}

const style = document.createElement('style');
style.innerHTML = `@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`;
document.head.appendChild(style);

// 2. ระบบค้นหา Real-time (รอ 0.5 วิแล้ว Submit)
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.querySelector('.view-search-input');
    if (searchInput) {
        
        // เลื่อนเคอร์เซอร์ไปท้ายสุด
        const val = searchInput.value;
        if (val) {
            searchInput.focus();
            searchInput.value = ''; 
            searchInput.value = val; 
        }

        let typingTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                const form = this.closest('form');
                if (form) form.submit();
            }, 500); 
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(typingTimer);
                const form = this.closest('form');
                if (form) form.submit();
            }
        });
    }
});
</script>

<!-- ── Requisition Modal JS ── -->
<script>
let _reqInventoryId = null;
let _jobSearchTimer = null;

/* ── Open modal ── */
function openRequisitionModal(inventoryId, itemType = 'new') {
    _reqInventoryId = inventoryId;

    // อัปเดต title ตาม type
    const titleEl = document.querySelector('#modal-requisition h3');
    if (titleEl) {
        if (itemType === 'used') {
            titleEl.innerHTML = '<span class="material-symbols-rounded" style="color:#f59e0b;font-size:26px;">build</span> ใช้อะไหล่ USED';
        } else {
            titleEl.innerHTML = '<span class="material-symbols-rounded" style="color:#10b981;font-size:26px;">output</span> เบิกอะไหล่ใหม่';
        }
    }

    // Reset
    document.getElementById('req-qty').value           = 1;
    document.getElementById('req-qty').max             = 99;
    document.getElementById('req-job-search').value    = '';
    document.getElementById('req-remarks').value       = '';
    document.getElementById('req-tracking-id').value   = '';
    document.getElementById('req-ticket-number').value = '';
    document.getElementById('req-lot-id').value        = '';
    document.getElementById('req-qty-max-label').textContent = '';
    document.getElementById('req-job-results').style.display  = 'none';
    document.getElementById('req-job-selected').style.display = 'none';
    document.getElementById('req-error').style.display        = 'none';
    document.getElementById('req-item-name').textContent = 'กำลังโหลด...';
    document.getElementById('req-item-sku').textContent  = '';
    document.getElementById('req-item-qty').textContent  = '';
    document.getElementById('req-lots-wrap').innerHTML  =
        '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;"><span class="material-symbols-rounded" style="animation:spin 1s linear infinite;font-size:20px;display:block;margin-bottom:6px;">sync</span>กำลังโหลด lots...</div>';

    document.getElementById('modal-requisition').classList.add('show');
    document.body.style.overflow = 'hidden';

    // Load item + lots in parallel
    Promise.all([
        fetch(`process_requisition.php?action=get_item&id=${inventoryId}`).then(r => r.json()),
        fetch(`process_requisition.php?action=get_lots&item_id=${inventoryId}`).then(r => r.json())
    ]).then(([item, lots]) => {
        document.getElementById('req-item-name').textContent = item.name || '—';
        document.getElementById('req-item-sku').textContent  = item.sku  || '—';
        document.getElementById('req-item-qty').textContent  = item.total_qty ?? 0;
        renderLots(lots, item.total_qty ?? 0);
    }).catch(() => {
        document.getElementById('req-item-name').textContent = 'โหลดไม่สำเร็จ';
    });
}

function renderLots(lots, totalQty) {
    const wrap = document.getElementById('req-lots-wrap');
    if (!lots.length) {
        wrap.innerHTML = '<div style="padding:14px;font-size:13px;color:#ef4444;">ไม่มีสต็อกในระบบ</div>';
        return;
    }

    let html = '';

    // FIFO option
    html += `
        <label class="lot-option" id="lot-opt-auto">
            <input type="radio" name="lot_pick" value="auto" checked onchange="onLotChange('auto',${totalQty})">
            <div class="lot-opt-body">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="material-symbols-rounded" style="font-size:16px;color:var(--primary);">auto_mode</span>
                    <span style="font-weight:700;font-size:13px;color:var(--text-main);">FIFO อัตโนมัติ</span>
                    <span style="font-size:11px;color:var(--text-muted);">(ตัดจากล็อตเก่าสุดก่อน)</span>
                </div>
                <span style="font-size:12px;color:var(--primary);font-weight:700;">รวม ${totalQty} ชิ้น</span>
            </div>
        </label>`;

    lots.forEach(lot => {
        const wEnd = lot.warranty_end ? new Date(lot.warranty_end) : null;
        const daysLeft = wEnd ? Math.ceil((wEnd - new Date()) / 86400000) : null;
        const wColor = !wEnd ? 'var(--text-muted)' : daysLeft < 0 ? '#ef4444' : daysLeft < 30 ? '#f59e0b' : '#10b981';
        const wText  = !wEnd ? '—' : wEnd.toLocaleDateString('th-TH',{day:'2-digit',month:'2-digit',year:'2-digit'});
        const expired = daysLeft !== null && daysLeft < 0;

        html += `
            <label class="lot-option ${expired ? 'lot-expired' : ''}" id="lot-opt-${lot.id}">
                <input type="radio" name="lot_pick" value="${lot.id}" ${expired?'disabled':''} onchange="onLotChange(${lot.id},${lot.qty_remaining})">
                <div class="lot-opt-body">
                    <div style="min-width:0;flex:1;">
                        <div style="font-weight:700;font-size:13px;color:var(--text-main);font-family:monospace;">${_escH(lot.lot_number)}</div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">${lot.supplier_name ? _escH(lot.supplier_name) : 'ไม่ระบุ Supplier'}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0;">
                        <span style="font-size:15px;font-weight:800;color:var(--text-main);">${lot.qty_remaining} <span style="font-size:10px;font-weight:500;color:var(--text-muted);">/ ${lot.qty_received}</span></span>
                        <span style="font-size:10px;font-weight:700;color:${wColor};">ประกัน ${wText}${expired?' (หมดแล้ว)':''}</span>
                        ${lot.cost_price > 0 ? `<span style="font-size:11px;color:var(--text-muted);">฿${Number(lot.cost_price).toLocaleString()}</span>` : ''}
                    </div>
                </div>
            </label>`;
    });

    wrap.innerHTML = html;

    // Set default max to total
    setQtyMax(totalQty);
}

function onLotChange(val, maxQty) {
    document.getElementById('req-lot-id').value = val === 'auto' ? '' : val;
    setQtyMax(maxQty);
}

function setQtyMax(max) {
    const inp = document.getElementById('req-qty');
    inp.max = max;
    if (parseInt(inp.value) > max) inp.value = max;
    document.getElementById('req-qty-max-label').textContent = `max ${max}`;
}

function _escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function closeRequisitionModal() {
    document.getElementById('modal-requisition').classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('req-job-results').style.display = 'none';
}

/* ── Qty +/- ── */
function adjustQty(delta) {
    const input = document.getElementById('req-qty');
    const maxQ  = parseInt(document.getElementById('req-item-qty').textContent) || 1;
    input.value = Math.max(1, Math.min(maxQ, (parseInt(input.value) || 1) + delta));
}

/* ── Job search ── */
function searchJobs(q) {
    clearTimeout(_jobSearchTimer);
    const results = document.getElementById('req-job-results');

    if (q.length < 2) { results.style.display = 'none'; return; }

    _jobSearchTimer = setTimeout(() => {
        fetch(`process_requisition.php?action=search_jobs&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(jobs => {
                if (!jobs.length) {
                    results.innerHTML = `<div style="padding:14px 16px; font-size:13px; color:var(--text-muted);">ไม่พบงานซ่อมที่ตรงกัน</div>`;
                } else {
                    results.innerHTML = jobs.map(j => `
                        <div class="req-job-item" onclick="selectJob(${j.id},'${escJ(j.ticket_number)}','${escJ(j.customer_name)}','${escJ(j.device_type)}','${escJ(j.device_model)}')"
                             style="padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--border); transition:background .12s;">
                            <div style="font-weight:700; font-size:13px; color:var(--text-main);">
                                <code style="color:var(--primary); font-size:12px;">${escH(j.ticket_number)}</code>
                                &nbsp;${escH(j.customer_name)}
                            </div>
                            <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                ${escH(j.device_type)} ${escH(j.device_model)} · ${escH(j.customer_phone)}
                            </div>
                        </div>
                    `).join('');
                }
                results.style.display = 'block';
            });
    }, 300);
}

function selectJob(id, ticket, name, dtype, dmodel) {
    document.getElementById('req-tracking-id').value   = id;
    document.getElementById('req-ticket-number').value = ticket;
    document.getElementById('req-job-search').value    = '';
    document.getElementById('req-job-results').style.display = 'none';
    document.getElementById('req-job-label').innerHTML =
        `<code style="color:var(--primary);">${escH(ticket)}</code> &nbsp;${escH(name)} · ${escH(dtype)} ${escH(dmodel)}`;
    document.getElementById('req-job-selected').style.display = 'block';
}

function clearJobSelection() {
    document.getElementById('req-tracking-id').value   = '';
    document.getElementById('req-ticket-number').value = '';
    document.getElementById('req-job-selected').style.display = 'none';
}

/* ── Submit ── */
function submitRequisition() {
    const btn = document.getElementById('req-submit-btn');
    const err = document.getElementById('req-error');
    err.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="animation:spin 1s linear infinite;">sync</span> กำลังเบิก...';

    const body = new FormData();
    body.append('action',         'requisition');
    body.append('inventory_id',   _reqInventoryId);
    body.append('qty',            document.getElementById('req-qty').value);
    body.append('lot_id',         document.getElementById('req-lot-id').value);
    body.append('tracking_id',    document.getElementById('req-tracking-id').value);
    body.append('ticket_number',  document.getElementById('req-ticket-number').value);
    body.append('remarks',        document.getElementById('req-remarks').value);

    fetch('process_requisition.php', { method: 'POST', body })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                closeRequisitionModal();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 500);
            } else {
                err.textContent = res.msg;
                err.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-rounded">output</span> ยืนยันเบิก';
            }
        })
        .catch(() => {
            err.textContent = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">output</span> ยืนยันเบิก';
        });
}

/* ── Helpers ── */
function escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escJ(s) { return String(s||'').replace(/'/g,"\\'"); }

// Close on backdrop click
document.getElementById('modal-requisition').addEventListener('click', function(e) {
    if (e.target === this) closeRequisitionModal();
});

// Close job results on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('#req-job-search') && !e.target.closest('#req-job-results')) {
        document.getElementById('req-job-results').style.display = 'none';
    }
});

// Hover effect for job items
document.getElementById('req-job-results').addEventListener('mouseover', e => {
    const item = e.target.closest('.req-job-item');
    if (item) item.style.background = 'var(--bg-surface-alt)';
});
document.getElementById('req-job-results').addEventListener('mouseout', e => {
    const item = e.target.closest('.req-job-item');
    if (item) item.style.background = '';
});
</script>

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

<!-- ══════════════════════════════════════════════════════
     Transfer to SALE Modal
═══════════════════════════════════════════════════════ -->
<div id="modal-to-sale" class="cmns-modal">
    <div class="modal-content" style="max-width:820px; padding:30px; max-height:92vh; overflow-y:auto;">

        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:18px; margin-bottom:22px;">
            <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-weight:800; font-size:20px; color:var(--text-main);">
                <span class="material-symbols-rounded" style="color:#ef4444; font-size:26px;">sell</span>
                ย้ายไป SALE
            </h3>
            <button class="modal-close-btn" onclick="closeToSaleModal()"><span class="material-symbols-rounded">close</span></button>
        </div>

        <!-- Source info badge -->
        <div id="ts-source-info" style="display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:10px; border:1px solid var(--border); background:var(--bg-surface-alt); margin-bottom:20px;">
            <span id="ts-source-icon" class="material-symbols-rounded" style="font-size:22px;"></span>
            <div>
                <div id="ts-source-name" style="font-weight:700; font-size:14px; color:var(--text-main);">กำลังโหลด...</div>
                <div id="ts-source-meta" style="font-size:11px; color:var(--text-muted); margin-top:2px;"></div>
            </div>
        </div>

        <!-- Lot selector + Qty (NEW only) -->
        <div id="ts-lot-section" style="display:none; margin-bottom:20px; padding:16px; border:1px solid rgba(37,99,235,.25); border-radius:10px; background:rgba(37,99,235,.04);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="font-size:11px; font-weight:800; color:var(--primary); text-transform:uppercase; letter-spacing:.8px; display:flex; align-items:center; gap:6px;">
                    <span class="material-symbols-rounded" style="font-size:16px;">account_tree</span>
                    เลือก LOT
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <label style="font-size:11px; font-weight:800; color:var(--primary); text-transform:uppercase; letter-spacing:.8px;">จำนวน</label>
                    <button type="button" onclick="tsAdjQty(-1)" style="width:28px;height:28px;border-radius:6px;border:1px solid rgba(37,99,235,.35);background:rgba(37,99,235,.08);color:var(--primary);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">−</button>
                    <input type="number" id="ts-qty" name="qty" value="1" min="1" max="1"
                           style="width:56px;text-align:center;padding:5px 6px;border:1.5px solid rgba(37,99,235,.4);border-radius:8px;background:var(--bg-surface);color:var(--text-main);font-size:15px;font-weight:800;outline:none;">
                    <button type="button" onclick="tsAdjQty(1)" style="width:28px;height:28px;border-radius:6px;border:1px solid rgba(37,99,235,.35);background:rgba(37,99,235,.08);color:var(--primary);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">+</button>
                    <span id="ts-qty-max-label" style="font-size:11px;color:var(--text-muted);"></span>
                </div>
            </div>
            <div id="ts-lots-wrap"></div>
            <input type="hidden" id="ts-lot-id" value="">
        </div>

        <form id="form-to-sale">
            <input type="hidden" id="ts-source-type"  name="source_type">
            <input type="hidden" id="ts-inventory-id" name="inventory_id">

            <!-- Row 1: Name + Sell Price + Status + Grade -->
            <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="cmns-label">ชื่อสินค้า <span style="color:red">*</span></label>
                    <input type="text" name="name" id="ts-name" class="cmns-input" required>
                </div>
                <div>
                    <label class="cmns-label">ราคาขาย (฿)</label>
                    <input type="number" name="sell_price" id="ts-sell-price" class="cmns-input" step="1" value="0" min="0">
                </div>
                <div>
                    <label class="cmns-label">Status</label>
                    <select name="status" id="ts-status" class="cmns-input" style="font-weight:700;">
                        <option value="PENDING">PENDING</option>
                        <option value="READY">READY</option>
                    </select>
                </div>
                <div>
                    <label class="cmns-label">Grade</label>
                    <select name="condition_grade" id="ts-grade" class="cmns-input" style="font-weight:700;">
                        <option value="">— ไม่ระบุ —</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
            </div>

            <!-- Row 2: Serial + Asset Tag + Color -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="cmns-label">Serial No.</label>
                    <input type="text" name="serial_number" id="ts-serial" class="cmns-input" placeholder="XXXXXXXXXXXXX">
                </div>
                <div>
                    <label class="cmns-label">Asset Tag</label>
                    <input type="text" name="asset_tag" id="ts-asset-tag" class="cmns-input" placeholder="CMNS-0001">
                </div>
                <div>
                    <label class="cmns-label">สี (Color)</label>
                    <input type="text" name="color" id="ts-color" class="cmns-input" placeholder="Space Gray">
                </div>
            </div>

            <!-- Row 3: Specs -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="cmns-label">CPU / Chip</label>
                    <input type="text" name="cpu_spec" id="ts-cpu" class="cmns-input" placeholder="M2 Pro 12-core">
                </div>
                <div>
                    <label class="cmns-label">RAM</label>
                    <input type="text" name="ram_spec" id="ts-ram" class="cmns-input" placeholder="16GB">
                </div>
                <div>
                    <label class="cmns-label">Storage</label>
                    <input type="text" name="storage_spec" id="ts-storage" class="cmns-input" placeholder="512GB SSD">
                </div>
            </div>

            <!-- Row 4: Warranty + Battery -->
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label class="cmns-label">ประกัน Apple (วันหมด)</label>
                    <input type="date" name="apple_warranty_date" id="ts-apple-warranty" class="cmns-input">
                </div>
                <div>
                    <label class="cmns-label">ประกันร้าน (วัน)</label>
                    <input type="number" name="store_warranty_days" id="ts-store-warranty" class="cmns-input" min="0" placeholder="90">
                </div>
                <div>
                    <label class="cmns-label">Battery Health (%)</label>
                    <input type="number" name="battery_health" id="ts-battery-health" class="cmns-input" min="0" max="100" placeholder="89">
                </div>
                <div>
                    <label class="cmns-label">Battery Cycles</label>
                    <input type="number" name="battery_cycles" id="ts-battery-cycles" class="cmns-input" min="0" placeholder="150">
                </div>
            </div>

            <!-- Row 5: Condition note -->
            <div style="margin-bottom:6px;">
                <label class="cmns-label">ตำหนิ / สภาพ</label>
                <input type="text" name="condition_note" id="ts-condition-note" class="cmns-input" placeholder="มีรอยขีดข่วนเล็กน้อยด้านล่าง...">
            </div>
        </form>

        <div id="ts-error" style="display:none; margin-top:14px; padding:10px 14px; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); border-radius:8px; color:#dc2626; font-size:13px; font-weight:600;"></div>

        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:22px; padding-top:16px; border-top:1px solid var(--border);">
            <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeToSaleModal()">ยกเลิก</button>
            <button type="button" id="ts-submit-btn" class="cmns-btn cmns-btn-primary" style="background:#ef4444; border-color:#ef4444;" onclick="submitToSale()">
                <span class="material-symbols-rounded">sell</span> ย้ายไป SALE
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     Mark Sold Modal
═══════════════════════════════════════════════════════ -->
<div id="modal-mark-sold" class="cmns-modal">
    <div class="modal-content" style="max-width:420px; padding:28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:16px; margin-bottom:20px;">
            <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-size:18px; color:var(--text-main); font-weight:800;">
                <span class="material-symbols-rounded" style="color:#ef4444; font-size:24px;">payments</span>
                ยืนยันขาย
            </h3>
            <button class="modal-close-btn" onclick="closeMarkSoldModal()"><span class="material-symbols-rounded">close</span></button>
        </div>

        <div id="ms-item-info" style="background:var(--bg-surface-alt); border:1px solid var(--border); border-radius:10px; padding:12px 16px; margin-bottom:20px;">
            <div id="ms-item-name" style="font-weight:700; font-size:14px; color:var(--text-main);"></div>
        </div>

        <div style="margin-bottom:20px;">
            <label class="cmns-label">ราคาที่ขายจริง (฿)</label>
            <input type="number" id="ms-sold-price" class="cmns-input" step="1" min="0" style="font-size:20px; font-weight:800; text-align:center;">
        </div>

        <input type="hidden" id="ms-inventory-id">

        <div id="ms-error" style="display:none; margin-bottom:14px; padding:10px 14px; background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); border-radius:8px; color:#dc2626; font-size:13px; font-weight:600;"></div>

        <div style="display:flex; justify-content:flex-end; gap:10px; padding-top:16px; border-top:1px solid var(--border);">
            <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeMarkSoldModal()">ยกเลิก</button>
            <button type="button" id="ms-submit-btn" class="cmns-btn cmns-btn-primary" style="background:#ef4444; border-color:#ef4444;" onclick="submitMarkSold()">
                <span class="material-symbols-rounded">payments</span> ยืนยันขาย
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════
     JS: Transfer to SALE + Mark Sold + SALE status
═══════════════════════════════════════════════════════ -->
<script>
// ─── Transfer to SALE ─────────────────────────────────
let _tsInventoryId  = null;
let _tsSourceType   = null;
let _tsTotalQty     = 0;

const _tsTypeIcon  = { new:'fiber_new', used:'build', machine:'computer' };
const _tsTypeColor = { new:'#10b981',  used:'#f59e0b', machine:'#8b5cf6' };
const _tsTypeLabel = { new:'NEW',      used:'USED',     machine:'MACHINE' };

function openToSaleModal(inventoryId, sourceType) {
    _tsInventoryId = inventoryId;
    _tsSourceType  = sourceType;
    _tsTotalQty    = 0;

    // Reset form
    document.getElementById('ts-source-type').value  = sourceType;
    document.getElementById('ts-inventory-id').value = inventoryId;
    document.getElementById('ts-lot-id').value        = '';
    document.getElementById('ts-error').style.display = 'none';
    document.getElementById('form-to-sale').reset();
    document.getElementById('ts-source-type').value  = sourceType;
    document.getElementById('ts-inventory-id').value = inventoryId;

    // Source badge
    const icon  = _tsTypeIcon[sourceType]  || 'inventory_2';
    const color = _tsTypeColor[sourceType] || '#888';
    const el = document.getElementById('ts-source-icon');
    el.textContent     = icon;
    el.style.color     = color;
    document.getElementById('ts-source-name').textContent = 'กำลังโหลด...';
    document.getElementById('ts-source-meta').textContent = '';

    // Lot section: show only for NEW
    const qtyInp = document.getElementById('ts-qty');
    if (qtyInp) { qtyInp.value = 1; qtyInp.max = 1; }
    const maxLabel = document.getElementById('ts-qty-max-label');
    if (maxLabel) maxLabel.textContent = '';

    document.getElementById('ts-lot-section').style.display = sourceType === 'new' ? 'block' : 'none';
    document.getElementById('ts-lots-wrap').innerHTML =
        '<div style="padding:16px; text-align:center; color:var(--text-muted); font-size:13px;"><span class="material-symbols-rounded" style="animation:spin 1s linear infinite; font-size:18px; display:block; margin-bottom:4px;">sync</span>กำลังโหลด lots...</div>';

    document.getElementById('modal-to-sale').classList.add('show');
    document.body.style.overflow = 'hidden';

    // Fetch item data
    const fetches = [
        fetch(`process_to_sale.php?action=get_item&id=${inventoryId}`).then(r => r.json()),
    ];
    if (sourceType === 'new') {
        fetches.push(fetch(`process_requisition.php?action=get_lots&item_id=${inventoryId}`).then(r => r.json()));
    }

    Promise.all(fetches).then(([item, lots]) => {
        if (!item) { document.getElementById('ts-source-name').textContent = 'โหลดไม่สำเร็จ'; return; }
        _tsTotalQty = parseInt(item.total_qty) || 0;

        // Fill source info
        document.getElementById('ts-source-name').textContent = item.name || '—';
        const metaParts = [`${_tsTypeLabel[sourceType]}`];
        if (item.sku)          metaParts.push(`SKU: ${item.sku}`);
        if (item.total_qty > 0) metaParts.push(`คงเหลือ: ${item.total_qty}`);
        document.getElementById('ts-source-meta').textContent = metaParts.join(' · ');

        // Pre-fill SALE fields
        document.getElementById('ts-name').value          = item.name || '';
        document.getElementById('ts-sell-price').value    = item.sell_price || 0;
        document.getElementById('ts-serial').value        = item.serial_number || '';
        document.getElementById('ts-asset-tag').value     = item.asset_tag || '';
        document.getElementById('ts-color').value         = item.color || '';
        document.getElementById('ts-cpu').value           = item.cpu_spec || '';
        document.getElementById('ts-ram').value           = item.ram_spec || '';
        document.getElementById('ts-storage').value       = item.storage_spec || '';
        document.getElementById('ts-condition-note').value = item.condition_note || '';
        if (item.condition_grade) document.getElementById('ts-grade').value = item.condition_grade;
        if (item.apple_warranty_date) document.getElementById('ts-apple-warranty').value = item.apple_warranty_date;
        if (item.store_warranty_days) document.getElementById('ts-store-warranty').value = item.store_warranty_days;
        if (item.battery_health)  document.getElementById('ts-battery-health').value  = item.battery_health;
        if (item.battery_cycles)  document.getElementById('ts-battery-cycles').value  = item.battery_cycles;

        // For USED → default READY (สินค้ามีอยู่แล้ว)
        if (sourceType === 'used' || sourceType === 'machine') {
            document.getElementById('ts-status').value = 'READY';
        }

        // Render lots for NEW
        if (sourceType === 'new' && lots) {
            renderTsLots(lots);
        }
    }).catch(() => {
        document.getElementById('ts-source-name').textContent = 'โหลดไม่สำเร็จ';
    });
}

let _tsLots = [];

function renderTsLots(lots) {
    _tsLots = lots || [];
    const wrap = document.getElementById('ts-lots-wrap');
    if (!_tsLots.length) {
        wrap.innerHTML = '<div style="font-size:13px; color:#ef4444;">ไม่มีสต็อกในระบบ</div>';
        return;
    }

    let html = '';
    _tsLots.forEach((lot, i) => {
        const wEnd   = lot.warranty_end ? new Date(lot.warranty_end) : null;
        const dLeft  = wEnd ? Math.ceil((wEnd - new Date()) / 86400000) : null;
        const wColor = !wEnd ? 'var(--text-muted)' : dLeft < 0 ? '#ef4444' : dLeft < 30 ? '#f59e0b' : '#10b981';
        const wText  = !wEnd ? '—' : wEnd.toLocaleDateString('th-TH', {day:'2-digit', month:'2-digit', year:'2-digit'});

        html += `
            <label class="lot-option" style="margin-bottom:6px;">
                <input type="radio" name="ts_lot_pick" value="${lot.id}" data-max="${lot.qty_remaining}" ${i===0?'checked':''}
                       onchange="tsOnLotChange(this)">
                <div class="lot-opt-body">
                    <div>
                        <div style="font-weight:700; font-size:13px; font-family:monospace;">${_escH(lot.lot_number)}</div>
                        <div style="font-size:11px; color:var(--text-muted);">${lot.supplier_name ? _escH(lot.supplier_name) : 'ไม่ระบุ Supplier'}</div>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:2px;">
                        <span style="font-size:14px; font-weight:800;">${lot.qty_remaining} <span style="font-size:10px; font-weight:400; color:var(--text-muted);">/ ${lot.qty_received}</span></span>
                        <span style="font-size:10px; font-weight:700; color:${wColor};">Warranty: ${wText}</span>
                        ${lot.cost_price > 0 ? `<span style="font-size:11px; color:var(--text-muted);">ทุน ฿${Number(lot.cost_price).toLocaleString()}</span>` : ''}
                    </div>
                </div>
            </label>`;
    });

    wrap.innerHTML = html;
    // Init with first lot
    document.getElementById('ts-lot-id').value = _tsLots[0].id;
    tsSetQtyMax(_tsLots[0].qty_remaining);
}

function tsOnLotChange(radio) {
    document.getElementById('ts-lot-id').value = radio.value;
    tsSetQtyMax(parseInt(radio.dataset.max) || 1);
}

function tsSetQtyMax(max) {
    const inp = document.getElementById('ts-qty');
    if (!inp) return;
    inp.max = max;
    if (parseInt(inp.value) > max) inp.value = max;
    const label = document.getElementById('ts-qty-max-label');
    if (label) label.textContent = `max ${max}`;
}

function tsAdjQty(delta) {
    const inp = document.getElementById('ts-qty');
    if (!inp) return;
    const max = parseInt(inp.max) || 1;
    inp.value = Math.max(1, Math.min(max, (parseInt(inp.value) || 1) + delta));
}

function closeToSaleModal() {
    document.getElementById('modal-to-sale').classList.remove('show');
    document.body.style.overflow = '';
}

function submitToSale() {
    const btn = document.getElementById('ts-submit-btn');
    const err = document.getElementById('ts-error');
    err.style.display = 'none';

    const name = document.getElementById('ts-name').value.trim();
    if (!name) { err.textContent = 'กรุณากรอกชื่อสินค้า'; err.style.display = 'block'; return; }

    if (_tsSourceType === 'new' && !document.getElementById('ts-lot-id').value) {
        err.textContent = 'กรุณาเลือก Lot'; err.style.display = 'block'; return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="animation:spin 1s linear infinite;">sync</span> กำลังย้าย...';

    const fd = new FormData(document.getElementById('form-to-sale'));
    if (_tsSourceType === 'new') {
        fd.append('lot_id', document.getElementById('ts-lot-id').value);
        fd.append('qty', document.getElementById('ts-qty').value || 1);
    }

    fetch('process_to_sale.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                closeToSaleModal();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 500);
            } else {
                err.textContent = res.msg;
                err.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-rounded">sell</span> ย้ายไป SALE';
            }
        })
        .catch(() => {
            err.textContent = 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">sell</span> ย้ายไป SALE';
        });
}

document.getElementById('modal-to-sale').addEventListener('click', function(e) {
    if (e.target === this) closeToSaleModal();
});

// ─── SALE Status: PENDING → READY ─────────────────────
function updateSaleStatus(inventoryId, action) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('inventory_id', inventoryId);

    fetch('process_sale_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:2500, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 400);
            } else {
                alert(res.msg);
            }
        })
        .catch(() => alert('เกิดข้อผิดพลาด'));
}

// ─── Mark Sold Modal ───────────────────────────────────
function openMarkSoldModal(inventoryId, itemName, listPrice) {
    document.getElementById('ms-inventory-id').value = inventoryId;
    document.getElementById('ms-item-name').textContent = itemName;
    document.getElementById('ms-sold-price').value = listPrice;
    document.getElementById('ms-error').style.display = 'none';
    const btn = document.getElementById('ms-submit-btn');
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-rounded">payments</span> ยืนยันขาย';
    document.getElementById('modal-mark-sold').classList.add('show');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('ms-sold-price').select(), 100);
}

function closeMarkSoldModal() {
    document.getElementById('modal-mark-sold').classList.remove('show');
    document.body.style.overflow = '';
}

function submitMarkSold() {
    const btn = document.getElementById('ms-submit-btn');
    const err = document.getElementById('ms-error');
    err.style.display = 'none';

    const soldPrice = document.getElementById('ms-sold-price').value;
    if (soldPrice === '' || isNaN(parseFloat(soldPrice))) {
        err.textContent = 'กรุณากรอกราคาที่ขายจริง'; err.style.display = 'block'; return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="animation:spin 1s linear infinite;">sync</span> กำลังบันทึก...';

    const fd = new FormData();
    fd.append('action',       'mark_sold');
    fd.append('inventory_id', document.getElementById('ms-inventory-id').value);
    fd.append('sold_price',   soldPrice);

    fetch('process_sale_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                closeMarkSoldModal();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 500);
            } else {
                err.textContent = res.msg;
                err.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-rounded">payments</span> ยืนยันขาย';
            }
        })
        .catch(() => {
            err.textContent = 'เกิดข้อผิดพลาด';
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">payments</span> ยืนยันขาย';
        });
}

document.getElementById('modal-mark-sold').addEventListener('click', function(e) {
    if (e.target === this) closeMarkSoldModal();
});

// ─── Revert SALE → ที่เดิม ─────────────────────────────
function confirmRevertSale(inventoryId, itemName) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'warning',
            title: 'คืนของที่เดิม?',
            html: `<b>${_escH(itemName)}</b><br><span style="font-size:13px;color:#888;">จะถูกย้ายกลับไปยังประเภทเดิม (NEW/USED/MACHINE)</span>`,
            showCancelButton: true,
            confirmButtonText: 'คืนเลย',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#f59e0b',
        }).then(result => { if (result.isConfirmed) _doRevertSale(inventoryId); });
    } else {
        if (confirm(`คืน "${itemName}" กลับที่เดิม?`)) _doRevertSale(inventoryId);
    }
}

function _doRevertSale(inventoryId) {
    const fd = new FormData();
    fd.append('inventory_id', inventoryId);

    fetch('process_revert_sale.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:3000, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 500);
            } else {
                alert(res.msg);
            }
        })
        .catch(() => alert('เกิดข้อผิดพลาด'));
}

// ── HARD DELETE (super_admin id=1 only) — ลบทั้งก้อน ถาวร ไม่เก็บประวัติ ──
let _hdId = null;
function confirmHardDelete(inventoryId, itemName) {
    _hdId = inventoryId;
    document.getElementById('hd-title').textContent = itemName;
    const input = document.getElementById('hd-input');
    const btn   = document.getElementById('hd-btn');
    input.value = '';
    btn.disabled = true;
    document.getElementById('hd-overlay').style.display = 'flex';
    setTimeout(() => input.focus(), 50);
}
function closeHardDelete() { document.getElementById('hd-overlay').style.display = 'none'; }
function _hdCheck() {
    document.getElementById('hd-btn').disabled = (document.getElementById('hd-input').value !== 'DELETE');
}
function doHardDelete() {
    if (!_hdId || document.getElementById('hd-input').value !== 'DELETE') return;
    const btn = document.getElementById('hd-btn');
    btn.disabled = true; btn.textContent = 'กำลังลบ...';
    const fd = new FormData();
    fd.append('inventory_id', _hdId);
    fetch('process_delete.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            closeHardDelete();
            if (res.ok) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon:'success', title: res.msg, toast:true, position:'top-end', showConfirmButton:false, timer:2500, timerProgressBar:true });
                }
                setTimeout(() => location.reload(), 400);
            } else {
                Swal.fire({ icon:'error', title:'ลบไม่สำเร็จ', text: res.msg||'', toast:true, position:'top-end', showConfirmButton:false, timer:3000 });
            }
            btn.textContent = 'ลบถาวร';
        })
        .catch(() => { btn.disabled = false; btn.textContent = 'ลบถาวร'; alert('เกิดข้อผิดพลาด'); });
}
</script>

<?php if ($can_hard_delete): ?>
<!-- Hard Delete Confirm Dialog (super_admin id=1 only) — house style ตาม shop/user -->
<div id="hd-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);width:90%;max-width:380px;border-radius:16px;overflow:hidden;border:1px solid var(--border);box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <div style="padding:20px 20px 12px;text-align:center;background:rgba(239,68,68,.06);border-bottom:1px solid var(--border);">
      <div style="width:44px;height:44px;border-radius:50%;background:rgba(239,68,68,.12);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
        <span class="material-symbols-rounded" style="font-size:22px;">delete_forever</span>
      </div>
      <h3 style="margin:0;font-size:15px;font-weight:700;color:#dc2626;">ลบทั้งก้อนถาวร?</h3>
    </div>
    <div style="padding:16px 20px;text-align:center;font-size:14px;line-height:1.6;">
      ลบ <strong id="hd-title" style="color:var(--primary)"></strong> พร้อมล็อตสต็อกทั้งหมด<br>
      <span style="font-size:12px;color:#ef4444;font-weight:600;">‼️ กู้คืนไม่ได้ และไม่เก็บประวัติ</span>
      <input id="hd-input" type="text" oninput="_hdCheck()" placeholder="พิมพ์ DELETE เพื่อยืนยัน" autocomplete="off"
             style="width:100%;margin-top:14px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-surface-alt);color:var(--text-main);text-align:center;font-size:14px;letter-spacing:1px;">
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-surface-alt);display:flex;gap:8px;justify-content:center;">
      <button onclick="closeHardDelete()" class="cmns-btn cmns-btn-secondary">ยกเลิก</button>
      <button id="hd-btn" onclick="doHardDelete()" class="cmns-btn cmns-btn-primary" disabled style="background:#ef4444;border-color:#ef4444;">ลบถาวร</button>
    </div>
  </div>
</div>
<script>
document.getElementById('hd-overlay').addEventListener('click', e => { if (e.target === document.getElementById('hd-overlay')) closeHardDelete(); });
document.getElementById('hd-input').addEventListener('keydown', e => { if (e.key === 'Enter' && !document.getElementById('hd-btn').disabled) doHardDelete(); });
</script>
<?php endif; ?>

<?php include 'modal_add.php'; ?>

<!-- Edit Modal -->
<div id="modal-edit" class="cmns-modal">
    <div class="modal-content" style="max-width: 750px; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 25px;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px; color: var(--text-main); font-weight: 800; font-size: 20px;">
                <span class="material-symbols-rounded" style="color: var(--primary); font-size: 28px;">edit</span>
                <span id="edit-modal-title-text">แก้ไขข้อมูลสินค้า</span>
            </h3>
            <style>.rs-hidden{display:none !important;}</style>
            <button type="button" class="modal-close-btn" onclick="closeEditModal()"><span class="material-symbols-rounded">close</span></button>
        </div>

        <div id="edit-loading" style="text-align:center; padding: 60px; color:var(--text-muted);">
            <span class="material-symbols-rounded" style="font-size:36px; animation: spin 1s linear infinite;">sync</span>
        </div>

        <form id="form-edit-item" action="process_edit.php" method="POST" enctype="multipart/form-data" style="display:none;">
            <input type="hidden" name="id" id="edit-id">
            <input type="hidden" name="type" id="edit-type" value="new">
            <select name="status" id="edit-status" style="display:none;" disabled>
                <option value="STOCK">STOCK</option><option value="OOS">OOS</option>
                <option value="GOOD">GOOD</option><option value="TEST">TEST</option><option value="DEAD">DEAD</option>
                <option value="READY">READY</option><option value="PARTIAL">PARTIAL</option>
                <option value="DISCOUNT">DISCOUNT</option>
            </select>

            <!-- Info bar: Type + Status (read-only) -->
            <div id="edit-info-bar" style="display:flex; align-items:center; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                <span style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px;">ข้อมูลระบบ :</span>
                <div id="edit-type-badge"></div>
                <div id="edit-status-badge"></div>
            </div>

            <div id="edit-profile-block" style="display: flex; gap: 25px; flex-wrap: wrap; margin-bottom: 20px;">
                <!-- Image -->
                <div style="flex-shrink: 0;">
                    <label class="cmns-label">รูปสินค้า</label>
                    <div style="width: 120px; aspect-ratio:1; border: 2px dashed var(--border); border-radius:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; overflow:hidden; cursor:pointer;" onclick="document.getElementById('edit-image').click()">
                        <div id="edit-img-placeholder" style="display:flex; flex-direction:column; align-items:center; color:var(--text-muted); opacity:0.5;">
                            <span class="material-symbols-rounded" style="font-size:30px;">add_photo_alternate</span>
                            <span style="font-size:10px; font-weight:700;">เปลี่ยนรูป</span>
                        </div>
                        <img id="edit-img-preview" src="" style="width:100%; height:100%; object-fit:contain; display:none; position:absolute; top:0; left:0; background:var(--bg-surface-alt);">
                    </div>
                    <input type="file" name="image" id="edit-image" accept="image/*" hidden onchange="previewEditImage(this)">
                </div>

                <!-- Main fields -->
                <div style="flex:1; min-width: 280px;">
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom:12px;">
                        <div>
                            <label class="cmns-label">ชื่อสินค้า <span style="color:red">*</span></label>
                            <input type="text" name="name" id="edit-name" class="cmns-input" required>
                        </div>
                        <div>
                            <label class="cmns-label">SKU</label>
                            <input type="text" name="sku" id="edit-sku" class="cmns-input">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                        <div>
                            <label class="cmns-label">ราคาขาย (฿)</label>
                            <input type="number" name="sell_price" id="edit-sell-price" class="cmns-input" step="0.01">
                        </div>
                        <div>
                            <label class="cmns-label">ตำแหน่งเก็บ</label>
                            <input type="text" name="location" id="edit-location" class="cmns-input" placeholder="ตู้ A ชั้น 2">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic fields ตาม type -->
            <div id="edit-dynamic-fields" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;"></div>

            <!-- ── เติมสต็อก section (toggle) ── -->
            <div id="restock-section" style="display:none; border-top:1.5px dashed var(--border); margin-top:4px; padding-top:18px; margin-bottom:4px;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
                    <span class="material-symbols-rounded" style="font-size:20px; color:#10b981;">add_box</span>
                    <span style="font-weight:700; font-size:14px; color:var(--text-main);">เพิ่มสต็อก Lot ใหม่</span>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label class="cmns-label">จำนวนที่รับเข้า <span style="color:red">*</span></label>
                        <input type="number" name="qty_received" id="rs-qty" class="cmns-input" min="1" value="" placeholder="เช่น 5" disabled>
                    </div>
                    <div>
                        <label class="cmns-label">ราคาทุน / ชิ้น (฿)</label>
                        <input type="number" name="cost_price" id="rs-cost" class="cmns-input" min="0" step="0.01" value="0">
                    </div>
                    <div>
                        <label class="cmns-label">Supplier / แหล่งที่มา</label>
                        <input type="text" name="supplier_name" id="rs-supplier" class="cmns-input" placeholder="เช่น Apple Parts TH">
                    </div>
                    <div>
                        <label class="cmns-label">ประกันหมดอายุ</label>
                        <input type="date" name="warranty_end" id="rs-warranty" class="cmns-input">
                    </div>
                </div>
                <div style="margin-top:4px;">
                    <label class="cmns-label">Lot Number <span style="font-size:11px; color:var(--text-muted);">(ว่างเว้น = ออโต้)</span></label>
                    <input type="text" name="lot_number" id="rs-lot" class="cmns-input" placeholder="เช่น LOT-2026-001">
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; align-items:center; gap:10px; margin-top:18px; padding-top:16px; border-top:1px solid var(--border);">
                    <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeEditModal()">
                        <span class="material-symbols-rounded">close</span> ยกเลิก
                    </button>
                    <button type="button" id="btn-toggle-restock"
                            onclick="toggleRestockSection()"
                            class="cmns-btn cmns-btn-warranty">
                        <span class="material-symbols-rounded">add_box</span> เติมสต็อก
                    </button>
                <button type="submit" class="cmns-btn cmns-btn-primary">
                    <span class="material-symbols-rounded">save</span> บันทึก
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id) {
    const modal = document.getElementById('modal-edit');
    const form  = document.getElementById('form-edit-item');
    const loading = document.getElementById('edit-loading');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    form.style.display = 'none';
    loading.style.display = 'block';

    fetch(`process_edit.php?action=get_item&id=${id}`)
        .then(r => r.json())
        .then(item => {
            if (!item) { alert('ไม่พบข้อมูล'); closeEditModal(); return; }

            document.getElementById('edit-id').value         = item.id;
            document.getElementById('edit-name').value       = item.name || '';
            document.getElementById('edit-sku').value        = item.sku || '';
            document.getElementById('edit-sell-price').value = item.sell_price || '';
            document.getElementById('edit-location').value   = item.location || '';

            // ── Type badge (info only) ──
            const typeInput = document.getElementById('edit-type');
            const typeVal   = item.type || 'new';
            typeInput.value = typeVal;
            const typeColor = {new:'#10b981', used:'#f59e0b', machine:'#8b5cf6', sale:'#ef4444'};
            const tc = typeColor[typeVal] || 'var(--primary)';
            document.getElementById('edit-type-badge').innerHTML =
                `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:800;background:${tc}18;color:${tc};border:1px solid ${tc}33;">`+
                `<span style="width:6px;height:6px;border-radius:50%;background:${tc};"></span>`+
                `${typeVal.toUpperCase()}</span>`;

            // ── Status badge (info only) — actual value set by applyStatusOptions ──
            applyStatusOptions(typeVal, item.total_qty ?? 0, item.status);

            // รูปเดิม
            const preview = document.getElementById('edit-img-preview');
            const placeholder = document.getElementById('edit-img-placeholder');
            if (item.image) {
                preview.src = `../../uploads/inventory/${item.image}`;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                preview.style.display = 'none';
                placeholder.style.display = 'flex';
            }

            form.dataset.item = JSON.stringify(item);
            toggleEditTypeFields();

            loading.style.display = 'none';
            form.style.display = 'block';
        })
        .catch(() => { alert('โหลดข้อมูลล้มเหลว'); closeEditModal(); });
}

function applyStatusOptions(type, totalQty, currentStatus) {
    const sel     = document.getElementById('edit-status');
    const badgeEl = document.getElementById('edit-status-badge');
    const SC = {STOCK:'#10b981',OOS:'#ef4444',GOOD:'#10b981',TEST:'#f59e0b',DEAD:'#ef4444',READY:'#10b981',SOLD:'#6b7280',PENDING:'#f59e0b'};

    if (type === 'new') {
        const st = totalQty > 0 ? 'STOCK' : 'OOS';
        sel.value = st; sel.disabled = true;
        const c = SC[st];
        badgeEl.innerHTML =
            `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;`+
            `font-size:12px;font-weight:800;background:${c}18;color:${c};border:1px solid ${c}33;">`+
            `<span class="material-symbols-rounded" style="font-size:12px;">auto_mode</span>`+
            `${st}${totalQty>0?` · ${totalQty} ชิ้น`:''}</span>`;
    } else {
        const st = currentStatus || 'STOCK';
        sel.value = st; sel.disabled = false;
        const c = SC[st] || '#888';
        const opts = type === 'used'
            ? ['GOOD','TEST','DEAD']
            : type === 'new'  ? ['STOCK','OOS']
            : type === 'machine' ? ['READY','PARTIAL','stripped']
            : ['READY','SOLD','PENDING'];
        badgeEl.innerHTML =
            `<select onchange="syncStatusSelect(this)" `+
            `style="padding:3px 10px;border-radius:20px;border:1px solid ${c}44;background:${c}18;`+
            `color:${c};font-size:12px;font-weight:800;outline:none;cursor:pointer;">`+
            opts.map(o=>
                `<option value="${o}" ${o===st?'selected':''}>${o}</option>`).join('')+`</select>`;
    }
}

function updateAdjPreview(curQty) {
    const mode   = document.getElementById('adj-mode');
    const qtyInp = document.getElementById('adj-qty');
    const prev   = document.getElementById('adj-preview');
    if (!mode || !qtyInp || !prev) return;

    const modeVal = mode.value;
    qtyInp.style.opacity = modeVal ? '1' : '.35';
    qtyInp.style.pointerEvents = modeVal ? 'auto' : 'none';
    if (!modeVal) { prev.textContent = ''; qtyInp.value = ''; return; }

    const n = parseInt(qtyInp.value) || 0;
    let result, color;
    if (modeVal === 'add')      { result = curQty + n; color = '#10b981'; prev.textContent = `${curQty} + ${n} = ${result} ชิ้น`; }
    else if (modeVal === 'sub') { result = Math.max(0, curQty - n); color = '#ef4444'; prev.textContent = `${curQty} − ${n} = ${result} ชิ้น`; }
    else if (modeVal === 'set') { result = n; color = '#3b82f6'; prev.textContent = `ตั้งค่าเป็น ${n} ชิ้น`; }
    prev.style.color = color;
    prev.style.fontWeight = '600';
}

function syncStatusSelect(miniSel) {
    const val = miniSel.value;
    document.getElementById('edit-status').value = val;
    const SC = {STOCK:'#10b981',OOS:'#ef4444',GOOD:'#10b981',TEST:'#f59e0b',DEAD:'#ef4444',READY:'#10b981',SOLD:'#6b7280',PENDING:'#f59e0b'};
    const c = SC[val] || '#888';
    miniSel.style.borderColor = c+'44'; miniSel.style.background = c+'18'; miniSel.style.color = c;
}

function toggleEditTypeFields() {
    const type = document.getElementById('edit-type').value; // hidden input

    const form = document.getElementById('form-edit-item');
    let item = {};
    try { item = JSON.parse(form.dataset.item || '{}'); } catch(e) {}
    const totalQty = item.total_qty ?? 0;
    applyStatusOptions(type, totalQty, document.getElementById('edit-status').value);

    const container = document.getElementById('edit-dynamic-fields');
    try { item = JSON.parse(form.dataset.item || '{}'); } catch(e) {}
    let html = '';

    if (type === 'new') {
        const curQty = parseInt(item.total_qty) || 0;
        html = `
            <div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" value="${esc(item.part_number)}" placeholder="เช่น 661-123"></div>
            <div><label class="cmns-label">รุ่นรองรับ (Model)</label><input type="text" name="compatible_models" class="cmns-input" value="${esc(item.compatible_models)}" placeholder="เช่น A2337"></div>
            <div>
                <label class="cmns-label" style="color:#f59e0b;">เตือนของหมด (Min Qty)</label>
                <input type="number" name="min_qty" class="cmns-input" value="${item.min_qty || 1}" min="0">
            </div>
            <div>
                <label class="cmns-label" style="display:flex;justify-content:space-between;">
                    <span>ปรับสต็อก (Adjust)</span>
                    <span style="color:var(--text-muted);font-weight:400;">ปัจจุบัน: <b style="color:var(--text-main);">${curQty}</b></span>
                </label>
                <div style="display:flex;gap:6px;align-items:center;">
                    <select name="adjust_mode" id="adj-mode"
                            style="padding:9px 10px;border:1.5px solid var(--border);border-radius:8px;background:var(--bg-surface-alt);color:var(--text-main);font-size:13px;outline:none;flex-shrink:0;"
                            onchange="updateAdjPreview(${curQty})">
                        <option value="">— ไม่ปรับ —</option>
                        <option value="add">+ เพิ่ม</option>
                        <option value="sub">− ลด</option>
                        <option value="set">= ตั้งค่า</option>
                    </select>
                    <input type="number" name="adjust_qty" id="adj-qty" min="0" placeholder="0"
                           class="cmns-input" style="flex:1; opacity:.35; pointer-events:none;"
                           oninput="updateAdjPreview(${curQty})">
                </div>
                <div id="adj-preview" style="font-size:11px;color:var(--text-muted);margin-top:4px;min-height:16px;"></div>
            </div>
        `;
    } else if (type === 'used') {
        html = `
            <div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" value="${esc(item.part_number)}"></div>
            <div><label class="cmns-label">Min Qty</label><input type="number" name="min_qty" class="cmns-input" value="${item.min_qty || 1}" min="0"></div>
        `;
    } else if (type === 'machine') {
        html = `
            <div><label class="cmns-label">รหัสเครื่อง (Asset Tag)</label><input type="text" name="asset_tag" class="cmns-input" value="${esc(item.asset_tag)}"></div>
            <div><label class="cmns-label">Serial Number</label><input type="text" name="serial_number" class="cmns-input" value="${esc(item.serial_number)}"></div>
            <div style="grid-column:span 2;"><label class="cmns-label" style="color:#10b981;">สถานะการแยกอะไหล่</label>
                <select name="disassembly_status" class="cmns-input" style="border-color:#10b981;">
                    <option value="intact" ${item.disassembly_status=='intact'?'selected':''}>ยังไม่แกะ</option>
                    <option value="partially_stripped" ${item.disassembly_status=='partially_stripped'?'selected':''}>แกะไปบางส่วน</option>
                    <option value="stripped" ${item.disassembly_status=='stripped'?'selected':''}>แกะหมดแล้ว</option>
                </select>
            </div>
        `;
    } else if (type === 'sale') {
        const gradeOpts = ['A','B','C'].map(g =>
            `<option value="${g}" ${item.condition_grade===g?'selected':''}>${g}</option>`).join('');
        html = `
            <div>
                <label class="cmns-label" style="color:#ef4444;">Asset Tag</label>
                <input type="text" name="asset_tag" class="cmns-input" value="${esc(item.asset_tag)}" style="border-color:#ef4444;">
            </div>
            <div>
                <label class="cmns-label">Serial Number</label>
                <input type="text" name="serial_number" class="cmns-input" value="${esc(item.serial_number)}">
            </div>
            <div>
                <label class="cmns-label">สี (Color)</label>
                <input type="text" name="color" class="cmns-input" value="${esc(item.color)}" placeholder="เช่น Space Gray, Midnight">
            </div>
            <div>
                <label class="cmns-label" style="color:#ef4444;">เกรดสภาพ (Grade)</label>
                <select name="condition_grade" class="cmns-input" style="border-color:#ef4444;font-weight:700;">
                    <option value="">-- เลือกเกรด --</option>
                    ${gradeOpts}
                </select>
            </div>
            <div>
                <label class="cmns-label">CPU / Chip</label>
                <input type="text" name="cpu_spec" class="cmns-input" value="${esc(item.cpu_spec)}" placeholder="เช่น Apple M3 Pro">
            </div>
            <div>
                <label class="cmns-label">RAM</label>
                <input type="text" name="ram_spec" class="cmns-input" value="${esc(item.ram_spec)}" placeholder="เช่น 16GB">
            </div>
            <div>
                <label class="cmns-label">Storage</label>
                <input type="text" name="storage_spec" class="cmns-input" value="${esc(item.storage_spec)}" placeholder="เช่น 512GB SSD">
            </div>
            <div>
                <label class="cmns-label">GPU</label>
                <input type="text" name="gpu_spec" class="cmns-input" value="${esc(item.gpu_spec)}" placeholder="เช่น 18-core GPU">
            </div>
            <div>
                <label class="cmns-label" style="color:#10b981;">ประกัน Apple ศูนย์หมด</label>
                <input type="date" name="apple_warranty_date" class="cmns-input" value="${esc(item.apple_warranty_date)}" style="border-color:#10b981;">
            </div>
            <div>
                <label class="cmns-label" style="color:#3b82f6;">ประกันร้าน (วัน)</label>
                <input type="number" name="store_warranty_days" class="cmns-input" value="${item.store_warranty_days??''}" placeholder="เช่น 90, 180" min="0" style="border-color:#3b82f6;">
            </div>
            <div>
                <label class="cmns-label">สุขภาพแบต (%)</label>
                <input type="number" name="battery_health" class="cmns-input" value="${item.battery_health??''}" placeholder="เช่น 89" min="0" max="100">
            </div>
            <div>
                <label class="cmns-label">รอบชาร์จ</label>
                <input type="number" name="battery_cycles" class="cmns-input" value="${item.battery_cycles??''}" placeholder="เช่น 142" min="0">
            </div>
            <div style="grid-column:span 2;">
                <label class="cmns-label">ตำหนิ / รายละเอียดสภาพ</label>
                <textarea name="condition_note" class="cmns-input" rows="2" style="resize:vertical;" placeholder="เช่น มีรอยขีดข่วนด้านล่าง จอสมบูรณ์...">${esc(item.condition_note)}</textarea>
            </div>
        `;
    }
    container.innerHTML = html;
}

function esc(v) {
    if (!v) return '';
    return String(v).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function previewEditImage(input) {
    const preview = document.getElementById('edit-img-preview');
    const placeholder = document.getElementById('edit-img-placeholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display='block'; placeholder.style.display='none'; };
        reader.readAsDataURL(input.files[0]);
    }
}

function closeEditModal() {
    const modal = document.getElementById('modal-edit');
    if (modal) { modal.classList.remove('show'); document.body.style.overflow = 'auto'; }
    // reset restock section
    const rs = document.getElementById('restock-section');
    const btn = document.getElementById('btn-toggle-restock');
    if (rs)  { rs.style.display = 'none'; rs.querySelectorAll('input').forEach(i => { if(i.name !== 'qty_received') i.value = i.defaultValue || ''; else i.value = 1; }); }
    if (btn) { btn.style.background = 'rgba(16,185,129,.1)'; btn.style.color = '#059669'; }
    // โชว์ส่วนแก้โปรไฟล์กลับ + คืนหัวข้อ (เผื่อปิดตอนอยู่โหมดเติมสต็อก)
    ['edit-info-bar', 'edit-profile-block', 'edit-dynamic-fields'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('rs-hidden');
    });
    const ttl0 = document.getElementById('edit-modal-title-text');
    if (ttl0) ttl0.textContent = 'แก้ไขข้อมูลสินค้า';
}

function toggleRestockSection() {
    const rs   = document.getElementById('restock-section');
    const btn  = document.getElementById('btn-toggle-restock');
    const opening = rs.style.display !== 'block';   // กำลังจะเปิดโหมดเติมสต็อก?
    rs.style.display = opening ? 'block' : 'none';
    rs.querySelectorAll('input').forEach(i => i.disabled = !opening);

    // เติมสต็อก = โชว์แค่ส่วนล่าง → ซ่อนส่วนบน (แก้โปรไฟล์)
    ['edit-info-bar', 'edit-profile-block', 'edit-dynamic-fields'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('rs-hidden', opening);
    });
    const ttl = document.getElementById('edit-modal-title-text');
    if (ttl) ttl.textContent = opening ? 'เติมสต็อก Lot ใหม่' : 'แก้ไขข้อมูลสินค้า';

    btn.classList.toggle('cmns-btn-warranty', !opening);
    btn.classList.toggle('cmns-btn-primary',  opening);
    if (opening) {
        btn.style.background = '#10b981';
        btn.style.borderColor = '#10b981';
        document.getElementById('rs-qty').focus();
    } else {
        btn.style.background = '';
        btn.style.borderColor = '';
    }
}

const _stripSubCats = <?= json_encode(array_values($sub_cats), JSON_UNESCAPED_UNICODE) ?>;

function openStripModal(machineId, machineName, assetTag) {
    document.getElementById('form-strip').reset();
    document.getElementById('strip-machine-id').value        = machineId;
    document.getElementById('strip-machine-name').textContent = machineName;
    document.getElementById('strip-machine-tag').textContent  = assetTag ? 'Asset Tag: ' + assetTag : '';
    document.getElementById('strip-img-preview').style.display    = 'none';
    document.getElementById('strip-img-placeholder').style.display = 'flex';
    document.getElementById('strip-sub-cat').innerHTML = '<option value="">-- รอเลือกอุปกรณ์ --</option>';
    document.getElementById('strip-sub-cat').disabled = true;
    document.getElementById('modal-strip').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeStripModal() {
    document.getElementById('modal-strip').classList.remove('show');
    document.body.style.overflow = 'auto';
}
function previewStripImage(input) {
    const preview     = document.getElementById('strip-img-preview');
    const placeholder = document.getElementById('strip-img-placeholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; placeholder.style.display = 'none'; };
        reader.readAsDataURL(input.files[0]);
    } else { preview.style.display = 'none'; placeholder.style.display = 'flex'; }
}
function updateStripSubCat() {
    const mainId = document.getElementById('strip-main-cat').value;
    const sub    = document.getElementById('strip-sub-cat');
    sub.innerHTML = '<option value="">-- เลือกประเภทอะไหล่ --</option>';
    if (!mainId) { sub.disabled = true; return; }
    sub.disabled = false;
    const filtered = _stripSubCats.filter(c => c.parent_id == mainId);
    if (filtered.length) {
        filtered.forEach(c => sub.innerHTML += `<option value="${c.id}">${c.name}</option>`);
        sub.innerHTML += `<option value="${mainId}" style="color:var(--primary);font-weight:bold;">ใส่ในตู้หลัก</option>`;
    } else {
        sub.innerHTML = `<option value="${mainId}" selected>ใส่ในตู้หลัก</option>`;
    }
}
</script>

<?php include '../templates/footer_admin.php'; ?>