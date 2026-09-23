<?php
/* =========================================================
   admin/inventory/print_bins.php — A4 QR labels for storage slots

   GET ?ids=<storage_bins.id,…>

   72 × 88 mm (a 66 mm QR + the code), six to an A4 page, stuck on the front of the box. Only
   the QR (CMNS:B-<id>) and the slot code — what the slot holds is data
   in bins.php and can change without a reprint. Same paper rules as
   print_labels.php: each .sheet IS one A4 page, labels are placed in mm,
   each draws its own cut line, X/Y nudge is remembered per device.
   ========================================================= */
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage_bin_lib.php';
require_once __DIR__ . '/../../includes/part_label_lib.php';   // plb_parse_ids
require_login();
require_perms(['parts.manage']);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$ids = plb_parse_ids((string)($_GET['ids'] ?? ''), 200);
$bins = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("
        SELECT b.id, b.slot, s.code AS shelf_code
        FROM storage_bins b JOIN storage_shelves s ON s.id = b.shelf_id
        WHERE b.id IN ($in)
        ORDER BY LENGTH(s.code), s.code, b.slot
    ");
    $st->execute($ids);
    $bins = $st->fetchAll(PDO::FETCH_ASSOC);
}

$sheet = sbin_sheet();
$per   = $sheet['cols'] * $sheet['rows'];
$pages = array_chunk($bins, $per);

