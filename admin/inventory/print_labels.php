<?php
/* =========================================================
   admin/inventory/print_labels.php — A4 QR label sheet for parts

   GET ?run=<part_label_runs.id> [&start=S] [&size=s|m|l|xl]
     run    a run logged by labels.php (items in picked order + copies)
     start  first slot on the first sheet, overrides the logged one
            — for reprinting a run onto a part-used sheet
     size   label/QR size (plb_sizes()); the last one used is remembered
            per device and re-applied when the URL has none

   Same paper and layout rules as tracking/stickers_print.php: each .sheet
   IS one A4 page, labels are absolutely placed in mm, packed edge to edge,
   and each printed label draws its own cut line. X/Y nudges and a "cut
   lines only" test mode are remembered per device in localStorage.
   ========================================================= */
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/part_label_lib.php';
require_login();
require_perms(['parts.manage']);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$st = $pdo->prepare("SELECT * FROM part_label_runs WHERE id = ?");
$st->execute([(int)($_GET['run'] ?? 0)]);
$run = $st->fetch(PDO::FETCH_ASSOC);
if (!$run) { header('Location: labels.php'); exit; }

$sizes  = plb_sizes();
$size   = isset($sizes[$_GET['size'] ?? '']) ? $_GET['size'] : 's';
$per    = plb_per_sheet($size);
$copies = max(1, (int)$run['copies']);
$slot0  = max(1, min($per, (int)($_GET['start'] ?? $run['start_slot'])));

