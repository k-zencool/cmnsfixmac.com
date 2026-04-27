<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// =========================================================
// 🛑 1. SELF-AJAX LOGIC (กางรายละเอียดล็อตแบบ Seamless)
// =========================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_lots_inline') {
    $item_id = (int)$_GET['item_id'];
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
                        $urgent = (strtotime($l['warranty_end']) - time() < 2592000);
                    ?>
                    <tr>
                        <td><code><?= htmlspecialchars($l['lot_number']) ?></code></td>
                        <td align="center" style="color: var(--text-main);">
                            <b style="font-size: 14px;"><?= $l['qty_remaining'] ?></b> <span style="color: var(--text-muted);">/ <?= $l['qty_received'] ?></span>
                        </td>
                        <td align="center" style="color: var(--text-muted);">฿<?= number_format($l['cost_price']) ?></td>
                        <td align="center" style="color: <?= $urgent ? '#ef4444' : 'var(--text-main)' ?>; font-weight: <?= $urgent ? '800' : '500' ?>;">
                            <?= date('d/m/Y', strtotime($l['warranty_end'])) ?>
                        </td>
                        <td style="color: var(--text-muted);"><?= htmlspecialchars($l['supplier_name']) ?></td>
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
if ($search !== '') {
    $where[] = "(i.name LIKE ? OR i.asset_tag LIKE ? OR i.serial_number LIKE ? OR i.sku LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
$where_sql = implode(" AND ", $where);

$order_map = ['newest'=>'i.created_at DESC','oldest'=>'i.created_at ASC','price_low'=>'i.sell_price ASC','price_high'=>'i.sell_price DESC'];
$order_sql = "ORDER BY " . ($order_map[$sort] ?? 'i.created_at DESC');

$stmt_count = $pdo->prepare("SELECT COUNT(DISTINCT i.id) FROM inventory i WHERE $where_sql");
$stmt_count->execute($params);
$total_items = $stmt_count->fetchColumn();
$total_pages = ceil($total_items / $per_page);

$sql = "SELECT i.*, SUM(l.qty_remaining) as total_qty, 
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
                        $status_opts = ($current_type == 'new') ? ['STOCK', 'OOS'] : (($current_type == 'used') ? ['GOOD', 'TEST'] : ['READY', 'PARTIAL', 'DISCOUNT']);
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
                <thead>
                    <tr>
                        <th width="40" style="text-align: center;">#</th>
                        <th width="60">IMG</th>
                        <?php if($current_type == 'machine'): ?>
                            <th>MACHINE DETAILS</th>
                            <th>SERIAL / ASSET</th>
                        <?php elseif($current_type == 'sale'): ?>
                            <th>PRODUCT NAME</th>
                            <th>CONDITION</th>
                        <?php else: ?>
                            <th>PRODUCT NAME / SKU</th>
                            <th>LOCATION</th>
                        <?php endif; ?>
                        <th width="60" style="text-align: center;">QTY</th>
                        <th width="140" style="text-align: center;">STATUS / WARRANTY</th>
                        <th width="100" style="text-align: right;">PRICE</th>
                        <th width="60" style="text-align: center;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($items)): ?>
                        <tr><td colspan="9" align="center" style="padding: 100px 20px; color: var(--text-muted);">No items found in this section!</td></tr>
                    <?php else: ?>
                        <?php foreach($items as $idx => $item): 
                            $it = $item['type'];
                        ?>
                            <tr class="inventory-row" id="row-<?= $item['id'] ?>" onclick="toggleLotDetails(<?= $item['id'] ?>)">
                                <td align="center" style="opacity: 0.5; font-size: 11px;"><?= ($offset + $idx + 1) ?></td>
                                
                                <td>
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="../../uploads/inventory/<?= htmlspecialchars($item['image']) ?>" 
                                             alt="img"
                                             style="width: 44px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);"
                                             onerror="this.outerHTML='<div class=\'no-img-box\'><span class=\'material-symbols-rounded\'>image_not_supported</span></div>'">
                                    <?php else: ?>
                                        <div class="no-img-box"><span class="material-symbols-rounded">image</span></div>
                                    <?php endif; ?>
                                </td>
                                
                                <?php if($it == 'machine'): ?>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 14px;"><?= htmlspecialchars($item['name']) ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);">Model: <?= htmlspecialchars($item['device_model'] ?: '-') ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px;">S/N: <span class="serial-number"><?= htmlspecialchars($item['serial_number'] ?: '-') ?></span></div>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Asset: <code><?= htmlspecialchars($item['asset_tag'] ?: '-') ?></code></div>
                                    </td>
                                <?php elseif($it == 'sale'): ?>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 14px;"><?= htmlspecialchars($item['name']) ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);">Device: <?= htmlspecialchars($item['device_type'] ?: '-') ?></div>
                                    </td>
                                    <td><span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($item['condition_note'] ?: '-') ?></span></td>
                                <?php else: ?>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main); font-size: 14px;"><?= htmlspecialchars($item['name']) ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);">SKU: <code><?= htmlspecialchars($item['sku'] ?: '-') ?></code></div>
                                    </td>
                                    <td><span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($item['location'] ?: 'Not Set') ?></span></td>
                                <?php endif; ?>

                                <td align="center"><b style="font-size:15px;"><?= number_format($item['total_qty'] ?: 0) ?></b></td>
                                <td align="center">
                                    <?php 
                                        $st = strtoupper(trim($item['status']));
                                        $st_class = ($st == 'STOCK' || $st == 'GOOD' || $st == 'READY') ? 'status-green' : (($st == 'TEST') ? 'status-orange' : 'status-red');
                                        if($st == 'PARTIAL') $st_class = 'status-purple';
                                    ?>
                                    <span class="status-indicator <?= $st_class ?>"><?= $st ?: 'UNKNOWN' ?></span>
                                    <?php if($item['nearest_warranty']): ?>
                                        <div style="font-size: 10px; color: var(--text-muted); margin-top: 6px;">Exp: <?= date('d/m/y', strtotime($item['nearest_warranty'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td align="right" style="font-weight: 700; color: var(--primary); font-size: 14px;">฿<?= number_format($item['sell_price']) ?></td>
                                <td align="center">
                                    <button class="edit-btn" onclick="event.stopPropagation(); openEditModal(<?= $item['id'] ?>)"><span class="material-symbols-rounded">edit</span></button>
                                </td>
                            </tr>
                            
                            <tr id="lot-detail-<?= $item['id'] ?>" class="lot-detail-row" style="display:none;">
                                <td colspan="9">
                                    <div id="lot-content-<?= $item['id'] ?>"></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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

<link rel="stylesheet" href="../templates/assets/css/modal.css?v=<?= time(); ?>">
<?php include 'modal_add.php'; ?>

<!-- Edit Modal -->
<div id="modal-edit" class="cmns-modal">
    <div class="modal-content" style="max-width: 750px; padding: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 20px; margin-bottom: 25px;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 10px; color: var(--text-main); font-weight: 800; font-size: 20px;">
                <span class="material-symbols-rounded" style="color: var(--primary); font-size: 28px;">edit</span>
                แก้ไขข้อมูลสินค้า
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeEditModal()"><span class="material-symbols-rounded">close</span></button>
        </div>

        <div id="edit-loading" style="text-align:center; padding: 60px; color:var(--text-muted);">
            <span class="material-symbols-rounded" style="font-size:36px; animation: spin 1s linear infinite;">sync</span>
        </div>

        <form id="form-edit-item" action="process_edit.php" method="POST" enctype="multipart/form-data" style="display:none;">
            <input type="hidden" name="id" id="edit-id">

            <div style="display: flex; gap: 25px; flex-wrap: wrap; margin-bottom: 20px;">
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
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                        <div>
                            <label class="cmns-label">ประเภท (Type)</label>
                            <select name="type" id="edit-type" class="cmns-input" onchange="toggleEditTypeFields()">
                                <option value="new">NEW</option>
                                <option value="used">USED</option>
                                <option value="machine">MACHINE</option>
                                <option value="sale">SALE</option>
                            </select>
                        </div>
                        <div>
                            <label class="cmns-label">สถานะ</label>
                            <select name="status" id="edit-status" class="cmns-input">
                                <option value="STOCK">STOCK</option>
                                <option value="OOS">OOS</option>
                                <option value="GOOD">GOOD</option>
                                <option value="TEST">TEST</option>
                                <option value="READY">READY</option>
                                <option value="PARTIAL">PARTIAL</option>
                                <option value="DISCOUNT">DISCOUNT</option>
                            </select>
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

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:10px;">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closeEditModal()">ยกเลิก</button>
                <button type="submit" class="cmns-btn cmns-btn-primary" style="padding:12px 30px;">
                    <span class="material-symbols-rounded">save</span> บันทึกการแก้ไข
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

            document.getElementById('edit-id').value        = item.id;
            document.getElementById('edit-name').value      = item.name || '';
            document.getElementById('edit-sku').value       = item.sku || '';
            document.getElementById('edit-type').value      = item.type || 'new';
            document.getElementById('edit-status').value    = item.status || 'STOCK';
            document.getElementById('edit-sell-price').value = item.sell_price || '';
            document.getElementById('edit-location').value  = item.location || '';

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

            // เก็บข้อมูลไว้ใน dataset เพื่อใช้ใน toggleEditTypeFields
            form.dataset.item = JSON.stringify(item);
            toggleEditTypeFields();

            loading.style.display = 'none';
            form.style.display = 'block';
        })
        .catch(() => { alert('โหลดข้อมูลล้มเหลว'); closeEditModal(); });
}