$qrPayload = [];
foreach ($bins as $b) $qrPayload[$b['id']] = sbin_qr_payload((int)$b['id']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ฉลากช่องเก็บของ · <?= count($bins) ?> ช่อง</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@700;800&display=swap" rel="stylesheet">
<style>
@page { size: A4 portrait; margin: 0; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root { --dx: 0mm; --dy: 0mm; }
html, body { background: #d1d5db; }
body { font-family: 'Sarabun', sans-serif; color: #000; }

.toolbar {
    position: sticky; top: 0; z-index: 10;
    display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap;
    padding: 12px 16px; background: #fff; border-bottom: 1px solid #cbd5e1;
    font-size: 14px; color: #0f172a;
}
.toolbar a, .toolbar button {
    font: 700 14px 'Sarabun', sans-serif;
    padding: 9px 16px; border-radius: 10px;
    border: 1px solid #cbd5e1; background: #fff; color: #0f172a;
    text-decoration: none; cursor: pointer;
}
.toolbar .primary { background: #1e3a5f; border-color: #1e3a5f; color: #fff; }
.toolbar .primary:disabled { opacity: .5; cursor: wait; }
.toolbar .info { font-weight: 700; }
.toolbar label { display: inline-flex; align-items: center; gap: 5px; }
.toolbar input[type="number"] {
    width: 64px; padding: 7px 8px; border: 1px solid #cbd5e1; border-radius: 8px;
    font: 600 14px 'Sarabun', sans-serif;
}
.hint { text-align: center; font-size: 13px; color: #475569; padding: 10px 16px 0; }
.empty { text-align: center; padding: 60px 16px; font-size: 16px; color: #334155; }

.pages { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 16px; }
.sheet {
    position: relative; width: 210mm; height: 297mm;
    background: #fff; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.2);
}
.label {
    position: absolute;
    width: <?= $sheet['w'] ?>mm; height: <?= $sheet['h'] ?>mm;
    transform: translate(var(--dx), var(--dy));
    outline: .2mm solid #777; outline-offset: -.1mm;
    display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
    padding-top: 3mm;
}
.qr { width: 66mm; height: 66mm; }
.qr svg { display: block; width: 100%; height: 100%; }
/* read from across the room */
.code {
    margin-top: .5mm;
    font-family: 'Courier New', monospace; font-weight: 800;
    font-size: 36pt; line-height: 1; letter-spacing: -1pt;
}
.brand { margin-top: 1mm; font-size: 6pt; font-weight: 800; letter-spacing: .6pt; color: #444; line-height: 1; }

body.is-test .label > * { visibility: hidden; }

@media print {
    html, body { background: #fff; }
    .toolbar, .hint { display: none; }
    .pages { display: block; padding: 0; }
    .sheet { box-shadow: none; page-break-after: always; break-after: page; }
    .sheet:last-child { page-break-after: auto; break-after: auto; }
}
</style>
</head>
<body>

<div class="toolbar">
    <a href="bins.php">← กลับ</a>
    <span class="info"><?= count($bins) ?> ช่อง · <?= count($pages) ?> แผ่น</span>
    <label title="เลื่อนแนวนอน (มม.) ค่าบวก = ขวา">X <input type="number" id="dx" step="0.5" value="0"> มม.</label>
    <label title="เลื่อนแนวตั้ง (มม.) ค่าบวก = ลง">Y <input type="number" id="dy" step="0.5" value="0"> มม.</label>
    <label><input type="checkbox" id="testMode"> พิมพ์เฉพาะเส้นตัด (ทดสอบ)</label>
    <button type="button" class="primary" id="btnPrint" disabled onclick="window.print()">กำลังสร้าง QR…</button>
</div>
<p class="hint">ตั้งค่าพิมพ์: กระดาษ A4 · ขนาด <b>100% / Actual size</b> (ห้าม “Fit to page”) · ไม่มีขอบกระดาษ · ปิดหัว/ท้ายกระดาษ · แผ่นละ <?= $per ?> ดวง (<?= $sheet['w'] ?>×<?= $sheet['h'] ?> มม.)</p>

<?php if (!$bins): ?>
    <p class="empty">ไม่พบช่องที่เลือก</p>
<?php else: ?>
<div class="pages">
<?php foreach ($pages as $slots): ?>
    <section class="sheet">
    <?php foreach ($slots as $i => $b):
        $left = $sheet['left'] + ($i % $sheet['cols']) * $sheet['w'];
        $top  = $sheet['top'] + intdiv($i, $sheet['cols']) * $sheet['h'];
    ?>
        <div class="label" style="left:<?= $left ?>mm; top:<?= $top ?>mm;">
            <div class="qr" data-id="<?= (int)$b['id'] ?>"></div>
            <div class="code"><?= h(sbin_code($b['shelf_code'], (int)$b['slot'])) ?></div>
            <div class="brand">CMNS FIX MAC</div>
        </div>
    <?php endforeach; ?>
    </section>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<!-- Own block: if the CDN ever fails, the throw stays here -->
<script>
(function () {
    var btn = document.getElementById('btnPrint');
    var payload = <?= json_encode((object)$qrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    // separate from the part-label sheet: different paper, different nudge
    var KEY = 'cmns.binLabelOffset';
    var dx = document.getElementById('dx'), dy = document.getElementById('dy');
    var test = document.getElementById('testMode');
    try {
        var saved = JSON.parse(localStorage.getItem(KEY) || '{}');
        if (typeof saved.dx === 'number') dx.value = saved.dx;
        if (typeof saved.dy === 'number') dy.value = saved.dy;
    } catch (e) {}
    function applyOffset() {
        var x = parseFloat(dx.value) || 0, y = parseFloat(dy.value) || 0;
        document.documentElement.style.setProperty('--dx', x + 'mm');
        document.documentElement.style.setProperty('--dy', y + 'mm');
        try { localStorage.setItem(KEY, JSON.stringify({ dx: x, dy: y })); } catch (e) {}
    }
    dx.addEventListener('input', applyOffset);
    dy.addEventListener('input', applyOffset);
    test.addEventListener('change', function () { document.body.classList.toggle('is-test', test.checked); });
    applyOffset();

    var keys = Object.keys(payload);
    if (!keys.length) { btn.textContent = 'ไม่มีรายการ'; return; }
    if (!window.QRCode) {
        btn.textContent = 'โหลด QR ไม่สำเร็จ';
        console.error('QRCode library failed to load');
        return;
    }
    var svgs = {}, pending = keys.length, failed = 0;
    function finish() {
        if (failed) { btn.textContent = 'สร้าง QR ไม่ครบ (' + failed + ')'; return; }
        document.querySelectorAll('.qr[data-id]').forEach(function (b) { b.innerHTML = svgs[b.dataset.id]; });
        btn.disabled = false;
        btn.textContent = 'พิมพ์';
    }
    keys.forEach(function (id) {
        QRCode.toString(payload[id], {
            type: 'svg', margin: 1, errorCorrectionLevel: 'M',
            color: { dark: '#000000', light: '#ffffff' }
        }, function (err, svg) {
            if (err) { console.error(err); failed++; } else { svgs[id] = svg; }
            if (--pending === 0) finish();
        });
    });
})();
</script>
</body>
</html>