// picked order; an item deleted since the run just drops out
$st = $pdo->prepare("
    SELECT i.id, i.name, i.type, i.sku, i.part_number, i.asset_tag, i.compatible_models
    FROM part_label_run_items ri JOIN inventory i ON i.id = ri.inventory_id
    WHERE ri.run_id = ? ORDER BY ri.sort
");
$st->execute([(int)$run['id']]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

/* Each item repeated `copies` times, side by side */
$labels = [];
foreach ($items as $it) for ($c = 0; $c < $copies; $c++) $labels[] = $it;

$pages = [];
$page = 0; $slot = $slot0;
foreach ($labels as $l) {
    if ($slot > $per) { $page++; $slot = 1; }
    $pages[$page][$slot] = $l;
    $slot++;
}

$qrPayload = [];
foreach ($items as $it) $qrPayload[$it['id']] = plb_qr_payload((int)$it['id']);

$sheet = plb_sheet($size);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ฉลาก QR · <?= count($items) ?> รายการ</title>
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
.toolbar form { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: center; }
.toolbar select {
    padding: 7px 8px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff;
    font: 600 14px 'Sarabun', sans-serif; color: #0f172a;
}
.hint { text-align: center; font-size: 13px; color: #475569; padding: 10px 16px 0; }
.empty { text-align: center; padding: 60px 16px; font-size: 16px; color: #334155; }

/* ── Paper ── */
.pages { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 16px; }
.sheet {
    position: relative;
    width: 210mm; height: 297mm;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,.2);
}
:root {
    --qr: <?= $sheet['qr'] ?>mm;
    --fs-name: <?= $sheet['name'] ?>pt; --fs-models: <?= $sheet['models'] ?>pt;
    --fs-sku: <?= $sheet['sku'] ?>pt;   --fs-brand: <?= $sheet['brand'] ?>pt;
    --k: <?= round($sheet['qr'] / 14, 3) ?>;   /* spacing grows with the QR */
}
.label {
    position: absolute;
    width: <?= $sheet['w'] ?>mm; height: <?= $sheet['h'] ?>mm;
    transform: translate(var(--dx), var(--dy));
    overflow: hidden;
}
.label.is-cut { outline: .15mm solid #777; outline-offset: -.075mm; }
@media screen { .label:not(.is-cut) { outline: .15mm dashed #cbd5e1; outline-offset: -.075mm; } }
.label.is-empty { background: repeating-linear-gradient(45deg, #f1f5f9 0 2mm, #fff 2mm 4mm); }

/* QR left, text right */
.frame {
    position: absolute; inset: 0;
    display: flex; align-items: center; gap: calc(1.2mm * var(--k));
    padding: 0 calc(1.8mm * var(--k)) 0 calc(1.4mm * var(--k));
}
.qr { flex: 0 0 var(--qr); width: var(--qr); height: var(--qr); }
.qr svg { display: block; width: 100%; height: 100%; }
.txt { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: calc(.5mm * var(--k)); }
.name {
    font-size: var(--fs-name); font-weight: 800; line-height: 1.15;
    word-break: break-word;   /* never clipped — fitLabels() shrinks it until the whole name fits */
}
/* which Apple models it fits — one line of codes, wrapping only between codes */
.codes {
    font-size: calc(var(--fs-models) * 1.12); font-weight: 800; line-height: 1.15;
    letter-spacing: .1pt;
}
/* what was in brackets: chip generation, (Used), year … */
.tags { font-size: calc(var(--fs-models) * .92); font-weight: 700; line-height: 1.1; color: #333; }
/* free-text compatible_models (no Apple codes in it) */
.models {
    font-size: var(--fs-models); font-weight: 700; line-height: 1.1;
    word-break: break-word;
}
.sku {
    font-family: 'Courier New', monospace; font-weight: 800;
    font-size: var(--fs-sku); line-height: 1; letter-spacing: -.15pt;
    white-space: nowrap; overflow: hidden; text-overflow: clip;
}
.sku.is-unit { font-size: calc(var(--fs-sku) * 1.2); letter-spacing: -.3pt; }   /* a machine's asset tag — the only thing telling two "MacBook Air A1466" apart */
.brand { font-size: var(--fs-brand); font-weight: 800; letter-spacing: .3pt; line-height: 1; color: #444; }

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
    <a href="labels.php">← กลับ</a>
    <span class="info"><?= count($items) ?> รายการ · ดวงละ <?= $copies ?> · <?= count($labels) ?> ดวง · <?= count($pages) ?> แผ่น</span>
    <form method="get" action="print_labels.php">
        <input type="hidden" name="run" value="<?= (int)$run['id'] ?>">
        <label title="ขนาดฉลาก / QR — เครื่องนี้จะจำไว้ใช้ครั้งหน้า">ขนาด
            <select name="size" id="sizeSel" onchange="this.form.submit()">
                <?php foreach ($sizes as $k => $z): ?>
                <option value="<?= $k ?>" <?= $k === $size ? 'selected' : '' ?>><?= h($z['label']) ?> · QR <?= $z['qr'] ?> มม. · <?= $z['w'] ?>×<?= $z['h'] ?> · <?= $z['cols'] * $z['rows'] ?> ดวง/แผ่น</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label title="เริ่มพิมพ์ที่ช่องที่เท่าไหร่ของแผ่นแรก (ใช้แผ่นที่เหลือจากคราวก่อน)">เริ่มช่อง <input type="number" name="start" min="1" max="<?= $per ?>" value="<?= $slot0 ?>"></label>
        <button type="submit">ใช้</button>
    </form>
    <label title="เลื่อนแนวนอน (มม.) ค่าบวก = ขวา">X <input type="number" id="dx" step="0.5" value="0"> มม.</label>
    <label title="เลื่อนแนวตั้ง (มม.) ค่าบวก = ลง">Y <input type="number" id="dy" step="0.5" value="0"> มม.</label>
    <label><input type="checkbox" id="testMode"> พิมพ์เฉพาะเส้นตัด (ทดสอบ)</label>
    <button type="button" class="primary" id="btnPrint" disabled onclick="window.print()">กำลังสร้าง QR…</button>
</div>
<p class="hint">ตั้งค่าพิมพ์: กระดาษ A4 · ขนาด <b>100% / Actual size</b> (ห้าม “Fit to page”) · ไม่มีขอบกระดาษ · ปิดหัว/ท้ายกระดาษ · แผ่นละ <?= $per ?> ดวง (<?= $sheet['w'] ?>×<?= $sheet['h'] ?> มม.)</p>

<?php if (!$items): ?>
    <p class="empty">รายการในรอบนี้ถูกลบออกจากคลังหมดแล้ว</p>
<?php else: ?>
<div class="pages">
<?php foreach ($pages as $pi => $slots): ?>
    <section class="sheet">
    <?php for ($s = 1; $s <= $per; $s++):
        $col  = ($s - 1) % $sheet['cols'];
        $row  = intdiv($s - 1, $sheet['cols']);
        $left = round($sheet['left'] + $col * $sheet['pitchX'], 2);
        $top  = round($sheet['top'] + $row * $sheet['pitchY'], 2);
        $l    = $slots[$s] ?? null;
    ?>
        <?php if ($l === null): ?>
        <div class="label<?= ($pi === 0 && $s < $slot0) ? ' is-empty' : '' ?>" style="left:<?= $left ?>mm; top:<?= $top ?>mm;"></div>
        <?php else: ?>
        <div class="label is-cut" style="left:<?= $left ?>mm; top:<?= $top ?>mm;">
            <div class="frame">
                <div class="qr" data-id="<?= (int)$l['id'] ?>"></div>
                <div class="txt">
                    <div class="brand">CMNS FIX MAC</div>
                    <?php $lp = plb_label_parts($l['name'], $l['compatible_models']); ?>
                    <div class="name"><?= str_replace('/', '/<wbr>', h($lp['title'])) ?></div>
                    <?php if ($lp['codes']): ?>
                    <div class="codes"><?= implode(' · ', array_map('h', $lp['codes'])) ?></div>
                    <?php endif; ?>
                    <?php if ($lp['tags']): ?>
                    <div class="tags"><?= implode(' · ', array_map('h', $lp['tags'])) ?></div>
                    <?php endif; ?>
                    <?php if ($lp['extra'] !== ''): ?>
                    <div class="models"><?= h($lp['extra']) ?></div>
                    <?php endif; ?>
                    <?php if ($l['type'] === 'machine'): ?>
                    <div class="sku is-unit"><?= h($l['asset_tag'] ?: $l['sku']) ?></div>
                    <?php else: ?>
                    <div class="sku"><?= h($l['sku'] ?: ($l['asset_tag'] ?: $l['part_number'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endfor; ?>
    </section>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
<!-- Own block: if the CDN ever fails, the throw stays here -->
<script>
(function () {
    var btn = document.getElementById('btnPrint');
    // this batch is logged — labels.php starts the next one with nothing picked
    try { sessionStorage.removeItem('cmns.plbPick'); } catch (e) {}

    /* ── label size, remembered per device: a URL without ?size= gets the
       last one used here (labels.php links here without it) ── */
    var SIZE_KEY = 'cmns.partLabelSize', cur = <?= json_encode($size) ?>;
    var params = new URLSearchParams(location.search);
    try {
        var want = localStorage.getItem(SIZE_KEY);
        if (!params.has('size') && want && want !== cur && document.querySelector('#sizeSel option[value="' + want + '"]')) {
            params.set('size', want);
            location.replace('?' + params.toString());
            return;
        }
        localStorage.setItem(SIZE_KEY, cur);
    } catch (e) {}
    var payload = <?= json_encode((object)$qrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    /* ── Calibration, remembered per device — separate from the job-sticker
       sheet, whose paper and grid differ ── */
    var KEY = 'cmns.partLabelOffset';
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
    /* Nothing on a label is ever clipped. The SKU / asset tag (what tells
       two same-named items apart) shrinks to fit its width; then the name
       and model line shrink together until the whole text block fits the
       label's height. Runs after the web font is in — it changes widths. */
    function fitSku(el) {
        el.style.fontSize = '';
        var w = el.clientWidth, sw = el.scrollWidth;
        if (w && sw > w) {
            var px = parseFloat(getComputedStyle(el).fontSize);
            el.style.fontSize = Math.max(px * 0.55, px * w / sw * 0.98) + 'px';
        }
    }
    function fitLabels() {
        document.querySelectorAll('.label .txt').forEach(function (txt) {
            var lab = txt.closest('.label');
            var box = lab.clientHeight - 2 * (lab.clientHeight / <?= $sheet['h'] ?>) * 0.8;   // 0.8 mm clear of each cut line
            var parts = [].slice.call(txt.querySelectorAll('.name, .codes, .tags, .models'));
            var base = parts.map(function (el) { el.style.fontSize = ''; return parseFloat(getComputedStyle(el).fontSize); });
            txt.querySelectorAll('.sku').forEach(fitSku);
            for (var f = 1; txt.scrollHeight > box && f > 0.4; f -= 0.04) {
                parts.forEach(function (el, i) { el.style.fontSize = (base[i] * f) + 'px'; });
            }
        });
    }
    window.fitLabels = fitLabels;
    function finish() {
        if (failed) { btn.textContent = 'สร้าง QR ไม่ครบ (' + failed + ')'; return; }
        document.querySelectorAll('.qr[data-id]').forEach(function (b) { b.innerHTML = svgs[b.dataset.id]; });
        var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
        ready.then(function () {
            fitLabels();
            btn.disabled = false;
            btn.textContent = 'พิมพ์';
        });
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
