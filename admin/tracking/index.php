<?php
/********************************************************************
 * admin/tracking/index.php  –  Repair Job List
 ********************************************************************/

session_start();
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

/* ── Helpers ── */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k, $d = null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function strip_html_content($t){ return $t ? trim(strip_tags(html_entity_decode($t))) : '-'; }

function get_pager(): array {
    $per  = max(5, min(200, (int)getv('per', 20)));
    $page = max(1, (int)getv('page', 1));
    return [$per, $page, ($page - 1) * $per];
}
function page_url(int $i): string {
    $q = $_GET; $q['page'] = max(1, $i);
    return '?' . http_build_query($q);
}

/* ── State ── */
$q      = getv('q', '');
$dfrom  = getv('date_from', '');
$dto    = getv('date_to', '');
$group  = getv('group', 'all');
$statusFilter = isset($_GET['status']) ? (array)$_GET['status'] : [];

$pageTitle = "Tracking / Repair Jobs";

// Group shortcuts (override statusFilter if no manual selection)
if (empty($statusFilter)) {
    if ($group === 'active') $statusFilter = ['QS','WC','OK','RW'];
    elseif ($group === 'done')  $statusFilter = ['FN'];
}

[$per, $page, $offset] = get_pager();

/* ── Status Map ── */
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

/* ── Delete Action ── */
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $pdo->prepare("DELETE FROM tracking WHERE id = ?")->execute([(int)$_POST['id']]);
    $_SESSION['success'] = "ลบรายการเรียบร้อย";
    if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
    header("Location: index.php");
    exit;
}

/* ── Query Builder ── */
$where  = [];
$params = [];

if ($q) {
    foreach (preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) as $idx => $word) {
        $k = ":q{$idx}";
        $where[]    = "(ticket_number LIKE $k OR customer_name LIKE $k OR customer_phone LIKE $k OR serial_number LIKE $k OR device_model LIKE $k OR device_series LIKE $k OR problem_details LIKE $k)";
        $params[$k] = "%{$word}%";
    }
}
if (!empty($statusFilter)) {
    $in = [];
    foreach ($statusFilter as $i => $s) { $k = ":s{$i}"; $in[] = $k; $params[$k] = $s; }
    $where[] = "status IN (" . implode(',', $in) . ")";
}
if ($dfrom) { $where[] = "DATE(created_at) >= :df"; $params[':df'] = $dfrom; }
if ($dto)   { $where[] = "DATE(created_at) <= :dt"; $params[':dt'] = $dto; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM tracking $whereSql");
foreach ($params as $k => $v) $stmtCnt->bindValue($k, $v);
$stmtCnt->execute();
$total = (int)($stmtCnt->fetchColumn() ?: 0);

$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $per; }

