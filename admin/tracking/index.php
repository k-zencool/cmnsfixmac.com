<?php
/********************************************************************
 * admin/tracking/index.php
 *
 * อัปเดต: แยกคอลัมน์ "ประเภท" กับ "รุ่น" ออกจากกัน
 ********************************************************************/

// =========================[ 0) SETUP & GUARD ]========================
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "ติดตามงานซ่อม";

// =========================[ 1) CONSTANTS / MAPS ]=====================
// 1.1 สถานะ
$STATUS_LABELS = [
    'QS'  => 'รอเช็คราคา',
    'WC'  => 'รอคอนเฟิร์ม',
    'OK'  => 'กำลังซ่อม',
    'RW'  => 'งานแก้/เคลม',
    'FN'  => 'ซ่อมเสร็จ',
    'NCF' => 'ติดต่อไม่ได้(เสร็จ)',
    'NCS' => 'ติดต่อไม่ได้(เสนอราคา)',
    'XX'  => 'ยกเลิก',
    'RT'  => 'รับคืนแล้ว'
];

// 1.2 สีของ Badge
$STATUS_COLORS = [
    'QS'  => 'badge-blue',
    'WC'  => 'badge-orange',
    'OK'  => 'badge-amber',
    'RW'  => 'badge-purple',
    'FN'  => 'badge-green',
    'NCF' => 'badge-red',
    'NCS' => 'badge-orange',
    'XX'  => 'badge-red',
    'RT'  => 'badge-gray'
];

// 1.3 ประเภทเครื่อง
$DEVICE_TYPES = ['iPhone', 'iPad', 'MacBook', 'iMac', 'Apple Watch', 'Android', 'Notebook', 'PC', 'Other'];

// =========================[ 2) HELPERS ]==============================
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function getvArray($key, array $allowKeys = null): array {
    $v = isset($_GET[$key]) ? (array)$_GET[$key] : [];
    if ($allowKeys !== null) return array_values(array_intersect($v, $allowKeys));
    return $v;
}

function whereSearch(string $q, array $cols, array &$params, string $pfx): ?string {
    if ($q === '') return null;
    $ors = []; $i = 0;
    foreach ($cols as $c) {
        $ph = ":{$pfx}{$i}";
        $ors[] = "$c LIKE $ph";
        $params[$ph] = "%{$q}%";
        $i++;
    }
    return '(' . implode(' OR ', $ors) . ')';
}

function whereIn(string $col, array $vals, array &$params, string $pfx): ?string {
    if (!$vals) return null;
    $in = [];
    foreach ($vals as $i => $v) {
        $ph = ":{$pfx}{$i}";
        $params[$ph] = $v;
        $in[] = $ph;
    }
    return "$col IN (" . implode(',', $in) . ")";
}

function get_pager(): array {
    $per = max(5, min(200, (int)getv('per', 20)));
    $page = max(1, (int)getv('page', 1));
    $off = ($page - 1) * $per;
    return [$per, $page, $off];
}

function page_url($i) {
    $q = $_GET;
    $q['page'] = max(1, (int)$i);
    return '?' . http_build_query($q);
}

// =========================[ 3) STATE ]================================
$q = getv('q', '');
$filterStatus = getvArray('status', array_keys($STATUS_LABELS));
$filterTypes  = getvArray('type');
$dfrom = getv('date_from', '');
$dto   = getv('date_to', '');

[$per, $page, $offset] = get_pager();

// =========================[ 4) LOAD DATA ]============================
$jobs = [];
$total = 0;
$pages = 1;
$params = [];
$where = [];

// 4.1 Search
if ($w = whereSearch($q, ['ticket_number', 'customer_name', 'customer_phone', 'device_model', 'problem_details'], $params, 'q')) {
    $where[] = $w;
}
// 4.2 Status
if ($w = whereIn('status', $filterStatus, $params, 'st')) {
    $where[] = $w;
}
// 4.3 Device
if ($w = whereIn('device_type', $filterTypes, $params, 'dt')) {
    $where[] = $w;
}
// 4.4 Date
if ($dfrom !== '') { $where[] = "DATE(created_at) >= :df"; $params[':df'] = $dfrom; }
if ($dto !== '')   { $where[] = "DATE(created_at) <= :dt"; $params[':dt'] = $dto; }

$where_sql = $where ? ("WHERE " . implode(' AND ', $where)) : "";

// 4.5 Count
$stc = $pdo->prepare("SELECT COUNT(*) FROM tracking {$where_sql}");
foreach ($params as $k => $v) $stc->bindValue($k, $v);
$stc->execute();
$total = (int)($stc->fetchColumn() ?: 0);
$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $per; }

