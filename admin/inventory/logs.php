<?php
/********************************************************************
 * admin/inventory/logs.php  –  Inventory Movement Log
 * Source 1: inventory_lots       → action IN  (รับเข้า)
 * Source 2: parts_requisitions   → action OUT (เบิกออก)
 ********************************************************************/

session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

/* ── Filters ── */
$filter_action  = $_GET['action']  ?? 'all';   // all | IN | OUT
$filter_type    = $_GET['type']    ?? 'all';   // all | new | used | machine | sale
$filter_cat     = (int)($_GET['cat'] ?? 0);
$filter_date_from = $_GET['from']  ?? date('Y-m-01');
$filter_date_to   = $_GET['to']    ?? date('Y-m-d');
$search           = trim($_GET['q'] ?? '');
$per_page = 30;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

/* ── Root categories for dropdown ── */
$cats = $pdo->query("SELECT id, name FROM parts_categories ORDER BY parent_id IS NULL DESC, name")->fetchAll(PDO::FETCH_ASSOC);

/* ── Build UNION ── */
$where_common = [];
$params       = [];

if ($filter_date_from) { $where_common[] = "log_date >= ?"; $params[] = $filter_date_from . ' 00:00:00'; }
if ($filter_date_to)   { $where_common[] = "log_date <= ?"; $params[] = $filter_date_to   . ' 23:59:59'; }
if ($search !== '') {
    $where_common[] = "(item_name LIKE ? OR sku LIKE ? OR ref LIKE ? OR extra_info LIKE ?)";
    $s = "%$search%"; $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($filter_type !== 'all') { $where_common[] = "inv_type = ?"; $params[] = $filter_type; }
if ($filter_cat  >  0)      { $where_common[] = "cat_id   = ?"; $params[] = $filter_cat;  }

$action_sql = ['IN'  => "AND action = 'IN'", 'OUT' => "AND action = 'OUT'"][$filter_action] ?? '';

$where_sql = !empty($where_common) ? 'WHERE ' . implode(' AND ', $where_common) : '';

$c = 'COLLATE utf8mb4_unicode_ci';

/* IN: inventory_lots */
$sql_in = "
    SELECT
        il.id                                                       AS log_id,
        'IN'                                                        AS action,
        il.created_at                                               AS log_date,
        i.id                                                        AS inv_id,
        CONVERT(i.name        USING utf8mb4) $c                    AS item_name,
        CONVERT(i.sku         USING utf8mb4) $c                    AS sku,
        CONVERT(i.type        USING utf8mb4) $c                    AS inv_type,
        i.category_id                                               AS cat_id,
        CONVERT(pc.name       USING utf8mb4) $c                    AS cat_name,
        CONVERT(il.lot_number USING utf8mb4) $c                    AS ref,
        il.qty_received                                             AS qty_in,
        0                                                           AS qty_out,
        il.qty_remaining                                            AS qty_after,
        il.cost_price                                               AS unit_cost,
        CONVERT(il.supplier_name USING utf8mb4) $c                 AS extra_info,
        il.warranty_end                                             AS warranty_end,
        NULL                                                        AS tracking_ticket,
        NULL                                                        AS tracking_id,
        NULL                                                        AS admin_name,
        NULL                                                        AS remarks
    FROM inventory_lots il
    JOIN inventory i         ON il.inventory_id = i.id
    JOIN parts_categories pc ON i.category_id   = pc.id
";

/* OUT: parts_requisitions */
$sql_out = "
    SELECT
        pr.id                                                       AS log_id,
        'OUT'                                                       AS action,
        pr.created_at                                               AS log_date,
        i.id                                                        AS inv_id,
        CONVERT(pr.item_name     USING utf8mb4) $c                 AS item_name,
        CONVERT(pr.item_sku      USING utf8mb4) $c                 AS sku,
        CONVERT(i.type           USING utf8mb4) $c                 AS inv_type,
        i.category_id                                               AS cat_id,
        CONVERT(pc.name          USING utf8mb4) $c                 AS cat_name,
        CONVERT(pr.ticket_number USING utf8mb4) $c                 AS ref,
        0                                                           AS qty_in,
        pr.qty                                                      AS qty_out,
        NULL                                                        AS qty_after,
        pr.cost_price                                               AS unit_cost,
        CONVERT(pr.admin_name    USING utf8mb4) $c                 AS extra_info,
        NULL                                                        AS warranty_end,
        CONVERT(pr.ticket_number USING utf8mb4) $c                 AS tracking_ticket,
        pr.tracking_id                                              AS tracking_id,
        CONVERT(pr.admin_name    USING utf8mb4) $c                 AS admin_name,
        CONVERT(pr.remarks       USING utf8mb4) $c                 AS remarks
    FROM parts_requisitions pr
    JOIN inventory i         ON pr.inventory_id = i.id
    JOIN parts_categories pc ON i.category_id   = pc.id
";

$union_sql = "($sql_in) UNION ALL ($sql_out)";

$full_sql = "
    SELECT * FROM ($union_sql) AS combined
    $where_sql
    $action_sql
    ORDER BY log_date DESC
";

/* Count */
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM ($full_sql) AS cnt");
$stmt_count->execute($params);
$total_rows  = (int)$stmt_count->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));

