<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// ── Filters ──────────────────────────────────────────────────
$filter_action  = $_GET['action']   ?? 'all';   // all | IN | OUT | ADJUST
$filter_type    = $_GET['type']     ?? 'all';   // all | new | used | machine | sale
$filter_cat     = (int)($_GET['cat'] ?? 0);
$filter_date_from = $_GET['from']   ?? date('Y-m-01');
$filter_date_to   = $_GET['to']     ?? date('Y-m-d');
$search         = trim($_GET['q']   ?? '');
$per_page       = 30;
$page           = max(1, (int)($_GET['page'] ?? 1));
$offset         = ($page - 1) * $per_page;

// ── Root categories for filter dropdown ──────────────────────
$cats = $pdo->query("SELECT id, name FROM parts_categories ORDER BY parent_id IS NULL DESC, name")->fetchAll(PDO::FETCH_ASSOC);

// ── Build unified log query ───────────────────────────────────
// Source 1: inventory_lots  → action = 'IN'
// Source 2: parts_used_log  → action = OUT/ADJUST/MOVE/INSERT

$where_common = [];
$params       = [];

if ($filter_date_from) { $where_common[] = "log_date >= ?"; $params[] = $filter_date_from . ' 00:00:00'; }
if ($filter_date_to)   { $where_common[] = "log_date <= ?"; $params[] = $filter_date_to   . ' 23:59:59'; }
if ($search !== '') {
    $where_common[] = "(item_name LIKE ? OR sku LIKE ? OR lot_ref LIKE ? OR supplier_note LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}

// กรอง action
$action_filter_sql = '';
if ($filter_action === 'IN')     $action_filter_sql = "AND action = 'IN'";
elseif ($filter_action === 'OUT') $action_filter_sql = "AND action IN ('CONSUME','OUT')";
elseif ($filter_action === 'ADJUST') $action_filter_sql = "AND action IN ('ADJUST','MOVE','INSERT')";

// กรอง type
$type_filter_sql = '';
if ($filter_type !== 'all') { $type_filter_sql = "AND inv_type = ?"; $params[] = $filter_type; }

// กรอง category
$cat_filter_sql = '';
if ($filter_cat > 0) { $cat_filter_sql = "AND cat_id = ?"; $params[] = $filter_cat; }

$where_sql = !empty($where_common) ? 'WHERE ' . implode(' AND ', $where_common) : '';

// UNION query
$union_sql = "
    (
        SELECT
            il.id             AS log_id,
            'IN'              AS action,
            il.created_at     AS log_date,
            i.id              AS inv_id,
            i.name            AS item_name,
            i.sku             AS sku,
            i.type            AS inv_type,
            i.category_id     AS cat_id,
            pc.name           AS cat_name,
            il.lot_number     AS lot_ref,
            il.qty_received   AS qty_in,
            0                 AS qty_out,
            il.qty_remaining  AS qty_after,
            il.cost_price     AS unit_cost,
            il.supplier_name  AS supplier_note,
            il.warranty_end   AS warranty_end,
            NULL              AS remarks,
            'lot'             AS source
        FROM inventory_lots il
        JOIN inventory i ON il.inventory_id = i.id
        JOIN parts_categories pc ON i.category_id = pc.id
    )
    UNION ALL
    (
        SELECT
            ul.id                                              AS log_id,
            CONVERT(ul.action    USING utf8mb4)               AS action,
            ul.created_at                                      AS log_date,
            NULL                                               AS inv_id,
            CONVERT(ul.part_name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS item_name,
            CONVERT(ul.part_code USING utf8mb4) COLLATE utf8mb4_unicode_ci AS sku,
            NULL                                               AS inv_type,
            NULL                                               AS cat_id,
            CONVERT(ul.category  USING utf8mb4) COLLATE utf8mb4_unicode_ci AS cat_name,
            CONVERT(ul.part_number USING utf8mb4) COLLATE utf8mb4_unicode_ci AS lot_ref,
            0                                                   AS qty_in,
            1                                                   AS qty_out,
            NULL                                                AS qty_after,
            NULL                                                AS unit_cost,
            CONVERT(ul.location USING utf8mb4) COLLATE utf8mb4_unicode_ci AS supplier_note,
            NULL                                                AS warranty_end,
            CONVERT(ul.remarks  USING utf8mb4) COLLATE utf8mb4_unicode_ci AS remarks,
            'used_log'                                          AS source
        FROM parts_used_log ul
    )
";

$full_sql = "
    SELECT * FROM ($union_sql) AS combined
    $where_sql
    $action_filter_sql
    $type_filter_sql
    $cat_filter_sql
    ORDER BY log_date DESC
";

// Count total
$count_sql = "SELECT COUNT(*) FROM ($full_sql) AS cnt";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

// Fetch page
$page_sql = $full_sql . " LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($page_sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Summary stats (ไม่กรองวันที่ เอาแค่เดือนนี้) ─────────────
$stmt_stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM inventory_lots WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())) AS lots_this_month,
        (SELECT COALESCE(SUM(qty_received),0) FROM inventory_lots WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())) AS qty_in_month,
        (SELECT COUNT(*) FROM parts_used_log WHERE action='CONSUME' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())) AS qty_out_month,
        (SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_lots) AS total_stock,
        (SELECT COALESCE(SUM(qty_remaining * cost_price),0) FROM inventory_lots) AS stock_value
