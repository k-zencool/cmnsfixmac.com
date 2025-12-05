<?php
/********************************************************************
 * admin/tracking/index.php
 *
 * ฉบับสมบูรณ์ (Final Ultimate Fixed - Revision Left Align):
 * - รวมคอลัมน์ "อุปกรณ์" และ "รุ่น" เข้าด้วยกัน
 * - แสดง "ประเภท" ด้านบน และ "รุ่น" (Badge) ด้านล่าง
 * - แก้ไข Filter ให้ทำงานได้จริง
 * - ปรับหัวตารางและเลย์เอาต์ให้สวยงาม
 * - [UPDATE LATEST] Serial/Password: หัวกลาง / เนื้อหาชิดซ้าย
 ********************************************************************/

// =========================[ 0) SETUP & GUARD ]========================
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "ติดตามงานซ่อม";

// =========================[ 1) CONSTANTS / MAPS ]=====================
$STATUS_LABELS = [
    'QS'  => 'รอเช็คราคา',
    'WC'  => 'รอคอนเฟิร์ม',
    'OK'  => 'กำลังซ่อม',
    'RW'  => 'งานแก้/เคลม',
    'FN'  => 'ซ่อมเสร็จ (รอรับ)',
    'DV'  => 'ส่งมอบแล้ว',
    'NCF' => 'ติดต่อไม่ได้ (เสร็จ)',
    'NCS' => 'ติดต่อไม่ได้ (เสนอ)',
    'XX'  => 'ยกเลิก',
    'RT'  => 'รับคืนแล้ว'
];

$STATUS_COLORS = [
    'QS'  => 'badge-blue',
    'WC'  => 'badge-orange',
    'OK'  => 'badge-amber',
    'RW'  => 'badge-purple',
    'FN'  => 'badge-green',
    'DV'  => 'badge-black',
    'NCF' => 'badge-red',
    'NCS' => 'badge-orange',
    'XX'  => 'badge-red',
    'RT'  => 'badge-gray'
];

$DEVICE_TYPES = ['iPhone', 'iPad', 'MacBook', 'iMac', 'Apple Watch', 'Android', 'Notebook', 'PC', 'Other'];
$FINISHED_STATUSES = ['FN', 'DV', 'XX', 'RT', 'NCF'];

// =========================[ 2) HELPERS ]==============================
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

