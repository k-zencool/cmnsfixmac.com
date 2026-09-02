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

/* split "[ sym1, sym2 ] detail" → [symptoms[], clean detail] */
function trk_parse_problem($raw) {
    $symps = []; $detail = $raw ?? '';
    if (preg_match('/^\[(.*?)\](.*)/s', $detail, $m)) {
        $symps  = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
        $detail = trim($m[2]);
    }
    $detail = trim(strip_tags(preg_replace('/<\/(p|div|li)>|<br\s*\/?>/i', "\n", $detail)));
    return [$symps, $detail];
}
/* split "สภาพ: a, b | Note: xxx" → [states[], clean note] */
function trk_parse_note($raw) {
    $states = []; $note = $raw ?? '';
    if (preg_match('/^สภาพ:\s*([^|]*)(?:\|\s*(?:Note:\s*)?(.*))?$/su', $note, $m)) {
        $states = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
        $note   = trim($m[2] ?? '');
    }
    return [$states, $note];
}

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
// Overdue isn't a status list — it's an appointment-past-due condition (see query builder)
$overdueFilter = ($group === 'overdue' && empty($_GET['status']));

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
    if (!can('jobs.write')) { // ลบงานซ่อม: ช่าง+ ขึ้นไป (ยกเว้นบัญชี)
        if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'ไม่มีสิทธิ์']); exit; }
        header("Location: index.php?err=" . rawurlencode('ไม่มีสิทธิ์ลบงาน')); exit;
    }
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
if ($overdueFilter) {
    $where[] = "status NOT IN ('DV','RT','NCF','NCS') AND appointment_date IS NOT NULL AND appointment_date < CURDATE()";
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

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-logs.css') ?>">
<link rel="stylesheet" href="assets/css/tracking-index.css?v=<?= asset_ver('/admin/tracking/assets/css/tracking-index.css') ?>">

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

    <!-- ── Stat Cards (shop-style: icon + value + label) ── -->
    <div class="trk-stats" style="margin-bottom:24px;">
        <a href="index.php?group=active" class="stat-card">
            <div class="stat-icon" style="background:#eff6ff;"><span class="material-symbols-rounded" style="color:#3b82f6;">pending_actions</span></div>
            <div><div class="stat-val"><?= number_format($stats['active_count']) ?></div><div class="stat-lbl">กำลังดำเนินการ</div></div>
        </a>
        <a href="index.php?group=done" class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4;"><span class="material-symbols-rounded" style="color:#10b981;">check_circle</span></div>
            <div><div class="stat-val"><?= number_format($stats['done_count']) ?></div><div class="stat-lbl">ซ่อมเสร็จ รอรับ</div></div>
        </a>
        <a href="index.php?group=overdue" class="stat-card">
            <div class="stat-icon" style="background:#fef2f2;"><span class="material-symbols-rounded" style="color:#ef4444;">warning</span></div>
            <div><div class="stat-val"><?= number_format($stats['overdue_count']) ?></div><div class="stat-lbl">เกินกำหนดนัด</div></div>
        </a>
        <a href="index.php?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-t') ?>" class="stat-card">
            <div class="stat-icon" style="background:#fffbeb;"><span class="material-symbols-rounded" style="color:#f59e0b;">calendar_month</span></div>
            <div><div class="stat-val"><?= number_format($stats['this_month']) ?></div><div class="stat-lbl">เปิดงานเดือนนี้</div></div>
        </a>
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
        <a href="<?= $tabBase ?>group=overdue" class="log-tab <?= $group === 'overdue' && !$isManualStatus ? 'active-out' : '' ?>">
            <span class="material-symbols-rounded" style="font-size:14px; vertical-align:middle;">warning</span>
            เกินกำหนดนัด
        </a>
    </div>

    <!-- ── Table ── -->
    <div class="log-card">
        <div style="overflow-x:auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:110px;">Job No.</th>
                        <th class="col-datein" style="width:85px;">วันที่รับ</th>
                        <th>ลูกค้า</th>
                        <th style="text-align:center; width:170px;">อุปกรณ์</th>
                        <th class="col-snpass" style="width:130px;">S/N &amp; Pass</th>
                        <th class="col-problem">อาการเสีย</th>
                        <th class="col-appt" style="width:90px;">กำหนดนัด</th>
                        <th class="col-timeleft" style="width:100px;">เหลือเวลา</th>
                        <th class="col-price" style="width:88px; text-align:right;">ราคา</th>
                        <th style="width:155px; text-align:center;">สถานะ</th>
                        <th class="col-actions" style="width:110px; text-align:center;">จัดการ</th>
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
                        <?php $viewJobs = []; ?>
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

                            // Soft row tint by status (only these three); overdue/today still override
                            $stTint = ['QS' => 'tr-st-qs', 'OK' => 'tr-st-ok', 'FN' => 'tr-st-fn'][$stCode] ?? '';

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
                            [$vSymps, $vDetail] = trk_parse_problem($row['problem_details']);
                            [$vStates, $vNote]  = trk_parse_note($row['technician_note'] ?? '');
                            $viewJobs[(int)$row['id']] = [
                                'id'       => (int)$row['id'],
                                'ticket'   => $row['ticket_number'],
                                'stLabel'  => $stData['label'],
                                'stClass'  => $stData['class'],
                                'created'  => date('d/m/Y H:i', strtotime($row['created_at'])),
                                'appt'     => $row['appointment_date'] ? date('d/m/Y', strtotime($row['appointment_date'])) : null,
                                'pickup'   => !empty($row['pickup_date']) ? date('d/m/Y H:i', strtotime($row['pickup_date'])) : null,
                                'timeText' => $timeText,
                                'timeClass'=> $timeClass,
                                'name'     => $row['customer_name'],
                                'phone'    => $row['customer_phone'],
                                'device'   => trim(($row['device_type'] ?? '') . ' ' . ($row['device_series'] ?? '')),
                                'model'    => $row['device_model'],
                                'sn'       => $row['serial_number'] ?: null,
                                'pass'     => $row['device_password'] ?: null,
                                'symptoms' => $vSymps,
                                'detail'   => $vDetail !== '' ? $vDetail : null,
                                'states'   => $vStates,
                                'note'     => $vNote !== '' ? $vNote : null,
                                'accs'     => array_values(array_filter(array_map('trim', explode(',', $row['accessories'] ?? '')))),
                                'cost'     => number_format((float)$row['estimated_cost']),
                            ];
                        ?>
                        <tr class="<?= trim($rowClass . ' ' . $stTint) ?>" id="trk-row-<?= $row['id'] ?>">

                            <td>
                                <a href="#" class="job-link" onclick="openViewModal(<?= (int)$row['id'] ?>); return false;">
                                    <?= h($row['ticket_number']) ?>
                                </a>
                                <!-- folded on ≤1200px: received date -->
                                <div class="trk-fold trk-fold-sub" style="color:var(--text-muted);">รับ <?= $dateIn ?></div>
                            </td>

                            <td class="col-datein" style="font-size:12px; color:var(--text-muted); white-space:nowrap;"><?= $dateIn ?></td>

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
                                <!-- folded on ≤1200px: S/N & Pass -->
                                <div class="trk-fold trk-fold-sub" style="font-family:monospace; text-align:center; margin-top:4px;">
                                    <span style="color:var(--text-muted);">SN: <?= h($row['serial_number'] ?: '—') ?></span><br>
                                    <span style="color:#dc2626; font-weight:700;">Pass: <?= h($row['device_password'] ?: '—') ?></span>
                                </div>
                            </td>

                            <td class="col-snpass">
                                <div style="font-family:monospace; font-size:11px; color:var(--text-muted);">
                                    SN: <?= h($row['serial_number'] ?: '—') ?>
                                </div>
                                <div style="font-family:monospace; font-size:11px; color:#dc2626; font-weight:700; margin-top:2px;">
                                    Pass: <?= h($row['device_password'] ?: '—') ?>
                                </div>
                            </td>

                            <td class="col-problem">
                                <span style="font-size:12px; color:#ef4444; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:inline-block; vertical-align:bottom;"
                                      title="<?= h($cleanP) ?>">
                                    <?= h($cleanP) ?>
                                </span>
                            </td>

                            <td class="col-appt" style="font-size:12px; color:var(--text-muted); white-space:nowrap;"><?= $dateApp ?></td>

                            <td class="col-timeleft"><span class="<?= $timeClass ?>"><?= $timeText ?></span></td>

                            <td class="col-price" style="text-align:right; font-weight:700; font-size:13px;">
                                <?= number_format($row['estimated_cost']) ?>
                            </td>

                            <td style="text-align:center;">
                                <span class="status-badge <?= $stData['class'] ?>"><?= h($stData['label']) ?></span>
                                <!-- folded on ≤1200px: price + appointment + time-left -->
                                <div class="trk-fold trk-fold-sub" style="margin-top:6px;">
                                    <div style="font-weight:700; color:var(--text-main);">฿<?= number_format($row['estimated_cost']) ?></div>
                                    <div style="color:var(--text-muted);">นัด <?= $dateApp ?></div>
                                    <div><span class="<?= $timeClass ?>"><?= $timeText ?></span></div>
                                </div>
                            </td>

                            <td class="col-actions" style="text-align:center;">
                                <div style="display:flex; justify-content:center; gap:5px;">
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

<!-- ── View Job Modal (read-only detail card) ── -->
<div id="viewModal" class="trk-modal-overlay">
    <div class="trk-view">
        <header class="trk-view-hd">
            <div class="trk-view-hd-l">
                <span class="trk-view-ticket" id="vm-ticket"></span>
                <span class="status-badge" id="vm-status"></span>
            </div>
            <button type="button" class="trk-view-close" onclick="closeViewModal()" aria-label="ปิด">
                <span class="material-symbols-rounded">close</span>
            </button>
        </header>

        <div class="trk-view-body">

            <!-- meta tiles -->
            <div class="trk-view-meta">
                <div><label>วันที่รับ</label><b id="vm-created"></b></div>
                <div><label>นัดหมาย</label><b><span id="vm-appt"></span> <span id="vm-time"></span></b></div>
                <div><label>รับเครื่องคืน</label><b id="vm-pickup"></b></div>
                <div><label>ราคาประเมิน</label><b id="vm-cost" class="trk-view-cost"></b></div>
            </div>

            <section class="trk-view-sec">
                <label>ลูกค้า</label>
                <div class="trk-view-line"><b id="vm-name"></b> · <a id="vm-phone" href="#"></a></div>
            </section>

            <section class="trk-view-sec">
                <label>อุปกรณ์</label>
                <div class="trk-view-line"><b id="vm-device"></b> <span id="vm-model" class="trk-view-dim"></span></div>
                <div class="trk-view-line trk-view-mono">SN: <span id="vm-sn"></span></div>
                <div class="trk-view-line trk-view-mono trk-view-pass">Pass: <span id="vm-pass"></span></div>
            </section>

            <section class="trk-view-sec" id="vm-sec-problem">
                <label>อาการเสีย</label>
                <div class="trk-view-tags trk-tags-red" id="vm-symptoms"></div>
                <p class="trk-view-p" id="vm-detail"></p>
            </section>

            <section class="trk-view-sec" id="vm-sec-recv">
                <label>ตรวจรับเครื่อง</label>
                <div class="trk-view-tags" id="vm-accs"></div>
                <div class="trk-view-tags trk-tags-amber" id="vm-states"></div>
                <p class="trk-view-p" id="vm-note"></p>
            </section>

        </div>

        <footer class="trk-view-ft">
            <button type="button" class="trk-btn-cancel" onclick="closeViewModal()">ปิด</button>
            <a id="vm-edit" href="#" class="trk-view-editbtn" onclick="showLoader()">
                <span class="material-symbols-rounded">edit</span> แก้ไขงานนี้
            </a>
        </footer>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
/* ── View job modal ── */
const trkJobs = <?= json_encode($viewJobs ?? [], JSON_UNESCAPED_UNICODE) ?>;

function _fillTags(elId, items) {
    const box = document.getElementById(elId);
    box.innerHTML = '';
    (items || []).forEach(t => {
        const s = document.createElement('span');
        s.textContent = t;
        box.appendChild(s);
    });
    box.style.display = (items && items.length) ? '' : 'none';
}
function _fillP(elId, text) {
    const p = document.getElementById(elId);
    p.textContent = text || '';
    p.style.display = text ? '' : 'none';
}

function openViewModal(id) {
    const j = trkJobs[id];
    if (!j) return;

    document.getElementById('vm-ticket').textContent = j.ticket;
    const st = document.getElementById('vm-status');
    st.textContent = j.stLabel;
    st.className = 'status-badge ' + j.stClass;

    document.getElementById('vm-created').textContent = j.created;
    document.getElementById('vm-appt').textContent    = j.appt || '—';
    const tm = document.getElementById('vm-time');
    tm.textContent = (j.appt && j.timeText !== '—') ? '(' + j.timeText + ')' : '';
    tm.className   = j.timeClass || '';
    document.getElementById('vm-pickup').textContent  = j.pickup || '—';
    document.getElementById('vm-cost').textContent    = '฿' + j.cost;

    document.getElementById('vm-name').textContent  = j.name;
    const ph = document.getElementById('vm-phone');
    ph.textContent = j.phone; ph.href = 'tel:' + (j.phone || '').replace(/[^0-9+]/g, '');

    document.getElementById('vm-device').textContent = j.device || '—';
    document.getElementById('vm-model').textContent  = j.model || '';
    document.getElementById('vm-sn').textContent     = j.sn || '—';
    document.getElementById('vm-pass').textContent   = j.pass || '—';

    _fillTags('vm-symptoms', j.symptoms);
    _fillP('vm-detail', j.detail);
    document.getElementById('vm-sec-problem').style.display =
        (j.symptoms.length || j.detail) ? '' : 'none';

    _fillTags('vm-accs', j.accs);
    _fillTags('vm-states', j.states);
    _fillP('vm-note', j.note);
    document.getElementById('vm-sec-recv').style.display =
        (j.accs.length || j.states.length || j.note) ? '' : 'none';

    document.getElementById('vm-edit').href = 'edit.php?id=' + j.id;

    const m = document.getElementById('viewModal');
    m.style.display = 'flex';
    requestAnimationFrame(() => m.classList.add('show'));
}
function closeViewModal() {
    const m = document.getElementById('viewModal');
    m.classList.remove('show');
    setTimeout(() => { m.style.display = 'none'; }, 150);
}
document.getElementById('viewModal').addEventListener('click', function(e) {
    if (e.target === this) closeViewModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeViewModal();
});

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