");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$pageTitle = "Stock Logs";
include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-view.css?v=<?= time() ?>">

<style>
.log-wrapper { padding: 0 4px; }

/* ── Summary Cards ── */
.log-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 24px; }
.log-stat-card {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.log-stat-card .stat-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); }
.log-stat-card .stat-value { font-size: 22px; font-weight: 800; color: var(--text-main); line-height: 1; }
.log-stat-card .stat-sub   { font-size: 11px; color: var(--text-muted); }
.stat-accent-green  { border-left: 3px solid #10b981; }
.stat-accent-red    { border-left: 3px solid #ef4444; }
.stat-accent-blue   { border-left: 3px solid var(--primary); }
.stat-accent-purple { border-left: 3px solid #8b5cf6; }
.stat-accent-yellow { border-left: 3px solid #f59e0b; }

/* ── Filter Bar ── */
.log-filter-bar {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
    margin-bottom: 20px;
}
.log-filter-group { display: flex; flex-direction: column; gap: 5px; min-width: 140px; }
.log-filter-group label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: .5px; }
.log-filter-group select,
.log-filter-group input {
    background: var(--bg-surface-alt);
    border: 1px solid var(--border);
    color: var(--text-main);
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-family: 'Sarabun', sans-serif;
    outline: none;
    transition: border-color .2s;
}
.log-filter-group select:focus,
.log-filter-group input:focus { border-color: var(--primary); }
.log-search-wrap { position: relative; flex: 1; min-width: 200px; }
.log-search-wrap .search-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 18px; color: var(--text-muted); pointer-events: none; }
.log-search-wrap input { width: 100%; padding-left: 36px; }
.btn-filter { background: var(--primary); color: #fff; border: none; padding: 9px 18px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; align-self: flex-end; transition: .2s; font-family: 'Sarabun', sans-serif; }
.btn-filter:hover { background: var(--primary-hover); }
.btn-reset { background: transparent; color: var(--text-muted); border: 1px solid var(--border); padding: 9px 14px; border-radius: 8px; font-size: 13px; cursor: pointer; align-self: flex-end; font-family: 'Sarabun', sans-serif; transition: .2s; }
.btn-reset:hover { border-color: var(--text-muted); color: var(--text-main); }

/* ── Action Tabs ── */
.log-tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
.log-tab {
    padding: 7px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;
    text-decoration: none; color: var(--text-muted); background: var(--bg-surface);
    border: 1px solid var(--border); transition: .2s; white-space: nowrap;
}
.log-tab:hover { border-color: var(--primary); color: var(--primary); }
.log-tab.active-all     { background: var(--primary); color: #fff; border-color: var(--primary); }
.log-tab.active-in      { background: #10b981; color: #fff; border-color: #10b981; }
.log-tab.active-out     { background: #ef4444; color: #fff; border-color: #ef4444; }
.log-tab.active-adjust  { background: #8b5cf6; color: #fff; border-color: #8b5cf6; }

/* ── Log Table ── */
.log-card {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
}
.log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.log-table th {
    background: var(--bg-surface-alt);
    padding: 12px 14px;
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.log-table td {
    padding: 11px 14px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    color: var(--text-main);
}
.log-table tr:last-child td { border-bottom: none; }
.log-table tr:hover td { background: var(--bg-surface-alt); }

/* ── Action Badge ── */
.action-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 800; letter-spacing: .5px;
    white-space: nowrap;
}
.badge-in      { background: rgba(16,185,129,.12); color: #10b981; }
.badge-out     { background: rgba(239,68,68,.12);  color: #ef4444; }
.badge-adjust  { background: rgba(139,92,246,.12); color: #8b5cf6; }
.badge-insert  { background: rgba(245,158,11,.12); color: #f59e0b; }
.badge-move    { background: rgba(59,130,246,.12); color: #3b82f6; }

/* ── Type Chip ── */
.type-chip {
    display: inline-block; padding: 2px 8px; border-radius: 4px;
    font-size: 10px; font-weight: 800; text-transform: uppercase;
}
.chip-new     { background: rgba(16,185,129,.1); color: #10b981; }
.chip-used    { background: rgba(245,158,11,.1);  color: #f59e0b; }
.chip-machine { background: rgba(139,92,246,.1);  color: #8b5cf6; }
.chip-sale    { background: rgba(239,68,68,.1);   color: #ef4444; }

/* ── Qty display ── */
.qty-in  { color: #10b981; font-weight: 800; font-size: 14px; }
.qty-out { color: #ef4444; font-weight: 800; font-size: 14px; }
.qty-neutral { color: var(--text-muted); font-size: 13px; }

/* ── Pagination ── */
.log-pagination { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; font-size: 13px; color: var(--text-muted); border-top: 1px solid var(--border); flex-wrap: wrap; gap: 10px; }
.page-btns { display: flex; gap: 6px; }
.page-btn {
    padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border);
    background: var(--bg-surface-alt); color: var(--text-main); font-size: 13px;
    text-decoration: none; font-weight: 600; transition: .2s;
}
.page-btn:hover:not(.disabled) { border-color: var(--primary); color: var(--primary); }
.page-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.page-btn.disabled { opacity: .35; pointer-events: none; }

/* ── Empty state ── */
.empty-state { text-align: center; padding: 80px 20px; color: var(--text-muted); }
.empty-state .material-symbols-rounded { font-size: 64px; opacity: .2; display: block; margin-bottom: 16px; }
</style>

<div class="cmns-wrapper log-wrapper">

    <!-- Header -->
    <div class="cmns-header-bar" style="margin-bottom: 20px;">
        <div>
            <h1 class="cmns-page-title">
                <span class="material-symbols-rounded" style="font-size: 30px;">receipt_long</span>
                STOCK LOGS
            </h1>
            <p style="color:var(--text-muted); margin-top:4px; font-size:13px;">
                ประวัติการเคลื่อนไหวสต็อก · แสดง <?= number_format($total_rows) ?> รายการ
            </p>
        </div>
        <a href="index.php" class="cmns-btn cmns-btn-secondary">
            <span class="material-symbols-rounded">arrow_back</span> INVENTORY
        </a>
    </div>

    <!-- Summary Stats -->
    <div class="log-stats">
        <div class="log-stat-card stat-accent-green">
            <span class="stat-label">รับเข้าเดือนนี้</span>
            <span class="stat-value">+<?= number_format($stats['qty_in_month']) ?></span>
            <span class="stat-sub"><?= number_format($stats['lots_this_month']) ?> lot</span>
        </div>
        <div class="log-stat-card stat-accent-red">
            <span class="stat-label">จ่ายออกเดือนนี้</span>
            <span class="stat-value">-<?= number_format($stats['qty_out_month']) ?></span>
            <span class="stat-sub">รายการ Consume</span>
        </div>
        <div class="log-stat-card stat-accent-blue">
            <span class="stat-label">สต็อกรวมทั้งหมด</span>
            <span class="stat-value"><?= number_format($stats['total_stock']) ?></span>
            <span class="stat-sub">ชิ้น (qty remaining)</span>
        </div>
        <div class="log-stat-card stat-accent-purple">
            <span class="stat-label">มูลค่าสต็อก</span>
            <span class="stat-value">฿<?= number_format($stats['stock_value'] / 1000, 1) ?>K</span>
            <span class="stat-sub">ราคาทุนรวม</span>
        </div>
        <div class="log-stat-card stat-accent-yellow">
            <span class="stat-label">ช่วงเวลา</span>
            <span class="stat-value" style="font-size:15px;"><?= date('d/m', strtotime($filter_date_from)) ?></span>
            <span class="stat-sub">ถึง <?= date('d/m/Y', strtotime($filter_date_to)) ?></span>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="logs.php">
        <div class="log-filter-bar">

            <div class="log-filter-group" style="flex: 1; min-width: 200px;">
                <label>ค้นหา</label>
                <div class="log-search-wrap">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ชื่อสินค้า, SKU, Lot, Supplier...">
                </div>
            </div>

            <div class="log-filter-group">
                <label>จากวันที่</label>
                <input type="date" name="from" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>

            <div class="log-filter-group">
                <label>ถึงวันที่</label>
                <input type="date" name="to" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>

            <div class="log-filter-group">
                <label>ประเภทสินค้า</label>
                <select name="type">
                    <option value="all" <?= $filter_type==='all'?'selected':'' ?>>ทั้งหมด</option>
                    <option value="new"     <?= $filter_type==='new'?'selected':'' ?>>NEW</option>
                    <option value="used"    <?= $filter_type==='used'?'selected':'' ?>>USED</option>
                    <option value="machine" <?= $filter_type==='machine'?'selected':'' ?>>MACHINE</option>
                    <option value="sale"    <?= $filter_type==='sale'?'selected':'' ?>>SALE</option>
                </select>
            </div>

            <div class="log-filter-group">
                <label>หมวดหมู่</label>
                <select name="cat">
                    <option value="0">ทุกหมวด</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filter_cat==$c['id']?'selected':'' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- รักษา action filter ผ่าน hidden input -->
            <input type="hidden" name="action" value="<?= htmlspecialchars($filter_action) ?>">

            <button type="submit" class="btn-filter">
                <span class="material-symbols-rounded" style="font-size:18px;">filter_list</span> กรอง
            </button>
            <a href="logs.php" class="btn-reset">รีเซ็ต</a>
        </div>
    </form>

    <!-- Action Tabs -->
    <?php
    $tab_base = "logs.php?q=" . urlencode($search) . "&from=$filter_date_from&to=$filter_date_to&type=$filter_type&cat=$filter_cat";
    ?>
    <div class="log-tabs">
        <a href="<?= $tab_base ?>&action=all"    class="log-tab <?= $filter_action==='all'?'active-all':'' ?>">
            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">list</span> ทั้งหมด (<?= number_format($total_rows) ?>)
        </a>
        <a href="<?= $tab_base ?>&action=IN"     class="log-tab <?= $filter_action==='IN'?'active-in':'' ?>">
            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">arrow_downward</span> รับเข้า (IN)
        </a>
        <a href="<?= $tab_base ?>&action=OUT"    class="log-tab <?= $filter_action==='OUT'?'active-out':'' ?>">
            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">arrow_upward</span> จ่ายออก (OUT)
        </a>
        <a href="<?= $tab_base ?>&action=ADJUST" class="log-tab <?= $filter_action==='ADJUST'?'active-adjust':'' ?>">
            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">tune</span> ปรับสต็อก
        </a>
    </div>

    <!-- Log Table -->
    <div class="log-card">
        <div style="overflow-x: auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:130px;">วันที่/เวลา</th>
                        <th style="width:80px; text-align:center;">ACTION</th>
                        <th>ชื่อสินค้า / SKU</th>
                        <th style="width:110px;">หมวดหมู่</th>
                        <th style="width:80px; text-align:center;">TYPE</th>
                        <th style="width:130px;">Lot / Ref</th>
                        <th style="width:80px; text-align:center;">QTY</th>
                        <th style="width:90px; text-align:right;">ราคาทุน</th>
                        <th>Supplier / หมายเหตุ</th>
                        <th style="width:100px; text-align:center;">ประกันถึง</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <span class="material-symbols-rounded">inventory_2</span>
                                    <p style="font-size:15px; font-weight:600; margin:0 0 6px 0;">ไม่พบรายการ</p>
                                    <p style="font-size:13px; margin:0;">ลองเปลี่ยนตัวกรองหรือช่วงวันที่</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log):
                            $action = strtoupper($log['action']);
                            $is_in  = ($action === 'IN');
                            $qty_display = $is_in ? $log['qty_in'] : $log['qty_out'];
                            $log_dt = new DateTime($log['log_date']);
                        ?>
                        <tr>
                            <td style="white-space:nowrap;">
                                <div style="font-weight:600; font-size:13px;"><?= $log_dt->format('d/m/Y') ?></div>
                                <div style="font-size:11px; color:var(--text-muted);"><?= $log_dt->format('H:i') ?></div>
                            </td>

                            <td style="text-align:center;">
                                <?php
                                $badge_class = match($action) {
                                    'IN'      => 'badge-in',
                                    'CONSUME' => 'badge-out',
                                    'ADJUST'  => 'badge-adjust',
                                    'INSERT'  => 'badge-insert',
                                    'MOVE'    => 'badge-move',
                                    default   => 'badge-adjust',
                                };
                                $badge_icon = match($action) {
                                    'IN'      => 'arrow_downward',
                                    'CONSUME' => 'arrow_upward',
                                    'ADJUST'  => 'tune',
                                    'INSERT'  => 'add_circle',
                                    'MOVE'    => 'swap_horiz',
                                    default   => 'change_circle',
                                };
                                $badge_label = match($action) {
                                    'IN'      => 'IN',
                                    'CONSUME' => 'OUT',
                                    'ADJUST'  => 'ADJUST',
                                    'INSERT'  => 'INSERT',
                                    'MOVE'    => 'MOVE',
                                    default   => $action,
                                };
                                ?>
                                <span class="action-badge <?= $badge_class ?>">
                                    <span class="material-symbols-rounded" style="font-size:13px;"><?= $badge_icon ?></span>
                                    <?= $badge_label ?>
                                </span>
                            </td>

                            <td>
                                <div style="font-weight:600; font-size:13px; color:var(--text-main);">
                                    <?= htmlspecialchars($log['item_name'] ?: '-') ?>
                                </div>
                                <?php if ($log['sku']): ?>
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                        <code style="background:var(--bg-surface-alt); padding:1px 5px; border-radius:4px;"><?= htmlspecialchars($log['sku']) ?></code>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td style="font-size:12px; color:var(--text-muted);">
                                <?= htmlspecialchars($log['cat_name'] ?: '-') ?>
                            </td>

                            <td style="text-align:center;">
                                <?php if ($log['inv_type']): ?>
                                    <span class="type-chip chip-<?= $log['inv_type'] ?>"><?= strtoupper($log['inv_type']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:11px;">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($log['lot_ref']): ?>
                                    <code style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($log['lot_ref']) ?></code>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>

                            <td style="text-align:center;">
                                <?php if ($is_in): ?>
                                    <span class="qty-in">+<?= number_format($qty_display) ?></span>
                                <?php elseif ($qty_display > 0): ?>
                                    <span class="qty-out">-<?= number_format($qty_display) ?></span>
                                <?php else: ?>
                                    <span class="qty-neutral">—</span>
                                <?php endif; ?>
                            </td>

                            <td style="text-align:right;">
                                <?php if ($log['unit_cost'] > 0): ?>
                                    <span style="font-size:13px; font-weight:600; color:var(--text-main);">
                                        ฿<?= number_format($log['unit_cost']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>

                            <td style="max-width:200px;">
                                <div style="font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($log['supplier_note'] . ($log['remarks'] ? ' · ' . $log['remarks'] : '')) ?>">
                                    <?= htmlspecialchars($log['supplier_note'] ?: '') ?>
                                    <?php if ($log['remarks']): ?>
                                        <span style="opacity:.6;"> · <?= htmlspecialchars($log['remarks']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td style="text-align:center;">
                                <?php if ($log['warranty_end']):
                                    $days_left = (new DateTime())->diff(new DateTime($log['warranty_end']))->days;
                                    $is_past   = new DateTime() > new DateTime($log['warranty_end']);
                                    $color = $is_past ? '#ef4444' : ($days_left < 30 ? '#f59e0b' : 'var(--text-muted)');
                                ?>
                                    <span style="font-size:11px; font-weight:600; color:<?= $color ?>;">
                                        <?= (new DateTime($log['warranty_end']))->format('d/m/y') ?>
                                    </span>
                                    <?php if ($is_past): ?>
                                        <div style="font-size:10px; color:#ef4444;">หมดแล้ว</div>
                                    <?php elseif ($days_left < 30): ?>
                                        <div style="font-size:10px; color:#f59e0b;"><?= $days_left ?>d</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1):
            $pag_base = "logs.php?q=" . urlencode($search) . "&from=$filter_date_from&to=$filter_date_to&type=$filter_type&cat=$filter_cat&action=$filter_action";
        ?>
        <div class="log-pagination">
            <div>แสดง <b><?= number_format(min($total_rows, $offset + 1)) ?>–<?= number_format(min($total_rows, $offset + $per_page)) ?></b> จาก <b><?= number_format($total_rows) ?></b> รายการ</div>
            <div class="page-btns">
                <a href="<?= $pag_base ?>&page=<?= max(1, $page - 1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <span class="material-symbols-rounded" style="font-size:16px; vertical-align:middle;">chevron_left</span>
                </a>

                <?php
                $start = max(1, $page - 2);
                $end   = min($total_pages, $start + 4);
                for ($p = $start; $p <= $end; $p++):
                ?>
                    <a href="<?= $pag_base ?>&page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>

                <a href="<?= $pag_base ?>&page=<?= min($total_pages, $page + 1) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <span class="material-symbols-rounded" style="font-size:16px; vertical-align:middle;">chevron_right</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include '../templates/footer_admin.php'; ?>