/* Fetch page */
$stmt = $pdo->prepare($full_sql . " LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Summary stats ── */
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*)                 FROM inventory_lots      WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())) AS lots_month,
        (SELECT COALESCE(SUM(qty_received),0) FROM inventory_lots WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())) AS qty_in_month,
        (SELECT COUNT(*)                 FROM parts_requisitions  WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())) AS req_month,
        (SELECT COALESCE(SUM(qty),0)     FROM parts_requisitions  WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())) AS qty_out_month,
        (SELECT COALESCE(SUM(qty_remaining),0)               FROM inventory_lots) AS total_stock,
        (SELECT COALESCE(SUM(qty_remaining * cost_price),0)  FROM inventory_lots) AS stock_value,
        (SELECT COUNT(DISTINCT tracking_id) FROM parts_requisitions WHERE tracking_id IS NOT NULL) AS linked_jobs
")->fetch(PDO::FETCH_ASSOC);

$pageTitle = "Stock Logs";
include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-view.css?v=<?= time() ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= time() ?>">

<div class="cmns-wrapper log-wrapper">

    <!-- Back -->
    <div style="margin-bottom:16px;">
        <a href="index.php" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> INVENTORY
        </a>
    </div>

    <!-- Header -->
    <div class="cmns-header-bar" style="margin-bottom:24px;">
        <div>
            <h1 class="cmns-page-title">
                <span class="material-symbols-rounded" style="font-size:30px;">receipt_long</span>
                STOCK LOGS
            </h1>
            <p style="color:var(--text-muted); margin-top:4px; font-size:13px;">
                ประวัติการเคลื่อนไหวสต็อก · แสดง <b><?= number_format($total_rows) ?></b> รายการ
            </p>
        </div>
        <div class="cmns-action-buttons">
            <button onclick="openAddModal()" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">add_circle</span> เติมสต็อก
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="log-stats" style="margin-bottom:28px;">
        <div class="log-stat-card stat-accent-green">
            <span class="stat-label">รับเข้าเดือนนี้</span>
            <span class="stat-value">+<?= number_format($stats['qty_in_month']) ?></span>
            <span class="stat-sub"><?= number_format($stats['lots_month']) ?> lot</span>
        </div>
        <div class="log-stat-card stat-accent-red">
            <span class="stat-label">เบิกออกเดือนนี้</span>
            <span class="stat-value">-<?= number_format($stats['qty_out_month']) ?></span>
            <span class="stat-sub"><?= number_format($stats['req_month']) ?> รายการ</span>
        </div>
        <div class="log-stat-card stat-accent-blue">
            <span class="stat-label">สต็อกรวม</span>
            <span class="stat-value"><?= number_format($stats['total_stock']) ?></span>
            <span class="stat-sub">ชิ้น (qty remaining)</span>
        </div>
        <div class="log-stat-card stat-accent-purple">
            <span class="stat-label">มูลค่าสต็อก</span>
            <span class="stat-value">฿<?= $stats['stock_value'] >= 1000 ? number_format($stats['stock_value']/1000,1).'K' : number_format($stats['stock_value']) ?></span>
            <span class="stat-sub">ราคาทุนรวม</span>
        </div>
        <div class="log-stat-card stat-accent-yellow">
            <span class="stat-label">งานซ่อมที่เบิก</span>
            <span class="stat-value"><?= number_format($stats['linked_jobs']) ?></span>
            <span class="stat-sub">jobs linked</span>
        </div>
    </div>

    <!-- Filter -->
    <form method="GET" action="logs.php">
        <div class="log-filter-bar">

            <div class="log-filter-group" style="flex:1; min-width:220px;">
                <label>ค้นหา</label>
                <div class="log-search-wrap">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ชื่อสินค้า, SKU, Lot, Supplier, Job No...">
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
                    <option value="all"     <?= $filter_type==='all'?'selected':'' ?>>ทั้งหมด</option>
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

            <input type="hidden" name="action" value="<?= htmlspecialchars($filter_action) ?>">

            <button type="submit" class="btn-filter">
                <span class="material-symbols-rounded" style="font-size:17px;">filter_list</span> กรอง
            </button>
            <?php if ($search || $filter_type!=='all' || $filter_cat || $filter_action!=='all'): ?>
                <a href="logs.php?from=<?= $filter_date_from ?>&to=<?= $filter_date_to ?>" class="btn-reset">รีเซ็ต</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Action Tabs -->
    <?php $tab_base = "logs.php?q=".urlencode($search)."&from=$filter_date_from&to=$filter_date_to&type=$filter_type&cat=$filter_cat"; ?>
    <div class="log-tabs">
        <a href="<?= $tab_base ?>&action=all" class="log-tab <?= $filter_action==='all'?'active-all':'' ?>">
            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">list</span>
            ทั้งหมด (<?= number_format($total_rows) ?>)
        </a>
        <a href="<?= $tab_base ?>&action=IN" class="log-tab <?= $filter_action==='IN'?'active-in':'' ?>">
            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">arrow_downward</span>
            รับเข้า (IN)
        </a>
        <a href="<?= $tab_base ?>&action=OUT" class="log-tab <?= $filter_action==='OUT'?'active-out':'' ?>">
            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">output</span>
            เบิกออก (OUT)
        </a>
    </div>

    <!-- Table -->
    <div class="log-card">
        <div style="overflow-x:auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:120px;">วันที่/เวลา</th>
                        <th style="width:80px; text-align:center;">ACTION</th>
                        <th>ชื่อสินค้า / SKU</th>
                        <th style="width:110px;">หมวดหมู่</th>
                        <th style="width:75px; text-align:center;">TYPE</th>
                        <th style="width:140px;">Lot / Job Ref.</th>
                        <th style="width:70px; text-align:center;">QTY</th>
                        <th style="width:95px; text-align:right;">ราคาทุน</th>
                        <th>Supplier / ผู้เบิก / Job</th>
                        <th style="width:100px; text-align:center;">ประกัน/หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="10">
                            <div class="empty-state">
                                <span class="material-symbols-rounded">receipt_long</span>
                                <p style="font-size:15px; font-weight:600; margin:0 0 6px;">ไม่พบรายการ</p>
                                <p style="font-size:13px; margin:0;">ลองเปลี่ยนตัวกรองหรือช่วงวันที่</p>
                            </div>
                        </td></tr>
                    <?php else: foreach ($logs as $log):
                        $action  = $log['action'];
                        $is_in   = ($action === 'IN');
                        $log_dt  = new DateTime($log['log_date']);
                    ?>
                        <tr>
                            <!-- Date -->
                            <td style="white-space:nowrap;">
                                <div style="font-weight:600; font-size:13px;"><?= $log_dt->format('d/m/Y') ?></div>
                                <div style="font-size:11px; color:var(--text-muted);"><?= $log_dt->format('H:i') ?></div>
                            </td>

                            <!-- Action badge -->
                            <?php
                                $remarks_raw = $log['remarks'] ?? '';
                                $is_sale_transfer = !$is_in && str_starts_with($remarks_raw, 'TRANSFER_TO_SALE');
                                $is_converted     = !$is_in && str_starts_with($remarks_raw, 'CONVERTED_TO_SALE');
                                $is_sold          = !$is_in && $remarks_raw === 'SOLD';
                            ?>
                            <td style="text-align:center;">
                                <?php if ($is_sale_transfer || $is_converted): ?>
                                    <span class="action-badge" style="background:rgba(239,68,68,.1); color:#ef4444; border:1px solid rgba(239,68,68,.25);">
                                        <span class="material-symbols-rounded" style="font-size:12px;">sell</span>
                                        → SALE
                                    </span>
                                <?php elseif ($is_sold): ?>
                                    <span class="action-badge" style="background:rgba(16,185,129,.1); color:#10b981; border:1px solid rgba(16,185,129,.25);">
                                        <span class="material-symbols-rounded" style="font-size:12px;">payments</span>
                                        SOLD
                                    </span>
                                <?php else: ?>
                                    <span class="action-badge <?= $is_in ? 'badge-in' : 'badge-out' ?>">
                                        <span class="material-symbols-rounded" style="font-size:12px;"><?= $is_in ? 'arrow_downward' : 'output' ?></span>
                                        <?= $action ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Item name -->
                            <td>
                                <div style="font-weight:600; font-size:13px; color:var(--text-main);">
                                    <?= htmlspecialchars($log['item_name'] ?: '—') ?>
                                </div>
                                <?php if ($log['sku']): ?>
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                        <code style="background:var(--bg-surface-alt); padding:1px 5px; border-radius:4px;"><?= htmlspecialchars($log['sku']) ?></code>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Category -->
                            <td style="font-size:12px; color:var(--text-muted);">
                                <?= htmlspecialchars($log['cat_name'] ?: '—') ?>
                            </td>

                            <!-- Type chip -->
                            <td style="text-align:center;">
                                <?php if ($log['inv_type']): ?>
                                    <span class="type-chip chip-<?= $log['inv_type'] ?>"><?= strtoupper($log['inv_type']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:11px;">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Lot / Job ref -->
                            <td>
                                <?php if ($is_in && $log['ref']): ?>
                                    <code style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($log['ref']) ?></code>
                                <?php elseif (!$is_in && $log['tracking_ticket']): ?>
                                    <a href="../tracking/edit.php?id=<?= (int)($log['tracking_id'] ?? 0) ?>"
                                       style="font-size:12px; font-weight:700; color:var(--primary); text-decoration:none;"
                                       title="เปิดงานซ่อม">
                                        <span class="material-symbols-rounded" style="font-size:13px; vertical-align:-2px;">build_circle</span>
                                        <?= htmlspecialchars($log['tracking_ticket']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- QTY -->
                            <td style="text-align:center;">
                                <?php if ($is_in): ?>
                                    <span class="qty-in">+<?= number_format($log['qty_in']) ?></span>
                                <?php else: ?>
                                    <span class="qty-out">-<?= number_format($log['qty_out']) ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Cost -->
                            <td style="text-align:right;">
                                <?php if ($log['unit_cost'] > 0): ?>
                                    <span style="font-size:13px; font-weight:600; color:var(--text-main);">
                                        ฿<?= number_format($log['unit_cost']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Supplier / Admin / Info -->
                            <td style="max-width:200px;">
                                <div style="font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                                     title="<?= htmlspecialchars(($log['extra_info'] ?? '') . ($log['remarks'] ? ' · ' . $log['remarks'] : '')) ?>">
                                    <?php if ($is_in): ?>
                                        <?= htmlspecialchars($log['extra_info'] ?: '—') ?>
                                    <?php else: ?>
                                        <?php if ($log['admin_name']): ?>
                                            <span class="material-symbols-rounded" style="font-size:12px; vertical-align:-2px;">person</span>
                                            <?= htmlspecialchars($log['admin_name']) ?>
                                        <?php endif; ?>
                                        <?php if ($remarks_raw):
                                            if ($is_sale_transfer) {
                                                $sale_id = explode(':', $remarks_raw)[1] ?? '';
                                                echo '<span style="color:#ef4444; font-weight:700;"> · → SALE' . ($sale_id ? " #{$sale_id}" : '') . '</span>';
                                            } elseif ($is_converted) {
                                                $src_type = strtoupper(explode(':', $remarks_raw)[1] ?? '');
                                                echo "<span style='color:#ef4444; font-weight:700;'> · {$src_type} → SALE</span>";
                                            } elseif ($is_sold) {
                                                echo '<span style="color:#10b981; font-weight:700;"> · ขายแล้ว</span>';
                                            } else {
                                                echo '<span style="opacity:.6;"> · ' . htmlspecialchars($remarks_raw) . '</span>';
                                            }
                                        endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Warranty / note -->
                            <td style="text-align:center;">
                                <?php if ($is_in && $log['warranty_end']):
                                    $days_left = (new DateTime())->diff(new DateTime($log['warranty_end']))->days;
                                    $is_past   = new DateTime() > new DateTime($log['warranty_end']);
                                    $wcolor    = $is_past ? '#ef4444' : ($days_left < 30 ? '#f59e0b' : 'var(--text-muted)');
                                ?>
                                    <span style="font-size:11px; font-weight:600; color:<?= $wcolor ?>;">
                                        <?= (new DateTime($log['warranty_end']))->format('d/m/y') ?>
                                    </span>
                                    <?php if ($is_past): ?>
                                        <div style="font-size:10px; color:#ef4444;">หมดแล้ว</div>
                                    <?php elseif ($days_left < 30): ?>
                                        <div style="font-size:10px; color:#f59e0b;"><?= $days_left ?>d</div>
                                    <?php endif; ?>
                                <?php elseif (!$is_in && $log['qty_after'] !== null): ?>
                                    <span style="font-size:11px; color:var(--text-muted);">คงเหลือ <?= number_format($log['qty_after']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1):
            $pag_base = "logs.php?q=".urlencode($search)."&from=$filter_date_from&to=$filter_date_to&type=$filter_type&cat=$filter_cat&action=$filter_action";
        ?>
        <div class="log-pagination">
            <div>
                แสดง <b><?= number_format(min($total_rows, $offset+1)) ?>–<?= number_format(min($total_rows, $offset+$per_page)) ?></b>
                จาก <b><?= number_format($total_rows) ?></b> รายการ
            </div>
            <div class="page-btns">
                <a href="<?= $pag_base ?>&page=<?= max(1,$page-1) ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">
                    <span class="material-symbols-rounded" style="font-size:16px;">chevron_left</span>
                </a>
                <?php
                $pstart = max(1, $page-2); $pend = min($total_pages, $pstart+4);
                for ($p = $pstart; $p <= $pend; $p++):
                ?>
                    <a href="<?= $pag_base ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a href="<?= $pag_base ?>&page=<?= min($total_pages,$page+1) ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>">
                    <span class="material-symbols-rounded" style="font-size:16px;">chevron_right</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- .cmns-wrapper -->

<?php
$current_type = 'new'; // default mode ใน modal
?>
<link rel="stylesheet" href="../templates/assets/css/modal.css?v=<?= time() ?>">
<?php include 'modal_add.php'; ?>

<?php include '../templates/footer_admin.php'; ?>
