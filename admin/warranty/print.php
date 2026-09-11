<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$w = $pdo->prepare("SELECT w.*, t.ticket_number
                    FROM warranties w
                    LEFT JOIN tracking t ON t.id = w.tracking_id
                    WHERE w.id = ?");
$w->execute([$id]);
$war = $w->fetch(PDO::FETCH_ASSOC);
if (!$war) { header('Location: index.php'); exit; }

$public_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
              . '/warranty/?q=' . urlencode($war['warranty_no']);

/* Letterhead — the same details the public site prints in includes/footer.php.
   Change them there and here together. */
$shop = [
    'name'    => 'CMNS Fix Mac',
    'tagline' => 'ศูนย์ซ่อม Mac & iPhone เชียงใหม่',
    'address' => '482 หมู่ 8 หลังกาดวรุณ ต.แม่เหียะ เชียงใหม่ 50100',
    'tel'     => '084-151-1684',
    'line'    => '@cmns',
    'web'     => 'cmnsfixmac.com',
];

$d = fn($date) => date('d/m/Y', strtotime($date));

// A printed certificate records what was issued — a closed one is stamped, not hidden
$stamp = ['voided' => 'ยกเลิก · VOID', 'expired' => 'หมดอายุ · EXPIRED'][$war['status']] ?? null;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ใบรับประกัน <?= h($war['warranty_no']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* One sheet of A5 landscape (210 × 148 mm). .sheet IS the paper: @page has
   no margin and the sheet carries its own padding, so the screen shows
   exactly what prints. Navy + greys only — reads fine on a B/W printer. */
@page { size: A5 landscape; margin: 0; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root { --ink: #0f172a; --navy: #1e3a5f; --muted: #64748b; --line: #cbd5e1; --soft: #f1f5f9; }
html, body { background: #e5e7eb; }
body {
    font-family: 'Sarabun', sans-serif;
    font-size: 10pt;
    line-height: 1.45;
    color: var(--ink);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

/* ── Screen-only toolbar ── */
.toolbar { width: 210mm; margin: 16px auto 10px; display: flex; justify-content: flex-end; gap: 8px; }
.toolbar a, .toolbar button {
    padding: 8px 16px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #fff;
    color: var(--ink);
    font: 600 13px 'Sarabun', sans-serif;
    text-decoration: none;
    cursor: pointer;
}
.toolbar button { background: var(--navy); border-color: var(--navy); color: #fff; }

/* ── The sheet, with a double certificate frame ── */
.sheet {
    position: relative;
    width: 210mm;
    height: 148mm;
    margin: 0 auto 24px;
    padding: 9mm 11mm 8mm;
    display: flex;
    flex-direction: column;
    background: #fff;
    box-shadow: 0 6px 24px rgba(15, 23, 42, .18);
    overflow: hidden;
}
.sheet::before,
.sheet::after { content: ''; position: absolute; pointer-events: none; }
.sheet::before { inset: 4mm;   border: .45mm solid var(--navy); }
.sheet::after  { inset: 5.2mm; border: .15mm solid var(--navy); }

/* ── Letterhead ── */
.head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 6mm;
    padding-bottom: 2.6mm;
    border-bottom: .5mm solid var(--navy);
}
.brand { display: flex; align-items: center; gap: 3.5mm; }
.brand img { height: 13mm; width: auto; display: block; }
.brand-name { font-size: 13pt; font-weight: 800; color: var(--navy); letter-spacing: .3px; line-height: 1.2; }
.brand-meta { font-size: 7.4pt; color: var(--muted); line-height: 1.5; }

.title { text-align: right; }
.title h1 { font-size: 18pt; font-weight: 800; color: var(--navy); line-height: 1.1; }
.title .en { margin-top: .4mm; font-size: 7.2pt; font-weight: 700; letter-spacing: 2.2px; text-transform: uppercase; color: var(--muted); }
.meta { margin: 1.6mm 0 0 auto; border-collapse: collapse; font-size: 8pt; }
.meta th { padding: .4mm 3mm .4mm 0; text-align: left; font-weight: 600; color: var(--muted); }
.meta td { text-align: right; font-weight: 700; }
.mono { font-family: 'Courier New', monospace; }
.meta .mono { font-size: 9.5pt; }

/* ── Sections ── */
/* No wide tracking here: letter-spacing pulls Thai vowel/tone marks apart */
.sec-t { margin: 3mm 0 1.3mm; font-size: 7.6pt; font-weight: 800; letter-spacing: .2px; color: var(--navy); }
/* Fixed columns, so every certificate has the same grid whatever the data */
.grid { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.3pt; }
.grid td { overflow-wrap: anywhere; }
.grid th, .grid td { padding: 1.2mm 2.5mm; border: .25mm solid var(--line); vertical-align: top; }
.grid th { width: 25mm; background: var(--soft); font-weight: 600; color: #334155; text-align: left; white-space: nowrap; }
.grid td { font-weight: 600; }

.period {
    display: grid;
    grid-template-columns: 1.1fr 1fr 1fr;
    margin-top: 2.4mm;
    border: .35mm solid var(--navy);
    border-radius: 1.5mm;
}
.period > div { padding: 1.4mm 3mm; }
.period > div + div { border-left: .25mm solid var(--line); }
.period label { display: block; font-size: 7pt; font-weight: 700; letter-spacing: .4px; color: var(--muted); }
.period b { font-size: 12pt; font-weight: 800; color: var(--navy); font-variant-numeric: tabular-nums; }
.period .big b { font-size: 14pt; }

/* ── Terms + QR ── */
.lower { display: grid; grid-template-columns: 1fr 26mm; gap: 5mm; margin-top: 2.4mm; flex: 1; min-height: 0; }
.lower .sec-t { margin-top: 0; }
.terms { font-size: 7.3pt; line-height: 1.5; color: #334155; }
.terms ol { padding-left: 4mm; }
.qr { text-align: center; }
.qr canvas { width: 24mm !important; height: 24mm !important; display: block; margin: 0 auto; }
.qr small { display: block; margin-top: .6mm; font-size: 6.5pt; line-height: 1.3; color: var(--muted); }

/* ── Signatures + footer ── */
.sign { display: grid; grid-template-columns: 1fr 1fr; gap: 16mm; font-size: 8pt; text-align: center; }
.sign .line { margin: 0 8mm 1mm; border-top: .25mm solid var(--ink); }
.sign small { font-size: 7pt; color: var(--muted); }
.foot { display: flex; justify-content: space-between; margin-top: 1.8mm; font-size: 6.5pt; color: var(--muted); }

/* ── Closed certificates get stamped ── */
.stamp {
    position: absolute;
    top: 52%; left: 50%;
    transform: translate(-50%, -50%) rotate(-14deg);
    padding: 1mm 7mm;
    border: 1.2mm solid rgba(220, 38, 38, .2);
    border-radius: 3mm;
    color: rgba(220, 38, 38, .2);
    font-size: 32pt;
    font-weight: 800;
    letter-spacing: 3px;
    white-space: nowrap;
    pointer-events: none;
}
.stamp.is-expired { color: rgba(100, 116, 139, .22); border-color: rgba(100, 116, 139, .22); }

@media print {
    html, body { background: #fff; }
    .toolbar { display: none; }
    .sheet { margin: 0; box-shadow: none; }
}
</style>
</head>
<body>

<div class="toolbar">
    <a href="view.php?id=<?= $id ?>">← กลับ</a>
    <button type="button" onclick="window.print()">พิมพ์ / บันทึก PDF</button>
</div>

<main class="sheet">
    <?php if ($stamp): ?>
        <div class="stamp is-<?= h($war['status']) ?>"><?= $stamp ?></div>
    <?php endif; ?>

    <header class="head">
        <div class="brand">
            <img src="/assets/img/Logo1.png" alt="<?= h($shop['name']) ?>">
            <div>
                <div class="brand-name"><?= h($shop['name']) ?></div>
                <div class="brand-meta">
                    <?= h($shop['tagline']) ?><br>
                    <?= h($shop['address']) ?><br>
                    โทร <?= h($shop['tel']) ?> · LINE <?= h($shop['line']) ?> · <?= h($shop['web']) ?>
                </div>
            </div>
        </div>
        <div class="title">
            <h1>ใบรับประกัน</h1>
            <div class="en">Warranty Certificate</div>
            <table class="meta">
                <tr><th>เลขที่</th><td class="mono"><?= h($war['warranty_no']) ?></td></tr>
                <tr><th>วันที่ออก</th><td><?= $d($war['created_at']) ?></td></tr>
                <?php if ($war['ticket_number']): ?>
                <tr><th>อ้างอิงงานซ่อม</th><td class="mono"><?= h($war['ticket_number']) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </header>

    <div class="sec-t">ข้อมูลผู้ถือใบรับประกัน</div>
    <table class="grid">
        <colgroup><col style="width:25mm"><col><col style="width:25mm"><col></colgroup>
        <tr>
            <th>ชื่อลูกค้า</th><td><?= h($war['customer_name']) ?></td>
            <th>เบอร์โทร</th><td><?= $war['customer_phone'] ? h($war['customer_phone']) : '—' ?></td>
        </tr>
        <tr>
            <th>รุ่นเครื่อง</th><td><?= h($war['device_model']) ?></td>
            <th>Serial No.</th><td class="mono"><?= $war['serial_no'] ? h($war['serial_no']) : '—' ?></td>
        </tr>
        <tr>
            <th>รายการที่ซ่อม</th>
            <td colspan="3" style="white-space:pre-line;"><?= $war['repair_summary'] ? h($war['repair_summary']) : '—' ?></td>
        </tr>
    </table>

    <div class="period">
        <div class="big"><label>ระยะเวลารับประกัน</label><b><?= (int)$war['warranty_days'] ?> วัน</b></div>
        <div><label>เริ่มรับประกัน</label><b><?= $d($war['start_date']) ?></b></div>
        <div><label>สิ้นสุดการรับประกัน</label><b><?= $d($war['end_date']) ?></b></div>
    </div>

    <div class="lower">
        <div class="terms">
            <div class="sec-t">เงื่อนไขการรับประกัน</div>
            <ol>
                <li>ครอบคลุมเฉพาะอาการเดิมจากงานซ่อมที่ระบุในเอกสารนี้</li>
                <li>ไม่รวมความเสียหายจากการตก กระแทก น้ำ ความชื้น การแกะซ่อมจากภายนอก หรือการใช้งานผิดวิธี</li>
                <li>หากพบอาการผิดปกติ กรุณานำเครื่องมาให้ช่างตรวจภายในระยะประกัน</li>
                <li>ตรวจสอบสถานะประกันได้โดยสแกน QR หรือที่ <?= h($shop['web']) ?></li>
            </ol>
        </div>
        <div class="qr">
            <canvas id="qr-print"></canvas>
            <small>สแกนตรวจสอบสถานะ<br><?= h($war['warranty_no']) ?></small>
        </div>
    </div>

    <div class="sign">
        <div><div class="line"></div>ผู้ออกใบรับประกัน<br><small><?= h($shop['name']) ?></small></div>
        <div><div class="line"></div>ลูกค้า<br><small>ผู้รับใบรับประกัน</small></div>
    </div>

    <footer class="foot">
        <span>เอกสารนี้ออกโดยระบบ <?= h($shop['name']) ?></span>
        <span>พิมพ์เมื่อ <?= date('d/m/Y H:i') ?></span>
    </footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<!-- Own block: if the CDN ever fails, the throw stays here -->
<script>
if (window.QRCode) {
    QRCode.toCanvas(document.getElementById('qr-print'), <?= json_encode($public_url) ?>, {
        width: 200, margin: 0, color: { dark: '#0f172a', light: '#ffffff' }
    }, function (err) { if (err) console.error(err); });
} else {
    console.error('QRCode library failed to load');
}
</script>
</body>
</html>
