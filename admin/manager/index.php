<?php
/********************************************************************
 * admin/manager/index.php
 * งานค้าง — เครื่องที่ยังอยู่ในร้าน เรียงจากค้างนานสุด (layout ตามหน้า tracking)
 *   กลุ่ม "ต้องทำ"   : QS รอเช็คราคา / WC รอคอนเฟิร์ม / OK กำลังซ่อม / RW งานแก้
 *   กลุ่ม "รอลูกค้า" : FN เสร็จรอรับ / XX ยกเลิกรอรับคืน / NCF·NCS ติดต่อไม่ได้
 ********************************************************************/
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

require_login();
require_perms(['manager.center']);

require_once __DIR__ . '/_status.php';

$F = mgr_fetch_stuck_jobs($pdo, $STATUS, true);
$group = $F['group']; $st = $F['st']; $dev = $F['dev']; $q = $F['q'];
$jobs  = $F['jobs'];  $total = $F['total']; $per = $F['per'];
$page  = $F['page'];  $pages = $F['pages']; $offset = $F['offset'];

$group_codes   = array_keys(array_filter($STATUS, function ($m) use ($group) { return $m['group'] === $group; }));
$device_counts = mgr_device_counts($pdo, $STATUS, $group, $st);

// ── นับต่อ status (chips) ──
$all_codes = array_keys($STATUS);
$in_all = "'" . implode("','", $all_codes) . "'";
$counts = $pdo->query("SELECT status, COUNT(*) c FROM tracking WHERE status IN ($in_all) GROUP BY status")
              ->fetchAll(PDO::FETCH_KEY_PAIR);
$cnt_todo    = 0;
$cnt_waiting = 0;
foreach ($STATUS as $code => $m) {
    if ($m['group'] === 'todo') $cnt_todo += (int)($counts[$code] ?? 0);
    else $cnt_waiting += (int)($counts[$code] ?? 0);
}

$pageTitle = "งานค้าง — Manager";
include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time(); ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= time(); ?>">
<style>
/* ── ระยะขอบเท่าหน้า tracking ── */
.content-padding { padding: 14px 14px 30px !important; }

/* ── status chips ── */
.mgr-chips { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; align-items:center; }
.mgr-chip {
    display:inline-flex; align-items:center; gap:7px;
    padding:7px 14px; border-radius:20px; text-decoration:none;
    font-size:12.5px; font-weight:700;
    background:var(--bg-surface); border:1px solid var(--border); color:var(--text-muted);
    transition:all .15s;
}
.mgr-chip:hover { transform:translateY(-1px); }
.mgr-chip.active { border-width:1.5px; }
.mgr-chip .dot { width:8px; height:8px; border-radius:50%; }
.mgr-chip .n { font-weight:800; color:var(--text-main); }