// 4.6 Fetch Data
$sql = "
    SELECT * FROM tracking
    {$where_sql}
    ORDER BY 
        CASE status
            WHEN 'RW' THEN 1 WHEN 'OK' THEN 2 WHEN 'QS' THEN 3 WHEN 'WC' THEN 4 
            WHEN 'NCS' THEN 5 WHEN 'NCF' THEN 6 WHEN 'FN' THEN 7 ELSE 8
        END ASC,
        created_at DESC
    LIMIT :limit OFFSET :off
";

$st = $pdo->prepare($sql);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':limit', $per, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$jobs = $st->fetchAll(PDO::FETCH_ASSOC);

// =========================[ 5) TEMPLATE ]=============================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
    <div class="topbar">
        <span><?= h($pageTitle) ?></span>
        <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
    </div>

    <div class="section-header">
        <h2>รายการงานซ่อมทั้งหมด</h2>
        <div>
            <a href="create.php" class="btn-primary">+ เปิดงานซ่อมใหม่</a>
        </div>
    </div>

    <form action="index.php" method="GET">
        <div class="search-and-filter-group">
            <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา เลขงาน/ลูกค้า/เบอร์/รุ่น/อาการ...">

            <div class="filter-dropdown">
                <button type="button" class="btn-secondary" onclick="toggleFilterMenu()">
                    <span class="material-symbols-rounded" style="font-size:18px; vertical-align:middle;">filter_list</span> ตัวกรอง
                </button>
                
                <div id="filterMenu" class="filter-menu">
                    <div class="filter-section">
                        <div class="filter-title">สถานะงาน</div>
                        <?php foreach ($STATUS_LABELS as $key => $label): 
                            $checked = in_array($key, $filterStatus) ? 'checked' : ''; 
                            $colorClass = $STATUS_COLORS[$key] ?? 'badge-gray';
                            $dotColor = '#9ca3af'; 
                            if(strpos($colorClass,'blue')!==false) $dotColor='#3b82f6';
                            if(strpos($colorClass,'amber')!==false) $dotColor='#f59e0b';
                            if(strpos($colorClass,'orange')!==false) $dotColor='#f97316';
                            if(strpos($colorClass,'green')!==false) $dotColor='#10b981';
                            if(strpos($colorClass,'red')!==false) $dotColor='#ef4444';
                            if(strpos($colorClass,'purple')!==false) $dotColor='#8b5cf6';
                        ?>
                            <label class="checkline">
                                <input type="checkbox" name="status[]" value="<?= h($key) ?>" <?= $checked ?>>
                                <span style="width:8px; height:8px; border-radius:50%; background:<?= $dotColor ?>; display:inline-block; margin-right:4px;"></span>
                                <span><?= h($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="filter-section">
                        <div class="filter-title">ประเภทเครื่อง</div>
                        <?php foreach ($DEVICE_TYPES as $type): 
                            $checked = in_array($type, $filterTypes) ? 'checked' : ''; 
                        ?>
                            <label class="checkline">
                                <input type="checkbox" name="type[]" value="<?= h($type) ?>" <?= $checked ?>>
                                <span><?= h($type) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="filter-section">
                        <div class="filter-title">วันที่รับงาน</div>
                        <div class="range-inline">
                            <input type="date" name="date_from" value="<?= h($dfrom) ?>">
                            <span class="mx-2">-</span>
                            <input type="date" name="date_to" value="<?= h($dto) ?>">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="button" class="btn-secondary" onclick="clearFilters()">ล้าง</button>
                        <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="page" value="1">
            <button class="btn-search">ค้นหา</button>
        </div>
    </form>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>เลขที่ซ่อม</th>
                    <th>ลูกค้า</th>
                    <th>ประเภท</th>
                    <th>รุ่น / Model</th>
                    
                    <th>อาการเสีย</th>
                    <th>สถานะ</th>
                    <th>วันนัดรับ</th>
                    <th>ราคาประเมิน</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($jobs): foreach ($jobs as $i => $row): 
                    $badgeColor = $STATUS_COLORS[$row['status']] ?? 'badge-gray';
                    $statusText = $STATUS_LABELS[$row['status']] ?? $row['status'];
                ?>
                    <tr>
                        <td><?= ($offset + $i + 1) ?></td>
                        <td>
                            <span style="font-family:monospace; font-weight:600; color:var(--primary-color);">
                                <?= h($row['ticket_number']) ?>
                            </span>
                            <div class="muted" style="font-size:0.8em;"><?= date('d/m/y H:i', strtotime($row['created_at'])) ?></div>
                        </td>
                        <td>
                            <strong><?= h($row['customer_name']) ?></strong>
                            <div class="muted" style="font-size:0.85em;">
                                <span class="material-symbols-rounded" style="font-size:14px; vertical-align:text-bottom;">call</span>
                                <?= h($row['customer_phone']) ?>
                            </div>
                        </td>
                        
                        <td><?= h($row['device_type']) ?></td>
                        
                        <td>
                            <span class="badge" style="background:#f3f4f6; color:#374151; font-weight:normal;">
                                <?= h($row['device_model']) ?>
                            </span>
                        </td>

                        <td style="max-width:200px; white-space:normal; line-height:1.4;">
                            <?= h($row['problem_details']) ?>
                        </td>
                        <td>
                            <span class="badge <?= $badgeColor ?>"><?= $statusText ?></span>
                        </td>
                        <td>
                            <?php if ($row['appointment_date']): ?>
                                <?= date('d/m/Y', strtotime($row['appointment_date'])) ?>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $row['estimated_cost'] > 0 ? number_format($row['estimated_cost']) : '<span class="muted">-</span>' ?>
                        </td>
                        <td class="no-wrap">
                            <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn-edit">
                                <span class="material-symbols-rounded" style="font-size:18px;">edit_document</span> แก้ไข
                            </a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="10" class="text-center" style="padding:40px; color:#9ca3af;">
                            <span class="material-symbols-rounded" style="font-size:48px; display:block; margin-bottom:10px;">search_off</span>
                            ไม่พบงานซ่อมตามเงื่อนไข
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pager-bar">
        <div class="pager-left">
            <span class="pager-total">พบ <?= number_format($total) ?> รายการ</span>
            <span class="divider">•</span>
            <span>หน้า <?= (int)$page ?> / <?= (int)$pages ?></span>
        </div>
        <nav class="pager-nav" aria-label="Pagination">
            <a class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? page_url($page - 1) : '#' ?>" rel="prev">‹</a>
            
            <?php 
            $start = max(1, $page - 2);
            $end = min($pages, $page + 2);
            if ($start > 1) echo '<span class="page-ellipsis">…</span>';
            for ($i = $start; $i <= $end; $i++): ?>
                <a class="page-btn <?= $i == $page ? 'is-active' : '' ?>" href="<?= page_url($i) ?>"><?= $i ?></a>
            <?php endfor; 
            if ($end < $pages) echo '<span class="page-ellipsis">…</span>'; ?>
            
            <a class="page-btn <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? page_url($page + 1) : '#' ?>" rel="next">›</a>
            
            <div class="page-size">
                <select id="ppSelect" class="pager-select">
                    <?php foreach ([20, 50, 100] as $pp): ?>
                        <option value="<?= $pp ?>" <?= (int)$per === $pp ? 'selected' : '' ?>><?= $pp ?>/หน้า</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </nav>
    </div>

</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
    function toggleFilterMenu() { var m = document.getElementById('filterMenu'); if (m) m.classList.toggle('show'); }
    
    document.addEventListener('click', function(e) {
        var dd = e.target.closest ? e.target.closest('.filter-dropdown') : null;
        var m = document.getElementById('filterMenu');
        if (m && m.classList.contains('show') && (!dd || !dd.contains(m))) m.classList.remove('show');
    });

    function clearFilters() {
        var root = document.getElementById('filterMenu'); if (!root) return;
        root.querySelectorAll('input[type="checkbox"]').forEach(el => el.checked = false);
        root.querySelectorAll('input[type="date"]').forEach(el => el.value = '');
    }

    (function() {
        const sel = document.getElementById('ppSelect'); if (!sel) return;
        sel.addEventListener('change', function() {
            const u = new URL(location.href); u.searchParams.set('per', this.value); u.searchParams.set('page', '1'); location = u.toString();
        });
        document.addEventListener('keydown', function(e) {
            if (e.altKey || e.metaKey || e.ctrlKey || e.target.tagName === 'INPUT') return;
            if (e.key === 'ArrowRight') document.querySelector('.page-btn[rel="next"]')?.click();
            if (e.key === 'ArrowLeft') document.querySelector('.page-btn[rel="prev"]')?.click();
        });
    })();
</script>

<style>
    .badge-blue { background: #dbeafe; color: #1e40af; }
    .badge-amber { background: #fef3c7; color: #92400e; }
    .badge-orange { background: #ffedd5; color: #9a3412; }
    .badge-green { background: #d1fae5; color: #065f46; }
    .badge-red { background: #fee2e2; color: #991b1b; }
    .badge-gray { background: #f3f4f6; color: #374151; }
    .badge-purple { background: #ede9fe; color: #5b21b6; }
    
    .checkline { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; cursor: pointer; user-select: none; }
    .checkline input { accent-color: var(--primary-color); width: 16px; height: 16px; }
    .range-inline { display: flex; align-items: center; gap: 5px; }
    .range-inline input { width: 100%; padding: 6px; border: 1px solid #e5e7eb; border-radius: 4px; }
</style>