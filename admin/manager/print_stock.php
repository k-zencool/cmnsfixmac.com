<?php
/********************************************************************
 * admin/manager/print_stock.php — ใบเช็คสต็อกอะไหล่ (A4, ขาวดำ)
 * แบ่ง section ตามหมวดหมู่ มีช่องกรอก "นับได้จริง" + หมายเหตุ
 * filters: ?cat=ID (หมวด) ?t=new|used|machine|sale (ประเภท) — ว่าง = ทั้งหมด
 ********************************************************************/
session_start();
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

require_login();
require_perms(['manager.center']);

$embed = !empty($_GET['embed']); // เปิดใน modal ของ index — ซ่อนปุ่ม toolbar
$cat   = (int)($_GET['cat'] ?? 0);
$type  = trim($_GET['t'] ?? '');
if (!in_array($type, ['new', 'used', 'machine', 'sale'], true)) $type = '';

// หมวดทั้งหมด (สำหรับ dropdown)
$cats = $pdo->query("SELECT id, name FROM parts_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// รายการสต็อก: new/used นับจาก lot คงเหลือ, machine/sale = 1 record ต่อ 1 เครื่อง
$where  = ["i.status <> 'SOLD'"];
$params = [];
if ($cat)  { $where[] = "i.category_id = ?"; $params[] = $cat; }
if ($type) { $where[] = "i.type = ?";        $params[] = $type; }
$where_sql = implode(" AND ", $where);

$stmt = $pdo->prepare("
    SELECT i.sku, i.name, i.type, i.status, i.location, i.compatible_models,
           c.id AS cat_id, c.name AS cat_name,
           CASE WHEN i.type IN ('new','used') THEN COALESCE(l.qty, 0) ELSE 1 END AS qty_sys
    FROM inventory i
    JOIN parts_categories c ON c.id = i.category_id
    LEFT JOIN (
        SELECT inventory_id, SUM(qty_remaining) AS qty
        FROM inventory_lots GROUP BY inventory_id
    ) l ON l.inventory_id = i.id
    WHERE $where_sql
    ORDER BY c.name ASC, i.name ASC");
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// จัดกลุ่มตามหมวด
$sections = [];
$qty_total = 0;
foreach ($items as $it) {
    $sections[$it['cat_name']][] = $it;
    $qty_total += (int)$it['qty_sys'];
}

$type_labels = ['new' => 'อะไหล่ใหม่', 'used' => 'อะไหล่มือสอง', 'machine' => 'เครื่อง', 'sale' => 'สินค้าขาย'];
$title = 'เช็คสต็อก';
if ($cat) {
    foreach ($cats as $c) if ((int)$c['id'] === $cat) { $title .= ' — ' . $c['name']; break; }
}
if ($type) $title .= ' · ' . $type_labels[$type];
$printed_by = $_SESSION['admin_username'] ?? '-';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ใบเช็คสต็อก — <?= htmlspecialchars($title) ?></title>
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
td { padding:7px 6px; border-bottom:1px solid #ccc; vertical-align:top; }
tr { page-break-inside:avoid; }

.sku { font-family:'Courier New', monospace; font-weight:700; font-size:11.5px; white-space:nowrap; }
.pname b { font-weight:700; }
.pname .models { color:#666; font-size:11px; }
.loc { color:#444; font-size:12px; }
.qty { font-weight:800; text-align:center; white-space:nowrap; }
.qty.zero { color:#999; font-weight:400; }
.count-box { border:1.8px solid #111; border-radius:5px; width:52px; height:24px; margin:0 auto; }
.note-line { border-bottom:1px dotted #999; height:16px; min-width:90px; }

.summary { margin-top:16px; font-size:12px; color:#444; display:flex; justify-content:space-between; border-top:2px solid #111; padding-top:8px; }
.sign { margin-top:34px; display:flex; justify-content:flex-end; gap:60px; font-size:12px; }
.sign .line { border-top:1px solid #111; padding-top:5px; min-width:180px; text-align:center; }

/* filter bar — โชว์ทั้งใน embed ให้เปลี่ยนหมวดจาก modal ได้ แต่ไม่ติดไปตอนพิมพ์ */
.toolbar { max-width:760px; margin:0 auto 16px; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.toolbar button {
    font-family:inherit; font-size:13px; font-weight:700; padding:8px 18px;
    border:1.5px solid #111; border-radius:8px; background:#111; color:#fff; cursor:pointer;
}
.toolbar button.ghost { background:#fff; color:#111; }
.toolbar select {
    font-family:inherit; font-size:13px; font-weight:600; padding:7px 10px;
    border:1.5px solid #111; border-radius:8px; background:#fff; color:#111; cursor:pointer;
}

@media print {
    body { padding:0; font-size:12px; }
    .toolbar { display:none; }
    @page { size:A4; margin:14mm 12mm; }
}
</style>
</head>
<body>

<div class="toolbar">
    <select onchange="reloadWith('cat', this.value)">
        <option value="">ทุกหมวด</option>
        <?php foreach ($cats as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select onchange="reloadWith('t', this.value)">
        <option value="">ทุกประเภท</option>
        <?php foreach ($type_labels as $tk => $tl): ?>
        <option value="<?= $tk ?>" <?= $type === $tk ? 'selected' : '' ?>><?= $tl ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (!$embed): ?>
    <button onclick="window.print()">🖨 พิมพ์</button>
    <button class="ghost" onclick="window.close()">ปิด</button>
    <?php endif; ?>
</div>

<div class="sheet">
    <div class="head">
        <div>
            <h1>ใบเช็คสต็อก: <?= htmlspecialchars($title) ?></h1>
            <div style="font-size:12px; color:#555; margin-top:3px;">ทั้งหมด <?= count($items) ?> รายการ · ในระบบรวม <?= number_format($qty_total) ?> ชิ้น</div>
        </div>
        <div class="meta">
            พิมพ์เมื่อ <?= date('d/m/Y H:i') ?><br>
            โดย <?= htmlspecialchars($printed_by) ?>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <p style="padding:50px 0; text-align:center; color:#777;">ไม่มีรายการสต็อกตามเงื่อนไขนี้</p>
    <?php else: foreach ($sections as $cat_name => $rows): ?>

    <div class="section-title">
        <span><?= htmlspecialchars($cat_name) ?></span>
        <span><?= count($rows) ?> รายการ</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>รายการ</th>
                <th>ที่เก็บ</th>
                <th style="text-align:center;">ระบบ</th>
                <th style="text-align:center; width:64px;">นับได้</th>
                <th style="width:110px;">หมายเหตุ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $it): $qs = (int)$it['qty_sys']; ?>
            <tr>
                <td class="sku"><?= htmlspecialchars($it['sku']) ?></td>
                <td class="pname">
                    <b><?= htmlspecialchars($it['name']) ?></b>
                    <?php if ($it['type'] !== 'new'): ?>
                        <span style="font-size:10.5px; color:#555;">[<?= $type_labels[$it['type']] ?? $it['type'] ?>]</span>
                    <?php endif; ?>
                    <?php if ($it['compatible_models']): ?>
                        <br><span class="models"><?= htmlspecialchars($it['compatible_models']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="loc"><?= htmlspecialchars($it['location'] ?: '—') ?></td>
                <td class="qty <?= $qs === 0 ? 'zero' : '' ?>"><?= $qs ?></td>
                <td><div class="count-box"></div></td>
                <td><div class="note-line"></div></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php endforeach; endif; ?>

    <div class="summary">
        <span>รวม <b><?= count($items) ?></b> รายการ · <b><?= number_format($qty_total) ?></b> ชิ้นในระบบ</span>
        <span>cmnsfixmac.com — ใบเช็คสต็อกภายในร้าน</span>
    </div>
    <div class="sign">
        <div class="line">ผู้นับสต็อก / วันที่</div>
        <div class="line">ผู้ตรวจสอบ / วันที่</div>
    </div>
</div>

<script>
// เปลี่ยน filter แล้ว reload ตัวเอง (ใช้ได้ทั้งเปิดตรงและใน iframe ของ modal)
function reloadWith(key, val) {
    const qs = new URLSearchParams(window.location.search);
    if (val === '') qs.delete(key); else qs.set(key, val);
    window.location.search = qs.toString();
}
</script>

</body>
</html>
