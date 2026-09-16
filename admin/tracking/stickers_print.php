<?php
/* =========================================================
   admin/tracking/stickers_print.php — A4 QR sticker sheet (cut by hand)

   Renders one logged run from sticker_prints (stickers.php inserts the
   row, then redirects here — so a refresh re-renders, never re-logs).

   Each .sheet IS one A4 page: @page has no margin and every label is
   absolutely placed in mm from the sheet corner, so the preview matches
   the paper. Labels are packed edge to edge on plain sticker paper and
   each printed label draws its own cut line. Printers still shift a
   little, so X/Y nudges and a "cut lines only" test mode live in the
   toolbar and are remembered per device in localStorage.
   ========================================================= */
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sticker_lib.php';
require_login();
require_perms(['jobs.write']);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM sticker_prints WHERE id = ?");
$st->execute([$id]);
$run = $st->fetch(PDO::FETCH_ASSOC);
if (!$run) { header('Location: stickers.php'); exit; }

$tickets = [];
if ($run['kind'] === 'range') {
    // each number repeated per_no times, side by side (V5900 V5900 V5901 V5901 …)
    $perNo = max(1, (int)($run['per_no'] ?? 1));
    for ($n = (int)$run['start_no']; $n <= (int)$run['end_no']; $n++) {
        for ($c = 0; $c < $perNo; $c++) $tickets[] = stk_fmt($n);
    }
} else {
    $tickets = array_fill(0, (int)$run['qty'], $run['ticket']);
}

$sheet = stk_sheet();
$per   = stk_per_sheet();
$slot0 = max(1, min($per, (int)$run['start_slot']));

/* Lay tickets onto pages: the first page starts at the chosen slot, the rest at slot 1 */
$pages = [];
$page = 0; $slot = $slot0;
foreach ($tickets as $t) {
    if ($slot > $per) { $page++; $slot = 1; }
    $pages[$page][$slot] = $t;
    $slot++;
}

$title = $run['kind'] === 'range'
    ? stk_fmt((int)$run['start_no']) . '–' . stk_fmt((int)$run['end_no'])
    : 'พิมพ์ซ้ำ ' . $run['ticket'];

/* Number size by length: "V5700" sits comfortably; legacy "V5508 (2)" shrinks to fit the cell */
$sizeClass = function (string $t): string {
    return mb_strlen($t) <= 7 ? '' : ' is-long';
};

