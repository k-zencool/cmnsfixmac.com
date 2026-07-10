<?php
/********************************************************************
 * admin/tracking/history.php  –  Repair Job Change History
 ********************************************************************/

session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "Repair History";

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k, $d = null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

function get_pager(): array {
    $per  = max(5, min(200, (int)getv('per', 20)));
    $page = max(1, (int)getv('page', 1));
    return [$per, $page, ($page - 1) * $per];
}
function page_url(int $i): string {
    $q = $_GET; $q['page'] = max(1, $i);
    return '?' . http_build_query($q);
}

function th_date_label($strDate) {
    if (!$strDate) return '-';
    $ts     = strtotime($strDate);
    $months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    return date("j", $ts) . " " . $months[(int)date("n", $ts)] . " " . (date("Y", $ts) + 543);
}

$statusMap = [
    'QS'  => ['label' => 'รอเช็คราคา',          'class' => 'st-amber'],
    'WC'  => ['label' => 'รอคอนเฟิร์ม',         'class' => 'st-blue'],
    'OK'  => ['label' => 'กำลังซ่อม',           'class' => 'st-purple'],
    'RW'  => ['label' => 'งานแก้ / เคลม',       'class' => 'st-red'],
    'FN'  => ['label' => 'ซ่อมเสร็จ (รอรับ)',   'class' => 'st-green'],
    'NCF' => ['label' => 'ติดต่อไม่ได้ (เสร็จ)', 'class' => 'st-gray'],
    'NCS' => ['label' => 'ติดต่อไม่ได้ (เสนอ)',  'class' => 'st-gray'],
    'XX'  => ['label' => 'ยกเลิก (รอรับคืน)',   'class' => 'st-red'],
    'DV'  => ['label' => 'ส่งมอบแล้ว',          'class' => 'st-dark'],
    'RT'  => ['label' => 'ยกเลิก (คืนแล้ว)',    'class' => 'st-dark'],
];

/* ── Filters ── */
$q     = getv('q', '');
$dFrom = getv('date_from', '');
$dTo   = getv('date_to', '');
$statusFilter = isset($_GET['status']) ? (array)$_GET['status'] : [];

$where  = ['1=1'];
$params = [];

if ($q) {
    $where[]      = "(t.ticket_number LIKE :q OR t.customer_name LIKE :q OR t.customer_phone LIKE :q OR t.device_model LIKE :q)";
    $params[':q'] = "%$q%";
}
if (!empty($statusFilter)) {
    $in = [];
    foreach ($statusFilter as $i => $s) { $k = ":s{$i}"; $in[] = $k; $params[$k] = $s; }
    $where[] = "h.status_new IN (" . implode(',', $in) . ")";
}
if ($dFrom) { $where[] = "DATE(h.changed_at) >= :df"; $params[':df'] = $dFrom; }
if ($dTo)   { $where[] = "DATE(h.changed_at) <= :dt"; $params[':dt'] = $dTo; }

$whereSql = "WHERE " . implode(" AND ", $where);

[$per, $page, $offset] = get_pager();

// Count
$stmtCnt = $pdo->prepare("
    SELECT COUNT(*)
    FROM tracking_history h
    JOIN tracking t ON h.tracking_id = t.id
    $whereSql
");
$stmtCnt->execute($params);
$total = (int)($stmtCnt->fetchColumn() ?: 0);

$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $per; }

