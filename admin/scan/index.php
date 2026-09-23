<?php
/* =========================================================
   admin/scan/index.php — QR scanner (mobile-first)

   Opens the camera, decodes a QR, and opens what it belongs to. Two
   printed formats are routed; resolve.php does the lookup and redirect:
     - warranty slip  → /warranty/?q=<warranty_no>  (admin/warranty/print.php)
     - number sticker → CMNS:<ticket>, not a URL — only this scanner opens it  (admin/tracking/stickers.php)
     - part label     → CMNS:P-<inventory id>, opens the part sheet here      (admin/inventory/print_labels.php)
     - slot label     → CMNS:B-<storage_bins id>, opens the slot sheet here   (admin/inventory/print_bins.php)

   Anything else just shows its decoded value.

   Camera needs a secure context. https://…:8444 and http://localhost are
   both fine; http://<LAN-IP or .local> is NOT, and scan.js says so in
   plain Thai rather than failing silently.
   ========================================================= */
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
require_login();

$pageTitle = "สแกน QR";

/* resolve.php bounces back here when a scan cannot be opened. It sends the
   value it actually read so the message can name it — "ไม่พบ" on its own is
   useless when the slip is in your hand. */
$scan_err_map = [
    'empty'        => 'อ่าน QR ไม่ได้ ลองสแกนใหม่อีกครั้ง',
    'format'       => 'QR นี้ไม่ใช่ใบประกัน สติ๊กเกอร์งานซ่อม ฉลากอะไหล่ หรือฉลากช่องเก็บของของร้าน',
    'notfound'     => 'ไม่พบใบประกันนี้ในระบบ',
    'ticket_unused'   => 'เลขที่ซ่อมนี้ยังไม่ถูกใช้งาน',
    'ticket_notfound' => 'ไม่พบเลขที่ซ่อมนี้ในระบบ',
];
$scan_err_key = $_GET['err'] ?? '';
$scan_err     = $scan_err_map[$scan_err_key] ?? '';
$scan_err_raw = trim((string)($_GET['raw'] ?? ''));

/* An unused sticker number is the start of a job, not a dead end: offer to
   open one with the number already filled in. */
$scan_new_ticket = '';
if ($scan_err_key === 'ticket_unused' && preg_match('/^t=(V\d{1,7})$/', $scan_err_raw, $m)) {
    $scan_new_ticket = $m[1];
}

include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="<?= $assets_base ?>css/scan.css?v=<?= asset_ver('/admin/templates/assets/css/scan.css') ?>">
<link rel="stylesheet" href="/admin/tracking/assets/css/job-view.css?v=<?= asset_ver('/admin/tracking/assets/css/job-view.css') ?>">
<?php if (can('parts.consume')): ?>
<link rel="stylesheet" href="<?= $assets_base ?>css/modal.css?v=<?= asset_ver('/admin/templates/assets/css/modal.css') ?>">
<?php endif; ?>

<div class="scan-page">

