<?php
/* =========================================================
   admin/scan/index.php — QR scanner (mobile-first)

   Opens the camera, decodes a QR, and opens the warranty it belongs to.
   The QRs this system prints encode `/warranty/?q=<warranty_no>` (see
   admin/warranty/print.php); resolve.php does the lookup and redirect.

   Anything that is not a warranty still just shows its decoded value —
   no other format is routed yet.

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
    'empty'    => 'อ่าน QR ไม่ได้ ลองสแกนใหม่อีกครั้ง',
    'format'   => 'QR นี้ไม่ใช่ใบประกันของร้าน',
    'notfound' => 'ไม่พบใบประกันนี้ในระบบ',
];
$scan_err_key = $_GET['err'] ?? '';
$scan_err     = $scan_err_map[$scan_err_key] ?? '';
$scan_err_raw = trim((string)($_GET['raw'] ?? ''));

include '../templates/header_admin.php';
?>

<link rel="stylesheet" href="<?= $assets_base ?>css/scan.css?v=<?= asset_ver('/admin/templates/assets/css/scan.css') ?>">

<div class="scan-page">

<?php if ($scan_err !== ''): ?>
    <div class="scan-alert" role="alert">
        <span class="material-symbols-rounded">error</span>
        <div>
            <strong><?= htmlspecialchars($scan_err, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if ($scan_err_raw !== ''): ?>
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
        <p class="scan-note" id="scanNote">QR ใบประกันจะเปิดใบนั้นให้อัตโนมัติ — ที่เห็นค่านี้แปลว่าอ่านได้แต่ไม่ใช่ใบประกัน</p>
    </div>

</div>

<!-- jsQR: iOS Safari has no BarcodeDetector, so native decoding is not an option -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script src="<?= $assets_base ?>js/scan.js?v=<?= asset_ver('/admin/templates/assets/js/scan.js') ?>"></script>

<?php include '../templates/footer_admin.php'; ?>
