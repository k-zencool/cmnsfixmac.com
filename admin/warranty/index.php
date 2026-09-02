<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function war_page_url($i){ $q = $_GET; $q['page'] = max(1, (int)$i); return '?' . http_build_query($q); }

$pageTitle = "ใบรับประกัน";

w_sync_expired($pdo);

$status_filter = $_GET['status'] ?? 'all';
$q             = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];

if ($status_filter !== 'all') {
    $where[]  = 'w.status = ?';
    $params[] = $status_filter;
}
if ($q !== '') {
    $where[]  = '(w.warranty_no LIKE ? OR w.customer_name LIKE ? OR w.customer_phone LIKE ? OR w.serial_no LIKE ? OR w.device_model LIKE ?)';
    $like     = "%$q%";
    array_push($params, $like, $like, $like, $like, $like);
}

$where_sql = implode(' AND ', $where);

// ── Pagination ──
$per  = max(10, min(200, (int)($_GET['per'] ?? 25)));
$page = max(1, (int)($_GET['page'] ?? 1));

$cst = $pdo->prepare("SELECT COUNT(*) FROM warranties w LEFT JOIN tracking t ON t.id = w.tracking_id WHERE $where_sql");
$cst->execute($params);
$total  = (int)$cst->fetchColumn();
$pages  = max(1, (int)ceil($total / $per));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $per;