function toggleEditTypeFields() {
    const type = document.getElementById('edit-type').value;
    const container = document.getElementById('edit-dynamic-fields');
    const form = document.getElementById('form-edit-item');
    let item = {};
    try { item = JSON.parse(form.dataset.item || '{}'); } catch(e) {}
    let html = '';

    if (type === 'new') {
        html = `
            <div><label class="cmns-label">เลขพาร์ท (Part No.)</label><input type="text" name="part_number" class="cmns-input" value="${esc(item.part_number)}" placeholder="เช่น 661-123"></div>
            <div><label class="cmns-label">รุ่นรองรับ (Model)</label><input type="text" name="compatible_models" class="cmns-input" value="${esc(item.compatible_models)}" placeholder="เช่น A2337"></div>
            <div><label class="cmns-label" style="color:#f59e0b;">เตือนของหมด (Min Qty)</label><input type="number" name="min_qty" class="cmns-input" value="${item.min_qty || 1}" min="0"></div>
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
        html = `
            <div><label class="cmns-label">Asset Tag</label><input type="text" name="asset_tag" class="cmns-input" value="${esc(item.asset_tag)}"></div>
            <div><label class="cmns-label">Serial Number</label><input type="text" name="serial_number" class="cmns-input" value="${esc(item.serial_number)}"></div>
            <div style="grid-column:span 2;"><label class="cmns-label">สภาพเครื่อง (Condition)</label><input type="text" name="condition_note" class="cmns-input" value="${esc(item.condition_note)}" placeholder="ตำหนิต่างๆ..."></div>
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
}
</script>

<?php include '../templates/footer_admin.php'; ?>