/* ── table bits ── */
.mgr-st {
    display:inline-flex; align-items:center; gap:6px;
    padding:3px 10px; border-radius:20px; font-size:11px; font-weight:800; white-space:nowrap;
}
.mgr-ticket { font-family:'Courier New', monospace; font-weight:800; font-size:13px; color:var(--primary); text-decoration:none; white-space:nowrap; }
.mgr-ticket:hover { text-decoration:underline; }
.days-badge {
    display:inline-block; min-width:52px; text-align:center;
    padding:5px 10px; border-radius:9px; font-size:15px; font-weight:800;
}
.days-ok    { background:rgba(16,185,129,.1);  color:#10b981; }
.days-warn  { background:rgba(245,158,11,.12); color:#f59e0b; }
.days-late  { background:rgba(239,68,68,.1);   color:#ef4444; }
.appt-late  { color:#ef4444; font-weight:700; }

/* ── icon buttons (ตาม trk-icon-btn ของ tracking) ── */
.mgr-icon-btn {
    width:42px; height:42px; border-radius:10px; border:1px solid var(--border);
    background:var(--primary); color:#fff; cursor:pointer;
    display:inline-flex; align-items:center; justify-content:center; text-decoration:none;
    transition:all .15s;
}
.mgr-icon-btn:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(37,99,235,.3); }
.mgr-icon-btn.reset { background:var(--bg-surface); color:var(--text-muted); }
.mgr-icon-btn.reset:hover { color:#ef4444; border-color:#ef4444; box-shadow:none; }

/* ── print modal ── */
.mgr-modal {
    display:none; position:fixed; inset:0; z-index:9999;
    background:rgba(0,0,0,.5); backdrop-filter:blur(6px);
    align-items:center; justify-content:center; padding:20px;
    opacity:0; transition:opacity .2s ease;
}
.mgr-modal.show { display:flex; opacity:1; }
.mgr-modal-box {
    background:var(--bg-surface); border:1px solid var(--border); border-radius:16px;
    width:100%; max-width:860px; height:88vh;
    display:flex; flex-direction:column; overflow:hidden;
    box-shadow:0 20px 40px rgba(0,0,0,.25);
    transform:translateY(16px) scale(.97); transition:transform .2s ease;
}
.mgr-modal.show .mgr-modal-box { transform:translateY(0) scale(1); }
.mgr-modal-head {
    display:flex; justify-content:space-between; align-items:center;
    padding:14px 20px; border-bottom:1px solid var(--border); flex-shrink:0;
}
.mgr-modal-head h3 { margin:0; font-size:16px; font-weight:800; color:var(--text-main); display:flex; align-items:center; gap:8px; }
.mgr-modal-close { background:transparent; border:none; color:var(--text-muted); cursor:pointer; padding:6px; border-radius:8px; display:flex; }
.mgr-modal-close:hover { background:var(--bg-surface-alt); color:#ef4444; }
#print-frame { flex:1; width:100%; border:none; background:#fff; }
.mgr-modal-foot {
    display:flex; justify-content:flex-end; gap:10px;
    padding:12px 20px; border-top:1px solid var(--border); background:var(--bg-surface-alt); flex-shrink:0;
}

@media (max-width: 768px) {
    .col-appt, .col-recv { display:none; }
    .mgr-modal { padding:8px; }
    .mgr-modal-box { height:94vh; }
}
</style>

<div class="cmns-wrapper">

    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title">
                <span class="material-symbols-rounded" style="font-size:32px;">pending_actions</span>
                งานค้าง
            </h1>
            <p style="color:var(--text-muted); margin-top:5px; font-size:14px;">
                เครื่องที่ยังอยู่ในร้าน เรียงจากค้างนานสุด · เขียว ≤ 3 วัน · เหลือง 4–7 วัน · แดง > 7 วัน
            </p>
        </div>
        <div class="cmns-action-buttons">
            <a href="../tracking/" class="cmns-btn cmns-btn-secondary">
                <span class="material-symbols-rounded">build</span> TRACKING
            </a>
            <button type="button" onclick="openPrintModal()" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">print</span> พิมพ์ TODO
            </button>
        </div>
    </div>

    <!-- ── Filter bar (pattern เดียวกับ tracking) ── -->
    <form method="GET" action="index.php">
        <input type="hidden" name="group" value="<?= $group ?>">
        <?php if ($st !== ''): ?><input type="hidden" name="st" value="<?= htmlspecialchars($st) ?>"><?php endif; ?>
        <?php if ($per !== 20): ?><input type="hidden" name="per" value="<?= $per ?>"><?php endif; ?>
        <div class="log-filter-bar">
            <div class="log-filter-group" style="flex:1; min-width:220px;">
                <label>ค้นหา</label>
                <div class="log-search-wrap">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Job / ชื่อ / เบอร์ / รุ่น / S/N">
                </div>
            </div>

            <div class="log-filter-group">
                <label>ประเภทเครื่อง</label>
                <select name="dev">
                    <option value="">ทุกประเภท</option>
                    <?php foreach ($device_counts as $d => $c): ?>
                    <option value="<?= htmlspecialchars($d) ?>" <?= $dev === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?> (<?= $c ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="mgr-icon-btn" title="ค้นหา" style="align-self:flex-end;">
                <span class="material-symbols-rounded">search</span>
            </button>

            <?php if ($q !== '' || $dev !== '' || $st !== ''): ?>
            <a href="index.php?group=<?= $group ?>" class="mgr-icon-btn reset" title="ล้างค่าทั้งหมด" style="align-self:flex-end;">
                <span class="material-symbols-rounded">close</span>
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- ── Group Tabs ── -->
    <div class="log-tabs">
        <a href="<?= mgr_url(['group' => 'todo', 'st' => null, 'page' => null]) ?>" class="log-tab <?= $group === 'todo' ? 'active-all' : '' ?>">
            <span class="material-symbols-rounded">engineering</span> ร้านต้องทำ (<?= $cnt_todo ?>)
        </a>
        <a href="<?= mgr_url(['group' => 'waiting', 'st' => null, 'page' => null]) ?>" class="log-tab <?= $group === 'waiting' ? 'active-all' : '' ?>">
            <span class="material-symbols-rounded">hail</span> รอลูกค้ามารับ (<?= $cnt_waiting ?>)
        </a>
    </div>

    <!-- ── Status chips ── -->
    <div class="mgr-chips">
        <?php foreach ($group_codes as $code): $m = $STATUS[$code]; $c = (int)($counts[$code] ?? 0); ?>
        <a href="<?= mgr_url(['st' => $st === $code ? null : $code, 'page' => null]) ?>"
           class="mgr-chip <?= $st === $code ? 'active' : '' ?>"
           <?= $st === $code ? 'style="border-color:' . $m['color'] . '; color:' . $m['color'] . ';"' : '' ?>>
            <span class="dot" style="background:<?= $m['color'] ?>;"></span>
            <?= $m['label'] ?> <span class="n"><?= $c ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── Table ── -->
    <div class="log-card">
        <div style="overflow-x:auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>TICKET</th>
                        <th>ลูกค้า</th>
                        <th>เครื่อง</th>
                        <th>สถานะ</th>
                        <th class="col-recv">รับเมื่อ</th>
                        <th class="col-appt">นัดหมาย</th>
                        <th style="text-align:center;">ค้างมา</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($jobs)): ?>
                    <tr><td colspan="7">
                        <div class="empty-state" style="padding:60px 20px;">
                            <span class="material-symbols-rounded" style="font-size:56px; opacity:.15; display:block; margin-bottom:12px;">task_alt</span>
                            ไม่พบงานค้างตามเงื่อนไขนี้
                        </div>
                    </td></tr>
                <?php else: foreach ($jobs as $j):
                    $m    = $STATUS[$j['status']] ?? ['label' => $j['status'], 'color' => '#888'];
                    $days = max(0, (int)$j['days_in']);
                    $dCls = $days <= 3 ? 'days-ok' : ($days <= 7 ? 'days-warn' : 'days-late');
                    $appt = $j['appointment_date'] ? strtotime($j['appointment_date']) : null;
                    $appt_late = $appt && $appt < strtotime('today');
                ?>
                    <tr>
                        <td><a class="mgr-ticket" href="../tracking/edit.php?id=<?= (int)$j['id'] ?>"><?= htmlspecialchars($j['ticket_number']) ?></a></td>
                        <td>
                            <div style="font-weight:700;"><?= htmlspecialchars($j['customer_name']) ?></div>
                            <?php if ($j['customer_phone']): ?>
                            <a href="tel:<?= htmlspecialchars($j['customer_phone']) ?>" style="font-size:11.5px; color:var(--text-muted); text-decoration:none;"><?= htmlspecialchars($j['customer_phone']) ?></a>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12.5px; color:var(--text-muted);">
                            <?= htmlspecialchars(trim(($j['device_type'] ?: '') . ' ' . ($j['device_model'] ?: '')) ?: '—') ?>
                        </td>
                        <td>
                            <span class="mgr-st" style="background:<?= $m['color'] ?>18; color:<?= $m['color'] ?>; border:1px solid <?= $m['color'] ?>33;">
                                <?= $m['label'] ?>
                            </span>
                        </td>
                        <td class="col-recv" style="font-size:12.5px; color:var(--text-muted); white-space:nowrap;">
                            <?= date('d/m/y', strtotime($j['created_at'])) ?>
                        </td>
                        <td class="col-appt" style="font-size:12.5px; white-space:nowrap;">
                            <?php if ($appt): ?>
                                <span class="<?= $appt_late ? 'appt-late' : '' ?>" style="<?= $appt_late ? '' : 'color:var(--text-muted);' ?>">
                                    <?= date('d/m/y', $appt) ?><?= $appt_late ? ' ⚠ เลยนัด' : '' ?>
                                </span>
                            <?php else: ?><span style="color:var(--text-muted); opacity:.4;">—</span><?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="days-badge <?= $dCls ?>"><?= $days ?> วัน</span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination (pattern เดียวกับ tracking) ── -->
        <div class="log-pagination">
            <div>
                แสดง <b><?= number_format(min($total, $offset + 1)) ?>–<?= number_format(min($total, $offset + $per)) ?></b>
                จาก <b><?= number_format($total) ?></b> รายการ
                &nbsp;·&nbsp; หน้า <?= $page ?> / <?= $pages ?>
            </div>
            <div class="page-btns">
                <a href="<?= $page > 1 ? mgr_url(['page' => $page - 1]) : '#' ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                    <span class="material-symbols-rounded" style="font-size:16px;">chevron_left</span>
                </a>
                <?php
                $w_start = max(1, $page - 2);
                $w_end   = min($pages, $w_start + 4);
                $w_start = max(1, $w_end - 4);
                for ($p = $w_start; $p <= $w_end; $p++):
                ?>
                <a href="<?= mgr_url(['page' => $p]) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a href="<?= $page < $pages ? mgr_url(['page' => $page + 1]) : '#' ?>" class="page-btn <?= $page >= $pages ? 'disabled' : '' ?>">
                    <span class="material-symbols-rounded" style="font-size:16px;">chevron_right</span>
                </a>
                <select onchange="location.href=this.value"
                    style="padding:6px 10px; border-radius:8px; border:1px solid var(--border); background:var(--bg-surface); color:var(--text-main); font-size:13px; outline:none; cursor:pointer; font-family:'Sarabun',sans-serif;">
                    <?php foreach ([20, 50, 100] as $pp): ?>
                    <option value="<?= mgr_url(['per' => $pp, 'page' => null]) ?>" <?= $per === $pp ? 'selected' : '' ?>><?= $pp ?>/หน้า</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

</div>

<!-- ── Print Modal (เด้งกลางจอ ไม่เปิดแท็บใหม่) ── -->
<div id="print-modal" class="mgr-modal" onclick="if(event.target===this) closePrintModal()">
    <div class="mgr-modal-box">
        <div class="mgr-modal-head">
            <h3><span class="material-symbols-rounded" style="color:var(--primary);">print</span> ตัวอย่างใบ TODO งานค้าง</h3>
            <button type="button" class="mgr-modal-close" onclick="closePrintModal()"><span class="material-symbols-rounded">close</span></button>
        </div>
        <iframe id="print-frame" src="about:blank"></iframe>
        <div class="mgr-modal-foot">
            <button type="button" class="cmns-btn cmns-btn-secondary" onclick="closePrintModal()">
                <span class="material-symbols-rounded">close</span> ปิด
            </button>
            <button type="button" class="cmns-btn cmns-btn-primary" onclick="doPrintFrame()">
                <span class="material-symbols-rounded">print</span> พิมพ์
            </button>
        </div>
    </div>
</div>

<?php include '../templates/footer_admin.php'; ?>
<script>
function openPrintModal() {
    const modal = document.getElementById('print-modal');
    const frame = document.getElementById('print-frame');
    // ใบพิมพ์ใช้ filter ชุดเดียวกับบอร์ด (ตัด page/per — พิมพ์ทั้งชุดที่กรอง)
    const qs = new URLSearchParams(window.location.search);
    qs.delete('page'); qs.delete('per');
    qs.set('embed', '1');
    frame.src = 'print.php?' + qs.toString() + (qs.has('group') ? '' : '&group=todo');
    modal.style.display = 'flex';
    requestAnimationFrame(() => modal.classList.add('show'));
}
function closePrintModal() {
    const modal = document.getElementById('print-modal');
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; document.getElementById('print-frame').src = 'about:blank'; }, 200);
}
function doPrintFrame() {
    const frame = document.getElementById('print-frame');
    if (frame.contentWindow) { frame.contentWindow.focus(); frame.contentWindow.print(); }
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePrintModal();
});
</script>