$rows = $pdo->prepare("SELECT w.*, t.ticket_number
                        FROM warranties w
                        LEFT JOIN tracking t ON t.id = w.tracking_id
                        WHERE $where_sql
                        ORDER BY w.id DESC
                        LIMIT $per OFFSET $offset");
$rows->execute($params);
$warranties = $rows->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query("SELECT status, COUNT(*) AS cnt FROM warranties GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$cnt    = array_column($counts, 'cnt', 'status');
$cnt['all'] = array_sum($cnt);

$flash = $_SESSION['success'] ?? null;
unset($_SESSION['success']);

include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="<?= $assets_base ?>css/inventory-dashboard.css?v=<?= asset_ver('/admin/templates/assets/css/inventory-dashboard.css') ?>">
<link rel="stylesheet" href="<?= $assets_base ?>css/modal.css?v=<?= asset_ver('/admin/templates/assets/css/modal.css') ?>">
<style>
/* ── shared form components ── */
.cmns-label { font-size:11px; font-weight:800; color:var(--text-muted); margin-bottom:6px; display:block; text-transform:uppercase; letter-spacing:.5px; }
.cmns-input { width:100%; background:var(--bg-surface-alt); border:1px solid var(--border); color:var(--text-main); padding:11px 13px; border-radius:10px; font-size:13px; outline:none; transition:all .2s; font-family:inherit; }
.cmns-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); background:var(--bg-surface); }
textarea.cmns-input { resize:vertical; min-height:72px; }
.cmns-alert { display:flex; align-items:center; gap:10px; padding:10px 16px; border-radius:10px; font-size:.88rem; margin-bottom:14px; }
.cmns-alert-success { background:rgba(16,185,129,.1); color:#065f46; border:1px solid rgba(16,185,129,.3); }
.cmns-alert-danger  { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
/* ── warranty pages ── */
.war-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
.war-stats  { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
.war-stat   { background:var(--bg-surface); border:1px solid var(--border); border-radius:12px; padding:16px 20px; display:flex; align-items:center; gap:12px; }
.war-stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.war-stat-icon .material-symbols-rounded { font-size:21px; }
.war-stat-val { font-size:1.5rem; font-weight:800; color:var(--text-main); line-height:1; }
.war-stat-lbl { font-size:0.78rem; color:var(--text-muted); margin-top:3px; }
.war-filters  { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.war-filter   { padding:6px 16px; border-radius:20px; border:1.5px solid var(--border); background:var(--bg-surface); cursor:pointer; font-size:0.84rem; font-weight:600; color:var(--text-muted); text-decoration:none; transition:.15s; display:inline-flex; align-items:center; gap:6px; }
.war-filter:hover { border-color:var(--primary); color:var(--primary); }
.war-filter.active { background:var(--primary); border-color:var(--primary); color:#fff; }
.war-search  { display:flex; gap:10px; margin-bottom:18px; }
.war-search-wrap { flex:1; position:relative; }
.war-search-wrap .material-symbols-rounded { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:20px; pointer-events:none; }
.war-search-input { width:100%; padding:10px 12px 10px 40px; border:1.5px solid var(--border); border-radius:8px; font-size:0.95rem; background:var(--bg-surface); color:var(--text-main); }
.war-search-input:focus { outline:none; border-color:var(--primary); }
.war-table-wrap { background:var(--bg-surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
.war-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
.war-table th { padding:11px 14px; text-align:left; font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); border-bottom:1px solid var(--border); background:var(--bg-surface-alt); }
.war-table td { padding:12px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
.war-table tr:last-child td { border-bottom:none; }
.war-table tr:hover td { background:var(--bg-surface-alt); }
.status-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:600; border:1px solid transparent; white-space:nowrap; }
.war-no { font-weight:700; font-family:monospace; font-size:0.9rem; color:var(--primary); }
.war-device { font-size:0.82rem; color:var(--text-muted); margin-top:2px; }
.war-days-left { font-size:0.8rem; font-weight:700; }
.war-days-left.ok { color:#059669; }
.war-days-left.warn { color:#b45309; }
.war-days-left.over { color:var(--text-muted); }
.war-actions { display:flex; gap:6px; justify-content:flex-end; }

/* ── Action buttons (มาตรฐานเดียวกับหน้าอื่น: .t-btn) ── */
.t-btn { width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; border-radius:7px; border:1px solid var(--border); background:var(--bg-surface-alt); color:var(--text-muted); cursor:pointer; transition:all .18s; padding:0; flex-shrink:0; text-decoration:none; }
.t-btn .material-symbols-rounded { font-size:16px; line-height:1; }
.t-btn:hover { transform:translateY(-1px); box-shadow:0 3px 8px rgba(0,0,0,.1); border-color:var(--primary); color:var(--primary); background:rgba(37,99,235,.07); }
.t-edit:hover { color:var(--primary); background:rgba(37,99,235,.07); border-color:var(--primary); }
.t-del:hover  { color:#ef4444; background:rgba(239,68,68,.07); border-color:#ef4444; }

/* ── Pagination (มาตรฐานเดียวกับหน้าอื่น) ── */
.log-pagination { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; font-size:13px; color:var(--text-muted); border-top:1px solid var(--border); flex-wrap:wrap; gap:10px; }
.page-btns { display:flex; gap:5px; }
.page-btn { min-width:36px; height:36px; padding:0 10px; border-radius:9px; border:1px solid var(--border); background:var(--bg-surface-alt); color:var(--text-main); font-size:13px; text-decoration:none; font-weight:600; transition:.2s; display:inline-flex; align-items:center; justify-content:center; }
.page-btn:hover:not(.disabled) { border-color:var(--primary); color:var(--primary); background:rgba(37,99,235,.06); }
.page-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); box-shadow:0 2px 8px rgba(37,99,235,.3); }
.page-btn.disabled { opacity:.3; pointer-events:none; }

@media(max-width:768px){ .war-stats { grid-template-columns:repeat(2,1fr); } }
@media(max-width:640px){ .log-pagination { flex-direction:column; align-items:flex-start; } }
</style>

<div class="main-content">
<?php if ($flash): ?>
    <div class="cmns-alert cmns-alert-success" style="margin-bottom:16px;">
        <span class="material-symbols-rounded">check_circle</span> <?= h($flash) ?>
    </div>
<?php endif; ?>

<div class="war-header">
    <div>
        <h1 class="page-title" style="margin:0;">
            <span class="material-symbols-rounded" style="vertical-align:middle;">verified_user</span>
            ใบรับประกัน
        </h1>
    </div>
    <button onclick="openCreateModal()" class="cmns-btn cmns-btn-primary">
        <span class="material-symbols-rounded">add</span> ออกใบประกันใหม่
    </button>
</div>

<!-- Stats -->
<div class="war-stats">
    <div class="war-stat">
        <div class="war-stat-icon" style="background:rgba(37,99,235,.1);">
            <span class="material-symbols-rounded" style="color:var(--primary);">verified_user</span>
        </div>
        <div>
            <div class="war-stat-val"><?= $cnt['all'] ?? 0 ?></div>
            <div class="war-stat-lbl">ทั้งหมด</div>
        </div>
    </div>
    <div class="war-stat">
        <div class="war-stat-icon" style="background:rgba(16,185,129,.1);">
            <span class="material-symbols-rounded" style="color:#059669;">check_circle</span>
        </div>
        <div>
            <div class="war-stat-val"><?= $cnt['active'] ?? 0 ?></div>
            <div class="war-stat-lbl">ใช้งานได้</div>
        </div>
    </div>
    <div class="war-stat">
        <div class="war-stat-icon" style="background:rgba(107,114,128,.1);">
            <span class="material-symbols-rounded" style="color:var(--text-muted);">schedule</span>
        </div>
        <div>
            <div class="war-stat-val"><?= $cnt['expired'] ?? 0 ?></div>
            <div class="war-stat-lbl">หมดอายุ</div>
        </div>
    </div>
    <div class="war-stat">
        <div class="war-stat-icon" style="background:rgba(239,68,68,.1);">
            <span class="material-symbols-rounded" style="color:#dc2626;">block</span>
        </div>
        <div>
            <div class="war-stat-val"><?= $cnt['voided'] ?? 0 ?></div>
            <div class="war-stat-lbl">ยกเลิกแล้ว</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="war-filters">
    <?php
    $filters = ['all'=>'ทั้งหมด','active'=>'ใช้งานได้','expired'=>'หมดอายุ','voided'=>'ยกเลิก'];
    foreach ($filters as $k => $label):
        $active = $status_filter === $k ? 'active' : '';
        $n      = $cnt[$k] ?? 0;
        $url    = '?' . http_build_query(array_merge($_GET, ['status'=>$k, 'page'=>1]));
    ?>
    <a href="<?= h($url) ?>" class="war-filter <?= $active ?>"><?= $label ?> <span style="opacity:.6;">(<?= $n ?>)</span></a>
    <?php endforeach; ?>
</div>

<!-- Search -->
<form class="war-search" method="get">
    <input type="hidden" name="status" value="<?= h($status_filter) ?>">
    <div class="war-search-wrap">
        <span class="material-symbols-rounded">search</span>
        <input type="text" name="q" class="war-search-input" placeholder="ค้นหา เลขประกัน / ชื่อ / โทร / Serial / เครื่อง…" value="<?= h($q) ?>">
    </div>
    <button class="cmns-btn cmns-btn-primary" type="submit">ค้นหา</button>
    <?php if ($q): ?><a href="?status=<?= h($status_filter) ?>" class="cmns-btn cmns-btn-secondary">ล้าง</a><?php endif; ?>
</form>

<!-- Table -->
<div class="war-table-wrap">
    <table class="war-table">
        <thead>
            <tr>
                <th>เลขประกัน</th>
                <th>ลูกค้า</th>
                <th>เครื่อง / Serial</th>
                <th>ระยะประกัน</th>
                <th>วันหมดอายุ</th>
                <th>สถานะ</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($warranties)): ?>
            <tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">ไม่มีข้อมูล</td></tr>
        <?php else: foreach ($warranties as $w):
            $days_left = w_days_left($w['end_date']);
            if ($w['status'] === 'active') {
                $days_cls  = $days_left > 30 ? 'ok' : ($days_left > 0 ? 'warn' : 'over');
                $days_txt  = $days_left > 0 ? "เหลือ $days_left วัน" : "หมดแล้ว";
            } else {
                $days_cls = 'over';
                $days_txt = '-';
            }
        ?>
            <tr>
                <td>
                    <div class="war-no"><?= h($w['warranty_no']) ?></div>
                    <?php if ($w['ticket_number']): ?>
                        <div class="war-device">
                            <a href="../tracking/edit.php?id=<?= $w['tracking_id'] ?>" style="color:var(--text-muted); font-size:0.78rem;" target="_blank">
                                <span class="material-symbols-rounded" style="font-size:12px; vertical-align:middle;">link</span>
                                <?= h($w['ticket_number']) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <div><?= h($w['customer_name']) ?></div>
                    <div style="font-size:0.8rem; color:var(--text-muted);"><?= h($w['customer_phone']) ?></div>
                </td>
                <td>
                    <div><?= h($w['device_model']) ?></div>
                    <?php if ($w['serial_no']): ?>
                        <div class="war-device"><?= h($w['serial_no']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-weight:600;"><?= $w['warranty_days'] ?> วัน</td>
                <td>
                    <div><?= date('d/m/Y', strtotime($w['end_date'])) ?></div>
                    <div class="war-days-left <?= $days_cls ?>"><?= $days_txt ?></div>
                </td>
                <td><?= w_status_badge($w['status']) ?></td>
                <td>
                    <div class="war-actions" style="justify-content:flex-end;">
                        <a href="view.php?id=<?= $w['id'] ?>" class="t-btn" title="ดู">
                            <span class="material-symbols-rounded">visibility</span>
                        </a>
                        <?php if (can('content.write')): ?>
                        <a href="edit.php?id=<?= $w['id'] ?>" class="t-btn t-edit" title="แก้ไข">
                            <span class="material-symbols-rounded">edit</span>
                        </a>
                        <?php endif; ?>
                        <a href="print.php?id=<?= $w['id'] ?>" target="_blank" class="t-btn" title="พิมพ์ใบประกัน">
                            <span class="material-symbols-rounded">print</span>
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <?php if ($total > 0): ?>
    <div class="log-pagination">
        <div>
            แสดง <b><?= number_format(min($total, $offset+1)) ?>–<?= number_format(min($total, $offset+$per)) ?></b>
            จาก <b><?= number_format($total) ?></b> รายการ
            &nbsp;·&nbsp; หน้า <?= $page ?> / <?= $pages ?>
        </div>
        <div class="page-btns">
            <a href="<?= $page > 1 ? war_page_url($page-1) : '#' ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">
                <span class="material-symbols-rounded" style="font-size:16px;">chevron_left</span>
            </a>
            <?php
            $pStart = max(1, $page - 2);
            $pEnd   = min($pages, $pStart + 4);
            for ($p = $pStart; $p <= $pEnd; $p++): ?>
                <a href="<?= war_page_url($p) ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="<?= $page < $pages ? war_page_url($page+1) : '#' ?>" class="page-btn <?= $page>=$pages?'disabled':'' ?>">
                <span class="material-symbols-rounded" style="font-size:16px;">chevron_right</span>
            </a>
            <select onchange="goPerPage(this)"
                    style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface);color:var(--text-main);font-size:13px;outline:none;cursor:pointer;font-family:'Sarabun',sans-serif;">
                <?php foreach([25,50,100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>

<script>
function goPerPage(sel) {
    const u = new URL(location.href);
    u.searchParams.set('per', sel.value);
    u.searchParams.set('page', '1');
    location = u.toString();
}
</script>

<!-- ══════════════════════════════════════════
     CREATE WARRANTY MODAL
══════════════════════════════════════════ -->
<div id="modal-create-warranty" class="cmns-modal">
    <div class="modal-content" style="max-width:620px; padding:30px;">

        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:18px; margin-bottom:24px;">
            <h3 style="margin:0; display:flex; align-items:center; gap:10px; font-weight:800; font-size:1.15rem;">
                <span class="material-symbols-rounded" style="color:var(--primary); font-size:26px;">verified_user</span>
                ออกใบรับประกัน
            </h3>
            <button class="modal-close-btn" onclick="closeCreateModal()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>

        <!-- Error -->
        <div id="cw-err" style="display:none; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; color:#dc2626; font-size:0.88rem; margin-bottom:16px; align-items:center; gap:8px;">
            <span class="material-symbols-rounded" style="font-size:18px; flex-shrink:0;">error</span>
            <span id="cw-err-txt"></span>
        </div>

        <!-- Tracking lookup -->
        <div style="margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid var(--border);">
            <label class="cmns-label" style="margin-bottom:8px; display:block;">
                <span class="material-symbols-rounded" style="font-size:16px; vertical-align:middle;">link</span>
                ผูกกับงานซ่อม (ไม่บังคับ) — พิมพ์ Ticket No. แล้วกดดึงข้อมูล
            </label>
            <div style="display:flex; gap:8px;">
                <input type="text" id="cw-ticket" class="cmns-input" placeholder="TRK-2026-0001" style="flex:1;">
                <button type="button" class="cmns-btn cmns-btn-secondary" onclick="cwLookup()">
                    <span class="material-symbols-rounded">search</span> ดึงข้อมูล
                </button>
            </div>
            <div id="cw-lookup-result" style="display:none; margin-top:8px; padding:8px 12px; background:rgba(37,99,235,.06); border:1px solid rgba(37,99,235,.2); border-radius:8px; font-size:0.84rem;"></div>
        </div>

        <!-- Fields -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div>
                <label class="cmns-label">ชื่อลูกค้า <span style="color:#ef4444;">*</span></label>
                <input type="text" id="cw-cname" class="cmns-input" required>
            </div>
            <div>
                <label class="cmns-label">เบอร์โทร</label>
                <input type="text" id="cw-cphone" class="cmns-input">
            </div>
            <div>
                <label class="cmns-label">รุ่นเครื่อง <span style="color:#ef4444;">*</span></label>
                <input type="text" id="cw-device" class="cmns-input" required>
            </div>
            <div>
                <label class="cmns-label">Serial Number</label>
                <input type="text" id="cw-serial" class="cmns-input">
            </div>
            <div style="grid-column:1/-1;">
                <label class="cmns-label">สรุปงานที่ซ่อม</label>
                <textarea id="cw-summary" class="cmns-input" rows="2" placeholder="เช่น เปลี่ยนแบตเตอรี่ / ซ่อมจอ..."></textarea>
            </div>
        </div>

        <!-- Warranty days -->
        <div style="margin-top:20px;">
            <label class="cmns-label" style="display:block; margin-bottom:10px;">ระยะเวลารับประกัน</label>
            <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:8px;">
                <?php foreach ([30=>'1 เดือน',60=>'2 เดือน',90=>'3 เดือน',180=>'6 เดือน',365=>'1 ปี'] as $d=>$lbl): ?>
                <div>
                    <input type="radio" name="cw_days" id="cwd_<?= $d ?>" value="<?= $d ?>"
                           <?= $d===90?'checked':'' ?> style="display:none;" onchange="cwRecalc()">
                    <label for="cwd_<?= $d ?>" class="cw-days-lbl">
                        <?= $d ?> <span style="display:block;font-size:0.7rem;font-weight:400;opacity:.7;"><?= $lbl ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Dates -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:16px;">
            <div>
                <label class="cmns-label">วันที่เริ่มประกัน</label>
                <input type="date" id="cw-start" class="cmns-input" value="<?= date('Y-m-d') ?>" onchange="cwRecalc()">
            </div>
            <div>
                <label class="cmns-label">วันหมดประกัน</label>
                <input type="text" id="cw-end-disp" class="cmns-input" readonly style="background:var(--bg-surface-alt); color:var(--text-muted);">
            </div>
        </div>

        <!-- Footer -->
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:24px; border-top:1px solid var(--border); padding-top:18px;">
            <button type="button" onclick="closeCreateModal()" class="cmns-btn cmns-btn-secondary">ยกเลิก</button>
            <button type="button" onclick="cwSubmit()" id="cw-submit-btn" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">verified_user</span> ออกใบประกัน
            </button>
        </div>
    </div>
</div>

<style>
.cw-days-lbl {
    display:block; padding:9px 6px; border:2px solid var(--border);
    border-radius:10px; text-align:center; cursor:pointer;
    font-size:0.9rem; font-weight:700; transition:.15s; line-height:1.3;
}
input[name="cw_days"]:checked + .cw-days-lbl {
    border-color:var(--primary); background:var(--primary-light); color:var(--primary);
}
.cw-days-lbl:hover { border-color:var(--primary); }
@keyframes cw-spin { to { transform:rotate(360deg); } }
</style>

<script>
let cwTrackingId = null;

function openCreateModal() {
    // reset
    cwTrackingId = null;
    ['cw-cname','cw-cphone','cw-device','cw-serial','cw-summary','cw-ticket'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('cw-lookup-result').style.display = 'none';
    document.getElementById('cw-err').style.display = 'none';
    document.getElementById('cw-start').value = '<?= date('Y-m-d') ?>';
    document.querySelector('input[name="cw_days"][value="90"]').checked = true;
    cwRecalc();
    document.getElementById('modal-create-warranty').classList.add('show');
    setTimeout(() => document.getElementById('cw-cname').focus(), 300);
}

function closeCreateModal() {
    document.getElementById('modal-create-warranty').classList.remove('show');
}

function cwRecalc() {
    const start = document.getElementById('cw-start').value;
    const days  = parseInt(document.querySelector('input[name="cw_days"]:checked')?.value || 90);
    if (!start) return;
    const d = new Date(start);
    d.setDate(d.getDate() + days);
    document.getElementById('cw-end-disp').value =
        d.toLocaleDateString('th-TH', {day:'2-digit', month:'2-digit', year:'numeric'});
}

async function cwLookup() {
    const q   = document.getElementById('cw-ticket').value.trim();
    const box = document.getElementById('cw-lookup-result');
    if (!q) return;
    box.style.display = 'block';
    box.innerHTML = '<span style="color:var(--text-muted);">กำลังค้นหา...</span>';

    const res  = await fetch(`ajax.php?action=lookup_tracking&q=${encodeURIComponent(q)}`);
    const data = await res.json();

    if (data.ok) {
        cwTrackingId = data.id;
        document.getElementById('cw-cname').value  = data.customer_name;
        document.getElementById('cw-cphone').value = data.customer_phone;
        document.getElementById('cw-device').value = data.device_model.trim();
        document.getElementById('cw-serial').value = data.serial_no || '';
        box.innerHTML = `<span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle;color:#059669;">check_circle</span>
                         ดึงข้อมูลจาก <strong>${data.ticket_number}</strong> — ${data.customer_name} / ${data.device_model.trim()}`;
        box.style.background = 'rgba(16,185,129,.06)';
        box.style.borderColor = 'rgba(16,185,129,.3)';
    } else {
        cwTrackingId = null;
        box.innerHTML = `<span style="color:#dc2626;">ไม่พบงานซ่อม "${q}"</span>`;
        box.style.background = 'rgba(239,68,68,.06)';
        box.style.borderColor = 'rgba(239,68,68,.2)';
    }
}

async function cwSubmit() {
    const errBox = document.getElementById('cw-err');
    const btn    = document.getElementById('cw-submit-btn');
    errBox.style.display = 'none';

    const cname  = document.getElementById('cw-cname').value.trim();
    const device = document.getElementById('cw-device').value.trim();
    if (!cname || !device) {
        document.getElementById('cw-err-txt').textContent = 'กรุณากรอกชื่อลูกค้าและรุ่นเครื่อง';
        errBox.style.display = 'flex';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="animation:cw-spin 1s linear infinite;">sync</span> กำลังออกใบ...';

    const body = new URLSearchParams({
        action:         'create_warranty',
        tracking_id:    cwTrackingId || '',
        customer_name:  cname,
        customer_phone: document.getElementById('cw-cphone').value.trim(),
        device_model:   device,
        serial_no:      document.getElementById('cw-serial').value.trim(),
        repair_summary: document.getElementById('cw-summary').value.trim(),
        warranty_days:  document.querySelector('input[name="cw_days"]:checked')?.value || 90,
        start_date:     document.getElementById('cw-start').value,
    });

    try {
        const res  = await fetch('ajax.php', {method:'POST', body});
        const data = await res.json();
        if (data.ok) {
            window.location.href = data.view_url;
        } else {
            document.getElementById('cw-err-txt').textContent = data.msg || 'เกิดข้อผิดพลาด';
            errBox.style.display = 'flex';
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-rounded">verified_user</span> ออกใบประกัน';
        }
    } catch(e) {
        document.getElementById('cw-err-txt').textContent = 'ไม่สามารถเชื่อมต่อได้';
        errBox.style.display = 'flex';
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-rounded">verified_user</span> ออกใบประกัน';
    }
}

document.getElementById('modal-create-warranty').addEventListener('click', function(e) {
    if (e.target === this) closeCreateModal();
});

// Enter key on ticket input
document.getElementById('cw-ticket').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); cwLookup(); }
});

cwRecalc();
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