// Helper: รับค่า Array จาก URL
function getvArray($key, array $allowKeys = null): array {
    $v = isset($_GET[$key]) ? $_GET[$key] : [];
    if (!is_array($v)) $v = []; 
    if ($allowKeys !== null) return array_values(array_intersect($v, $allowKeys));
    return array_values($v);
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
    if (empty($vals)) return null;
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

function getDaysRemaining($dateStr) {
    if (!$dateStr) return null;
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $target = new DateTime($dateStr);
    $target->setTime(0, 0, 0);
    $diff = $today->diff($target);
    return (int)$diff->format('%r%a');
}

// =========================[ 3) STATE ]================================
$q = getv('q', '');
$filterStatus = getvArray('status', array_keys($STATUS_LABELS));
$filterTypes  = getvArray('type');
$dfrom = getv('date_from', '');
$dto   = getv('date_to', '');
$sort  = getv('sort', 'deadline_asc');

[$per, $page, $offset] = get_pager();

// =========================[ 4) LOAD DATA ]============================
$jobs = [];
$total = 0;
$pages = 1;
$params = [];
$where = [];

// 4.1 Search
if ($w = whereSearch($q, ['ticket_number', 'customer_name', 'customer_phone', 'device_model', 'problem_details', 'serial_number', 'technician_note'], $params, 'q')) { 
    $where[] = $w; 
}
// 4.2 Filter Status
if ($w = whereIn('status', $filterStatus, $params, 'st')) { $where[] = $w; }
// 4.3 Filter Device Type
if ($w = whereIn('device_type', $filterTypes, $params, 'dt')) { $where[] = $w; }
// 4.4 Filter Date
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

// 4.6 Sort Logic
$orderBy = "created_at DESC";
switch ($sort) {
    case 'deadline_asc':
        $orderBy = "
            CASE WHEN status IN ('FN', 'DV', 'XX', 'RT', 'NCF') THEN 1 ELSE 0 END ASC, 
            (appointment_date IS NULL) ASC, 
            appointment_date ASC, 
            created_at DESC";
        break;
    case 'status_priority':
        $orderBy = "
            CASE status
                WHEN 'RW' THEN 1 WHEN 'OK' THEN 2 WHEN 'QS' THEN 3 WHEN 'WC' THEN 4 
                WHEN 'NCS' THEN 5 WHEN 'NCF' THEN 6 WHEN 'FN' THEN 7 WHEN 'DV' THEN 99 ELSE 8
            END ASC, created_at DESC";
        break;
    case 'created_desc': $orderBy = "created_at DESC"; break;
    case 'created_asc': $orderBy = "created_at ASC"; break;
}

// 4.7 Fetch Data
$sql = "
    SELECT * FROM tracking
    {$where_sql}
    ORDER BY {$orderBy}
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
        <div><a href="create.php" class="btn-primary">+ เปิดงานซ่อมใหม่</a></div>
    </div>

    <form action="index.php" method="get" id="searchForm" class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา เลขงาน/ลูกค้า/Serial/อาการ...">
        
        <select name="sort" class="filter-input" onchange="document.getElementById('searchForm').submit()" style="min-width: 180px;">
            <option value="deadline_asc" <?= $sort==='deadline_asc'?'selected':'' ?>>งานด่วน (ใกล้กำหนดส่ง)</option>
            <option value="status_priority" <?= $sort==='status_priority'?'selected':'' ?>>เรียงตามสถานะงาน</option>
            <option value="created_desc" <?= $sort==='created_desc'?'selected':'' ?>>วันที่รับงาน (ใหม่ → เก่า)</option>
            <option value="created_asc" <?= $sort==='created_asc'?'selected':'' ?>>วันที่รับงาน (เก่า → ใหม่)</option>
        </select>

        <div class="filter-dropdown">
            <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuJobs')">ตัวกรอง</button>
            
            <div id="filterMenuJobs" class="filter-menu">
                <div class="filter-section">
                    <div class="filter-title">สถานะงาน</div>
                    <?php foreach ($STATUS_LABELS as $key => $label): 
                        $checked = in_array($key, $filterStatus) ? 'checked' : ''; 
                        $colorClass = $STATUS_COLORS[$key] ?? 'badge-gray';
                        $dotColor = '#9ca3af';
                        if(strpos($colorClass,'blue')!==false) $dotColor='#3b82f6';
                        elseif(strpos($colorClass,'amber')!==false) $dotColor='#f59e0b';
                        elseif(strpos($colorClass,'orange')!==false) $dotColor='#f97316';
                        elseif(strpos($colorClass,'green')!==false) $dotColor='#10b981';
                        elseif(strpos($colorClass,'red')!==false) $dotColor='#ef4444';
                        elseif(strpos($colorClass,'purple')!==false) $dotColor='#8b5cf6';
                        elseif(strpos($colorClass,'black')!==false) $dotColor='#1f2937';
                    ?>
                        <label class="checkline">
                            <input type="checkbox" name="status[]" value="<?= h($key) ?>" <?= $checked ?>>
                            <span style="width:8px; height:8px; border-radius:50%; background:<?= $dotColor ?>; display:inline-block; margin-right:6px;"></span>
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
                    <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuJobs')">ล้าง</button>
                    <button type="submit" class="btn-primary">ค้นหา</button>
                </div>
            </div>
        </div>

        <input type="hidden" name="page" value="1">
        <button type="submit" class="btn-search">ค้นหา</button>
    </form>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th style="white-space: nowrap;">เลขที่ซ่อม</th>
                    <th>ลูกค้า</th>
                    <th style="text-align:center;">อุปกรณ์ / รุ่น</th>
                    <th style="text-align:center;">Serial / Password</th>
                    <th>อาการเสีย</th>
                    <th>หมายเหตุ</th>
                    <th style="text-align:center;">สถานะ</th>
                    <th>วันนัดรับ</th>
                    <th style="text-align:center;">เหลือเวลา</th>
                    <th>ราคา</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($jobs): foreach ($jobs as $i => $row): 
                    $badgeColor = $STATUS_COLORS[$row['status']] ?? 'badge-gray';
                    $statusText = $STATUS_LABELS[$row['status']] ?? $row['status'];
                    $daysLeft = getDaysRemaining($row['appointment_date']);
                    $timeLeftBadge = '<span class="muted">-</span>';
                    $isFinished = in_array($row['status'], $FINISHED_STATUSES);

                    if (!$isFinished && $row['appointment_date']) {
                        if ($daysLeft < 0) {
                            $timeLeftBadge = '<span class="badge badge-red-outline">เกิน '.abs($daysLeft).' วัน</span>';
                        } elseif ($daysLeft == 0) {
                            $timeLeftBadge = '<span class="badge badge-red-flash">วันนี้!</span>';
                        } elseif ($daysLeft <= 2) {
                            $timeLeftBadge = '<span class="badge badge-orange">อีก '.$daysLeft.' วัน</span>';
                        } else {
                            $timeLeftBadge = '<span class="badge badge-green">อีก '.$daysLeft.' วัน</span>';
                        }
                    } elseif ($row['status'] === 'FN') {
                        $timeLeftBadge = '<span class="badge badge-green-outline">รอรับ</span>';
                    } elseif ($row['status'] === 'DV') {
                        $timeLeftBadge = '<span class="badge badge-gray-outline">จบงาน</span>';
                    }
                ?>
                    <tr>
                        <td><?= ($offset + $i + 1) ?></td>
                        
                        <td>
                            <div style="font-family:monospace; font-weight:600; color:var(--primary-color); font-size:1.1em; line-height:1.2;">
                                <?= h($row['ticket_number']) ?>
                            </div>
                            <div class="muted" style="font-size:0.8em; color: #9ca3af; margin-top: 2px;">
                                <?= date('d/m/y H:i', strtotime($row['created_at'])) ?>
                            </div>
                        </td>

                        <td>
                            <strong><?= h($row['customer_name']) ?></strong>
                            <div class="muted" style="font-size:0.85em;">
                                <span class="material-symbols-rounded" style="font-size:14px; vertical-align:text-bottom;">call</span>
                                <?= h($row['customer_phone']) ?>
                            </div>
                        </td>
                        
                        <td style="text-align: center;">
                            <div style="font-weight: 600; color: #374151; margin-bottom: 4px;">
                                <?= h($row['device_type']) ?>
                            </div>
                            <span class="badge-model" title="<?= h($row['device_model']) ?>">
                                <?= h($row['device_model']) ?>
                            </span>
                        </td>

                        <td style="text-align:left;">
                            <div style="font-size:0.85em; color:#4b5563; font-family:monospace;">
                                <span style="color:#9ca3af;">SN:</span> <?= h($row['serial_number'] ?? '-') ?>
                            </div>
                            <div style="font-size:0.85em; font-family:monospace; margin-top:2px;">
                                <span style="color:#9ca3af;">PW:</span> 
                                <span style="color:#ef4444; font-weight:bold;"><?= h($row['device_password'] ?? '-') ?></span>
                            </div>
                        </td>
                        
                        <td class="clickable-cell" onclick="openTextModal(this)" data-fulltext="<?= h($row['problem_details']) ?>" data-title="อาการเสีย">
                            <div class="truncate-text"><?= h($row['problem_details']) ?></div>
                        </td>

                        <td class="clickable-cell" onclick="openTextModal(this)" data-fulltext="<?= h($row['technician_note']) ?>" data-title="หมายเหตุ">
                            <div class="truncate-text" style="color:#6b7280; font-style:italic;">
                                <?= h($row['technician_note'] ?: '-') ?>
                            </div>
                        </td>
                        
                        <td style="text-align:center;"><span class="badge <?= $badgeColor ?>"><?= $statusText ?></span></td>
                        
                        <td>
                            <?php if ($row['appointment_date']): ?>
                                <?= date('d/m/Y', strtotime($row['appointment_date'])) ?>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; white-space:nowrap;"><?= $timeLeftBadge ?></td>
                        <td><?= $row['estimated_cost'] > 0 ? number_format($row['estimated_cost']) : '<span class="muted">-</span>' ?></td>
                        <td class="no-wrap">
                            <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn-edit">
                                <span class="material-symbols-rounded" style="font-size:18px;">edit_document</span> แก้ไข
                            </a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="12" class="text-center" style="padding:40px; color:#9ca3af;">
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
        <nav class="pager-nav">
            <a class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? page_url($page - 1) : '#' ?>">‹</a>
            <?php 
            $start = max(1, $page - 2); $end = min($pages, $page + 2);
            if ($start > 1) echo '<span class="page-ellipsis">…</span>';
            for ($i = $start; $i <= $end; $i++): ?>
                <a class="page-btn <?= $i == $page ? 'is-active' : '' ?>" href="<?= page_url($i) ?>"><?= $i ?></a>
            <?php endfor; if ($end < $pages) echo '<span class="page-ellipsis">…</span>'; ?>
            <a class="page-btn <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? page_url($page + 1) : '#' ?>">›</a>
            <div class="page-size">
                <select id="ppSelect" class="pager-select">
                    <?php foreach ([20, 50, 100] as $pp): ?><option value="<?= $pp ?>" <?= (int)$per === $pp ? 'selected' : '' ?>><?= $pp ?>/หน้า</option><?php endforeach; ?>
                </select>
            </div>
        </nav>
    </div>

    <div id="textModal" class="modal-overlay" aria-hidden="true" onclick="closeTextModal(event)">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">รายละเอียด</h3>
                <button type="button" class="close-btn" onclick="closeTextModal()">✕</button>
            </div>
            <div class="modal-body" id="modalTextContent"></div>
        </div>
    </div>

</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
    function toggleMenu(id) { var m = document.getElementById(id); if (m) m.classList.toggle('show'); }
    function clearMenu(id) {
        var root = document.getElementById(id); if (!root) return;
        root.querySelectorAll('input[type="checkbox"]').forEach(el => el.checked = false);
        root.querySelectorAll('input[type="date"]').forEach(el => el.value = '');
    }
    document.addEventListener('click', function(e) {
        var dd = e.target.closest ? e.target.closest('.filter-dropdown') : null;
        document.querySelectorAll('.filter-menu.show').forEach(function(m) {
            if (!dd || !dd.contains(m)) m.classList.remove('show');
        });
    });
    
    function openTextModal(element) {
        var text = element.getAttribute('data-fulltext');
        var title = element.getAttribute('data-title') || 'รายละเอียด';
        if (!text || text === '-') return; 
        document.getElementById('modalTextContent').innerText = text;
        document.getElementById('modalTitle').innerText = title;
        var modal = document.getElementById('textModal');
        modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false');
    }
    function closeTextModal(e) {
        if (e && e.target !== e.currentTarget && !e.target.classList.contains('close-btn')) return;
        var modal = document.getElementById('textModal');
        modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true');
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { var modal = document.getElementById('textModal'); if (modal && modal.classList.contains('show')) closeTextModal(); }
    });

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
    /* 1. Equal Height */
    .search-and-filter-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .filter-input, .btn-search, .btn-secondary { height: 42px !important; padding: 0 12px; font-size: 0.95rem; border-radius: 6px; border: 1px solid #e5e7eb; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; vertical-align: middle; margin: 0; }
    input.filter-input { flex: 1; min-width: 200px; }
    select.filter-input { background-color: #fff; cursor: pointer; }
    .btn-search { background: var(--primary-color); color: #fff; border: none; font-weight: 500; cursor: pointer; } 
    .btn-search:hover { opacity: 0.9; }
    .btn-secondary { background: #fff; color: var(--text); cursor: pointer; }
    .btn-secondary:hover { background: #f9fafb; border-color: #d1d5db; }

    /* 2. Badges */
    .badge-blue { background: #dbeafe; color: #1e40af; }
    .badge-amber { background: #fef3c7; color: #92400e; }
    .badge-orange { background: #ffedd5; color: #9a3412; }
    .badge-green { background: #d1fae5; color: #065f46; }
    .badge-red { background: #fee2e2; color: #991b1b; }
    .badge-gray { background: #f3f4f6; color: #374151; }
    .badge-purple { background: #ede9fe; color: #5b21b6; }
    .badge-black { background: #1f2937; color: #fff; } 
    .badge-red-outline { border: 1px solid #ef4444; color: #ef4444; background: #fff; font-weight: bold; }
    .badge-red-flash { background: #ef4444; color: #fff; font-weight: bold; animation: pulse 2s infinite; }
    .badge-gray-outline { border: 1px solid #9ca3af; color: #9ca3af; background: #fff; }
    .badge-green-outline { border: 1px solid #10b981; color: #10b981; background: #fff; }
    .badge, .badge-model, .badge-red-outline, .badge-red-flash, .badge-gray-outline, .badge-green-outline {
        display: inline-flex; justify-content: center; align-items: center;
        min-width: 130px; height: 28px; padding: 0 10px; border-radius: 20px;
        font-size: 0.85em; font-weight: 500; white-space: nowrap;
        box-sizing: border-box; vertical-align: middle;
        overflow: hidden; text-overflow: ellipsis;
    }
    .badge-model { background: #f3f4f6; color: #374151; font-weight: 600; min-width: 100px; text-align: center;}
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }

    /* 3. Clickable Text (Minimal) */
    .clickable-cell { cursor: pointer; color: #4b5563; max-width: 220px; transition: color 0.1s; }
    .clickable-cell:hover { color: #000; }
    .truncate-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; width: 100%; }

    /* 4. Dropdown */
    .filter-dropdown { position: relative; }
    .filter-menu { display: none; position: absolute; top: 100%; right: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); width: 320px; z-index: 100; margin-top: 8px; padding: 16px; box-sizing: border-box; }
    .filter-menu.show { display: block; animation: slideDown 0.15s ease-out; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    .filter-section { margin-bottom: 16px; border-bottom: 1px solid #f3f4f6; padding-bottom: 12px; }
    .filter-title { font-weight: 600; margin-bottom: 10px; color: #6b7280; font-size: 0.85rem; text-transform: uppercase; }
    .checkline { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; cursor: pointer; user-select: none; font-size: 0.95rem; }
    .checkline input { accent-color: var(--primary-color); width: 18px; height: 18px; }
    .filter-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e5e7eb; }
    .range-inline { display: flex; align-items: center; gap: 5px; }
    .range-inline input { flex: 1; width: 0; min-width: 0; padding: 6px; border: 1px solid #e5e7eb; border-radius: 4px; box-sizing: border-box; }

    /* 5. Modal */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; }
    .modal-overlay.show { display: flex; opacity: 1; }
    .modal-content { background: #fff; padding: 25px; border-radius: 12px; width: 90%; max-width: 500px; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.2); transform: translateY(20px); transition: transform 0.2s; }
    .modal-overlay.show .modal-content { transform: translateY(0); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; }
    .modal-header h3 { margin: 0; font-size: 1.2rem; color: #374151; }
    .modal-header .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #9ca3af; }
    .modal-body { font-size: 1rem; line-height: 1.6; color: #4b5563; white-space: pre-wrap; }
</style>