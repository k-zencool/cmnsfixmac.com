<?php
/********************************************************************
 * admin/manager/print.php
 * พิมพ์ To-do: รายการที่ผู้จัดการยังไม่จัดการ (status=active) ตามสถานะ/ประเภท
 ********************************************************************/
session_start();
require_once '../../includes/db.php';
require_once '../../includes/manager_lib.php';
require_login();
require_perms(['manager.center']);

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ── Filters (สืบทอดจากหน้า index; default status = active = "ยังไม่จัดการ") ──
$f_type   = trim($_GET['type']   ?? '');
$f_status = trim($_GET['status'] ?? 'active');
$f_q      = trim($_GET['q']      ?? '');

$where  = [];
$params = [];
if ($f_type !== '')   { $where[] = 'action_type = ?'; $params[] = $f_type; }
if ($f_status !== '') { $where[] = 'status = ?';      $params[] = $f_status; }
if ($f_q !== '') {
    $where[] = '(summary LIKE ? OR actor_name LIKE ?)';
    $params[] = "%$f_q%"; $params[] = "%$f_q%";
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// เรียงตามประเภทงาน แล้วเวลาเก่า→ใหม่ (todo)
$type_order = "FIELD(action_type,'requisition','stock_edit','stock_delete','donor_strip','to_sale','sale_status','price_set')";
$stmt = $pdo->prepare("SELECT * FROM manager_actions $where_sql ORDER BY $type_order, created_at ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total     = count($rows);
$sum_amt   = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));
$status_lbl = ['active' => 'ยังไม่จัดการ', 'reversed' => 'ย้อนแล้ว', '' => 'ทุกสถานะ'][$f_status] ?? $f_status;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>To-do ผู้จัดการ — <?= h($status_lbl) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body { font-family: 'Sarabun', sans-serif; color: #111; background: #f3f4f6; margin: 0; padding: 24px; }
.sheet { background: #fff; max-width: 900px; margin: 0 auto; padding: 28px 32px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
.hd { display: flex; align-items: flex-start; justify-content: space-between; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 6px; }
.hd h1 { font-size: 20px; font-weight: 800; margin: 0 0 4px; }
.hd .meta { font-size: 12px; color: #555; }
.hd .right { text-align: right; font-size: 12px; color: #555; }
.summary { display: flex; gap: 22px; font-size: 13px; margin: 12px 0 18px; }
.summary b { font-size: 15px; }
table { width: 100%; border-collapse: collapse; }
thead th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #666; border-bottom: 1px solid #999; padding: 6px 8px; }
tbody td { font-size: 13px; padding: 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
.grp td { background: #f3f4f6; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; padding: 7px 8px; border-bottom: 1px solid #999; }
.chk { width: 26px; text-align: center; }
.box { display: inline-block; width: 15px; height: 15px; border: 1.5px solid #111; border-radius: 3px; }
.tm { white-space: nowrap; color: #555; font-size: 11px; width: 74px; }
.amt { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; font-weight: 700; width: 90px; }
.actor { white-space: nowrap; color: #444; font-size: 12px; }
.actor small { display: block; color: #888; }
.empty { text-align: center; padding: 40px; color: #888; }
.foot { margin-top: 26px; font-size: 12px; color: #555; display: flex; justify-content: space-between; }
.sign { margin-top: 40px; display: flex; gap: 60px; }
.sign div { flex: 1; border-top: 1px dotted #999; padding-top: 6px; text-align: center; font-size: 12px; color: #555; }
.print-btn { position: fixed; top: 18px; right: 18px; background: #2563eb; color: #fff; border: none; border-radius: 8px; padding: 10px 18px; font-family: inherit; font-weight: 700; font-size: 14px; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.3); }
.back-btn { position: fixed; top: 18px; left: 18px; background: #fff; color: #111; border: 1px solid #ccc; border-radius: 8px; padding: 10px 16px; font-family: inherit; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; }
@media print {
    body { background: #fff; padding: 0; }
    .sheet { box-shadow: none; max-width: none; padding: 0; }
    .print-btn, .back-btn { display: none; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
@page { size: A4 portrait; margin: 14mm; }
</style>
</head>
<body>

<a href="index.php" class="back-btn">← กลับ</a>
<button class="print-btn" onclick="window.print()">🖨 พิมพ์</button>

<div class="sheet">
    <div class="hd">
        <div>
            <h1>รายการที่ต้องตรวจสอบ (To-do)</h1>
            <div class="meta">
                สถานะ: <b><?= h($status_lbl) ?></b>
                <?php if ($f_type !== ''): $m = mgr_action_meta($f_type); ?> · ประเภท: <b><?= h($m['label']) ?></b><?php endif; ?>
                <?php if ($f_q !== ''): ?> · ค้นหา: "<?= h($f_q) ?>"<?php endif; ?>
            </div>
        </div>
        <div class="right">
            CMNS Fix Mac<br>
            พิมพ์เมื่อ <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <div class="summary">
        <div>ทั้งหมด <b><?= number_format($total) ?></b> รายการ</div>
        <div>มูลค่ารวม <b>฿<?= number_format($sum_amt, 0) ?></b></div>
    </div>

    <?php if ($total === 0): ?>
        <div class="empty">— ไม่มีรายการที่ต้องจัดการ —</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th class="chk">✔</th>
                <th class="tm">เวลา</th>
                <th>รายการ</th>
                <th class="actor">ผู้ทำ</th>
                <th class="amt">มูลค่า</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $last_type = null;
        foreach ($rows as $r):
            if ($r['action_type'] !== $last_type):
                $last_type = $r['action_type'];
                $meta = mgr_action_meta($r['action_type']);
                $grp_cnt = count(array_filter($rows, fn($x) => $x['action_type'] === $last_type));
        ?>
            <tr class="grp"><td colspan="5"><?= h($meta['label']) ?> (<?= $grp_cnt ?>)</td></tr>
        <?php endif; ?>
            <tr>
                <td class="chk"><span class="box"></span></td>
                <td class="tm"><?= date('d/m/y', strtotime($r['created_at'])) ?><br><?= date('H:i', strtotime($r['created_at'])) ?></td>
                <td><?= h($r['summary']) ?></td>
                <td class="actor"><?= h($r['actor_name'] ?: '—') ?><small><?= h(role_label($r['actor_role'] ?? '')) ?></small></td>
                <td class="amt"><?= $r['amount'] !== null ? '฿' . number_format((float)$r['amount'], 0) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="sign">
        <div>ผู้ตรวจสอบ / ผู้จัดการ</div>
        <div>วันที่</div>
    </div>
    <?php endif; ?>

    <div class="foot">
        <span>ศูนย์ควบคุมผู้จัดการ — CMNS Fix Mac</span>
        <span>ติ๊ก ✔ เมื่อจัดการรายการเรียบร้อย</span>
    </div>
</div>

</body>
</html>