<?php if ($scan_err !== ''): ?>
    <div class="scan-alert<?= $scan_new_ticket !== '' ? ' is-info' : '' ?>" role="alert">
        <span class="material-symbols-rounded"><?= $scan_new_ticket !== '' ? 'new_label' : 'error' ?></span>
        <div>
            <strong><?= htmlspecialchars($scan_err, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if ($scan_new_ticket !== ''): ?>
                <code><?= htmlspecialchars($scan_new_ticket, ENT_QUOTES, 'UTF-8') ?></code>
                <?php if (can('jobs.write')): ?>
                <a class="scan-alert-cta" href="/admin/tracking/create.php?ticket=<?= urlencode($scan_new_ticket) ?>">
                    <span class="material-symbols-rounded">add_task</span> เปิดงานใหม่ด้วยเลขนี้
                </a>
                <?php endif; ?>
            <?php elseif ($scan_err_raw !== ''): ?>
                <code><?= htmlspecialchars($scan_err_raw, ENT_QUOTES, 'UTF-8') ?></code>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

    <?php /* data-last-fail stops the scanner re-routing the very QR that just
             bounced: the slip is usually still in frame when this page comes
             back, which would otherwise loop resolve.php forever. */ ?>
    <div class="scan-stage" id="scanStage" data-last-fail="<?= htmlspecialchars($scan_err !== '' ? $scan_err_raw : '', ENT_QUOTES, 'UTF-8') ?>">
        <video id="scanVideo" playsinline muted autoplay></video>

        <!-- "Scan to add" mode (slot sheet → สแกนใส่): every item label read goes into this slot -->
        <div class="scan-addbar" id="scanAddBar" hidden>
            <span class="material-symbols-rounded">move_to_inbox</span>
            <span class="scan-addbar-txt">ใส่ของเข้า <b id="scanAddCode"></b><small id="scanAddCount"></small></span>
            <button type="button" class="scan-addbar-done" id="scanAddDone">เสร็จ</button>
        </div>

        <!-- Reticle: four corner brackets over a dimmed surround -->
        <div class="scan-reticle" aria-hidden="true">
            <span class="c tl"></span><span class="c tr"></span>
            <span class="c bl"></span><span class="c br"></span>
            <span class="scan-laser"></span>
        </div>

        <!-- Covers the stage until the camera is live, or when it fails -->
        <div class="scan-cover" id="scanCover">
            <span class="material-symbols-rounded scan-cover-ico" id="scanCoverIco">photo_camera</span>
            <p class="scan-cover-msg" id="scanCoverMsg">กำลังเปิดกล้อง…</p>
            <button type="button" class="scan-btn scan-btn-ghost" id="scanRetry" hidden>ลองใหม่</button>
        </div>
    </div>

    <p class="scan-hint" id="scanHint">เล็ง QR ให้อยู่ในกรอบ</p>

    <!-- Result panel — appears only after a successful decode -->
    <div class="scan-result" id="scanResult" hidden>
        <div class="scan-result-head">
            <span class="material-symbols-rounded">check_circle</span>
            <span>อ่านได้แล้ว</span>
        </div>
        <pre class="scan-result-value" id="scanValue"></pre>
        <div class="scan-result-actions">
            <button type="button" class="scan-btn scan-btn-primary" id="scanAgain">สแกนอีกครั้ง</button>
            <button type="button" class="scan-btn scan-btn-ghost" id="scanCopy">คัดลอก</button>
        </div>
        <p class="scan-note" id="scanNote">QR ใบประกัน สติ๊กเกอร์งานซ่อม ฉลากอะไหล่ และฉลากช่องเก็บของจะเปิดให้อัตโนมัติ — ที่เห็นค่านี้แปลว่าอ่านได้แต่ไม่ใช่ของร้าน</p>
    </div>

</div>

<?php /* A scanned sticker opens this sheet over the camera — no page load, so
         iOS does not ask for camera permission again between scans. */
      include __DIR__ . '/../tracking/partials/job_view_sheet.php'; ?>

<!-- A scanned part label opens this sheet the same way (filled by part-view.js) -->
<div id="partModal" class="trk-modal-overlay">
    <div class="trk-view sheet-on-mobile">
        <header class="trk-view-hd">
            <div class="trk-view-hd-l">
                <span class="trk-view-ticket pv-sku" id="pv-sku"></span>
                <span class="status-badge" id="pv-stock"></span>
            </div>
            <button type="button" class="trk-view-close" data-pv-close aria-label="ปิด">
                <span class="material-symbols-rounded">close</span>
            </button>
        </header>
        <div class="trk-view-body">
            <div class="pv-top">
                <div class="pv-img" id="pv-img"></div>
                <div class="pv-top-txt">
                    <div class="pv-name" id="pv-name"></div>
                    <div class="pv-name-th" id="pv-name-th"></div>
                    <div class="pv-cat" id="pv-cat"></div>
                </div>
            </div>
            <div class="trk-view-meta">
                <div><label id="pv-qty-lbl">คงเหลือ</label><b id="pv-qty"></b></div>
                <div><label>ราคาขาย</label><b id="pv-price" class="trk-view-cost"></b></div>
                <div><label>ที่เก็บ</label><b id="pv-loc"></b></div>
                <div><label id="pv-pn-lbl">Part No.</label><b id="pv-pn"></b></div>
            </div>
            <section class="trk-view-sec" id="pv-sec-compat">
                <label>ใช้กับรุ่น</label>
                <div class="trk-view-tags" id="pv-compat"></div>
            </section>
            <section class="trk-view-sec" id="pv-sec-lots">
                <label>ล็อตที่มีของ</label>
                <div id="pv-lots"></div>
            </section>
        </div>
        <footer class="trk-view-ft">
            <a class="trk-view-cancel pv-open" id="pv-open" href="#">เปิดในคลัง</a>
            <a class="trk-view-cancel pv-open pv-act" id="pv-edit" href="#" hidden>
                <span class="material-symbols-rounded">edit</span> แก้ไข
            </a>
            <a class="trk-view-cancel pv-open pv-act" id="pv-strip" href="#" hidden>
                <span class="material-symbols-rounded">content_cut</span> แยกอะไหล่
            </a>
            <?php if (can('parts.consume')): ?>
            <button type="button" class="trk-view-editbtn pv-take" id="pv-take" hidden>
                <span class="material-symbols-rounded">output</span> <span id="pv-take-txt">เบิกเข้างาน</span>
            </button>
            <?php endif; ?>
        </footer>
    </div>
</div>
<?php if (can('parts.consume')) include __DIR__ . '/../inventory/partials/_modal_requisition.php'; ?>

<!-- "Scan to add": each scanned item shows here first, and goes in only on confirm (scan.js) -->
<div id="addModal" class="trk-modal-overlay">
    <div class="trk-view sheet-on-mobile">
        <header class="trk-view-hd">
            <div class="trk-view-hd-l">
                <span class="trk-view-ticket ac-tag" id="ac-tag"></span>
                <span class="status-badge st-blue" id="ac-kind"></span>
            </div>
            <button type="button" class="trk-view-close" data-ac-close aria-label="ข้าม">
                <span class="material-symbols-rounded">close</span>
            </button>
        </header>
        <div class="trk-view-body">
            <div class="ac-name" id="ac-name"></div>
            <div class="trk-view-meta">
                <div><label>Serial</label><b id="ac-serial"></b></div>
                <div><label>สถานะ</label><b id="ac-status"></b></div>
                <div><label>ตอนนี้อยู่</label><b id="ac-from"></b></div>
                <div><label>จะใส่เข้า</label><b id="ac-to" class="ac-to"></b></div>
            </div>
            <p class="ac-msg" id="ac-msg" hidden></p>
        </div>
        <footer class="trk-view-ft">
            <button type="button" class="trk-view-cancel bv-btn" data-ac-close>ข้าม</button>
            <button type="button" class="trk-view-editbtn ac-go" id="ac-go">
                <span class="material-symbols-rounded">move_to_inbox</span> <span id="ac-go-txt">ใส่</span>
            </button>
        </footer>
    </div>
</div>

<!-- A scanned slot label (box on a shelf) opens this sheet (filled by bin-view.js) -->
<div id="binModal" class="trk-modal-overlay">
    <div class="trk-view sheet-on-mobile">
        <header class="trk-view-hd">
            <div class="trk-view-hd-l">
                <span class="trk-view-ticket bv-code" id="bv-code"></span>
                <span class="status-badge" id="bv-kind"></span>
            </div>
            <button type="button" class="trk-view-close" data-bv-close aria-label="ปิด">
                <span class="material-symbols-rounded">close</span>
            </button>
        </header>
        <div class="trk-view-body">
            <p class="bv-where" id="bv-where"></p>
            <section class="trk-view-sec">
                <label id="bv-count"></label>
                <div id="bv-items"></div>
            </section>
            <section class="trk-view-sec" id="bv-find" hidden>
                <label>ใส่ของเข้าช่องนี้</label>
                <input type="search" class="bv-q" id="bv-q" placeholder="ชื่อ, asset tag, serial" autocomplete="off" enterkeyhint="search">
                <div id="bv-results"></div>
            </section>
        </div>
        <footer class="trk-view-ft">
            <a class="trk-view-cancel bv-open" id="bv-open" href="#">หน้าช่อง</a>
            <button type="button" class="trk-view-cancel bv-btn" id="bv-add" hidden>
                <span class="material-symbols-rounded">search</span> ค้นใส่
            </button>
            <button type="button" class="trk-view-editbtn bv-scanadd" id="bv-scanadd" hidden>
                <span class="material-symbols-rounded">qr_code_scanner</span> สแกนใส่
            </button>
        </footer>
    </div>
</div>

<!-- zxing-wasm, jsQR fallback: iOS Safari has no BarcodeDetector -->
<script src="https://cdn.jsdelivr.net/npm/zxing-wasm@3.1.4/dist/iife/reader/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script src="/admin/tracking/assets/js/job-view.js?v=<?= asset_ver('/admin/tracking/assets/js/job-view.js') ?>"></script>
<script src="<?= $assets_base ?>js/part-view.js?v=<?= asset_ver('/admin/templates/assets/js/part-view.js') ?>"></script>
<script src="<?= $assets_base ?>js/bin-view.js?v=<?= asset_ver('/admin/templates/assets/js/bin-view.js') ?>"></script>
<?php if (can('parts.consume')): ?>
<script src="/admin/inventory/assets/js/inventory-requisition.js?v=<?= asset_ver('/admin/inventory/assets/js/inventory-requisition.js') ?>"></script>
<?php endif; ?>
<script src="<?= $assets_base ?>js/scan.js?v=<?= asset_ver('/admin/templates/assets/js/scan.js') ?>"></script>

<?php include '../templates/footer_admin.php'; ?>