$qrPayload = [];
foreach (array_unique($tickets) as $t) $qrPayload[$t] = stk_scan_url($t);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>สติ๊กเกอร์ <?= h($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@500;700;800&display=swap" rel="stylesheet">
<style>
@page { size: A4 portrait; margin: 0; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root { --dx: 0mm; --dy: 0mm; }
html, body { background: #d1d5db; }
body { font-family: 'Sarabun', sans-serif; color: #000; }

/* ── Toolbar (screen only) ── */
.toolbar {
    position: sticky; top: 0; z-index: 10;
    display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap;
    padding: 12px 16px;
    background: #fff; border-bottom: 1px solid #cbd5e1;
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

/* ── Paper ── */
.pages { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 16px; }
.sheet {
    position: relative;
    width: 210mm; height: 297mm;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,.2);
}
.label {
    position: absolute;
    width: <?= $sheet['w'] ?>mm; height: <?= $sheet['h'] ?>mm;
    transform: translate(var(--dx), var(--dy));
    overflow: hidden;
}
/* Cut line: straddles the label edge (offset = -half its width), so two
   neighbours draw the exact same line instead of a double one. Grey, so
   it never reads as part of the QR. */
.label.is-cut { outline: .15mm solid #777; outline-offset: -.075mm; }
@media screen { .label:not(.is-cut) { outline: .15mm dashed #cbd5e1; outline-offset: -.075mm; } }
.label.is-empty { background: repeating-linear-gradient(45deg, #f1f5f9 0 2mm, #fff 2mm 4mm); }

/* Stacked: shop name / QR / number, filling the whole cell */
.frame {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: .35mm;
}
.brand { font-size: 4.2pt; font-weight: 800; letter-spacing: .3pt; line-height: 1; white-space: nowrap; }
.qr { flex: 0 0 auto; width: 14mm; height: 14mm; }
.qr svg { display: block; width: 100%; height: 100%; }
.no {
    font-family: 'Courier New', monospace; font-weight: 800;
    font-size: 7.5pt; line-height: 1; letter-spacing: .2pt;
    white-space: nowrap;   /* a long legacy number widens the frame, never wraps */
}
.no.is-long { font-size: 6pt; letter-spacing: 0; }

/* calibration mode: cut lines only — check margins/drift before wasting sticker paper */
body.is-test .label > * { visibility: hidden; }

@media print {
    html, body { background: #fff; }
    .toolbar, .hint { display: none; }
    .pages { display: block; padding: 0; }
    .sheet { box-shadow: none; page-break-after: always; break-after: page; }
    .sheet:last-child { page-break-after: auto; break-after: auto; }
    .label.is-empty { background: none; }
}
</style>
</head>
<body>

<div class="toolbar">
    <a href="stickers.php">← กลับ</a>
    <span class="info"><?= h($title) ?> · <?= count($tickets) ?> ดวง · <?= count($pages) ?> แผ่น</span>
    <label title="เลื่อนแนวนอน (มม.) ค่าบวก = ขวา">X <input type="number" id="dx" step="0.5" value="0"> มม.</label>
    <label title="เลื่อนแนวตั้ง (มม.) ค่าบวก = ลง">Y <input type="number" id="dy" step="0.5" value="0"> มม.</label>
    <label><input type="checkbox" id="testMode"> พิมพ์เฉพาะเส้นตัด (ทดสอบ)</label>
    <button type="button" class="primary" id="btnPrint" disabled onclick="window.print()">กำลังสร้าง QR…</button>
</div>
<p class="hint">ตั้งค่าพิมพ์: กระดาษ A4 · ขนาด <b>100% / Actual size</b> (ห้าม “Fit to page”) · ไม่มีขอบกระดาษ · ปิดหัว/ท้ายกระดาษ</p>

<div class="pages">
<?php foreach ($pages as $pi => $slots): ?>
    <section class="sheet">
    <?php for ($s = 1; $s <= $per; $s++):
        $col  = ($s - 1) % $sheet['cols'];
        $row  = intdiv($s - 1, $sheet['cols']);
        $left = round($sheet['left'] + $col * $sheet['pitchX'], 2);
        $top  = round($sheet['top'] + $row * $sheet['pitchY'], 2);
        $t    = $slots[$s] ?? null;
    ?>
        <?php if ($t === null): ?>
        <?php // Only the first page can have leading gaps; trailing slots on the last page stay plain ?>
        <div class="label<?= ($pi === 0 && $s < $slot0) ? ' is-empty' : '' ?>" style="left:<?= $left ?>mm; top:<?= $top ?>mm;"></div>
        <?php else: ?>
        <div class="label is-cut" style="left:<?= $left ?>mm; top:<?= $top ?>mm;">
            <div class="frame">
                <div class="brand">CMNS FIX MAC</div>
                <div class="qr" data-t="<?= h($t) ?>"></div>
                <div class="no<?= $sizeClass($t) ?>"><?= h($t) ?></div>
            </div>
        </div>
        <?php endif; ?>
    <?php endfor; ?>
    </section>
<?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<!-- Own block: if the CDN ever fails, the throw stays here -->
<script>
(function () {
    var btn = document.getElementById('btnPrint');
    var payload = <?= json_encode($qrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    /* ── Calibration, remembered per device (each printer drifts its own way) ── */
    var KEY = 'cmns.stickerOffset';
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

    /* ── QR codes ── */
    if (!window.QRCode) {
        // Sheets without QR are worthless — refuse to print them
        btn.textContent = 'โหลด QR ไม่สำเร็จ';
        console.error('QRCode library failed to load');
        return;
    }
    // One SVG per distinct ticket; a reprint of ×10 shares the same markup
    var keys = Object.keys(payload), svgs = {}, pending = keys.length, failed = 0;
    function finish() {
        if (failed) { btn.textContent = 'สร้าง QR ไม่ครบ (' + failed + ')'; return; }
        document.querySelectorAll('.qr[data-t]').forEach(function (b) { b.innerHTML = svgs[b.dataset.t]; });
        btn.disabled = false;
        btn.textContent = 'พิมพ์';
    }
    keys.forEach(function (t) {
        QRCode.toString(payload[t], {
            type: 'svg', margin: 1, errorCorrectionLevel: 'M',
            color: { dark: '#000000', light: '#ffffff' }
        }, function (err, svg) {
            if (err) { console.error(err); failed++; } else { svgs[t] = svg; }
            if (--pending === 0) finish();
        });
    });
})();
</script>
</body>
</html>
