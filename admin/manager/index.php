<?php
/********************************************************************
 * admin/manager/index.php
 * งานค้าง — เครื่องที่ยังอยู่ในร้าน เรียงจากค้างนานสุด
 *   กลุ่ม "ต้องทำ"   : QS รอเช็คราคา / WC รอคอนเฟิร์ม / OK กำลังซ่อม / RW งานแก้
 *   กลุ่ม "รอลูกค้า" : FN เสร็จรอรับ / XX ยกเลิกรอรับคืน / NCF·NCS ติดต่อไม่ได้
 * (ledger + reverse เดิมถูกถอดออก 2026-07-11 — data ยังเก็บใน manager_actions ผ่าน mgr_log)
 ********************************************************************/
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

require_login();
require_perms(['manager.center']);

require_once __DIR__ . '/_status.php';

list($group, $st, $jobs) = mgr_fetch_stuck_jobs($pdo, $STATUS);
$group_codes = array_keys(array_filter($STATUS, function ($m) use ($group) { return $m['group'] === $group; }));

// ── นับต่อ status (ทั้งสองกลุ่ม สำหรับ chips) ──
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
<style>
/* ── page-local: บอร์ดงานค้าง ── */
.mgr-chips { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
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

.mgr-card {
    background:var(--bg-surface); border:1px solid var(--border);
    border-radius:16px; padding:20px; overflow-x:auto;
}
.mgr-table { width:100%; border-collapse:collapse; font-size:13.5px; }
.mgr-table th {
    text-align:left; padding:10px 12px; font-size:11px; letter-spacing:.5px;
    color:var(--text-muted); text-transform:uppercase; border-bottom:2px solid var(--border);
    white-space:nowrap;
}
.mgr-table td { padding:12px; border-bottom:1px solid var(--border); vertical-align:middle; }
.mgr-table tr:hover td { background:var(--bg-surface-alt); }
.mgr-table a.ticket {
    font-family:'Courier New', monospace; font-weight:800; font-size:13px;
    color:var(--primary); text-decoration:none;
}
.mgr-table a.ticket:hover { text-decoration:underline; }

.mgr-st {
    display:inline-flex; align-items:center; gap:6px;
    padding:3px 10px; border-radius:20px; font-size:11px; font-weight:800; white-space:nowrap;
}
.days-badge {
    display:inline-block; min-width:52px; text-align:center;
    padding:5px 10px; border-radius:9px; font-size:15px; font-weight:800;
}
.days-ok    { background:rgba(16,185,129,.1);  color:#10b981; }
.days-warn  { background:rgba(245,158,11,.12); color:#f59e0b; }
.days-late  { background:rgba(239,68,68,.1);   color:#ef4444; }
.appt-late  { color:#ef4444; font-weight:700; }

@media (max-width: 768px) {
    .mgr-card { padding:12px; border-radius:12px; }
    .mgr-table th, .mgr-table td { padding:9px 8px; }
    .col-appt, .col-recv { display:none; }
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
            <a href="print.php?group=<?= $group ?><?= $st !== '' ? '&st=' . $st : '' ?>" target="_blank" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">print</span> พิมพ์ TODO
            </a>
        </div>
    </div>

    <!-- group tabs -->
    <div class="cmns-tabs" style="margin-bottom:14px;">
        <a href="?group=todo" class="cmns-tab <?= $group === 'todo' && $st === '' ? 'active-all' : '' ?>">
            <span class="material-symbols-rounded">engineering</span> ร้านต้องทำ (<?= $cnt_todo ?>)
        </a>
        <a href="?group=waiting" class="cmns-tab <?= $group === 'waiting' && $st === '' ? 'active-all' : '' ?>">
            <span class="material-symbols-rounded">hail</span> รอลูกค้ามารับ (<?= $cnt_waiting ?>)
        </a>
    </div>

    <!-- status chips ของกลุ่มที่เลือก -->
    <div class="mgr-chips">
        <?php foreach ($group_codes as $code): $m = $STATUS[$code]; $c = (int)($counts[$code] ?? 0); ?>
        <a href="?group=<?= $group ?>&st=<?= $code ?>"
           class="mgr-chip <?= $st === $code ? 'active' : '' ?>"
           <?= $st === $code ? 'style="border-color:' . $m['color'] . '; color:' . $m['color'] . ';"' : '' ?>>
            <span class="dot" style="background:<?= $m['color'] ?>;"></span>
            <?= $m['label'] ?> <span class="n"><?= $c ?></span>
        </a>
        <?php endforeach; ?>
        <?php if ($st !== ''): ?>
        <a href="?group=<?= $group ?>" class="mgr-chip">✕ ล้าง filter</a>
        <?php endif; ?>
    </div>

    <div class="mgr-card">
        <table class="mgr-table">
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
                <tr><td colspan="7" style="padding:70px 20px; text-align:center; color:var(--text-muted);">
                    <span class="material-symbols-rounded" style="font-size:56px; opacity:.15; display:block; margin-bottom:12px;">task_alt</span>
                    ไม่มีงานค้างในกลุ่มนี้ — เคลียร์เกลี้ยง
                </td></tr>
            <?php else: foreach ($jobs as $j):
                $m    = $STATUS[$j['status']] ?? ['label' => $j['status'], 'color' => '#888'];
                $days = max(0, (int)$j['days_in']);
                $dCls = $days <= 3 ? 'days-ok' : ($days <= 7 ? 'days-warn' : 'days-late');
                $appt = $j['appointment_date'] ? strtotime($j['appointment_date']) : null;
                $appt_late = $appt && $appt < strtotime('today');
            ?>
                <tr>
                    <td><a class="ticket" href="../tracking/edit.php?id=<?= (int)$j['id'] ?>"><?= htmlspecialchars($j['ticket_number']) ?></a></td>
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

</div>

<?php include '../templates/footer_admin.php'; ?>