// Fetch
$stmt = $pdo->prepare("SELECT * FROM tracking $whereSql ORDER BY created_at DESC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $per, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Stats ── */
$stats = $pdo->query("
    SELECT
        COALESCE(SUM(status IN ('QS','WC','OK','RW')), 0)                                        AS active_count,
        COALESCE(SUM(status = 'FN'), 0)                                                          AS done_count,
        COALESCE(SUM(status NOT IN ('DV','RT','NCF','NCS') AND appointment_date IS NOT NULL AND appointment_date < CURDATE()), 0) AS overdue_count,
        COALESCE(SUM(MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())), 0)    AS this_month
    FROM tracking
")->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/tracking-index.css?v=<?= time() ?>">

<div class="cmns-wrapper">

    <!-- ── Header ── -->
    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color: var(--primary);">
                <span class="material-symbols-rounded" style="font-size:32px;">build_circle</span>
                TRACKING / REPAIR JOBS
            </h1>
            <p style="color:var(--text-muted); margin-top:5px; font-size:13px;">
                จัดการงานซ่อมทั้งหมด · แสดง <b><?= number_format($total) ?></b> รายการ
            </p>
        </div>
        <div class="cmns-action-buttons">
            <a href="history.php" class="cmns-btn cmns-btn-secondary">
                <span class="material-symbols-rounded">history</span> HISTORY
            </a>
            <a href="create.php" class="cmns-btn cmns-btn-primary" onclick="showLoader()">
                <span class="material-symbols-rounded">add_circle</span> เปิดงานซ่อม
            </a>
        </div>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="log-stats" style="margin-bottom:24px;">
        <a href="index.php?group=active" class="log-stat-card stat-accent-blue" style="text-decoration:none;">
            <span class="stat-label">กำลังดำเนินการ</span>
            <span class="stat-value"><?= number_format($stats['active_count']) ?></span>
            <span class="stat-sub">QS · WC · OK · RW</span>
        </a>
        <a href="index.php?group=done" class="log-stat-card stat-accent-green" style="text-decoration:none;">
            <span class="stat-label">ซ่อมเสร็จ รอรับ</span>
            <span class="stat-value"><?= number_format($stats['done_count']) ?></span>
            <span class="stat-sub">สถานะ FN</span>
        </a>
        <div class="log-stat-card stat-accent-red">
            <span class="stat-label">เกินกำหนดนัด</span>
            <span class="stat-value"><?= number_format($stats['overdue_count']) ?></span>
            <span class="stat-sub">นัดผ่านมาแล้ว</span>
        </div>
        <div class="log-stat-card stat-accent-yellow">
            <span class="stat-label">เปิดงานเดือนนี้</span>
            <span class="stat-value"><?= number_format($stats['this_month']) ?></span>
            <span class="stat-sub"><?= date('M Y') ?></span>
        </div>
    </div>

    <!-- ── Filter Bar ── -->
    <form method="GET" action="index.php">
        <?php if ($group !== 'all'): ?>
            <input type="hidden" name="group" value="<?= h($group) ?>">
        <?php endif; ?>
        <div class="log-filter-bar">
            <div class="log-filter-group" style="flex:1; min-width:220px;">
                <label>ค้นหา</label>
                <div class="log-search-wrap">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Job / ชื่อ / เบอร์ / S/N / รุ่น / อาการ">
                </div>
            </div>

            <div class="log-filter-group">
                <label>จากวันที่</label>
                <input type="date" name="date_from" value="<?= h($dfrom) ?>">
            </div>

            <div class="log-filter-group">
                <label>ถึงวันที่</label>
                <input type="date" name="date_to" value="<?= h($dto) ?>">
            </div>

            <div class="trk-filter-wrap" style="align-self:flex-end;">
                <button type="button" class="trk-filter-btn <?= !empty($statusFilter) ? 'has-filter' : '' ?>"
                        id="filterBtn" onclick="toggleFilterMenu(event)" title="กรองตามสถานะ">
                    <span class="material-symbols-rounded">filter_list</span>
                    <?php if (!empty($statusFilter)): ?>
                        <span class="trk-filter-badge"><?= count($statusFilter) ?></span>
                    <?php endif; ?>
                </button>
                <div class="trk-filter-menu" id="filterMenu">
                    <div class="trk-filter-title">สถานะงาน</div>
                    <?php foreach ($statusMap as $k => $v): ?>
                        <label class="trk-filter-item">
                            <input type="checkbox" name="status[]" value="<?= $k ?>"
                                <?= in_array($k, $statusFilter) ? 'checked' : '' ?>>
                            <span class="trk-filter-dot <?= $v['class'] ?>"></span>
                            <span><?= h($v['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <div class="trk-filter-actions">
                        <button type="button" onclick="clearFilterMenu()">ล้าง</button>
                        <button type="submit" onclick="showLoader()">ค้นหา</button>
                    </div>
                </div>
            </div>

            <button type="submit" class="trk-icon-btn trk-icon-search" onclick="showLoader()" title="ค้นหา" style="align-self:flex-end;">
                <span class="material-symbols-rounded">search</span>
            </button>

            <?php if ($q || !empty($_GET['status']) || $dfrom || $dto): ?>
                <a href="index.php?group=<?= h($group) ?>" class="trk-icon-btn trk-icon-reset" onclick="showLoader()" title="ล้างค่าทั้งหมด" style="align-self:flex-end;">
                    <span class="material-symbols-rounded">close</span>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- ── Group Tabs ── -->
    <?php
    $tabBase = 'index.php?'
        . ($q    ? 'q='         . urlencode($q) . '&' : '')
        . ($dfrom ? 'date_from=' . $dfrom . '&' : '')
        . ($dto   ? 'date_to='   . $dto   . '&' : '');
    $isManualStatus = !empty($_GET['status']);
    ?>
    <div class="log-tabs">
        <a href="<?= $tabBase ?>group=all"    class="log-tab <?= $group === 'all'    && !$isManualStatus ? 'active-all'    : '' ?>">
            <span class="material-symbols-rounded" style="font-size:14px; vertical-align:middle;">list</span>
            ทั้งหมด
        </a>
        <a href="<?= $tabBase ?>group=active" class="log-tab <?= $group === 'active' && !$isManualStatus ? 'active-in'     : '' ?>">
            <span class="material-symbols-rounded" style="font-size:14px; vertical-align:middle;">pending</span>
            กำลังดำเนินการ
        </a>
        <a href="<?= $tabBase ?>group=done"   class="log-tab <?= $group === 'done'   && !$isManualStatus ? 'active-adjust' : '' ?>">
            <span class="material-symbols-rounded" style="font-size:14px; vertical-align:middle;">check_circle</span>
            ซ่อมเสร็จ รอรับ
        </a>
    </div>

    <!-- ── Table ── -->
    <div class="log-card">
        <div style="overflow-x:auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:110px;">Job No.</th>
                        <th style="width:85px;">วันที่รับ</th>
                        <th>ลูกค้า</th>
                        <th style="text-align:center; width:170px;">อุปกรณ์</th>
                        <th style="width:130px;">S/N &amp; Pass</th>
                        <th>อาการเสีย</th>
                        <th style="width:90px;">กำหนดนัด</th>
                        <th style="width:100px;">เหลือเวลา</th>
                        <th style="width:88px; text-align:right;">ราคา</th>
                        <th style="width:155px; text-align:center;">สถานะ</th>
                        <th style="width:110px; text-align:center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                        <tr><td colspan="11">
                            <div class="empty-state">
                                <span class="material-symbols-rounded">build_circle</span>
                                <p style="font-size:15px; font-weight:600; margin:0 0 6px;">ไม่พบข้อมูลงานซ่อม</p>
                                <p style="font-size:13px; margin:0;">ลองเปลี่ยนตัวกรองหรือค้นหาใหม่</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($jobs as $row):
                            $stCode  = $row['status'] ?? 'QS';
                            $stData  = $statusMap[$stCode] ?? ['label' => $stCode, 'class' => 'st-gray'];
                            $dateIn  = date('d/m/y', strtotime($row['created_at']));
                            $dateApp = $row['appointment_date'] ? date('d/m/y', strtotime($row['appointment_date'])) : '—';
                            $isDone  = in_array($stCode, ['DV', 'RT']);
                            $cleanP  = strip_html_content($row['problem_details']);

                            $rowClass  = '';
                            $timeText  = '—';
                            $timeClass = '';

                            if ($isDone) {
                                $rowClass = 'tr-done';
                            } elseif (!empty($row['appointment_date'])) {
                                $today  = strtotime(date('Y-m-d'));
                                $target = strtotime(date('Y-m-d', strtotime($row['appointment_date'])));
                                $days   = ($target - $today) / 86400;
                                if      ($days > 0)  { $timeText = 'อีก ' . number_format($days) . ' วัน'; $timeClass = 'time-ok'; }
                                elseif  ($days == 0) { $timeText = 'วันนี้'; $timeClass = 'time-warn'; $rowClass = 'tr-today'; }
                                else                 { $timeText = 'เกิน ' . number_format(abs($days)) . ' วัน'; $timeClass = 'time-danger'; $rowClass = 'tr-overdue'; }
                            }
                        ?>
                        <tr class="<?= $rowClass ?>" id="trk-row-<?= $row['id'] ?>">

                            <td>
                                <a href="edit.php?id=<?= $row['id'] ?>" class="job-link" onclick="showLoader()">
                                    <?= h($row['ticket_number']) ?>
                                </a>
                            </td>

                            <td style="font-size:12px; color:var(--text-muted); white-space:nowrap;"><?= $dateIn ?></td>

                            <td>
                                <div style="font-weight:600; font-size:13px; color:var(--text-main);">
                                    <?= h($row['customer_name']) ?>
                                </div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                    <?= h($row['customer_phone']) ?>
                                </div>
                            </td>

                            <td style="text-align:center;">
                                <div style="font-size:13px; font-weight:700; color:var(--text-main); white-space:nowrap;">
                                    <?= h($row['device_type']) ?>
                                    <span style="font-weight:400; color:var(--text-muted);"><?= h($row['device_series'] ?? '') ?></span>
                                </div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:2px;"><?= h($row['device_model']) ?></div>
                            </td>

                            <td>
                                <div style="font-family:monospace; font-size:11px; color:var(--text-muted);">
                                    SN: <?= h($row['serial_number'] ?: '—') ?>
                                </div>
                                <div style="font-family:monospace; font-size:11px; color:#dc2626; font-weight:700; margin-top:2px;">
                                    Pass: <?= h($row['device_password'] ?: '—') ?>
                                </div>
                            </td>

                            <td>
                                <span style="font-size:12px; color:#ef4444; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:inline-block; vertical-align:bottom;"
                                      title="<?= h($cleanP) ?>">
                                    <?= h($cleanP) ?>
                                </span>
                            </td>

                            <td style="font-size:12px; color:var(--text-muted); white-space:nowrap;"><?= $dateApp ?></td>

                            <td><span class="<?= $timeClass ?>"><?= $timeText ?></span></td>

                            <td style="text-align:right; font-weight:700; font-size:13px;">
                                <?= number_format($row['estimated_cost']) ?>
                            </td>

                            <td style="text-align:center;">
                                <span class="status-badge <?= $stData['class'] ?>"><?= h($stData['label']) ?></span>
                            </td>

                            <td style="text-align:center;">
                                <div style="display:flex; justify-content:center; gap:5px;">
                                    <a href="print_job.php?id=<?= $row['id'] ?>" target="_blank"
                                       class="t-btn t-print" title="พิมพ์ใบรับซ่อม">
                                        <span class="material-symbols-rounded">print</span>
                                    </a>
                                    <a href="edit.php?id=<?= $row['id'] ?>" class="t-btn t-edit"
                                       title="แก้ไข" onclick="showLoader()">
                                        <span class="material-symbols-rounded">edit</span>
                                    </a>
                                    <button type="button" class="t-btn t-del" title="ลบ"
                                        onclick="openDeleteModal(<?= $row['id'] ?>, '<?= h($row['ticket_number']) ?>')">
                                        <span class="material-symbols-rounded">delete</span>
                                    </button>
                                </div>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
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

<!-- ── Delete Modal ── -->

<div id="deleteModal" class="trk-modal-overlay">
    <div class="trk-modal">
        <div class="trk-modal-header">
            <div class="trk-modal-icon"><span class="material-symbols-rounded" style="font-size:26px;">warning</span></div>
            <h3>ยืนยันการลบ?</h3>
        </div>
        <div class="trk-modal-body">
            ลบรายการ <strong id="delTicketNum">...</strong> ใช่หรือไม่?<br>
            <span style="font-size:.85em;color:#ef4444;">ไม่สามารถย้อนกลับได้</span>
        </div>
        <div class="trk-modal-footer">
            <button type="button" class="trk-btn-cancel" onclick="closeDeleteModal()">ยกเลิก</button>
            <button id="trk-del-btn" type="button" class="trk-btn-confirm" onclick="doDeleteTracking()">ลบรายการ</button>
        </div>
    </div>
</div>

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

let _trkDelId = null;
function openDeleteModal(id, ticket) {
    _trkDelId = id;
    document.getElementById('delTicketNum').innerText = ticket;
    const m = document.getElementById('deleteModal');
    m.style.display = 'flex';
    requestAnimationFrame(() => m.classList.add('show'));
}
function closeDeleteModal() {
    const m = document.getElementById('deleteModal');
    m.classList.remove('show');
    setTimeout(() => { m.style.display = 'none'; }, 150);
}
function doDeleteTracking() {
    if (!_trkDelId) return;
    const btn = document.getElementById('trk-del-btn');
    btn.disabled = true; btn.textContent = 'กำลังลบ...';
    const fd = new FormData();
    fd.append('action','delete'); fd.append('id', _trkDelId); fd.append('ajax','1');
    fetch('', { method:'POST', body: fd }).then(r => r.json()).then(data => {
        closeDeleteModal();
        if (data.ok) {
            const row = document.getElementById('trk-row-' + _trkDelId);
            if (row) {
                row.style.transition = 'opacity .25s,transform .25s';
                row.style.opacity = '0'; row.style.transform = 'translateX(30px)';
                setTimeout(() => row.remove(), 260);
            }
        }
        btn.disabled = false; btn.textContent = 'ลบรายการ';
    });
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

function goPerPage(sel) {
    showLoader();
    const u = new URL(location.href);
    u.searchParams.set('per', sel.value);
    u.searchParams.set('page', '1');
    location = u.toString();
}

// ── Filter Menu ──
function toggleFilterMenu(e) {
    e.stopPropagation();
    const menu = document.getElementById('filterMenu');
    const btn  = document.getElementById('filterBtn');
    const open = menu.classList.toggle('open');
    btn.classList.toggle('active', open);
}
function clearFilterMenu() {
    document.querySelectorAll('#filterMenu input[type="checkbox"]').forEach(cb => cb.checked = false);
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.trk-filter-wrap')) {
        const menu = document.getElementById('filterMenu');
        const btn  = document.getElementById('filterBtn');
        if (menu) menu.classList.remove('open');
        if (btn)  btn.classList.remove('active');
    }
});
</script>
