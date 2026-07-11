<?php
/********************************************************************
 * admin/manager/print.php — พิมพ์ TODO list งานค้าง (A4, ขาวดำ)
 * รับ filter เดียวกับ index.php: ?group=todo|waiting [&st=QS]
 ********************************************************************/
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once __DIR__ . '/_status.php';

require_login();
require_perms(['manager.center']);

$F = mgr_fetch_stuck_jobs($pdo, $STATUS);
$group = $F['group']; $st = $F['st']; $dev = $F['dev']; $q = $F['q']; $jobs = $F['jobs'];
$embed = !empty($_GET['embed']); // เปิดใน modal ของ index — ซ่อน toolbar

// จัดกลุ่มตาม status เพื่อพิมพ์เป็น section
$sections = [];
foreach ($jobs as $j) $sections[$j['status']][] = $j;

$group_label = $group === 'todo' ? 'ร้านต้องทำ' : 'รอลูกค้ามารับ';
$title = $st !== '' ? ($STATUS[$st]['label'] ?? $st) : $group_label;
if ($dev !== '') $title .= ' · ' . $dev;
if ($q !== '')   $title .= ' · ค้นหา "' . $q . '"';
$printed_by = $_SESSION['admin_username'] ?? '-';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>TODO งานค้าง — <?= htmlspecialchars($title) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Sarabun', sans-serif; font-size:13px; color:#111; background:#fff; padding:24px; }

.sheet { max-width:760px; margin:0 auto; }
.head { display:flex; justify-content:space-between; align-items:flex-end; border-bottom:2.5px solid #111; padding-bottom:10px; margin-bottom:6px; }
.head h1 { font-size:19px; font-weight:800; }
.head .meta { font-size:11px; color:#555; text-align:right; line-height:1.6; }

.section-title {
    font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.5px;
    margin:18px 0 6px; padding:5px 10px; background:#eee; border-left:4px solid #111;
    display:flex; justify-content:space-between;
}

table { width:100%; border-collapse:collapse; }
th { text-align:left; font-size:10.5px; color:#555; text-transform:uppercase; letter-spacing:.4px;
     padding:5px 6px; border-bottom:1.5px solid #111; white-space:nowrap; }
td { padding:8px 6px; border-bottom:1px solid #ccc; vertical-align:top; }
tr { page-break-inside:avoid; }

.chk { width:22px; }
.box { display:inline-block; width:14px; height:14px; border:1.8px solid #111; border-radius:3px; margin-top:2px; }
.ticket { font-family:'Courier New', monospace; font-weight:800; white-space:nowrap; }
.cust b { font-weight:700; }
.cust .tel { color:#444; font-size:12px; }
.dev { color:#444; font-size:12px; }
.days { font-weight:800; white-space:nowrap; text-align:center; }
.days.late { color:#000; }
.days.late::after { content:" ⚠"; }
.appt { font-size:12px; white-space:nowrap; }
.appt.late { font-weight:800; }
.note-line { border-bottom:1px dotted #999; height:16px; min-width:110px; }

.summary { margin-top:16px; font-size:12px; color:#444; display:flex; justify-content:space-between; border-top:2px solid #111; padding-top:8px; }
.sign { margin-top:34px; display:flex; justify-content:flex-end; gap:60px; font-size:12px; }
.sign .line { border-top:1px solid #111; padding-top:5px; min-width:180px; text-align:center; }

.toolbar { max-width:760px; margin:0 auto 16px; display:flex; gap:8px; }
.toolbar button {
    font-family:inherit; font-size:13px; font-weight:700; padding:8px 18px;
    border:1.5px solid #111; border-radius:8px; background:#111; color:#fff; cursor:pointer;
}
.toolbar button.ghost { background:#fff; color:#111; }

@media print {
    body { padding:0; font-size:12px; }
    .toolbar { display:none; }
    @page { size:A4; margin:14mm 12mm; }
}
</style>
</head>
<body>

<?php if (!$embed): ?>
<div class="toolbar">
    <button onclick="window.print()">🖨 พิมพ์</button>
    <button class="ghost" onclick="window.close()">ปิด</button>
</div>
<?php endif; ?>

<div class="sheet">
    <div class="head">
        <div>
            <h1>TODO — งานค้าง: <?= htmlspecialchars($title) ?></h1>
            <div style="font-size:12px; color:#555; margin-top:3px;">เรียงจากค้างนานสุด · ทั้งหมด <?= count($jobs) ?> งาน</div>
        </div>
        <div class="meta">
            พิมพ์เมื่อ <?= date('d/m/Y H:i') ?><br>
            โดย <?= htmlspecialchars($printed_by) ?>
        </div>
    </div>

    <?php if (empty($jobs)): ?>
        <p style="padding:50px 0; text-align:center; color:#777;">ไม่มีงานค้างในกลุ่มนี้</p>
    <?php else: foreach ($sections as $code => $rows): $m = $STATUS[$code] ?? ['label' => $code]; ?>

    <div class="section-title">
        <span><?= $m['label'] ?></span>
        <span><?= count($rows) ?> งาน</span>
    </div>
    <table>
        <thead>
            <tr>
                <th class="chk"></th>
                <th>TICKET</th>
                <th>ลูกค้า / โทร</th>
                <th>เครื่อง</th>
                <th style="text-align:center;">ค้างมา</th>
                <th>นัดหมาย</th>
                <th style="width:150px;">โน้ตตามงาน</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $j):
                $days = max(0, (int)$j['days_in']);
                $appt = $j['appointment_date'] ? strtotime($j['appointment_date']) : null;
                $appt_late = $appt && $appt < strtotime('today');
            ?>
            <tr>
                <td class="chk"><span class="box"></span></td>
                <td class="ticket"><?= htmlspecialchars($j['ticket_number']) ?></td>
                <td class="cust">
                    <b><?= htmlspecialchars($j['customer_name']) ?></b><br>
                    <span class="tel"><?= htmlspecialchars($j['customer_phone'] ?: '—') ?></span>
                </td>
                <td class="dev"><?= htmlspecialchars(trim(($j['device_type'] ?: '') . ' ' . ($j['device_model'] ?: '')) ?: '—') ?></td>
                <td class="days <?= $days > 7 ? 'late' : '' ?>"><?= $days ?> วัน</td>
                <td class="appt <?= $appt_late ? 'late' : '' ?>">
                    <?= $appt ? date('d/m/y', $appt) . ($appt_late ? ' (เลยนัด)' : '') : '—' ?>
                </td>
                <td><div class="note-line"></div></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php endforeach; endif; ?>

    <div class="summary">
        <span>รวมทั้งหมด <b><?= count($jobs) ?></b> งาน<?= count($jobs) >= 300 ? ' (ตัดที่ 300)' : '' ?></span>
        <span>cmnsfixmac.com — ใบตามงานภายในร้าน</span>
    </div>
    <div class="sign">
        <div class="line">ผู้ตามงาน / วันที่</div>
    </div>
</div>

</body>
</html>