// Fetch
$stmt = $pdo->prepare("
    SELECT
        h.*,
        t.ticket_number, t.customer_name, t.customer_phone,
        t.device_type, t.device_model, t.device_series, t.status AS current_status
    FROM tracking_history h
    JOIN tracking t ON h.tracking_id = t.id
    $whereSql
    ORDER BY h.changed_at DESC
    LIMIT :lim OFFSET :off
");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $per, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalCount = $total;

/* ── Stats (top cards) ── */
$hStats = ['total' => 0, 'today_cnt' => 0, 'week_cnt' => 0, 'st_cnt' => 0];
try {
    $hStats = $pdo->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(DATE(changed_at) = CURDATE()), 0)              AS today_cnt,
            COALESCE(SUM(changed_at >= NOW() - INTERVAL 7 DAY), 0)      AS week_cnt,
            COALESCE(SUM(status_old <> status_new), 0)                  AS st_cnt
        FROM tracking_history
    ")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* cards only — ignore */ }

/* ── Labels for diff fields ── */
$fieldLabels = [
    'status'           => 'สถานะ',
    'customer_name'    => 'ชื่อลูกค้า',
    'customer_phone'   => 'เบอร์โทร',
    'device_type'      => 'ประเภทเครื่อง',
    'device_model'     => 'Model',
    'device_series'    => 'Series',
    'serial_number'    => 'S/N',
    'device_password'  => 'Password',
    'estimated_cost'   => 'ราคาประเมิน',
    'appointment_date' => 'วันนัดหมาย',
    'pickup_date'      => 'วันรับเครื่อง',
    'technician_note'  => 'หมายเหตุช่าง',
    'problem_details'  => 'อาการเสีย',
    'accessories'      => 'อุปกรณ์',
];

require_once __DIR__ . '/../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/tracking-index.css?v=<?= time() ?>">

<style>
/* ── Diff chips ── */
.diff-list { display:flex; flex-wrap:wrap; gap:5px; }
.diff-chip {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 8px; border-radius:20px; font-size:11px; font-weight:700;
    border:1px solid transparent; white-space:nowrap;
}
.diff-chip.status-chip  { background:rgba(37,99,235,.1);  color:var(--primary); border-color:rgba(37,99,235,.2); }
.diff-chip.cost-chip    { background:rgba(16,185,129,.1); color:#059669; border-color:rgba(16,185,129,.2); }
.diff-chip.date-chip    { background:rgba(139,92,246,.1); color:#7c3aed; border-color:rgba(139,92,246,.2); }
.diff-chip.text-chip    { background:var(--bg-surface-alt); color:var(--text-muted); border-color:var(--border); }
.diff-arrow { opacity:.5; font-size:10px; }

/* ── Status mini badge ── */
.st-mini {
    display:inline-flex; padding:2px 7px; border-radius:10px;
    font-size:10px; font-weight:800; border:1px solid transparent;
}
.st-mini.st-amber  { background:rgba(245,158,11,.15); color:#b45309; border-color:rgba(245,158,11,.3); }
.st-mini.st-blue   { background:rgba(37,99,235,.1);   color:var(--primary); border-color:rgba(37,99,235,.25); }
.st-mini.st-purple { background:rgba(139,92,246,.1);  color:#7c3aed; border-color:rgba(139,92,246,.25); }
.st-mini.st-red    { background:rgba(239,68,68,.1);   color:#dc2626; border-color:rgba(239,68,68,.25); }
.st-mini.st-green  { background:rgba(16,185,129,.1);  color:#059669; border-color:rgba(16,185,129,.25); }
.st-mini.st-dark,
.st-mini.st-gray   { background:var(--bg-surface-alt); color:var(--text-muted); border-color:var(--border); }
</style>

<div class="cmns-wrapper">

    <!-- ── Back ── -->
    <div style="margin-bottom:16px;">
        <a href="index.php" class="cmns-back-link">
            <span class="material-symbols-rounded">arrow_back</span> TRACKING
        </a>
    </div>

    <!-- ── Header ── -->
    <div class="cmns-header-bar" style="margin-bottom:24px;">
        <div>
            <h1 class="cmns-page-title" style="color:var(--primary);">
                <span class="material-symbols-rounded" style="font-size:30px;">manage_history</span>
                REPAIR HISTORY
            </h1>
            <p style="color:var(--text-muted); margin-top:5px; font-size:13px;">
                บันทึกการเปลี่ยนแปลงทุกครั้ง · แสดง <b><?= number_format($totalCount) ?></b> รายการ
            </p>
        </div>
        <div class="cmns-action-buttons">
            <a href="create.php" class="cmns-btn cmns-btn-primary" onclick="showLoader()">
                <span class="material-symbols-rounded">add_circle</span> เปิดงานซ่อม
            </a>
        </div>
    </div>

    <!-- ── Stat Cards (shop-style, same as tracking index) ── -->
    <div class="trk-stats" style="margin-bottom:24px;">
        <a href="history.php?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>" class="stat-card">
            <div class="stat-icon" style="background:#eff6ff;"><span class="material-symbols-rounded" style="color:#3b82f6;">edit_note</span></div>
            <div><div class="stat-val"><?= number_format($hStats['today_cnt']) ?></div><div class="stat-lbl">แก้ไขวันนี้</div></div>
        </a>
        <a href="history.php?date_from=<?= date('Y-m-d', strtotime('-6 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4;"><span class="material-symbols-rounded" style="color:#10b981;">date_range</span></div>
            <div><div class="stat-val"><?= number_format($hStats['week_cnt']) ?></div><div class="stat-lbl">7 วันล่าสุด</div></div>
        </a>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f5f3ff;"><span class="material-symbols-rounded" style="color:#8b5cf6;">flag</span></div>
            <div><div class="stat-val"><?= number_format($hStats['st_cnt']) ?></div><div class="stat-lbl">เปลี่ยนสถานะ</div></div>
        </div>
        <a href="history.php" class="stat-card">
            <div class="stat-icon" style="background:#fffbeb;"><span class="material-symbols-rounded" style="color:#f59e0b;">history</span></div>
            <div><div class="stat-val"><?= number_format($hStats['total']) ?></div><div class="stat-lbl">ทั้งหมด</div></div>
        </a>
    </div>

    <!-- ── Filter Bar ── -->
    <form method="GET" action="history.php">
        <div class="log-filter-bar">
            <div class="log-filter-group" style="flex:1; min-width:220px;">
                <label>ค้นหา</label>
                <div class="log-search-wrap">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Job No. / ชื่อ / เบอร์ / รุ่น">
                </div>
            </div>
            <div class="log-filter-group">
                <label>จากวันที่</label>
                <input type="date" name="date_from" value="<?= h($dFrom) ?>">
            </div>
            <div class="log-filter-group">
                <label>ถึงวันที่</label>
                <input type="date" name="date_to" value="<?= h($dTo) ?>">
            </div>

            <div class="trk-filter-wrap" style="align-self:flex-end;">
                <button type="button" class="trk-filter-btn <?= !empty($statusFilter) ? 'has-filter' : '' ?>"
                        id="hFilterBtn" onclick="toggleHFilter(event)" title="กรองตามสถานะ">
                    <span class="material-symbols-rounded">filter_list</span>
                    <?php if (!empty($statusFilter)): ?>
                        <span class="trk-filter-badge"><?= count($statusFilter) ?></span>
                    <?php endif; ?>
                </button>
                <div class="trk-filter-menu" id="hFilterMenu">
                    <div class="trk-filter-title">สถานะ (ใหม่)</div>
                    <?php foreach ($statusMap as $k => $v): ?>
                        <label class="trk-filter-item">
                            <input type="checkbox" name="status[]" value="<?= $k ?>"
                                <?= in_array($k, $statusFilter) ? 'checked' : '' ?>>
                            <span class="trk-filter-dot <?= $v['class'] ?>"></span>
                            <span><?= h($v['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <div class="trk-filter-actions">
                        <button type="button" onclick="clearHFilter()">ล้าง</button>
                        <button type="submit" onclick="showLoader()">ค้นหา</button>
                    </div>
                </div>
            </div>

            <button type="submit" class="trk-icon-btn trk-icon-search" onclick="showLoader()" title="ค้นหา" style="align-self:flex-end;">
                <span class="material-symbols-rounded">search</span>
            </button>
            <?php if ($q || !empty($_GET['status']) || $dFrom || $dTo): ?>
                <a href="history.php" class="trk-icon-btn trk-icon-reset" onclick="showLoader()" title="ล้างค่า" style="align-self:flex-end;">
                    <span class="material-symbols-rounded">close</span>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- ── Table ── -->
    <div class="log-card">
        <div style="overflow-x:auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:90px;">เวลา</th>
                        <th style="width:110px;">Job No.</th>
                        <th style="width:160px;">ลูกค้า</th>
                        <th class="hcol-dev" style="width:150px;">อุปกรณ์</th>
                        <th>การเปลี่ยนแปลง</th>
                        <th class="hcol-status" style="width:110px; text-align:center;">สถานะใหม่</th>
                        <th class="hcol-editor" style="width:100px; text-align:center;">ผู้แก้ไข</th>
                        <th class="hcol-arrow" style="width:46px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <span class="material-symbols-rounded">manage_history</span>
                            <p style="font-size:15px; font-weight:600; margin:0 0 6px;">ยังไม่มีประวัติการแก้ไข</p>
                            <p style="font-size:13px; margin:0;">ประวัติจะแสดงทุกครั้งที่มีการบันทึกแก้ไขงานซ่อม</p>
                        </div>
                    </td></tr>
                <?php else:
                    $currDate  = '';
                    $todayDate = date('Y-m-d');

                    foreach ($logs as $row):
                        $ts      = strtotime($row['changed_at']);
                        $rawDate = date('Y-m-d', $ts);
                        $diff    = $row['diff_json'] ? json_decode($row['diff_json'], true) : [];
                        $stNew   = $statusMap[$row['status_new']] ?? null;
                        $stOld   = $statusMap[$row['status_old']] ?? null;

                        if ($rawDate !== $currDate):
                            $isToday   = ($rawDate === $todayDate);
                            $dateLabel = th_date_label($row['changed_at']) . ($isToday ? '  — วันนี้' : '');
                            $currDate  = $rawDate;
                ?>
                    <tr class="hist-date-row <?= $isToday ? 'is-today' : '' ?>">
                        <td colspan="8">
                            <span class="material-symbols-rounded" style="font-size:14px; vertical-align:-2px;">
                                <?= $isToday ? 'today' : 'calendar_month' ?>
                            </span>
                            <?= $dateLabel ?>
                        </td>
                    </tr>
                <?php endif; ?>
                    <tr>
                        <td style="font-family:monospace; font-size:12px; color:var(--text-muted); white-space:nowrap;">
                            <?= date('H:i', $ts) ?>
                            <?php if (!empty($row['admin_name'])): ?>
                            <!-- folded on ≤1200px: editor -->
                            <div class="trk-fold trk-fold-sub" style="font-family:'Sarabun',sans-serif;"><?= h($row['admin_name']) ?></div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a href="edit.php?id=<?= $row['tracking_id'] ?>" class="job-link" onclick="showLoader()">
                                <?= h($row['ticket_number']) ?>
                            </a>
                            <?php if ($stNew): ?>
                            <!-- folded on ≤600px: new status -->
                            <div class="trk-fold-600 trk-fold-sub"><span class="st-mini <?= $stNew['class'] ?>"><?= h($stNew['label']) ?></span></div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div style="font-weight:600; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px;">
                                <?= h($row['customer_name']) ?>
                            </div>
                            <div style="font-size:11px; color:var(--text-muted);"><?= h($row['customer_phone']) ?></div>
                            <!-- folded on ≤1200px: device -->
                            <div class="trk-fold trk-fold-sub" style="color:var(--text-muted);"><?= h($row['device_type']) ?> · <?= h($row['device_model']) ?></div>
                        </td>

                        <td class="hcol-dev">
                            <div style="font-size:13px; font-weight:600; color:var(--text-main);">
                                <?= h($row['device_type']) ?>
                            </div>
                            <div style="font-size:11px; color:var(--text-muted);"><?= h($row['device_model']) ?></div>
                        </td>

                        <!-- ── Diff column ── -->
                        <td>
                            <?php if (empty($diff)): ?>
                                <span style="font-size:11px; color:var(--text-muted);">—</span>
                            <?php else: ?>
                                <div class="diff-list">
                                <?php foreach ($diff as $key => $change):
                                    $from = $change['from'] ?? '';
                                    $to   = $change['to']   ?? '';
                                    $lbl  = $fieldLabels[$key] ?? $key;

                                    // Determine chip type
                                    if ($key === 'status'):
                                        $sfrom = $statusMap[$from] ?? null;
                                        $sto   = $statusMap[$to]   ?? null;
                                ?>
                                    <span class="diff-chip status-chip">
                                        <?php if ($sfrom): ?><span class="st-mini <?= $sfrom['class'] ?>"><?= h($sfrom['label']) ?></span><?php endif; ?>
                                        <span class="diff-arrow">→</span>
                                        <?php if ($sto): ?><span class="st-mini <?= $sto['class'] ?>"><?= h($sto['label']) ?></span><?php endif; ?>
                                    </span>
                                    <?php elseif ($key === 'estimated_cost'): ?>
                                    <span class="diff-chip cost-chip">
                                        ฿<?= number_format((float)$from) ?> <span class="diff-arrow">→</span> ฿<?= number_format((float)$to) ?>
                                    </span>
                                    <?php elseif (in_array($key, ['appointment_date','pickup_date'])): ?>
                                    <span class="diff-chip date-chip" title="<?= h($lbl) ?>">
                                        <span class="material-symbols-rounded" style="font-size:12px;"><?= $key === 'pickup_date' ? 'check_circle' : 'event' ?></span>
                                        <?= $to ? date('d/m/y', strtotime($to)) : 'ล้าง' ?>
                                    </span>
                                    <?php elseif (in_array($key, ['technician_note','problem_details','accessories'])): ?>
                                    <span class="diff-chip text-chip">
                                        <span class="material-symbols-rounded" style="font-size:12px;">edit_note</span>
                                        <?= h($lbl) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="diff-chip text-chip" title="<?= h($lbl) ?>: <?= h($from) ?> → <?= h($to) ?>">
                                        <?= h($lbl) ?>: <span style="font-weight:400; opacity:.8;"><?= mb_strimwidth(h($to), 0, 20, '…') ?></span>
                                    </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- ── New status ── -->
                        <td class="hcol-status" style="text-align:center;">
                            <?php if ($stNew): ?>
                                <span class="status-badge <?= $stNew['class'] ?>"><?= h($stNew['label']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- ── Editor ── -->
                        <td class="hcol-editor" style="text-align:center;">
                            <?php if (!empty($row['admin_name'])): ?>
                                <span style="font-size:12px; font-weight:600; color:var(--text-main);"><?= h($row['admin_name']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>

                        <td class="hcol-arrow" style="text-align:center;">
                            <a href="edit.php?id=<?= $row['tracking_id'] ?>" class="t-btn t-edit" title="ดู/แก้ไข" onclick="showLoader()">
                                <span class="material-symbols-rounded">chevron_right</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="log-pagination">
            <div>
                แสดง <b><?= number_format(min($total, $offset + 1)) ?>–<?= number_format(min($total, $offset + $per)) ?></b>
                จาก <b><?= number_format($total) ?></b> รายการ
                &nbsp;·&nbsp; หน้า <?= $page ?> / <?= $pages ?>
            </div>
            <div class="page-btns">
                <a href="<?= $page > 1 ? page_url($page - 1) : '#' ?>"
                   class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                   onclick="if(<?= (int)($page > 1) ?>) showLoader()">
                    <span class="material-symbols-rounded" style="font-size:16px;">chevron_left</span>
                </a>

                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $start + 4);
                for ($p = $start; $p <= $end; $p++):
                ?>
                    <a href="<?= page_url($p) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"
                       onclick="showLoader()"><?= $p ?></a>
                <?php endfor; ?>

                <a href="<?= $page < $pages ? page_url($page + 1) : '#' ?>"
                   class="page-btn <?= $page >= $pages ? 'disabled' : '' ?>"
                   onclick="if(<?= (int)($page < $pages) ?>) showLoader()">
                    <span class="material-symbols-rounded" style="font-size:16px;">chevron_right</span>
                </a>

                <select onchange="goPerPage(this)"
                    style="padding:6px 10px; border-radius:8px; border:1px solid var(--border); background:var(--bg-surface); color:var(--text-main); font-size:13px; outline:none; cursor:pointer; font-family:'Sarabun',sans-serif;">
                    <?php foreach ([20, 50, 100] as $pp): ?>
                        <option value="<?= $pp ?>" <?= $per === $pp ? 'selected' : '' ?>><?= $pp ?>/หน้า</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

</div><!-- .cmns-wrapper -->

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
function showLoader() {
    const el = document.getElementById('global-loader');
    if (el) { el.style.display = 'flex'; setTimeout(() => { el.style.display = 'none'; }, 5000); }
}
window.addEventListener('pageshow', () => {
    const el = document.getElementById('global-loader');
    if (el) el.style.display = 'none';
});
function goPerPage(sel) {
    showLoader();
    const u = new URL(location.href);
    u.searchParams.set('per', sel.value);
    u.searchParams.set('page', '1');
    location = u.toString();
}
function toggleHFilter(e) {
    e.stopPropagation();
    const menu = document.getElementById('hFilterMenu');
    const btn  = document.getElementById('hFilterBtn');
    const open = menu.classList.toggle('open');
    btn.classList.toggle('active', open);
}
function clearHFilter() {
    document.querySelectorAll('#hFilterMenu input[type="checkbox"]').forEach(cb => cb.checked = false);
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.trk-filter-wrap')) {
        document.querySelectorAll('.trk-filter-menu.open').forEach(m => m.classList.remove('open'));
        document.querySelectorAll('.trk-filter-btn.active').forEach(b => b.classList.remove('active'));
    }
});
</script>
